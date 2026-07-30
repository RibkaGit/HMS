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
// GET IPD PATIENTS (from bed_assignments)
// ============================================================================
$ipdPatientsQuery = "SELECT ba.assignment_id as ipd_record_id, ba.visit_id, v.patient_id, ba.bed_id, b.ward_id, 'Admitted' as status,
                     ba.assigned_at as admission_date, '' as primary_diagnosis, '' as admission_notes,
                     v.visit_code, v.admitted_at,
                     CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                     p.patient_code, p.date_of_birth,
                     b.bed_number,
                     w.name as ward_name,
                     CONCAT(d.first_name, ' ', d.last_name) as attending_doctor
              FROM bed_assignments ba
              JOIN visits v ON ba.visit_id = v.visit_id
              JOIN patients p ON v.patient_id = p.patient_id
              JOIN beds b ON ba.bed_id = b.bed_id
              JOIN wards w ON b.ward_id = w.ward_id
              LEFT JOIN staff d ON v.attending_doctor_id = d.staff_id
              WHERE ba.discharged_at IS NULL
              ORDER BY ba.assigned_at DESC";
$ipdPatientsResult = $conn->query($ipdPatientsQuery);
$ipdPatients = [];
if ($ipdPatientsResult) {
    $ipdPatients = $ipdPatientsResult->fetch_all(MYSQLI_ASSOC);
} else {
    $error = "Database query failed: " . $conn->error;
}

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

// Fetch lab orders for selected visit (only IPD visits - verified by bed assignment)
$labOrders = [];
if ($selectedVisitId > 0) {
    // First verify this is an IPD visit by checking bed assignments
    $ipdCheckQuery = "SELECT ba.*, v.visit_code, ba.assigned_at as admission_date
                      FROM bed_assignments ba
                      JOIN visits v ON ba.visit_id = v.visit_id
                      WHERE ba.visit_id = ? AND ba.discharged_at IS NULL";
    $ipdCheckStmt = $conn->prepare($ipdCheckQuery);
    if ($ipdCheckStmt) {
        $ipdCheckStmt->bind_param('i', $selectedVisitId);
        $ipdCheckStmt->execute();
        $ipdCheckResult = $ipdCheckStmt->get_result();
        
        if ($ipdCheckResult->num_rows > 0) {
            // This is an IPD visit, get admission date and filter lab orders
            $ipdVisit = $ipdCheckResult->fetch_assoc();
            $admissionDate = $ipdVisit['admission_date'];
            
            // Only fetch lab orders created on or after IPD admission
            // Use COALESCE to handle NULL dates - try ordered_at first, then created_at
            $labOrdersQuery = "SELECT lo.*, ltt.name as test_name, ltt.category as test_category, los.name as status_name
                               FROM lab_orders lo
                               JOIN lookup_test_types ltt ON lo.test_type_id = ltt.test_type_id
                               JOIN lookup_order_statuses los ON lo.order_status_id = los.order_status_id
                               WHERE lo.visit_id = ? AND COALESCE(lo.ordered_at, lo.created_at) >= ?
                               ORDER BY COALESCE(lo.ordered_at, lo.created_at) DESC";
            $labOrdersStmt = $conn->prepare($labOrdersQuery);
            if ($labOrdersStmt) {
                $labOrdersStmt->bind_param('is', $selectedVisitId, $admissionDate);
                $labOrdersStmt->execute();
                $labOrders = $labOrdersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            } else {
                $labOrders = [];
            }
        } else {
            // Not an IPD visit, no lab orders
            $labOrders = [];
        }
    } else {
        $labOrders = [];
    }
}

// Fetch prescriptions for selected visit (only IPD visits - verified by bed assignment)
$prescriptions = [];
if ($selectedVisitId > 0) {
    // First verify this is an IPD visit by checking bed assignments
    $ipdCheckQuery = "SELECT ba.*, v.visit_code, ba.assigned_at as admission_date
                      FROM bed_assignments ba
                      JOIN visits v ON ba.visit_id = v.visit_id
                      WHERE ba.visit_id = ? AND ba.discharged_at IS NULL";
    $ipdCheckStmt = $conn->prepare($ipdCheckQuery);
    if ($ipdCheckStmt) {
        $ipdCheckStmt->bind_param('i', $selectedVisitId);
        $ipdCheckStmt->execute();
        $ipdCheckResult = $ipdCheckStmt->get_result();
        
        if ($ipdCheckResult->num_rows > 0) {
            // This is an IPD visit, get admission date and filter prescriptions
            $ipdVisit = $ipdCheckResult->fetch_assoc();
            $admissionDate = $ipdVisit['admission_date'];
            
            // Only fetch prescriptions created on or after IPD admission
            $prescriptionsQuery = "SELECT p.*, CONCAT(s.first_name, ' ', s.last_name) as doctor_name
                                   FROM prescriptions p
                                   LEFT JOIN staff s ON p.doctor_id = s.staff_id
                                   WHERE p.visit_id = ? AND p.created_at >= ?
                                   ORDER BY p.created_at DESC";
            $prescriptionsStmt = $conn->prepare($prescriptionsQuery);
            if ($prescriptionsStmt) {
                $prescriptionsStmt->bind_param('is', $selectedVisitId, $admissionDate);
                $prescriptionsStmt->execute();
                $prescriptions = $prescriptionsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            } else {
                $prescriptions = [];
            }
        } else {
            // Not an IPD visit, no prescriptions
            $prescriptions = [];
        }
    } else {
        $prescriptions = [];
    }
}

