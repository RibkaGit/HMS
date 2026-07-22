<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - DOCTOR MANAGEMENT
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

// Get departments for dropdown
$departments = $conn->query("SELECT * FROM lookup_departments")->fetch_all(MYSQLI_ASSOC);

// ============================================================================
// CREATE DOCTOR
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $query = "INSERT INTO staff (staff_code, first_name, last_name, role_id, department_id, email, phone, username, password_hash, is_active) 
              VALUES (?, ?, ?, (SELECT role_id FROM lookup_roles WHERE name = 'Doctor'), ?, ?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssissss',
        $_POST['staff_code'],
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['department_id'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['username'],
        $passwordHash
    );
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Created Doctor', "Created doctor: {$_POST['first_name']} {$_POST['last_name']}");
        $message = 'Doctor created successfully!';
        header('Location: doctors.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to create doctor. Please try again.';
    }
}

// ============================================================================
// UPDATE DOCTOR
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $doctorId = intval($_POST['doctor_id']);
    
    $query = "UPDATE staff SET 
              first_name = ?, last_name = ?, department_id = ?, email = ?, phone = ?, is_active = ?
              WHERE staff_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssissii',
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['department_id'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['is_active'],
        $doctorId
    );
    
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Doctor', "Updated doctor ID: {$doctorId}");
        $message = 'Doctor updated successfully!';
        header('Location: doctors.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to update doctor. Please try again.';
    }
}

// ============================================================================
// DELETE DOCTOR
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $doctorId = intval($_GET['id']);
    
    $query = "UPDATE staff SET is_active = 0 WHERE staff_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $doctorId);
    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Deleted Doctor', "Deleted doctor ID: {$doctorId}");
        $message = 'Doctor deleted successfully!';
        header('Location: doctors.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to delete doctor. Please try again.';
    }
}

// ============================================================================
// GET DOCTORS
// ============================================================================
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

$query = "SELECT s.*, d.name as department_name 
          FROM staff s
          JOIN lookup_departments d ON s.department_id = d.department_id
          WHERE s.role_id = (SELECT role_id FROM lookup_roles WHERE name = 'Doctor')";

if ($searchTerm) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ?)";
    $searchTermLike = "%{$searchTerm}%";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sss', $searchTermLike, $searchTermLike, $searchTermLike);
    $stmt->execute();
    $doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $query .= " ORDER BY s.first_name";
    $doctors = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Get doctor for edit
$editDoctor = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $doctorId = intval($_GET['id']);
    $query = "SELECT * FROM staff WHERE staff_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $doctorId);
    $stmt->execute();
    $editDoctor = $stmt->get_result()->fetch_assoc();
}

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Generate staff code
$staffCode = 'DOC-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
$year = date('Y');
$staffCode = "DOC-{$year}-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors - HMS</title>
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
            display: <?php echo ($editDoctor || isset($_GET['action']) && $_GET['action'] === 'create') ? 'flex' : 'none'; ?>;
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
        .search-box-doctors {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .search-box-doctors input {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            width: 250px;
        }
        .search-box-doctors input:focus {
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
        .status-badge-doctor {
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active { background: #d1fae5; color: #059669; }
        .status-inactive { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <h1>Doctor Management</h1>
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
                <div class="search-box-doctors">
                    <form method="GET" action="" style="display: flex; gap: 8px;">
                        <input type="text" name="search" placeholder="Search doctors..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($searchTerm): ?>
                            <a href="doctors.php" style="padding: 8px 16px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <button class="btn-create" onclick="window.location.href='doctors.php?action=create'">
                    <i class="fas fa-plus"></i> Add New Doctor
                </button>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Staff Code</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($doctors)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-user-md" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        No doctors found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($doctor['staff_code']); ?></strong></td>
                                    <td>
                                        <div class="patient-info">
                                            <div class="avatar" style="background: <?php echo getUserColor($doctor['first_name']); ?>; width: 32px; height: 32px; font-size: 12px;">
                                                <?php echo strtoupper(substr($doctor['first_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <span class="patient-name"><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($doctor['department_name']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge-doctor status-<?php echo $doctor['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="doctors.php?action=edit&id=<?php echo $doctor['staff_id']; ?>" class="btn-edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if ($doctor['is_active']): ?>
                                                <a href="doctors.php?action=delete&id=<?php echo $doctor['staff_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to deactivate this doctor?');">
                                                    <i class="fas fa-user-slash"></i> Deactivate
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
    <div class="form-modal" id="doctorModal" style="display: <?php echo ($editDoctor || isset($_GET['action']) && $_GET['action'] === 'create') ? 'flex' : 'none'; ?>;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="window.location.href='doctors.php'">&times;</button>
            <h2 style="margin-bottom: 24px;"><?php echo $editDoctor ? 'Edit Doctor' : 'Add New Doctor'; ?></h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="<?php echo $editDoctor ? 'update' : 'create'; ?>">
                <?php if ($editDoctor): ?>
                    <input type="hidden" name="doctor_id" value="<?php echo $editDoctor['staff_id']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($editDoctor['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($editDoctor['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <?php if (!$editDoctor): ?>
                <div class="form-group">
                    <label for="staff_code">Staff Code</label>
                    <input type="text" id="staff_code" name="staff_code" value="<?php echo $staffCode; ?>" readonly style="background: #f1f5f9;">
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="department_id">Department *</label>
                    <select id="department_id" name="department_id" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" <?php echo (isset($editDoctor['department_id']) && $editDoctor['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo $dept['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($editDoctor['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($editDoctor['phone'] ?? ''); ?>">
                    </div>
                </div>
                
                <?php if (!$editDoctor): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($editDoctor): ?>
                <div class="form-group">
                    <label for="is_active">Status</label>
                    <select id="is_active" name="is_active">
                        <option value="1" <?php echo ($editDoctor['is_active'] == 1) ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo ($editDoctor['is_active'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <?php echo $editDoctor ? 'Update Doctor' : 'Create Doctor'; ?>
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='doctors.php'">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>