<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - OPERATION ROOM
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

// ============================================================================
// ENSURE OPERATION ROOMS TABLE EXISTS
// ============================================================================
$conn->query("CREATE TABLE IF NOT EXISTS operation_rooms (
    or_room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(50) NOT NULL UNIQUE,
    room_name VARCHAR(100),
    room_type VARCHAR(50) DEFAULT 'General',
    floor INT,
    status VARCHAR(30) DEFAULT 'Available',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ============================================================================
// INSERT SAMPLE OR ROOMS IF TABLE IS EMPTY
// ============================================================================
$orRoomsCount = $conn->query("SELECT COUNT(*) as count FROM operation_rooms")->fetch_assoc()['count'];
if ($orRoomsCount == 0) {
    $sampleRooms = [
        ['OR-01', 'Main Operating Theater', 'General', 1],
        ['OR-02', 'Cardiac Surgery Room', 'Cardiac', 1],
        ['OR-03', 'Orthopedic Surgery Room', 'Orthopedic', 1],
        ['OR-04', 'Neurosurgery Room', 'Neurology', 2],
        ['OR-05', 'Pediatric Surgery Room', 'Pediatric', 2],
        ['OR-06', 'Emergency Surgery Room', 'Emergency', 1]
    ];
    foreach ($sampleRooms as $room) {
        $conn->query("INSERT INTO operation_rooms (room_number, room_name, room_type, floor, status, is_active) VALUES ('{$room[0]}', '{$room[1]}', '{$room[2]}', {$room[3]}, 'Available', 1)");
    }
}

// ============================================================================
// ENSURE OR ASSIGNMENTS TABLE EXISTS
// ============================================================================
$conn->query("CREATE TABLE IF NOT EXISTS or_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    or_room_id INT NOT NULL,
    visit_id INT NOT NULL,
    patient_id INT NOT NULL,
    assigned_by INT NOT NULL,
    notes TEXT,
    status VARCHAR(30) DEFAULT 'In Progress',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP DEFAULT NULL
)");

// ============================================================================
// ENSURE DOCTOR ORDERS TABLE EXISTS
// ============================================================================
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// ============================================================================
// ENSURE NURSE ASSIGNMENTS TABLE EXISTS
// ============================================================================
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

// ============================================================================
// CREATE OPERATION ROOM
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_or_room') {
    $roomNumber = sanitizeInput($_POST['room_number']);
    $roomName = sanitizeInput($_POST['room_name'] ?? '');
    $roomType = sanitizeInput($_POST['room_type'] ?? 'General');
    $floor = intval($_POST['floor'] ?? 1);

    $query = "INSERT INTO operation_rooms (room_number, room_name, room_type, floor, status, is_active)
              VALUES (?, ?, ?, ?, 'Available', 1)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssi', $roomNumber, $roomName, $roomType, $floor);

    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Created OR Room', "Created OR room: {$roomNumber}");
        $message = 'Operation room created successfully!';
    } else {
        $error = 'Failed to create operation room.';
    }
}

// ============================================================================
// GET MATERIAL PLAN ITEMS (AJAX)
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_material_plan_items' && isset($_GET['plan_id'])) {
    $planId = intval($_GET['plan_id']);

    $itemsQuery = "SELECT mpi.*, m.name as material_name, med.name as medication_name
                   FROM material_plan_items mpi
                   LEFT JOIN materials m ON mpi.material_id = m.material_id
                   LEFT JOIN medications med ON mpi.medication_id = med.medication_id
                   WHERE mpi.plan_id = ?";
    $itemsStmt = $conn->prepare($itemsQuery);
    if ($itemsStmt) {
        $itemsStmt->bind_param('i', $planId);
        $itemsStmt->execute();
        $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'items' => $items]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch items']);
    }
    exit();
}

// ============================================================================
// UPDATE MATERIAL ITEM STATUS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_material_status') {
    $planItemId = intval($_POST['plan_item_id']);
    $status = sanitizeInput($_POST['status']);

    $query = "UPDATE material_plan_items SET item_status = ?, updated_at = CURRENT_TIMESTAMP WHERE plan_item_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $status, $planItemId);

    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Material Status', "Material plan item ID: {$planItemId} to {$status}");
        $message = 'Material status updated successfully!';
    } else {
        $error = 'Failed to update material status.';
    }
}

