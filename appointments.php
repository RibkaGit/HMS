<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - APPOINTMENT MANAGEMENT
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
$patients = $conn->query("SELECT patient_id, patient_code, first_name, last_name FROM patients WHERE is_active = 1 ORDER BY patient_id ASC")->fetch_all(MYSQLI_ASSOC);

// Get all doctors for dropdown
$doctors = $conn->query("SELECT staff_id, first_name, last_name FROM staff WHERE role_id = (SELECT role_id FROM lookup_roles WHERE name = 'Doctor') AND is_active = 1")->fetch_all(MYSQLI_ASSOC);

// Get departments
$departments = $conn->query("SELECT * FROM lookup_departments")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// CREATE APPOINTMENT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $data = [
        'patient_id' => intval($_POST['patient_id']),
        'doctor_id' => intval($_POST['doctor_id']),
        'department_id' => intval($_POST['department_id']),
        'scheduled_at' => $_POST['scheduled_at'],
        'status' => sanitizeInput($_POST['status'])
    ];
    
    $result = createAppointment($conn, $data);
    if ($result) {
        queueAppointmentSms($conn, $result);
        logUserActivity($conn, $_SESSION['user_id'], 'Created Appointment', "Created appointment for patient ID: {$data['patient_id']}");
        $message = 'Appointment created successfully!';
        header('Location: appointments.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create appointment. Please try again.';
    }
}

// ============================================================================
// UPDATE APPOINTMENT
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $appointmentId = intval($_POST['appointment_id']);
    $data = [
        'doctor_id' => intval($_POST['doctor_id']),
        'department_id' => intval($_POST['department_id']),
        'scheduled_at' => $_POST['scheduled_at'],
        'status' => sanitizeInput($_POST['status'])
    ];
    
    $query = "UPDATE appointments SET 
              doctor_id = ?, department_id = ?, scheduled_at = ?, status = ?
              WHERE appointment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iissi',
        $data['doctor_id'],
        $data['department_id'],
        $data['scheduled_at'],
        $data['status'],
        $appointmentId
    );
    
    if ($stmt->execute()) {
        queueAppointmentSms($conn, $appointmentId);
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Appointment', "Updated appointment ID: {$appointmentId}");
        $message = 'Appointment updated successfully!';
        header('Location: appointments.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update appointment. Please try again.';
    }
}

// ============================================================================
// DELETE APPOINTMENT
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $appointmentId = intval($_GET['id']);
    
    $query = "UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $appointmentId);
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Cancelled Appointment', "Cancelled appointment ID: {$appointmentId}");
        $message = 'Appointment cancelled successfully!';
        header('Location: appointments.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to cancel appointment. Please try again.';
    }
}

// ============================================================================
// GET APPOINTMENTS
// ============================================================================
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

$query = "SELECT a.*, 
          p.first_name, p.last_name, p.patient_code,
          CONCAT(s.first_name, ' ', s.last_name) as doctor_name,
          d.name as department
          FROM appointments a
          JOIN patients p ON a.patient_id = p.patient_id
          LEFT JOIN staff s ON a.doctor_id = s.staff_id
          JOIN lookup_departments d ON a.department_id = d.department_id
          WHERE 1=1";

$params = [];
$types = "";

if ($searchTerm) {
    $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_code LIKE ?)";
    $searchTermLike = "%{$searchTerm}%";
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $params[] = $searchTermLike;
    $types .= "sss";
}

