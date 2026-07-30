<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - IPD PATIENTS MANAGEMENT
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
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';
$selectedVisitId = isset($_GET['visit_id']) ? intval($_GET['visit_id']) : 0;

// ============================================================================
// GET IPD PATIENTS
// ============================================================================
$ipdPatientsQuery = "SELECT ir.ipd_record_id, ir.visit_id, ir.patient_id, ir.bed_id, ir.ward_id, ir.status,
                     ir.admission_date, ir.primary_diagnosis, ir.admission_notes,
                     v.visit_code, v.admitted_at,
                     CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                     p.patient_code, p.gender, p.date_of_birth,
                     b.bed_number,
                     w.name as ward_name,
                     CONCAT(d.first_name, ' ', d.last_name) as attending_doctor
              FROM ipd_records ir
              JOIN visits v ON ir.visit_id = v.visit_id
              JOIN patients p ON ir.patient_id = p.patient_id
              LEFT JOIN beds b ON ir.bed_id = b.bed_id
              LEFT JOIN wards w ON ir.ward_id = w.ward_id
              LEFT JOIN staff d ON ir.attending_doctor_id = d.staff_id
              WHERE ir.status = 'Admitted'
              ORDER BY ir.admission_date DESC";
$ipdPatientsResult = $conn->query($ipdPatientsQuery);
$ipdPatients = $ipdPatientsResult ? $ipdPatientsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get selected patient details
$selectedPatient = null;
if ($selectedVisitId > 0) {
    foreach ($ipdPatients as $patient) {
        if ($patient['visit_id'] == $selectedVisitId) {
            $selectedPatient = $patient;
            break;
        }
    }
}

// ============================================================================
// CREATE IPD MEDICAL RECORD
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ipd_record') {
    $visitId = intval($_POST['visit_id']);
    $patientId = intval($_POST['patient_id']);
    $doctorId = intval($_POST['doctor_id']);
    $diagnosis = sanitizeInput($_POST['diagnosis'] ?? '');
    $clinicalNotes = sanitizeInput($_POST['clinical_notes'] ?? '');
    $needsLab = isset($_POST['needs_lab']) ? 1 : 0;
    $needsRadiology = isset($_POST['needs_radiology']) ? 1 : 0;
    $needsPharmacy = isset($_POST['needs_pharmacy']) ? 1 : 0;

    $query = "INSERT INTO medical_records (visit_id, patient_id, doctor_id, diagnosis, clinical_notes, needs_lab, needs_radiology, needs_pharmacy, record_type)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'IPD')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iisssiii',
        $visitId,
        $patientId,
        $doctorId,
        $diagnosis,
        $clinicalNotes,
        $needsLab,
        $needsRadiology,
        $needsPharmacy
    );

    if ($stmt->execute()) {
        $recordId = $conn->insert_id;
        logUserActivity($conn, $_SESSION['user_id'], 'Created IPD Medical Record', "Created IPD medical record ID: {$recordId} for visit ID: {$visitId}");
        $message = 'IPD medical record created successfully!';
        header('Location: ipd_patients.php?tab=medical_records&visit_id=' . $visitId . '&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create IPD medical record. Please try again.';
    }
}

// ============================================================================
// CREATE LAB ORDER FOR IPD PATIENT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_lab_order') {
    $visitId = intval($_POST['visit_id']);
    $testTypeIds = array_map('intval', $_POST['test_type_ids'] ?? []);

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
        logUserActivity($conn, $_SESSION['user_id'], 'Created Lab Orders', "Created {$createdCount} lab order(s) for IPD visit ID: {$visitId}");
        $message = 'Lab order created successfully!';
        header('Location: ipd_patients.php?tab=lab&visit_id=' . $visitId . '&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Select at least one lab test and try again.';
    }
}

