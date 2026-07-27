<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - MEDICAL RECORDS
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
$selectedPatientId = 0;
if ($selectedVisitId > 0) {
    $visitPatientStmt = $conn->prepare("SELECT patient_id FROM visits WHERE visit_id = ?");
    $visitPatientStmt->bind_param('i', $selectedVisitId);
    $visitPatientStmt->execute();
    $visitPatient = $visitPatientStmt->get_result()->fetch_assoc();
    $selectedPatientId = $visitPatient ? (int) $visitPatient['patient_id'] : 0;
}

// Get all patients for dropdown
$patients = $conn->query("SELECT patient_id, patient_code, first_name, last_name FROM patients WHERE is_active = 1 ORDER BY patient_id ASC")->fetch_all(MYSQLI_ASSOC);

// Get all visits for dropdown
$visits = $conn->query("SELECT v.visit_id, v.visit_code, CONCAT(p.first_name, ' ', p.last_name) as patient_name 
                        FROM visits v 
                        JOIN patients p ON v.patient_id = p.patient_id 
                        ORDER BY v.admitted_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// PATIENT HISTORY (AJAX)
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'patient_history' && isset($_GET['patient_id'])) {
    header('Content-Type: application/json');
    $history = getPatientHistory($conn, intval($_GET['patient_id']));
    if (!$history) {
        echo json_encode(['success' => false, 'error' => 'Patient not found']);
    } else {
        echo json_encode(['success' => true, 'data' => $history]);
    }
    exit();
}

// ============================================================================
// TOGGLE LAB / BED FLAG (AJAX)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_flag') {
    header('Content-Type: application/json');
    $recordId = intval($_POST['record_id'] ?? 0);
    $flag = $_POST['flag'] ?? '';
    $value = isset($_POST['value']) && $_POST['value'] === '1' ? 1 : 0;

    if ($recordId <= 0 || !in_array($flag, ['needs_lab', 'needs_radiology', 'needs_bed', 'needs_pharmacy'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit();
    }

    $query = "UPDATE medical_records SET {$flag} = ?, updated_at = NOW() WHERE record_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $value, $recordId);

    if ($stmt->execute()) {
        $label = $flag === 'needs_lab' ? 'lab referral' : ($flag === 'needs_radiology' ? 'radiology referral' : 'bed referral');
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Medical Record', "Set {$label} to {$value} for record ID: {$recordId}");
        echo json_encode(['success' => true, 'value' => $value]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update record']);
    }
    exit();
}

// ============================================================================
// CREATE MEDICAL RECORD
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_record') {
    $visitId = intval($_POST['visit_id']);
    
    // Get the attending doctor from the visit
    $visitQuery = "SELECT attending_doctor_id, patient_id FROM visits WHERE visit_id = ?";
    $visitStmt = $conn->prepare($visitQuery);
    $visitStmt->bind_param('i', $visitId);
    $visitStmt->execute();
    $visit = $visitStmt->get_result()->fetch_assoc();
    $doctorId = $visit['attending_doctor_id'] ? intval($visit['attending_doctor_id']) : null;
    $patientId = intval($visit['patient_id']);
    
    $needsLab = isset($_POST['needs_lab']) ? 1 : 0;
    $needsBed = isset($_POST['needs_bed']) ? 1 : 0;
    $needsRadiology = isset($_POST['needs_radiology']) ? 1 : 0;
    $needsPharmacy = isset($_POST['needs_pharmacy']) ? 1 : 0;
    $query = "INSERT INTO medical_records (visit_id, patient_id, doctor_id, diagnosis, clinical_notes, needs_lab, needs_radiology, needs_bed, needs_pharmacy) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiissiiii', 
        $visitId,
        $patientId,
        $doctorId,
        $_POST['diagnosis'],
        $_POST['clinical_notes'],
        $needsLab,
        $needsRadiology,
        $needsBed,
        $needsPharmacy
    );
    
    if ($stmt->execute()) {
        $recordId = $conn->insert_id;
        logUserActivity($conn, $_SESSION['user_id'], 'Created Medical Record', "Created medical record ID: {$recordId}");
        $message = 'Medical record created successfully!';
        if ($needsLab || $needsRadiology || $needsBed || $needsPharmacy) {
            $parts = [];
            if ($needsLab) {
                $parts[] = 'sent to lab queue';
            }
            if ($needsRadiology) {
                $parts[] = 'sent to radiology queue';
            }
            if ($needsBed) {
                $parts[] = 'sent to bed queue';
            }
            if ($needsPharmacy) {
                $parts[] = 'sent to pharmacy queue';
            }
            $message .= ' Patient ' . implode(' and ', $parts) . '.';
        }
        header('Location: medical_records.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create medical record. Please try again.';
    }
}

// ============================================================================
// CREATE PRESCRIPTION
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_prescription') {
    $visitId = intval($_POST['visit_id']);
    $patientId = intval($_POST['patient_id']);
    
    $visitQuery = "SELECT attending_doctor_id FROM visits WHERE visit_id = ?";
    $visitStmt = $conn->prepare($visitQuery);
    $visitStmt->bind_param('i', $visitId);
    $visitStmt->execute();
    $visitRow = $visitStmt->get_result()->fetch_assoc();
    $doctorId = !empty($visitRow['attending_doctor_id']) ? intval($visitRow['attending_doctor_id']) : intval($_POST['doctor_id']);
    
    $prescriptionQuery = "INSERT INTO prescriptions (visit_id, prescribed_by, status) VALUES (?, ?, 'Pending')";
    $presStmt = $conn->prepare($prescriptionQuery);
    $presStmt->bind_param('ii', $visitId, $doctorId);
    
    if ($presStmt->execute()) {
        $prescriptionId = $conn->insert_id;
        
        $addedItems = 0;
        if (isset($_POST['medications']) && is_array($_POST['medications'])) {
            foreach ($_POST['medications'] as $medication) {
                if (!empty($medication['medication_id'])) {
                    $itemQuery = "INSERT INTO prescription_items (prescription_id, medication_id, dosage, duration_days, quantity) 
                                 VALUES (?, ?, ?, ?, ?)";
                    $itemStmt = $conn->prepare($itemQuery);
                    $medicationId = intval($medication['medication_id']);
                    $dosage = sanitizeInput($medication['dosage'] ?? '');
                    $durationDays = !empty($medication['duration_days']) ? intval($medication['duration_days']) : null;
                    $quantity = intval($medication['quantity'] ?? 1);
                    $itemStmt->bind_param('iisii', 
                        $prescriptionId,
                        $medicationId,
                        $dosage,
                        $durationDays,
                        $quantity
                    );
                    if ($itemStmt->execute()) {
                        $addedItems++;
                        $priceStmt = $conn->prepare("SELECT name, strength, unit_price FROM medications WHERE medication_id = ?");
                        $priceStmt->bind_param('i', $medicationId);
                        $priceStmt->execute();
                        $medInfo = $priceStmt->get_result()->fetch_assoc();
                        if ($medInfo) {
                            addInvoiceCharge($conn, $visitId, 'Medication: ' . $medInfo['name'] . ' ' . $medInfo['strength'], 'Medication', $quantity, (float) $medInfo['unit_price']);
                        }
                    }
                }
            }
        }
        
        if ($addedItems > 0) {
            $pharmacyFlag = $conn->prepare("UPDATE medical_records SET needs_pharmacy = 1 WHERE visit_id = ?");
            $pharmacyFlag->bind_param('i', $visitId);
            $pharmacyFlag->execute();
            logUserActivity($conn, $_SESSION['user_id'], 'Created Prescription', "Created prescription ID: {$prescriptionId} with {$addedItems} items");
            $message = 'Prescription created successfully and sent to pharmacy queue!';
            header('Location: medical_records.php?message=' . urlencode($message));
            exit();
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM prescriptions WHERE prescription_id = ?");
            $deleteStmt->bind_param('i', $prescriptionId);
            $deleteStmt->execute();
            $error = 'Please add at least one medication to the prescription.';
        }
    } else {
        $error = 'Failed to create prescription. Please try again.';
    }
}

