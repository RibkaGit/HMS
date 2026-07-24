<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - BILLING MANAGEMENT
// ============================================================================

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$userName = $_SESSION['full_name'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'Staff';
$userInitial = strtoupper(substr($userName, 0, 1));

$message = '';
$error = '';

// Get visits without invoices
$unbilledVisits = $conn->query("
    SELECT v.visit_id, v.visit_code, 
           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
           p.patient_code,
           vt.name as visit_type,
           v.admitted_at
    FROM visits v
    JOIN patients p ON v.patient_id = p.patient_id
    JOIN lookup_visit_types vt ON v.visit_type_id = vt.visit_type_id
    LEFT JOIN invoices i ON v.visit_id = i.visit_id
    WHERE i.invoice_id IS NULL 
    AND v.visit_status_id != (SELECT visit_status_id FROM lookup_visit_statuses WHERE name = 'Cancelled')
    ORDER BY v.admitted_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Get invoice statuses
$invoiceStatuses = $conn->query("SELECT * FROM lookup_invoice_statuses")->fetch_all(MYSQLI_ASSOC);
$paymentMethods = $conn->query("SELECT * FROM lookup_payment_methods")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// CREATE INVOICE
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_invoice') {
    $visitId = intval($_POST['visit_id']);
    
    // Get visit details
    $visitQuery = "SELECT v.*, p.first_name, p.last_name 
                   FROM visits v 
                   JOIN patients p ON v.patient_id = p.patient_id 
                   WHERE v.visit_id = ?";
    $stmt = $conn->prepare($visitQuery);
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    $visit = $stmt->get_result()->fetch_assoc();
    
    if (!$visit) {
        $error = 'Visit not found.';
    } else {
        // Calculate total from items
        $items = [];
        $total = 0;
        
        // Consultation fee
        $consultationFee = 50.00;
        $items[] = [
            'description' => 'Consultation Fee',
            'item_type' => 'Consultation',
            'quantity' => 1,
            'unit_price' => $consultationFee,
            'line_total' => $consultationFee
        ];
        $total += $consultationFee;
        
        // Check if there are lab tests
        $labQuery = "SELECT COUNT(*) as count FROM lab_orders WHERE visit_id = ?";
        $labStmt = $conn->prepare($labQuery);
        $labStmt->bind_param('i', $visitId);
        $labStmt->execute();
        $labResult = $labStmt->get_result();
        $labCount = $labResult->fetch_assoc()['count'];
        
        if ($labCount > 0) {
            $labFee = 25.00 * $labCount;
            $items[] = [
                'description' => "Lab Tests ($labCount tests)",
                'item_type' => 'Test',
                'quantity' => $labCount,
                'unit_price' => 25.00,
                'line_total' => $labFee
            ];
            $total += $labFee;
        }
        
        // Create invoice
        $invoiceCode = generateInvoiceCode($conn);
        $statusId = getLookupId($conn, 'lookup_invoice_statuses', 'name', 'Unpaid');
        
        $query = "INSERT INTO invoices (invoice_code, visit_id, patient_id, invoice_status_id, subtotal, total) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('siiddd', 
            $invoiceCode,
            $visitId,
            $visit['patient_id'],
            $statusId,
            $total,
            $total
        );
        
        if ($stmt->execute()) {
            $invoiceId = $conn->insert_id;
            
            // Add invoice items
            foreach ($items as $item) {
                $query = "INSERT INTO invoice_items (invoice_id, description, item_type, quantity, unit_price, line_total) 
                          VALUES (?, ?, ?, ?, ?, ?)";
                $itemStmt = $conn->prepare($query);
                $itemStmt->bind_param('issidd',
                    $invoiceId,
                    $item['description'],
                    $item['item_type'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['line_total']
                );
                $itemStmt->execute();
            }
            
            logUserActivity($conn, $_SESSION['user_id'], 'Created Invoice', "Created invoice: {$invoiceCode}");
            $message = 'Invoice created successfully!';
            header('Location: billing.php?message=' . urlencode($message));
            exit();
        } else {
            $error = 'Failed to create invoice. Please try again.';
        }
    }
}

// ============================================================================
// PROCESS PAYMENT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    $invoiceId = intval($_POST['invoice_id']);
    $amount = floatval($_POST['amount']);
    $paymentMethodId = intval($_POST['payment_method_id']);
    
    // Get invoice details
    $invoiceQuery = "SELECT * FROM invoices WHERE invoice_id = ?";
    $stmt = $conn->prepare($invoiceQuery);
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    
    if (!$invoice) {
        $error = 'Invoice not found.';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid payment amount.';
    } elseif ($amount > $invoice['total']) {
        $error = 'Payment amount cannot exceed invoice total.';
    } else {
        // Add payment
        $receivedBy = intval($_SESSION['user_id']);
        $query = "INSERT INTO payments (invoice_id, payment_method_id, amount, received_by) 
                  VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iidi', $invoiceId, $paymentMethodId, $amount, $receivedBy);
        
        if ($stmt->execute()) {
            // Update invoice status
            $paidAmount = $amount;
            
            // Get total paid
            $paidQuery = "SELECT SUM(amount) as total_paid FROM payments WHERE invoice_id = ?";
            $paidStmt = $conn->prepare($paidQuery);
            $paidStmt->bind_param('i', $invoiceId);
            $paidStmt->execute();
            $paidResult = $paidStmt->get_result();
            $totalPaid = $paidResult->fetch_assoc()['total_paid'] ?? 0;
            
            $newStatus = 'Partially Paid';
            if ($totalPaid >= $invoice['total']) {
                $newStatus = 'Paid';
            }
            
            $statusId = getLookupId($conn, 'lookup_invoice_statuses', 'name', $newStatus);
            $updateQuery = "UPDATE invoices SET invoice_status_id = ? WHERE invoice_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param('ii', $statusId, $invoiceId);
            $updateStmt->execute();

            if ($newStatus === 'Paid') {
                updateVisitStatus($conn, $invoice['visit_id'], 'Registered');
            }
            
            logUserActivity($conn, $_SESSION['user_id'], 'Processed Payment', "Payment of \${$amount} for invoice ID: {$invoiceId}");
            $message = 'Payment processed successfully!';
            $nextPage = $newStatus === 'Paid' ? 'visits.php' : 'billing.php';
            header('Location: ' . $nextPage . '?message=' . urlencode($message));
            exit();
        } else {
            $error = 'Failed to process payment. Please try again.';
        }
    }
}