if ($filterStatus && $filterStatus !== 'All') {
    $query .= " AND a.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

$query .= " ORDER BY a.scheduled_at DESC LIMIT 50";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get appointment for edit
$editAppointment = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $appointmentId = intval($_GET['id']);
    $query = "SELECT * FROM appointments WHERE appointment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $appointmentId);
    $stmt->execute();
    $editAppointment = $stmt->get_result()->fetch_assoc();
}

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Get status counts
$statusCounts = [];
$statusQuery = "SELECT status, COUNT(*) as count FROM appointments GROUP BY status";
$statusResult = $conn->query($statusQuery);
while ($row = $statusResult->fetch_assoc()) {
    $statusCounts[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - HMS</title>
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
            display: <?php echo ($editAppointment || isset($_GET['action']) && $_GET['action'] === 'create') ? 'flex' : 'none'; ?>;
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
        .search-box-appointments {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-box-appointments input, .search-box-appointments select {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        .search-box-appointments input:focus, .search-box-appointments select:focus {
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
        .status-badge-appointment {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-scheduled { background: #dbeafe; color: #2563eb; }
        .status-checkedin { background: #fef3c7; color: #d97706; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-noshow { background: #fee2e2; color: #dc2626; }
        .status-cancelled { background: #f1f5f9; color: #64748b; }
        
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
        .filter-tab .count {
            background: rgba(255,255,255,0.2);
            padding: 1px 8px;
            border-radius: 9999px;
            font-size: 11px;
            margin-left: 4px;
        }
        .filter-tab.active .count {
            background: rgba(255,255,255,0.3);
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
                    <h1>Appointment Management</h1>
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
                <div class="search-box-appointments">
                    <form method="GET" action="" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <input type="text" name="search" placeholder="Search appointments..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="width: 200px;">
                        <select name="status">
                            <option value="All">All Status</option>
                            <option value="Scheduled" <?php echo $filterStatus === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="Checked-in" <?php echo $filterStatus === 'Checked-in' ? 'selected' : ''; ?>>Checked-in</option>
                            <option value="Completed" <?php echo $filterStatus === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="No-show" <?php echo $filterStatus === 'No-show' ? 'selected' : ''; ?>>No-show</option>
                            <option value="Cancelled" <?php echo $filterStatus === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($searchTerm || $filterStatus): ?>
                            <a href="appointments.php" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <button class="btn-create" onclick="window.location.href='appointments.php?action=create'">
                    <i class="fas fa-plus"></i> New Appointment
                </button>
            </div>

            <!-- Status Filter Tabs -->
            <div class="filter-tabs" style="margin-bottom: 20px;">
                <a href="appointments.php" class="filter-tab <?php echo !$filterStatus ? 'active' : ''; ?>">
                    All <span class="count"><?php echo array_sum($statusCounts); ?></span>
                </a>
                <a href="appointments.php?status=Scheduled" class="filter-tab <?php echo $filterStatus === 'Scheduled' ? 'active' : ''; ?>">
                    Scheduled <span class="count"><?php echo $statusCounts['Scheduled'] ?? 0; ?></span>
                </a>
                <a href="appointments.php?status=Checked-in" class="filter-tab <?php echo $filterStatus === 'Checked-in' ? 'active' : ''; ?>">
                    Checked-in <span class="count"><?php echo $statusCounts['Checked-in'] ?? 0; ?></span>
                </a>
                <a href="appointments.php?status=Completed" class="filter-tab <?php echo $filterStatus === 'Completed' ? 'active' : ''; ?>">
                    Completed <span class="count"><?php echo $statusCounts['Completed'] ?? 0; ?></span>
                </a>
                <a href="appointments.php?status=No-show" class="filter-tab <?php echo $filterStatus === 'No-show' ? 'active' : ''; ?>">
                    No-show <span class="count"><?php echo $statusCounts['No-show'] ?? 0; ?></span>
                </a>
                <a href="appointments.php?status=Cancelled" class="filter-tab <?php echo $filterStatus === 'Cancelled' ? 'active' : ''; ?>">
                    Cancelled <span class="count"><?php echo $statusCounts['Cancelled'] ?? 0; ?></span>
                </a>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Department</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-calendar-times" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        No appointments found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <div class="patient-info">
                                            <div class="avatar" style="background: <?php echo getUserColor($appointment['first_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo strtoupper(substr($appointment['first_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="patient-name"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></span>
                                                <small><?php echo htmlspecialchars($appointment['patient_code']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['doctor_name'] ?? 'Not Assigned'); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['department']); ?></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($appointment['scheduled_at'])); ?></td>
                                    <td>
                                        <span class="status-badge-appointment status-<?php echo strtolower($appointment['status']); ?>">
                                            <?php echo htmlspecialchars($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($appointment['status'] !== 'Cancelled' && $appointment['status'] !== 'Completed'): ?>
                                                <a href="appointments.php?action=edit&id=<?php echo $appointment['appointment_id']; ?>" class="btn-edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="appointments.php?action=delete&id=<?php echo $appointment['appointment_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 12px;">No actions</span>
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
    <div class="form-modal" id="appointmentModal" style="display: <?php echo ($editAppointment || isset($_GET['action']) && $_GET['action'] === 'create') ? 'flex' : 'none'; ?>;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='appointments.php'">&times;</button>
            <h2 style="margin-bottom: 24px;"><?php echo $editAppointment ? 'Edit Appointment' : 'Create New Appointment'; ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $editAppointment ? 'update' : 'create'; ?>">
                <?php if ($editAppointment): ?>
                    <input type="hidden" name="appointment_id" value="<?php echo $editAppointment['appointment_id']; ?>">
                <?php endif; ?>
                
                <?php if (!$editAppointment): ?>
                <div class="form-group">
                    <label for="patient_id">Patient *</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['patient_id']; ?>">
                                <?php echo htmlspecialchars($patient['patient_code'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="doctor_id">Doctor *</label>
                        <select id="doctor_id" name="doctor_id" required>
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['staff_id']; ?>" <?php echo (isset($editAppointment['doctor_id']) && $editAppointment['doctor_id'] == $doctor['staff_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="department_id">Department *</label>
                        <select id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" <?php echo (isset($editAppointment['department_id']) && $editAppointment['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                    <?php echo $dept['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="scheduled_at">Date & Time *</label>
                        <input type="datetime-local" id="scheduled_at" name="scheduled_at" required 
                               value="<?php echo isset($editAppointment['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($editAppointment['scheduled_at'])) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="Scheduled" <?php echo (isset($editAppointment['status']) && $editAppointment['status'] === 'Scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="Checked-in" <?php echo (isset($editAppointment['status']) && $editAppointment['status'] === 'Checked-in') ? 'selected' : ''; ?>>Checked-in</option>
                            <option value="Completed" <?php echo (isset($editAppointment['status']) && $editAppointment['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="No-show" <?php echo (isset($editAppointment['status']) && $editAppointment['status'] === 'No-show') ? 'selected' : ''; ?>>No-show</option>
                            <option value="Cancelled" <?php echo (isset($editAppointment['status']) && $editAppointment['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <?php echo $editAppointment ? 'Update Appointment' : 'Create Appointment'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='appointments.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>