// ============================================================================
// CREATE PRESCRIPTION FOR IPD PATIENT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_prescription') {
    $visitId = intval($_POST['visit_id']);
    $patientId = intval($_POST['patient_id']);
    $doctorId = intval($_POST['doctor_id']);
    $notes = sanitizeInput($_POST['notes'] ?? '');

    $prescriptionQuery = "INSERT INTO prescriptions (visit_id, patient_id, doctor_id, notes, status)
                         VALUES (?, ?, ?, ?, 'Pending')";
    $prescriptionStmt = $conn->prepare($prescriptionQuery);
    $prescriptionStmt->bind_param('iiis', $visitId, $patientId, $doctorId, $notes);

    if ($prescriptionStmt->execute()) {
        $prescriptionId = $conn->insert_id;

        if (isset($_POST['medications']) && is_array($_POST['medications'])) {
            foreach ($_POST['medications'] as $med) {
                if (!empty($med['medication_id'])) {
                    $itemQuery = "INSERT INTO prescription_items (prescription_id, medication_id, dosage, duration_days, quantity, note)
                                 VALUES (?, ?, ?, ?, ?, ?)";
                    $itemStmt = $conn->prepare($itemQuery);
                    $itemStmt->bind_param('iisiis',
                        $prescriptionId,
                        intval($med['medication_id']),
                        sanitizeInput($med['dosage'] ?? ''),
                        intval($med['duration_days'] ?? 1),
                        intval($med['quantity'] ?? 1),
                        sanitizeInput($med['note'] ?? '')
                    );
                    $itemStmt->execute();
                }
            }
        }

        logUserActivity($conn, $_SESSION['user_id'], 'Created IPD Prescription', "Created prescription ID: {$prescriptionId} for IPD visit ID: {$visitId}");
        $message = 'Prescription created successfully!';
        header('Location: ipd_patients.php?tab=pharmacy&visit_id=' . $visitId . '&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create prescription. Please try again.';
    }
}

// ============================================================================
// CREATE IPD CHECKUP
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
    $vitalSigns = sanitizeInput($_POST['vital_signs'] ?? '');

    $checkupQuery = "INSERT INTO ipd_checkups (visit_id, patient_id, recorded_by, progress_notes, glucose_level, glucose_unit, glucose_type, injection_given, injection_type, injection_dosage, medicine_given, medicine_notes, vital_signs)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $checkupStmt = $conn->prepare($checkupQuery);
    $checkupStmt->bind_param('iisdsdsississ',
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
        $vitalSigns
    );

    if ($checkupStmt->execute()) {
        $checkupId = $conn->insert_id;

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

        logUserActivity($conn, $_SESSION['user_id'], 'Created IPD Checkup', "Created checkup ID: {$checkupId} for IPD visit ID: {$visitId}");
        $message = 'Checkup recorded successfully!';
        header('Location: ipd_patients.php?tab=checkups&visit_id=' . $visitId . '&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to record checkup. Please try again.';
    }
}

// ============================================================================
// DISCHARGE IPD PATIENT
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'discharge' && isset($_GET['ipd_record_id'])) {
    $ipdRecordId = intval($_GET['ipd_record_id']);

    $conn->begin_transaction();
    try {
        $updateQuery = "UPDATE ipd_records SET discharge_date = NOW(), status = 'Discharged', discharged_by = ? WHERE ipd_record_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('ii', $_SESSION['user_id'], $ipdRecordId);
        $updateStmt->execute();

        $visitQuery = "UPDATE visits SET discharged_at = NOW(), discharged_by = ?, visit_status_id = (SELECT visit_status_id FROM lookup_visit_statuses WHERE name = 'Discharged') WHERE visit_id = (SELECT visit_id FROM ipd_records WHERE ipd_record_id = ?)";
        $visitStmt = $conn->prepare($visitQuery);
        $visitStmt->bind_param('ii', $_SESSION['user_id'], $ipdRecordId);
        $visitStmt->execute();

        $bedQuery = "UPDATE beds SET status = 'Available' WHERE bed_id = (SELECT bed_id FROM ipd_records WHERE ipd_record_id = ?)";
        $bedStmt = $conn->prepare($bedQuery);
        $bedStmt->bind_param('i', $ipdRecordId);
        $bedStmt->execute();

        $conn->commit();
        logUserActivity($conn, $_SESSION['user_id'], 'Discharged IPD Patient', "Discharged IPD record ID: {$ipdRecordId}");
        $message = 'Patient discharged successfully!';
        header('Location: ipd_patients.php?message=' . urlencode($message));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Failed to discharge patient. Please try again.';
    }
}

