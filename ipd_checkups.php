<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - IPD CHECKUPS
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

// ============================================================================
// CREATE CHECKUP
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_checkup') {
    $visitId = intval($_POST['visit_id']);
    $patientId = intval($_POST['patient_id']);
    $recordedBy = intval($_SESSION['user_id']);

    $progressNotes = sanitizeInput($_POST['progress_notes'] ?? '');
    $glucoseLevel = !empty($_POST['glucose_level']) ? floatval($_POST['glucose_level']) : null;
    $glucoseUnit = sanitizeInput($_POST['glucose_unit'] ?? 'mg/dL');
    $glucoseType = sanitizeInput($_POST['glucose_type'] ?? 'Random');
    $injectionGiven = isset($_POST['injection_given']) ? 1 : 0;
    $injectionType = sanitizeInput($_POST['injection_type'] ?? '');
    $injectionDosage = sanitizeInput($_POST['injection_dosage'] ?? '');
    $medicineGiven = isset($_POST['medicine_given']) ? 1 : 0;
    $medicineNotes = sanitizeInput($_POST['medicine_notes'] ?? '');
    $materialGiven = isset($_POST['material_given']) ? 1 : 0;
    $materialNotes = sanitizeInput($_POST['material_notes'] ?? '');
    $vitalSigns = sanitizeInput($_POST['vital_signs'] ?? '');

    $checkupQuery = "INSERT INTO ipd_checkups (visit_id, patient_id, recorded_by, progress_notes, glucose_level, glucose_unit, glucose_type, injection_given, injection_type, injection_dosage, medicine_given, medicine_notes, material_given, material_notes, vital_signs)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $checkupStmt = $conn->prepare($checkupQuery);
    $checkupStmt->bind_param('iisdsdsississs',
        $visitId,
        $patientId,
        $recordedBy,
        $progressNotes,
        $glucoseLevel,
        $glucoseUnit,
        $glucoseType,
        $injectionGiven,
        $injectionType,
        $injectionDosage,
        $medicineGiven,
        $medicineNotes,
        $materialGiven,
        $materialNotes,
        $vitalSigns
    );

    if ($checkupStmt->execute()) {
        $checkupId = $conn->insert_id;

        // Handle medicine administration
        if ($medicineGiven && isset($_POST['medications']) && is_array($_POST['medications'])) {
            foreach ($_POST['medications'] as $med) {
                if (!empty($med['medication_id'])) {
                    $medQuery = "INSERT INTO ipd_medicine_administration (checkup_id, visit_id, medication_id, dosage, quantity, administered_by, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $medStmt = $conn->prepare($medQuery);
                    $medStmt->bind_param('iiisiis',
                        $checkupId,
                        $visitId,
                        intval($med['medication_id']),
                        sanitizeInput($med['dosage'] ?? ''),
                        intval($med['quantity'] ?? 1),
                        $recordedBy,
                        sanitizeInput($med['notes'] ?? '')
                    );
                    $medStmt->execute();
                }
            }
        }

        // Handle material administration
        if ($materialGiven && isset($_POST['materials']) && is_array($_POST['materials'])) {
            foreach ($_POST['materials'] as $mat) {
                if (!empty($mat['material_id'])) {
                    $matQuery = "INSERT INTO ipd_material_administration (checkup_id, visit_id, material_id, quantity_used, unit_cost, administered_by, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $matStmt = $conn->prepare($matQuery);
                    $matStmt->bind_param('iiisiis',
                        $checkupId,
                        $visitId,
                        intval($mat['material_id']),
                        intval($mat['quantity_used'] ?? 1),
                        floatval($mat['unit_cost'] ?? 0),
                        $recordedBy,
                        sanitizeInput($mat['notes'] ?? '')
                    );
                    if ($matStmt->execute()) {
                        // Update material stock
                        $updateStockQuery = "UPDATE materials SET stock_quantity = stock_quantity - ? WHERE material_id = ?";
                        $updateStockStmt = $conn->prepare($updateStockQuery);
                        $updateStockStmt->bind_param('ii', intval($mat['quantity_used'] ?? 1), intval($mat['material_id']));
                        $updateStockStmt->execute();

                        // Record material usage
                        $usageQuery = "INSERT INTO material_usage (visit_id, material_id, quantity_used, unit_cost, total_cost, used_at, used_by)
                                     VALUES (?, ?, ?, ?, ?, NOW(), ?)";
                        $usageStmt = $conn->prepare($usageQuery);
                        $totalCost = (intval($mat['quantity_used'] ?? 1) * floatval($mat['unit_cost'] ?? 0));
                        $usageStmt->bind_param('iiiddi',
                            $visitId,
                            intval($mat['material_id']),
                            intval($mat['quantity_used'] ?? 1),
                            floatval($mat['unit_cost'] ?? 0),
                            $totalCost,
                            $recordedBy
                        );
                        $usageStmt->execute();
                    }
                }
            }
        }

        logUserActivity($conn, $_SESSION['user_id'], 'Created IPD Checkup', "Created checkup ID: {$checkupId} for visit ID: {$visitId}");
        $message = 'Checkup recorded successfully!';
        header('Location: ipd_checkups.php?visit_id=' . $visitId . '&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to record checkup. Please try again.';
    }
}

// ============================================================================
// DELETE CHECKUP
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete_checkup' && isset($_GET['id'])) {
    $checkupId = intval($_GET['id']);

    $query = "DELETE FROM ipd_checkups WHERE checkup_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $checkupId);

    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Deleted IPD Checkup', "Deleted checkup ID: {$checkupId}");
        $message = 'Checkup deleted successfully!';
        header('Location: ipd_checkups.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to delete checkup. Please try again.';
    }
}

