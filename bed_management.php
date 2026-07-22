<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - BED MANAGEMENT
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
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// Get all wards with department info
$wards = $conn->query("SELECT w.*, d.name as department_name 
                       FROM wards w
                       JOIN lookup_departments d ON w.department_id = d.department_id
                       ORDER BY w.name")->fetch_all(MYSQLI_ASSOC);

// Get bed types
$bedTypes = $conn->query("SELECT * FROM lookup_bed_types ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get departments for dropdown
$departments = $conn->query("SELECT * FROM lookup_departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// CREATE WARD
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ward') {
    $query = "INSERT INTO wards (name, department_id, floor, is_active) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('siss', $_POST['name'], $_POST['department_id'], $_POST['floor'], $_POST['is_active']);
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Created Ward', "Created ward: {$_POST['name']}");
        $message = 'Ward created successfully!';
        header('Location: bed_management.php?tab=wards&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create ward. Please try again.';
    }
}

// ============================================================================
// UPDATE WARD
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ward') {
    $wardId = intval($_POST['ward_id']);
    $query = "UPDATE wards SET name = ?, department_id = ?, floor = ?, is_active = ? WHERE ward_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sissi', $_POST['name'], $_POST['department_id'], $_POST['floor'], $_POST['is_active'], $wardId);
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Ward', "Updated ward ID: {$wardId}");
        $message = 'Ward updated successfully!';
        header('Location: bed_management.php?tab=wards&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update ward. Please try again.';
    }
}

// ============================================================================
// DELETE WARD
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete_ward' && isset($_GET['id'])) {
    $wardId = intval($_GET['id']);
    
    // Check if ward has beds
    $checkQuery = "SELECT COUNT(*) as count FROM beds WHERE ward_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('i', $wardId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkRow = $checkResult->fetch_assoc();
    
    if ($checkRow['count'] > 0) {
        $error = 'Cannot delete ward with existing beds. Please remove beds first.';
    } else {
        $query = "DELETE FROM wards WHERE ward_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $wardId);
        if ($stmt->execute()) {
            logUserActivity($conn, $_SESSION['user_id'], 'Deleted Ward', "Deleted ward ID: {$wardId}");
            $message = 'Ward deleted successfully!';
            header('Location: bed_management.php?tab=wards&message=' . urlencode($message));
            exit();
        } else {
            $error = 'Failed to delete ward. Please try again.';
        }
    }
}

// ============================================================================
// CREATE BED
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_bed') {
    $bedTypeId = !empty($_POST['bed_type_id']) ? intval($_POST['bed_type_id']) : null;
    $pricePerDay = !empty($_POST['price_per_day']) ? floatval($_POST['price_per_day']) : null;
    
    $query = "INSERT INTO beds (ward_id, bed_number, bed_type_id, price_per_day, status, is_active) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isidsi', 
        $_POST['ward_id'], 
        $_POST['bed_number'], 
        $bedTypeId,
        $pricePerDay,
        $_POST['status'],
        $_POST['is_active']
    );
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Created Bed', "Created bed: {$_POST['bed_number']}");
        $message = 'Bed created successfully!';
        header('Location: bed_management.php?tab=beds&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create bed. Please try again.';
    }
}

// ============================================================================
// UPDATE BED
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_bed') {
    $bedId = intval($_POST['bed_id']);
    $bedTypeId = !empty($_POST['bed_type_id']) ? intval($_POST['bed_type_id']) : null;
    $pricePerDay = !empty($_POST['price_per_day']) ? floatval($_POST['price_per_day']) : null;
    
    $query = "UPDATE beds SET 
              ward_id = ?, bed_number = ?, bed_type_id = ?, 
              price_per_day = ?, status = ?, is_active = ?
              WHERE bed_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isdsdii', 
        $_POST['ward_id'], 
        $_POST['bed_number'], 
        $bedTypeId,
        $pricePerDay,
        $_POST['status'],
        $_POST['is_active'],
        $bedId
    );
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Bed', "Updated bed ID: {$bedId}");
        $message = 'Bed updated successfully!';
        header('Location: bed_management.php?tab=beds&message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update bed. Please try again.';
    }
}

// ============================================================================
// DELETE BED
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete_bed' && isset($_GET['id'])) {
    $bedId = intval($_GET['id']);
    
    // Check if bed is occupied
    $checkQuery = "SELECT status FROM beds WHERE bed_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('i', $bedId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $bedStatus = $checkResult->fetch_assoc();
    
    if ($bedStatus['status'] === 'Occupied') {
        $error = 'Cannot delete occupied bed. Please discharge patient first.';
    } else {
        $query = "DELETE FROM beds WHERE bed_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $bedId);
        if ($stmt->execute()) {
            logUserActivity($conn, $_SESSION['user_id'], 'Deleted Bed', "Deleted bed ID: {$bedId}");
            $message = 'Bed deleted successfully!';
            header('Location: bed_management.php?tab=beds&message=' . urlencode($message));
            exit();
        } else {
            $error = 'Failed to delete bed. Please try again.';
        }
    }
}