// ============================================================================
// CREATE MATERIAL PLAN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_material_plan') {
    $visitId = intval($_POST['visit_id']);
    $patientId = intval($_POST['patient_id']);
    $selectedMaterials = isset($_POST['materials']) ? $_POST['materials'] : [];
    $medications = isset($_POST['medications']) ? $_POST['medications'] : [];
    $notes = sanitizeInput($_POST['notes'] ?? '');

    // Ensure material_plans table exists
    $conn->query("CREATE TABLE IF NOT EXISTS material_plans (
        plan_id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        patient_id INT NOT NULL,
        plan_type VARCHAR(50) DEFAULT 'OR Operation',
        notes TEXT,
        status VARCHAR(30) DEFAULT 'Pending',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS material_plan_items (
        plan_item_id INT AUTO_INCREMENT PRIMARY KEY,
        plan_id INT NOT NULL,
        material_id INT DEFAULT NULL,
        medication_id INT DEFAULT NULL,
        quantity INT DEFAULT 1,
        unit_cost DECIMAL(10,2) DEFAULT 0,
        item_status VARCHAR(30) DEFAULT 'Pending',
        FOREIGN KEY (plan_id) REFERENCES material_plans(plan_id) ON DELETE CASCADE
    )");

    $conn->begin_transaction();
    try {
        // Create material plan
        $planQuery = "INSERT INTO material_plans (visit_id, patient_id, plan_type, notes, status, created_by)
                      VALUES (?, ?, 'OR Operation', ?, 'Pending', ?)";
        $planStmt = $conn->prepare($planQuery);
        $planStmt->bind_param('iisi', $visitId, $patientId, $notes, $_SESSION['user_id']);
        $planStmt->execute();
        $planId = $conn->insert_id;

        // Add materials to plan
        foreach ($selectedMaterials as $matId) {
            $matId = intval($matId);
            // Get material details
            $matQuery = "SELECT unit_cost FROM materials WHERE material_id = ?";
            $matStmt = $conn->prepare($matQuery);
            $matStmt->bind_param('i', $matId);
            $matStmt->execute();
            $matResult = $matStmt->get_result()->fetch_assoc();
            $unitCost = $matResult['unit_cost'] ?? 0;

            $itemQuery = "INSERT INTO material_plan_items (plan_id, material_id, quantity, unit_cost, item_status)
                         VALUES (?, ?, 1, ?, 'Pending')";
            $itemStmt = $conn->prepare($itemQuery);
            $itemStmt->bind_param('iid', $planId, $matId, $unitCost);
            $itemStmt->execute();
        }

        // Add medications to plan
        foreach ($medications as $medId => $medData) {
            $medId = intval($medId);
            $quantity = intval($medData['quantity'] ?? 1);
            // Get medication details
            $medQuery = "SELECT unit_price FROM medications WHERE medication_id = ?";
            $medStmt = $conn->prepare($medQuery);
            $medStmt->bind_param('i', $medId);
            $medStmt->execute();
            $medResult = $medStmt->get_result()->fetch_assoc();
            $unitCost = $medResult['unit_price'] ?? 0;

            $itemQuery = "INSERT INTO material_plan_items (plan_id, medication_id, quantity, unit_cost, item_status)
                         VALUES (?, ?, ?, ?, 'Pending')";
            $itemStmt = $conn->prepare($itemQuery);
            $itemStmt->bind_param('iiid', $planId, $medId, $quantity, $unitCost);
            $itemStmt->execute();
        }

        // Create nurse assignment for material plan
        $assignQuery = "INSERT INTO nurse_assignments (visit_id, patient_id, nurse_id, assigned_by, assignment_type, notes, status)
                      SELECT ?, ?, assigned_nurse_id, ?, 'Material Plan', ?, 'Assigned'
                      FROM doctor_orders WHERE visit_id = ? AND assigned_nurse_id IS NOT NULL LIMIT 1";
        $assignStmt = $conn->prepare($assignQuery);
        $assignStmt->bind_param('iiisi', $visitId, $patientId, $_SESSION['user_id'], $notes, $visitId);
        $assignStmt->execute();

        $conn->commit();
        logUserActivity($conn, $_SESSION['user_id'], 'Created Material Plan', "Created material plan for visit ID: {$visitId}");
        $message = 'Material plan created successfully and assigned to nurse!';
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Failed to create material plan: ' . $e->getMessage();
    }
}

// ============================================================================
// ASSIGN PATIENT TO OR ROOM
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_or_room') {
    $orRoomId = intval($_POST['or_room_id']);
    $visitId = intval($_POST['visit_id']);
    $patientId = intval($_POST['patient_id']);
    $notes = sanitizeInput($_POST['notes'] ?? '');

    // Check if OR room is available
    $roomCheck = $conn->query("SELECT status FROM operation_rooms WHERE or_room_id = $orRoomId");
    $roomStatus = $roomCheck->fetch_assoc();

    if ($roomStatus['status'] !== 'Available') {
        $error = 'Operation room is not available.';
    } else {
        $conn->begin_transaction();
        try {
            // Assign OR room
            $query = "INSERT INTO or_assignments (or_room_id, visit_id, patient_id, assigned_by, notes, status)
                      VALUES (?, ?, ?, ?, ?, 'In Progress')";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('iiisi', $orRoomId, $visitId, $patientId, $_SESSION['user_id'], $notes);
            $stmt->execute();

            // Update OR room status
            $updateQuery = "UPDATE operation_rooms SET status = 'Occupied' WHERE or_room_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param('i', $orRoomId);
            $updateStmt->execute();

            $conn->commit();
            logUserActivity($conn, $_SESSION['user_id'], 'Assigned OR Room', "Assigned OR room ID: {$orRoomId} to patient ID: {$patientId}");
            $message = 'Patient assigned to operation room successfully!';
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to assign operation room.';
        }
    }
}

