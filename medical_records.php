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

// Get all patients for dropdown
$patients = $conn->query("SELECT patient_id, patient_code, first_name, last_name FROM patients WHERE is_active = 1 ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// Get all doctors for dropdown
$doctors = $conn->query("SELECT staff_id, first_name, last_name FROM staff WHERE role_id = (SELECT role_id FROM lookup_roles WHERE name = 'Doctor') AND is_active = 1")->fetch_all(MYSQLI_ASSOC);

// Get all visits for dropdown
$visits = $conn->query("SELECT v.visit_id, v.visit_code, CONCAT(p.first_name, ' ', p.last_name) as patient_name 
                        FROM visits v 
                        JOIN patients p ON v.patient_id = p.patient_id 
                        ORDER BY v.admitted_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// CREATE MEDICAL RECORD
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_record') {
    $query = "INSERT INTO medical_records (visit_id, patient_id, doctor_id, diagnosis, clinical_notes) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiiss', 
        $_POST['visit_id'],
        $_POST['patient_id'],
        $_POST['doctor_id'],
        $_POST['diagnosis'],
        $_POST['clinical_notes']
    );
    
    if ($stmt->execute()) {
        $recordId = $conn->insert_id;
        logUserActivity($conn, $_SESSION['user_id'], 'Created Medical Record', "Created medical record ID: {$recordId}");
        $message = 'Medical record created successfully!';
        header('Location: medical_records.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create medical record. Please try again.';
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

$query .= " ORDER BY mr.created_at DESC LIMIT 100";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$medicalRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

            <div class="table-card">
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
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['patient_id']; ?>">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="visit_id">Visit</label>
                        <select id="visit_id" name="visit_id">
                            <option value="">Select Visit</option>
                            <?php foreach ($visits as $visit): ?>
                                <option value="<?php echo $visit['visit_id']; ?>" <?php echo $selectedVisitId === (int) $visit['visit_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($visit['visit_code'] . ' - ' . $visit['patient_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="patient_id" value="<?php echo $editRecord['patient_id']; ?>">
                    <input type="hidden" name="visit_id" value="<?php echo $editRecord['visit_id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="doctor_id">Doctor *</label>
                    <select id="doctor_id" name="doctor_id" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['staff_id']; ?>" <?php echo (isset($editRecord['doctor_id']) && $editRecord['doctor_id'] == $doctor['staff_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="diagnosis">Diagnosis</label>
                    <input type="text" id="diagnosis" name="diagnosis" value="<?php echo htmlspecialchars($editRecord['diagnosis'] ?? ''); ?>" placeholder="e.g., Acute Bronchitis, Hypertension">
                </div>
                
                <div class="form-group">
                    <label for="clinical_notes">Clinical Notes</label>
                    <textarea id="clinical_notes" name="clinical_notes" rows="5" placeholder="Detailed clinical notes, observations, and treatment plan..."><?php echo htmlspecialchars($editRecord['clinical_notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <?php echo $editRecord ? 'Update Record' : 'Create Record'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='medical_records.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>