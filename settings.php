<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - SETTINGS
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

// Only admin can access settings
if ($_SESSION['role'] !== 'System Administrator') {
    header('Location: index.php?error=unauthorized');
    exit();
}

// ============================================================================
// UPDATE SETTINGS
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $userId = $_SESSION['user_id'];
        $fullName = sanitizeInput($_POST['full_name']);
        $email = sanitizeInput($_POST['email']);
        
        $query = "UPDATE users SET full_name = ?, email = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssi', $fullName, $email, $userId);
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $fullName;
            $message = 'Profile updated successfully!';
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
    } elseif ($action === 'change_password') {
        $userId = $_SESSION['user_id'];
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Get current password hash
        $query = "SELECT password_hash FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password_hash = ? WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('si', $newHash, $userId);
            
            if ($stmt->execute()) {
                $message = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password. Please try again.';
            }
        }
    } elseif ($action === 'add_lookup') {
        $table = $_POST['table'];
        $value = sanitizeInput($_POST['value']);
        
        $query = "INSERT INTO $table (name) VALUES (?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $value);
        
        if ($stmt->execute()) {
            logUserActivity($conn, $_SESSION['user_id'], 'Added Lookup', "Added $value to $table");
            $message = 'Lookup value added successfully!';
        } else {
            $error = 'Failed to add lookup value. Please try again.';
        }
    } elseif ($action === 'delete_lookup') {
        $table = $_POST['table'];
        $id = intval($_POST['id']);
        
        $idColumn = rtrim($table, 's') . '_id';
        $query = "DELETE FROM $table WHERE $idColumn = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            logUserActivity($conn, $_SESSION['user_id'], 'Deleted Lookup', "Deleted from $table");
            $message = 'Lookup value deleted successfully!';
        } else {
            $error = 'Failed to delete lookup value. Please try again.';
        }
    }
    
    // Refresh page to show updates
    if ($message) {
        header('Location: settings.php?message=' . urlencode($message));
        exit();
    } elseif ($error) {
        header('Location: settings.php?error=' . urlencode($error));
        exit();
    }
}

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Get lookup tables
$lookupTables = [
    'departments' => 'lookup_departments',
    'roles' => 'lookup_roles',
    'visit_types' => 'lookup_visit_types',
    'visit_statuses' => 'lookup_visit_statuses',
    'genders' => 'lookup_genders',
    'test_types' => 'lookup_test_types',
    'bed_types' => 'lookup_bed_types',
    'order_statuses' => 'lookup_order_statuses',
    'payment_methods' => 'lookup_payment_methods',
    'invoice_statuses' => 'lookup_invoice_statuses'
];

$lookupData = [];
foreach ($lookupTables as $key => $table) {
    $result = $conn->query("SELECT * FROM $table ORDER BY name");
    $lookupData[$key] = $result->fetch_all(MYSQLI_ASSOC);
}

// Get current user
$userQuery = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$currentUser = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .settings-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .settings-card h3 {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .settings-card .form-group {
            margin-bottom: 16px;
        }
        .settings-card .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 4px;
        }
        .settings-card .form-group input, 
        .settings-card .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        .settings-card .form-group input:focus, 
        .settings-card .form-group select:focus {
            outline: none;
            border-color: #2563eb;
        }
        .btn-primary {
            padding: 10px 24px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .btn-danger {
            padding: 10px 24px;
            background: #dc2626;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
            font-size: 14px;
            transition: background 0.3s;
        }
        .btn-danger:hover {
            background: #b91c1c;
        }
        .btn-sm {
            padding: 4px 12px;
            font-size: 12px;
        }
        .lookup-table {
            width: 100%;
            font-size: 14px;
            margin-top: 8px;
        }
        .lookup-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .lookup-table tr:last-child td {
            border-bottom: none;
        }
        .lookup-table .actions {
            display: flex;
            gap: 4px;
        }
        .lookup-table .actions button {
            padding: 2px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-family: inherit;
        }
        .lookup-table .actions .btn-delete-sm {
            background: #fee2e2;
            color: #dc2626;
        }
        .lookup-table .actions .btn-delete-sm:hover {
            background: #fecaca;
        }
        .lookup-add-form {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        .lookup-add-form input {
            flex: 1;
            padding: 6px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
        }
        .lookup-add-form input:focus {
            outline: none;
            border-color: #2563eb;
        }
        .lookup-add-form button {
            padding: 6px 16px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
        }
        .lookup-add-form button:hover {
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
        .password-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        @media (max-width: 768px) {
            .settings-grid {
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
                    <h1>System Settings</h1>
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
                <button class="tab active" data-tab="profile">Profile</button>
                <button class="tab" data-tab="lookup">Lookup Tables</button>
            </div>

            <!-- Profile Tab -->
            <div class="tab-content active" id="tab-profile">
                <div class="settings-grid">
                    <!-- Profile Settings -->
                    <div class="settings-card">
                        <h3><i class="fas fa-user"></i> Profile Settings</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($currentUser['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>" disabled style="background: #f1f5f9;">
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>" disabled style="background: #f1f5f9;">
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="settings-card">
                        <h3><i class="fas fa-lock"></i> Change Password</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                                <div class="password-hint">Minimum 6 characters</div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lookup Tables Tab -->
            <div class="tab-content" id="tab-lookup">
                <div class="settings-grid">
                    <?php foreach ($lookupData as $key => $data): ?>
                    <div class="settings-card">
                        <h3><?php echo ucwords(str_replace('_', ' ', $key)); ?></h3>
                        
                        <table class="lookup-table">
                            <tbody>
                                <?php foreach ($data as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td class="actions">
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_lookup">
                                            <input type="hidden" name="table" value="<?php echo $lookupTables[$key]; ?>">
                                            <input type="hidden" name="id" value="<?php echo $item[key($item)]; ?>">
                                            <button type="submit" class="btn-delete-sm" onclick="return confirm('Are you sure you want to delete this item?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <form method="POST" action="" class="lookup-add-form">
                            <input type="hidden" name="action" value="add_lookup">
                            <input type="hidden" name="table" value="<?php echo $lookupTables[$key]; ?>">
                            <input type="text" name="value" placeholder="Add new <?php echo rtrim($key, 's'); ?>..." required>
                            <button type="submit"><i class="fas fa-plus"></i> Add</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
            });
        });
        
        // Check for tab parameter on load
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam) {
            document.querySelectorAll('.tab').forEach(tab => {
                if (tab.dataset.tab === tabParam) {
                    tab.click();
                }
            });
        }
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>