// ============================================================================
// RELEASE OR ROOM
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'release_or_room') {
    $assignmentId = intval($_POST['assignment_id']);
    $orRoomId = intval($_POST['or_room_id']);

    $conn->begin_transaction();
    try {
        // Update assignment status
        $query = "UPDATE or_assignments SET status = 'Completed', released_at = CURRENT_TIMESTAMP WHERE assignment_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $assignmentId);
        $stmt->execute();

        // Update OR room status
        $updateQuery = "UPDATE operation_rooms SET status = 'Available' WHERE or_room_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('i', $orRoomId);
        $updateStmt->execute();

        $conn->commit();
        logUserActivity($conn, $_SESSION['user_id'], 'Released OR Room', "Released OR room ID: {$orRoomId}");
        $message = 'Operation room released successfully!';
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Failed to release operation room.';
    }
}

// ============================================================================
// CREATE DOCTOR ORDER
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    $visitId = intval($_POST['visit_id']);
    $patientId = intval($_POST['patient_id']);
    $doctorId = intval($_SESSION['user_id']);
    $orderType = sanitizeInput($_POST['order_type']);
    $orderDescription = sanitizeInput($_POST['order_description']);
    $priority = sanitizeInput($_POST['priority'] ?? 'Normal');
    $isOrRequired = isset($_POST['is_or_required']) ? 1 : 0;
    $assignedNurseId = !empty($_POST['assigned_nurse_id']) ? intval($_POST['assigned_nurse_id']) : null;

    $query = "INSERT INTO doctor_orders (visit_id, patient_id, doctor_id, order_type, order_description, priority, is_or_required, assigned_nurse_id, order_status)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiisssii', $visitId, patientId, $doctorId, $orderType, $orderDescription, $priority, $isOrRequired, $assignedNurseId);

    if ($stmt->execute()) {
        $orderId = $conn->insert_id;

        // If nurse is assigned, create nurse assignment
        if ($assignedNurseId) {
            $assignQuery = "INSERT INTO nurse_assignments (visit_id, patient_id, nurse_id, assigned_by, assignment_type, notes, status)
                          VALUES (?, ?, ?, ?, 'Doctor Order', ?, 'Assigned')";
            $assignStmt = $conn->prepare($assignQuery);
            $assignStmt->bind_param('iiiis', $visitId, patientId, $assignedNurseId, $doctorId, $orderDescription);
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
    } else {
        $error = 'Failed to create doctor order.';
    }
}

// ============================================================================
// UPDATE ORDER STATUS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    $orderId = intval($_POST['order_id']);
    $status = sanitizeInput($_POST['status']);

    $query = "UPDATE doctor_orders SET order_status = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $status, $orderId);

    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Order Status', "Order ID: {$orderId} to {$status}");
        $message = 'Order status updated successfully!';
    } else {
        $error = 'Failed to update order status.';
    }
}

// ============================================================================
// COMPLETE NURSE ASSIGNMENT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_assignment') {
    $assignmentId = intval($_POST['assignment_id']);

    $query = "UPDATE nurse_assignments SET status = 'Completed', completed_at = CURRENT_TIMESTAMP WHERE assignment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $assignmentId);

    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Completed Nurse Assignment', "Assignment ID: {$assignmentId}");
        $message = 'Assignment completed successfully!';
    } else {
        $error = 'Failed to complete assignment.';
    }
}

// ============================================================================
// SCHEDULE PROCEDURE
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'schedule_procedure') {
    $visitId = intval($_POST['visit_id']);
    $procedureName = sanitizeInput($_POST['procedure_name']);
    $scheduledDate = sanitizeInput($_POST['scheduled_date']);
    $orRoomId = intval($_POST['or_room_id']);
    $surgeonId = intval($_POST['surgeon_id']);
    $assistantId = !empty($_POST['assistant_id']) ? intval($_POST['assistant_id']) : null;
    $anesthesiologistId = !empty($_POST['anesthesiologist_id']) ? intval($_POST['anesthesiologist_id']) : null;
    $notes = sanitizeInput($_POST['notes'] ?? '');

    // Auto-generate procedure code: PROC-YYYYMMDD-XXXX
    $procedureCode = 'PROC-' . date('Ymd') . '-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

    $query = "INSERT INTO procedures (visit_id, procedure_code, procedure_name, scheduled_date, or_room_id, surgeon_id, assistant_id, anesthesiologist_id, notes, status)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isssiiiss', $visitId, $procedureCode, $procedureName, $scheduledDate, $orRoomId, $surgeonId, $assistantId, $anesthesiologistId, $notes);

    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Scheduled Procedure', "Procedure: {$procedureName}");
        $message = 'Procedure scheduled successfully!';
    } else {
        $error = 'Failed to schedule procedure.';
    }
}

// ============================================================================
// FETCH DATA
// ============================================================================

