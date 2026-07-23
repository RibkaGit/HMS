<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - LABORATORY MANAGEMENT
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

// Get test types
$testTypes = $conn->query("SELECT * FROM lookup_test_types WHERE is_active = 1 AND category = 'Laboratory'")->fetch_all(MYSQLI_ASSOC);

// Get order statuses
$orderStatuses = $conn->query("SELECT * FROM lookup_order_statuses")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// CREATE LAB ORDER
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
                addInvoiceCharge($conn, $visitId, 'Lab: ' . $test['name'], 'Test', 1, (float) $test['price'] > 0 ? (float) $test['price'] : 25.00);
            }
        }
        updateVisitStatus($conn, $visitId, 'Awaiting Results');
        $clearLabFlag = $conn->prepare("UPDATE medical_records SET needs_lab = 0 WHERE visit_id = ?");
        $clearLabFlag->bind_param('i', $visitId);
        $clearLabFlag->execute();
        logUserActivity($conn, $_SESSION['user_id'], 'Created Lab Orders', "Created {$createdCount} lab order(s) for visit ID: {$visitId}");
        if (isset($_POST['next']) && $_POST['next'] === 'bed') {
            header('Location: bed_management.php?action=assign_bed&visit_id=' . $visitId);
            exit();
        }
        $message = 'Lab order created successfully!';
        header('Location: lab.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Select at least one test and try again.';
    }
}

// ============================================================================
// UPDATE LAB ORDER STATUS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $orderId = intval($_POST['order_id']);
    $statusId = intval($_POST['status_id']);
    
    $query = "UPDATE lab_orders SET order_status_id = ? WHERE order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $statusId, $orderId);
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Lab Order', "Updated lab order ID: {$orderId}");
        $message = 'Lab order status updated successfully!';
        header('Location: lab.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update lab order. Please try again.';
    }
}

// ============================================================================
// ADD LAB RESULT (BULK FOR GROUPED VIEW)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bulk_results') {
    $visitId = intval($_POST['visit_id']);
    $successCount = 0;
    
    foreach ($_POST['results'] as $orderId => $resultData) {
        if (!empty($resultData['value'])) {
            $data = [
                'entered_by' => $_SESSION['user_id'],
                'result_value' => $resultData['value'],
                'result_notes' => sanitizeInput($resultData['notes'] ?? '')
            ];
            if (updateLabResult($conn, intval($orderId), $data)) {
                $successCount++;
            }
        }
    }
    
    if ($successCount > 0) {
        updateVisitStatus($conn, $visitId, 'Awaiting Billing');
        
        // Notify medical records that lab results are ready
        $notifyStmt = $conn->prepare("UPDATE medical_records SET lab_results_ready = 1, lab_results_ready_at = NOW() WHERE visit_id = ?");
        $notifyStmt->bind_param('i', $visitId);
        $notifyStmt->execute();
        
        logUserActivity($conn, $_SESSION['user_id'], 'Added Lab Results', "Added {$successCount} lab results for visit ID: {$visitId}");
        $message = "Successfully added {$successCount} lab result(s)! Medical records notified.";
        header('Location: lab.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'No results were added. Please try again.';
    }
}

// ============================================================================
// ADD LAB RESULT (SINGLE)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_result') {
    $resultData = [
        'entered_by' => $_SESSION['user_id'],
        'result_value' => $_POST['result_value'],
        'result_notes' => sanitizeInput($_POST['result_notes'] ?? '')
    ];
    
    $result = updateLabResult($conn, intval($_POST['order_id']), $resultData);
    if ($result) {
        $visitStmt = $conn->prepare("SELECT visit_id FROM lab_orders WHERE order_id = ?");
        $visitStmt->bind_param('i', $_POST['order_id']);
        $visitStmt->execute();
        $visitRow = $visitStmt->get_result()->fetch_assoc();
        if ($visitRow) {
            updateVisitStatus($conn, $visitRow['visit_id'], 'Awaiting Billing');
            
            // Notify medical records that lab results are ready
            $notifyStmt = $conn->prepare("UPDATE medical_records SET lab_results_ready = 1, lab_results_ready_at = NOW() WHERE visit_id = ?");
            $notifyStmt->bind_param('i', $visitRow['visit_id']);
            $notifyStmt->execute();
        }
        logUserActivity($conn, $_SESSION['user_id'], 'Added Lab Result', "Added result for order ID: {$_POST['order_id']}");
        $message = 'Lab result added successfully! Medical records notified.';
        header('Location: lab.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to add lab result. Please try again.';
    }
}

// ============================================================================
// GET LAB ORDERS (GROUPED BY PATIENT/VISIT)
// ============================================================================
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

