<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - PHARMACY MANAGEMENT
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

// Get all medications
$medications = $conn->query("SELECT * FROM medications WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get visits for prescription
$visits = $conn->query("SELECT v.visit_id, v.visit_code, p.first_name, p.last_name 
                        FROM visits v 
                        JOIN patients p ON v.patient_id = p.patient_id 
                        WHERE v.visit_status_id != (SELECT visit_status_id FROM lookup_visit_statuses WHERE name = 'Discharged')
                        ORDER BY v.admitted_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);

// Get doctors
$doctors = $conn->query("SELECT staff_id, first_name, last_name FROM staff WHERE role_id = (SELECT role_id FROM lookup_roles WHERE name = 'Doctor') AND is_active = 1")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// ADD MEDICATION
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_medication') {
    $query = "INSERT INTO medications (name, strength, unit, unit_price, stock_quantity, reorder_level) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssdii',
        $_POST['name'],
        $_POST['strength'],
        $_POST['unit'],
        $_POST['unit_price'],
        $_POST['stock_quantity'],
        $_POST['reorder_level']
    );
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Added Medication', "Added medication: {$_POST['name']}");
        $message = 'Medication added successfully!';
        header('Location: pharmacy.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to add medication. Please try again.';
    }
}

// ============================================================================
// UPDATE MEDICATION
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_medication') {
    $medicationId = intval($_POST['medication_id']);
    
    $query = "UPDATE medications SET 
              name = ?, strength = ?, unit = ?, unit_price = ?, 
              stock_quantity = ?, reorder_level = ?, is_active = ?
              WHERE medication_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssdiii',
        $_POST['name'],
        $_POST['strength'],
        $_POST['unit'],
        $_POST['unit_price'],
        $_POST['stock_quantity'],
        $_POST['reorder_level'],
        $_POST['is_active'],
        $medicationId
    );
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Medication', "Updated medication ID: {$medicationId}");
        $message = 'Medication updated successfully!';
        header('Location: pharmacy.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update medication. Please try again.';
    }
}

// ============================================================================
// CREATE PRESCRIPTION
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_prescription') {
    $data = [
        'visit_id' => intval($_POST['visit_id']),
        'prescribed_by' => intval($_POST['prescribed_by']),
        'status' => 'Pending'
    ];
    
    $prescriptionId = createPrescription($conn, $data);
    if ($prescriptionId) {
        // Add prescription items
        $medicationIds = $_POST['medication_id'] ?? [];
        $dosages = $_POST['dosage'] ?? [];
        $durations = $_POST['duration_days'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        
        for ($i = 0; $i < count($medicationIds); $i++) {
            if (!empty($medicationIds[$i])) {
                $itemData = [
                    'prescription_id' => $prescriptionId,
                    'medication_id' => intval($medicationIds[$i]),
                    'dosage' => sanitizeInput($dosages[$i] ?? ''),
                    'duration_days' => !empty($durations[$i]) ? intval($durations[$i]) : null,
                    'quantity' => intval($quantities[$i] ?? 1)
                ];
                if (addPrescriptionItem($conn, $itemData)) {
                    $priceStmt = $conn->prepare("SELECT name, strength, unit_price FROM medications WHERE medication_id = ?");
                    $priceStmt->bind_param('i', $itemData['medication_id']);
                    $priceStmt->execute();
                    $medication = $priceStmt->get_result()->fetch_assoc();
                    if ($medication) {
                        addInvoiceCharge($conn, $data['visit_id'], 'Medication: ' . $medication['name'] . ' ' . $medication['strength'], 'Medication', $itemData['quantity'], (float) $medication['unit_price']);
                    }
                }
            }
        }
        
        logUserActivity($conn, $_SESSION['user_id'], 'Created Prescription', "Created prescription ID: {$prescriptionId}");
        $message = 'Prescription created successfully!';
        header('Location: pharmacy.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create prescription. Please try again.';
    }
}

// ============================================================================
// DISPENSE PRESCRIPTION
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dispense') {
    $prescriptionId = intval($_POST['prescription_id']);
    
    // Get prescription items
    $items = getMedicationsByPrescription($conn, $prescriptionId);
    $canDispense = true;
    
    // Check stock
    foreach ($items as $item) {
        $stockQuery = "SELECT stock_quantity FROM medications WHERE medication_id = ?";
        $stockStmt = $conn->prepare($stockQuery);
        $stockStmt->bind_param('i', $item['medication_id']);
        $stockStmt->execute();
        $stockResult = $stockStmt->get_result();
        $stockRow = $stockResult->fetch_assoc();
        
        if ($stockRow['stock_quantity'] < $item['quantity']) {
            $canDispense = false;
            $error = 'Insufficient stock for: ' . $item['medication_name'];
            break;
        }
    }
    
    if ($canDispense) {
        // Update stock
        foreach ($items as $item) {
            updateMedicationStock($conn, $item['medication_id'], $item['quantity']);
        }
        
        // Update prescription status
        $query = "UPDATE prescriptions SET status = 'Dispensed', dispensed_by = ?, dispensed_at = NOW() WHERE prescription_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $_SESSION['user_id'], $prescriptionId);
        
        if ($stmt->execute()) {
            logUserActivity($conn, $_SESSION['user_id'], 'Dispensed Prescription', "Dispensed prescription ID: {$prescriptionId}");
            $message = 'Prescription dispensed successfully!';
            header('Location: pharmacy.php?message=' . urlencode($message));
            exit();
        } else {
            $error = 'Failed to dispense prescription. Please try again.';
        }
    }
}

