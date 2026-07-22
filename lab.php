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

// Get visits for dropdown
$visits = $conn->query("SELECT v.visit_id, v.visit_code, p.first_name, p.last_name 
                        FROM visits v 
                        JOIN patients p ON v.patient_id = p.patient_id 
                        ORDER BY v.admitted_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);

// Get test types
$testTypes = $conn->query("SELECT * FROM lookup_test_types WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

// Get order statuses
$orderStatuses = $conn->query("SELECT * FROM lookup_order_statuses")->fetch_all(MYSQLI_ASSOC);

// Get doctors for dropdown
$doctors = $conn->query("SELECT staff_id, first_name, last_name FROM staff WHERE role_id = (SELECT role_id FROM lookup_roles WHERE name = 'Doctor') AND is_active = 1")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// CREATE LAB ORDER
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $data = [
        'visit_id' => intval($_POST['visit_id']),
        'test_type_id' => intval($_POST['test_type_id']),
        'ordered_by' => intval($_POST['ordered_by']),
        'order_status_id' => getLookupId($conn, 'lookup_order_statuses', 'name', 'Ordered')
    ];
    
    $result = createLabOrder($conn, $data);
    if ($result) {
        logUserActivity($conn, $_SESSION['user_id'], 'Created Lab Order', "Created lab order ID: {$result}");
        $message = 'Lab order created successfully!';
        header('Location: lab.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create lab order. Please try again.';
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
// ADD LAB RESULT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_result') {
    $resultData = [
        'entered_by' => $_SESSION['user_id'],
        'result_value' => $_POST['result_value'],
        'result_notes' => sanitizeInput($_POST['result_notes'] ?? '')
    ];
    
    $result = updateLabResult($conn, intval($_POST['order_id']), $resultData);
    if ($result) {
        logUserActivity($conn, $_SESSION['user_id'], 'Added Lab Result', "Added result for order ID: {$_POST['order_id']}");
        $message = 'Lab result added successfully!';
        header('Location: lab.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to add lab result. Please try again.';
    }
}

// ============================================================================
// GET LAB ORDERS
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
          WHERE 1=1";

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
$labOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
                            <?php if (empty($labOrders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-flask" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        No lab orders found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($labOrders as $order): ?>
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
                                    <td><?php echo htmlspecialchars($order['category']); ?></td>
                                    <td><?php echo htmlspecialchars($order['ordered_by_name']); ?></td>
                                    <td>
                                        <span class="status-badge-lab status-<?php echo strtolower(str_replace(' ', '', $order['status_name'])); ?>">
                                            <?php echo htmlspecialchars($order['status_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($order['status_name'] === 'Ordered' || $order['status_name'] === 'Sample Collected'): ?>
                                                <form method="POST" action="" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                    <select name="status_id" onchange="this.form.submit()" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0; font-size: 12px;">
                                                        <?php foreach ($orderStatuses as $status): ?>
                                                            <option value="<?php echo $status['order_status_id']; ?>" <?php echo $status['order_status_id'] == $order['order_status_id'] ? 'selected' : ''; ?>>
                                                                <?php echo $status['name']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['status_name'] !== 'Result Ready' && $order['status_name'] !== 'Reviewed' && $order['status_name'] !== 'Cancelled'): ?>
                                                <a href="lab.php?action=result&id=<?php echo $order['order_id']; ?>" class="btn-result">
                                                    <i class="fas fa-plus"></i> Add Result
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (isset($labResults[$order['order_id']])): ?>
                                            <div class="result-box">
                                                <?php foreach ($labResults[$order['order_id']] as $result): ?>
                                                    <p><span class="result-label">Result:</span> <?php echo htmlspecialchars($result['result_value']); ?></p>
                                                    <?php if ($result['result_notes']): ?>
                                                        <p><span class="result-label">Notes:</span> <?php echo htmlspecialchars($result['result_notes']); ?></p>
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

    <!-- Create Lab Order Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'create'): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='lab.php'">&times;</button>
            <h2 style="margin-bottom: 24px;">Create New Lab Order</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label for="visit_id">Visit *</label>
                    <select id="visit_id" name="visit_id" required>
                        <option value="">Select Visit</option>
                        <?php foreach ($visits as $visit): ?>
                            <option value="<?php echo $visit['visit_id']; ?>">
                                <?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['first_name'] . ' ' . $visit['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="test_type_id">Test Type *</label>
                    <select id="test_type_id" name="test_type_id" required>
                        <option value="">Select Test</option>
                        <?php foreach ($testTypes as $test): ?>
                            <option value="<?php echo $test['test_type_id']; ?>">
                                <?php echo htmlspecialchars($test['name'] . ' (' . $test['category'] . ') - $' . number_format($test['price'], 2)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="ordered_by">Ordering Doctor *</label>
                    <select id="ordered_by" name="ordered_by" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['staff_id']; ?>">
                                <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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