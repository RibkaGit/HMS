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

// Get test types and group them
$testTypesQuery = $conn->query("SELECT * FROM lookup_test_types WHERE is_active = 1 AND category = 'Laboratory' ORDER BY sub_category, name");
$testTypes = $testTypesQuery ? $testTypesQuery->fetch_all(MYSQLI_ASSOC) : [];
$labCategories = [];
foreach ($testTypes as $test) {
    $sub = !empty($test['sub_category']) ? $test['sub_category'] : 'Other';
    $labCategories[$sub][] = $test;
}

// Get order statuses
$orderStatuses = $conn->query("SELECT * FROM lookup_order_statuses")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// SAMPLE COLLECT (create lab order with ordered status - waiting for sample collection)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sample_collect') {
    $visitId = intval($_POST['visit_id']);
    $testTypeIds = array_map('intval', $_POST['test_type_ids'] ?? []);
    
    ensureVisitMrId($conn, $visitId);
    
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
            'order_status_id' => $orderStatusId,
            'sample_id' => null
        ];
        if (createLabOrder($conn, $data)) {
            $createdCount++;
        }
    }
    if ($createdCount > 0) {
        // Clear needs_lab flag in medical records since tests are now ordered
        $clearLabFlag = $conn->prepare("UPDATE medical_records SET needs_lab = 0 WHERE visit_id = ?");
        $clearLabFlag->bind_param('i', $visitId);
        $clearLabFlag->execute();
        
        logUserActivity($conn, $_SESSION['user_id'], 'Lab Tests Ordered', "Ordered {$createdCount} test(s) for visit ID: {$visitId}");
        $message = 'Lab tests ordered successfully! Waiting for sample collection.';
        header('Location: lab.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Select at least one test and try again.';
    }
}