// ============================================================================
// GET PRESCRIPTIONS
// ============================================================================
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

$query = "SELECT p.*, 
          CONCAT(pat.first_name, ' ', pat.last_name) as patient_name,
          pat.patient_code,
          CONCAT(s.first_name, ' ', s.last_name) as doctor_name,
          v.visit_code
          FROM prescriptions p
          JOIN visits v ON p.visit_id = v.visit_id
          JOIN patients pat ON v.patient_id = pat.patient_id
          JOIN staff s ON p.prescribed_by = s.staff_id
          WHERE 1=1";

$params = [];
$types = "";

if ($searchTerm) {
    $query .= " AND (pat.first_name LIKE ? OR pat.last_name LIKE ? OR v.visit_code LIKE ?)";
    $searchTermLike = "%{$searchTerm}%";
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $types .= "sss";
}

if ($filterStatus && $filterStatus !== 'All') {
    $query .= " AND p.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$query .= " ORDER BY p.prescribed_at DESC LIMIT 50";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$prescriptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get prescription items
$prescriptionItems = [];
if (!empty($prescriptions)) {
    $prescriptionIds = array_column($prescriptions, 'prescription_id');
    $ids = implode(',', $prescriptionIds);
    $itemQuery = "SELECT pi.*, m.name as medication_name, m.strength, m.unit
                  FROM prescription_items pi
                  JOIN medications m ON pi.medication_id = m.medication_id
                  WHERE pi.prescription_id IN ($ids)";
    $itemResult = $conn->query($itemQuery);
    while ($row = $itemResult->fetch_assoc()) {
        $prescriptionItems[$row['prescription_id']][] = $row;
    }
}

// Get edit data
$editMedication = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_medication' && isset($_GET['id'])) {
    $medId = intval($_GET['id']);
    $query = "SELECT * FROM medications WHERE medication_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $medId);
    $stmt->execute();
    $editMedication = $stmt->get_result()->fetch_assoc();
}

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Get low stock medications
$lowStock = $conn->query("SELECT * FROM medications WHERE stock_quantity <= reorder_level AND is_active = 1 ORDER BY stock_quantity ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy - HMS</title>
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
            display: <?php echo (isset($_GET['action']) && ($_GET['action'] === 'add_medication' || $_GET['action'] === 'edit_medication' || $_GET['action'] === 'create_prescription')) ? 'flex' : 'none'; ?>;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }
        .form-modal-content {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            max-width: 700px;
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
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
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
        .btn-dispense {
            background: #d1fae5;
            color: #059669;
        }
        .btn-dispense:hover {
            background: #a7f3d0;
        }
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }
        .btn-delete:hover {
            background: #fecaca;
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
        .btn-success {
            background: #22c55e;
            color: #fff;
        }
        .btn-success:hover {
            background: #16a34a;
        }
        .search-box-pharmacy {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-box-pharmacy input, .search-box-pharmacy select {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .search-box-pharmacy input:focus, .search-box-pharmacy select:focus {
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
        .status-badge-pharmacy {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-dispensed { background: #d1fae5; color: #059669; }
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
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .tab {
            padding: 10px 24px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            font-family: inherit;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tab:hover {
            color: #334155;
        }
        .tab.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .stock-low {
            color: #dc2626;
            font-weight: 600;
        }
        .stock-medium {
            color: #d97706;
            font-weight: 600;
        }
        .stock-high {
            color: #059669;
            font-weight: 600;
        }
        .medication-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .medication-item .med-name {
            font-weight: 500;
        }
        .medication-item .med-detail {
            font-size: 13px;
            color: #64748b;
        }
        .medication-item .med-price {
            font-weight: 600;
            color: #2563eb;
        }
        .add-item-btn {
            padding: 6px 12px;
            background: #dbeafe;
            color: #2563eb;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-family: inherit;
        }
        .add-item-btn:hover {
            background: #bfdbfe;
        }
        .remove-item-btn {
            padding: 4px 8px;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-family: inherit;
        }
        .remove-item-btn:hover {
            background: #fecaca;
        }
        .prescription-items-container {
            margin-top: 8px;
        }
        .prescription-item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 0.5fr;
            gap: 8px;
            align-items: end;
            margin-bottom: 8px;
        }
        .prescription-item-row .form-group {
            margin-bottom: 0;
        }
        .stock-warning {
            background: #fef2f2;
            border: 1px solid #fecaca;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .stock-warning h4 {
            color: #dc2626;
            margin-bottom: 8px;
        }
        .stock-warning ul {
            list-style: none;
            padding: 0;
        }
        .stock-warning ul li {
            padding: 4px 0;
            font-size: 14px;
        }
        .stock-warning ul li strong {
            color: #dc2626;
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
                    <h1>Pharmacy Management</h1>
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

            <!-- Low Stock Warning -->
            <?php if (!empty($lowStock)): ?>
            <div class="stock-warning">
                <h4><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h4>
                <ul>
                    <?php foreach ($lowStock as $item): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong> 
                            - Stock: <?php echo $item['stock_quantity']; ?> <?php echo $item['unit']; ?> 
                            (Reorder Level: <?php echo $item['reorder_level']; ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" data-tab="prescriptions">Prescriptions</button>
                <button class="tab" data-tab="medications">Medications</button>
            </div>

            <!-- Prescriptions Tab -->
            <div class="tab-content active" id="tab-prescriptions">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                    <div class="search-box-pharmacy">
                        <form method="GET" action="" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                            <input type="hidden" name="tab" value="prescriptions">
                            <input type="text" name="search" placeholder="Search prescriptions..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="width: 200px;">
                            <select name="status">
                                <option value="All">All Status</option>
                                <option value="Pending" <?php echo $filterStatus === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Dispensed" <?php echo $filterStatus === 'Dispensed' ? 'selected' : ''; ?>>Dispensed</option>
                                <option value="Cancelled" <?php echo $filterStatus === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if ($searchTerm || $filterStatus): ?>
                                <a href="pharmacy.php" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <button class="btn-create" onclick="window.location.href='pharmacy.php?action=create_prescription'">
                        <i class="fas fa-plus"></i> New Prescription
                    </button>
                </div>

                <!-- Status Filter Tabs -->
                <div class="filter-tabs">
                    <a href="pharmacy.php?tab=prescriptions" class="filter-tab <?php echo !$filterStatus ? 'active' : ''; ?>">All</a>
                    <a href="pharmacy.php?tab=prescriptions&status=Pending" class="filter-tab <?php echo $filterStatus === 'Pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="pharmacy.php?tab=prescriptions&status=Dispensed" class="filter-tab <?php echo $filterStatus === 'Dispensed' ? 'active' : ''; ?>">Dispensed</a>
                    <a href="pharmacy.php?tab=prescriptions&status=Cancelled" class="filter-tab <?php echo $filterStatus === 'Cancelled' ? 'active' : ''; ?>">Cancelled</a>
                </div>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Prescription ID</th>
                                    <th>Patient</th>
                                    <th>Visit</th>
                                    <th>Doctor</th>
                                    <th>Medications</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($prescriptions)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="fas fa-prescription-bottle" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No prescriptions found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($prescriptions as $prescription): ?>
                                    <tr>
                                        <td><strong>#<?php echo $prescription['prescription_id']; ?></strong></td>
                                        <td>
                                            <div class="patient-info">
                                                <div class="avatar" style="background: <?php echo getUserColor($prescription['patient_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                                    <?php echo strtoupper(substr($prescription['patient_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <span class="patient-name"><?php echo htmlspecialchars($prescription['patient_name']); ?></span>
                                                    <small><?php echo htmlspecialchars($prescription['patient_code']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($prescription['visit_code']); ?></td>
                                        <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                                        <td>
                                            <?php if (isset($prescriptionItems[$prescription['prescription_id']])): ?>
                                                <?php foreach ($prescriptionItems[$prescription['prescription_id']] as $item): ?>
                                                    <div style="font-size: 13px;">
                                                        <?php echo htmlspecialchars($item['medication_name']); ?>
                                                        (<?php echo $item['quantity']; ?> <?php echo $item['unit']; ?>)
                                                        <?php if ($item['dosage']): ?>
                                                            - <?php echo htmlspecialchars($item['dosage']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge-pharmacy status-<?php echo strtolower($prescription['status']); ?>">
                                                <?php echo htmlspecialchars($prescription['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($prescription['prescribed_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($prescription['status'] === 'Pending'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="action" value="dispense">
                                                        <input type="hidden" name="prescription_id" value="<?php echo $prescription['prescription_id']; ?>">
                                                        <button type="submit" class="btn-dispense" onclick="return confirm('Are you sure you want to dispense this prescription?');">
                                                            <i class="fas fa-check"></i> Dispense
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($prescription['status'] === 'Pending'): ?>
                                                    <a href="#" class="btn-delete" onclick="return confirm('Are you sure you want to cancel this prescription?');">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </a>
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
            </div>

            <!-- Medications Tab -->
            <div class="tab-content" id="tab-medications">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                    <div class="search-box-pharmacy">
                        <form method="GET" action="" style="display: flex; gap: 8px;">
                            <input type="hidden" name="tab" value="medications">
                            <input type="text" name="search_med" placeholder="Search medications..." value="<?php echo htmlspecialchars($_GET['search_med'] ?? ''); ?>" style="width: 250px;">
                            <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <button class="btn-create" onclick="window.location.href='pharmacy.php?action=add_medication'">
                        <i class="fas fa-plus"></i> Add Medication
                    </button>
                </div>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Medication</th>
                                    <th>Strength</th>
                                    <th>Unit</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Reorder Level</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $searchMed = isset($_GET['search_med']) ? sanitizeInput($_GET['search_med']) : '';
                                $meds = $medications;
                                if ($searchMed) {
                                    $meds = array_filter($meds, function($m) use ($searchMed) {
                                        return stripos($m['name'], $searchMed) !== false;
                                    });
                                }
                                ?>
                                <?php if (empty($meds)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="fas fa-pills" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No medications found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($meds as $med): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($med['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($med['strength'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($med['unit']); ?></td>
                                        <td>Birr <?php echo number_format($med['unit_price'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $stockClass = 'stock-high';
                                            if ($med['stock_quantity'] <= $med['reorder_level']) {
                                                $stockClass = 'stock-low';
                                            } elseif ($med['stock_quantity'] <= $med['reorder_level'] * 2) {
                                                $stockClass = 'stock-medium';
                                            }
                                            ?>
                                            <span class="<?php echo $stockClass; ?>">
                                                <?php echo $med['stock_quantity']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $med['reorder_level']; ?></td>
                                        <td>
                                            <span class="status-badge-doctor status-<?php echo $med['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $med['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="pharmacy.php?action=edit_medication&id=<?php echo $med['medication_id']; ?>" class="btn-edit">
                                                    <i class="fas fa-edit"></i> Edit
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
            </div>
        </main>
    </div>

    <!-- Add/Edit Medication Modal -->
    <?php if (isset($_GET['action']) && ($_GET['action'] === 'add_medication' || $_GET['action'] === 'edit_medication')): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='pharmacy.php'">&times;</button>
            <h2 style="margin-bottom: 24px;"><?php echo $_GET['action'] === 'add_medication' ? 'Add New Medication' : 'Edit Medication'; ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $_GET['action']; ?>">
                <?php if ($_GET['action'] === 'edit_medication'): ?>
                    <input type="hidden" name="medication_id" value="<?php echo $editMedication['medication_id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="name">Medication Name *</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($editMedication['name'] ?? ''); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="strength">Strength</label>
                        <input type="text" id="strength" name="strength" placeholder="e.g., 500mg" value="<?php echo htmlspecialchars($editMedication['strength'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="unit">Unit *</label>
                        <select id="unit" name="unit" required>
                            <option value="tablet" <?php echo (isset($editMedication['unit']) && $editMedication['unit'] === 'tablet') ? 'selected' : ''; ?>>Tablet</option>
                            <option value="capsule" <?php echo (isset($editMedication['unit']) && $editMedication['unit'] === 'capsule') ? 'selected' : ''; ?>>Capsule</option>
                            <option value="ml" <?php echo (isset($editMedication['unit']) && $editMedication['unit'] === 'ml') ? 'selected' : ''; ?>>ml</option>
                            <option value="mg" <?php echo (isset($editMedication['unit']) && $editMedication['unit'] === 'mg') ? 'selected' : ''; ?>>mg</option>
                            <option value="unit" <?php echo (isset($editMedication['unit']) && $editMedication['unit'] === 'unit') ? 'selected' : ''; ?>>Unit</option>
                            <option value="vial" <?php echo (isset($editMedication['unit']) && $editMedication['unit'] === 'vial') ? 'selected' : ''; ?>>Vial</option>
                            <option value="bottle" <?php echo (isset($editMedication['unit']) && $editMedication['unit'] === 'bottle') ? 'selected' : ''; ?>>Bottle</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="unit_price">Unit Price ($) *</label>
                        <input type="number" id="unit_price" name="unit_price" step="0.01" required value="<?php echo htmlspecialchars($editMedication['unit_price'] ?? '0.00'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity *</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" required value="<?php echo htmlspecialchars($editMedication['stock_quantity'] ?? '0'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="reorder_level">Reorder Level *</label>
                        <input type="number" id="reorder_level" name="reorder_level" required value="<?php echo htmlspecialchars($editMedication['reorder_level'] ?? '20'); ?>">
                    </div>
                    <?php if ($_GET['action'] === 'edit_medication'): ?>
                    <div class="form-group">
                        <label for="is_active">Status</label>
                        <select id="is_active" name="is_active">
                            <option value="1" <?php echo ($editMedication['is_active'] == 1) ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo ($editMedication['is_active'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <?php echo $_GET['action'] === 'add_medication' ? 'Add Medication' : 'Update Medication'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='pharmacy.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Create Prescription Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'create_prescription'): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='pharmacy.php'">&times;</button>
            <h2 style="margin-bottom: 24px;">Create New Prescription</h2>
            
            <form method="POST" action="" id="prescriptionForm">
                <input type="hidden" name="action" value="create_prescription">
                
                <div class="form-row">
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
                        <label for="prescribed_by">Prescribing Doctor *</label>
                        <select id="prescribed_by" name="prescribed_by" required>
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['staff_id']; ?>">
                                    <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Prescription Items</label>
                    <div class="prescription-items-container" id="prescriptionItems">
                        <div class="prescription-item-row">
                            <div class="form-group">
                                <label>Medication</label>
                                <select name="medication_id[]" class="medication-select" required>
                                    <option value="">Select Medication</option>
                                    <?php foreach ($medications as $med): ?>
                                        <option value="<?php echo $med['medication_id']; ?>">
                                            <?php echo htmlspecialchars($med['name'] . ' (' . $med['strength'] . ') - Stock: ' . $med['stock_quantity']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Dosage</label>
                                <input type="text" name="dosage[]" placeholder="e.g., 1 tablet twice daily">
                            </div>
                            <div class="form-group">
                                <label>Duration (days)</label>
                                <input type="number" name="duration_days[]" placeholder="7">
                            </div>
                            <div class="form-group">
                                <label>Qty</label>
                                <input type="number" name="quantity[]" value="1" min="1" required>
                            </div>
                            <div style="display: flex; align-items: end; padding-bottom: 4px;">
                                <button type="button" class="remove-item-btn" onclick="removePrescriptionItem(this)">×</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="add-item-btn" onclick="addPrescriptionItem()">
                        <i class="fas fa-plus"></i> Add Another Medication
                    </button>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Create Prescription
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='pharmacy.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
                
                // Update URL with tab parameter
                const url = new URL(window.location);
                url.searchParams.set('tab', this.dataset.tab);
                window.history.pushState({}, '', url);
            });
        });
        
        // Check for tab parameter on load
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam) {
            document.querySelectorAll('.tab').forEach(tab => {
                if (tab.dataset.tab === tabParam) {
                    tab.click();
                }
            });
        }
        
        // Prescription items management
        function addPrescriptionItem() {
            const container = document.getElementById('prescriptionItems');
            const row = container.querySelector('.prescription-item-row').cloneNode(true);
            
            // Clear values
            row.querySelectorAll('input, select').forEach(input => {
                if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                } else {
                    input.value = '';
                }
            });
            
            // Reset quantity to 1
            const qtyInput = row.querySelector('input[name="quantity[]"]');
            if (qtyInput) qtyInput.value = 1;
            
            // Show remove button
            const removeBtn = row.querySelector('.remove-item-btn');
            removeBtn.style.display = 'block';
            
            container.appendChild(row);
        }
        
        function removePrescriptionItem(btn) {
            const row = btn.closest('.prescription-item-row');
            const container = document.getElementById('prescriptionItems');
            if (container.querySelectorAll('.prescription-item-row').length > 1) {
                row.remove();
            } else {
                alert('You must have at least one medication in the prescription.');
            }
        }
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>