// Get patients in OR department
$orPatientsQuery = "SELECT v.*, p.first_name, p.last_name, p.patient_code,
                   CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                   vs.name as visit_status
                   FROM visits v
                   JOIN patients p ON v.patient_id = p.patient_id
                   LEFT JOIN staff d ON v.attending_doctor_id = d.staff_id
                   JOIN lookup_visit_statuses vs ON v.visit_status_id = vs.visit_status_id
                   WHERE v.department_id = 5
                   ORDER BY v.admitted_at DESC";
$orPatientsResult = $conn->query($orPatientsQuery);
$orPatients = $orPatientsResult ? $orPatientsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get nurse assignments
$assignmentsQuery = "SELECT na.*, v.visit_code, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    CONCAT(n.first_name, ' ', n.last_name) as nurse_name,
                    CONCAT(a.first_name, ' ', a.last_name) as assigned_by_name
                    FROM nurse_assignments na
                    JOIN visits v ON na.visit_id = v.visit_id
                    JOIN patients p ON na.patient_id = p.patient_id
                    JOIN staff n ON na.nurse_id = n.staff_id
                    JOIN staff a ON na.assigned_by = a.staff_id
                    WHERE na.status = 'Assigned'
                    ORDER BY na.assigned_at DESC";
$assignmentsResult = $conn->query($assignmentsQuery);
$assignments = $assignmentsResult ? $assignmentsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get scheduled procedures
$proceduresQuery = "SELECT pr.*, v.visit_code, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                   CONCAT(s.first_name, ' ', s.last_name) as surgeon_name
                   FROM procedures pr
                   JOIN visits v ON pr.visit_id = v.visit_id
                   JOIN patients p ON v.patient_id = p.patient_id
                   LEFT JOIN staff s ON pr.surgeon_id = s.staff_id
                   ORDER BY pr.scheduled_date ASC";
$proceduresResult = $conn->query($proceduresQuery);
$procedures = $proceduresResult ? $proceduresResult->fetch_all(MYSQLI_ASSOC) : [];

// Get materials for material plan
$materialsQuery = "SELECT * FROM materials WHERE is_active = 1 ORDER BY name ASC";
$materialsResult = $conn->query($materialsQuery);
$materials = $materialsResult ? $materialsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get medications for material plan
$medicationsQuery = "SELECT * FROM medications WHERE is_active = 1 ORDER BY name ASC";
$medicationsResult = $conn->query($medicationsQuery);
$medications = $medicationsResult ? $medicationsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get material plans
$materialPlansQuery = "SELECT mp.*, v.visit_code, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                      CONCAT(c.first_name, ' ', c.last_name) as created_by_name
                      FROM material_plans mp
                      JOIN visits v ON mp.visit_id = v.visit_id
                      JOIN patients p ON mp.patient_id = p.patient_id
                      LEFT JOIN staff c ON mp.created_by = c.staff_id
                      ORDER BY mp.created_at DESC";
$materialPlansResult = $conn->query($materialPlansQuery);
$materialPlans = $materialPlansResult ? $materialPlansResult->fetch_all(MYSQLI_ASSOC) : [];

// Get doctors for assignment
$doctorsQuery = "SELECT staff_id, CONCAT(first_name, ' ', last_name) as name FROM staff WHERE role_id = 3 AND is_active = 1";
$doctorsResult = $conn->query($doctorsQuery);
$doctors = $doctorsResult ? $doctorsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get nurses for assignment
$nursesQuery = "SELECT staff_id, CONCAT(first_name, ' ', last_name) as name FROM staff WHERE role_id = 2 AND is_active = 1";
$nursesResult = $conn->query($nursesQuery);
$nurses = $nursesResult ? $nursesResult->fetch_all(MYSQLI_ASSOC) : [];

// Get active visits for order creation
$activeVisitsQuery = "SELECT v.visit_id, v.visit_code, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_code
                     FROM visits v
                     JOIN patients p ON v.patient_id = p.patient_id
                     WHERE v.visit_status_id != (SELECT visit_status_id FROM lookup_visit_statuses WHERE name = 'Cancelled')
                     AND v.visit_status_id != (SELECT visit_status_id FROM lookup_visit_statuses WHERE name = 'Discharged')
                     ORDER BY v.admitted_at DESC LIMIT 50";
$activeVisitsResult = $conn->query($activeVisitsQuery);
$activeVisits = $activeVisitsResult ? $activeVisitsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get operation rooms
$orRoomsQuery = "SELECT * FROM operation_rooms WHERE is_active = 1 ORDER BY room_number ASC";
$orRoomsResult = $conn->query($orRoomsQuery);
$orRooms = $orRoomsResult ? $orRoomsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get OR assignments
$orAssignmentsQuery = "SELECT oa.*, orr.room_number, orr.room_name, v.visit_code,
                      CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                      CONCAT(s.first_name, ' ', s.last_name) as assigned_by_name
                      FROM or_assignments oa
                      JOIN operation_rooms orr ON oa.or_room_id = orr.or_room_id
                      JOIN visits v ON oa.visit_id = v.visit_id
                      JOIN patients p ON oa.patient_id = p.patient_id
                      LEFT JOIN staff s ON oa.assigned_by = s.staff_id
                      WHERE oa.status = 'In Progress'
                      ORDER BY oa.assigned_at DESC";