// ============================================================================
// GET CHECKUP (AJAX)
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_checkup' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $checkupId = intval($_GET['id']);
    
    $query = "SELECT * FROM ipd_checkups WHERE checkup_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $checkupId);
    $stmt->execute();
    $checkup = $stmt->get_result()->fetch_assoc();
    
    if ($checkup) {
        // Get medicines administered
        $medQuery = "SELECT ima.*, m.name as medication_name, m.strength, m.unit
                     FROM ipd_medicine_administration ima
                     JOIN medications m ON ima.medication_id = m.medication_id
                     WHERE ima.checkup_id = ?";
        $medStmt = $conn->prepare($medQuery);
        $medStmt->bind_param('i', $checkupId);
        $medStmt->execute();
        $medicines = $medStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get materials used
        $matQuery = "SELECT ima.*, m.name as material_name, m.unit
                     FROM ipd_material_administration ima
                     JOIN materials m ON ima.material_id = m.material_id
                     WHERE ima.checkup_id = ?";
        $matStmt = $conn->prepare($matQuery);
        $matStmt->bind_param('i', $checkupId);
        $matStmt->execute();
        $materials = $matStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'checkup' => $checkup, 'medicines' => $medicines, 'materials' => $materials]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Checkup not found']);
    }
    exit();
}

// ============================================================================
// UPDATE CHECKUP
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_checkup') {
    $checkupId = intval($_POST['checkup_id']);
    $visitId = intval($_POST['visit_id']);
    $patientId = intval($_POST['patient_id']);
    $recordedBy = intval($_SESSION['user_id']);

    $progressNotes = sanitizeInput($_POST['progress_notes'] ?? '');
    $glucoseLevel = !empty($_POST['glucose_level']) ? floatval($_POST['glucose_level']) : null;
    $glucoseUnit = sanitizeInput($_POST['glucose_unit'] ?? 'mg/dL');
    $glucoseType = sanitizeInput($_POST['glucose_type'] ?? 'Random');
    $injectionGiven = isset($_POST['injection_given']) ? 1 : 0;
    $injectionType = sanitizeInput($_POST['injection_type'] ?? '');
    $injectionDosage = sanitizeInput($_POST['injection_dosage'] ?? '');
    $medicineGiven = isset($_POST['medicine_given']) ? 1 : 0;
    $medicineNotes = sanitizeInput($_POST['medicine_notes'] ?? '');
    $materialGiven = isset($_POST['material_given']) ? 1 : 0;
    $materialNotes = sanitizeInput($_POST['material_notes'] ?? '');
    $vitalSigns = sanitizeInput($_POST['vital_signs'] ?? '');

    $checkupQuery = "UPDATE ipd_checkups SET progress_notes = ?, glucose_level = ?, glucose_unit = ?, glucose_type = ?, injection_given = ?, injection_type = ?, injection_dosage = ?, medicine_given = ?, medicine_notes = ?, material_given = ?, material_notes = ?, vital_signs = ? WHERE checkup_id = ?";
    $checkupStmt = $conn->prepare($checkupQuery);
    $checkupStmt->bind_param('sdssississsi',
        $progressNotes,
        $glucoseLevel,
        $glucoseUnit,
        $glucoseType,
        $injectionGiven,
        $injectionType,
        $injectionDosage,
        $medicineGiven,
        $medicineNotes,
        $materialGiven,
        $materialNotes,
        $vitalSigns,
        $checkupId
    );

    if ($checkupStmt->execute()) {
        // Delete existing medicine and material administrations
        $conn->prepare("DELETE FROM ipd_medicine_administration WHERE checkup_id = ?")->bind_param('i', $checkupId)->execute();
        $conn->prepare("DELETE FROM ipd_material_administration WHERE checkup_id = ?")->bind_param('i', $checkupId)->execute();

        // Handle medicine administration
        if ($medicineGiven && isset($_POST['medications']) && is_array($_POST['medications'])) {
            foreach ($_POST['medications'] as $med) {
                if (!empty($med['medication_id'])) {
                    $medQuery = "INSERT INTO ipd_medicine_administration (checkup_id, visit_id, medication_id, dosage, quantity, administered_by, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $medStmt = $conn->prepare($medQuery);
                    $medStmt->bind_param('iiisiis',
                        $checkupId,
                        $visitId,
                        intval($med['medication_id']),
                        sanitizeInput($med['dosage'] ?? ''),
                        intval($med['quantity'] ?? 1),
                        $recordedBy,
                        sanitizeInput($med['notes'] ?? '')
                    );
                    $medStmt->execute();
                }
            }
        }

        // Handle material administration
        if ($materialGiven && isset($_POST['materials']) && is_array($_POST['materials'])) {
            foreach ($_POST['materials'] as $mat) {
                if (!empty($mat['material_id'])) {
                    $matQuery = "INSERT INTO ipd_material_administration (checkup_id, visit_id, material_id, quantity_used, unit_cost, administered_by, notes)
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $matStmt = $conn->prepare($matQuery);
                    $matStmt->bind_param('iiisiis',
                        $checkupId,
                        $visitId,
                        intval($mat['material_id']),
                        intval($mat['quantity_used'] ?? 1),
                        floatval($mat['unit_cost'] ?? 0),
                        $recordedBy,
                        sanitizeInput($mat['notes'] ?? '')
                    );
                    if ($matStmt->execute()) {
                        // Update material stock
                        $updateStockQuery = "UPDATE materials SET stock_quantity = stock_quantity - ? WHERE material_id = ?";
                        $updateStockStmt = $conn->prepare($updateStockQuery);
                        $updateStockStmt->bind_param('ii', intval($mat['quantity_used'] ?? 1), intval($mat['material_id']));
                        $updateStockStmt->execute();

                        // Record material usage
                        $usageQuery = "INSERT INTO material_usage (visit_id, material_id, quantity_used, unit_cost, total_cost, used_at, used_by)
                                     VALUES (?, ?, ?, ?, ?, NOW(), ?)";
                        $usageStmt = $conn->prepare($usageQuery);
                        $totalCost = (intval($mat['quantity_used'] ?? 1) * floatval($mat['unit_cost'] ?? 0));
                        $usageStmt->bind_param('iiiddi',
                            $visitId,
                            intval($mat['material_id']),
                            intval($mat['quantity_used'] ?? 1),
                            floatval($mat['unit_cost'] ?? 0),
                            $totalCost,
                            $recordedBy
                        );
                        $usageStmt->execute();
                    }
                }
            }
        }

        logUserActivity($conn, $_SESSION['user_id'], 'Updated IPD Checkup', "Updated checkup ID: {$checkupId} for visit ID: {$visitId}");
        $message = 'Checkup updated successfully!';
        header('Location: ipd_checkups.php?visit_id=' . $visitId . '&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update checkup. Please try again.';
    }
}

