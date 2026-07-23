<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - RADIOLOGY MANAGEMENT
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
$selectedVisitId = isset($_GET['visit_id']) ? intval($_GET['visit_id']) : 0;

// Get visits for dropdown
$visits = $conn->query("SELECT v.visit_id, v.visit_code, p.first_name, p.last_name 
                        FROM visits v 
                        JOIN patients p ON v.patient_id = p.patient_id 
                        ORDER BY v.admitted_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);

// Get test types of category 'Radiology'
$testTypes = $conn->query("SELECT * FROM lookup_test_types WHERE is_active = 1 AND category = 'Radiology'")->fetch_all(MYSQLI_ASSOC);

// Get order statuses
$orderStatuses = $conn->query("SELECT * FROM lookup_order_statuses")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// CREATE RADIOLOGY ORDER
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $visitId = intval($_POST['visit_id']);
    $testTypeIds = array_map('intval', $_POST['test_type_ids'] ?? []);
    
    // Get the attending doctor from the visit
    $visitQuery = "SELECT attending_doctor_id FROM visits WHERE visit_id = ?";
    $visitStmt = $conn->prepare($visitQuery);
    $visitStmt->bind_param('i', $visitId);
    $visitStmt->execute();
    $visit = $visitStmt->get_result()->fetch_assoc();
    $orderedBy = $visit['attending_doctor_id'] ? intval($visit['attending_doctor_id']) : intval($_SESSION['user_id']);
    
    $orderStatusId = getLookupId($conn, 'lookup_order_statuses', 'name', 'Ordered');
    $createdCount = 0;
    
    foreach ($testTypeIds as $testTypeId) {
        $data = [
            'visit_id' => $visitId,
            'test_type_id' => $testTypeId,
            'ordered_by' => $orderedBy,
            'order_status_id' => $orderStatusId
        ];
        // Reuse lab order function as it writes to lab_orders which stores both Lab and Radiology
        if (createLabOrder($conn, $data)) {
            $createdCount++;
        }
    }
    
    if ($createdCount > 0) {
        $chargeQuery = $conn->prepare("SELECT name, price FROM lookup_test_types WHERE test_type_id = ?");
        foreach ($testTypeIds as $testTypeId) {
            $chargeQuery->bind_param('i', $testTypeId);
            $chargeQuery->execute();
            $test = $chargeQuery->get_result()->fetch_assoc();
            if ($test) {
                addInvoiceCharge($conn, $visitId, 'Radiology: ' . $test['name'], 'Procedure', 1, (float) $test['price'] > 0 ? (float) $test['price'] : 150.00);
            }
        }
        
        // Update visit status and clear radiology flag in medical records
        updateVisitStatus($conn, $visitId, 'Awaiting Results');
        $clearRadiologyFlag = $conn->prepare("UPDATE medical_records SET needs_radiology = 0 WHERE visit_id = ?");
        $clearRadiologyFlag->bind_param('i', $visitId);
        $clearRadiologyFlag->execute();
        
        logUserActivity($conn, $_SESSION['user_id'], 'Created Radiology Orders', "Created {$createdCount} radiology order(s) for visit ID: {$visitId}");
        
        if (isset($_POST['next']) && $_POST['next'] === 'bed') {
            header('Location: bed_management.php?action=assign_bed&visit_id=' . $visitId);
            exit();
        }
        $message = 'Radiology order created successfully!';
        header('Location: radiology.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Select at least one radiology test and try again.';
    }
}

// ============================================================================
// UPDATE RADIOLOGY ORDER STATUS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId = intval($_POST['order_id']);
    $statusId = intval($_POST['status_id']);
    
    $query = "UPDATE lab_orders SET order_status_id = ? WHERE order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $statusId, $orderId);
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Radiology Order', "Updated radiology order ID: {$orderId}");
        $message = 'Radiology order status updated successfully!';
        header('Location: radiology.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update radiology order. Please try again.';
    }
}