$query = "SELECT lo.*, 
          vt.name as test_name, vt.category,
          os.name as status_name,
          CONCAT(s.first_name, ' ', s.last_name) as ordered_by_name,
          CONCAT(p.first_name, ' ', p.last_name) as patient_name,
          p.patient_code,
          p.patient_id,
          v.visit_code,
          v.visit_id
          FROM lab_orders lo
          JOIN lookup_test_types vt ON lo.test_type_id = vt.test_type_id
          JOIN lookup_order_statuses os ON lo.order_status_id = os.order_status_id
          JOIN staff s ON lo.ordered_by = s.staff_id
          JOIN visits v ON lo.visit_id = v.visit_id
          JOIN patients p ON v.patient_id = p.patient_id
          WHERE vt.category = 'Laboratory'";

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

$query .= " ORDER BY lo.ordered_at DESC LIMIT 100";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$labOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group orders by patient/visit
$groupedOrders = [];
foreach ($labOrders as $order) {
    $key = $order['visit_id'];
    if (!isset($groupedOrders[$key])) {
        $groupedOrders[$key] = [
            'visit_id' => $order['visit_id'],
            'visit_code' => $order['visit_code'],
            'patient_id' => $order['patient_id'],
            'patient_name' => $order['patient_name'],
            'patient_code' => $order['patient_code'],
            'ordered_by_name' => $order['ordered_by_name'],
            'tests' => []
        ];
    }
    $groupedOrders[$key]['tests'][] = $order;
}

// Patients referred from medical records (needs lab)
$pendingLabQuery = "SELECT mr.record_id, mr.visit_id, mr.diagnosis, mr.created_at,
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           p.patient_code,
                           v.visit_code,
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                    FROM medical_records mr
                    JOIN patients p ON mr.patient_id = p.patient_id
                    JOIN visits v ON mr.visit_id = v.visit_id
                    JOIN staff d ON mr.doctor_id = d.staff_id
                    WHERE mr.needs_lab = 1
                    ORDER BY mr.created_at DESC";