// ============================================================================
// COLLECT SAMPLE (move from Ordered to Sample Collected and add to billing)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_sample') {
    $visitId = intval($_POST['visit_id']);
    $orderIds = array_map('intval', $_POST['order_ids'] ?? []);
    $sampleIds = $_POST['sample_ids'] ?? [];
    
    $orderStatusId = getLookupId($conn, 'lookup_order_statuses', 'name', 'Sample Collected');
    $updatedCount = 0;
    
    foreach ($orderIds as $orderId) {
        $sampleId = !empty($sampleIds[$orderId]) ? sanitizeInput($sampleIds[$orderId]) : null;
        
        // Get test details for billing
        $testQuery = $conn->prepare("SELECT lo.test_type_id, vt.name, vt.price 
                                     FROM lab_orders lo
                                     JOIN lookup_test_types vt ON lo.test_type_id = vt.test_type_id
                                     WHERE lo.order_id = ?");
        $testQuery->bind_param('i', $orderId);
        $testQuery->execute();
        $testData = $testQuery->get_result()->fetch_assoc();
        
        // Update order status
        $updateQuery = $conn->prepare("UPDATE lab_orders SET order_status_id = ?, sample_id = ? WHERE order_id = ?");
        $updateQuery->bind_param('isi', $orderStatusId, $sampleId, $orderId);
        if ($updateQuery->execute()) {
            $updatedCount++;
            
            // Add to billing
            if ($testData) {
                addInvoiceCharge($conn, $visitId, 'Lab: ' . $testData['name'], 'Test', 1, (float) $testData['price'] > 0 ? (float) $testData['price'] : 25.00);
            }
        }
    }
    
    if ($updatedCount > 0) {
        updateVisitStatus($conn, $visitId, 'Awaiting Payment');
        logUserActivity($conn, $_SESSION['user_id'], 'Sample Collected', "Collected {$updatedCount} sample(s) for visit ID: {$visitId}");
        $message = 'Sample collected successfully! Added to billing.';
        header('Location: lab.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to collect sample. Please try again.';
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
    
    // Check if visit is paid - if not, don't allow results
    if (!isVisitPaid($conn, $visitId)) {
        $error = 'Payment required before adding lab results. Please complete payment first.';
    } else {
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
}

// ============================================================================
// ACKNOWLEDGE LAB RESULTS (DOCTOR)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'acknowledge_results') {
    $visitId = intval($_POST['visit_id']);
    
    // Clear the lab_results_ready flag in medical_records
    $ackStmt = $conn->prepare("UPDATE medical_records SET lab_results_ready = 0 WHERE visit_id = ?");
    $ackStmt->bind_param('i', $visitId);
    if ($ackStmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Acknowledged Lab Results', "Acknowledged lab results for visit ID: {$visitId}");
        $message = 'Lab results acknowledged successfully.';
        header('Location: medical_records.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to acknowledge lab results. Please try again.';
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
          v.visit_id,
          v.mr_id
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

$query .= " ORDER BY lo.ordered_at ASC LIMIT 100";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$labOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group orders by patient/visit and split by collection status
$groupedOrders = [];
foreach ($labOrders as $order) {
    $key = $order['visit_id'];
    if (!isset($groupedOrders[$key])) {
        $groupedOrders[$key] = [
            'visit_id' => $order['visit_id'],
            'visit_code' => $order['visit_code'],
            'mr_id' => $order['mr_id'] ?? '',
            'patient_id' => $order['patient_id'],
            'patient_name' => $order['patient_name'],
            'patient_code' => $order['patient_code'],
            'ordered_by_name' => $order['ordered_by_name'],
            'tests' => []
        ];
    }
    $groupedOrders[$key]['tests'][] = $order;
}

// Get orders waiting for lab test selection (from medical records needs_lab - only new patients without existing lab orders)
$waitingLabOrders = [];
$pendingLabQuery = "SELECT mr.record_id, mr.visit_id, mr.diagnosis, mr.created_at,
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           p.patient_code,
                           p.patient_id,
                           v.visit_code,
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                    FROM medical_records mr
                    JOIN patients p ON mr.patient_id = p.patient_id
                    JOIN visits v ON mr.visit_id = v.visit_id
                    JOIN staff d ON mr.doctor_id = d.staff_id
                    WHERE mr.needs_lab = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM lab_orders lo WHERE lo.visit_id = mr.visit_id
                    )
                    ORDER BY mr.created_at ASC";
$pendingLabPatients = $conn->query($pendingLabQuery)->fetch_all(MYSQLI_ASSOC);

$awaitingSampleGroups = [];
$sampleCollectedGroups = [];
foreach ($groupedOrders as $key => $group) {
    $hasOrdered = false;
    $hasCollected = false;
    foreach ($group['tests'] as $test) {
        if ($test['status_name'] === 'Ordered') {
            $hasOrdered = true;
        }
        if ($test['status_name'] === 'Sample Collected') {
            $hasCollected = true;
        }
    }
    if ($hasOrdered) {
        $awaitingSampleGroups[$key] = $group;
    } elseif ($hasCollected) {
        $sampleCollectedGroups[$key] = $group;
    } else {
        $sampleCollectedGroups[$key] = $group;
    }
}

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
            display: <?php echo (isset($_GET['action']) && ($_GET['action'] === 'sample_collect' || $_GET['action'] === 'create' || $_GET['action'] === 'result')) ? 'flex' : 'none'; ?>;
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
        
        @media print {
            .btn-group, .btn-submit, .btn-cancel, .close-btn, .action-buttons, .btn-result, .btn-create-action, .filter-tabs, .search-box, .form-modal-overlay {
                display: none !important;
            }
            .form-modal {
                position: static !important;
                background: white !important;
                box-shadow: none !important;
                border: none !important;
            }
            .form-modal-content {
                max-width: 100% !important;
                margin: 0 !important;
            }
            body {
                background: white !important;
            }
            .sidebar {
                display: none !important;
            }
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
            </div>

            <!-- Waiting Lab Orders (from medical records) -->
            <?php if (!empty($pendingLabPatients)): ?>
            <div class="table-card" style="margin-bottom: 24px; border: 2px solid #3b82f6; background: #eff6ff;">
                <h3 style="margin: 0 0 16px; font-size: 18px; color: #1d4ed8;">
                    <i class="fas fa-clipboard-list"></i> Waiting Lab Orders (<?php echo count($pendingLabPatients); ?>)
                </h3>
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Visit</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingLabPatients as $pending): ?>
                            <tr>
                                <td>
                                    <div class="patient-info">
                                        <div class="avatar" style="background: <?php echo getUserColor($pending['patient_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                            <?php echo strtoupper(substr($pending['patient_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <span class="patient-name"><?php echo htmlspecialchars($pending['patient_name']); ?></span>
                                            <small><?php echo htmlspecialchars($pending['patient_code']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($pending['visit_code']); ?></td>
                                <td><?php echo htmlspecialchars($pending['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($pending['diagnosis'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="lab.php?action=create&visit_id=<?php echo $pending['visit_id']; ?>" class="btn-result">
                                        <i class="fas fa-vial"></i> Select Tests
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

            <?php if (!$filterStatus || $filterStatus === 'All' || $filterStatus === 'Ordered'): ?>
            <div class="table-card" style="margin-bottom: 24px;">
                <h3 style="margin: 0 0 16px; font-size: 18px; color: #2563eb;">
                    <i class="fas fa-vial"></i> Awaiting Sample Collection (<?php echo count($awaitingSampleGroups); ?>)
                </h3>
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Visit</th>
                                <th>Tests</th>
                                <th>Ordered By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($awaitingSampleGroups)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #94a3b8; padding: 32px;">
                                        No orders awaiting sample collection
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($awaitingSampleGroups as $group): ?>
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
                                    <td>
                                        <div><?php echo htmlspecialchars($group['visit_code']); ?></div>
                                        <?php if(!empty($group['mr_id'])): ?>
                                            <small style="color: #64748b; font-size: 11px;"><?php echo htmlspecialchars($group['mr_id']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <?php foreach ($group['tests'] as $test): ?>
                                                <?php if ($test['status_name'] === 'Ordered'): ?>
                                                <div style="font-size: 13px;">
                                                    <?php echo htmlspecialchars($test['test_name']); ?>
                                                </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($group['ordered_by_name']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="lab.php?action=collect_sample&visit_id=<?php echo $group['visit_id']; ?>" class="btn-result">
                                                <i class="fas fa-vial"></i> Collect Sample
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
            <?php endif; ?>

            <?php if (!$filterStatus || $filterStatus === 'All' || $filterStatus !== 'Ordered'): ?>
            <div class="table-card" style="<?php echo ($filterStatus && $filterStatus !== 'All') ? '' : 'margin-top: 24px;'; ?>">
                <?php
                $secondCardTitle = "Sample Collected / Processed List";
                $secondCardIcon = "fa-check-circle";
                $secondCardColor = "#d97706"; // Amber
                
                if ($filterStatus === 'Sample Collected') {
                    $secondCardTitle = "Sample Collected List";
                } elseif ($filterStatus === 'Result Ready') {
                    $secondCardTitle = "Result Ready List";
                    $secondCardIcon = "fa-flask";
                    $secondCardColor = "#10b981"; // Green
                } elseif ($filterStatus === 'Reviewed') {
                    $secondCardTitle = "Reviewed Orders List";
                    $secondCardIcon = "fa-clipboard-check";
                    $secondCardColor = "#2563eb"; // Blue
                } elseif ($filterStatus === 'Cancelled') {
                    $secondCardTitle = "Cancelled Orders List";
                    $secondCardIcon = "fa-times-circle";
                    $secondCardColor = "#ef4444"; // Red
                }
                ?>
                <h3 style="margin: 0 0 16px; font-size: 18px; color: <?php echo $secondCardColor; ?>;">
                    <i class="fas <?php echo $secondCardIcon; ?>"></i> <?php echo $secondCardTitle; ?> (<?php echo count($sampleCollectedGroups); ?>)
                </h3>
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
                            <?php if (empty($sampleCollectedGroups)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-flask" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        <?php
                                        if ($filterStatus === 'Result Ready') {
                                            echo "No results ready yet";
                                        } elseif ($filterStatus === 'Reviewed') {
                                            echo "No reviewed orders yet";
                                        } elseif ($filterStatus === 'Cancelled') {
                                            echo "No cancelled orders";
                                        } else {
                                            echo "No collected samples yet";
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sampleCollectedGroups as $group): ?>
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
                                    <td>
                                        <div><?php echo htmlspecialchars($group['visit_code']); ?></div>
                                        <?php if(!empty($group['mr_id'])): ?>
                                            <small style="color: #64748b; font-size: 11px;"><?php echo htmlspecialchars($group['mr_id']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <?php foreach ($group['tests'] as $test): ?>
                                                <div style="font-size: 13px; display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                                                    <?php echo htmlspecialchars($test['test_name']); ?>
                                                    <?php if(!empty($test['sample_id'])): ?>
                                                        <span style="color: #64748b; font-size: 11px;">(<?php echo htmlspecialchars($test['sample_id']); ?>)</span>
                                                        <?php if(!empty($group['mr_id'])): ?>
                                                            <button type="button" onclick="printSampleLabel('<?php echo htmlspecialchars($group['mr_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($test['sample_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($group['visit_code'], ENT_QUOTES); ?>')" style="padding: 1px 6px; font-size: 10px; background: #dbeafe; color: #2563eb; border: none; border-radius: 4px; cursor: pointer;" title="Print Label"><i class="fas fa-print"></i></button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
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
                                            if ($test['status_name'] === 'Sample Collected') {
                                                $anyInProgress = true;
                                            }
                                        }
                                        if ($allComplete) {
                                            echo '<span class="status-badge-lab status-resultready">Complete</span>';
                                        } elseif ($anyInProgress) {
                                            echo '<span class="status-badge-lab status-samplecollected">Processing</span>';
                                        } else {
                                            echo '<span class="status-badge-lab status-reviewed">Reviewed</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if(!empty($group['mr_id'])): ?>
                                                <button type="button" onclick="printSampleLabel('<?php echo htmlspecialchars($group['mr_id'], ENT_QUOTES); ?>', '', '<?php echo htmlspecialchars($group['visit_code'], ENT_QUOTES); ?>')" class="btn-print" title="Print Sample Label">
                                                    <i class="fas fa-print"></i> Print
                                                </button>
                                            <?php endif; ?>
                                            <a href="lab.php?action=view_grouped&visit_id=<?php echo $group['visit_id']; ?>" class="btn-result">
                                                <i class="fas fa-eye"></i> View & Add Results
                                            </a>
                                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 4px 10px; border-radius: 4px; background: #f3e8ff; color: #7c3aed; font-size: 12px; font-weight: 500; border: 1px solid #d8b4fe;">
                                                <input type="checkbox" name="show_balance_<?php echo $group['visit_id']; ?>" id="show_balance_<?php echo $group['visit_id']; ?>" onchange="toggleVisitBalance(<?php echo $group['visit_id']; ?>)" <?php echo isset($_GET['show_balance_visit']) && $_GET['show_balance_visit'] == $group['visit_id'] ? 'checked' : ''; ?>>
                                                Balance
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['show_balance_visit']) && $_GET['show_balance_visit']): ?>
            <?php
            $selectedBalanceVisitId = intval($_GET['show_balance_visit']);
            $visitInfoQuery = "SELECT v.visit_code, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_code
                              FROM visits v JOIN patients p ON v.patient_id = p.patient_id WHERE v.visit_id = ?";
            $visitStmt = $conn->prepare($visitInfoQuery);
            $visitStmt->bind_param('i', $selectedBalanceVisitId);
            $visitStmt->execute();
            $visitInfo = $visitStmt->get_result()->fetch_assoc();

            $labCost = 0;
            $labQuery = "SELECT lo.*, lt.price, lt.name as test_name FROM lab_orders lo JOIN lookup_test_types lt ON lo.test_type_id = lt.test_type_id WHERE lo.visit_id = ?";
            $labStmt = $conn->prepare($labQuery);
            if ($labStmt) {
                $labStmt->bind_param('i', $selectedBalanceVisitId);
                $labStmt->execute();
                $labOrders = $labStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                foreach ($labOrders as $order) $labCost += ($order['price'] ?? 50);
            } else { $labOrders = []; }

            $radiologyCost = 0;
            $radQuery = "SELECT ro.*, rt.price, rt.name as radiology_type_name FROM radiology_orders ro JOIN radiology_types rt ON ro.radiology_type_id = rt.radiology_type_id WHERE ro.visit_id = ?";
            $radStmt = $conn->prepare($radQuery);
            if ($radStmt) {
                $radStmt->bind_param('i', $selectedBalanceVisitId);
                $radStmt->execute();
                $radiologyOrders = $radStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                foreach ($radiologyOrders as $order) $radiologyCost += ($order['price'] ?? 100);
            } else { $radiologyOrders = []; }

            $medicineCost = 0;
            $medQuery = "SELECT pi.*, m.unit_price, m.name as medication_name FROM prescription_items pi JOIN medications m ON pi.medication_id = m.medication_id JOIN prescriptions p ON pi.prescription_id = p.prescription_id WHERE p.visit_id = ?";
            $medStmt = $conn->prepare($medQuery);
            if ($medStmt) {
                $medStmt->bind_param('i', $selectedBalanceVisitId);
                $medStmt->execute();
                $prescriptionItems = $medStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                foreach ($prescriptionItems as $item) $medicineCost += ($item['quantity'] * $item['unit_price']);
            } else { $prescriptionItems = []; }

            $materialCost = 0;
            $materialQuery = "SELECT mu.*, m.unit_price, m.name FROM material_usage mu JOIN materials m ON mu.material_id = m.material_id WHERE mu.visit_id = ?";
            $materialStmt = $conn->prepare($materialQuery);
            if ($materialStmt) {
                $materialStmt->bind_param('i', $selectedBalanceVisitId);
                $materialStmt->execute();
                $materialUsage = $materialStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                foreach ($materialUsage as $usage) $materialCost += $usage['total_cost'];
            } else { $materialUsage = []; }

            $totalAmount = $labCost + $radiologyCost + $medicineCost + $materialCost;
            ?>
            <div class="table-card" style="margin-top: 24px;">
                <h2 style="margin-bottom: 24px; font-size: 24px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">
                    <i class="fas fa-file-invoice-dollar" style="color: #7c3aed; margin-right: 8px;"></i> Balance for Visit: <?php echo htmlspecialchars($visitInfo['visit_code'] ?? 'N/A'); ?>
                </h2>
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; color: white;"><?php echo htmlspecialchars($visitInfo['patient_name'] ?? 'N/A'); ?></h3>
                            <p style="margin: 4px 0 0 0; opacity: 0.9;"><?php echo htmlspecialchars($visitInfo['patient_code'] ?? 'N/A'); ?></p>
                        </div>
                        <div style="text-align: right;">
                            <p style="margin: 0; opacity: 0.9;">Total Balance</p>
                            <p style="margin: 4px 0 0 0; font-size: 28px; font-weight: 700;">$<?php echo number_format($totalAmount, 2); ?></p>
                        </div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    <div style="background: #f3e8ff; padding: 16px; border-radius: 8px; border: 2px solid #d8b4fe;">
                        <h4 style="margin: 0 0 8px; color: #7c3aed;">Lab Tests</h4>
                        <p style="margin: 0; font-size: 20px; font-weight: 700; color: #7c3aed;">$<?php echo number_format($labCost, 2); ?></p>
                    </div>
                    <div style="background: #fce7f3; padding: 16px; border-radius: 8px; border: 2px solid #f9a8d4;">
                        <h4 style="margin: 0 0 8px; color: #be185d;">Radiology</h4>
                        <p style="margin: 0; font-size: 20px; font-weight: 700; color: #be185d;">$<?php echo number_format($radiologyCost, 2); ?></p>
                    </div>
                    <div style="background: #dcfce7; padding: 16px; border-radius: 8px; border: 2px solid #86efac;">
                        <h4 style="margin: 0 0 8px; color: #166534;">Medicine</h4>
                        <p style="margin: 0; font-size: 20px; font-weight: 700; color: #166534;">$<?php echo number_format($medicineCost, 2); ?></p>
                    </div>
                    <div style="background: #fef3c7; padding: 16px; border-radius: 8px; border: 2px solid #fcd34d;">
                        <h4 style="margin: 0 0 8px; color: #92400e;">Materials</h4>
                        <p style="margin: 0; font-size: 20px; font-weight: 700; color: #92400e;">$<?php echo number_format($materialCost, 2); ?></p>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569;">Category</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569;">Item</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569;">Quantity</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569;">Unit Price</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($labOrders as $order): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 12px 16px; color: #334155;"><strong>Lab</strong></td>
                                <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($order['test_name']); ?></td>
                                <td style="padding: 12px 16px; color: #64748b;">1</td>
                                <td style="padding: 12px 16px; color: #64748b;">$<?php echo number_format($order['price'] ?? 50, 2); ?></td>
                                <td style="padding: 12px 16px; color: #334155; font-weight: 600;">$<?php echo number_format($order['price'] ?? 50, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php foreach ($radiologyOrders as $order): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 12px 16px; color: #334155;"><strong>Radiology</strong></td>
                                <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($order['radiology_type_name']); ?></td>
                                <td style="padding: 12px 16px; color: #64748b;">1</td>
                                <td style="padding: 12px 16px; color: #64748b;">$<?php echo number_format($order['price'] ?? 100, 2); ?></td>
                                <td style="padding: 12px 16px; color: #334155; font-weight: 600;">$<?php echo number_format($order['price'] ?? 100, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php foreach ($prescriptionItems as $item): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 12px 16px; color: #334155;"><strong>Medicine</strong></td>
                                <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($item['medication_name']); ?></td>
                                <td style="padding: 12px 16px; color: #64748b;"><?php echo $item['quantity']; ?></td>
                                <td style="padding: 12px 16px; color: #64748b;">$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td style="padding: 12px 16px; color: #334155; font-weight: 600;">$<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php foreach ($materialUsage as $usage): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 12px 16px; color: #334155;"><strong>Material</strong></td>
                                <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($usage['name']); ?></td>
                                <td style="padding: 12px 16px; color: #64748b;"><?php echo $usage['quantity_used']; ?></td>
                                <td style="padding: 12px 16px; color: #64748b;">$<?php echo number_format($usage['unit_cost'], 2); ?></td>
                                <td style="padding: 12px 16px; color: #334155; font-weight: 600;">$<?php echo number_format($usage['total_cost'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- View Grouped Lab Results Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'view_grouped' && isset($_GET['visit_id'])): ?>
    <?php
    $visitId = intval($_GET['visit_id']);
    $isVisitPaid = isVisitPaid($conn, $visitId);
    $visitTestsQuery = "SELECT lo.*, vt.name as test_name, vt.category, os.name as status_name,
                        lr.result_value, lr.result_notes, lr.entered_at,
                        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                        p.patient_code,
                        v.visit_code, v.mr_id
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
                <p><strong>Payment Status:</strong> <?php echo $isVisitPaid ? '<span style="color: #059669; font-weight: bold;">Paid</span>' : '<span style="color: #dc2626; font-weight: bold;">Unpaid</span>'; ?></p>
            </div>
            
            <?php if (!$isVisitPaid): ?>
            <div style="background: #fee2e2; padding: 16px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #dc2626;">
                <p style="margin: 0; color: #dc2626; font-weight: bold;">
                    <i class="fas fa-exclamation-triangle"></i> Payment Required
                </p>
                <p style="margin: 8px 0 0 0; color: #7f1d1d;">
                    Please complete payment before adding lab results. Go to billing to process payment.
                </p>
                <a href="billing.php?visit_id=<?php echo $visitId; ?>" style="display: inline-block; margin-top: 12px; padding: 8px 16px; background: #dc2626; color: #fff; text-decoration: none; border-radius: 6px;">
                    <i class="fas fa-file-invoice-dollar"></i> Go to Billing
                </a>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_bulk_results">
                <input type="hidden" name="visit_id" value="<?php echo $visitId; ?>">
                
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <?php foreach ($visitTests as $test): ?>
                    <div style="background: #fff; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h3 style="margin: 0; font-size: 16px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <?php echo htmlspecialchars($test['test_name']); ?>
                                <?php if(!empty($test['sample_id'])): ?>
                                    <span style="color: #64748b; font-size: 12px;">Sample ID: <?php echo htmlspecialchars($test['sample_id']); ?></span>
                                    <?php if(!empty($visitInfo['mr_id'])): ?>
                                        <button type="button" onclick="printSampleLabel('<?php echo htmlspecialchars($visitInfo['mr_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($test['sample_id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($visitInfo['visit_code'], ENT_QUOTES); ?>')" style="padding: 2px 8px; font-size: 11px; background: #dbeafe; color: #2563eb; border: none; border-radius: 4px; cursor: pointer;"><i class="fas fa-print"></i> Print Label</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </h3>
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
                        <?php elseif ($isVisitPaid): ?>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <label style="display: block; margin-bottom: 6px; font-weight: 600;">Result Value </label>
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
                        <?php else: ?>
                        <div style="background: #f1f5f9; padding: 12px; border-radius: 6px; color: #64748b;">
                            <i class="fas fa-lock"></i> Result entry locked - payment required
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="btn-group" style="margin-top: 24px;">
                    <?php if ($isVisitPaid): ?>
                        <?php if (isset($_GET['from']) && $_GET['from'] === 'medical_records'): ?>
                            <button type="submit" class="btn-submit" onclick="this.form.elements['action'].value='acknowledge_results';">
                                <i class="fas fa-check-circle"></i> Acknowledge Results
                            </button>
                            <button type="button" class="btn-cancel" onclick="window.location.href='medical_records.php'">Cancel</button>
                        <?php else: ?>
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Save Results
                            </button>
                            <button type="button" class="btn-cancel" onclick="window.location.href='lab.php'">Cancel</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button type="button" class="btn-cancel" onclick="window.location.href='lab.php'">Close</button>
                    <?php endif; ?>
                </div>
            </form>
            <?php else: ?>
            <p style="text-align: center; color: #64748b; padding: 40px;">No lab tests found for this visit.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sample Collect Modal -->
    <?php if (isset($_GET['action']) && ($_GET['action'] === 'sample_collect' || $_GET['action'] === 'create')): ?>
    <?php
        $nextSampleId = getNextSampleIdPreview($conn);
        $createMrId = '';
        if ($selectedVisitId > 0) {
            $createMrId = ensureVisitMrId($conn, $selectedVisitId);
        }
    ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='lab.php'">&times;</button>
            <h2 style="margin-bottom: 24px;">Sample Collect</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="sample_collect">
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
                    <div class="test-categories-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                        <?php foreach ($labCategories as $subCat => $tests): ?>
                            <div class="test-category-card" style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <h4 style="margin-top: 0; margin-bottom: 8px; color: #1e293b; font-size: 14px; border-bottom: 2px solid #cbd5e1; padding-bottom: 4px;"><?php echo htmlspecialchars($subCat); ?></h4>
                                <div class="test-checklist" style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($tests as $test): ?>
                                        <label class="lab-test-label" style="display: flex; align-items: flex-start; gap: 8px; margin: 6px 0; font-size: 13px; cursor: pointer; flex-wrap: wrap;">
                                            <input type="checkbox" class="lab-test-checkbox" name="test_type_ids[]" value="<?php echo $test['test_type_id']; ?>" data-test-id="<?php echo $test['test_type_id']; ?>" style="margin-top: 2px; width: auto;">
                                            <span><?php echo htmlspecialchars($test['name']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-vial"></i> Save Sample Collect
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='lab.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Collect Sample Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'collect_sample' && isset($_GET['visit_id'])): ?>
    <?php
        $collectVisitId = intval($_GET['visit_id']);
        $collectVisitData = getVisitById($conn, $collectVisitId);
        
        // Get ordered tests for this visit
        $orderedTestsQuery = "SELECT lo.*, vt.name as test_name
                             FROM lab_orders lo
                             JOIN lookup_test_types vt ON lo.test_type_id = vt.test_type_id
                             WHERE lo.visit_id = ? AND lo.order_status_id = (SELECT order_status_id FROM lookup_order_statuses WHERE name = 'Ordered')";
        $orderedTestsStmt = $conn->prepare($orderedTestsQuery);
        $orderedTestsStmt->bind_param('i', $collectVisitId);
        $orderedTestsStmt->execute();
        $orderedTests = $orderedTestsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Generate unique sample IDs for each test
        $sampleIds = [];
        foreach ($orderedTests as $test) {
            $sampleIds[$test['order_id']] = generateUniqueSampleId($conn);
        }
    ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='lab.php'">&times;</button>
            <h2 style="margin-bottom: 24px;">Collect Sample</h2>
            
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                <p><strong>Patient:</strong> <?php echo htmlspecialchars($collectVisitData['first_name'] . ' ' . $collectVisitData['last_name']); ?></p>
                <p><strong>Visit Code:</strong> <?php echo htmlspecialchars($collectVisitData['visit_code']); ?></p>
                <p><strong>MR ID:</strong> <?php echo htmlspecialchars($collectVisitData['mr_id'] ?? 'N/A'); ?></p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="collect_sample">
                <input type="hidden" name="visit_id" value="<?php echo $collectVisitId; ?>">
                
                <div class="form-group">
                    <label>Tests to Collect Sample For *</label>
                    <?php if (empty($orderedTests)): ?>
                        <p style="color: #64748b;">No ordered tests found for this visit.</p>
                    <?php else: ?>
                        <?php foreach ($orderedTests as $test): ?>
                        <div style="background: #f1f5f9; padding: 12px; border-radius: 8px; margin-bottom: 12px; display: flex; align-items: center; gap: 12px;">
                            <input type="checkbox" name="order_ids[]" value="<?php echo $test['order_id']; ?>" required style="width: auto;">
                            <div style="flex: 1;">
                                <strong><?php echo htmlspecialchars($test['test_name']); ?></strong>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="text" name="sample_ids[<?php echo $test['order_id']; ?>]" 
                                       placeholder="Sample ID" 
                                       value="<?php echo $sampleIds[$test['order_id']]; ?>"
                                       readonly
                                       style="width: 120px; padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc;">
                                <button type="button" onclick="printSampleLabel('<?php echo $collectVisitData['mr_id'] ?? 'N/A'; ?>', '<?php echo $sampleIds[$test['order_id']]; ?>')" style="padding: 4px 8px; background: #dbeafe; color: #2563eb; border: none; border-radius: 4px; cursor: pointer;" title="Print Label">
                                    <i class="fas fa-print"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-vial"></i> Save Sample Collection
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='lab.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function printSampleLabel(mrId, sampleId) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Sample Label</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; text-align: center; }
                        .label { border: 2px solid #000; padding: 20px; display: inline-block; }
                        .mr-id { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
                        .sample-id { font-size: 24px; font-weight: bold; color: #2563eb; }
                    </style>
                </head>
                <body>
                    <div class="label">
                        <div class="mr-id">MR ID: ${mrId}</div>
                        <div class="sample-id">Sample ID: ${sampleId}</div>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
    </script>
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
                    <textarea id="result_value" name="result_value" rows="7" required placeholder="Enter the test result..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="result_notes">Additional Notes</label>
                    <textarea id="result_notes" name="result_notes" rows="7" placeholder="Any additional notes about the result..."></textarea>
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
    <script>
    (function() {
        const nextSamplePreview = <?php echo json_encode($nextSampleId ?? getNextSampleIdPreview($conn)); ?>;
        const createMrId = <?php echo json_encode($createMrId ?? ''); ?>;
        let sampleCounter = 0;
        const match = nextSamplePreview.match(/SMP-\d+-(\d+)/);
        if (match) {
            sampleCounter = parseInt(match[1], 10) - 1;
        }

        function generateNextSampleId() {
            sampleCounter++;
            const year = new Date().getFullYear().toString().slice(-2);
            return 'SMP-' + year + '-' + String(sampleCounter).padStart(4, '0');
        }

        function getMrIdForForm() {
            const visitSelect = document.getElementById('visit_id');
            return createMrId || '';
        }

        document.querySelectorAll('.lab-test-checkbox').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const label = this.closest('.lab-test-label');
                const display = label.querySelector('.sample-id-display');
                const hiddenInput = label.querySelector('.sample-id-input');
                const textEl = label.querySelector('.sample-id-text');
                const printBtn = label.querySelector('.btn-print-sample');

                if (this.checked) {
                    const sampleId = generateNextSampleId();
                    hiddenInput.value = sampleId;
                    textEl.textContent = sampleId;
                    display.style.display = 'inline-flex';
                    printBtn.onclick = function(e) {
                        e.preventDefault();
                        printSampleLabel(getMrIdForForm(), sampleId, '');
                    };
                } else {
                    hiddenInput.value = '';
                    textEl.textContent = '';
                    display.style.display = 'none';
                }
            });
        });
    })();

    function printSampleLabel(mrId, sampleId, visitCode) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Sample Label</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .label { border: 2px solid #000; padding: 20px; width: 300px; margin: 0 auto; }
                    .label-header { text-align: center; font-weight: bold; font-size: 18px; margin-bottom: 15px; }
                    .label-row { margin: 10px 0; font-size: 14px; }
                    .label-label { font-weight: bold; }
                    .barcode { text-align: center; font-family: monospace; font-size: 16px; margin-top: 15px; letter-spacing: 3px; }
                </style>
            </head>
            <body>
                <div class="label">
                    <div class="label-header">HOSPITAL SAMPLE LABEL</div>
                    <div class="label-row">
                        <span class="label-label">MR ID:</span> ${mrId || 'N/A'}
                    </div>
                    ${sampleId ? `<div class="label-row"><span class="label-label">Sample ID:</span> ${sampleId}</div>` : ''}
                    ${visitCode ? `<div class="label-row"><span class="label-label">Visit Code:</span> ${visitCode}</div>` : ''}
                    <div class="label-row">
                        <span class="label-label">Date:</span> ${new Date().toLocaleDateString()}
                    </div>
                    <div class="barcode">${sampleId || mrId}</div>
                </div>
                <script>
                    window.print();
                    window.onafterprint = function() { window.close(); };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    function toggleVisitBalance(visitId) {
        const checkbox = document.getElementById('show_balance_' + visitId);
        const url = new URL(window.location.href);
        if (checkbox.checked) {
            url.searchParams.set('show_balance_visit', visitId);
        } else {
            url.searchParams.delete('show_balance_visit');
        }
        window.location.href = url.toString();
    }
    </script>
</body>
</html>