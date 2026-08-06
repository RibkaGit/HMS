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

        // Handle services provided
        if (isset($_POST['services']) && is_array($_POST['services'])) {
            $serviceNames = [
                'anesthesia' => 'Anesthesia (Pain Killer)',
                'injection' => 'Injection Administration',
                'iv_fluids' => 'IV Fluids',
                'blood_transfusion' => 'Blood Transfusion',
                'oxygen_therapy' => 'Oxygen Therapy',
                'monitoring' => 'Vital Monitoring',
                'dressing' => 'Wound Dressing',
                'catheter' => 'Catheter Insertion'
            ];
            $serviceList = [];
            foreach ($_POST['services'] as $service) {
                $serviceList[] = $serviceNames[$service] ?? $service;
            }
            if (!empty($serviceList)) {
                $servicesString = implode(', ', $serviceList);
                $progressNotes .= "\n\nServices Provided: " . $servicesString;
                // Update the checkup record with services
                $updateQuery = "UPDATE ipd_checkups SET progress_notes = ? WHERE checkup_id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param('si', $progressNotes, $checkupId);
                $updateStmt->execute();
            }
        }

        logUserActivity($conn, $_SESSION['user_id'], 'Created IPD Checkup', "Created checkup ID: {$checkupId} for visit ID: {$visitId}");

        // Auto-complete all pending doctor orders for this visit
        $completeOrdersQuery = "UPDATE doctor_orders SET order_status = 'Completed', updated_at = CURRENT_TIMESTAMP
                                WHERE visit_id = ? AND order_status = 'Pending'";
        $completeOrdersStmt = $conn->prepare($completeOrdersQuery);
        if ($completeOrdersStmt) {
            $completeOrdersStmt->bind_param('i', $visitId);
            $completeOrdersStmt->execute();
        }

        $message = 'Checkup recorded successfully! Doctor orders auto-completed.';
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
// COMPLETE DOCTOR ORDERS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_doctor_orders') {
    $visitId = intval($_POST['visit_id']);
    $completedOrders = isset($_POST['completed_orders']) ? $_POST['completed_orders'] : [];

    if (!empty($completedOrders)) {
        foreach ($completedOrders as $orderId) {
            $orderId = intval($orderId);
            $updateQuery = "UPDATE doctor_orders SET order_status = 'Completed', updated_at = CURRENT_TIMESTAMP WHERE order_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param('i', $orderId);
            $updateStmt->execute();
        }
        logUserActivity($conn, $_SESSION['user_id'], 'Completed Doctor Orders', "Completed " . count($completedOrders) . " orders for visit ID: {$visitId}");
        $message = 'Doctor orders marked as completed successfully!';
    } else {
        $error = 'No orders selected.';
    }
}