// ============================================================================
// ASSIGN PATIENT TO BED
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_bed') {
    $bedId = intval($_POST['bed_id']);
    $visitId = intval($_POST['visit_id']);
    $patientId = intval($_POST['patient_id']);
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    // Check if bed is available
    $bedCheck = $conn->query("SELECT status FROM beds WHERE bed_id = $bedId");
    $bedStatus = $bedCheck->fetch_assoc();
    
    if ($bedStatus['status'] !== 'Available') {
        $error = 'Bed is not available.';
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Assign bed
            $query = "INSERT INTO bed_assignments (bed_id, visit_id, patient_id, notes) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('iiis', $bedId, $visitId, $patientId, $notes);
            $stmt->execute();
            
            // Update bed status
            $updateQuery = "UPDATE beds SET status = 'Occupied' WHERE bed_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param('i', $bedId);
            $updateStmt->execute();
            
            // Update visit with bed info
            $visitQuery = "UPDATE visits SET ward_bed = (SELECT CONCAT(w.name, ' / Bed ', b.bed_number) 
                           FROM beds b JOIN wards w ON b.ward_id = w.ward_id WHERE b.bed_id = ?) 
                           WHERE visit_id = ?";
            $visitStmt = $conn->prepare($visitQuery);
            $visitStmt->bind_param('ii', $bedId, $visitId);
            $visitStmt->execute();

            $bedCharge = $conn->prepare("SELECT b.price_per_day, bt.base_price, w.name, b.bed_number
                                         FROM beds b
                                         JOIN wards w ON b.ward_id = w.ward_id
                                         LEFT JOIN lookup_bed_types bt ON b.bed_type_id = bt.bed_type_id
                                         WHERE b.bed_id = ?");
            $bedCharge->bind_param('i', $bedId);
            $bedCharge->execute();
            $bedDetails = $bedCharge->get_result()->fetch_assoc();
            $bedPrice = (float) ($bedDetails['price_per_day'] ?: $bedDetails['base_price']);
            addInvoiceCharge($conn, $visitId, 'IPD bed: ' . $bedDetails['name'] . ' / Bed ' . $bedDetails['bed_number'], 'Bed', 1, $bedPrice);

            $ipdTypeId = getLookupId($conn, 'lookup_visit_types', 'name', 'IPD');
            $ipdDepartmentId = getLookupId($conn, 'lookup_departments', 'code', 'IPD');
            $typeStmt = $conn->prepare("UPDATE visits SET visit_type_id = ?, department_id = ? WHERE visit_id = ?");
            $typeStmt->bind_param('iii', $ipdTypeId, $ipdDepartmentId, $visitId);
            $typeStmt->execute();
            
            $conn->commit();
            
            $clearBedFlag = $conn->prepare("UPDATE medical_records SET needs_bed = 0 WHERE visit_id = ?");
            $clearBedFlag->bind_param('i', $visitId);
            $clearBedFlag->execute();

            logUserActivity($conn, $_SESSION['user_id'], 'Assigned Bed', "Assigned bed ID: {$bedId} to patient ID: {$patientId}");
            $message = 'Patient assigned to bed successfully!';
            header('Location: bed_management.php?tab=assignments&message=' . urlencode($message));
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to assign bed. Please try again.';
        }
    }
}

// ============================================================================
// DISCHARGE PATIENT FROM BED
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'discharge' && isset($_GET['id'])) {
    $assignmentId = intval($_GET['id']);
    
    $conn->begin_transaction();
    
    try {
        // Get assignment details
        $assignmentQuery = "SELECT bed_id, visit_id FROM bed_assignments WHERE assignment_id = ?";
        $assignmentStmt = $conn->prepare($assignmentQuery);
        $assignmentStmt->bind_param('i', $assignmentId);
        $assignmentStmt->execute();
        $assignment = $assignmentStmt->get_result()->fetch_assoc();
        
        if ($assignment) {
            // Update bed status
            $updateQuery = "UPDATE beds SET status = 'Available' WHERE bed_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param('i', $assignment['bed_id']);
            $updateStmt->execute();
            
            // Update assignment
            $dischargeQuery = "UPDATE bed_assignments SET discharged_at = NOW() WHERE assignment_id = ?";
            $dischargeStmt = $conn->prepare($dischargeQuery);
            $dischargeStmt->bind_param('i', $assignmentId);
            $dischargeStmt->execute();
            
            // Update visit
            $visitQuery = "UPDATE visits SET ward_bed = NULL, discharged_at = NOW() WHERE visit_id = ?";
            $visitStmt = $conn->prepare($visitQuery);
            $visitStmt->bind_param('i', $assignment['visit_id']);
            $visitStmt->execute();
        }
        
        $conn->commit();
        
        logUserActivity($conn, $_SESSION['user_id'], 'Discharged Patient', "Discharged assignment ID: {$assignmentId}");
        $message = 'Patient discharged from bed successfully!';
        header('Location: bed_management.php?tab=assignments&message=' . urlencode($message));
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Failed to discharge patient. Please try again.';
    }
}

