<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - VISIT MANAGEMENT
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

// Get all patients for dropdown
$patients = $conn->query("SELECT patient_id, patient_code, first_name, last_name FROM patients WHERE is_active = 1 ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// Get all doctors for dropdown
$doctors = $conn->query("SELECT staff_id, first_name, last_name FROM staff WHERE role_id = (SELECT role_id FROM lookup_roles WHERE name = 'Doctor') AND is_active = 1")->fetch_all(MYSQLI_ASSOC);

// Get visit types and statuses
$visitTypes = $conn->query("SELECT * FROM lookup_visit_types")->fetch_all(MYSQLI_ASSOC);
$visitStatuses = $conn->query("SELECT * FROM lookup_visit_statuses")->fetch_all(MYSQLI_ASSOC);
$departments = $conn->query("SELECT * FROM lookup_departments")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// CREATE VISIT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $data = [
        'visit_code' => generateVisitCode($conn),
        'patient_id' => intval($_POST['patient_id']),
        'visit_type_id' => intval($_POST['visit_type_id']),
        'department_id' => intval($_POST['department_id']),
        'attending_doctor_id' => !empty($_POST['attending_doctor_id']) ? intval($_POST['attending_doctor_id']) : null,
        'visit_status_id' => intval($_POST['visit_status_id']),
        'notes' => sanitizeInput($_POST['notes'] ?? '')
    ];
    
    $result = createVisit($conn, $data);
    if ($result) {
        if ($data['attending_doctor_id']) {
            updateVisitStatus($conn, $result, 'In Consultation');
        }
        logUserActivity($conn, $_SESSION['user_id'], 'Created Visit', "Created visit: {$data['visit_code']}");
        $message = 'Visit created successfully!';
        header('Location: visits.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create visit. Please try again.';
    }
}

// ============================================================================
// UPDATE VISIT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $visitId = intval($_POST['visit_id']);
    $data = [
        'visit_type_id' => intval($_POST['visit_type_id']),
        'department_id' => intval($_POST['department_id']),
        'attending_doctor_id' => !empty($_POST['attending_doctor_id']) ? intval($_POST['attending_doctor_id']) : null,
        'visit_status_id' => intval($_POST['visit_status_id']),
        'notes' => sanitizeInput($_POST['notes'] ?? '')
    ];
    
    $query = "UPDATE visits SET 
              visit_type_id = ?, department_id = ?, attending_doctor_id = ?,
              visit_status_id = ?, notes = ?
              WHERE visit_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiii si', 
        $data['visit_type_id'],
        $data['department_id'],
        $data['attending_doctor_id'],
        $data['visit_status_id'],
        $data['notes'],
        $visitId
    );
    
    if ($stmt->execute()) {
        if ($data['attending_doctor_id']) {
            updateVisitStatus($conn, $visitId, 'In Consultation');
        }
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Visit', "Updated visit ID: {$visitId}");
        $message = 'Visit updated successfully!';
        header('Location: visits.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update visit. Please try again.';
    }
}

// ============================================================================
// DELETE VISIT
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $visitId = intval($_GET['id']);
    
    $query = "UPDATE visits SET visit_status_id = (SELECT visit_status_id FROM lookup_visit_statuses WHERE name = 'Cancelled') WHERE visit_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $visitId);
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Cancelled Visit', "Cancelled visit ID: {$visitId}");
        $message = 'Visit cancelled successfully!';
        header('Location: visits.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to cancel visit. Please try again.';
    }
}

// ============================================================================
// GET VISITS
// ============================================================================
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
if ($searchTerm) {
    $query = "SELECT v.*, 
              p.first_name, p.last_name, p.patient_code, p.payment_confirmed,
              vt.name as visit_type, vs.name as visit_status,
              d.name as department,
              CONCAT(s.first_name, ' ', s.last_name) as doctor_name
              FROM visits v
              JOIN patients p ON v.patient_id = p.patient_id
              JOIN lookup_visit_types vt ON v.visit_type_id = vt.visit_type_id
              JOIN lookup_visit_statuses vs ON v.visit_status_id = vs.visit_status_id
              JOIN lookup_departments d ON v.department_id = d.department_id
              LEFT JOIN staff s ON v.attending_doctor_id = s.staff_id
              WHERE p.first_name LIKE ? OR p.last_name LIKE ? OR v.visit_code LIKE ?
              ORDER BY v.admitted_at DESC";
    $searchTermLike = "%{$searchTerm}%";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sss', $searchTermLike, $searchTermLike, $searchTermLike);
    $stmt->execute();
    $visits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $query = "SELECT v.*, 
              p.first_name, p.last_name, p.patient_code, p.payment_confirmed,
              vt.name as visit_type, vs.name as visit_status,
              d.name as department,
              CONCAT(s.first_name, ' ', s.last_name) as doctor_name
              FROM visits v
              JOIN patients p ON v.patient_id = p.patient_id
              JOIN lookup_visit_types vt ON v.visit_type_id = vt.visit_type_id
              JOIN lookup_visit_statuses vs ON v.visit_status_id = vs.visit_status_id
              JOIN lookup_departments d ON v.department_id = d.department_id
              LEFT JOIN staff s ON v.attending_doctor_id = s.staff_id
              ORDER BY v.admitted_at DESC
              LIMIT 50";
    $visits = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Get visit for edit
$editVisit = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editVisit = getVisitById($conn, intval($_GET['id']));
}

$selectedPatientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$selectedPatient = null;
foreach ($patients as $patient) {
    if ((int) $patient['patient_id'] === $selectedPatientId) {
        $selectedPatient = $patient;
        break;
    }
}

$patientsWithoutVisits = $conn->query("SELECT p.patient_id, p.patient_code, p.first_name, p.last_name
    FROM patients p
    LEFT JOIN visits v ON v.patient_id = p.patient_id
    WHERE p.is_active = 1 AND v.visit_id IS NULL
    ORDER BY p.registered_at DESC")->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visits - HMS</title>
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
            display: <?php echo ($editVisit || isset($_GET['action']) && $_GET['action'] === 'create') ? 'flex' : 'none'; ?>;
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
        .search-box-visits {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .search-box-visits input {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            width: 250px;
        }
        .search-box-visits input:focus {
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
        .status-badge {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-registered { background: #dbeafe; color: #2563eb; }
        .status-triage { background: #fef3c7; color: #d97706; }
        .status-inconsultation { background: #ede9fe; color: #7c3aed; }
        .status-awaitingresults { background: #dbeafe; color: #2563eb; }
        .status-awaitingbilling { background: #fef3c7; color: #d97706; }
        .status-discharged { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
    <div class="dashboard-container">
<?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <h1>Visit Management</h1>
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
                <div class="search-box-visits">
                    <form method="GET" action="" style="display: flex; gap: 8px;">
                        <input type="text" name="search" placeholder="Search visits..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($searchTerm): ?>
                            <a href="visits.php" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if (!empty($patientsWithoutVisits)): ?>
            <div class="table-card" style="margin-bottom: 24px;">
                <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
                    <strong>Patients Needing Their First Visit</strong>
                </div>
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead><tr><th>Patient</th><th>Registered</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($patientsWithoutVisits as $newPatient): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($newPatient['patient_code'] . ' - ' . $newPatient['first_name'] . ' ' . $newPatient['last_name']); ?></td>
                                <td>New patient</td>
                                <td><a href="visits.php?action=create&patient_id=<?php echo $newPatient['patient_id']; ?>" class="btn-create-action"><i class="fas fa-calendar-plus"></i> Add Visit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Visit Code</th>
                                <th>Patient</th>
                                <th>Type</th>
                                <th>Department</th>
                                <th>Doctor</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($visits)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        No visits found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($visits as $visit): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($visit['visit_code']); ?></strong></td>
                                    <td>
                                        <div class="patient-info">
                                            <div class="avatar" style="background: <?php echo getUserColor($visit['first_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo strtoupper(substr($visit['first_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="patient-name"><?php echo htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']); ?></span>
                                                <small><?php echo htmlspecialchars($visit['patient_code']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($visit['visit_type']); ?></td>
                                    <td><?php echo htmlspecialchars($visit['department']); ?></td>
                                    <td><?php echo htmlspecialchars($visit['doctor_name'] ?? 'Not Assigned'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $visit['visit_status'])); ?>">
                                            <?php echo htmlspecialchars($visit['visit_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($visit['admitted_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="visits.php?action=edit&id=<?php echo $visit['visit_id']; ?>" class="btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if ($visit['visit_status'] === 'Awaiting Billing' || $visit['payment_confirmed'] == 0): ?>
                                                <a href="#" onclick="return false;" class="btn-create-action" style="opacity: 0.5; cursor: not-allowed;" title="Payment Required">
                                                    <i class="fas fa-heartbeat"></i> Vital
                                                </a>
                                                <a href="#" onclick="return false;" class="btn-create-action" style="opacity: 0.5; cursor: not-allowed;" title="Payment Required">
                                                    <i class="fas fa-file-medical"></i> Record
                                                </a>
                                                <a href="#" onclick="return false;" class="btn-create-action" style="opacity: 0.5; cursor: not-allowed;" title="Payment Required">
                                                    <i class="fas fa-flask"></i> Lab
                                                </a>
                                                <a href="#" onclick="return false;" class="btn-create-action" style="opacity: 0.5; cursor: not-allowed;" title="Payment Required">
                                                    <i class="fas fa-x-ray"></i> Radiology
                                                </a>
                                                <a href="#" onclick="return false;" class="btn-create-action" style="opacity: 0.5; cursor: not-allowed;" title="Payment Required">
                                                    <i class="fas fa-pills"></i> Medication
                                                </a>
                                                <?php if ($visit['visit_type'] === 'IPD'): ?>
                                                    <a href="#" onclick="return false;" class="btn-create-action" style="opacity: 0.5; cursor: not-allowed;" title="Payment Required">
                                                        <i class="fas fa-bed"></i> Bed
                                                    </a>
                                                <?php endif; ?>
                                            <?php elseif ($visit['visit_status'] !== 'Cancelled'): ?>
                                                <a href="vital_signs.php?action=create_vital&visit_id=<?php echo $visit['visit_id']; ?>" class="btn-create-action">
                                                    <i class="fas fa-heartbeat"></i> Vital
                                                </a>
                                                <a href="medical_records.php?action=create_record&visit_id=<?php echo $visit['visit_id']; ?>" class="btn-create-action">
                                                    <i class="fas fa-file-medical"></i> Record
                                                </a>
                                                <a href="lab.php?action=create&visit_id=<?php echo $visit['visit_id']; ?>" class="btn-create-action">
                                                    <i class="fas fa-flask"></i> Lab
                                                </a>
                                                <a href="radiology.php?action=create&visit_id=<?php echo $visit['visit_id']; ?>" class="btn-create-action">
                                                    <i class="fas fa-x-ray"></i> Radiology
                                                </a>
                                                <a href="pharmacy.php?action=create_prescription&visit_id=<?php echo $visit['visit_id']; ?>" class="btn-create-action">
                                                    <i class="fas fa-pills"></i> Medication
                                                </a>
                                                <?php if ($visit['visit_type'] === 'IPD'): ?>
                                                    <a href="bed_management.php?action=assign_bed&visit_id=<?php echo $visit['visit_id']; ?>" class="btn-create-action">
                                                        <i class="fas fa-bed"></i> Bed
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($visit['visit_status'] !== 'Cancelled' && $visit['visit_status'] !== 'Discharged'): ?>
                                                <a href="visits.php?action=delete&id=<?php echo $visit['visit_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to cancel this visit?');">
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
        </main>
    </div>

    <!-- Create/Edit Modal -->
    <div class="form-modal" id="visitModal" style="display: <?php echo ($editVisit || isset($_GET['action']) && $_GET['action'] === 'create') ? 'flex' : 'none'; ?>;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='visits.php'">&times;</button>
            <h2 style="margin-bottom: 24px;"><?php echo $editVisit ? 'Edit Visit' : 'Create New Visit'; ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $editVisit ? 'update' : 'create'; ?>">
                <?php if ($editVisit): ?>
                    <input type="hidden" name="visit_id" value="<?php echo $editVisit['visit_id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="patient_id">Patient *</label>
                        <?php if (!$editVisit && $selectedPatient): ?>
                            <input type="hidden" name="patient_id" value="<?php echo $selectedPatient['patient_id']; ?>">
                            <input type="text" value="<?php echo htmlspecialchars($selectedPatient['patient_code'] . ' - ' . $selectedPatient['first_name'] . ' ' . $selectedPatient['last_name']); ?>" readonly>
                        <?php else: ?>
                        <select id="patient_id" name="patient_id" required <?php echo $editVisit ? 'disabled' : ''; ?>>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patient_id']; ?>" <?php echo ((isset($editVisit['patient_id']) && $editVisit['patient_id'] == $patient['patient_id']) || (!$editVisit && $selectedPatientId == $patient['patient_id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['patient_code'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <?php if ($editVisit): ?>
                            <input type="hidden" name="patient_id" value="<?php echo $editVisit['patient_id']; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="visit_type_id">Visit Type *</label>
                        <select id="visit_type_id" name="visit_type_id" required>
                            <option value="">Select Type</option>
                            <?php foreach ($visitTypes as $type): ?>
                                <option value="<?php echo $type['visit_type_id']; ?>" <?php echo (isset($editVisit['visit_type_id']) && $editVisit['visit_type_id'] == $type['visit_type_id']) ? 'selected' : ''; ?>>
                                    <?php echo $type['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="department_id">Department *</label>
                        <select id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" <?php echo (isset($editVisit['department_id']) && $editVisit['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                    <?php echo $dept['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="attending_doctor_id">Attending Doctor</label>
                        <select id="attending_doctor_id" name="attending_doctor_id">
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['staff_id']; ?>" <?php echo (isset($editVisit['attending_doctor_id']) && $editVisit['attending_doctor_id'] == $doctor['staff_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="visit_status_id">Status *</label>
                    <select id="visit_status_id" name="visit_status_id" required>
                        <option value="">Select Status</option>
                        <?php foreach ($visitStatuses as $status): ?>
                            <option value="<?php echo $status['visit_status_id']; ?>" <?php echo (isset($editVisit['visit_status_id']) && $editVisit['visit_status_id'] == $status['visit_status_id']) ? 'selected' : ''; ?>>
                                <?php echo $status['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($editVisit['notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <?php echo $editVisit ? 'Update Visit' : 'Create Visit'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='visits.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>