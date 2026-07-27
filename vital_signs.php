<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - VITAL SIGNS
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

// Get visits in arrival order so the nurse can work from the oldest waiting patient.
$visits = $conn->query("SELECT v.visit_id, v.visit_code, 
                        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                        p.patient_code, v.admitted_at,
                        vs.name as visit_status
                        FROM visits v 
                        JOIN patients p ON v.patient_id = p.patient_id 
                        JOIN lookup_visit_statuses vs ON v.visit_status_id = vs.visit_status_id
                        WHERE vs.name NOT IN ('Cancelled', 'Discharged')
                        ORDER BY v.admitted_at ASC LIMIT 200")->fetch_all(MYSQLI_ASSOC);

// Get staff for dropdown (nurses and doctors)
$staff = $conn->query("SELECT staff_id, first_name, last_name, 
                       (SELECT name FROM lookup_roles WHERE role_id = s.role_id) as role_name
                       FROM staff s 
                       WHERE is_active = 1 
                       ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// Get doctors for assignment
$doctors = $conn->query("SELECT staff_id, first_name, last_name FROM staff WHERE role_id = (SELECT role_id FROM lookup_roles WHERE name = 'Doctor') AND is_active = 1")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// CREATE VITAL SIGNS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_vital') {
    // Sanitize inputs
    $temperature = !empty($_POST['temperature_c']) ? floatval($_POST['temperature_c']) : null;
    $pulse = !empty($_POST['pulse_bpm']) ? intval($_POST['pulse_bpm']) : null;
    $bloodPressure = !empty($_POST['blood_pressure']) ? sanitizeInput($_POST['blood_pressure']) : null;
    $respiration = !empty($_POST['respiration_rate']) ? intval($_POST['respiration_rate']) : null;
    $spo2 = !empty($_POST['spo2_percent']) ? intval($_POST['spo2_percent']) : null;
    $weight = !empty($_POST['weight_kg']) ? floatval($_POST['weight_kg']) : null;
    $height = !empty($_POST['height_cm']) ? floatval($_POST['height_cm']) : null;
    
    $query = "INSERT INTO vital_signs (visit_id, recorded_by, temperature_c, pulse_bpm, 
              blood_pressure, respiration_rate, spo2_percent, weight_kg, height_cm) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $visitId = intval($_POST['visit_id']);
    $recordedBy = intval($_POST['recorded_by']);
    $stmt->bind_param('iidisiidd', 
        $visitId,
        $recordedBy,
        $temperature,
        $pulse,
        $bloodPressure,
        $respiration,
        $spo2,
        $weight,
        $height
    );
    
    if ($stmt->execute()) {
        $vitalId = $conn->insert_id;
        
        // Assign doctor if selected
        if (!empty($_POST['attending_doctor_id'])) {
            $doctorId = intval($_POST['attending_doctor_id']);
            $updateVisit = $conn->prepare("UPDATE visits SET attending_doctor_id = ? WHERE visit_id = ?");
            $updateVisit->bind_param('ii', $doctorId, $visitId);
            $updateVisit->execute();
            updateVisitStatus($conn, $visitId, 'Registered');
        }
        
        logUserActivity($conn, $_SESSION['user_id'], 'Created Vital Signs', "Created vital signs ID: {$vitalId} for visit ID: {$_POST['visit_id']}");
        $message = 'Vital signs recorded successfully!';
        header('Location: vital_signs.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to record vital signs. Please try again.';
    }
}

// ============================================================================
// UPDATE VITAL SIGNS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_vital') {
    $vitalId = intval($_POST['vital_id']);
    
    $temperature = !empty($_POST['temperature_c']) ? floatval($_POST['temperature_c']) : null;
    $pulse = !empty($_POST['pulse_bpm']) ? intval($_POST['pulse_bpm']) : null;
    $bloodPressure = !empty($_POST['blood_pressure']) ? sanitizeInput($_POST['blood_pressure']) : null;
    $respiration = !empty($_POST['respiration_rate']) ? intval($_POST['respiration_rate']) : null;
    $spo2 = !empty($_POST['spo2_percent']) ? intval($_POST['spo2_percent']) : null;
    $weight = !empty($_POST['weight_kg']) ? floatval($_POST['weight_kg']) : null;
    $height = !empty($_POST['height_cm']) ? floatval($_POST['height_cm']) : null;
    
    $query = "UPDATE vital_signs SET 
              temperature_c = ?, pulse_bpm = ?, blood_pressure = ?, 
              respiration_rate = ?, spo2_percent = ?, weight_kg = ?, height_cm = ?
              WHERE vital_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('disiiddi', 
        $temperature,
        $pulse,
        $bloodPressure,
        $respiration,
        $spo2,
        $weight,
        $height,
        $vitalId
    );
    
    if ($stmt->execute()) {
        // Assign doctor if selected
        if (!empty($_POST['attending_doctor_id']) && !empty($_POST['visit_id'])) {
            $doctorId = intval($_POST['attending_doctor_id']);
            $visitIdToUpdate = intval($_POST['visit_id']);
            $updateVisit = $conn->prepare("UPDATE visits SET attending_doctor_id = ? WHERE visit_id = ?");
            $updateVisit->bind_param('ii', $doctorId, $visitIdToUpdate);
            $updateVisit->execute();
        }
        
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Vital Signs', "Updated vital signs ID: {$vitalId}");
        $message = 'Vital signs updated successfully!';
        header('Location: vital_signs.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update vital signs. Please try again.';
    }
}

// ============================================================================
// DELETE VITAL SIGNS
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete_vital' && isset($_GET['id'])) {
    $vitalId = intval($_GET['id']);
    
    $query = "DELETE FROM vital_signs WHERE vital_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $vitalId);
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Deleted Vital Signs', "Deleted vital signs ID: {$vitalId}");
        $message = 'Vital signs deleted successfully!';
        header('Location: vital_signs.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to delete vital signs. Please try again.';
    }
}