// Get IPD patients (patients with bed assignments)
$ipdPatientsQuery = "SELECT v.visit_id, v.visit_code, v.admitted_at,
                     p.patient_id, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_code,
                     b.bed_number, w.name as ward_name,
                     ir.ipd_record_id, ir.status as ipd_status,
                     CONCAT(d.first_name, ' ', d.last_name) as attending_doctor
                     FROM ipd_records ir
                     JOIN visits v ON ir.visit_id = v.visit_id
                     JOIN patients p ON ir.patient_id = p.patient_id
                     LEFT JOIN beds b ON ir.bed_id = b.bed_id
                     LEFT JOIN wards w ON ir.ward_id = w.ward_id
                     LEFT JOIN staff d ON ir.attending_doctor_id = d.staff_id
                     WHERE ir.status = 'Admitted'
                     ORDER BY v.admitted_at DESC";
$ipdPatients = $conn->query($ipdPatientsQuery)->fetch_all(MYSQLI_ASSOC);

// Get checkups for selected visit
$checkups = [];
$selectedPatient = null;
if ($selectedVisitId > 0) {
    $checkupQuery = "SELECT ic.*, CONCAT(s.first_name, ' ', s.last_name) as recorded_by_name
                     FROM ipd_checkups ic
                     LEFT JOIN staff s ON ic.recorded_by = s.staff_id
                     WHERE ic.visit_id = ?
                     ORDER BY ic.checkup_time DESC";
    $checkupStmt = $conn->prepare($checkupQuery);
    $checkupStmt->bind_param('i', $selectedVisitId);
    $checkupStmt->execute();
    $checkups = $checkupStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get patient info
    foreach ($ipdPatients as $patient) {
        if ($patient['visit_id'] == $selectedVisitId) {
            $selectedPatient = $patient;
            break;
        }
    }
}

