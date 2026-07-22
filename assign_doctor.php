<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - BULK DOCTOR ASSIGNMENT
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

// Get all doctors
$doctors = $conn->query("SELECT staff_id, first_name, last_name FROM staff WHERE role_id = (SELECT role_id FROM lookup_roles WHERE name = 'Doctor') AND is_active = 1 ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

// Get patients without doctors
$patientsWithoutDoctor = $conn->query("
    SELECT p.*, g.name as gender_name
    FROM patients p
    LEFT JOIN lookup_genders g ON p.gender_id = g.gender_id
    WHERE p.is_active = 1 AND p.primary_doctor_id IS NULL
    ORDER BY p.last_name, p.first_name
")->fetch_all(MYSQLI_ASSOC);

// Get patients with doctors
$patientsWithDoctor = $conn->query("
    SELECT p.*, g.name as gender_name,
           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
           d.staff_id as doctor_id
    FROM patients p
    LEFT JOIN lookup_genders g ON p.gender_id = g.gender_id
    LEFT JOIN staff d ON p.primary_doctor_id = d.staff_id
    WHERE p.is_active = 1 AND p.primary_doctor_id IS NOT NULL
    ORDER BY d.last_name, p.last_name
")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// BULK ASSIGN DOCTOR
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_assign') {
    $doctorId = intval($_POST['doctor_id']);
    $patientIds = $_POST['patient_ids'] ?? [];
    
    if (empty($patientIds)) {
        $error = 'Please select at least one patient.';
    } elseif (empty($doctorId)) {
        $error = 'Please select a doctor.';
    } else {
        $successCount = 0;
        foreach ($patientIds as $patientId) {
            $query = "UPDATE patients SET primary_doctor_id = ? WHERE patient_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $doctorId, $patientId);
            if ($stmt->execute()) {
                $successCount++;
            }
        }
        
        if ($successCount > 0) {
            logUserActivity($conn, $_SESSION['user_id'], 'Bulk Assigned Doctors', "Assigned doctor to {$successCount} patients");
            $message = "Successfully assigned doctor to {$successCount} patient(s)!";
            header('Location: assign_doctor.php?message=' . urlencode($message));
            exit();
        } else {
            $error = 'Failed to assign doctors. Please try again.';
        }
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
    <title>Assign Doctor - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .assign-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .assign-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 24px;
        }
        .assign-card h3 {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .patient-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .patient-checkbox:hover {
            background: #f8fafc;
        }
        .patient-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #2563eb;
        }
        .patient-checkbox .patient-name {
            font-weight: 500;
        }
        .patient-checkbox .patient-code {
            color: #94a3b8;
            font-size: 13px;
        }
        .patient-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .btn-assign-bulk {
            padding: 10px 24px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
            margin-top: 16px;
        }
        .btn-assign-bulk:hover {
            background: #1d4ed8;
        }
        .btn-select-all {
            padding: 6px 16px;
            background: #f1f5f9;
            color: #475569;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
        }
        .btn-select-all:hover {
            background: #e2e8f0;
        }
        .doctor-badge-small {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            background: #dbeafe;
            color: #2563eb;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            .assign-container {
                grid-template-columns: 1fr;
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
                    <h1>Assign Doctors to Patients</h1>
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

            <div class="assign-container">
                <!-- Bulk Assign -->
                <div class="assign-card">
                    <h3><i class="fas fa-user-plus"></i> Bulk Assign Doctor</h3>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="bulk_assign">
                        
                        <div class="form-group">
                            <label for="doctor_id">Select Doctor</label>
                            <select id="doctor_id" name="doctor_id" required>
                                <option value="">-- Select Doctor --</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['staff_id']; ?>">
                                        <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h4 style="font-size: 14px; font-weight: 600; color: #0f172a;">
                                Patients Without Doctor (<?php echo count($patientsWithoutDoctor); ?>)
                            </h4>
                            <button type="button" class="btn-select-all" onclick="toggleAllCheckboxes()">
                                Select All
                            </button>
                        </div>
                        
                        <div class="patient-list">
                            <?php if (empty($patientsWithoutDoctor)): ?>
                                <p style="color: #94a3b8; text-align: center; padding: 20px;">
                                    <i class="fas fa-check-circle" style="color: #22c55e;"></i> 
                                    All patients have doctors assigned!
                                </p>
                            <?php else: ?>
                                <?php foreach ($patientsWithoutDoctor as $patient): ?>
                                    <div class="patient-checkbox">
                                        <input type="checkbox" name="patient_ids[]" value="<?php echo $patient['patient_id']; ?>" class="patient-check">
                                        <div>
                                            <span class="patient-name"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
                                            <span class="patient-code">(<?php echo htmlspecialchars($patient['patient_code']); ?>)</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn-assign-bulk" <?php echo empty($patientsWithoutDoctor) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check"></i> Assign Selected Patients
                        </button>
                    </form>
                </div>

                <!-- Current Assignments -->
                <div class="assign-card">
                    <h3><i class="fas fa-user-md"></i> Current Doctor Assignments</h3>
                    
                    <div class="patient-list">
                        <?php if (empty($patientsWithDoctor)): ?>
                            <p style="color: #94a3b8; text-align: center; padding: 20px;">
                                No patients with assigned doctors.
                            </p>
                        <?php else: ?>
                            <?php 
                            $currentDoctor = '';
                            foreach ($patientsWithDoctor as $patient): 
                            ?>
                                <?php if ($currentDoctor !== $patient['doctor_name']): ?>
                                    <?php if ($currentDoctor !== ''): ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="margin-top: 12px; padding: 8px 12px; background: #f8fafc; border-radius: 6px;">
                                        <strong style="color: #2563eb;">
                                            <i class="fas fa-user-md"></i> 
                                            <?php echo htmlspecialchars($patient['doctor_name']); ?>
                                        </strong>
                                        <div style="margin-top: 4px;">
                                <?php 
                                    $currentDoctor = $patient['doctor_name'];
                                endif; 
                                ?>
                                <div style="padding: 4px 0; font-size: 14px; display: flex; justify-content: space-between;">
                                    <span><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
                                    <span style="color: #94a3b8; font-size: 12px;"><?php echo htmlspecialchars($patient['patient_code']); ?></span>
                                </div>
                            <?php endforeach; ?>
                                    </div>
                                </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleAllCheckboxes() {
            const checkboxes = document.querySelectorAll('.patient-check');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        }
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>