// ============================================================================
// GET VITAL SIGNS
// ============================================================================
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$visitFilter = isset($_GET['visit_id']) ? intval($_GET['visit_id']) : 0;

$query = "SELECT vs.*, 
          CONCAT(p.first_name, ' ', p.last_name) as patient_name,
          p.patient_code,
          CONCAT(s.first_name, ' ', s.last_name) as recorded_by_name,
          v.visit_code,
          v.visit_type_id,
          vt.name as visit_type
          FROM vital_signs vs
          JOIN visits v ON vs.visit_id = v.visit_id
          JOIN patients p ON v.patient_id = p.patient_id
          LEFT JOIN staff s ON vs.recorded_by = s.staff_id
          LEFT JOIN lookup_visit_types vt ON v.visit_type_id = vt.visit_type_id
          WHERE 1=1";

$params = [];
$types = "";

if ($searchTerm) {
    $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_code LIKE ? OR v.visit_code LIKE ?)";
    $searchTermLike = "%{$searchTerm}%";
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $types .= "ssss";
}

if ($visitFilter > 0) {
    $query .= " AND vs.visit_id = ?";
    $params[] = $visitFilter;
    $types .= "i";
}

$query .= " ORDER BY vs.recorded_at ASC LIMIT 100";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$vitalSigns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$queueQuery = "SELECT v.visit_id, v.visit_code, v.admitted_at,
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               p.patient_code, vs.name as visit_status
               FROM visits v
               JOIN patients p ON v.patient_id = p.patient_id
               JOIN lookup_visit_statuses vs ON v.visit_status_id = vs.visit_status_id
               LEFT JOIN vital_signs existing ON existing.visit_id = v.visit_id
               WHERE existing.vital_id IS NULL
               AND vs.name NOT IN ('Cancelled', 'Discharged')
               AND p.payment_confirmed = 1
               ORDER BY v.admitted_at ASC
               LIMIT 200";