$pendingLabPatients = $conn->query($pendingLabQuery)->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Get lab results for orders
$labResults = [];
if (!empty($labOrders)) {
    $orderIds = array_column($labOrders, 'order_id');
    $ids = implode(',', $orderIds);
    $resultQuery = "SELECT * FROM lab_results WHERE order_id IN ($ids)";
    $resultResult = $conn->query($resultQuery);
    while ($row = $resultResult->fetch_assoc()) {
        $labResults[$row['order_id']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory - HMS</title>
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
            display: <?php echo (isset($_GET['action']) && ($_GET['action'] === 'create' || $_GET['action'] === 'result')) ? 'flex' : 'none'; ?>;
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
        .btn-edit {
            background: #dbeafe;
            color: #2563eb;
        }
        .btn-edit:hover {
            background: #bfdbfe;
        }
        .btn-result {
            background: #d1fae5;
            color: #059669;
        }
        .btn-result:hover {
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
        .search-box-lab {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-box-lab input, .search-box-lab select {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .search-box-lab input:focus, .search-box-lab select:focus {
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
        .status-badge-lab {
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
        .filter-tab.active {
            background: #2563eb;
            color: #fff;
        }
        .result-box {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            margin-top: 8px;
            border-left: 3px solid #22c55e;
        }
        .result-box p {
            margin: 4px 0;
            font-size: 13px;
        }
        .result-box .result-label {
            font-weight: 600;
            color: #475569;
        }
        .pending-lab-card {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .pending-lab-card h3 {
            margin: 0 0 12px;
            font-size: 15px;
            color: #1d4ed8;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pending-lab-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .pending-lab-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            background: #fff;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #dbeafe;
            flex-wrap: wrap;
        }
        .pending-lab-item-info strong {
            display: block;
            color: #0f172a;
            font-size: 14px;
        }
        .pending-lab-item-info small {
            color: #64748b;
            font-size: 12px;
        }
        .btn-order-lab {
            padding: 6px 14px;
            background: #2563eb;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
        }
        .btn-order-lab:hover {
            background: #1d4ed8;
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
                    <h1>Laboratory Management</h1>
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
                <div class="search-box-lab">
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
                        <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($searchTerm || $filterStatus): ?>
                            <a href="lab.php" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <button class="btn-create" onclick="window.location.href='lab.php?action=create'">
                    <i class="fas fa-plus"></i> New Lab Order
                </button>
            </div>

            <?php if (!empty($pendingLabPatients)): ?>
            <div class="pending-lab-card">
                <h3><i class="fas fa-user-clock"></i> Patients Referred for Lab (<?php echo count($pendingLabPatients); ?>)</h3>
                <div class="pending-lab-list">
                    <?php foreach ($pendingLabPatients as $pending): ?>
                    <div class="pending-lab-item">
                        <div class="pending-lab-item-info">
                            <strong><?php echo htmlspecialchars($pending['patient_name']); ?> (<?php echo htmlspecialchars($pending['patient_code']); ?>)</strong>
                            <small>
                                Visit: <?php echo htmlspecialchars($pending['visit_code']); ?>
                                · Dr. <?php echo htmlspecialchars($pending['doctor_name']); ?>
                                <?php if ($pending['diagnosis']): ?> · <?php echo htmlspecialchars($pending['diagnosis']); ?><?php endif; ?>
                            </small>
                        </div>
                        <a href="lab.php?action=create&visit_id=<?php echo $pending['visit_id']; ?>" class="btn-order-lab">
                            <i class="fas fa-flask"></i> Create Lab Order
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Status Filter Tabs -->
            <div class="filter-tabs">
                <a href="lab.php" class="filter-tab <?php echo !$filterStatus ? 'active' : ''; ?>">All</a>
                <a href="lab.php?status=Ordered" class="filter-tab <?php echo $filterStatus === 'Ordered' ? 'active' : ''; ?>">Ordered</a>
                <a href="lab.php?status=Sample%20Collected" class="filter-tab <?php echo $filterStatus === 'Sample Collected' ? 'active' : ''; ?>">Sample Collected</a>
                <a href="lab.php?status=Result%20Ready" class="filter-tab <?php echo $filterStatus === 'Result Ready' ? 'active' : ''; ?>">Result Ready</a>
                <a href="lab.php?status=Reviewed" class="filter-tab <?php echo $filterStatus === 'Reviewed' ? 'active' : ''; ?>">Reviewed</a>
                <a href="lab.php?status=Cancelled" class="filter-tab <?php echo $filterStatus === 'Cancelled' ? 'active' : ''; ?>">Cancelled</a>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Visit</th>
                                <th>Tests</th>
                                <th>Ordered By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($groupedOrders)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-flask" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        No lab orders found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($groupedOrders as $group): ?>
                                <tr>
                                    <td>
                                        <div class="patient-info">
                                            <div class="avatar" style="background: <?php echo getUserColor($group['patient_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo strtoupper(substr($group['patient_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="patient-name"><?php echo htmlspecialchars($group['patient_name']); ?></span>
                                                <small><?php echo htmlspecialchars($group['patient_code']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($group['visit_code']); ?></td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <?php foreach ($group['tests'] as $test): ?>
                                                <div style="font-size: 13px;">
                                                    <?php echo htmlspecialchars($test['test_name']); ?>
                                                    <span class="status-badge-lab status-<?php echo strtolower(str_replace(' ', '', $test['status_name'])); ?>" style="font-size: 10px; padding: 2px 6px;">
                                                        <?php echo htmlspecialchars($test['status_name']); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($group['ordered_by_name']); ?></td>
                                    <td>
                                        <?php 
                                        $allComplete = true;
                                        $anyInProgress = false;
                                        foreach ($group['tests'] as $test) {
                                            if ($test['status_name'] !== 'Result Ready' && $test['status_name'] !== 'Reviewed') {
                                                $allComplete = false;
                                            }
                                            if ($test['status_name'] === 'Ordered' || $test['status_name'] === 'Sample Collected') {
                                                $anyInProgress = true;
                                            }
                                        }
                                        if ($allComplete) {
                                            echo '<span class="status-badge-lab status-resultready">Complete</span>';
                                        } elseif ($anyInProgress) {
                                            echo '<span class="status-badge-lab status-ordered">In Progress</span>';
                                        } else {
                                            echo '<span class="status-badge-lab status-reviewed">Reviewed</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="lab.php?action=view_grouped&visit_id=<?php echo $group['visit_id']; ?>" class="btn-result">
                                                <i class="fas fa-eye"></i> View & Add Results
                                            </a>
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

    <!-- View Grouped Lab Results Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'view_grouped' && isset($_GET['visit_id'])): ?>
    <?php
    $visitId = intval($_GET['visit_id']);
    $visitTestsQuery = "SELECT lo.*, vt.name as test_name, vt.category, os.name as status_name,
                        lr.result_value, lr.result_notes, lr.entered_at,
                        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                        p.patient_code,
                        v.visit_code
                        FROM lab_orders lo
                        JOIN lookup_test_types vt ON lo.test_type_id = vt.test_type_id
                        JOIN lookup_order_statuses os ON lo.order_status_id = os.order_status_id
                        JOIN visits v ON lo.visit_id = v.visit_id
                        JOIN patients p ON v.patient_id = p.patient_id
                        LEFT JOIN lab_results lr ON lo.order_id = lr.order_id
                        WHERE lo.visit_id = ? AND vt.category = 'Laboratory'
                        ORDER BY lo.ordered_at ASC";
    $visitStmt = $conn->prepare($visitTestsQuery);
    $visitStmt->bind_param('i', $visitId);
    $visitStmt->execute();
    $visitTests = $visitStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $visitInfo = !empty($visitTests) ? $visitTests[0] : null;
    ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content" style="max-width: 900px;">
            <button class="close-btn" onclick="window.location.href='lab.php'">&times;</button>
            <h2 style="margin-bottom: 24px;">Lab Results - <?php echo htmlspecialchars($visitInfo['patient_name'] ?? 'Patient'); ?></h2>
            
            <?php if ($visitInfo): ?>
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                <p><strong>Patient:</strong> <?php echo htmlspecialchars($visitInfo['patient_name']); ?> (<?php echo htmlspecialchars($visitInfo['patient_code']); ?>)</p>
                <p><strong>Visit:</strong> <?php echo htmlspecialchars($visitInfo['visit_code']); ?></p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_bulk_results">
                <input type="hidden" name="visit_id" value="<?php echo $visitId; ?>">
                
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($visitTests as $test): ?>
                    <div style="background: #fff; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h3 style="margin: 0; font-size: 16px;"><?php echo htmlspecialchars($test['test_name']); ?></h3>
                            <span class="status-badge-lab status-<?php echo strtolower(str_replace(' ', '', $test['status_name'])); ?>">
                                <?php echo htmlspecialchars($test['status_name']); ?>
                            </span>
                        </div>
                        
                        <?php if ($test['result_value']): ?>
                        <div style="background: #f0fdf4; padding: 12px; border-radius: 6px; margin-bottom: 12px;">
                            <p style="margin: 0;"><strong>Result:</strong> <?php echo htmlspecialchars($test['result_value']); ?></p>
                            <?php if ($test['result_notes']): ?>
                            <p style="margin: 4px 0 0 0;"><strong>Notes:</strong> <?php echo htmlspecialchars($test['result_notes']); ?></p>
                            <?php endif; ?>
                            <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">
                                Entered: <?php echo date('M d, Y g:i A', strtotime($test['entered_at'])); ?>
                            </p>
                        </div>
                        <?php else: ?>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <label style="display: block; margin-bottom: 6px; font-weight: 500;">Result Value *</label>
                                <input type="text" name="results[<?php echo $test['order_id']; ?>][value]" 
                                       placeholder="Enter result..." 
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            </div>
                            <div style="flex: 1; min-width: 200px;">
                                <label style="display: block; margin-bottom: 6px; font-weight: 500;">Notes (Optional)</label>
                                <input type="text" name="results[<?php echo $test['order_id']; ?>][notes]" 
                                       placeholder="Additional notes..." 
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="btn-group" style="margin-top: 24px;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Save Results
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='lab.php'">Cancel</button>
                </div>
            </form>
            <?php else: ?>
            <p style="text-align: center; color: #64748b; padding: 40px;">No lab tests found for this visit.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Create Lab Order Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'create'): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='lab.php'">&times;</button>
            <h2 style="margin-bottom: 24px;">Create New Lab Order</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                <?php if (isset($_GET['next']) && $_GET['next'] === 'bed'): ?>
                    <input type="hidden" name="next" value="bed">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="visit_id">Visit *</label>
                    <?php if ($selectedVisitId > 0): ?>
                        <?php foreach ($visits as $visit): if ((int) $visit['visit_id'] === $selectedVisitId): ?>
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                            <input type="text" value="<?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['first_name'] . ' ' . $visit['last_name']); ?>" readonly>
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
                    <label>Tests needed for this patient *</label>
                    <div class="test-checklist">
                        <?php foreach ($testTypes as $test): ?>
                            <label style="display: block; margin: 8px 0;">
                                <input type="checkbox" name="test_type_ids[]" value="<?php echo $test['test_type_id']; ?>">
                                <?php echo htmlspecialchars($test['name'] . ' (' . $test['category'] . ') - $' . number_format($test['price'], 2)); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Create Lab Order
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='lab.php'">Cancel</button>
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
            <button class="close-btn" onclick="window.location.href='lab.php'">&times;</button>
            <h2 style="margin-bottom: 24px;">Add Lab Result</h2>
            
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                <p><strong>Patient:</strong> <?php echo htmlspecialchars($orderData['patient_name']); ?></p>
                <p><strong>Test:</strong> <?php echo htmlspecialchars($orderData['test_name']); ?></p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_result">
                <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                
                <div class="form-group">
                    <label for="result_value">Result Value *</label>
                    <textarea id="result_value" name="result_value" rows="3" required placeholder="Enter the test result..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="result_notes">Additional Notes</label>
                    <textarea id="result_notes" name="result_notes" rows="2" placeholder="Any additional notes about the result..."></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Save Result
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='lab.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>