// Get medications for dropdown
$medications = $conn->query("SELECT medication_id, name, strength, unit, stock_quantity FROM medications WHERE is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Get materials for dropdown
$materials = $conn->query("SELECT material_id, name, unit_price, stock_quantity FROM materials WHERE is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPD Checkups - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .checkup-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }
        .checkup-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .checkup-time {
            font-size: 13px;
            color: #64748b;
        }
        .checkup-notes {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        .vital-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 6px;
            font-size: 12px;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        .glucose-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #fef3c7;
            color: #92400e;
            border-radius: 6px;
            font-size: 12px;
        }
        .injection-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #fce7f3;
            color: #9d174d;
            border-radius: 6px;
            font-size: 12px;
        }
        .medicine-list {
            background: #f0fdf4;
            padding: 12px;
            border-radius: 8px;
            margin-top: 8px;
        }
        .medicine-item {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #dcfce7;
        }
        .medicine-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="header">
                <div class="header-left">
                    <h1><i class="fas fa-clipboard-user"></i> IPD Checkups</h1>
                    <p>Track patient progress, glucose levels, injections, and medication administration</p>
                </div>
                <div class="header-right">
                    <div class="user-profile">
                        <div class="avatar"><?php echo $userInitial; ?></div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($userRole); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success" style="background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error" style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$selectedVisitId): ?>
                <div class="table-card">
                    <h2 style="margin-bottom: 20px;">IPD Patients</h2>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Visit</th>
                                    <th>Ward/Bed</th>
                                    <th>Attending Doctor</th>
                                    <th>Admitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ipdPatients)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="fas fa-bed" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No IPD patients found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ipdPatients as $patient): ?>
                                    <tr>
                                        <td>
                                            <div class="patient-info">
                                                <div class="avatar" style="background: #3b82f6; width: 32px; height: 32px; font-size: 12px;">
                                                    <?php echo strtoupper(substr($patient['patient_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <span class="patient-name"><?php echo htmlspecialchars($patient['patient_name']); ?></span>
                                                    <small><?php echo htmlspecialchars($patient['patient_code']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['visit_code']); ?></td>
                                        <td><?php echo htmlspecialchars($patient['ward_name'] . ' / Bed ' . $patient['bed_number']); ?></td>
                                        <td><?php echo htmlspecialchars($patient['attending_doctor'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($patient['admitted_at'])); ?></td>
                                        <td>
                                            <a href="ipd_checkups.php?visit_id=<?php echo $patient['visit_id']; ?>" class="btn-create-action">
                                                <i class="fas fa-clipboard-list"></i> View Checkups
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 20px;">
                    <a href="ipd_patients.php" class="btn-cancel" style="display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-arrow-left"></i> Back to IPD Patients
                    </a>
                </div>

                <?php if ($selectedPatient): ?>
                <div class="table-card" style="margin-bottom: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h2 style="margin: 0; color: white;"><?php echo htmlspecialchars($selectedPatient['patient_name']); ?></h2>
                            <p style="margin: 4px 0 0 0; opacity: 0.9;">
                                <?php echo htmlspecialchars($selectedPatient['patient_code']); ?> ·
                                <?php echo htmlspecialchars($selectedPatient['visit_code']); ?> ·
                                <?php echo htmlspecialchars($selectedPatient['ward_name'] ?? 'N/A'); ?> / Bed <?php echo htmlspecialchars($selectedPatient['bed_number'] ?? 'N/A'); ?>
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <p style="margin: 0; opacity: 0.9;">Attending Doctor</p>
                            <p style="margin: 4px 0 0 0; font-weight: 600;"><?php echo htmlspecialchars($selectedPatient['attending_doctor'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="table-card">
                    <h2 style="margin-bottom: 20px;">New Checkup</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_checkup">
                        <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                        <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patient_id'] ?? 0; ?>">

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label for="checkup_time">Checkup Time</label>
                                <input type="datetime-local" id="checkup_time" name="checkup_time" value="<?php echo date('Y-m-d\TH:i'); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="progress_notes">Progress Notes</label>
                            <textarea id="progress_notes" name="progress_notes" rows="4" placeholder="Detailed progress notes, observations, and updates..." style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="vital_signs">Vital Signs</label>
                            <textarea id="vital_signs" name="vital_signs" rows="2" placeholder="e.g., BP: 120/80, Pulse: 72, Temp: 98.6°F, SpO2: 98%" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex: 1;">
                                <label for="glucose_level">Glucose Level</label>
                                <input type="number" id="glucose_level" name="glucose_level" step="0.1" placeholder="e.g., 120" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="glucose_unit">Unit</label>
                                <select id="glucose_unit" name="glucose_unit" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                    <option value="mg/dL">mg/dL</option>
                                    <option value="mmol/L">mmol/L</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1;">
                                <label for="glucose_type">Type</label>
                                <select id="glucose_type" name="glucose_type" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                    <option value="Random">Random</option>
                                    <option value="Fasting">Fasting</option>
                                    <option value="Postprandial">Postprandial</option>
                                    <option value="HbA1c">HbA1c</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="background: #fef2f2; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                <input type="checkbox" name="injection_given" value="1" id="injection_given" onchange="toggleInjectionFields()">
                                <strong>Injection Given</strong>
                            </label>
                            <div id="injection_fields" style="display: none; margin-top: 12px;">
                                <div class="form-row">
                                    <div class="form-group" style="flex: 1;">
                                        <label for="injection_type">Injection Type</label>
                                        <input type="text" id="injection_type" name="injection_type" placeholder="e.g., Insulin, Antibiotic" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                    </div>
                                    <div class="form-group" style="flex: 1;">
                                        <label for="injection_dosage">Dosage</label>
                                        <input type="text" id="injection_dosage" name="injection_dosage" placeholder="e.g., 10 units, 500mg" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="background: #f0fdf4; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                <input type="checkbox" name="medicine_given" value="1" id="medicine_given" onchange="toggleMedicineFields()">
                                <strong>Medicine Administered</strong>
                            </label>
                            <div id="medicine_fields" style="display: none; margin-top: 12px;">
                                <div id="medication_container">
                                    <div class="form-row" style="margin-bottom: 12px;">
                                        <div class="form-group" style="flex: 2;">
                                            <label>Medication</label>
                                            <select name="medications[0][medication_id]" required style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                                <option value="">Select medication...</option>
                                                <?php foreach ($medications as $med): ?>
                                                    <option value="<?php echo $med['medication_id']; ?>">
                                                        <?php echo htmlspecialchars($med['name'] . ' ' . $med['strength'] . ' ' . $med['unit']); ?> (Stock: <?php echo $med['stock_quantity']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label>Dosage</label>
                                            <input type="text" name="medications[0][dosage]" placeholder="e.g., 500mg" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label>Quantity</label>
                                            <input type="number" name="medications[0][quantity]" min="1" value="1" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <label>Notes</label>
                                            <input type="text" name="medications[0][notes]" placeholder="Optional notes" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        </div>
                                    </div>
                                </div>
                                <button type="button" onclick="addMedicationRow()" class="btn-cancel" style="margin-top: 8px; width: 100%; border-style: dashed;">
                                    <i class="fas fa-plus"></i> Add Another Medication
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="medicine_notes">General Medicine Notes</label>
                            <textarea id="medicine_notes" name="medicine_notes" rows="2" placeholder="Additional notes about medication administration..." style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;"></textarea>
                        </div>

                        <div class="form-group" style="background: #fef3c7; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                <input type="checkbox" name="material_given" value="1" id="material_given" onchange="toggleMaterialFields()">
                                <strong>Material Used</strong>
                            </label>
                            <div id="material_fields" style="display: none; margin-top: 12px;">
                                <div id="material_container">
                                    <div class="form-row" style="margin-bottom: 12px;">
                                        <div class="form-group" style="flex: 2;">
                                            <label>Material</label>
                                            <select name="materials[0][material_id]" required style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                                <option value="">Select material...</option>
                                                <?php foreach ($materials as $mat): ?>
                                                    <option value="<?php echo $mat['material_id']; ?>" data-unit-cost="<?php echo $mat['unit_price']; ?>">
                                                        <?php echo htmlspecialchars($mat['name']); ?> (Stock: <?php echo $mat['stock_quantity']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label>Quantity</label>
                                            <input type="number" name="materials[0][quantity_used]" min="1" value="1" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label>Unit Cost</label>
                                            <input type="number" name="materials[0][unit_cost]" step="0.01" placeholder="0.00" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <label>Notes</label>
                                            <input type="text" name="materials[0][notes]" placeholder="Optional notes" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        </div>
                                    </div>
                                </div>
                                <button type="button" onclick="addMaterialRow()" class="btn-cancel" style="margin-top: 8px; width: 100%; border-style: dashed;">
                                    <i class="fas fa-plus"></i> Add Another Material
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="material_notes">General Material Notes</label>
                            <textarea id="material_notes" name="material_notes" rows="2" placeholder="Additional notes about material usage..." style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;"></textarea>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> Save Checkup
                            </button>
                            <button type="button" class="btn-cancel" onclick="window.location.href='ipd_checkups.php?visit_id=<?php echo $selectedVisitId; ?>'">Cancel</button>
                        </div>
                    </form>
                </div>

                <!-- Edit Checkup Modal -->
                <div class="form-modal" id="editCheckupModal" style="display: none;">
                    <div class="form-modal-content" style="max-width: 900px;">
                        <button class="close-btn" onclick="closeEditCheckupModal()">&times;</button>
                        <h2 style="margin-bottom: 24px;">Edit Checkup</h2>
                        <form method="POST" action="" id="editCheckupForm">
                            <input type="hidden" name="action" value="update_checkup">
                            <input type="hidden" name="checkup_id" id="edit_checkup_id">
                            <input type="hidden" name="visit_id" id="edit_visit_id">
                            <input type="hidden" name="patient_id" id="edit_patient_id">

                            <div class="form-group">
                                <label for="edit_progress_notes">Progress Notes</label>
                                <textarea id="edit_progress_notes" name="progress_notes" rows="4" placeholder="Detailed progress notes, observations, and updates..." style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="edit_vital_signs">Vital Signs</label>
                                <textarea id="edit_vital_signs" name="vital_signs" rows="2" placeholder="e.g., BP: 120/80, Pulse: 72, Temp: 98.6°F, SpO2: 98%" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;"></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="flex: 1;">
                                    <label for="edit_glucose_level">Glucose Level</label>
                                    <input type="number" id="edit_glucose_level" name="glucose_level" step="0.1" placeholder="e.g., 120" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="edit_glucose_unit">Unit</label>
                                    <select id="edit_glucose_unit" name="glucose_unit" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        <option value="mg/dL">mg/dL</option>
                                        <option value="mmol/L">mmol/L</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="edit_glucose_type">Type</label>
                                    <select id="edit_glucose_type" name="glucose_type" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        <option value="Random">Random</option>
                                        <option value="Fasting">Fasting</option>
                                        <option value="Postprandial">Postprandial</option>
                                        <option value="HbA1c">HbA1c</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group" style="background: #fef2f2; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                    <input type="checkbox" name="injection_given" value="1" id="edit_injection_given" onchange="toggleEditInjectionFields()">
                                    <strong>Injection Given</strong>
                                </label>
                                <div id="edit_injection_fields" style="display: none; margin-top: 12px;">
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1;">
                                            <label for="edit_injection_type">Injection Type</label>
                                            <input type="text" id="edit_injection_type" name="injection_type" placeholder="e.g., Insulin, Antibiotic" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="edit_injection_dosage">Dosage</label>
                                            <input type="text" id="edit_injection_dosage" name="injection_dosage" placeholder="e.g., 10 units, 500mg" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="background: #f0fdf4; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                    <input type="checkbox" name="medicine_given" value="1" id="edit_medicine_given" onchange="toggleEditMedicineFields()">
                                    <strong>Medicine Administered</strong>
                                </label>
                                <div id="edit_medicine_fields" style="display: none; margin-top: 12px;">
                                    <div id="edit_medication_container">
                                        <div class="form-row" style="margin-bottom: 12px;">
                                            <div class="form-group" style="flex: 2;">
                                                <label>Medication</label>
                                                <select name="medications[0][medication_id]" required style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                                    <option value="">Select medication...</option>
                                                    <?php foreach ($medications as $med): ?>
                                                        <option value="<?php echo $med['medication_id']; ?>">
                                                            <?php echo htmlspecialchars($med['name'] . ' ' . $med['strength'] . ' ' . $med['unit']); ?> (Stock: <?php echo $med['stock_quantity']; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group" style="flex: 1;">
                                                <label>Dosage</label>
                                                <input type="text" name="medications[0][dosage]" placeholder="e.g., 500mg" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                            </div>
                                            <div class="form-group" style="flex: 1;">
                                                <label>Quantity</label>
                                                <input type="number" name="medications[0][quantity]" min="1" value="1" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                            </div>
                                            <div class="form-group" style="flex: 2;">
                                                <label>Notes</label>
                                                <input type="text" name="medications[0][notes]" placeholder="Optional notes" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="addEditMedicationRow()" class="btn-cancel" style="margin-top: 8px; width: 100%; border-style: dashed;">
                                        <i class="fas fa-plus"></i> Add Another Medication
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="edit_medicine_notes">General Medicine Notes</label>
                                <textarea id="edit_medicine_notes" name="medicine_notes" rows="2" placeholder="Additional notes about medication administration..." style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;"></textarea>
                            </div>

                            <div class="form-group" style="background: #fef3c7; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                    <input type="checkbox" name="material_given" value="1" id="edit_material_given" onchange="toggleEditMaterialFields()">
                                    <strong>Material Used</strong>
                                </label>
                                <div id="edit_material_fields" style="display: none; margin-top: 12px;">
                                    <div id="edit_material_container">
                                        <div class="form-row" style="margin-bottom: 12px;">
                                            <div class="form-group" style="flex: 2;">
                                                <label>Material</label>
                                                <select name="materials[0][material_id]" required style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                                    <option value="">Select material...</option>
                                                    <?php foreach ($materials as $mat): ?>
                                                        <option value="<?php echo $mat['material_id']; ?>" data-unit-cost="<?php echo $mat['unit_price']; ?>">
                                                            <?php echo htmlspecialchars($mat['name']); ?> (Stock: <?php echo $mat['stock_quantity']; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group" style="flex: 1;">
                                                <label>Quantity</label>
                                                <input type="number" name="materials[0][quantity_used]" min="1" value="1" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                            </div>
                                            <div class="form-group" style="flex: 1;">
                                                <label>Unit Cost</label>
                                                <input type="number" name="materials[0][unit_cost]" step="0.01" placeholder="0.00" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                            </div>
                                            <div class="form-group" style="flex: 2;">
                                                <label>Notes</label>
                                                <input type="text" name="materials[0][notes]" placeholder="Optional notes" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="addEditMaterialRow()" class="btn-cancel" style="margin-top: 8px; width: 100%; border-style: dashed;">
                                        <i class="fas fa-plus"></i> Add Another Material
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="edit_material_notes">General Material Notes</label>
                                <textarea id="edit_material_notes" name="material_notes" rows="2" placeholder="Additional notes about material usage..." style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;"></textarea>
                            </div>

                            <div class="btn-group">
                                <button type="submit" class="btn-submit">
                                    <i class="fas fa-save"></i> Update Checkup
                                </button>
                                <button type="button" class="btn-cancel" onclick="closeEditCheckupModal()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-card">
                    <h2 style="margin-bottom: 20px;">Checkup History</h2>
                    <?php if (empty($checkups)): ?>
                        <p style="color: #94a3b8; padding: 40px; text-align: center;">
                            <i class="fas fa-clipboard-list" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            No checkups recorded yet
                        </p>
                    <?php else: ?>
                        <?php foreach ($checkups as $checkup): ?>
                            <div class="checkup-card">
                                <div class="checkup-header">
                                    <div>
                                        <strong><?php echo htmlspecialchars($checkup['recorded_by_name']); ?></strong>
                                        <span class="checkup-time"><?php echo date('M d, Y g:i A', strtotime($checkup['checkup_time'])); ?></span>
                                    </div>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-edit" onclick="editCheckup(<?php echo $checkup['checkup_id']; ?>)" style="padding: 4px 8px; font-size: 12px;">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="ipd_checkups.php?action=delete_checkup&id=<?php echo $checkup['checkup_id']; ?>&visit_id=<?php echo $selectedVisitId; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this checkup?');" style="padding: 4px 8px; font-size: 12px;">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>

                                <?php if ($checkup['progress_notes']): ?>
                                    <div class="checkup-notes">
                                        <strong>Progress Notes:</strong>
                                        <p style="margin: 4px 0 0 0;"><?php echo nl2br(htmlspecialchars($checkup['progress_notes'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($checkup['vital_signs']): ?>
                                    <div style="margin-bottom: 12px;">
                                        <span class="vital-badge"><i class="fas fa-heartbeat"></i> <?php echo htmlspecialchars($checkup['vital_signs']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($checkup['glucose_level']): ?>
                                    <div style="margin-bottom: 12px;">
                                        <span class="glucose-badge"><i class="fas fa-tint"></i> Glucose: <?php echo $checkup['glucose_level']; ?> <?php echo htmlspecialchars($checkup['glucose_unit']); ?> (<?php echo htmlspecialchars($checkup['glucose_type']); ?>)</span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($checkup['injection_given']): ?>
                                    <div style="margin-bottom: 12px;">
                                        <span class="injection-badge"><i class="fas fa-syringe"></i> Injection: <?php echo htmlspecialchars($checkup['injection_type']); ?> - <?php echo htmlspecialchars($checkup['injection_dosage']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($checkup['medicine_given']): ?>
                                    <div class="medicine-list">
                                        <strong><i class="fas fa-pills"></i> Medicines Administered</strong>
                                        <?php
                                        $medAdminQuery = "SELECT ima.*, m.name as medication_name, m.strength, m.unit
                                                         FROM ipd_medicine_administration ima
                                                         JOIN medications m ON ima.medication_id = m.medication_id
                                                         WHERE ima.checkup_id = ?";
                                        $medAdminStmt = $conn->prepare($medAdminQuery);
                                        $medAdminStmt->bind_param('i', $checkup['checkup_id']);
                                        $medAdminStmt->execute();
                                        $medicines = $medAdminStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        ?>
                                        <?php foreach ($medicines as $med): ?>
                                            <div class="medicine-item">
                                                <span><?php echo htmlspecialchars($med['medication_name'] . ' ' . $med['strength'] . ' ' . $med['unit']); ?> - <?php echo htmlspecialchars($med['dosage']); ?></span>
                                                <span>Qty: <?php echo $med['quantity']; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($checkup['medicine_notes']): ?>
                                    <div style="margin-top: 12px; font-size: 13px; color: #64748b;">
                                        <em><?php echo nl2br(htmlspecialchars($checkup['medicine_notes'])); ?></em>
                                    </div>
                                <?php endif; ?>

                                <?php if ($checkup['material_given']): ?>
                                    <div style="margin-bottom: 12px;">
                                        <span class="medicine-badge" style="background: #fef3c7; color: #92400e;">
                                            <i class="fas fa-boxes"></i> Materials Used
                                        </span>
                                        <?php
                                        $matAdminQuery = "SELECT ima.*, m.name as material_name, m.unit
                                                         FROM ipd_material_administration ima
                                                         JOIN materials m ON ima.material_id = m.material_id
                                                         WHERE ima.checkup_id = ?";
                                        $matAdminStmt = $conn->prepare($matAdminQuery);
                                        $matAdminStmt->bind_param('i', $checkup['checkup_id']);
                                        $matAdminStmt->execute();
                                        $materialsUsed = $matAdminStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        ?>
                                        <?php foreach ($materialsUsed as $mat): ?>
                                            <div class="medicine-item">
                                                <span><?php echo htmlspecialchars($mat['material_name']); ?> - Qty: <?php echo $mat['quantity_used']; ?></span>
                                                <span>Cost: Birr <?php echo number_format($mat['unit_cost'], 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($checkup['material_notes']): ?>
                                    <div style="margin-top: 12px; font-size: 13px; color: #64748b;">
                                        <em><?php echo nl2br(htmlspecialchars($checkup['material_notes'])); ?></em>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        let medicationCount = 0;
        let materialCount = 0;
        let editMedicationCount = 0;
        let editMaterialCount = 0;

        function toggleInjectionFields() {
            const checkbox = document.getElementById('injection_given');
            const fields = document.getElementById('injection_fields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
        }

        function toggleMedicineFields() {
            const checkbox = document.getElementById('medicine_given');
            const fields = document.getElementById('medicine_fields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
        }

        function toggleMaterialFields() {
            const checkbox = document.getElementById('material_given');
            const fields = document.getElementById('material_fields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
        }

        function toggleEditInjectionFields() {
            const checkbox = document.getElementById('edit_injection_given');
            const fields = document.getElementById('edit_injection_fields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
        }

        function toggleEditMedicineFields() {
            const checkbox = document.getElementById('edit_medicine_given');
            const fields = document.getElementById('edit_medicine_fields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
        }

        function toggleEditMaterialFields() {
            const checkbox = document.getElementById('edit_material_given');
            const fields = document.getElementById('edit_material_fields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
        }

        function editCheckup(checkupId) {
            fetch('ipd_checkups.php?action=get_checkup&id=' + checkupId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editCheckupModal').style.display = 'flex';
                        document.getElementById('edit_checkup_id').value = checkupId;
                        document.getElementById('edit_visit_id').value = data.checkup.visit_id;
                        document.getElementById('edit_patient_id').value = data.checkup.patient_id;
                        
                        document.getElementById('edit_progress_notes').value = data.checkup.progress_notes || '';
                        document.getElementById('edit_vital_signs').value = data.checkup.vital_signs || '';
                        document.getElementById('edit_glucose_level').value = data.checkup.glucose_level || '';
                        document.getElementById('edit_glucose_unit').value = data.checkup.glucose_unit || 'mg/dL';
                        document.getElementById('edit_glucose_type').value = data.checkup.glucose_type || 'Random';
                        
                        document.getElementById('edit_injection_given').checked = data.checkup.injection_given == 1;
                        document.getElementById('edit_injection_type').value = data.checkup.injection_type || '';
                        document.getElementById('edit_injection_dosage').value = data.checkup.injection_dosage || '';
                        toggleEditInjectionFields();
                        
                        document.getElementById('edit_medicine_given').checked = data.checkup.medicine_given == 1;
                        document.getElementById('edit_medicine_notes').value = data.checkup.medicine_notes || '';
                        toggleEditMedicineFields();
                        
                        document.getElementById('edit_material_given').checked = data.checkup.material_given == 1;
                        document.getElementById('edit_material_notes').value = data.checkup.material_notes || '';
                        toggleEditMaterialFields();
                        
                        // Load existing medicines
                        const medContainer = document.getElementById('edit_medication_container');
                        medContainer.innerHTML = '';
                        editMedicationCount = 0;
                        
                        if (data.medicines && data.medicines.length > 0) {
                            data.medicines.forEach((med, index) => {
                                addEditMedicationRow(med);
                            });
                        } else {
                            addEditMedicationRow();
                        }
                        
                        // Load existing materials
                        const matContainer = document.getElementById('edit_material_container');
                        matContainer.innerHTML = '';
                        editMaterialCount = 0;
                        
                        if (data.materials && data.materials.length > 0) {
                            data.materials.forEach((mat, index) => {
                                addEditMaterialRow(mat);
                            });
                        } else {
                            addEditMaterialRow();
                        }
                    }
                })
                .catch(error => {
                    alert('Error loading checkup data');
                });
        }

        function closeEditCheckupModal() {
            document.getElementById('editCheckupModal').style.display = 'none';
        }

        function addEditMedicationRow(existingMed = null) {
            const container = document.getElementById('edit_medication_container');
            const newRow = document.createElement('div');
            newRow.className = 'form-row';
            newRow.style.marginBottom = '12px';
            
            const medValue = existingMed ? existingMed.medication_id : '';
            const dosageValue = existingMed ? existingMed.dosage : '';
            const quantityValue = existingMed ? existingMed.quantity : 1;
            const notesValue = existingMed ? existingMed.notes : '';
            
            newRow.innerHTML = `
                <div class="form-group" style="flex: 2;">
                    <label>Medication</label>
                    <select name="medications[${editMedicationCount}][medication_id]" required style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="">Select medication...</option>
                        <?php foreach ($medications as $med): ?>
                            <option value="<?php echo $med['medication_id']; ?>" ${medValue === <?php echo $med['medication_id']; ?> ? 'selected' : ''}>
                                <?php echo htmlspecialchars($med['name'] . ' ' . $med['strength'] . ' ' . $med['unit']); ?> (Stock: <?php echo $med['stock_quantity']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Dosage</label>
                    <input type="text" name="medications[${editMedicationCount}][dosage]" value="${dosageValue}" placeholder="e.g., 500mg" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Quantity</label>
                    <input type="number" name="medications[${editMedicationCount}][quantity]" min="1" value="${quantityValue}" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>Notes</label>
                    <input type="text" name="medications[${editMedicationCount}][notes]" value="${notesValue}" placeholder="Optional notes" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 0;">
                    <label>&nbsp;</label>
                    <button type="button" onclick="this.closest('.form-row').remove()" class="btn-delete" style="padding: 8px 12px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
            editMedicationCount++;
        }

        function addEditMaterialRow(existingMat = null) {
            const container = document.getElementById('edit_material_container');
            const newRow = document.createElement('div');
            newRow.className = 'form-row';
            newRow.style.marginBottom = '12px';
            
            const matValue = existingMat ? existingMat.material_id : '';
            const quantityValue = existingMat ? existingMat.quantity_used : 1;
            const costValue = existingMat ? existingMat.unit_cost : '';
            const notesValue = existingMat ? existingMat.notes : '';
            
            newRow.innerHTML = `
                <div class="form-group" style="flex: 2;">
                    <label>Material</label>
                    <select name="materials[${editMaterialCount}][material_id]" required style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="">Select material...</option>
                        <?php foreach ($materials as $mat): ?>
                            <option value="<?php echo $mat['material_id']; ?>" data-unit-cost="<?php echo $mat['unit_price']; ?>" ${matValue === <?php echo $mat['material_id']; ?> ? 'selected' : ''}>
                                <?php echo htmlspecialchars($mat['name']); ?> (Stock: <?php echo $mat['stock_quantity']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Quantity</label>
                    <input type="number" name="materials[${editMaterialCount}][quantity_used]" min="1" value="${quantityValue}" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Unit Cost</label>
                    <input type="number" name="materials[${editMaterialCount}][unit_cost]" step="0.01" value="${costValue}" placeholder="0.00" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>Notes</label>
                    <input type="text" name="materials[${editMaterialCount}][notes]" value="${notesValue}" placeholder="Optional notes" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 0;">
                    <label>&nbsp;</label>
                    <button type="button" onclick="this.closest('.form-row').remove()" class="btn-delete" style="padding: 8px 12px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
            editMaterialCount++;
        }

        function addMedicationRow() {
            medicationCount++;
            const container = document.getElementById('medication_container');
            const newRow = document.createElement('div');
            newRow.className = 'form-row';
            newRow.style.marginBottom = '12px';
            newRow.innerHTML = `
                <div class="form-group" style="flex: 2;">
                    <label>Medication</label>
                    <select name="medications[${medicationCount}][medication_id]" required style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="">Select medication...</option>
                        <?php foreach ($medications as $med): ?>
                            <option value="<?php echo $med['medication_id']; ?>">
                                <?php echo htmlspecialchars($med['name'] . ' ' . $med['strength'] . ' ' . $med['unit']); ?> (Stock: <?php echo $med['stock_quantity']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Dosage</label>
                    <input type="text" name="medications[${medicationCount}][dosage]" placeholder="e.g., 500mg" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Quantity</label>
                    <input type="number" name="medications[${medicationCount}][quantity]" min="1" value="1" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>Notes</label>
                    <input type="text" name="medications[${medicationCount}][notes]" placeholder="Optional notes" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 0;">
                    <label>&nbsp;</label>
                    <button type="button" onclick="this.closest('.form-row').remove()" class="btn-delete" style="padding: 8px 12px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        }

        function addMaterialRow() {
            materialCount++;
            const container = document.getElementById('material_container');
            const newRow = document.createElement('div');
            newRow.className = 'form-row';
            newRow.style.marginBottom = '12px';
            newRow.innerHTML = `
                <div class="form-group" style="flex: 2;">
                    <label>Material</label>
                    <select name="materials[${materialCount}][material_id]" required style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="">Select material...</option>
                        <?php foreach ($materials as $mat): ?>
                            <option value="<?php echo $mat['material_id']; ?>" data-unit-cost="<?php echo $mat['unit_price']; ?>">
                                <?php echo htmlspecialchars($mat['name']); ?> (Stock: <?php echo $mat['stock_quantity']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Quantity</label>
                    <input type="number" name="materials[${materialCount}][quantity_used]" min="1" value="1" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Unit Cost</label>
                    <input type="number" name="materials[${materialCount}][unit_cost]" step="0.01" placeholder="0.00" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>Notes</label>
                    <input type="text" name="materials[${materialCount}][notes]" placeholder="Optional notes" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div class="form-group" style="flex: 0;">
                    <label>&nbsp;</label>
                    <button type="button" onclick="this.closest('.form-row').remove()" class="btn-delete" style="padding: 8px 12px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        }
    </script>
</body>
</html>