// Fetch medical records for selected visit (only IPD visits - verified by bed assignment)
$medicalRecords = [];
if ($selectedVisitId > 0) {
    // First verify this is an IPD visit by checking bed assignments
    $ipdCheckQuery = "SELECT ba.*, v.visit_code, ba.assigned_at as admission_date
                      FROM bed_assignments ba
                      JOIN visits v ON ba.visit_id = v.visit_id
                      WHERE ba.visit_id = ? AND ba.discharged_at IS NULL";
    $ipdCheckStmt = $conn->prepare($ipdCheckQuery);
    if ($ipdCheckStmt) {
        $ipdCheckStmt->bind_param('i', $selectedVisitId);
        $ipdCheckStmt->execute();
        $ipdCheckResult = $ipdCheckStmt->get_result();
        
        if ($ipdCheckResult->num_rows > 0) {
            // This is an IPD visit, get admission date and filter medical records
            $ipdVisit = $ipdCheckResult->fetch_assoc();
            $admissionDate = $ipdVisit['admission_date'];
            
            // Only fetch medical records created on or after IPD admission
            $medicalRecordsQuery = "SELECT mr.*, CONCAT(s.first_name, ' ', s.last_name) as doctor_name
                                    FROM medical_records mr
                                    LEFT JOIN staff s ON mr.doctor_id = s.staff_id
                                    WHERE mr.visit_id = ? AND mr.created_at >= ?
                                    ORDER BY mr.created_at DESC";
            $medicalRecordsStmt = $conn->prepare($medicalRecordsQuery);
            if ($medicalRecordsStmt) {
                $medicalRecordsStmt->bind_param('is', $selectedVisitId, $admissionDate);
                $medicalRecordsStmt->execute();
                $medicalRecords = $medicalRecordsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            } else {
                $medicalRecords = [];
            }
        } else {
            // Not an IPD visit, no medical records
            $medicalRecords = [];
        }
    } else {
        $medicalRecords = [];
    }
}