// ============================================================================
// UPDATE MEDICAL RECORD
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_record') {
    $recordId = intval($_POST['record_id']);
    $query = "UPDATE medical_records SET 
              diagnosis = ?, clinical_notes = ?, doctor_id = ?, updated_at = NOW()
              WHERE record_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssii', 
        $_POST['diagnosis'],
        $_POST['clinical_notes'],
        $_POST['doctor_id'],
        $recordId
    );
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Medical Record', "Updated medical record ID: {$recordId}");
        $message = 'Medical record updated successfully!';
        header('Location: medical_records.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update medical record. Please try again.';
    }
}

// ============================================================================
// DELETE MEDICAL RECORD
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete_record' && isset($_GET['id'])) {
    $recordId = intval($_GET['id']);
    
    $query = "DELETE FROM medical_records WHERE record_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $recordId);
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Deleted Medical Record', "Deleted medical record ID: {$recordId}");
        $message = 'Medical record deleted successfully!';
        header('Location: medical_records.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to delete medical record. Please try again.';
    }
}

// ============================================================================
// GET MEDICAL RECORDS
// ============================================================================
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$patientFilter = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

$query = "SELECT mr.*, 
          CONCAT(p.first_name, ' ', p.last_name) as patient_name,
          p.patient_code,
          CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
          v.visit_code
          FROM medical_records mr
          JOIN patients p ON mr.patient_id = p.patient_id
          JOIN staff d ON mr.doctor_id = d.staff_id
          LEFT JOIN visits v ON mr.visit_id = v.visit_id
          WHERE 1=1";

$params = [];
$types = "";

if ($searchTerm) {
    $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_code LIKE ? OR mr.diagnosis LIKE ?)";
    $searchTermLike = "%{$searchTerm}%";
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $types .= "ssss";
}

if ($patientFilter > 0) {
    $query .= " AND mr.patient_id = ?";
    $params[] = $patientFilter;
    $types .= "i";
}