// ============================================================================
// ADD RADIOLOGY RESULT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_result') {
    $resultData = [
        'entered_by' => $_SESSION['user_id'],
        'result_value' => $_POST['result_value'],
        'result_notes' => sanitizeInput($_POST['result_notes'] ?? '')
    ];
    
    // Reuse lab result function as it writes to lab_results
    $result = updateLabResult($conn, intval($_POST['order_id']), $resultData);
    if ($result) {
        $visitStmt = $conn->prepare("SELECT visit_id FROM lab_orders WHERE order_id = ?");
        $visitStmt->bind_param('i', $_POST['order_id']);
        $visitStmt->execute();
        $visitRow = $visitStmt->get_result()->fetch_assoc();
        if ($visitRow) {
            updateVisitStatus($conn, $visitRow['visit_id'], 'Awaiting Billing');
        }
        logUserActivity($conn, $_SESSION['user_id'], 'Added Radiology Result', "Added radiology result for order ID: {$_POST['order_id']}");
        $message = 'Radiology result added successfully!';
        header('Location: radiology.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to add radiology result. Please try again.';
    }
}

// ============================================================================
// GET RADIOLOGY ORDERS
// ============================================================================
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

$query = "SELECT lo.*, 
          vt.name as test_name, vt.category,
          os.name as status_name,
          CONCAT(s.first_name, ' ', s.last_name) as ordered_by_name,
          CONCAT(p.first_name, ' ', p.last_name) as patient_name,
          p.patient_code,
          v.visit_code
          FROM lab_orders lo
          JOIN lookup_test_types vt ON lo.test_type_id = vt.test_type_id
          JOIN lookup_order_statuses os ON lo.order_status_id = os.order_status_id
          JOIN staff s ON lo.ordered_by = s.staff_id
          JOIN visits v ON lo.visit_id = v.visit_id
          JOIN patients p ON v.patient_id = p.patient_id
          WHERE vt.category = 'Radiology'";

$params = [];
$types = "";

if ($searchTerm) {
    $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR v.visit_code LIKE ?)";
    $searchTermLike = "%{$searchTerm}%";
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $types .= "sss";
}