// Fetch checkups for selected visit (only IPD visits - verified by bed assignment)
$checkups = [];
if ($selectedVisitId > 0) {
    // First verify this is an IPD visit by checking bed assignments
    $ipdCheckQuery = "SELECT ba.*, v.visit_code, ba.assigned_at as admission_date
                      FROM bed_assignments ba
                      JOIN visits v ON ba.visit_id = v.visit_id
                      WHERE ba.visit_id = ? AND ba.discharged_at IS NULL";
    $ipdCheckStmt = $conn->prepare($ipdCheckQuery);
    if ($ipdCheckStmt) {
        $ipdCheckStmt->bind_param('i', $selectedVisitId);
        $ipdCheckStmt->execute();
        $ipdCheckResult = $ipdCheckStmt->get_result();
        
        if ($ipdCheckResult->num_rows > 0) {
            // This is an IPD visit, get admission date and filter checkups
            $ipdVisit = $ipdCheckResult->fetch_assoc();
            $admissionDate = $ipdVisit['admission_date'];
            
            // Only fetch checkups created on or after IPD admission
            $checkupsQuery = "SELECT ic.*, CONCAT(s.first_name, ' ', s.last_name) as recorded_by_name
                              FROM ipd_checkups ic
                              LEFT JOIN staff s ON ic.recorded_by = s.staff_id
                              WHERE ic.visit_id = ? AND ic.checkup_time >= ?
                              ORDER BY ic.checkup_time DESC";
            $checkupsStmt = $conn->prepare($checkupsQuery);
            if ($checkupsStmt) {
                $checkupsStmt->bind_param('is', $selectedVisitId, $admissionDate);
                $checkupsStmt->execute();
                $checkups = $checkupsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            } else {
                $checkups = [];
            }
        } else {
            // Not an IPD visit, no checkups
            $checkups = [];
        }
    } else {
        $checkups = [];
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

    // Validate doctor_id exists in staff table, if not use current user
    $doctorCheckQuery = "SELECT staff_id FROM staff WHERE staff_id = ?";
    $doctorCheckStmt = $conn->prepare($doctorCheckQuery);
    if ($doctorCheckStmt) {
        $doctorCheckStmt->bind_param('i', $doctorId);
        $doctorCheckStmt->execute();
        if ($doctorCheckStmt->get_result()->num_rows === 0) {
            // Doctor not found, use current user
            $doctorId = intval($_SESSION['user_id']);
        }
    }

    $query = "INSERT INTO medical_records (visit_id, patient_id, doctor_id, diagnosis, clinical_notes, needs_lab, needs_radiology, needs_pharmacy)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $error = 'Failed to prepare medical record query: ' . $conn->error;
    } else {
        $stmt->bind_param('iiisiiii',
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
            header('Location: ipd_patients.php?visit_id=' . $visitId . '&tab=medical-records&message=' . urlencode($message));
            exit();
        } else {
            $error = 'Failed to create IPD medical record: ' . $stmt->error;
        }
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
        header('Location: ipd_patients.php?visit_id=' . $visitId . '&tab=lab&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Select at least one lab test and try again.';
    }
}

// ============================================================================
// COLLECT SAMPLE FOR LAB ORDER
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_sample') {
    $labOrderId = intval($_POST['lab_order_id']);
    
    // Get the "Sample Collected" status ID
    $sampleCollectedStatusId = getLookupId($conn, 'lookup_order_statuses', 'name', 'Sample Collected');
    
    $updateQuery = "UPDATE lab_orders SET order_status_id = ? WHERE lab_order_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param('ii', $sampleCollectedStatusId, $labOrderId);
    
    if ($updateStmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Collected Lab Sample', "Collected sample for lab order ID: {$labOrderId}");
        $message = 'Sample collected successfully!';
        header('Location: ipd_patients.php?tab=lab-management&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to collect sample. Please try again.';
    }
}

// ============================================================================
// ADD RESULT FOR LAB ORDER
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_result') {
    $labOrderId = intval($_POST['lab_order_id']);
    
    // Get the "Results Ready" status ID
    $resultsReadyStatusId = getLookupId($conn, 'lookup_order_statuses', 'name', 'Results Ready');
    
    $updateQuery = "UPDATE lab_orders SET order_status_id = ? WHERE lab_order_id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param('ii', $resultsReadyStatusId, $labOrderId);
    
    if ($updateStmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Added Lab Result', "Added result for lab order ID: {$labOrderId}");
        $message = 'Result added successfully!';
        header('Location: ipd_patients.php?tab=lab-management&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to add result. Please try again.';
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
        header('Location: ipd_patients.php?visit_id=' . $visitId . '&tab=pharmacy&message=' . urlencode($message));
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
                WHERE mr.visit_id = ?
                ORDER BY mr.created_at DESC";
    $mrStmt = $conn->prepare($mrQuery);
    if ($mrStmt) {
        $mrStmt->bind_param('i', $selectedVisitId);
        $mrStmt->execute();
        $ipdMedicalRecords = $mrStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
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
    if ($labStmt) {
        $labStmt->bind_param('i', $selectedVisitId);
        $labStmt->execute();
        $labOrders = $labStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
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
    if ($rxStmt) {
        $rxStmt->bind_param('i', $selectedVisitId);
        $rxStmt->execute();
        $prescriptions = $rxStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
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
    if ($checkupStmt) {
        $checkupStmt->bind_param('i', $selectedVisitId);
        $checkupStmt->execute();
        $checkups = $checkupStmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <title>IPD Patients - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .patient-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            transition: all 0.3s;
        }
        .patient-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-color: #8b5cf6;
        }
        .patient-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        .patient-info {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .patient-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #8b5cf6;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 600;
        }
        .patient-details h4 {
            margin: 0 0 2px 0;
            color: #0f172a;
            font-size: 14px;
        }
        .patient-details p {
            margin: 0;
            color: #64748b;
            font-size: 12px;
        }
        .patient-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .patient-actions a {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            text-decoration: none;
            color: white;
            cursor: pointer;
            display: inline-block;
        }
        .patient-actions a:hover {
            opacity: 0.9;
        }
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .tabs .tab {
            padding: 12px 20px;
            background: #f8fafc;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        .tabs .tab:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .tabs .tab.active {
            background: #8b5cf6;
            color: white;
        }
        .tabs .tab i {
            font-size: 16px;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .patient-meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f1f5f9;
        }
        .meta-item {
            font-size: 11px;
        }
        .meta-label {
            color: #64748b;
            display: block;
            font-size: 10px;
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
                <!-- Overview Tabs -->
                <div class="tabs" style="margin-bottom: 24px;">
                    <a href="ipd_patients.php?tab=patients" class="tab <?php echo $activeTab === 'patients' || $activeTab === 'overview' ? 'active' : ''; ?>" id="tab-patients">
                        <i class="fas fa-users"></i> Patients
                    </a>
                    <a href="ipd_patients.php?tab=lab-management" class="tab <?php echo $activeTab === 'lab-management' ? 'active' : ''; ?>" id="tab-lab-management">
                        <i class="fas fa-flask"></i> Lab Management
                    </a>
                    <a href="ipd_patients.php?tab=pharmacy-management" class="tab <?php echo $activeTab === 'pharmacy-management' ? 'active' : ''; ?>" id="tab-pharmacy-management">
                        <i class="fas fa-pills"></i> Pharmacy Management
                    </a>
                </div>

                <?php if ($activeTab === 'patients' || $activeTab === 'overview'): ?>
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
                                    <a href="ipd_patients.php?visit_id=<?php echo $patient['visit_id']; ?>&tab=medical-records" style="background: #3b82f6;">
                                        <i class="fas fa-notes-medical"></i> Records
                                    </a>
                                    <a href="ipd_patients.php?visit_id=<?php echo $patient['visit_id']; ?>&tab=lab" style="background: #10b981;">
                                        <i class="fas fa-flask"></i> Lab
                                    </a>
                                    <a href="ipd_patients.php?visit_id=<?php echo $patient['visit_id']; ?>&tab=pharmacy" style="background: #f59e0b;">
                                        <i class="fas fa-pills"></i> Pharmacy
                                    </a>
                                    <a href="ipd_patients.php?visit_id=<?php echo $patient['visit_id']; ?>&tab=checkups" style="background: #8b5cf6;">
                                        <i class="fas fa-clipboard-user"></i> Checkups
                                    </a>
                                    <a href="ipd_patients.php?visit_id=<?php echo $patient['visit_id']; ?>&tab=history" style="background: #6366f1;">
                                        <i class="fas fa-history"></i> History
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
                <?php endif; ?>

                <?php if ($activeTab === 'lab-management'): ?>
                <!-- Lab Management Tab - All IPD Lab Orders -->
                <div class="table-card">
                    <h2 style="margin-bottom: 20px;">IPD Lab Orders Management</h2>
                    
                    <!-- Status Filter -->
                    <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="ipd_patients.php?tab=lab-management" class="tab <?php echo !isset($_GET['status']) ? 'active' : ''; ?>" style="padding: 8px 16px; background: #e2e8f0; border-radius: 6px; text-decoration: none; color: #475569; font-size: 14px;">
                            All
                        </a>
                        <a href="ipd_patients.php?tab=lab-management&status=Ordered" class="tab <?php echo isset($_GET['status']) && $_GET['status'] === 'Ordered' ? 'active' : ''; ?>" style="padding: 8px 16px; background: #fef3c7; border-radius: 6px; text-decoration: none; color: #92400e; font-size: 14px;">
                            Awaiting Sample
                        </a>
                        <a href="ipd_patients.php?tab=lab-management&status=Sample Collected" class="tab <?php echo isset($_GET['status']) && $_GET['status'] === 'Sample Collected' ? 'active' : ''; ?>" style="padding: 8px 16px; background: #dbeafe; border-radius: 6px; text-decoration: none; color: #1e40af; font-size: 14px;">
                            Sample Collected
                        </a>
                        <a href="ipd_patients.php?tab=lab-management&status=Results Ready" class="tab <?php echo isset($_GET['status']) && $_GET['status'] === 'Results Ready' ? 'active' : ''; ?>" style="padding: 8px 16px; background: #dcfce7; border-radius: 6px; text-decoration: none; color: #166534; font-size: 14px;">
                            Results Ready
                        </a>
                    </div>
                    
                    <?php
                    // Fetch all IPD lab orders with optional status filter
                    $statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
                    $allIpdlabOrdersQuery = "SELECT lo.*, ltt.name as test_name, ltt.category as test_category, los.name as status_name, los.lookup_order_status_id as status_id,
                                             v.visit_code, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_code,
                                             b.bed_number, w.name as ward_name
                                            FROM lab_orders lo
                                            JOIN lookup_test_types ltt ON lo.test_type_id = ltt.test_type_id
                                            JOIN lookup_order_statuses los ON lo.order_status_id = los.order_status_id
                                            JOIN visits v ON lo.visit_id = v.visit_id
                                            JOIN patients p ON v.patient_id = p.patient_id
                                            JOIN bed_assignments ba ON v.visit_id = ba.visit_id
                                            JOIN beds b ON ba.bed_id = b.bed_id
                                            JOIN wards w ON b.ward_id = w.ward_id
                                            WHERE ba.discharged_at IS NULL";
                    if ($statusFilter) {
                        $allIpdlabOrdersQuery .= " AND los.name = '" . $conn->real_escape_string($statusFilter) . "'";
                    }
                    $allIpdlabOrdersQuery .= " ORDER BY lo.created_at DESC";
                    $allIpdlabOrdersResult = $conn->query($allIpdlabOrdersQuery);
                    $allIpdlabOrders = [];
                    if ($allIpdlabOrdersResult) {
                        $allIpdlabOrders = $allIpdlabOrdersResult->fetch_all(MYSQLI_ASSOC);
                    }
                    ?>
                    <?php if (empty($allIpdlabOrders)): ?>
                        <p style="text-align: center; color: #94a3b8; padding: 40px;">
                            <i class="fas fa-flask" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            No IPD lab orders found
                        </p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Patient</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Ward/Bed</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Test Name</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Category</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Status</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Ordered Date</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allIpdlabOrders as $order): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 12px 16px; color: #334155;">
                                            <strong><?php echo htmlspecialchars($order['patient_name']); ?></strong><br>
                                            <small style="color: #64748b;"><?php echo htmlspecialchars($order['patient_code']); ?></small>
                                        </td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($order['ward_name']); ?> / <?php echo htmlspecialchars($order['bed_number']); ?></td>
                                        <td style="padding: 12px 16px; color: #334155;"><?php echo htmlspecialchars($order['test_name']); ?></td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($order['test_category'] ?? 'N/A'); ?></td>
                                        <td style="padding: 12px 16px;">
                                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                                                <?php 
                                                if ($order['status_name'] === 'Ordered') echo 'background: #fef3c7; color: #92400e;';
                                                elseif ($order['status_name'] === 'Sample Collected') echo 'background: #dbeafe; color: #1e40af;';
                                                elseif ($order['status_name'] === 'Results Ready') echo 'background: #dcfce7; color: #166534;';
                                                else echo 'background: #f1f5f9; color: #64748b;';
                                                ?>">
                                                <?php echo htmlspecialchars($order['status_name']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo date('M d, Y H:i', strtotime($order['created_at'] ?? 'now')); ?></td>
                                        <td style="padding: 12px 16px;">
                                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                                <?php if ($order['status_name'] === 'Ordered'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="action" value="collect_sample">
                                                        <input type="hidden" name="lab_order_id" value="<?php echo $order['lab_order_id']; ?>">
                                                        <button type="submit" onclick="return confirm('Mark sample as collected?')" style="padding: 6px 12px; background: #10b981; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer;">
                                                            <i class="fas fa-vial"></i> Collect Sample
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($order['status_name'] === 'Sample Collected'): ?>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="action" value="add_result">
                                                        <input type="hidden" name="lab_order_id" value="<?php echo $order['lab_order_id']; ?>">
                                                        <button type="submit" style="padding: 6px 12px; background: #3b82f6; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer;">
                                                            <i class="fas fa-file-medical"></i> Add Result
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="ipd_patients.php?visit_id=<?php echo $order['visit_id']; ?>&tab=lab" style="padding: 6px 12px; background: #64748b; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block;">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($activeTab === 'pharmacy-management'): ?>
                <!-- Pharmacy Management Tab - All IPD Prescriptions -->
                <div class="table-card">
                    <h2 style="margin-bottom: 20px;">IPD Prescriptions Management</h2>
                    <?php
                    // Fetch all IPD prescriptions
                    $allIpdPrescriptionsQuery = "SELECT p.*, CONCAT(s.first_name, ' ', s.last_name) as doctor_name,
                                                  v.visit_code, CONCAT(pat.first_name, ' ', pat.last_name) as patient_name, pat.patient_code,
                                                  b.bed_number, w.name as ward_name
                                                 FROM prescriptions p
                                                 LEFT JOIN staff s ON p.doctor_id = s.staff_id
                                                 JOIN visits v ON p.visit_id = v.visit_id
                                                 JOIN patients pat ON v.patient_id = pat.patient_id
                                                 JOIN bed_assignments ba ON v.visit_id = ba.visit_id
                                                 JOIN beds b ON ba.bed_id = b.bed_id
                                                 JOIN wards w ON b.ward_id = w.ward_id
                                                 WHERE ba.discharged_at IS NULL
                                                 ORDER BY p.created_at DESC";
                    $allIpdPrescriptionsResult = $conn->query($allIpdPrescriptionsQuery);
                    $allIpdPrescriptions = [];
                    if ($allIpdPrescriptionsResult) {
                        $allIpdPrescriptions = $allIpdPrescriptionsResult->fetch_all(MYSQLI_ASSOC);
                    }
                    ?>
                    <?php if (empty($allIpdPrescriptions)): ?>
                        <p style="text-align: center; color: #94a3b8; padding: 40px;">
                            <i class="fas fa-pills" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            No IPD prescriptions found
                        </p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Prescription ID</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Patient</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Ward/Bed</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Doctor</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Status</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Created Date</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allIpdPrescriptions as $prescription): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 12px 16px; color: #334155; font-weight: 600;">#<?php echo $prescription['prescription_id']; ?></td>
                                        <td style="padding: 12px 16px; color: #334155;">
                                            <strong><?php echo htmlspecialchars($prescription['patient_name']); ?></strong><br>
                                            <small style="color: #64748b;"><?php echo htmlspecialchars($prescription['patient_code']); ?></small>
                                        </td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($prescription['ward_name']); ?> / <?php echo htmlspecialchars($prescription['bed_number']); ?></td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($prescription['doctor_name'] ?? 'N/A'); ?></td>
                                        <td style="padding: 12px 16px;">
                                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                                                <?php 
                                                if ($prescription['status'] === 'Pending') echo 'background: #fef3c7; color: #92400e;';
                                                elseif ($prescription['status'] === 'Dispensed') echo 'background: #dcfce7; color: #166534;';
                                                else echo 'background: #f1f5f9; color: #64748b;';
                                                ?>">
                                                <?php echo htmlspecialchars($prescription['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo date('M d, Y H:i', strtotime($prescription['created_at'] ?? 'now')); ?></td>
                                        <td style="padding: 12px 16px;">
                                            <a href="ipd_patients.php?visit_id=<?php echo $prescription['visit_id']; ?>&tab=pharmacy" style="padding: 6px 12px; background: #f59e0b; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
                    <a href="ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>&tab=medical-records" class="tab <?php echo $activeTab === 'medical-records' ? 'active' : ''; ?>" id="tab-medical-records">
                        <i class="fas fa-notes-medical"></i> Medical Records
                    </a>
                    <a href="ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>&tab=lab" class="tab <?php echo $activeTab === 'lab' ? 'active' : ''; ?>" id="tab-lab">
                        <i class="fas fa-flask"></i> Lab
                    </a>
                    <a href="ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>&tab=pharmacy" class="tab <?php echo $activeTab === 'pharmacy' ? 'active' : ''; ?>" id="tab-pharmacy">
                        <i class="fas fa-pills"></i> Pharmacy
                    </a>
                    <a href="ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>&tab=checkups" class="tab <?php echo $activeTab === 'checkups' ? 'active' : ''; ?>" id="tab-checkups">
                        <i class="fas fa-clipboard-user"></i> Checkups
                    </a>
                    <a href="ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>&tab=history" class="tab <?php echo $activeTab === 'history' ? 'active' : ''; ?>" id="tab-history">
                        <i class="fas fa-history"></i> History
                    </a>
                </div>

                <!-- Medical Records Tab Content -->
                <div class="tab-content <?php echo $activeTab === 'medical-records' ? 'active' : ''; ?>" id="content-medical-records">
                    <div class="table-card">
                        <h2 style="margin-bottom: 24px; font-size: 24px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">Create New Medical Record</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_ipd_record">
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patient_id'] ?? 0; ?>">

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px;">Attending Doctor</label>
                                <input type="text" value="<?php echo htmlspecialchars($selectedPatient['attending_doctor'] ?? 'N/A'); ?>" readonly style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; background: #f8fafc; color: #64748b; font-size: 14px;">
                                <input type="hidden" name="doctor_id" value="<?php echo $selectedPatient['attending_doctor_id'] ?? 0; ?>">
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="diagnosis" style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px;">Diagnosis *</label>
                                <textarea id="diagnosis" name="diagnosis" rows="4" required placeholder="Primary diagnosis and clinical findings..." style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical; transition: border-color 0.2s;"></textarea>
                            </div>

                            <div class="form-group" style="margin-bottom: 24px;">
                                <label for="clinical_notes" style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px;">Clinical Notes</label>
                                <textarea id="clinical_notes" name="clinical_notes" rows="5" placeholder="Detailed clinical notes, history, and observations..." style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical; transition: border-color 0.2s;"></textarea>
                            </div>

                            <div class="btn-group" style="display: flex; gap: 12px; margin-top: 28px;">
                                <button type="submit" class="btn-submit" style="flex: 1; padding: 14px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                    <i class="fas fa-save"></i> Save Medical Record
                                </button>
                                <button type="button" class="btn-cancel" onclick="window.location.href='ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>&tab=checkups';" style="flex: 1; padding: 14px 24px; background: #f1f5f9; color: #64748b; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s;">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <?php if (!empty($medicalRecords)): ?>
                    <div class="table-card" style="margin-top: 24px;">
                        <h3 style="margin-bottom: 20px; font-size: 20px; color: #1e293b;">IPD Medical Records History</h3>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Date</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Doctor</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Diagnosis</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Clinical Notes</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medicalRecords as $record): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo date('M d, Y H:i', strtotime($record['created_at'])); ?></td>
                                        <td style="padding: 12px 16px; color: #334155;"><?php echo htmlspecialchars($record['doctor_name'] ?? 'N/A'); ?></td>
                                        <td style="padding: 12px 16px; color: #334155;"><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 100)) . (strlen($record['diagnosis']) > 100 ? '...' : ''); ?></td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars(substr($record['clinical_notes'], 0, 100)) . (strlen($record['clinical_notes']) > 100 ? '...' : ''); ?></td>
                                        <td style="padding: 12px 16px;">
                                            <button type="button" onclick="editMedicalRecord(<?php echo $record['record_id']; ?>)" style="padding: 6px 12px; background: #3b82f6; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; transition: all 0.2s;">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Lab Tab Content -->
                <div class="tab-content <?php echo $activeTab === 'lab' ? 'active' : ''; ?>" id="content-lab">
                    <div class="table-card">
                        <h2 style="margin-bottom: 24px; font-size: 24px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">Order Lab Tests</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_lab_order">
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">

                            <div class="form-group" style="margin-bottom: 24px;">
                                <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 12px;">Select Lab Tests *</label>
                                <?php foreach ($labCategories as $category => $tests): ?>
                                    <div style="margin-bottom: 20px; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <strong style="display: block; margin-bottom: 12px; color: #475569; font-size: 15px; font-weight: 600;"><?php echo htmlspecialchars($category); ?></strong>
                                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                            <?php foreach ($tests as $test): ?>
                                                <label style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: white; border-radius: 6px; border: 1px solid #e2e8f0; cursor: pointer; transition: all 0.2s;">
                                                    <input type="checkbox" name="test_type_ids[]" value="<?php echo $test['test_type_id']; ?>" style="width: 18px; height: 18px; accent-color: #10b981;">
                                                    <span style="color: #334155; font-size: 14px;"><?php echo htmlspecialchars($test['name']); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="btn-group" style="display: flex; gap: 12px; margin-top: 28px;">
                                <button type="submit" class="btn-submit" style="flex: 1; padding: 14px 24px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                    <i class="fas fa-flask"></i> Order Lab Tests
                                </button>
                                <button type="button" class="btn-cancel" onclick="window.location.href='ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>&tab=checkups';" style="flex: 1; padding: 14px 24px; background: #f1f5f9; color: #64748b; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s;">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <?php if (!empty($labOrders)): ?>
                    <div class="table-card" style="margin-top: 24px;">
                        <h3 style="margin-bottom: 20px; font-size: 20px; color: #1e293b;">Lab Orders History</h3>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Test Name</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Category</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Status</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Ordered Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($labOrders as $order): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 12px 16px; color: #334155;"><?php echo htmlspecialchars($order['test_name']); ?></td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($order['test_category']); ?></td>
                                        <td style="padding: 12px 16px;">
                                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                                                <?php 
                                                if ($order['status_name'] === 'Ordered') echo 'background: #fef3c7; color: #92400e;';
                                                elseif ($order['status_name'] === 'Sample Collected') echo 'background: #dbeafe; color: #1e40af;';
                                                elseif ($order['status_name'] === 'Results Ready') echo 'background: #dcfce7; color: #166534;';
                                                else echo 'background: #f1f5f9; color: #64748b;';
                                                ?>">
                                                <?php echo htmlspecialchars($order['status_name']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pharmacy Tab Content -->
                <div class="tab-content <?php echo $activeTab === 'pharmacy' ? 'active' : ''; ?>" id="content-pharmacy">
                    <div class="table-card">
                        <h2 style="margin-bottom: 24px; font-size: 24px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">Create Prescription</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_prescription">
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patient_id'] ?? 0; ?>">

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px;">Prescribing Doctor</label>
                                <input type="text" value="<?php echo htmlspecialchars($selectedPatient['attending_doctor'] ?? 'N/A'); ?>" readonly style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; background: #f8fafc; color: #64748b; font-size: 14px;">
                                <input type="hidden" name="doctor_id" value="<?php echo $selectedPatient['attending_doctor_id'] ?? 0; ?>">
                            </div>

                            <div class="form-group" style="margin-bottom: 24px;">
                                <label style="display: block; font-weight: 600; color: #334155; margin-bottom: 12px;">Medications *</label>
                                <div id="medication_container">
                                    <div class="form-row" style="margin-bottom: 16px; display: flex; gap: 12px;">
                                        <div class="form-group" style="flex: 2;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Medication</label>
                                            <select name="medications[0][medication_id]" required style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; background: white;">
                                                <option value="">Select medication...</option>
                                                <?php foreach ($medications as $med): ?>
                                                    <option value="<?php echo $med['medication_id']; ?>">
                                                        <?php echo htmlspecialchars($med['name'] . ' ' . $med['strength'] . ' ' . $med['unit']); ?> (Stock: <?php echo $med['stock_quantity']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Dosage</label>
                                            <input type="text" name="medications[0][dosage]" placeholder="e.g., 500mg" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Duration (days)</label>
                                            <input type="number" name="medications[0][duration_days]" min="1" value="1" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Quantity</label>
                                            <input type="number" name="medications[0][quantity]" min="1" value="1" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                        </div>
                                        <div class="form-group" style="flex: 2;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Notes</label>
                                            <input type="text" name="medications[0][note]" placeholder="Optional notes" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                        </div>
                                    </div>
                                </div>
                                <button type="button" onclick="addMedicationRow()" style="margin-top: 12px; width: 100%; padding: 12px; background: #f8fafc; color: #64748b; border: 2px dashed #cbd5e1; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                    <i class="fas fa-plus"></i> Add Another Medication
                                </button>
                            </div>

                            <div class="form-group" style="margin-bottom: 24px;">
                                <label for="notes" style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px;">Prescription Notes</label>
                                <textarea id="notes" name="notes" rows="4" placeholder="Additional instructions..." style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical; transition: border-color 0.2s;"></textarea>
                            </div>

                            <div class="btn-group" style="display: flex; gap: 12px; margin-top: 28px;">
                                <button type="submit" class="btn-submit" style="flex: 1; padding: 14px 24px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                    <i class="fas fa-prescription"></i> Create Prescription
                                </button>
                                <button type="button" class="btn-cancel" onclick="window.location.href='ipd_patients.php?visit_id=<?php echo $selectedVisitId; ?>&tab=checkups';" style="flex: 1; padding: 14px 24px; background: #f1f5f9; color: #64748b; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s;">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <?php if (!empty($prescriptions)): ?>
                    <div class="table-card" style="margin-top: 24px;">
                        <h3 style="margin-bottom: 20px; font-size: 20px; color: #1e293b;">Prescriptions History</h3>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Prescription ID</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Doctor</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Status</th>
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Created Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prescriptions as $prescription): ?>
                                    <tr style="border-bottom: 1px solid #e2e8f0;">
                                        <td style="padding: 12px 16px; color: #334155; font-weight: 600;">#<?php echo $prescription['prescription_id']; ?></td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($prescription['doctor_name'] ?? 'N/A'); ?></td>
                                        <td style="padding: 12px 16px;">
                                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                                                <?php 
                                                if ($prescription['status'] === 'Pending') echo 'background: #fef3c7; color: #92400e;';
                                                elseif ($prescription['status'] === 'Dispensed') echo 'background: #dcfce7; color: #166534;';
                                                else echo 'background: #f1f5f9; color: #64748b;';
                                                ?>">
                                                <?php echo htmlspecialchars($prescription['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 16px; color: #64748b;"><?php echo date('M d, Y H:i', strtotime($prescription['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Checkups Tab Content -->
                <div class="tab-content <?php echo $activeTab === 'checkups' ? 'active' : ''; ?>" id="content-checkups">
                    <?php if ($selectedPatient): ?>
                    <div class="table-card" style="margin-bottom: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h2 style="margin: 0; color: white;"><?php echo htmlspecialchars($selectedPatient['patient_name']); ?></h2>
                                <p style="margin: 4px 0 0 0; opacity: 0.9;">
                                    <?php echo htmlspecialchars($selectedPatient['patient_code']); ?> ·
                                    <?php echo htmlspecialchars($selectedPatient['visit_code']); ?> ·
                                    Age: <?php echo htmlspecialchars($selectedPatient['age'] ?? 'N/A'); ?> ·
                                    <?php echo htmlspecialchars($selectedPatient['gender'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <p style="margin: 0; opacity: 0.9;">Ward/Bed</p>
                                <p style="margin: 4px 0 0 0; font-weight: 600;"><?php echo htmlspecialchars($selectedPatient['ward_name'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($selectedPatient['bed_number'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="table-card">
                        <h2 style="margin-bottom: 24px; font-size: 24px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">New Checkup</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_checkup">
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patient_id'] ?? 0; ?>">

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="checkup_time" style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px;">Checkup Time</label>
                                <input type="datetime-local" id="checkup_time" name="checkup_time" value="<?php echo date('Y-m-d\TH:i'); ?>" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="progress_notes" style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px;">Progress Notes *</label>
                                <textarea id="progress_notes" name="progress_notes" rows="5" required placeholder="Patient progress, symptoms, observations..." style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical; transition: border-color 0.2s;"></textarea>
                            </div>

                            <div class="form-group" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 2px solid #bfdbfe;">
                                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; cursor: pointer;">
                                    <input type="checkbox" name="vital_signs_check" value="1" id="vital_signs_check" onchange="toggleVitalSignsFields()" style="width: 20px; height: 20px; accent-color: #3b82f6;">
                                    <strong style="color: #1e40af; font-size: 16px;">Record Vital Signs</strong>
                                </label>
                                <div id="vital_signs_fields" style="display: none; margin-top: 16px;">
                                    <div class="form-row" style="display: flex; gap: 12px;">
                                        <div class="form-group" style="flex: 1;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">BP (mmHg)</label>
                                            <input type="text" name="vital_signs" placeholder="e.g., 120/80" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Pulse (bpm)</label>
                                            <input type="number" name="pulse" placeholder="e.g., 72" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Temp (°C)</label>
                                            <input type="number" step="0.1" name="temperature" placeholder="e.g., 37.0" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="background: linear-gradient(135deg, #fefce8 0%, #fef3c7 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 2px solid #fde68a;">
                                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; cursor: pointer;">
                                    <input type="checkbox" name="glucose_check" value="1" id="glucose_check" onchange="toggleGlucoseFields()" style="width: 20px; height: 20px; accent-color: #f59e0b;">
                                    <strong style="color: #92400e; font-size: 16px;">Glucose Monitoring</strong>
                                </label>
                                <div id="glucose_fields" style="display: none; margin-top: 16px;">
                                    <div class="form-row" style="display: flex; gap: 12px;">
                                        <div class="form-group" style="flex: 1;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Glucose Level</label>
                                            <input type="number" step="0.1" name="glucose_level" placeholder="e.g., 120" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
 </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Unit</label>
                                            <select name="glucose_unit" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; background: white;">
                                                <option value="mg/dL">mg/dL</option>
                                                <option value="mmol/L">mmol/L</option>
                                            </select>
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Type</label>
                                            <select name="glucose_type" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; background: white;">
                                                <option value="Random">Random</option>
                                                <option value="Fasting">Fasting</option>
                                                <option value="Postprandial">Postprandial</option>
                                                <option value="HbA1c">HbA1c</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 2px solid #fca5a5;">
                                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; cursor: pointer;">
                                    <input type="checkbox" name="injection_given" value="1" id="injection_given" onchange="toggleInjectionFields()" style="width: 20px; height: 20px; accent-color: #ef4444;">
                                    <strong style="color: #991b1b; font-size: 16px;">Injection Given</strong>
                                </label>
                                <div id="injection_fields" style="display: none; margin-top: 16px;">
                                    <div class="form-row" style="display: flex; gap: 12px;">
                                        <div class="form-group" style="flex: 1;">
                                            <label for="injection_type" style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Injection Type</label>
                                            <input type="text" id="injection_type" name="injection_type" placeholder="e.g., Insulin, Antibiotic" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="injection_dosage" style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Dosage</label>
                                            <input type="text" id="injection_dosage" name="injection_dosage" placeholder="e.g., 10 units, 500mg" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 2px solid #86efac;">
                                <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; cursor: pointer;">
                                    <input type="checkbox" name="medicine_given" value="1" id="medicine_given" onchange="toggleMedicineFields()" style="width: 20px; height: 20px; accent-color: #22c55e;">
                                    <strong style="color: #166534; font-size: 16px;">Medicine Administered</strong>
                                </label>
                                <div id="medicine_fields" style="display: none; margin-top: 16px;">
                                    <div id="checkup_medication_container">
                                        <div class="form-row" style="margin-bottom: 16px; display: flex; gap: 12px;">
                                            <div class="form-group" style="flex: 2;">
                                                <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Medication</label>
                                                <select name="medications[0][medication_id]" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; background: white;">
                                                    <option value="">Select medication...</option>
                                                    <?php foreach ($medications as $med): ?>
                                                        <option value="<?php echo $med['medication_id']; ?>">
                                                            <?php echo htmlspecialchars($med['name'] . ' ' . $med['strength'] . ' ' . $med['unit']); ?> (Stock: <?php echo $med['stock_quantity']; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group" style="flex: 1;">
                                                <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Dosage</label>
                                                <input type="text" name="medications[0][dosage]" placeholder="e.g., 500mg" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                            </div>
                                            <div class="form-group" style="flex: 1;">
                                                <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Quantity</label>
                                                <input type="number" name="medications[0][quantity]" min="1" value="1" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                            </div>
                                            <div class="form-group" style="flex: 2;">
                                                <label style="display: block; font-weight: 500; color: #475569; margin-bottom: 6px; font-size: 13px;">Notes</label>
                                                <input type="text" name="medications[0][notes]" placeholder="Optional notes" style="width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="addCheckupMedicationRow()" style="margin-top: 12px; width: 100%; padding: 12px; background: #f0fdf4; color: #166534; border: 2px dashed #86efac; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                        <i class="fas fa-plus"></i> Add Another Medication
                                    </button>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 24px;">
                                <label for="medicine_notes" style="display: block; font-weight: 600; color: #334155; margin-bottom: 8px;">General Medicine Notes</label>
                                <textarea id="medicine_notes" name="medicine_notes" rows="3" placeholder="Additional notes about medication administration..." style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; resize: vertical; transition: border-color 0.2s;"></textarea>
                            </div>

                            <div class="btn-group" style="display: flex; gap: 12px; margin-top: 28px;">
                                <button type="submit" class="btn-submit" style="flex: 1; padding: 14px 24px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                    <i class="fas fa-save"></i> Save Checkup
                                </button>
                                <button type="button" class="btn-cancel" style="flex: 1; padding: 14px 24px; background: #f1f5f9; color: #64748b; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s;">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- History Tab Content -->
                <div class="tab-content <?php echo $activeTab === 'history' ? 'active' : ''; ?>" id="content-history">
                    <?php if ($selectedPatient): ?>
                    <div class="table-card" style="margin-bottom: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h2 style="margin: 0; color: white;"><?php echo htmlspecialchars($selectedPatient['patient_name']); ?></h2>
                                <p style="margin: 4px 0 0 0; opacity: 0.9;">
                                    <?php echo htmlspecialchars($selectedPatient['patient_code']); ?> ·
                                    <?php echo htmlspecialchars($selectedPatient['visit_code']); ?> ·
                                    Age: <?php echo htmlspecialchars($selectedPatient['age'] ?? 'N/A'); ?> ·
                                    <?php echo htmlspecialchars($selectedPatient['gender'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <p style="margin: 0; opacity: 0.9;">Ward/Bed</p>
                                <p style="margin: 4px 0 0 0; font-weight: 600;"><?php echo htmlspecialchars($selectedPatient['ward_name'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($selectedPatient['bed_number'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Medical Records History -->
                    <div class="table-card" style="margin-bottom: 24px;">
                        <h3 style="margin-bottom: 20px; font-size: 20px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">
                            <i class="fas fa-notes-medical" style="color: #3b82f6; margin-right: 8px;"></i> Medical Records History
                        </h3>
                        <?php if (empty($medicalRecords)): ?>
                            <p style="text-align: center; color: #94a3b8; padding: 20px;">No medical records found</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8fafc;">
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Date</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Doctor</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Diagnosis</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Clinical Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($medicalRecords as $record): ?>
                                        <tr style="border-bottom: 1px solid #e2e8f0;">
                                            <td style="padding: 12px 16px; color: #64748b;"><?php echo date('M d, Y H:i', strtotime($record['created_at'])); ?></td>
                                            <td style="padding: 12px 16px; color: #334155;"><?php echo htmlspecialchars($record['doctor_name'] ?? 'N/A'); ?></td>
                                            <td style="padding: 12px 16px; color: #334155;"><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 100)) . (strlen($record['diagnosis']) > 100 ? '...' : ''); ?></td>
                                            <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars(substr($record['clinical_notes'], 0, 100)) . (strlen($record['clinical_notes']) > 100 ? '...' : ''); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Lab Orders History -->
                    <div class="table-card" style="margin-bottom: 24px;">
                        <h3 style="margin-bottom: 20px; font-size: 20px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">
                            <i class="fas fa-flask" style="color: #10b981; margin-right: 8px;"></i> Lab Orders History
                        </h3>
                        <?php if (empty($labOrders)): ?>
                            <p style="text-align: center; color: #94a3b8; padding: 20px;">No lab orders found</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8fafc;">
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Test Name</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Category</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Status</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Ordered Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($labOrders as $order): ?>
                                        <tr style="border-bottom: 1px solid #e2e8f0;">
                                            <td style="padding: 12px 16px; color: #334155;"><?php echo htmlspecialchars($order['test_name']); ?></td>
                                            <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($order['test_category'] ?? 'N/A'); ?></td>
                                            <td style="padding: 12px 16px;">
                                                <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                                                    <?php 
                                                    if ($order['status_name'] === 'Ordered') echo 'background: #fef3c7; color: #92400e;';
                                                    elseif ($order['status_name'] === 'Sample Collected') echo 'background: #dbeafe; color: #1e40af;';
                                                    elseif ($order['status_name'] === 'Results Ready') echo 'background: #dcfce7; color: #166534;';
                                                    else echo 'background: #f1f5f9; color: #64748b;';
                                                    ?>">
                                                    <?php echo htmlspecialchars($order['status_name']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px 16px; color: #64748b;"><?php echo date('M d, Y H:i', strtotime($order['ordered_at'] ?? $order['created_at'] ?? 'now')); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Prescriptions History -->
                    <div class="table-card" style="margin-bottom: 24px;">
                        <h3 style="margin-bottom: 20px; font-size: 20px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">
                            <i class="fas fa-pills" style="color: #f59e0b; margin-right: 8px;"></i> Prescriptions History
                        </h3>
                        <?php if (empty($prescriptions)): ?>
                            <p style="text-align: center; color: #94a3b8; padding: 20px;">No prescriptions found</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8fafc;">
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Prescription ID</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Doctor</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Status</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Created Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($prescriptions as $prescription): ?>
                                        <tr style="border-bottom: 1px solid #e2e8f0;">
                                            <td style="padding: 12px 16px; color: #334155; font-weight: 600;">#<?php echo $prescription['prescription_id']; ?></td>
                                            <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars($prescription['doctor_name'] ?? 'N/A'); ?></td>
                                            <td style="padding: 12px 16px;">
                                                <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                                                    <?php 
                                                    if ($prescription['status'] === 'Pending') echo 'background: #fef3c7; color: #92400e;';
                                                    elseif ($prescription['status'] === 'Dispensed') echo 'background: #dcfce7; color: #166534;';
                                                    else echo 'background: #f1f5f9; color: #64748b;';
                                                    ?>">
                                                    <?php echo htmlspecialchars($prescription['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px 16px; color: #64748b;"><?php echo date('M d, Y H:i', strtotime($prescription['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Checkups History -->
                    <div class="table-card" style="margin-bottom: 24px;">
                        <h3 style="margin-bottom: 20px; font-size: 20px; color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">
                            <i class="fas fa-clipboard-user" style="color: #8b5cf6; margin-right: 8px;"></i> Checkups History
                        </h3>
                        <?php if (empty($checkups)): ?>
                            <p style="text-align: center; color: #94a3b8; padding: 20px;">No checkups found</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f8fafc;">
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Checkup Time</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Recorded By</th>
                                            <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">Progress Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($checkups as $checkup): ?>
                                        <tr style="border-bottom: 1px solid #e2e8f0;">
                                            <td style="padding: 12px 16px; color: #64748b;"><?php echo date('M d, Y H:i', strtotime($checkup['checkup_time'])); ?></td>
                                            <td style="padding: 12px 16px; color: #334155;"><?php echo htmlspecialchars($checkup['recorded_by_name'] ?? 'N/A'); ?></td>
                                            <td style="padding: 12px 16px; color: #64748b;"><?php echo htmlspecialchars(substr($checkup['progress_notes'], 0, 100)) . (strlen($checkup['progress_notes']) > 100 ? '...' : ''); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        let medicationCount = 0;
        let checkupMedicationCount = 0;

        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById('content-' + tabName).classList.add('active');
            
            // Add active class to selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
        }

        function toggleVitalSignsFields() {
            const checkbox = document.getElementById('vital_signs_check');
            const fields = document.getElementById('vital_signs_fields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
        }

        function toggleGlucoseFields() {
            const checkbox = document.getElementById('glucose_check');
            const fields = document.getElementById('glucose_fields');
            fields.style.display = checkbox.checked ? 'block' : 'none';
        }

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