// ============================================================================
// GET INVOICES
// ============================================================================
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

$query = "SELECT i.*, 
          CONCAT(p.first_name, ' ', p.last_name) as patient_name,
          p.patient_code,
          v.visit_code,
          vs.name as visit_status,
          is_l.name as status_name,
          (SELECT SUM(amount) FROM payments WHERE invoice_id = i.invoice_id) as paid_amount
          FROM invoices i
          JOIN patients p ON i.patient_id = p.patient_id
          JOIN visits v ON i.visit_id = v.visit_id
          JOIN lookup_visit_statuses vs ON v.visit_status_id = vs.visit_status_id
          JOIN lookup_invoice_statuses is_l ON i.invoice_status_id = is_l.invoice_status_id
          WHERE 1=1";

$params = [];
$types = "";

if ($searchTerm) {
    $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR i.invoice_code LIKE ?)";
    $searchTermLike = "%{$searchTerm}%";
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $types .= "sss";
}

if ($filterStatus && $filterStatus !== 'All') {
    $query .= " AND is_l.name = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$query .= " ORDER BY i.created_at DESC LIMIT 50";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get invoice items
$invoiceItems = [];
if (!empty($invoices)) {
    $invoiceIds = array_column($invoices, 'invoice_id');
    $ids = implode(',', $invoiceIds);
    $itemQuery = "SELECT * FROM invoice_items WHERE invoice_id IN ($ids)";
    $itemResult = $conn->query($itemQuery);
    while ($row = $itemResult->fetch_assoc()) {
        $invoiceItems[$row['invoice_id']][] = $row;
    }
}

// Get payments
$invoicePayments = [];
if (!empty($invoices)) {
    $ids = implode(',', $invoiceIds);
    $paymentQuery = "SELECT p.*, pm.name as method_name 
                     FROM payments p
                     JOIN lookup_payment_methods pm ON p.payment_method_id = pm.payment_method_id
                     WHERE p.invoice_id IN ($ids)
                     ORDER BY p.paid_at DESC";
    $paymentResult = $conn->query($paymentQuery);
    while ($row = $paymentResult->fetch_assoc()) {
        $invoicePayments[$row['invoice_id']][] = $row;
    }
}

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .form-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: <?php echo (isset($_GET['action']) && ($_GET['action'] === 'create_invoice' || $_GET['action'] === 'pay')) ? 'flex' : 'none'; ?>;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }
        .form-modal-content {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .form-modal-content .close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 24px;
            color: #94a3b8;
            cursor: pointer;
        }
        .form-modal-content .close-btn:hover {
            color: #ef4444;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 4px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2563eb;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-submit:hover {
            background: #1d4ed8;
        }
        .btn-cancel {
            padding: 12px 24px;
            background: #f1f5f9;
            color: #475569;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
        }
        .btn-cancel:hover {
            background: #e2e8f0;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }
        .btn-group .btn-submit {
            flex: 1;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .action-buttons a, .action-buttons button {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-pay {
            background: #d1fae5;
            color: #059669;
        }
        .btn-pay:hover {
            background: #a7f3d0;
        }
        .btn-create {
            padding: 10px 20px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-create:hover {
            background: #1d4ed8;
        }
        .search-box-billing {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-box-billing input, .search-box-billing select {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .search-box-billing input:focus, .search-box-billing select:focus {
            outline: none;
            border-color: #2563eb;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .status-badge-billing {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-unpaid { background: #fee2e2; color: #dc2626; }
        .status-partiallypaid { background: #fef3c7; color: #d97706; }
        .status-paid { background: #d1fae5; color: #059669; }
        .status-void { background: #f1f5f9; color: #64748b; }
        
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .filter-tab {
            padding: 6px 16px;
            border-radius: 9999px;
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .filter-tab:hover {
            background: #e2e8f0;
        }
        .filter-tab.active {
            background: #2563eb;
            color: #fff;
        }
        .invoice-summary {
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            margin: 8px 0;
        }
        .invoice-summary .total {
            font-size: 18px;
            font-weight: 700;
            color: #2563eb;
        }
        .invoice-summary .paid {
            color: #059669;
        }
        .invoice-summary .balance {
            color: #dc2626;
        }
        .payment-history {
            margin-top: 8px;
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 4px;
            font-size: 13px;
        }
        .payment-history .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .payment-history .payment-item:last-child {
            border-bottom: none;
        }
        .invoice-items-list {
            margin: 4px 0;
        }
        .invoice-items-list .item-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 2px 0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <h1>Billing Management</h1>
                </div>
                <div class="top-bar-right">
                    <div class="date-display">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('F j, Y'); ?></span>
                    </div>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                <div class="search-box-billing">
                    <form method="GET" action="" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <input type="text" name="search" placeholder="Search invoices..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="width: 200px;">
                        <select name="status">
                            <option value="All">All Status</option>
                            <?php foreach ($invoiceStatuses as $status): ?>
                                <option value="<?php echo $status['name']; ?>" <?php echo $filterStatus === $status['name'] ? 'selected' : ''; ?>>
                                    <?php echo $status['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($searchTerm || $filterStatus): ?>
                            <a href="billing.php" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <button class="btn-create" onclick="window.location.href='billing.php?action=create_invoice'">
                    <i class="fas fa-plus"></i> Create Invoice
                </button>
            </div>

            <!-- Status Filter Tabs -->
            <div class="filter-tabs">
                <a href="billing.php" class="filter-tab <?php echo !$filterStatus ? 'active' : ''; ?>">All</a>
                <a href="billing.php?status=Unpaid" class="filter-tab <?php echo $filterStatus === 'Unpaid' ? 'active' : ''; ?>">Unpaid</a>
                <a href="billing.php?status=Partially%20Paid" class="filter-tab <?php echo $filterStatus === 'Partially Paid' ? 'active' : ''; ?>">Partially Paid</a>
                <a href="billing.php?status=Paid" class="filter-tab <?php echo $filterStatus === 'Paid' ? 'active' : ''; ?>">Paid</a>
                <a href="billing.php?status=Void" class="filter-tab <?php echo $filterStatus === 'Void' ? 'active' : ''; ?>">Void</a>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient</th>
                                <th>Visit</th>
                                <th>Visit Status</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="11" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-file-invoice" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        No invoices found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($invoice['invoice_code']); ?></strong></td>
                                    <td>
                                        <div class="patient-info">
                                            <div class="avatar" style="background: <?php echo getUserColor($invoice['patient_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo strtoupper(substr($invoice['patient_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="patient-name"><?php echo htmlspecialchars($invoice['patient_name']); ?></span>
                                                <small><?php echo htmlspecialchars($invoice['patient_code']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($invoice['visit_code']); ?></td>
                                    <td>
                                        <span class="status-badge-billing status-<?php echo strtolower(str_replace(' ', '', $invoice['visit_status'])); ?>">
                                            <?php echo htmlspecialchars($invoice['visit_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="invoice-items-list">
                                            <?php if (isset($invoiceItems[$invoice['invoice_id']])): ?>
                                                <?php foreach ($invoiceItems[$invoice['invoice_id']] as $item): ?>
                                                    <div class="item-row">
                                                        <span><?php echo htmlspecialchars($item['description']); ?></span>
                                                        <span>Birr <?php echo number_format($item['line_total'], 2); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><strong>Birr <?php echo number_format($invoice['total'], 2); ?></strong></td>
                                    <td>Birr <?php echo number_format($invoice['paid_amount'] ?? 0, 2); ?></td>
                                    <td>
                                        <strong class="balance">
                                            Birr <?php echo number_format($invoice['total'] - ($invoice['paid_amount'] ?? 0), 2); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="status-badge-billing status-<?php echo strtolower(str_replace(' ', '', $invoice['status_name'])); ?>">
                                            <?php echo htmlspecialchars($invoice['status_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($invoice['status_name'] !== 'Paid' && $invoice['status_name'] !== 'Void'): ?>
                                                <a href="billing.php?action=pay&id=<?php echo $invoice['invoice_id']; ?>" class="btn-pay">
                                                    <i class="fas fa-hand-holding-usd"></i> Pay
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($invoicePayments[$invoice['invoice_id']])): ?>
                                                <div class="payment-history">
                                                    <?php foreach ($invoicePayments[$invoice['invoice_id']] as $payment): ?>
                                                        <div class="payment-item">
                                                            <span>Birr <?php echo number_format($payment['amount'], 2); ?></span>
                                                            <span><?php echo htmlspecialchars($payment['method_name']); ?></span>
                                                            <span><?php echo date('M d, Y', strtotime($payment['paid_at'])); ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Invoice Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'create_invoice'): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='billing.php'">&times;</button>
            <h2 style="margin-bottom: 24px;">Create New Invoice</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_invoice">
                
                <div class="form-group">
                    <label for="visit_id">Select Visit *</label>
                    <select id="visit_id" name="visit_id" required>
                        <option value="">Select Visit</option>
                        <?php foreach ($unbilledVisits as $visit): ?>
                            <option value="<?php echo $visit['visit_id']; ?>">
                                <?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['patient_name'] . ' (' . $visit['visit_type'] . ')'); ?>
                                - <?php echo date('M d, Y', strtotime($visit['admitted_at'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                    <p style="font-size: 14px; color: #64748b;">
                        <i class="fas fa-info-circle"></i> 
                        The invoice will include consultation fee and any lab tests associated with this visit.
                    </p>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-file-invoice"></i> Create Invoice
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='billing.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payment Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'pay' && isset($_GET['id'])): 
        $invoiceId = intval($_GET['id']);
        $invoiceQuery = "SELECT i.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name 
                         FROM invoices i
                         JOIN patients p ON i.patient_id = p.patient_id
                         WHERE i.invoice_id = ?";
        $stmt = $conn->prepare($invoiceQuery);
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        $payInvoice = $stmt->get_result()->fetch_assoc();
        
        if ($payInvoice):
            $paidQuery = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ?";
            $paidStmt = $conn->prepare($paidQuery);
            $paidStmt->bind_param('i', $invoiceId);
            $paidStmt->execute();
            $paidResult = $paidStmt->get_result();
            $totalPaid = $paidResult->fetch_assoc()['total_paid'];
            $balance = $payInvoice['total'] - $totalPaid;
    ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='billing.php'">&times;</button>
            <h2 style="margin-bottom: 24px;">Process Payment</h2>
            
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                <p><strong>Invoice:</strong> <?php echo htmlspecialchars($payInvoice['invoice_code']); ?></p>
                <p><strong>Patient:</strong> <?php echo htmlspecialchars($payInvoice['patient_name']); ?></p>
                <p><strong>Total:</strong> Birr <?php echo number_format($payInvoice['total'], 2); ?></p>
                <p><strong>Paid:</strong> Birr <?php echo number_format($totalPaid, 2); ?></p>
                <p><strong>Balance:</strong> <strong class="balance">Birr <?php echo number_format($balance, 2); ?></strong></p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="invoice_id" value="<?php echo $invoiceId; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Payment Amount ($) *</label>
                        <input type="number" id="amount" name="amount" step="0.01" required 
                               max="<?php echo $balance; ?>" value="<?php echo min($balance, 100); ?>">
                    </div>
                    <div class="form-group">
                        <label for="payment_method_id">Payment Method *</label>
                        <select id="payment_method_id" name="payment_method_id" required>
                            <option value="">Select Method</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?php echo $method['payment_method_id']; ?>">
                                    <?php echo $method['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-check"></i> Process Payment
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='billing.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; endif; ?>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>