$query .= " ORDER BY mr.created_at ASC LIMIT 100";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$medicalRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pendingRecordQuery = "SELECT 0 as record_id, v.visit_id, '' as diagnosis, MIN(vs.recorded_at) as created_at,
                       p.patient_id, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       p.patient_code, v.visit_code,
                       CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                       FROM visits v
                       JOIN patients p ON v.patient_id = p.patient_id
                       JOIN vital_signs vs ON v.visit_id = vs.visit_id
                       LEFT JOIN staff d ON v.attending_doctor_id = d.staff_id
                       LEFT JOIN medical_records mr ON v.visit_id = mr.visit_id
                       JOIN lookup_visit_statuses lvs ON v.visit_status_id = lvs.visit_status_id
                       WHERE mr.record_id IS NULL 
                       AND lvs.name NOT IN ('Cancelled', 'Discharged')
                       GROUP BY v.visit_id
                       ORDER BY created_at ASC LIMIT 100";
$pendingRecords = $conn->query($pendingRecordQuery)->fetch_all(MYSQLI_ASSOC);

// Get records with lab results ready (only for identified samples with sample IDs)
$labReadyQuery = "SELECT mr.record_id, mr.visit_id, mr.diagnosis, mr.lab_results_ready_at,
                   p.patient_id, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                   p.patient_code, v.visit_code,
                   CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                   FROM medical_records mr
                   JOIN patients p ON mr.patient_id = p.patient_id
                   JOIN visits v ON mr.visit_id = v.visit_id
                   JOIN staff d ON mr.doctor_id = d.staff_id
                   JOIN lab_orders lo ON mr.visit_id = lo.visit_id
                   WHERE mr.lab_results_ready = 1
                   AND lo.sample_id IS NOT NULL
                   AND lo.sample_id != ''
                   GROUP BY mr.record_id
                   ORDER BY mr.lab_results_ready_at ASC LIMIT 50";
$labReadyRecords = $conn->query($labReadyQuery)->fetch_all(MYSQLI_ASSOC);

// Get record for edit
$editRecord = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_record' && isset($_GET['id'])) {
    $recordId = intval($_GET['id']);
    $query = "SELECT * FROM medical_records WHERE record_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $recordId);
    $stmt->execute();
    $editRecord = $stmt->get_result()->fetch_assoc();
}