// ============================================================================
// GET BED OCCUPANCY DATA (FIXED - added is_active and department_name)
// ============================================================================
$bedOccupancy = [];
$occupancyQuery = "SELECT 
                     w.ward_id,
                     w.name as ward_name,
                     w.floor,
                     w.is_active,
                     d.name as department_name,
                     COUNT(b.bed_id) as total_beds,
                     SUM(CASE WHEN b.status = 'Occupied' THEN 1 ELSE 0 END) as occupied_beds,
                     SUM(CASE WHEN b.status = 'Available' THEN 1 ELSE 0 END) as available_beds,
                     SUM(CASE WHEN b.status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance_beds,
                     SUM(CASE WHEN b.status = 'Reserved' THEN 1 ELSE 0 END) as reserved_beds
                   FROM wards w
                   LEFT JOIN beds b ON w.ward_id = b.ward_id AND b.is_active = 1
                   JOIN lookup_departments d ON w.department_id = d.department_id
                   WHERE w.is_active = 1
                   GROUP BY w.ward_id
                   ORDER BY w.name";
$occupancyResult = $conn->query($occupancyQuery);
$bedOccupancy = $occupancyResult->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GET ALL BEDS WITH DETAILS
// ============================================================================
$beds = [];
$bedsQuery = "SELECT b.*, 
                     w.name as ward_name,
                     w.floor,
                     bt.name as bed_type_name,
                     bt.base_price,
                     (SELECT CONCAT(p.first_name, ' ', p.last_name) 
                      FROM bed_assignments ba 
                      JOIN patients p ON ba.patient_id = p.patient_id 
                      WHERE ba.bed_id = b.bed_id AND ba.discharged_at IS NULL 
                      ORDER BY ba.assigned_at DESC LIMIT 1) as current_patient
              FROM beds b
              JOIN wards w ON b.ward_id = w.ward_id
              LEFT JOIN lookup_bed_types bt ON b.bed_type_id = bt.bed_type_id
              ORDER BY w.name, b.bed_number";
$bedsResult = $conn->query($bedsQuery);
$beds = $bedsResult->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GET CURRENT ASSIGNMENTS
// ============================================================================
$assignments = [];
$assignQuery = "SELECT ba.*,
                       b.bed_number,
                       w.name as ward_name,
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       p.patient_code,
                       v.visit_code,
                       v.admitted_at as visit_date
                FROM bed_assignments ba
                JOIN beds b ON ba.bed_id = b.bed_id
                JOIN wards w ON b.ward_id = w.ward_id
                JOIN patients p ON ba.patient_id = p.patient_id
                JOIN visits v ON ba.visit_id = v.visit_id
                WHERE ba.discharged_at IS NULL
                ORDER BY ba.assigned_at DESC";
$assignResult = $conn->query($assignQuery);
$assignments = $assignResult->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// GET UNASSIGNED VISITS (IPD patients without bed)
// ============================================================================
$unassignedVisits = [];
$unassignedQuery = "SELECT v.visit_id, v.visit_code, 
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           p.patient_id, p.patient_code,
                           mr.diagnosis,
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                    FROM medical_records mr
                    JOIN visits v ON mr.visit_id = v.visit_id
                    JOIN patients p ON v.patient_id = p.patient_id
                    JOIN staff d ON mr.doctor_id = d.staff_id
                    WHERE mr.needs_bed = 1
                    AND v.visit_status_id NOT IN (SELECT visit_status_id FROM lookup_visit_statuses WHERE name IN ('Discharged', 'Cancelled'))
                    AND v.ward_bed IS NULL
                    AND NOT EXISTS (
                        SELECT 1 FROM bed_assignments ba
                        WHERE ba.visit_id = v.visit_id AND ba.discharged_at IS NULL
                    )
                    ORDER BY mr.created_at DESC";
$unassignedResult = $conn->query($unassignedQuery);
$unassignedVisits = $unassignedResult->fetch_all(MYSQLI_ASSOC);

// Get edit data
$editWard = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_ward' && isset($_GET['id'])) {
    $wardId = intval($_GET['id']);
    $query = "SELECT * FROM wards WHERE ward_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $wardId);
    $stmt->execute();
    $editWard = $stmt->get_result()->fetch_assoc();
}