$vitalQueue = $conn->query($queueQuery)->fetch_all(MYSQLI_ASSOC);

// Get vital sign for edit
$editVital = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_vital' && isset($_GET['id'])) {
    $vitalId = intval($_GET['id']);
    $query = "SELECT vs.*, v.attending_doctor_id, v.visit_code, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_code 
              FROM vital_signs vs
              JOIN visits v ON vs.visit_id = v.visit_id
              JOIN patients p ON v.patient_id = p.patient_id
              WHERE vs.vital_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $vitalId);
    $stmt->execute();
    $editVital = $stmt->get_result()->fetch_assoc();
}

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Get vitals statistics
$statsQuery = "SELECT 
                COUNT(*) as total,
                AVG(temperature_c) as avg_temp,
                AVG(pulse_bpm) as avg_pulse,
                AVG(spo2_percent) as avg_spo2
                FROM vital_signs";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vital Signs - HMS</title>
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
            display: <?php echo ($editVital || isset($_GET['action']) && $_GET['action'] === 'create_vital') ? 'flex' : 'none'; ?>;
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
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
        .vital-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 16px 20px;
            margin-bottom: 12px;
            transition: all 0.3s;
        }
        .vital-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .vital-card .vital-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .vital-card .vital-header .patient {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .vital-card .vital-header .patient .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 12px;
        }
        .vital-card .vital-header .patient .name {
            font-weight: 600;
            color: #0f172a;
        }
        .vital-card .vital-header .patient .code {
            font-size: 12px;
            color: #94a3b8;
        }
        .vital-card .vital-header .meta {
            font-size: 12px;
            color: #94a3b8;
        }
        .vital-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 12px;
        }
        .vital-item {
            text-align: center;
            padding: 8px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .vital-item .value {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .vital-item .label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        .vital-item .unit {
            font-size: 11px;
            color: #94a3b8;
        }
        .vital-item .normal {
            color: #22c55e;
        }
        .vital-item .abnormal {
            color: #ef4444;
        }
        .vital-item .warning {
            color: #f59e0b;
        }
        .stats-grid-vital {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card-vital {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        .stat-card-vital .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }
        .stat-card-vital .stat-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        .record-meta {
            font-size: 12px;
            color: #94a3b8;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            .stats-grid-vital {
                grid-template-columns: 1fr 1fr;
            }
            .form-row-3 {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 480px) {
            .stats-grid-vital {
                grid-template-columns: 1fr;
            }
            .form-row-3 {
                grid-template-columns: 1fr;
            }
            .vital-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
                    <h1>Vital Signs</h1>
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

            <!-- Stats -->
            <div class="stats-grid-vital">
                <div class="stat-card-vital">
                    <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Recordings</div>
                </div>
                <div class="stat-card-vital">
                    <div class="stat-value"><?php echo $stats['avg_temp'] ? number_format($stats['avg_temp'], 1) : 'N/A'; ?>°C</div>
                    <div class="stat-label">Avg Temperature</div>
                </div>
                <div class="stat-card-vital">
                    <div class="stat-value"><?php echo $stats['avg_pulse'] ? number_format($stats['avg_pulse'], 0) : 'N/A'; ?></div>
                    <div class="stat-label">Avg Pulse (BPM)</div>
                </div>
                <div class="stat-card-vital">
                    <div class="stat-value"><?php echo $stats['avg_spo2'] ? number_format($stats['avg_spo2'], 0) : 'N/A'; ?>%</div>
                    <div class="stat-label">Avg SpO2</div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                <div class="search-box">
                    <form method="GET" action="" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <input type="text" name="search" placeholder="Search vital signs..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="width: 200px;">
                        <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($searchTerm || $visitFilter): ?>
                            <a href="vital_signs.php" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <details open style="margin-bottom: 20px;">
                <summary style="cursor: pointer; font-size: 18px; font-weight: 700; color: #1e293b; padding: 14px 0;">
                    <i class="fas fa-user-clock"></i> Patients Waiting for Vitals (<?php echo count($vitalQueue); ?>)
                </summary>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead><tr><th>#</th><th>Patient</th><th>Visit</th><th>Arrival</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if (empty($vitalQueue)): ?>
                                    <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 32px;">No patients are waiting for vital signs.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($vitalQueue as $position => $queuePatient): ?>
                                    <tr>
                                        <td><?php echo $position + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($queuePatient['patient_name']); ?></strong><br><small><?php echo htmlspecialchars($queuePatient['patient_code']); ?></small></td>
                                        <td><?php echo htmlspecialchars($queuePatient['visit_code']); ?></td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($queuePatient['admitted_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($queuePatient['visit_status']); ?></td>
                                        <td><a href="vital_signs.php?action=create_vital&visit_id=<?php echo $queuePatient['visit_id']; ?>" class="btn-create-action"><i class="fas fa-heartbeat"></i> Fill Vital</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>

            <details open>
                <summary style="cursor: pointer; font-size: 18px; font-weight: 700; color: #1e293b; padding: 14px 0;">
                    <i class="fas fa-history"></i> Previous Vital Records (<?php echo count($vitalSigns); ?>)
                </summary>
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="recent-table">
                            <thead><tr><th>Patient</th><th>Visit</th><th>Temperature</th><th>Pulse</th><th>BP</th><th>SpO2</th><th>Recorded</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (empty($vitalSigns)): ?>
                                    <tr><td colspan="8" style="text-align: center; color: #94a3b8; padding: 32px;">No vital records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($vitalSigns as $vital): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($vital['patient_name']); ?></strong><br><small><?php echo htmlspecialchars($vital['patient_code']); ?></small></td>
                                        <td><?php echo htmlspecialchars($vital['visit_code']); ?></td>
                                        <td><?php echo $vital['temperature_c'] !== null ? number_format($vital['temperature_c'], 1) . ' °C' : '-'; ?></td>
                                        <td><?php echo $vital['pulse_bpm'] !== null ? htmlspecialchars($vital['pulse_bpm']) . ' BPM' : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($vital['blood_pressure'] ?? '-'); ?></td>
                                        <td><?php echo $vital['spo2_percent'] !== null ? htmlspecialchars($vital['spo2_percent']) . '%' : '-'; ?></td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($vital['recorded_at'])); ?></td>
                                        <td>
                                            <a href="vital_signs.php?action=edit_vital&id=<?php echo $vital['vital_id']; ?>" class="btn-edit"><i class="fas fa-edit"></i></a>
                                            <a href="vital_signs.php?action=delete_vital&id=<?php echo $vital['vital_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this vital signs record?');"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>
        </main>
    </div>

    <!-- Create/Edit Vital Signs Modal -->
    <div class="form-modal" id="vitalModal" style="display: <?php echo ($editVital || isset($_GET['action']) && $_GET['action'] === 'create_vital') ? 'flex' : 'none'; ?>;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='vital_signs.php'">&times;</button>
            <h2 style="margin-bottom: 24px;"><?php echo $editVital ? 'Edit Vital Signs' : 'Record Vital Signs'; ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $editVital ? 'update_vital' : 'create_vital'; ?>">
                <?php if ($editVital): ?>
                    <input type="hidden" name="vital_id" value="<?php echo $editVital['vital_id']; ?>">
                <?php endif; ?>
                
                <?php if ($editVital): ?>
                    <input type="hidden" name="vital_id" value="<?php echo $editVital['vital_id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="visit_id">Visit *</label>
                        <?php if ($editVital): ?>
                            <input type="hidden" name="visit_id" value="<?php echo $editVital['visit_id']; ?>">
                            <input type="text" value="<?php echo htmlspecialchars($editVital['visit_code'] . ' - ' . $editVital['patient_name'] . ' (' . $editVital['patient_code'] . ')'); ?>" readonly>
                        <?php else: ?>
                            <?php foreach ($visits as $visit): if ((int) $visit['visit_id'] === $selectedVisitId): ?>
                                <input type="hidden" name="visit_id" value="<?php echo $selectedVisitId; ?>">
                                <input type="text" value="<?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['patient_name'] . ' (' . $visit['patient_code'] . ')'); ?>" readonly>
                            <?php endif; endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="recorded_by">Recorded By</label>
                        <select id="recorded_by" name="recorded_by">
                            <option value="">Select Staff</option>
                            <?php 
                            $currentRecorder = $editVital ? $editVital['recorded_by'] : $_SESSION['user_id'];
                            foreach ($staff as $person): 
                            ?>
                                <option value="<?php echo $person['staff_id']; ?>" <?php echo ($currentRecorder == $person['staff_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($person['first_name'] . ' ' . $person['last_name'] . ' (' . $person['role_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="attending_doctor_id">Assign Doctor</label>
                    <select id="attending_doctor_id" name="attending_doctor_id">
                        <option value="">-- Select Doctor --</option>
                        <?php 
                        $currentDoctor = $editVital ? $editVital['attending_doctor_id'] : '';
                        foreach ($doctors as $doctor): 
                        ?>
                            <option value="<?php echo $doctor['staff_id']; ?>" <?php echo ($currentDoctor == $doctor['staff_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row-3">
                    <div class="form-group">
                        <label for="temperature_c">Temperature (°C)</label>
                        <input type="number" id="temperature_c" name="temperature_c" step="0.1" 
                               value="<?php echo htmlspecialchars($editVital['temperature_c'] ?? ''); ?>" 
                               placeholder="36.5">
                    </div>
                    <div class="form-group">
                        <label for="pulse_bpm">Pulse (BPM)</label>
                        <input type="number" id="pulse_bpm" name="pulse_bpm" 
                               value="<?php echo htmlspecialchars($editVital['pulse_bpm'] ?? ''); ?>" 
                               placeholder="72">
                    </div>
                    <div class="form-group">
                        <label for="blood_pressure">Blood Pressure (mmHg)</label>
                        <input type="text" id="blood_pressure" name="blood_pressure" 
                               value="<?php echo htmlspecialchars($editVital['blood_pressure'] ?? ''); ?>" 
                               placeholder="120/80">
                    </div>
                </div>
                
                <div class="form-row-3">
                    <div class="form-group">
                        <label for="respiration_rate">Respiration Rate (/min)</label>
                        <input type="number" id="respiration_rate" name="respiration_rate" 
                               value="<?php echo htmlspecialchars($editVital['respiration_rate'] ?? ''); ?>" 
                               placeholder="16">
                    </div>
                    <div class="form-group">
                        <label for="spo2_percent">SpO2 (%)</label>
                        <input type="number" id="spo2_percent" name="spo2_percent" 
                               value="<?php echo htmlspecialchars($editVital['spo2_percent'] ?? ''); ?>" 
                               placeholder="98">
                    </div>
                    <div class="form-group">
                        <label for="weight_kg">Weight (kg)</label>
                        <input type="number" id="weight_kg" name="weight_kg" step="0.1" 
                               value="<?php echo htmlspecialchars($editVital['weight_kg'] ?? ''); ?>" 
                               placeholder="70.5">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="height_cm">Height (cm)</label>
                    <input type="number" id="height_cm" name="height_cm" step="0.1" 
                           value="<?php echo htmlspecialchars($editVital['height_cm'] ?? ''); ?>" 
                           placeholder="175.0">
                </div>
                
                <div style="background: #f8fafc; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; color: #64748b;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Normal Ranges:</strong>
                    Temperature: 36.5-37.5°C | Pulse: 60-100 BPM | BP: 120/80 mmHg | Respiration: 12-20/min | SpO2: 95-100%
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <?php echo $editVital ? 'Update Vital Signs' : 'Record Vital Signs'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='vital_signs.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>