// Get medications for prescription modal
$medicationsQuery = "SELECT medication_id, name, strength, unit, unit_price FROM medications WHERE is_active = 1 ORDER BY name ASC";
$medications = $conn->query($medicationsQuery)->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - HMS</title>
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
            display: <?php echo ($editRecord || isset($_GET['action']) && $_GET['action'] === 'create_record') ? 'flex' : 'none'; ?>;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }
        .form-modal-content {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            max-width: 650px;
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
        .action-buttons a {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
        }
        .btn-edit {
            background: #dbeafe;
            color: #2563eb;
        }
        .btn-edit:hover {
            background: #bfdbfe;
        }
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }
        .btn-delete:hover {
            background: #fecaca;
        }
        .btn-view {
            background: #d1fae5;
            color: #059669;
        }
        .btn-view:hover {
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
        .search-box {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-box input, .search-box select {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .search-box input:focus, .search-box select:focus {
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
        .record-diagnosis {
            font-weight: 500;
            color: #0f172a;
        }
        .record-notes {
            color: #64748b;
            font-size: 13px;
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .record-meta {
            font-size: 12px;
            color: #94a3b8;
        }
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
        .referral-toggle {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            user-select: none;
        }
        .referral-toggle input {
            width: auto;
            margin: 0;
            cursor: pointer;
        }
        .referral-toggle.checked-lab {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #2563eb;
        }
        .referral-toggle.checked-radiology {
            background: #f3e8ff;
            border-color: #d8b4fe;
            color: #7c3aed;
        }
        .referral-toggle.checked-bed {
            background: #fef3c7;
            border-color: #fcd34d;
            color: #d97706;
        }
        .referral-toggle.checked-pharmacy {
            background: #ede9fe;
            border-color: #c4b5fd;
            color: #7c3aed;
        }
        .referral-toggle a {
            color: inherit;
            text-decoration: none;
        }
        .referral-toggle a:hover {
            opacity: 0.8;
        }
        .btn-history {
            background: #ede9fe;
            color: #7c3aed;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-history:hover {
            background: #ddd6fe;
        }
        .history-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        .history-overlay.open {
            opacity: 1;
            visibility: visible;
        }
        .history-sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: 420px;
            max-width: 95vw;
            height: 100vh;
            background: #fff;
            box-shadow: -4px 0 24px rgba(0, 0, 0, 0.12);
            z-index: 10001;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .history-sidebar.open {
            transform: translateX(0);
        }
        .history-sidebar-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }
        .history-sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            color: #0f172a;
        }
        .history-sidebar-header p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #64748b;
        }
        .history-close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: #94a3b8;
            cursor: pointer;
            line-height: 1;
        }
        .history-close-btn:hover {
            color: #ef4444;
        }
        .history-sidebar-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px 24px 24px;
        }
        .history-section {
            margin-bottom: 24px;
        }
        .history-section h3 {
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 0 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .history-item {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            border-left: 3px solid #2563eb;
        }
        .history-item .history-item-title {
            font-weight: 600;
            font-size: 14px;
            color: #0f172a;
        }
        .history-item .history-item-meta {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .history-item .history-item-detail {
            font-size: 13px;
            color: #475569;
            margin-top: 6px;
        }
        .history-empty {
            color: #94a3b8;
            font-size: 13px;
            font-style: italic;
        }
        .history-loading {
            text-align: center;
            padding: 40px;
            color: #64748b;
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
                    <h1>Medical Records</h1>
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
                <div class="search-box">
                    <form method="GET" action="" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <input type="text" name="search" placeholder="Search records..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="width: 200px;">
                        <select name="patient_id">
                            <option value="0">All Patients</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patient_id']; ?>" <?php echo ($patientFilter == $patient['patient_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($searchTerm || $patientFilter): ?>
                            <a href="medical_records.php" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <button class="btn-create" onclick="window.location.href='medical_records.php?action=create_record'">
                    <i class="fas fa-plus"></i> New Medical Record
                </button>
            </div>

            <?php if (!empty($labReadyRecords)): ?>
            <div class="table-card" style="border: 2px solid #22c55e; background: #f0fdf4;">
                <details open>
                    <summary style="cursor: pointer; padding: 14px 0; font-size: 18px; font-weight: 700; color: #16a34a;">
                        <i class="fas fa-flask"></i> Lab Results Ready (<?php echo count($labReadyRecords); ?>)
                    </summary>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead><tr><th>Patient</th><th>Visit</th><th>Doctor</th><th>Ready At</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($labReadyRecords as $record): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($record['patient_name']); ?></strong><br><small><?php echo htmlspecialchars($record['patient_code']); ?></small></td>
                                        <td><?php echo htmlspecialchars($record['visit_code']); ?></td>
                                        <td><?php echo htmlspecialchars($record['doctor_name']); ?></td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($record['lab_results_ready_at'])); ?></td>
                                        <td>
                                            <a href="lab.php?action=view_grouped&visit_id=<?php echo $record['visit_id']; ?>&from=medical_records" class="btn-create-action" style="background: #22c55e;">
                                                <i class="fas fa-eye"></i> View Results
                                            </a>
                                            <a href="medical_records.php?action=create_prescription&visit_id=<?php echo $record['visit_id']; ?>" class="btn-create-action" style="background: #8b5cf6;">
                                                <i class="fas fa-prescription-bottle"></i> Prescription
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
            <?php endif; ?>

            <div class="table-card">
                <details open>
                    <summary style="cursor: pointer; padding: 14px 0; font-size: 18px; font-weight: 700; color: #1e293b;">
                        <i class="fas fa-user-clock"></i> Patients Waiting for Doctor Record (<?php echo count($pendingRecords); ?>)
                    </summary>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead><tr><th>#</th><th>Patient</th><th>Visit</th><th>Doctor</th><th>Arrival</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if (empty($pendingRecords)): ?>
                                    <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 32px;">No new patients are waiting for a doctor record.</td></tr>
                                <?php else: foreach ($pendingRecords as $position => $pending): ?>
                                    <tr>
                                        <td><?php echo $position + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($pending['patient_name']); ?></strong><br><small><?php echo htmlspecialchars($pending['patient_code']); ?></small></td>
                                        <td><?php echo htmlspecialchars($pending['visit_code']); ?></td>
                                        <td><?php echo htmlspecialchars($pending['doctor_name']); ?></td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($pending['created_at'])); ?></td>
                                        <td><a href="medical_records.php?action=create_record&visit_id=<?php echo $pending['visit_id']; ?>" class="btn-create-action"><i class="fas fa-file-medical"></i> Fill Record</a></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>

            <div class="table-card">
                <details open>
                    <summary style="cursor: pointer; padding: 14px 0; font-size: 18px; font-weight: 700; color: #1e293b;">
                        <i class="fas fa-history"></i> Previous Medical Records (<?php echo count($medicalRecords); ?>)
                    </summary>
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Visit</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th>Notes</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($medicalRecords)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-notes-medical" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        No medical records found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($medicalRecords as $record): ?>
                                <tr>
                                    <td>
                                        <div class="patient-info">
                                            <div class="avatar" style="background: <?php echo getUserColor($record['patient_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo strtoupper(substr($record['patient_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="patient-name"><?php echo htmlspecialchars($record['patient_name']); ?></span>
                                                <small><?php echo htmlspecialchars($record['patient_code']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['visit_code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($record['doctor_name']); ?></td>
                                    <td class="record-diagnosis"><?php echo htmlspecialchars($record['diagnosis'] ?? 'N/A'); ?></td>
                                    <td class="record-notes"><?php echo htmlspecialchars($record['clinical_notes'] ?? ''); ?></td>
                                    <td>
                                        <div class="record-meta">
                                            <?php echo date('M d, Y', strtotime($record['created_at'])); ?>
                                            <?php if ($record['updated_at'] && $record['updated_at'] != $record['created_at']): ?>
                                                <br><small>Updated: <?php echo date('M d, Y', strtotime($record['updated_at'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="medical_records.php?action=edit_record&id=<?php echo $record['record_id']; ?>" class="btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <label class="referral-toggle <?php echo !empty($record['needs_lab']) ? 'checked-lab' : ''; ?>" title="Mark patient for lab tests">
                                                <input type="checkbox" class="flag-toggle" data-record-id="<?php echo $record['record_id']; ?>" data-flag="needs_lab" <?php echo !empty($record['needs_lab']) ? 'checked' : ''; ?>>
                                                <?php if (!empty($record['needs_lab']) && $record['visit_id']): ?>
                                                    <a href="lab.php?action=sample_collect&visit_id=<?php echo $record['visit_id']; ?>" title="Open lab page"><i class="fas fa-flask"></i> Lab</a>
                                                <?php else: ?>
                                                    <span><i class="fas fa-flask"></i> Lab</span>
                                                <?php endif; ?>
                                            </label>
                                            <label class="referral-toggle <?php echo !empty($record['needs_radiology']) ? 'checked-radiology' : ''; ?>" title="Mark patient for radiology">
                                                <input type="checkbox" class="flag-toggle" data-record-id="<?php echo $record['record_id']; ?>" data-flag="needs_radiology" <?php echo !empty($record['needs_radiology']) ? 'checked' : ''; ?>">
                                                <?php if (!empty($record['needs_radiology']) && $record['visit_id']): ?>
                                                    <a href="radiology.php?action=create&visit_id=<?php echo $record['visit_id']; ?>" title="Open radiology page"><i class="fas fa-x-ray"></i> Radiology</a>
                                                <?php else: ?>
                                                    <span><i class="fas fa-x-ray"></i> Radiology</span>
                                                <?php endif; ?>
                                            </label>
                                            <label class="referral-toggle <?php echo !empty($record['needs_bed']) ? 'checked-bed' : ''; ?>" title="Mark patient for bed assignment">
                                                <input type="checkbox" class="flag-toggle" data-record-id="<?php echo $record['record_id']; ?>" data-flag="needs_bed" <?php echo !empty($record['needs_bed']) ? 'checked' : ''; ?>>
                                                <?php if (!empty($record['needs_bed']) && $record['visit_id']): ?>
                                                    <a href="bed_management.php?action=assign_bed&visit_id=<?php echo $record['visit_id']; ?>" title="Open bed assignment"><i class="fas fa-bed"></i> Bed</a>
                                                <?php else: ?>
                                                    <span><i class="fas fa-bed"></i> Bed</span>
                                                <?php endif; ?>
                                            </label>
                                            <label class="referral-toggle <?php echo !empty($record['needs_pharmacy']) ? 'checked-pharmacy' : ''; ?>" title="Mark patient for pharmacy medicine">
                                                <input type="checkbox" class="flag-toggle" data-record-id="<?php echo $record['record_id']; ?>" data-flag="needs_pharmacy" <?php echo !empty($record['needs_pharmacy']) ? 'checked' : ''; ?>>
                                                <?php if (!empty($record['needs_pharmacy']) && $record['visit_id']): ?>
                                                    <a href="medical_records.php?action=create_prescription&visit_id=<?php echo $record['visit_id']; ?>" title="Create prescription"><i class="fas fa-pills"></i> Pharmacy</a>
                                                <?php else: ?>
                                                    <span><i class="fas fa-pills"></i> Pharmacy</span>
                                                <?php endif; ?>
                                            </label>
                                            <button type="button" class="btn-history" data-patient-id="<?php echo $record['patient_id']; ?>" data-patient-name="<?php echo htmlspecialchars($record['patient_name']); ?>">
                                                <i class="fas fa-clock-rotate-left"></i> History
                                            </button>
                                            <a href="medical_records.php?action=delete_record&id=<?php echo $record['record_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this medical record?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </details>
            </div>
        </main>
    </div>

    <!-- Create/Edit Record Modal -->
    <div class="form-modal" id="recordModal" style="display: <?php echo ($editRecord || isset($_GET['action']) && $_GET['action'] === 'create_record') ? 'flex' : 'none'; ?>;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='medical_records.php'">&times;</button>
            <h2 style="margin-bottom: 24px;"><?php echo $editRecord ? 'Edit Medical Record' : 'Create New Medical Record'; ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $editRecord ? 'update_record' : 'create_record'; ?>">
                <?php if ($editRecord): ?>
                    <input type="hidden" name="record_id" value="<?php echo $editRecord['record_id']; ?>">
                <?php endif; ?>
                
                <?php if (!$editRecord): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="patient_id">Patient *</label>
                        <?php if ($selectedPatientId > 0): ?>
                            <?php foreach ($patients as $patient): if ((int) $patient['patient_id'] === $selectedPatientId): ?>
                                <input type="hidden" name="patient_id" value="<?php echo $selectedPatientId; ?>">
                                <input type="text" value="<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_code'] . ')'); ?>" readonly>
                            <?php endif; endforeach; ?>
                        <?php else: ?>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patient_id']; ?>" <?php echo $selectedPatientId === (int) $patient['patient_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="visit_id">Visit</label>
                        <?php if ($selectedVisitId > 0): ?>
                            <?php foreach ($visits as $visit): if ((int) $visit['visit_id'] === $selectedVisitId): ?>
                                <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                                <input type="text" value="<?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['patient_name']); ?>" readonly>
                            <?php endif; endforeach; ?>
                        <?php else: ?>
                        <select id="visit_id" name="visit_id">
                            <option value="">Select Visit</option>
                            <?php foreach ($visits as $visit): ?>
                                <option value="<?php echo $visit['visit_id']; ?>" <?php echo $selectedVisitId === (int) $visit['visit_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['patient_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="patient_id" value="<?php echo $editRecord['patient_id']; ?>">
                    <input type="hidden" name="visit_id" value="<?php echo $editRecord['visit_id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="diagnosis">Diagnosis</label>
                    <textarea id="diagnosis" name="diagnosis" rows="2" style="width: 100%;" placeholder="e.g., Acute Bronchitis, Hypertension"><?php echo htmlspecialchars($editRecord['diagnosis'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="clinical_notes">Clinical Notes</label>
                    <textarea id="clinical_notes" name="clinical_notes" rows="5" placeholder="Detailed clinical notes, observations, and treatment plan..."><?php echo htmlspecialchars($editRecord['clinical_notes'] ?? ''); ?></textarea>
                </div>

                <?php if (!$editRecord): ?>
                <div class="form-group" style="background: #f8fafc; padding: 12px; border-radius: 8px;">
                    <label>Next step for this visit</label>
                    <label style="display: block; margin-top: 8px; font-weight: 400;">
                        <input type="checkbox" name="needs_lab" value="1">
                        Patient needs laboratory tests
                    </label>
                    <label style="display: block; margin-top: 8px; font-weight: 400;">
                        <input type="checkbox" name="needs_radiology" value="1">
                        Patient needs radiology imaging
                    </label>
                    <label style="display: block; margin-top: 8px; font-weight: 400;">
                        <input type="checkbox" name="needs_bed" value="1">
                        Patient needs bed assignment
                    </label>
                    <label style="display: block; margin-top: 8px; font-weight: 400;">
                        <input type="checkbox" name="needs_pharmacy" value="1">
                        Patient needs pharmacy medicine
                    </label>
                </div>
                <?php endif; ?>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <?php echo $editRecord ? 'Update Record' : 'Create Record'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='medical_records.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Patient History Sidebar -->
    <div class="history-overlay" id="historyOverlay"></div>
    <aside class="history-sidebar" id="historySidebar">
        <div class="history-sidebar-header">
            <div>
                <h2 id="historyPatientName">Patient History</h2>
                <p id="historyPatientCode"></p>
            </div>
            <button type="button" class="history-close-btn" id="historyCloseBtn">&times;</button>
        </div>
        <div class="history-sidebar-body" id="historySidebarBody">
            <div class="history-loading"><i class="fas fa-spinner fa-spin"></i> Loading history...</div>
        </div>
    </aside>

    <!-- Prescription Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'create_prescription' && isset($_GET['visit_id'])): ?>
    <?php 
        $presVisitId = intval($_GET['visit_id']);
        $visitData = getVisitById($conn, $presVisitId);
    ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content" style="max-width: 800px;">
            <button class="close-btn" onclick="window.location.href='medical_records.php'">&times;</button>
            <h2 style="margin-bottom: 24px;">Create Prescription</h2>
            
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                <p><strong>Patient:</strong> <?php echo htmlspecialchars($visitData['first_name'] . ' ' . $visitData['last_name']); ?></p>
                <p><strong>Visit Code:</strong> <?php echo htmlspecialchars($visitData['visit_code']); ?></p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_prescription">
                <input type="hidden" name="visit_id" value="<?php echo $presVisitId; ?>">
                <input type="hidden" name="patient_id" value="<?php echo $visitData['patient_id']; ?>">
                <input type="hidden" name="doctor_id" value="<?php echo $_SESSION['user_id']; ?>">
                
                <div id="medicationItems">
                    <div class="medication-item" style="background: #f1f5f9; padding: 16px; border-radius: 8px; margin-bottom: 12px;">
                        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
                            <div>
                                <label style="display: block; margin-bottom: 6px; font-weight: 500;">Medication *</label>
                                <select name="medications[0][medication_id]" required style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                    <option value="">Select medication...</option>
                                    <?php foreach ($medications as $med): ?>
                                        <option value="<?php echo $med['medication_id']; ?>" data-price="<?php echo $med['unit_price']; ?>">
                                            <?php echo htmlspecialchars($med['name'] . ' ' . $med['strength'] . ' ' . $med['unit']); ?> - $<?php echo number_format($med['unit_price'], 2); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 6px; font-weight: 500;">Dosage</label>
                                <input type="text" name="medications[0][dosage]" placeholder="e.g., 500mg" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 6px; font-weight: 500;">Duration (days)</label>
                                <input type="number" name="medications[0][duration_days]" min="1" value="7" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 6px; font-weight: 500;">Quantity</label>
                                <input type="number" name="medications[0][quantity]" min="1" value="1" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            </div>
                            <div>
                                <button type="button" onclick="removeMedicationItem(this)" class="btn-cancel" style="padding: 8px 12px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" onclick="addMedicationItem()" class="btn-cancel" style="margin-bottom: 20px; width: 100%; border-style: dashed;">
                    <i class="fas fa-plus"></i> Add Another Medication
                </button>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-prescription-bottle"></i> Create Prescription
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='medical_records.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="assets/js/dashboard.js"></script>
    <script>
    let medicationItemCount = 1;
    
    function addMedicationItem() {
        const container = document.getElementById('medicationItems');
        const newItem = document.createElement('div');
        newItem.className = 'medication-item';
        newItem.style.cssText = 'background: #f1f5f9; padding: 16px; border-radius: 8px; margin-bottom: 12px;';
        newItem.innerHTML = `
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500;">Medication *</label>
                    <select name="medications[${medicationItemCount}][medication_id]" required style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="">Select medication...</option>
                        <?php foreach ($medications as $med): ?>
                            <option value="<?php echo $med['medication_id']; ?>" data-price="<?php echo $med['unit_price']; ?>">
                                <?php echo htmlspecialchars($med['name'] . ' ' . $med['strength'] . ' ' . $med['unit']); ?> - $<?php echo number_format($med['unit_price'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500;">Dosage</label>
                    <input type="text" name="medications[${medicationItemCount}][dosage]" placeholder="e.g., 500mg" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500;">Duration (days)</label>
                    <input type="number" name="medications[${medicationItemCount}][duration_days]" min="1" value="7" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500;">Quantity</label>
                    <input type="number" name="medications[${medicationItemCount}][quantity]" min="1" value="1" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div>
                    <button type="button" onclick="removeMedicationItem(this)" class="btn-cancel" style="padding: 8px 12px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(newItem);
        medicationItemCount++;
    }
    
    function removeMedicationItem(button) {
        const items = document.querySelectorAll('.medication-item');
        if (items.length > 1) {
            button.closest('.medication-item').remove();
        } else {
            alert('At least one medication is required.');
        }
    }
    </script>
    <script>
        document.querySelectorAll('.flag-toggle').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const recordId = this.dataset.recordId;
                const flag = this.dataset.flag;
                const value = this.checked ? '1' : '0';
                const label = this.closest('.referral-toggle');

                fetch('medical_records.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=toggle_flag&record_id=' + recordId + '&flag=' + flag + '&value=' + value
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (!data.success) {
                        checkbox.checked = !checkbox.checked;
                        alert(data.error || 'Failed to update');
                        return;
                    }
                    label.classList.toggle('checked-lab', flag === 'needs_lab' && checkbox.checked);
                    label.classList.toggle('checked-radiology', flag === 'needs_radiology' && checkbox.checked);
                    label.classList.toggle('checked-bed', flag === 'needs_bed' && checkbox.checked);
                    label.classList.toggle('checked-pharmacy', flag === 'needs_pharmacy' && checkbox.checked);
                    window.location.reload();
                })
                .catch(function() {
                    checkbox.checked = !checkbox.checked;
                    alert('Network error. Please try again.');
                });
            });
        });

        const historyOverlay = document.getElementById('historyOverlay');
        const historySidebar = document.getElementById('historySidebar');
        const historySidebarBody = document.getElementById('historySidebarBody');
        const historyPatientName = document.getElementById('historyPatientName');
        const historyPatientCode = document.getElementById('historyPatientCode');

        function closeHistorySidebar() {
            historyOverlay.classList.remove('open');
            historySidebar.classList.remove('open');
        }

        function openHistorySidebar() {
            historyOverlay.classList.add('open');
            historySidebar.classList.add('open');
        }

        document.getElementById('historyCloseBtn').addEventListener('click', closeHistorySidebar);
        historyOverlay.addEventListener('click', closeHistorySidebar);

        document.querySelectorAll('.btn-history').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const patientId = this.dataset.patientId;
                const patientName = this.dataset.patientName;
                historyPatientName.textContent = patientName;
                historyPatientCode.textContent = '';
                historySidebarBody.innerHTML = '<div class="history-loading"><i class="fas fa-spinner fa-spin"></i> Loading history...</div>';
                openHistorySidebar();

                fetch('medical_records.php?action=patient_history&patient_id=' + patientId)
                    .then(function(res) { return res.json(); })
                    .then(function(result) {
                        if (!result.success) {
                            historySidebarBody.innerHTML = '<p class="history-empty">' + (result.error || 'Could not load history') + '</p>';
                            return;
                        }
                        historyPatientCode.textContent = result.data.patient.patient_code || '';
                        historySidebarBody.innerHTML = renderPatientHistory(result.data);
                    })
                    .catch(function() {
                        historySidebarBody.innerHTML = '<p class="history-empty">Failed to load patient history.</p>';
                    });
            });
        });

        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function renderPatientHistory(data) {
            let html = '';

            html += '<div class="history-section"><h3><i class="fas fa-hospital-user"></i> Visits (' + data.visits.length + ')</h3>';
            if (data.visits.length === 0) {
                html += '<p class="history-empty">No visits recorded</p>';
            } else {
                data.visits.forEach(function(v) {
                    html += '<div class="history-item"><div class="history-item-title">' + escapeHtml(v.visit_code) + ' — ' + escapeHtml(v.visit_type) + '</div>';
                    html += '<div class="history-item-meta">' + formatDate(v.admitted_at) + ' · ' + escapeHtml(v.visit_status) + (v.doctor_name ? ' · Dr. ' + escapeHtml(v.doctor_name) : '') + '</div></div>';
                });
            }
            html += '</div>';

            html += '<div class="history-section"><h3><i class="fas fa-notes-medical"></i> Medical Records (' + data.medical_records.length + ')</h3>';
            if (data.medical_records.length === 0) {
                html += '<p class="history-empty">No medical records</p>';
            } else {
                data.medical_records.forEach(function(r) {
                    html += '<div class="history-item"><div class="history-item-title">' + escapeHtml(r.diagnosis || 'No diagnosis') + '</div>';
                    html += '<div class="history-item-meta">' + formatDate(r.created_at) + ' · Dr. ' + escapeHtml(r.doctor_name) + '</div>';
                    if (r.clinical_notes) {
                        html += '<div class="history-item-detail">' + escapeHtml(r.clinical_notes) + '</div>';
                    }
                    html += '</div>';
                });
            }
            html += '</div>';

            html += '<div class="history-section"><h3><i class="fas fa-flask"></i> Lab Orders (' + data.lab_orders.length + ')</h3>';
            if (data.lab_orders.length === 0) {
                html += '<p class="history-empty">No lab orders</p>';
            } else {
                data.lab_orders.forEach(function(l) {
                    html += '<div class="history-item"><div class="history-item-title">' + escapeHtml(l.test_name) + '</div>';
                    html += '<div class="history-item-meta">' + formatDate(l.ordered_at) + ' · ' + escapeHtml(l.status_name) + ' · ' + escapeHtml(l.visit_code) + '</div></div>';
                });
            }
            html += '</div>';

            html += '<div class="history-section"><h3><i class="fas fa-heart-pulse"></i> Vital Signs (' + data.vital_signs.length + ')</h3>';
            if (data.vital_signs.length === 0) {
                html += '<p class="history-empty">No vital signs recorded</p>';
            } else {
                data.vital_signs.forEach(function(vs) {
                    const parts = [];
                    if (vs.temperature_c) parts.push('Temp: ' + vs.temperature_c + '°C');
                    if (vs.blood_pressure) parts.push('BP: ' + vs.blood_pressure);
                    if (vs.pulse_bpm) parts.push('Pulse: ' + vs.pulse_bpm);
                    html += '<div class="history-item"><div class="history-item-title">' + escapeHtml(vs.visit_code) + '</div>';
                    html += '<div class="history-item-detail">' + escapeHtml(parts.join(' · ') || 'Recorded') + '</div>';
                    html += '<div class="history-item-meta">' + formatDate(vs.recorded_at) + '</div></div>';
                });
            }
            html += '</div>';

            html += '<div class="history-section"><h3><i class="fas fa-pills"></i> Prescriptions (' + data.prescriptions.length + ')</h3>';
            if (data.prescriptions.length === 0) {
                html += '<p class="history-empty">No prescriptions</p>';
            } else {
                data.prescriptions.forEach(function(rx) {
                    html += '<div class="history-item"><div class="history-item-title">' + escapeHtml(rx.visit_code) + ' — ' + escapeHtml(rx.status) + '</div>';
                    html += '<div class="history-item-meta">' + formatDate(rx.prescribed_at) + ' · Dr. ' + escapeHtml(rx.doctor_name) + '</div></div>';
                });
            }
            html += '</div>';

            html += '<div class="history-section"><h3><i class="fas fa-bed"></i> Bed Assignments (' + data.bed_assignments.length + ')</h3>';
            if (data.bed_assignments.length === 0) {
                html += '<p class="history-empty">No bed assignments</p>';
            } else {
                data.bed_assignments.forEach(function(ba) {
                    html += '<div class="history-item"><div class="history-item-title">' + escapeHtml(ba.ward_name) + ' — Bed ' + escapeHtml(ba.bed_number) + '</div>';
                    html += '<div class="history-item-meta">' + formatDate(ba.assigned_at) + ' · ' + escapeHtml(ba.visit_code);
                    if (ba.discharged_at) html += ' · Discharged ' + formatDate(ba.discharged_at);
                    html += '</div></div>';
                });
            }
            html += '</div>';

            return html;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>