$editBed = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_bed' && isset($_GET['id'])) {
    $bedId = intval($_GET['id']);
    $query = "SELECT * FROM beds WHERE bed_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $bedId);
    $stmt->execute();
    $editBed = $stmt->get_result()->fetch_assoc();
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
    <title>Bed Management - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* All your styles here - keep the same */
        .form-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: <?php echo ($editWard || $editBed || isset($_GET['action']) && ($_GET['action'] === 'create_ward' || $_GET['action'] === 'create_bed' || $_GET['action'] === 'assign_bed')) ? 'flex' : 'none'; ?>;
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
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }
        .btn-delete:hover {
            background: #fecaca;
        }
        .btn-assign {
            background: #d1fae5;
            color: #059669;
        }
        .btn-assign:hover {
            background: #a7f3d0;
        }
        .btn-discharge {
            background: #fef3c7;
            color: #d97706;
        }
        .btn-discharge:hover {
            background: #fde68a;
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
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            border-bottom: 2px solid #e2e8f0;
            flex-wrap: wrap;
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
        .bed-status {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-available { background: #d1fae5; color: #059669; }
        .status-occupied { background: #fee2e2; color: #dc2626; }
        .status-maintenance { background: #fef3c7; color: #d97706; }
        .status-reserved { background: #dbeafe; color: #2563eb; }
        
        .occupancy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .occupancy-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        .occupancy-card .ward-name {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .occupancy-card .stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }
        .occupancy-card .stat-item {
            text-align: center;
            padding: 8px;
            border-radius: 8px;
            background: #f8fafc;
        }
        .occupancy-card .stat-item .number {
            font-size: 24px;
            font-weight: 700;
            display: block;
        }
        .occupancy-card .stat-item .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        .stat-available .number { color: #059669; }
        .stat-occupied .number { color: #dc2626; }
        .stat-maintenance .number { color: #d97706; }
        
        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 9999px;
            margin-top: 12px;
            overflow: hidden;
        }
        .progress-bar .fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.3s;
        }
        .bed-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .bed-item {
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            transition: all 0.3s;
        }
        .bed-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .bed-item .bed-number {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .bed-item .bed-status {
            margin-top: 8px;
        }
        .bed-item .bed-details {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .bed-item .bed-actions {
            margin-top: 8px;
        }
        .bed-item.available { border-color: #22c55e; }
        .bed-item.occupied { border-color: #ef4444; background: #fef2f2; }
        .bed-item.maintenance { border-color: #f59e0b; }
        .bed-item.reserved { border-color: #3b82f6; }
        
        .search-bar {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .search-bar input, .search-bar select {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .search-bar input:focus, .search-bar select:focus {
            outline: none;
            border-color: #2563eb;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active {
            background: #d1fae5;
            color: #059669;
        }
        .status-inactive {
            background: #fee2e2;
            color: #dc2626;
        }
        .report-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .report-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        .report-card .report-title {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-card .report-value {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }
        .table-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .recent-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .recent-table th {
            background: #f8fafc;
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        .recent-table td {
            padding: 10px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .recent-table tr:last-child td {
            border-bottom: none;
        }
        .recent-table tr:hover td {
            background: #f8fafc;
        }
        .patient-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .patient-info .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: #fff;
        }
        .patient-name {
            font-weight: 500;
            color: #0f172a;
        }
        @media (max-width: 768px) {
            .report-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 480px) {
            .report-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- SIDEBAR - DO NOT DUPLICATE, USE THE ONE FROM index.php -->
        <!-- Just include the sidebar once from your main template -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <h1>Bed Management</h1>
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

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab <?php echo $activeTab === 'overview' ? 'active' : ''; ?>" data-tab="overview">
                    <i class="fas fa-chart-pie"></i> Overview
                </button>
                <button class="tab <?php echo $activeTab === 'beds' ? 'active' : ''; ?>" data-tab="beds">
                    <i class="fas fa-bed"></i> Beds
                </button>
                <button class="tab <?php echo $activeTab === 'wards' ? 'active' : ''; ?>" data-tab="wards">
                    <i class="fas fa-building"></i> Wards
                </button>
                <button class="tab <?php echo $activeTab === 'assignments' ? 'active' : ''; ?>" data-tab="assignments">
                    <i class="fas fa-user-check"></i> Assignments
                </button>
            </div>

            <!-- Overview Tab -->
            <div class="tab-content <?php echo $activeTab === 'overview' ? 'active' : ''; ?>" id="tab-overview">
                <!-- Occupancy Cards -->
                <div class="occupancy-grid">
                    <?php foreach ($bedOccupancy as $ward): ?>
                    <div class="occupancy-card">
                        <div class="ward-name">
                            <?php echo htmlspecialchars($ward['ward_name']); ?>
                            <span style="font-size: 12px; font-weight: 400; color: #64748b;">
                                (<?php echo htmlspecialchars($ward['department_name'] ?? 'N/A'); ?>)
                            </span>
                        </div>
                        <div class="stats">
                            <div class="stat-item stat-available">
                                <span class="number"><?php echo $ward['available_beds'] ?? 0; ?></span>
                                <span class="label">Available</span>
                            </div>
                            <div class="stat-item stat-occupied">
                                <span class="number"><?php echo $ward['occupied_beds'] ?? 0; ?></span>
                                <span class="label">Occupied</span>
                            </div>
                            <div class="stat-item stat-maintenance">
                                <span class="number"><?php echo $ward['maintenance_beds'] ?? 0; ?></span>
                                <span class="label">Maintenance</span>
                            </div>
                        </div>
                        <div class="progress-bar">
                            <?php 
                            $total = ($ward['total_beds'] ?? 0);
                            $occupied = ($ward['occupied_beds'] ?? 0);
                            $occupancyRate = $total > 0 ? ($occupied / $total) * 100 : 0;
                            $color = $occupancyRate > 80 ? '#dc2626' : ($occupancyRate > 60 ? '#f59e0b' : '#22c55e');
                            ?>
                            <div class="fill" style="width: <?php echo $occupancyRate; ?>%; background: <?php echo $color; ?>;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: #64748b; margin-top: 4px;">
                            <span><?php echo $occupied; ?> / <?php echo $total; ?> occupied</span>
                            <span><?php echo round($occupancyRate); ?>% occupancy</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Quick Stats -->
                <?php 
                $totalBeds = array_sum(array_column($bedOccupancy, 'total_beds'));
                $totalOccupied = array_sum(array_column($bedOccupancy, 'occupied_beds'));
                $totalAvailable = array_sum(array_column($bedOccupancy, 'available_beds'));
                ?>
                <div class="report-grid" style="margin-top: 20px;">
                    <div class="report-card">
                        <div class="report-title">Total Beds</div>
                        <div class="report-value"><?php echo $totalBeds; ?></div>
                    </div>
                    <div class="report-card">
                        <div class="report-title">Occupied</div>
                        <div class="report-value" style="color: #dc2626;"><?php echo $totalOccupied; ?></div>
                    </div>
                    <div class="report-card">
                        <div class="report-title">Available</div>
                        <div class="report-value" style="color: #059669;"><?php echo $totalAvailable; ?></div>
                    </div>
                    <div class="report-card">
                        <div class="report-title">Occupancy Rate</div>
                        <div class="report-value"><?php echo $totalBeds > 0 ? round(($totalOccupied / $totalBeds) * 100) : 0; ?>%</div>
                    </div>
                </div>

                <!-- Unassigned IPD Patients Alert -->
                <?php if (!empty($unassignedVisits)): ?>
                <div class="alert alert-warning" style="background: #fef3c7; color: #d97706; border-color: #fde68a; margin-top: 20px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong><?php echo count($unassignedVisits); ?> patients referred for bed assignment!</strong>
                                    <a href="bed_management.php?action=assign_bed" style="color: #d97706; font-weight: 600; text-decoration: underline;">Assign beds now</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Beds Tab -->
            <div class="tab-content <?php echo $activeTab === 'beds' ? 'active' : ''; ?>" id="tab-beds">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                    <div class="search-bar">
                        <form method="GET" action="" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                            <input type="hidden" name="tab" value="beds">
                            <input type="text" name="search_bed" placeholder="Search beds..." value="<?php echo htmlspecialchars($_GET['search_bed'] ?? ''); ?>">
                            <select name="status_filter">
                                <option value="">All Status</option>
                                <option value="Available" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] === 'Available') ? 'selected' : ''; ?>>Available</option>
                                <option value="Occupied" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] === 'Occupied') ? 'selected' : ''; ?>>Occupied</option>
                                <option value="Maintenance" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] === 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="Reserved" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] === 'Reserved') ? 'selected' : ''; ?>>Reserved</option>
                            </select>
                            <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (isset($_GET['search_bed']) || isset($_GET['status_filter'])): ?>
                                <a href="bed_management.php?tab=beds" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <button class="btn-create" onclick="window.location.href='bed_management.php?action=create_bed'">
                        <i class="fas fa-plus"></i> Add Bed
                    </button>
                </div>

                <div class="bed-list">
                    <?php 
                    $filteredBeds = $beds;
                    if (isset($_GET['search_bed']) && !empty($_GET['search_bed'])) {
                        $search = strtolower($_GET['search_bed']);
                        $filteredBeds = array_filter($filteredBeds, function($bed) use ($search) {
                            return stripos($bed['bed_number'], $search) !== false || 
                                   stripos($bed['ward_name'], $search) !== false;
                        });
                    }
                    if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
                        $filteredBeds = array_filter($filteredBeds, function($bed) {
                            return $bed['status'] === $_GET['status_filter'];
                        });
                    }
                    ?>
                    <?php if (empty($filteredBeds)): ?>
                        <p style="color: #94a3b8; text-align: center; padding: 40px; grid-column: 1 / -1;">
                            <i class="fas fa-bed" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            No beds found
                        </p>
                    <?php else: ?>
                        <?php foreach ($filteredBeds as $bed): ?>
                        <div class="bed-item <?php echo strtolower($bed['status']); ?>">
                            <div class="bed-number"><?php echo htmlspecialchars($bed['bed_number']); ?></div>
                            <div style="font-size: 13px; color: #475569;"><?php echo htmlspecialchars($bed['ward_name']); ?></div>
                            <div style="font-size: 12px; color: #64748b;">
                                <?php echo htmlspecialchars($bed['bed_type_name'] ?? 'General'); ?>
                                <?php if ($bed['price_per_day']): ?>
                                    - $<?php echo number_format($bed['price_per_day'], 2); ?>/day
                                <?php endif; ?>
                            </div>
                            <div class="bed-status status-<?php echo strtolower($bed['status']); ?>">
                                <?php echo $bed['status']; ?>
                            </div>
                            <?php if ($bed['current_patient']): ?>
                                <div style="font-size: 12px; color: #475569; margin-top: 4px;">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($bed['current_patient']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="bed-actions">
                                <a href="bed_management.php?action=edit_bed&id=<?php echo $bed['bed_id']; ?>" class="btn-edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($bed['status'] !== 'Occupied'): ?>
                                    <a href="bed_management.php?action=delete_bed&id=<?php echo $bed['bed_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this bed?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Wards Tab - FIXED -->
            <div class="tab-content <?php echo $activeTab === 'wards' ? 'active' : ''; ?>" id="tab-wards">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h3 style="font-size: 16px; font-weight: 600; color: #0f172a;">Manage Wards</h3>
                    <button class="btn-create" onclick="window.location.href='bed_management.php?action=create_ward'">
                        <i class="fas fa-plus"></i> Add Ward
                    </button>
                </div>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Ward Name</th>
                                    <th>Department</th>
                                    <th>Floor</th>
                                    <th>Total Beds</th>
                                    <th>Available</th>
                                    <th>Occupied</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bedOccupancy as $ward): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ward['ward_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ward['department_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($ward['floor'] ?? 'N/A'); ?></td>
                                    <td><?php echo $ward['total_beds'] ?? 0; ?></td>
                                    <td style="color: #059669;"><?php echo $ward['available_beds'] ?? 0; ?></td>
                                    <td style="color: #dc2626;"><?php echo $ward['occupied_beds'] ?? 0; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo (isset($ward['is_active']) && $ward['is_active'] == 1) ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo (isset($ward['is_active']) && $ward['is_active'] == 1) ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="bed_management.php?action=edit_ward&id=<?php echo $ward['ward_id']; ?>" class="btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if (($ward['total_beds'] ?? 0) == 0): ?>
                                                <a href="bed_management.php?action=delete_ward&id=<?php echo $ward['ward_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this ward?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Assignments Tab -->
            <div class="tab-content <?php echo $activeTab === 'assignments' ? 'active' : ''; ?>" id="tab-assignments">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                    <h3 style="font-size: 16px; font-weight: 600; color: #0f172a;">Current Bed Assignments</h3>
                    <button class="btn-create" onclick="window.location.href='bed_management.php?action=assign_bed'">
                        <i class="fas fa-user-plus"></i> Assign Bed
                    </button>
                </div>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Visit</th>
                                    <th>Ward</th>
                                    <th>Bed</th>
                                    <th>Assigned Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($assignments)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px;">
                                            <i class="fas fa-bed" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                            No active bed assignments
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td>
                                            <div class="patient-info">
                                                <div class="avatar" style="background: <?php echo getUserColor($assignment['patient_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                                    <?php echo strtoupper(substr($assignment['patient_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <span class="patient-name"><?php echo htmlspecialchars($assignment['patient_name']); ?></span>
                                                    <small><?php echo htmlspecialchars($assignment['patient_code']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($assignment['visit_code']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['ward_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($assignment['bed_number']); ?></strong></td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($assignment['assigned_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="lab.php?action=create&visit_id=<?php echo $assignment['visit_id']; ?>" class="btn-edit">
                                                    <i class="fas fa-flask"></i> Lab
                                                </a>
                                                <a href="pharmacy.php?action=create_prescription&visit_id=<?php echo $assignment['visit_id']; ?>" class="btn-edit">
                                                    <i class="fas fa-pills"></i> Medicine
                                                </a>
                                                <a href="bed_management.php?action=discharge&id=<?php echo $assignment['assignment_id']; ?>" class="btn-discharge" onclick="return confirm('Are you sure you want to discharge this patient from the bed?');">
                                                    <i class="fas fa-sign-out-alt"></i> Discharge
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

    <!-- ============================================================================
    MODALS - Keep all your modals here
    ============================================================================ -->

    <!-- Create Ward Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'create_ward'): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='bed_management.php?tab=wards'">&times;</button>
            <h2 style="margin-bottom: 24px;">Create New Ward</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_ward">
                
                <div class="form-group">
                    <label for="name">Ward Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="department_id">Department *</label>
                    <select id="department_id" name="department_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>">
                                <?php echo $dept['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="floor">Floor</label>
                    <input type="text" id="floor" name="floor" placeholder="e.g., 2nd Floor">
                </div>
                
                <div class="form-group">
                    <label for="is_active">Status</label>
                    <select id="is_active" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Create Ward
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='bed_management.php?tab=wards'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Ward Modal -->
    <?php if ($editWard): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='bed_management.php?tab=wards'">&times;</button>
            <h2 style="margin-bottom: 24px;">Edit Ward</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_ward">
                <input type="hidden" name="ward_id" value="<?php echo $editWard['ward_id']; ?>">
                
                <div class="form-group">
                    <label for="name">Ward Name *</label>
                    <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($editWard['name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="department_id">Department *</label>
                    <select id="department_id" name="department_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" <?php echo ($editWard['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo $dept['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="floor">Floor</label>
                    <input type="text" id="floor" name="floor" value="<?php echo htmlspecialchars($editWard['floor']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="is_active">Status</label>
                    <select id="is_active" name="is_active">
                        <option value="1" <?php echo ($editWard['is_active'] == 1) ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo ($editWard['is_active'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update Ward
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='bed_management.php?tab=wards'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Create Bed Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'create_bed'): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='bed_management.php?tab=beds'">&times;</button>
            <h2 style="margin-bottom: 24px;">Add New Bed</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_bed">
                
                <div class="form-group">
                    <label for="ward_id">Ward *</label>
                    <select id="ward_id" name="ward_id" required>
                        <option value="">Select Ward</option>
                        <?php foreach ($wards as $ward): ?>
                            <option value="<?php echo $ward['ward_id']; ?>">
                                <?php echo htmlspecialchars($ward['name'] . ' (' . $ward['department_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bed_number">Bed Number *</label>
                    <input type="text" id="bed_number" name="bed_number" required placeholder="e.g., E-101">
                </div>
                
                <div class="form-group">
                    <label for="bed_type_id">Bed Type</label>
                    <select id="bed_type_id" name="bed_type_id">
                        <option value="">Select Type</option>
                        <?php foreach ($bedTypes as $type): ?>
                            <option value="<?php echo $type['bed_type_id']; ?>">
                                <?php echo htmlspecialchars($type['name'] . ' - $' . number_format($type['base_price'], 2) . '/day'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="price_per_day">Price Per Day ($)</label>
                    <input type="number" id="price_per_day" name="price_per_day" step="0.01" placeholder="Leave empty to use default">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Available">Available</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Reserved">Reserved</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="is_active">Active</label>
                    <select id="is_active" name="is_active">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Create Bed
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='bed_management.php?tab=beds'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Bed Modal -->
    <?php if ($editBed): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='bed_management.php?tab=beds'">&times;</button>
            <h2 style="margin-bottom: 24px;">Edit Bed</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_bed">
                <input type="hidden" name="bed_id" value="<?php echo $editBed['bed_id']; ?>">
                
                <div class="form-group">
                    <label for="ward_id">Ward *</label>
                    <select id="ward_id" name="ward_id" required>
                        <option value="">Select Ward</option>
                        <?php foreach ($wards as $ward): ?>
                            <option value="<?php echo $ward['ward_id']; ?>" <?php echo ($editBed['ward_id'] == $ward['ward_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ward['name'] . ' (' . $ward['department_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bed_number">Bed Number *</label>
                    <input type="text" id="bed_number" name="bed_number" required value="<?php echo htmlspecialchars($editBed['bed_number']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="bed_type_id">Bed Type</label>
                    <select id="bed_type_id" name="bed_type_id">
                        <option value="">Select Type</option>
                        <?php foreach ($bedTypes as $type): ?>
                            <option value="<?php echo $type['bed_type_id']; ?>" <?php echo ($editBed['bed_type_id'] == $type['bed_type_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name'] . ' - $' . number_format($type['base_price'], 2) . '/day'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="price_per_day">Price Per Day ($)</label>
                    <input type="number" id="price_per_day" name="price_per_day" step="0.01" value="<?php echo htmlspecialchars($editBed['price_per_day']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Available" <?php echo ($editBed['status'] === 'Available') ? 'selected' : ''; ?>>Available</option>
                        <option value="Occupied" <?php echo ($editBed['status'] === 'Occupied') ? 'selected' : ''; ?>>Occupied</option>
                        <option value="Maintenance" <?php echo ($editBed['status'] === 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="Reserved" <?php echo ($editBed['status'] === 'Reserved') ? 'selected' : ''; ?>>Reserved</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="is_active">Active</label>
                    <select id="is_active" name="is_active">
                        <option value="1" <?php echo ($editBed['is_active'] == 1) ? 'selected' : ''; ?>>Yes</option>
                        <option value="0" <?php echo ($editBed['is_active'] == 0) ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update Bed
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='bed_management.php?tab=beds'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Assign Bed Modal -->
    <?php if (isset($_GET['action']) && $_GET['action'] === 'assign_bed'): ?>
    <div class="form-modal" style="display: flex;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='bed_management.php?tab=assignments'">&times;</button>
            <h2 style="margin-bottom: 24px;">Assign Patient to Bed</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="assign_bed">
                
                <div class="form-group">
                    <label for="visit_id">Select IPD Visit *</label>
                    <?php if ($selectedVisitId > 0): ?>
                        <?php foreach ($unassignedVisits as $visit): if ((int) $visit['visit_id'] === $selectedVisitId): ?>
                            <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                            <input type="text" value="<?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['patient_name'] . ' (' . $visit['patient_code'] . ')'); ?>" readonly>
                        <?php endif; endforeach; ?>
                    <?php else: ?>
                    <select id="visit_id" name="visit_id" required>
                        <option value="">Select Visit</option>
                        <?php foreach ($unassignedVisits as $visit): ?>
                            <option value="<?php echo $visit['visit_id']; ?>" <?php echo $selectedVisitId === (int) $visit['visit_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['patient_name'] . ' (' . $visit['patient_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <?php if (empty($unassignedVisits)): ?>
                        <small style="color: #22c55e;">All IPD patients have beds assigned!</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="bed_id">Select Bed *</label>
                    <select id="bed_id" name="bed_id" required>
                        <option value="">Select Bed</option>
                        <?php 
                        $availableBeds = array_filter($beds, function($bed) {
                            return $bed['status'] === 'Available' && $bed['is_active'] == 1;
                        });
                        foreach ($availableBeds as $bed): 
                        ?>
                            <option value="<?php echo $bed['bed_id']; ?>">
                                <?php echo htmlspecialchars($bed['ward_name'] . ' - Bed ' . $bed['bed_number'] . ' (' . ($bed['bed_type_name'] ?? 'General') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="patient_id">Patient (Auto-filled)</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Select Visit first</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Any additional notes about this assignment..."></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-check"></i> Assign Bed
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='bed_management.php?tab=assignments'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-fill patient ID when visit is selected
        document.getElementById('visit_id').addEventListener('change', function() {
            const visitId = this.value;
            const patientSelect = document.getElementById('patient_id');
            
            <?php foreach ($unassignedVisits as $visit): ?>
                if (visitId == '<?php echo $visit['visit_id']; ?>') {
                    patientSelect.innerHTML = '<option value="<?php echo $visit['patient_id']; ?>"><?php echo htmlspecialchars($visit['patient_name']); ?></option>';
                }
            <?php endforeach; ?>
            
            if (visitId === '') {
                patientSelect.innerHTML = '<option value="">Select Visit first</option>';
            }
        });
    </script>
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
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>