// Get test types for lab
$testTypesQuery = $conn->query("SELECT * FROM lookup_test_types WHERE is_active = 1 AND category = 'Laboratory' ORDER BY sub_category, name");
$testTypes = $testTypesQuery ? $testTypesQuery->fetch_all(MYSQLI_ASSOC) : [];
$labCategories = [];
foreach ($testTypes as $test) {
    $sub = !empty($test['sub_category']) ? $test['sub_category'] : 'Other';
    $labCategories[$sub][] = $test;
}

// Get medications for pharmacy
$medications = $conn->query("SELECT medication_id, name, strength, unit, stock_quantity FROM medications WHERE is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Get doctors for dropdown
$doctors = $conn->query("SELECT staff_id, CONCAT(first_name, ' ', last_name) as name FROM staff WHERE role_id IN (SELECT role_id FROM lookup_roles WHERE name IN ('Doctor', 'Consultant')) AND is_active = 1")->fetch_all(MYSQLI_ASSOC);

// Get medical records for selected patient
$ipdMedicalRecords = [];
if ($selectedVisitId > 0) {
    $mrQuery = "SELECT mr.*, CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                FROM medical_records mr
                LEFT JOIN staff d ON mr.doctor_id = d.staff_id
                WHERE mr.visit_id = ? AND mr.record_type = 'IPD'
                ORDER BY mr.created_at DESC";
    $mrStmt = $conn->prepare($mrQuery);
    $mrStmt->bind_param('i', $selectedVisitId);
    $mrStmt->execute();
    $ipdMedicalRecords = $mrStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get lab orders for selected patient
$labOrders = [];
if ($selectedVisitId > 0) {
    $labQuery = "SELECT lo.*, vt.name as test_name, vt.category, os.name as status_name
                 FROM lab_orders lo
                 JOIN lookup_test_types vt ON lo.test_type_id = vt.test_type_id
                 JOIN lookup_order_statuses os ON lo.order_status_id = os.order_status_id
                 WHERE lo.visit_id = ? AND vt.category = 'Laboratory'
                 ORDER BY lo.ordered_at DESC";
    $labStmt = $conn->prepare($labQuery);
    $labStmt->bind_param('i', $selectedVisitId);
    $labStmt->execute();
    $labOrders = $labStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get prescriptions for selected patient
$prescriptions = [];
if ($selectedVisitId > 0) {
    $rxQuery = "SELECT pr.*, CONCAT(d.first_name, ' ', d.last_name) as doctor_name
               FROM prescriptions pr
               LEFT JOIN staff d ON pr.doctor_id = d.staff_id
               WHERE pr.visit_id = ?
               ORDER BY pr.created_at DESC";
    $rxStmt = $conn->prepare($rxQuery);
    $rxStmt->bind_param('i', $selectedVisitId);
    $rxStmt->execute();
    $prescriptions = $rxStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get checkups for selected patient
$checkups = [];
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
    <title>IPD Patients - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .patient-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s;
        }
        .patient-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #8b5cf6;
        }
        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .patient-info {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .patient-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #8b5cf6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
        }
        .patient-details h4 {
            margin: 0 0 4px 0;
            color: #0f172a;
        }
        .patient-details p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }
        .patient-actions {
            display: flex;
            gap: 8px;
        }
        .patient-actions a {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            text-decoration: none;
            color: white;
        }
        .patient-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
        }
        .meta-item {
            font-size: 13px;
        }
        .meta-label {
            color: #64748b;
            display: block;
        }
        .meta-value {
            color: #0f172a;
            font-weight: 500;
        }
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
                    <h1><i class="fas fa-procedures"></i> IPD Patients</h1>
                    <p>Inpatient Department Management</p>
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
                <!-- Overview Tab - All IPD Patients -->
                <div class="table-card">
                    <h2 style="margin-bottom: 20px;">Admitted IPD Patients (<?php echo count($ipdPatients); ?>)</h2>
                    <?php if (empty($ipdPatients)): ?>
                        <p style="text-align: center; color: #94a3b8; padding: 40px;">
                            <i class="fas fa-procedures" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            No IPD patients admitted
                        </p>
                    <?php else: ?>
                        <?php foreach ($ipdPatients as $patient): ?>
                        <div class="patient-card">
                            <div class="patient-header">
                                <div class="patient-info">
                                    <div class="patient-avatar"><?php echo strtoupper(substr($patient['patient_name'], 0, 1)); ?></div>
                                    <div class="patient-details">
                                        <h4><?php echo htmlspecialchars($patient['patient_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($patient['patient_code']); ?> · <?php echo htmlspecialchars($patient['visit_code']); ?></p>
                                    </div>
                                </div>
                                <div class="patient-actions">
                                    <a href="ipd_patients.php?tab=medical_records&visit_id=<?php echo $patient['visit_id']; ?>" style="background: #3b82f6;">
                                        <i class="fas fa-notes-medical"></i> Records
                                    </a>
                                    <a href="ipd_patients.php?tab=lab&visit_id=<?php echo $patient['visit_id']; ?>" style="background: #10b981;">
                                        <i class="fas fa-flask"></i> Lab
                                    </a>
                                    <a href="ipd_patients.php?tab=pharmacy&visit_id=<?php echo $patient['visit_id']; ?>" style="background: #f59e0b;">
                                        <i class="fas fa-pills"></i> Pharmacy
                                    </a>
                                    <a href="ipd_patients.php?tab=checkups&visit_id=<?php echo $patient['visit_id']; ?>" style="background: #8b5cf6;">
                                        <i class="fas fa-clipboard-user"></i> Checkups
                                    </a>
                                    <a href="ipd_patients.php?action=discharge&ipd_record_id=<?php echo $patient['ipd_record_id']; ?>" style="background: #ef4444;" onclick="return confirm('Are you sure you want to discharge this patient?');">
                                        <i class="fas fa-sign-out-alt"></i> Discharge
                                    </a>
                                </div>
                            </div>
                            <div class="patient-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Ward/Bed</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($patient['ward_name'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($patient['bed_number'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Attending Doctor</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($patient['attending_doctor'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Admitted</span>
                                    <span class="meta-value"><?php echo date('M d, Y', strtotime($patient['admission_date'])); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Gender</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Age</span>
                                    <span class="meta-value"><?php echo $patient['date_of_birth'] ? floor((time() - strtotime($patient['date_of_birth'])) / 31556926) : 'N/A'; ?> years</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Primary Diagnosis</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($patient['primary_diagnosis'] ?? 'Not specified'); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Patient Selected - Show Tabs -->
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

                <!-- Patient Tabs -->
                <div class="tabs">
                    <button class="tab <?php echo $activeTab === 'medical_records' ? 'active' : ''; ?>" data-tab="medical_records">
                        <i class="fas fa-notes-medical"></i> Medical Records
                    </button>
                    <button class="tab <?php echo $activeTab === 'lab' ? 'active' : ''; ?>" data-tab="lab">
                        <i class="fas fa-flask"></i> Lab
                    </button>
                    <button class="tab <?php echo $activeTab === 'pharmacy' ? 'active' : ''; ?>" data-tab="pharmacy">
                        <i class="fas fa-pills"></i> Pharmacy
                    </button>
                    <button class="tab <?php echo $activeTab === 'checkups' ? 'active' : ''; ?>" data-tab="checkups">
                        <i class="fas fa-clipboard-user"></i> Checkups
                    </button>
                </div>

                <!-- Medical Records Tab -->
                <div class="tab-content <?php echo $activeTab === 'medical_records' ? 'active' : ''; ?>" id="tab-medical_records">
                    <div class="table-card">
                        <h2 style="margin-bottom: 20px;">Create IPD Medical Record</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_ipd_record">
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patient_id'] ?? 0; ?>">

                            <div class="form-group">
                                <label for="doctor_id">Attending Doctor *</label>
                                <select id="doctor_id" name="doctor_id" required>
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $doc): ?>
                                        <option value="<?php echo $doc['staff_id']; ?>" <?php echo ($selectedPatient['attending_doctor_id'] == $doc['staff_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($doc['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="diagnosis">Diagnosis *</label>
                                <textarea id="diagnosis" name="diagnosis" rows="3" required placeholder="Primary diagnosis and clinical findings..."></textarea>
                            </div>

                            <div class="form-group">
                                <label for="clinical_notes">Clinical Notes</label>
                                <textarea id="clinical_notes" name="clinical_notes" rows="4" placeholder="Detailed clinical notes, history, and observations..."></textarea>
                            </div>

                            <div class="form-group" style="background: #f0fdf4; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                    <input type="checkbox" name="needs_lab" value="1" id="needs_lab">
                                    <strong>Order Lab Tests</strong>
                                </label>
                            </div>

                            <div class="form-group" style="background: #fef3c7; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                    <input type="checkbox" name="needs_radiology" value="1" id="needs_radiology">
                                    <strong>Order Radiology Tests</strong>
                                </label>
                            </div>

                            <div class="form-group" style="background: #dbeafe; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                    <input type="checkbox" name="needs_pharmacy" value="1" id="needs_pharmacy">
                                    <strong>Order Medications</strong>
                                </label>
                            </div>

                            <div class="btn-group">
                                <button type="submit" class="btn-submit">
                                    <i class="fas fa-save"></i> Save Medical Record
                                </button>
                                <button type="button" class="btn-cancel" onclick="window.location.href='ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>'">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <div class="table-card" style="margin-top: 20px;">
                        <h2 style="margin-bottom: 20px;">IPD Medical Records History</h2>
                        <?php if (empty($ipdMedicalRecords)): ?>
                            <p style="text-align: center; color: #94a3b8; padding: 32px;">No IPD medical records found</p>
                        <?php else: ?>
                            <?php foreach ($ipdMedicalRecords as $record): ?>
                            <div class="checkup-card">
                                <div class="checkup-header">
                                    <div>
                                        <strong><?php echo htmlspecialchars($record['doctor_name']); ?></strong>
                                        <span class="checkup-time"><?php echo date('M d, Y g:i A', strtotime($record['created_at'])); ?></span>
                                    </div>
                                </div>
                                <div class="checkup-notes">
                                    <strong>Diagnosis:</strong>
                                    <p style="margin: 4px 0 0 0;"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                                </div>
                                <?php if ($record['clinical_notes']): ?>
                                <div class="checkup-notes">
                                    <strong>Clinical Notes:</strong>
                                    <p style="margin: 4px 0 0 0;"><?php echo nl2br(htmlspecialchars($record['clinical_notes'])); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lab Tab -->
                <div class="tab-content <?php echo $activeTab === 'lab' ? 'active' : ''; ?>" id="tab-lab">
                    <div class="table-card">
                        <h2 style="margin-bottom: 20px;">Order Lab Tests</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_lab_order">
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">

                            <div class="form-group">
                                <label>Select Lab Tests *</label>
                                <?php foreach ($labCategories as $category => $tests): ?>
                                    <div style="margin-bottom: 16px;">
                                        <strong style="display: block; margin-bottom: 8px; color: #64748b;"><?php echo htmlspecialchars($category); ?></strong>
                                        <?php foreach ($tests as $test): ?>
                                            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                                <input type="checkbox" name="test_type_ids[]" value="<?php echo $test['test_type_id']; ?>">
                                                <?php echo htmlspecialchars($test['name']); ?> - $<?php echo number_format($test['price'], 2); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="btn-group">
                                <button type="submit" class="btn-submit">
                                    <i class="fas fa-flask"></i> Order Lab Tests
                                </button>
                                <button type="button" class="btn-cancel" onclick="window.location.href='ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>'">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <div class="table-card" style="margin-top: 20px;">
                        <h2 style="margin-bottom: 20px;">Lab Orders History</h2>
                        <?php if (empty($labOrders)): ?>
                            <p style="text-align: center; color: #94a3b8; padding: 32px;">No lab orders found</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="recent-table">
                                    <thead>
                                        <tr>
                                            <th>Test</th>
                                            <th>Status</th>
                                            <th>Ordered At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($labOrders as $order): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['test_name']); ?></td>
                                            <td><?php echo htmlspecialchars($order['status_name']); ?></td>
                                            <td><?php echo date('M d, Y g:i A', strtotime($order['ordered_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pharmacy Tab -->
                <div class="tab-content <?php echo $activeTab === 'pharmacy' ? 'active' : ''; ?>" id="tab-pharmacy">
                    <div class="table-card">
                        <h2 style="margin-bottom: 20px;">Create Prescription</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_prescription">
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patient_id'] ?? 0; ?>">

                            <div class="form-group">
                                <label for="doctor_id">Prescribing Doctor *</label>
                                <select id="doctor_id" name="doctor_id" required>
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $doc): ?>
                                        <option value="<?php echo $doc['staff_id']; ?>" <?php echo ($selectedPatient['attending_doctor_id'] == $doc['staff_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($doc['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Medications *</label>
                                <div id="medication_container">
                                    <div class="form-row" style="margin-bottom: 12px;">
                                        <div class="form-group" style="flex: 2;">
                                            <label>Medication</label>
                                            <select name="medications[0][medication_id]" required>
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
                                            <input type="text" name="medications[0][dosage]" placeholder="e.g., 500mg">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label>Duration (days)</label>
                                            <input type="number" name="medications[0][duration_days]" min="1" value="1">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label>Quantity</label>
                                            <input type="number" name="medications[0][quantity]" min="1" value="1">
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <label>Notes</label>
                                            <input type="text" name="medications[0][note]" placeholder="Optional notes">
                                        </div>
                                    </div>
                                </div>
                                <button type="button" onclick="addMedicationRow()" class="btn-cancel" style="margin-top: 8px; width: 100%; border-style: dashed;">
                                    <i class="fas fa-plus"></i> Add Another Medication
                                </button>
                            </div>

                            <div class="form-group">
                                <label for="notes">Prescription Notes</label>
                                <textarea id="notes" name="notes" rows="3" placeholder="Additional instructions..."></textarea>
                            </div>

                            <div class="btn-group">
                                <button type="submit" class="btn-submit">
                                    <i class="fas fa-prescription"></i> Create Prescription
                                </button>
                                <button type="button" class="btn-cancel" onclick="window.location.href='ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>'">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <div class="table-card" style="margin-top: 20px;">
                        <h2 style="margin-bottom: 20px;">Prescription History</h2>
                        <p style="text-align: center; color: #94a3b8; padding: 32px;">Prescription history will be displayed here</p>
                    </div>
                </div>

                <!-- Checkups Tab -->
                <div class="tab-content <?php echo $activeTab === 'checkups' ? 'active' : ''; ?>" id="tab-checkups">
                    <div class="table-card">
                        <h2 style="margin-bottom: 20px;">New Checkup</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_checkup">
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patient_id'] ?? 0; ?>">

                            <div class="form-group">
                                <label for="progress_notes">Progress Notes</label>
                                <textarea id="progress_notes" name="progress_notes" rows="4" placeholder="Detailed progress notes, observations, and updates..."></textarea>
                            </div>

                            <div class="form-group">
                                <label for="vital_signs">Vital Signs</label>
                                <textarea id="vital_signs" name="vital_signs" rows="2" placeholder="e.g., BP: 120/80, Pulse: 72, Temp: 98.6°F, SpO2: 98%"></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="flex: 1;">
                                    <label for="glucose_level">Glucose Level</label>
                                    <input type="number" id="glucose_level" name="glucose_level" step="0.1" placeholder="e.g., 120">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="glucose_unit">Unit</label>
                                    <select id="glucose_unit" name="glucose_unit">
                                        <option value="mg/dL">mg/dL</option>
                                        <option value="mmol/L">mmol/L</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="glucose_type">Type</label>
                                    <select id="glucose_type" name="glucose_type">
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
                                            <input type="text" id="injection_type" name="injection_type" placeholder="e.g., Insulin, Antibiotic">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="injection_dosage">Dosage</label>
                                            <input type="text" id="injection_dosage" name="injection_dosage" placeholder="e.g., 10 units, 500mg">
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
                                    <div id="checkup_medication_container">
                                        <div class="form-row" style="margin-bottom: 12px;">
                                            <div class="form-group" style="flex: 2;">
                                                <label>Medication</label>
                                                <select name="medications[0][medication_id]">
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
                                                <input type="text" name="medications[0][dosage]" placeholder="e.g., 500mg">
                                            </div>
                                            <div class="form-group" style="flex: 1;">
                                                <label>Quantity</label>
                                                <input type="number" name="medications[0][quantity]" min="1" value="1">
                                            </div>
                                            <div class="form-group" style="flex: 2;">
                                                <label>Notes</label>
                                                <input type="text" name="medications[0][notes]" placeholder="Optional notes">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="addCheckupMedicationRow()" class="btn-cancel" style="margin-top: 8px; width: 100%; border-style: dashed;">
                                        <i class="fas fa-plus"></i> Add Another Medication
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="medicine_notes">General Medicine Notes</label>
                                <textarea id="medicine_notes" name="medicine_notes" rows="2" placeholder="Additional notes about medication administration..."></textarea>
                            </div>

                            <div class="btn-group">
                                <button type="submit" class="btn-submit">
                                    <i class="fas fa-save"></i> Save Checkup
                                </button>
                                <button type="button" class="btn-cancel" onclick="window.location.href='ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>'">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <div class="table-card" style="margin-top: 20px;">
                        <h2 style="margin-bottom: 20px;">Checkup History</h2>
                        <?php if (empty($checkups)): ?>
                            <p style="text-align: center; color: #94a3b8; padding: 32px;">No checkups recorded yet</p>
                        <?php else: ?>
                            <?php foreach ($checkups as $checkup): ?>
                            <div class="checkup-card">
                                <div class="checkup-header">
                                    <div>
                                        <strong><?php echo htmlspecialchars($checkup['recorded_by_name']); ?></strong>
                                        <span class="checkup-time"><?php echo date('M d, Y g:i A', strtotime($checkup['checkup_time'])); ?></span>
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
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    const url = new URL(window.location);
                    url.searchParams.set('tab', tabName);
                    window.location.href = url.toString();
                });
            });
        });

        let medicationCount = 0;
        let checkupMedicationCount = 0;

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

        function addMedicationRow() {
            medicationCount++;
            const container = document.getElementById('medication_container');
            const newRow = document.createElement('div');
            newRow.className = 'form-row';
            newRow.style.marginBottom = '12px';
            newRow.innerHTML = `
                <div class="form-group" style="flex: 2;">
                    <label>Medication</label>
                    <select name="medications[${medicationCount}][medication_id]" required>
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
                    <input type="text" name="medications[${medicationCount}][dosage]" placeholder="e.g., 500mg">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Duration (days)</label>
                    <input type="number" name="medications[${medicationCount}][duration_days]" min="1" value="1">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Quantity</label>
                    <input type="number" name="medications[${medicationCount}][quantity]" min="1" value="1">
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>Notes</label>
                    <input type="text" name="medications[${medicationCount}][note]" placeholder="Optional notes">
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

        function addCheckupMedicationRow() {
            checkupMedicationCount++;
            const container = document.getElementById('checkup_medication_container');
            const newRow = document.createElement('div');
            newRow.className = 'form-row';
            newRow.style.marginBottom = '12px';
            newRow.innerHTML = `
                <div class="form-group" style="flex: 2;">
                    <label>Medication</label>
                    <select name="medications[${checkupMedicationCount}][medication_id]">
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
                    <input type="text" name="medications[${checkupMedicationCount}][dosage]" placeholder="e.g., 500mg">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Quantity</label>
                    <input type="number" name="medications[${checkupMedicationCount}][quantity]" min="1" value="1">
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>Notes</label>
                    <input type="text" name="medications[${checkupMedicationCount}][notes]" placeholder="Optional notes">
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