$orAssignmentsResult = $conn->query($orAssignmentsQuery);
$orAssignments = $orAssignmentsResult ? $orAssignmentsResult->fetch_all(MYSQLI_ASSOC) : [];

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operation Room - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .or-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .or-tab {
            padding: 10px 20px;
            background: #f1f5f9;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        .or-tab:hover {
            background: #e2e8f0;
        }
        .or-tab.active {
            background: #2563eb;
            color: white;
        }
        .or-section {
            display: none;
        }
        .or-section.active {
            display: block;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-in-progress { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        .status-scheduled { background: #e0e7ff; color: #3730a3; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-normal { background: #dcfce7; color: #166534; }
        .priority-low { background: #f1f5f9; color: #64748b; }
        .btn-create {
            padding: 10px 20px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-create:hover {
            background: #1d4ed8;
        }
        .form-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
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
        .close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 24px;
            color: #94a3b8;
            cursor: pointer;
        }
        .close-btn:hover {
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
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
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
        }
        .btn-submit:hover {
            background: #1d4ed8;
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
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <h1>Operation Room Management</h1>
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

            <div class="or-tabs">
                <button class="or-tab active" onclick="showSection('or-patients')">OR Patients</button>
                <button class="or-tab" onclick="showSection('or-rooms')">OR Rooms</button>
                <button class="or-tab" onclick="showSection('nurse-assignments')">Nurse Assignments</button>
                <button class="or-tab" onclick="showSection('material-plans')">Material Plans</button>
                <button class="or-tab" onclick="showSection('procedures')">Procedures</button>
            </div>

            <!-- OR Patients Section -->
            <div id="or-patients" class="or-section active">
                <div class="table-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Patients in Operation Room (<?php echo count($orPatients); ?>)</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Visit Code</th>
                                    <th>Patient</th>
                                    <th>Patient Code</th>
                                    <th>Attending Doctor</th>
                                    <th>Visit Status</th>
                                    <th>Admitted At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orPatients)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="fas fa-procedures" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No patients in Operation Room
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orPatients as $patient): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($patient['visit_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($patient['patient_code']); ?></td>
                                        <td><?php echo htmlspecialchars($patient['doctor_name'] ?? 'Not Assigned'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $patient['visit_status'])); ?>">
                                                <?php echo htmlspecialchars($patient['visit_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($patient['admitted_at'])); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <button class="btn-create" onclick="openProcedureModal(<?php echo $patient['visit_id']; ?>, '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>')">
                                                    <i class="fas fa-calendar-plus"></i> Schedule Procedure
                                                </button>
                                                <button class="btn-create" onclick="openMaterialPlanModal(<?php echo $patient['visit_id']; ?>, '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>')" style="background: #8b5cf6;">
                                                    <i class="fas fa-boxes"></i> Plan Materials
                                                </button>
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

            <!-- OR Rooms Section -->
            <div id="or-rooms" class="or-section">
                <div class="table-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Operation Rooms (<?php echo count($orRooms); ?>)</h2>
                        <button class="btn-create" onclick="openCreateRoomModal()">
                            <i class="fas fa-plus"></i> Add OR Room
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Room Number</th>
                                    <th>Room Name</th>
                                    <th>Type</th>
                                    <th>Floor</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orRooms)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="fas fa-procedures" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No operation rooms found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orRooms as $room): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($room['room_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($room['room_type']); ?></td>
                                        <td><?php echo htmlspecialchars($room['floor']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($room['status']) === 'available' ? 'status-completed' : 'status-in-progress'; ?>">
                                                <?php echo htmlspecialchars($room['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($room['status'] === 'Available'): ?>
                                            <button class="btn-create" onclick="openAssignRoomModal(<?php echo $room['or_room_id']; ?>, '<?php echo htmlspecialchars($room['room_number']); ?>')">
                                                <i class="fas fa-user-plus"></i> Assign Patient
                                            </button>
                                            <?php else: ?>
                                            <span style="color: #64748b; font-size: 13px;">Occupied</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($orAssignments)): ?>
                <div class="table-card" style="margin-top: 20px;">
                    <h3 style="margin-bottom: 20px;">Current OR Assignments (<?php echo count($orAssignments); ?>)</h3>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Patient</th>
                                    <th>Visit</th>
                                    <th>Assigned By</th>
                                    <th>Assigned At</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orAssignments as $assignment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($assignment['room_number']); ?></strong>
                                        <?php if ($assignment['room_name']): ?>
                                        <br><small><?php echo htmlspecialchars($assignment['room_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($assignment['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['visit_code']); ?></td>
                                    <td><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($assignment['assigned_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-in-progress">
                                            <?php echo htmlspecialchars($assignment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="release_or_room">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
                                            <input type="hidden" name="or_room_id" value="<?php echo $assignment['or_room_id']; ?>">
                                            <button type="submit" onclick="return confirm('Release this operation room?')" class="btn-delete" style="padding: 6px 12px;">
                                                <i class="fas fa-sign-out-alt"></i> Release
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Material Plans Section -->
            <div id="material-plans" class="or-section">
                <div class="table-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Material Plans (<?php echo count($materialPlans); ?>)</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Visit</th>
                                    <th>Plan Type</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($materialPlans)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="fas fa-boxes" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No material plans found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($materialPlans as $plan): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($plan['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($plan['visit_code']); ?></td>
                                        <td><?php echo htmlspecialchars($plan['plan_type']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $plan['status'])); ?>">
                                                <?php echo htmlspecialchars($plan['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($plan['created_by_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($plan['created_at'])); ?></td>
                                        <td>
                                            <button class="btn-create" onclick="viewMaterialPlan(<?php echo $plan['plan_id']; ?>)" style="padding: 6px 12px; font-size: 12px;">
                                                <i class="fas fa-eye"></i> View Items
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Nurse Assignments Section -->
            <div id="nurse-assignments" class="or-section">
                <div class="table-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Nurse Assignments (<?php echo count($assignments); ?>)</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Visit</th>
                                    <th>Patient</th>
                                    <th>Nurse</th>
                                    <th>Assigned By</th>
                                    <th>Assignment Type</th>
                                    <th>Notes</th>
                                    <th>Status</th>
                                    <th>Assigned At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($assignments)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="fas fa-user-nurse" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No active nurse assignments
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['visit_code']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['nurse_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['assignment_type']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($assignment['notes'], 0, 30)) . (strlen($assignment['notes']) > 30 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $assignment['status'])); ?>">
                                                <?php echo htmlspecialchars($assignment['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($assignment['assigned_at'])); ?></td>
                                        <td>
                                            <button onclick="completeAssignment(<?php echo $assignment['assignment_id']; ?>)" style="padding: 4px 8px; background: #16a34a; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Procedures Section -->
            <div id="procedures" class="or-section">
                <div class="table-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Scheduled Procedures (<?php echo count($procedures); ?>)</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Visit</th>
                                    <th>Patient</th>
                                    <th>Procedure Code</th>
                                    <th>Procedure Name</th>
                                    <th>Surgeon</th>
                                    <th>Scheduled Date</th>
                                    <th>Performed Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($procedures)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="fas fa-procedures" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No procedures scheduled
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($procedures as $procedure): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($procedure['visit_code']); ?></td>
                                        <td><?php echo htmlspecialchars($procedure['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($procedure['procedure_code']); ?></td>
                                        <td><?php echo htmlspecialchars($procedure['procedure_name']); ?></td>
                                        <td><?php echo htmlspecialchars($procedure['surgeon_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo $procedure['scheduled_date'] ? date('M d, Y H:i', strtotime($procedure['scheduled_date'])) : 'Not Scheduled'; ?></td>
                                        <td><?php echo $procedure['performed_date'] ? date('M d, Y H:i', strtotime($procedure['performed_date'])) : '-'; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $procedure['status'])); ?>">
                                                <?php echo htmlspecialchars($procedure['status']); ?>
                                            </span>
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

    <!-- Create OR Room Modal -->
    <div class="form-modal" id="createRoomModal">
        <div class="form-modal-content">
            <button class="close-btn" onclick="closeCreateRoomModal()">&times;</button>
            <h2 style="margin-bottom: 24px;">Add Operation Room</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_or_room">
                
                <div class="form-group">
                    <label for="room_number">Room Number *</label>
                    <input type="text" id="room_number" name="room_number" required placeholder="e.g., OR-01">
                </div>
                
                <div class="form-group">
                    <label for="room_name">Room Name</label>
                    <input type="text" id="room_name" name="room_name" placeholder="e.g., Main Operating Theater">
                </div>
                
                <div class="form-group">
                    <label for="room_type">Room Type</label>
                    <select id="room_type" name="room_type">
                        <option value="General">General</option>
                        <option value="Cardiac">Cardiac</option>
                        <option value="Orthopedic">Orthopedic</option>
                        <option value="Neurology">Neurology</option>
                        <option value="Pediatric">Pediatric</option>
                        <option value="Emergency">Emergency</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="floor">Floor</label>
                    <input type="number" id="floor" name="floor" value="1" min="1">
                </div>
                
                <div class="btn-group" style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Create OR Room
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeCreateRoomModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign OR Room Modal -->
    <div class="form-modal" id="assignRoomModal">
        <div class="form-modal-content">
            <button class="close-btn" onclick="closeAssignRoomModal()">&times;</button>
            <h2 style="margin-bottom: 24px;">Assign Patient to OR Room</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="assign_or_room">
                <input type="hidden" id="assign_or_room_id" name="or_room_id">
                
                <div class="form-group">
                    <label>OR Room</label>
                    <input type="text" id="assign_room_display" readonly style="background: #f8fafc;">
                </div>
                
                <div class="form-group">
                    <label for="assign_visit_id">Select Visit *</label>
                    <select id="assign_visit_id" name="visit_id" required onchange="updateAssignPatientInfo()">
                        <option value="">Select Visit</option>
                        <?php foreach ($activeVisits as $visit): ?>
                            <option value="<?php echo $visit['visit_id']; ?>" data-patient-id="<?php echo $visit['patient_id']; ?>">
                                <?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['patient_name'] . ' (' . $visit['patient_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <input type="hidden" id="assign_patient_id" name="patient_id">
                
                <div class="form-group">
                    <label for="assign_notes">Notes</label>
                    <textarea id="assign_notes" name="notes" rows="3" placeholder="Additional notes about the assignment..."></textarea>
                </div>
                
                <div class="btn-group" style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-user-plus"></i> Assign Patient
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeAssignRoomModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Procedure Modal -->
    <div class="form-modal" id="procedureModal">
        <div class="form-modal-content">
            <button class="close-btn" onclick="closeProcedureModal()">&times;</button>
            <h2 style="margin-bottom: 24px;">Schedule Procedure</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="schedule_procedure">
                <input type="hidden" id="proc_visit_id" name="visit_id">
                
                <div class="form-group">
                    <label>Patient</label>
                    <input type="text" id="proc_patient_name" readonly style="background: #f1f5f9;">
                </div>

                <div class="form-group">
                    <label for="procedure_name">Procedure Name *</label>
                    <input type="text" id="procedure_name" name="procedure_name" required placeholder="e.g., Appendectomy">
                </div>
                
                <div class="form-group">
                    <label for="scheduled_date">Scheduled Date *</label>
                    <input type="datetime-local" id="scheduled_date" name="scheduled_date" required>
                </div>

                <div class="form-group">
                    <label for="or_room_id">Operation Room *</label>
                    <select id="or_room_id" name="or_room_id" required>
                        <option value="">Select OR Room</option>
                        <?php foreach ($orRooms as $room): ?>
                            <option value="<?php echo $room['or_room_id']; ?>">
                                <?php echo htmlspecialchars($room['room_number'] . ' - ' . ($room['room_name'] ?? $room['room_type'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="surgeon_id">Surgeon *</label>
                    <select id="surgeon_id" name="surgeon_id" required>
                        <option value="">Select Surgeon</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['staff_id']; ?>">
                                <?php echo htmlspecialchars($doctor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="assistant_id">Assistant Surgeon</label>
                    <select id="assistant_id" name="assistant_id">
                        <option value="">Select Assistant (Optional)</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['staff_id']; ?>">
                                <?php echo htmlspecialchars($doctor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="anesthesiologist_id">Anesthesiologist</label>
                    <select id="anesthesiologist_id" name="anesthesiologist_id">
                        <option value="">Select Anesthesiologist (Optional)</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['staff_id']; ?>">
                                <?php echo htmlspecialchars($doctor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="btn-group" style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-calendar-plus"></i> Schedule Procedure
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeProcedureModal()" style="flex: 1; padding: 12px; background: #64748b; color: white; border: none; border-radius: 8px; cursor: pointer;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Material Plan Modal -->
    <div class="form-modal" id="materialPlanModal">
        <div class="form-modal-content" style="max-width: 800px;">
            <button class="close-btn" onclick="closeMaterialPlanModal()">&times;</button>
            <h2 style="margin-bottom: 24px;">Plan Materials for OR Operation</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_material_plan">
                <input type="hidden" id="plan_visit_id" name="visit_id">
                <input type="hidden" id="plan_patient_id" name="patient_id">

                <div class="form-group">
                    <label>Patient</label>
                    <input type="text" id="plan_patient_name" readonly style="background: #f1f5f9;">
                </div>

                <div class="form-group" style="background: #f0fdf4; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #166534; margin-bottom: 12px;">
                        <i class="fas fa-boxes"></i> Select Materials
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                        <?php foreach ($materials as $mat): ?>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: white; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <input type="checkbox" name="materials[]" value="<?php echo $mat['material_id']; ?>">
                            <span style="font-size: 13px;">
                                <?php echo htmlspecialchars($mat['name']); ?>
                                <small style="color: #64748b;">(Stock: <?php echo $mat['stock_quantity']; ?>)</small>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group" style="background: #dbeafe; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; color: #1e40af; margin-bottom: 12px;">
                        <i class="fas fa-pills"></i> Select Medications
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px;">
                        <?php foreach ($medications as $med): ?>
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px; background: white; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <input type="checkbox" name="medications[<?php echo $med['medication_id']; ?>][medication_id]" value="<?php echo $med['medication_id']; ?>">
                            <span style="font-size: 13px;">
                                <?php echo htmlspecialchars($med['name'] . ' ' . $med['strength'] . ' ' . $med['unit']); ?>
                                <small style="color: #64748b;">(Stock: <?php echo $med['stock_quantity']; ?>)</small>
                            </span>
                            <input type="number" name="medications[<?php echo $med['medication_id']; ?>][quantity]" value="1" min="1" style="width: 50px; padding: 4px; border: 1px solid #e2e8f0; border-radius: 4px;">
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="plan_notes">Notes</label>
                    <textarea id="plan_notes" name="notes" rows="3" placeholder="Additional notes about the material plan..."></textarea>
                </div>

                <div class="btn-group" style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn-submit" style="background: #8b5cf6;">
                        <i class="fas fa-save"></i> Create Material Plan
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeMaterialPlanModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Material Plan Modal -->
    <div class="form-modal" id="viewMaterialPlanModal">
        <div class="form-modal-content" style="max-width: 900px;">
            <button class="close-btn" onclick="closeViewMaterialPlanModal()">&times;</button>
            <h2 style="margin-bottom: 24px;">Material Plan Items</h2>
            <div id="materialPlanItemsContent">
                <!-- Items will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
        });

        // OR Room Modal Functions
        function openCreateRoomModal() {
            document.getElementById('createRoomModal').style.display = 'block';
        }

        function closeCreateRoomModal() {
            document.getElementById('createRoomModal').style.display = 'none';
        }

        function openAssignRoomModal(orRoomId, roomNumber) {
            document.getElementById('assign_or_room_id').value = orRoomId;
            document.getElementById('assign_room_display').value = roomNumber;
            document.getElementById('assignRoomModal').style.display = 'block';
        }

        function closeAssignRoomModal() {
            document.getElementById('assignRoomModal').style.display = 'none';
            document.getElementById('assign_or_room_id').value = '';
            document.getElementById('assign_room_display').value = '';
            document.getElementById('assign_visit_id').value = '';
            document.getElementById('assign_patient_id').value = '';
            document.getElementById('assign_notes').value = '';
        }

        function updateAssignPatientInfo() {
            const select = document.getElementById('assign_visit_id');
            const selectedOption = select.options[select.selectedIndex];
            const patientId = selectedOption.getAttribute('data-patient-id');
            document.getElementById('assign_patient_id').value = patientId;
        }

        function showSection(sectionId) {
            document.querySelectorAll('.or-section').forEach(section => {
                section.classList.remove('active');
            });
            document.querySelectorAll('.or-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
            event.target.classList.add('active');
        }

        function openProcedureModal(visitId, patientName) {
            document.getElementById('proc_visit_id').value = visitId;
            document.getElementById('proc_patient_name').value = patientName;
            document.getElementById('procedureModal').style.display = 'flex';
        }

        function closeProcedureModal() {
            document.getElementById('procedureModal').style.display = 'none';
        }

        // Material Plan Modal Functions
        function openMaterialPlanModal(visitId, patientName) {
            document.getElementById('plan_visit_id').value = visitId;
            document.getElementById('plan_patient_name').value = patientName;
            document.getElementById('materialPlanModal').style.display = 'flex';
        }

        function closeMaterialPlanModal() {
            document.getElementById('materialPlanModal').style.display = 'none';
        }

        // View Material Plan Modal Functions
        function viewMaterialPlan(planId) {
            fetch('or.php?action=get_material_plan_items&plan_id=' + planId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<table class="recent-table"><thead><tr><th>Item Type</th><th>Name</th><th>Quantity</th><th>Unit Cost</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
                        data.items.forEach(item => {
                            html += '<tr>';
                            html += '<td>' + (item.material_id ? 'Material' : 'Medication') + '</td>';
                            html += '<td>' + (item.material_name || item.medication_name) + '</td>';
                            html += '<td>' + item.quantity + '</td>';
                            html += '<td>$' + item.unit_cost + '</td>';
                            html += '<td><span class="status-badge status-' + item.item_status.toLowerCase().replace(' ', '') + '">' + item.item_status + '</span></td>';
                            html += '<td>';
                            html += '<select onchange="updateMaterialItemStatus(' + item.plan_item_id + ', this.value)" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">';
                            html += '<option value="">Update Status</option>';
                            html += '<option value="Pending">Pending</option>';
                            html += '<option value="Give">Give</option>';
                            html += '<option value="Accepted">Accepted</option>';
                            html += '<option value="Return">Return</option>';
                            html += '<option value="Used">Used</option>';
                            html += '</select>';
                            html += '</td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                        document.getElementById('materialPlanItemsContent').innerHTML = html;
                        document.getElementById('viewMaterialPlanModal').style.display = 'flex';
                    } else {
                        alert('Failed to load material plan items');
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function closeViewMaterialPlanModal() {
            document.getElementById('viewMaterialPlanModal').style.display = 'none';
        }

        function updateMaterialItemStatus(planItemId, status) {
            if (status) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_material_status">
                    <input type="hidden" name="plan_item_id" value="${planItemId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function updateOrderStatus(orderId, status) {
            if (status) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_order_status">
                    <input type="hidden" name="order_id" value="${orderId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function completeAssignment(assignmentId) {
            if (confirm('Are you sure you want to mark this assignment as completed?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                form.innerHTML = `
                    <input type="hidden" name="action" value="complete_assignment">
                    <input type="hidden" name="assignment_id" value="${assignmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