if ($filterStatus && $filterStatus !== 'All') {
    $query .= " AND os.name = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$query .= " ORDER BY lo.ordered_at DESC LIMIT 50";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$radiologyOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Patients referred from medical records (needs radiology)
$pendingRadiologyQuery = "SELECT mr.record_id, mr.visit_id, mr.diagnosis, mr.created_at,
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           p.patient_code,
                           v.visit_code,
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                    FROM medical_records mr
                    JOIN patients p ON mr.patient_id = p.patient_id
                    JOIN visits v ON mr.visit_id = v.visit_id
                    JOIN staff d ON mr.doctor_id = d.staff_id
                    WHERE mr.needs_radiology = 1
                    ORDER BY mr.created_at DESC";
$pendingRadiologyPatients = $conn->query($pendingRadiologyQuery)->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Get radiology results for orders
$radiologyResults = [];
if (!empty($radiologyOrders)) {
    $orderIds = array_column($radiologyOrders, 'order_id');
    $ids = implode(',', $orderIds);
    $resultQuery = "SELECT * FROM lab_results WHERE order_id IN ($ids)";
    $resultResult = $conn->query($resultQuery);
    while ($row = $resultResult->fetch_assoc()) {
        $radiologyResults[$row['order_id']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Radiology Management - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Modernized Theme Accent Override (Premium Indigo/Purple Theme) */
        :root {
            --primary-purple: #7c3aed;
            --primary-purple-hover: #6d28d9;
            --primary-purple-light: #f3e8ff;
            --primary-purple-border: #d8b4fe;
            --primary-purple-dark: #6b21a8;
        }

        .btn-create, .btn-submit, .search-box-rad button[type="submit"] {
            background: var(--primary-purple) !important;
            transition: all 0.3s ease;
        }
        .btn-create:hover, .btn-submit:hover, .search-box-rad button[type="submit"]:hover {
            background: var(--primary-purple-hover) !important;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
        }

        .filter-tab.active {
            background: var(--primary-purple) !important;
        }

        .pending-rad-card {
            background: var(--primary-purple-light);
            border: 1px solid var(--primary-purple-border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            animation: fadeIn 0.4s ease;
        }
        .pending-rad-card h3 {
            margin: 0 0 12px;
            font-size: 15px;
            color: var(--primary-purple-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pending-rad-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .pending-rad-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            background: #fff;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--primary-purple-border);
            flex-wrap: wrap;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .pending-rad-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.08);
        }
        .pending-rad-item-info strong {
            display: block;
            color: #0f172a;
            font-size: 14px;
        }
        .pending-rad-item-info small {
            color: #64748b;
            font-size: 12px;
        }
        .btn-order-rad {
            padding: 6px 14px;
            background: var(--primary-purple);
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            transition: background 0.3s;
        }
        .btn-order-rad:hover {
            background: var(--primary-purple-hover);
        }

        .form-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
            display: <?php echo (isset($_GET['action']) && ($_GET['action'] === 'create' || $_GET['action'] === 'result')) ? 'flex' : 'none'; ?>;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
            animation: fadeIn 0.3s ease;
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
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-purple);
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
            transition: background 0.3s;
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
            transition: background 0.3s;
        }
        .btn-result {
            background: #d1fae5;
            color: #059669;
        }
        .btn-result:hover {
            background: #a7f3d0;
        }
        .search-box-rad {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-box-rad input, .search-box-rad select {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .search-box-rad input:focus, .search-box-rad select:focus {
            outline: none;
            border-color: var(--primary-purple);
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            animation: fadeIn 0.3s ease;
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
        .status-badge-rad {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-ordered { background: #dbeafe; color: #2563eb; }
        .status-samplecollected { background: #fef3c7; color: #d97706; }
        .status-resultready { background: #d1fae5; color: #059669; }
        .status-reviewed { background: #ede9fe; color: #7c3aed; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        
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
        .result-box {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            margin-top: 8px;
            border-left: 3px solid #22c55e;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .result-box p {
            margin: 4px 0;
            font-size: 13px;
        }
        .result-box .result-label {
            font-weight: 600;
            color: #475569;
        }
        .test-checklist label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .test-checklist label:hover {
            background: var(--primary-purple-light);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
                    <h1>Radiology Department</h1>
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
                <div class="search-box-rad">
                    <form method="GET" action="" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <input type="text" name="search" placeholder="Search orders..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="width: 200px;">
                        <select name="status">
                            <option value="All">All Status</option>
                            <?php foreach ($orderStatuses as $status): ?>
                                <option value="<?php echo $status['name']; ?>" <?php echo $filterStatus === $status['name'] ? 'selected' : ''; ?>>
                                    <?php echo $status['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" style="padding: 8px 16px; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if ($searchTerm || $filterStatus): ?>
                            <a href="radiology.php" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <button class="btn-create" onclick="window.location.href='radiology.php?action=create'">
                    <i class="fas fa-plus"></i> New Radiology Order
                </button>
            </div>

            <?php if (!empty($pendingRadiologyPatients)): ?>
            <div class="pending-rad-card">
                <h3><i class="fas fa-user-clock"></i> Patients Referred for Radiology (<?php echo count($pendingRadiologyPatients); ?>)</h3>
                <div class="pending-rad-list">
                    <?php foreach ($pendingRadiologyPatients as $pending): ?>
                    <div class="pending-rad-item">
                        <div class="pending-rad-item-info">
                            <strong><?php echo htmlspecialchars($pending['patient_name']); ?> (<?php echo htmlspecialchars($pending['patient_code']); ?>)</strong>
                            <small>
                                Visit: <?php echo htmlspecialchars($pending['visit_code']); ?>
                                · Dr. <?php echo htmlspecialchars($pending['doctor_name']); ?>
                                <?php if ($pending['diagnosis']): ?> · <?php echo htmlspecialchars($pending['diagnosis']); ?><?php endif; ?>
                            </small>
                        </div>
                        <a href="radiology.php?action=create&visit_id=<?php echo $pending['visit_id']; ?>" class="btn-order-rad">
                            <i class="fas fa-x-ray"></i> Create Radiology Order
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Status Filter Tabs -->
            <div class="filter-tabs">
                <a href="radiology.php" class="filter-tab <?php echo !$filterStatus ? 'active' : ''; ?>">All</a>
                <a href="radiology.php?status=Ordered" class="filter-tab <?php echo $filterStatus === 'Ordered' ? 'active' : ''; ?>">Ordered</a>
                <a href="radiology.php?status=Sample%20Collected" class="filter-tab <?php echo $filterStatus === 'Sample Collected' ? 'active' : ''; ?>">Imaging Scheduled</a>
                <a href="radiology.php?status=Result%20Ready" class="filter-tab <?php echo $filterStatus === 'Result Ready' ? 'active' : ''; ?>">Scan Ready</a>
                <a href="radiology.php?status=Reviewed" class="filter-tab <?php echo $filterStatus === 'Reviewed' ? 'active' : ''; ?>">Reviewed</a>
                <a href="radiology.php?status=Cancelled" class="filter-tab <?php echo $filterStatus === 'Cancelled' ? 'active' : ''; ?>">Cancelled</a>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Patient</th>
                                <th>Visit</th>
                                <th>Test</th>
                                <th>Category</th>
                                <th>Ordered By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($radiologyOrders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-x-ray" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        No radiology orders found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($radiologyOrders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                    <td>
                                        <div class="patient-info">
                                            <div class="avatar" style="background: <?php echo getUserColor($order['patient_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo strtoupper(substr($order['patient_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="patient-name"><?php echo htmlspecialchars($order['patient_name']); ?></span>
                                                <small><?php echo htmlspecialchars($order['patient_code']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['visit_code']); ?></td>
                                    <td><?php echo htmlspecialchars($order['test_name']); ?></td>
                                    <td><span style="background: #faf5ff; border: 1px solid #e9d5ff; color: #7c3aed; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500;"><?php echo htmlspecialchars($order['category']); ?></span></td>
                                    <td><?php echo htmlspecialchars($order['ordered_by_name']); ?></td>
                                    <td>
                                        <span class="status-badge-rad status-<?php echo strtolower(str_replace(' ', '', $order['status_name'])); ?>">
                                            <?php echo htmlspecialchars($order['status_name'] === 'Sample Collected' ? 'Imaging Scheduled' : ($order['status_name'] === 'Result Ready' ? 'Scan Ready' : $order['status_name'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($order['status_name'] === 'Ordered' || $order['status_name'] === 'Sample Collected'): ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                    <select name="status_id" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; font-size: 12px; font-family: inherit;">
                                                        <?php foreach ($orderStatuses as $status): ?>
                                                            <option value="<?php echo $status['order_status_id']; ?>" <?php echo $status['order_status_id'] == $order['order_status_id'] ? 'selected' : ''; ?>>
                                                                <?php echo $status['name'] === 'Sample Collected' ? 'Imaging Scheduled' : ($status['name'] === 'Result Ready' ? 'Scan Ready' : $status['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['status_name'] !== 'Result Ready' && $order['status_name'] !== 'Reviewed' && $order['status_name'] !== 'Cancelled'): ?>
                                                <a href="radiology.php?action=result&id=<?php echo $order['order_id']; ?>" class="btn-result">
                                                    <i class="fas fa-plus"></i> Add Report/Scan Result
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (isset($radiologyResults[$order['order_id']])): ?>
                                            <div class="result-box">
                                                <?php foreach ($radiologyResults[$order['order_id']] as $result): ?>
                                                    <p><span class="result-label">Diagnosis Report:</span> <?php echo htmlspecialchars($result['result_value']); ?></p>
                                                    <?php if ($result['result_notes']): ?>
                                                        <p><span class="result-label">Radiologist Notes:</span> <?php echo htmlspecialchars($result['result_notes']); ?></p>
                                                    <?php endif; ?>
                                                    <p style="font-size: 11px; color: #94a3b8;">
                                                        Entered: <?php echo date('M d, Y g:i A', strtotime($result['entered_at'])); ?>
                                                    </p>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
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

    <!-- Create Radiology Order Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'create'): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='radiology.php'">&times;</button>
            <h2 style="margin-bottom: 24px; color: var(--primary-purple-dark); font-weight: 700;"><i class="fas fa-x-ray"></i> Create Radiology Order</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                <?php if (isset($_GET['next']) && $_GET['next'] === 'bed'): ?>
                    <input type="hidden" name="next" value="bed">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="visit_id">Visit / Patient *</label>
                    <?php if ($selectedVisitId > 0): ?>
                        <?php foreach ($visits as $visit): if ((int) $visit['visit_id'] === $selectedVisitId): ?>
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                            <input type="text" value="<?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['first_name'] . ' ' . $visit['last_name']); ?>" readonly style="background: #f8fafc; font-weight: 500;">
                        <?php endif; endforeach; ?>
                    <?php else: ?>
                    <select id="visit_id" name="visit_id" required>
                        <option value="">Select Visit</option>
                        <?php foreach ($visits as $visit): ?>
                            <option value="<?php echo $visit['visit_id']; ?>" <?php echo $selectedVisitId === (int) $visit['visit_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['first_name'] . ' ' . $visit['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Imaging Tests Required *</label>
                    <div class="test-checklist" style="display: flex; flex-direction: column; gap: 8px; max-height: 250px; overflow-y: auto; padding: 4px;">
                        <?php foreach ($testTypes as $test): ?>
                            <label>
                                <input type="checkbox" name="test_type_ids[]" value="<?php echo $test['test_type_id']; ?>">
                                <span style="font-weight: 500;"><?php echo htmlspecialchars($test['name']); ?></span>
                                <span style="margin-left: auto; color: var(--primary-purple-dark); font-weight: 600;">$<?php echo number_format($test['price'], 2); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit" style="color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; padding: 12px;">
                        <i class="fas fa-save"></i> Create Order
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='radiology.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Result Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'result' && isset($_GET['id'])): 
        $orderId = intval($_GET['id']);
        $orderQuery = "SELECT lo.*, vt.name as test_name, CONCAT(p.first_name, ' ', p.last_name) as patient_name 
                       FROM lab_orders lo
                       JOIN lookup_test_types vt ON lo.test_type_id = vt.test_type_id
                       JOIN visits v ON lo.visit_id = v.visit_id
                       JOIN patients p ON v.patient_id = p.patient_id
                       WHERE lo.order_id = ?";
        $stmt = $conn->prepare($orderQuery);
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $orderData = $stmt->get_result()->fetch_assoc();
    ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='radiology.php'">&times;</button>
            <h2 style="margin-bottom: 24px; color: var(--primary-purple-dark); font-weight: 700;"><i class="fas fa-x-ray"></i> Add Radiology Report</h2>
            
            <div style="background: var(--primary-purple-light); padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--primary-purple-border);">
                <p style="margin: 4px 0;"><strong>Patient:</strong> <?php echo htmlspecialchars($orderData['patient_name']); ?></p>
                <p style="margin: 4px 0;"><strong>Imaging Test:</strong> <?php echo htmlspecialchars($orderData['test_name']); ?></p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_result">
                <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                
                <div class="form-group">
                    <label for="result_value">Diagnosis / Findings *</label>
                    <textarea id="result_value" name="result_value" rows="4" required placeholder="Enter diagnostic findings (e.g. No abnormalities detected, fracture on lower tibia)..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="result_notes">Radiologist Notes</label>
                    <textarea id="result_notes" name="result_notes" rows="3" placeholder="Enter additional notes or recommendations..."></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit" style="color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; padding: 12px;">
                        <i class="fas fa-save"></i> Save Report
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='radiology.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>