// ============================================================================
// CREATE DOCTOR ORDER
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_doctor_order') {
    $visitId = intval($_POST['visit_id']);
    $patientId = intval($_POST['patient_id']);
    $doctorId = intval($_SESSION['user_id']);
    $orderType = sanitizeInput($_POST['order_type']);
    $orderDescription = sanitizeInput($_POST['order_description']);
    $priority = sanitizeInput($_POST['priority'] ?? 'Normal');
    $isOrRequired = isset($_POST['is_or_required']) ? 1 : 0;
    $assignedNurseId = !empty($_POST['assigned_nurse_id']) ? intval($_POST['assigned_nurse_id']) : null;

    // Ensure doctor_orders table exists
    $conn->query("CREATE TABLE IF NOT EXISTS doctor_orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        order_type VARCHAR(50) NOT NULL,
        order_description TEXT,
        priority VARCHAR(20) DEFAULT 'Normal',
        is_or_required TINYINT(1) DEFAULT 0,
        assigned_nurse_id INT DEFAULT NULL,
        order_status VARCHAR(30) DEFAULT 'Pending',
        medication_id INT DEFAULT NULL,
        dosage VARCHAR(100),
        scheduled_time DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Ensure nurse_assignments table exists
    $conn->query("CREATE TABLE IF NOT EXISTS nurse_assignments (
        assignment_id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        patient_id INT NOT NULL,
        nurse_id INT NOT NULL,
        assigned_by INT NOT NULL,
        assignment_type VARCHAR(50) DEFAULT 'Checkup',
        notes TEXT,
        status VARCHAR(30) DEFAULT 'Assigned',
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP DEFAULT NULL
    )");

    $medicationId = !empty($_POST['medication_id']) ? intval($_POST['medication_id']) : null;
    $dosage = sanitizeInput($_POST['dosage'] ?? '');
    $scheduledTime = !empty($_POST['scheduled_time']) ? sanitizeInput($_POST['scheduled_time']) : null;

    $query = "INSERT INTO doctor_orders (visit_id, patient_id, doctor_id, order_type, order_description, priority, is_or_required, assigned_nurse_id, order_status, medication_id, dosage, scheduled_time)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiisssiiss', $visitId, $patientId, $doctorId, $orderType, $orderDescription, $priority, $isOrRequired, $assignedNurseId, $medicationId, $dosage, $scheduledTime);

    if ($stmt->execute()) {
        $orderId = $conn->insert_id;

        // If nurse is assigned, create nurse assignment
        if ($assignedNurseId) {
            $assignQuery = "INSERT INTO nurse_assignments (visit_id, patient_id, nurse_id, assigned_by, assignment_type, notes, status)
                          VALUES (?, ?, ?, ?, 'Doctor Order', ?, 'Assigned')";
            $assignStmt = $conn->prepare($assignQuery);
            $assignStmt->bind_param('iiiis', $visitId, $patientId, $assignedNurseId, $doctorId, $orderDescription);
            $assignStmt->execute();
        }

        // If OR is required, update visit to OR department
        if ($isOrRequired) {
            $orDeptId = 5; // Operation Theater department ID
            $updateVisit = "UPDATE visits SET department_id = ? WHERE visit_id = ?";
            $updateStmt = $conn->prepare($updateVisit);
            $updateStmt->bind_param('ii', $orDeptId, $visitId);
            $updateStmt->execute();
        }

        logUserActivity($conn, $_SESSION['user_id'], 'Created Doctor Order', "Order for patient ID: {$patientId}");
        $message = 'Doctor order created successfully!';
        header('Location: ipd_checkups.php?visit_id=' . $visitId . '&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create doctor order.';
    }
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

// Get nurses for assignment
$nurses = $conn->query("SELECT staff_id, CONCAT(first_name, ' ', last_name) as name FROM staff WHERE role_id = 2 AND is_active = 1")->fetch_all(MYSQLI_ASSOC);

// Get pending doctor orders for selected visit
$doctorOrders = [];
if ($selectedVisitId > 0) {
    $ordersQuery = "SELECT do.*, CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                   CONCAT(n.first_name, ' ', n.last_name) as nurse_name,
                   m.name as medication_name, m.strength, m.unit
                   FROM doctor_orders do
                   LEFT JOIN staff d ON do.doctor_id = d.staff_id
                   LEFT JOIN staff n ON do.assigned_nurse_id = n.staff_id
                   LEFT JOIN medications m ON do.medication_id = m.medication_id
                   WHERE do.visit_id = ? AND do.order_status = 'Pending'
                   ORDER BY do.scheduled_time ASC, do.created_at DESC";
    $ordersStmt = $conn->prepare($ordersQuery);
    if ($ordersStmt) {
        $ordersStmt->bind_param('i', $selectedVisitId);
        $ordersStmt->execute();
        $doctorOrders = $ordersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Get glucose orders specifically
$glucoseOrders = [];
if ($selectedVisitId > 0) {
    $glucoseQuery = "SELECT do.*, CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                     FROM doctor_orders do
                     LEFT JOIN staff d ON do.doctor_id = d.staff_id
                     WHERE do.visit_id = ? AND do.order_type = 'Vital Signs' AND do.order_status = 'Pending'
                     AND (do.order_description LIKE '%glucose%' OR do.order_description LIKE '%sugar%')
                     ORDER BY do.scheduled_time ASC, do.created_at DESC";
    $glucoseStmt = $conn->prepare($glucoseQuery);
    if ($glucoseStmt) {
        $glucoseStmt->bind_param('i', $selectedVisitId);
        $glucoseStmt->execute();
        $glucoseOrders = $glucoseStmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        /* Force two-column layout for checkup page */
        @media (min-width: 768px) {
            .checkup-two-column {
                grid-template-columns: 1fr 1fr !important;
            }
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

                <div style="display: flex; flex-direction: row; gap: 20px; width: 100%; align-items: stretch;">
                    <!-- Left Column: Nurse Checkup Form -->
                    <div class="table-card" style="flex: 1; min-width: 0; box-sizing: border-box;">
                        <h2 style="margin-bottom: 20px;"><i class="fas fa-user-nurse"></i> Nurse Checkup</h2>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create_checkup">
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patient_id'] ?? 0; ?>">

                            <div class="form-row">
                                <div class="form-group" style="flex: 1;">
                                    <label for="checkup_time">Checkup Time</label>
                                    <input type="datetime-local" id="checkup_time" name="checkup_time" value="<?php echo date('Y-m-d\TH:i'); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="progress_notes">Progress Notes</label>
                                <textarea id="progress_notes" name="progress_notes" rows="4" placeholder="Detailed progress notes, observations, and updates..." style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="vital_signs">Vital Signs</label>
                                <textarea id="vital_signs" name="vital_signs" rows="2" placeholder="e.g., BP: 120/80, Pulse: 72, Temp: 98.6°F, SpO2: 98%" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;"></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group" style="flex: 1;">
                                    <label for="glucose_level">Glucose Level</label>
                                    <input type="number" id="glucose_level" name="glucose_level" step="0.1" placeholder="e.g., 120" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="glucose_unit">Unit</label>
                                    <select id="glucose_unit" name="glucose_unit" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;">
                                        <option value="mg/dL">mg/dL</option>
                                        <option value="mmol/L">mmol/L</option>
                                    </select>
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label for="glucose_type">Type</label>
                                    <select id="glucose_type" name="glucose_type" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; box-sizing: border-box;">
                                        <option value="Random">Random</option>
                                        <option value="Fasting">Fasting</option>
                                        <option value="Postprandial">Postprandial</option>
                                        <option value="HbA1c">HbA1c</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group" style="background: #dbeafe; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                <label style="display: block; font-weight: 600; color: #1e40af; margin-bottom: 12px;">
                                    <i class="fas fa-concierge-bell"></i> Services Provided
                                </label>
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px; background: white; border-radius: 4px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="services[]" value="anesthesia">
                                        <span style="font-size: 12px;">
                                            <i class="fas fa-syringe"></i> Anesthesia
                                        </span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px; background: white; border-radius: 4px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="services[]" value="injection">
                                        <span style="font-size: 12px;">
                                            <i class="fas fa-syringe"></i> Injection
                                        </span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px; background: white; border-radius: 4px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="services[]" value="iv_fluids">
                                        <span style="font-size: 12px;">
                                            <i class="fas fa-tint"></i> IV Fluids
                                        </span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px; background: white; border-radius: 4px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="services[]" value="blood_transfusion">
                                        <span style="font-size: 12px;">
                                            <i class="fas fa-heart"></i> Blood Transfusion
                                        </span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px; background: white; border-radius: 4px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="services[]" value="oxygen_therapy">
                                        <span style="font-size: 12px;">
                                            <i class="fas fa-lungs"></i> Oxygen Therapy
                                        </span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px; background: white; border-radius: 4px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="services[]" value="monitoring">
                                        <span style="font-size: 12px;">
                                            <i class="fas fa-heartbeat"></i> Vital Monitoring
                                        </span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px; background: white; border-radius: 4px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="services[]" value="dressing">
                                        <span style="font-size: 12px;">
                                            <i class="fas fa-band-aid"></i> Wound Dressing
                                        </span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px; background: white; border-radius: 4px; border: 1px solid #e2e8f0;">
                                        <input type="checkbox" name="services[]" value="catheter">
                                        <span style="font-size: 12px;">
                                            <i class="fas fa-plug"></i> Catheter
                                        </span>
                                    </label>
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
                                                <input type="number" name="medications[0][quantity]" placeholder="1" min="1" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit" style="width: 100%; padding: 12px 24px;">
                                <i class="fas fa-save"></i> Save Checkup (Auto-completes Orders)
                            </button>
                        </form>
                    </div>

                    <!-- Right Column: Doctor Orders -->
                    <div class="table-card" style="flex: 1; min-width: 0; box-sizing: border-box; background: #fef3c7; border: 2px solid #fbbf24;">
                        <h2 style="margin-bottom: 16px; color: #92400e;"><i class="fas fa-clipboard-list"></i> Doctor Orders (<?php echo count($doctorOrders); ?>)</h2>
                        <?php if (!empty($doctorOrders)): ?>
                        <p style="margin-bottom: 16px; color: #92400e; font-size: 14px;">Orders will be auto-completed when you save checkup:</p>
                        <div style="display: flex; flex-direction: column; gap: 12px; max-height: 600px; overflow-y: auto;">
                            <?php foreach ($doctorOrders as $order): ?>
                            <div style="background: white; padding: 16px; border-radius: 8px; border: 1px solid #fcd34d;">
                                <div style="display: flex; align-items: flex-start; gap: 12px;">
                                    <div style="width: 24px; height: 24px; border: 2px solid #16a34a; border-radius: 4px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="fas fa-check" style="color: #16a34a; font-size: 14px;"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <strong style="color: #1e293b; font-size: 15px;"><?php echo htmlspecialchars($order['order_type']); ?></strong>
                                            <span style="padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;
                                                <?php
                                                if ($order['priority'] === 'High') echo 'background: #fee2e2; color: #dc2626;';
                                                elseif ($order['priority'] === 'Low') echo 'background: #f1f5f9; color: #64748b;';
                                                else echo 'background: #dcfce7; color: #166534;';
                                                ?>">
                                                <?php echo htmlspecialchars($order['priority']); ?>
                                            </span>
                                        </div>
                                        <p style="margin: 0 0 8px 0; color: #475569; font-size: 14px;"><?php echo htmlspecialchars($order['order_description']); ?></p>
                                        <?php if ($order['medication_name']): ?>
                                        <div style="background: #dbeafe; padding: 8px 12px; border-radius: 6px; margin-bottom: 8px;">
                                            <strong style="color: #1e40af; font-size: 13px;">Medication:</strong>
                                            <span style="color: #1e40af; font-size: 13px;">
                                                <?php echo htmlspecialchars($order['medication_name'] . ' ' . $order['strength'] . ' ' . $order['unit']); ?>
                                                <?php if ($order['dosage']): ?>
                                                - <?php echo htmlspecialchars($order['dosage']); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($order['scheduled_time']): ?>
                                        <div style="color: #64748b; font-size: 12px;">
                                            <i class="fas fa-clock"></i> Scheduled: <?php echo date('M d, Y H:i', strtotime($order['scheduled_time'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div style="color: #94a3b8; font-size: 12px; margin-top: 4px;">
                                            <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($order['doctor_name']); ?>
                                            <?php if ($order['nurse_name']): ?>
                                            | <i class="fas fa-user-nurse"></i> Assigned: <?php echo htmlspecialchars($order['nurse_name']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p style="color: #92400e; font-size: 14px;">No pending doctor orders for this patient.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Checkup History Section -->
                <div class="table-card" style="margin-top: 20px;">
                    <h2 style="margin-bottom: 20px;">Checkup History</h2>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Progress Notes</th>
                                    <th>Vital Signs</th>
                                    <th>Glucose</th>
                                    <th>Recorded By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($checkupHistory)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="fas fa-clipboard-list" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No checkup history found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($checkupHistory as $checkup): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($checkup['checkup_time'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($checkup['progress_notes'] ?? '', 0, 100)) . (strlen($checkup['progress_notes'] ?? '') > 100 ? '...' : ''); ?></td>
                                        <td><?php echo htmlspecialchars($checkup['vital_signs'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($checkup['glucose_level']): ?>
                                                <?php echo htmlspecialchars($checkup['glucose_level']); ?> <?php echo htmlspecialchars($checkup['glucose_unit']); ?> (<?php echo htmlspecialchars($checkup['glucose_type']); ?>)
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($checkup['recorded_by_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="ipd_checkups.php?action=delete_checkup&id=<?php echo $checkup['checkup_id']; ?>&visit_id=<?php echo $selectedVisitId; ?>" onclick="return confirm('Delete this checkup?')" style="padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block;">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>
<?php
$conn->close();
?>
