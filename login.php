<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - LOGIN PAGE
// ============================================================================

session_start();

// Check if already logged in - but ONLY redirect if session is valid
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$username = '';

// Check for logout success message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been successfully logged out.';
}

// Check for timeout message
if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Your session has expired. Please login again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $user = null;
        $fromStaff = false;
        
        // First, try to authenticate from users table
        $query = "SELECT u.user_id, u.username, u.password_hash, u.full_name, u.is_active,
                  r.name as role_name, u.staff_id
                  FROM users u
                  JOIN lookup_roles r ON u.role_id = r.role_id
                  WHERE u.username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // If not found in users table, try staff table
        if (!$user) {
            $query = "SELECT s.staff_id, s.username, s.password_hash, 
                      CONCAT(s.first_name, ' ', s.last_name) as full_name, 
                      s.is_active, r.name as role_name
                      FROM staff s
                      JOIN lookup_roles r ON s.role_id = r.role_id
                      WHERE s.username = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $staffUser = $result->fetch_assoc();
            
            if ($staffUser) {
                // Convert staff data to user-like medical record assign below 
                $user = [
                    'user_id' => $staffUser['staff_id'],
                    'username' => $staffUser['username'],
                    'password_hash' => $staffUser['password_hash'],
                    'full_name' => $staffUser['full_name'],
                    'is_active' => $staffUser['is_active'],
                    'role_name' => $staffUser['role_name'],
                    'staff_id' => $staffUser['staff_id'],
                    'from_staff' => true
                ];
                $fromStaff = true;
            }
        }
        
        if ($user) {
            // Check if user is active
            if ($user['is_active'] == 0) {
                $error = 'Your account has been deactivated. Please contact administrator.';
            } else {
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role_name'];
                    $_SESSION['staff_id'] = $user['staff_id'] ?? null;
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    if ($fromStaff) {
                        // Update staff table
                        $updateQuery = "UPDATE staff SET created_at = created_at WHERE staff_id = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param('i', $user['staff_id']);
                        $updateStmt->execute();
                    } else {
                        // Update users table
                        $updateQuery = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param('i', $user['user_id']);
                        $updateStmt->execute();
                    }
                    
                    // Log login activity
                    logUserActivity($conn, $user['user_id'], 'Login', "User {$username} logged in successfully");
                    
                    // Redirect to dashboard
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Invalid password. Please try again.';
                    // Log failed attempt "doctor asign to pati"
                    logUserActivity($conn, null, 'Failed Login', "Failed login attempt for username: {$username} - Incorrect password");
                }
            }
        } else {
            $error = 'Username not found. Please check your username.';
            // Log failed attempt
            logUserActivity($conn, null, 'Failed Login', "Failed login attempt for username: {$username} - User not found");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HMS Login - Hospital Management System</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Additional styles for the login page */
        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        .alert-success i {
            font-size: 16px;
            color: #16a34a;
        }
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .alert-error i {
            font-size: 16px;
            color: #dc2626;
        }
        .login-container {
            width: 100%;
            max-width: 440px;
        }
        /* Debug info - remove in production */
        .debug-info {
            margin-top: 10px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 4px;
            font-size: 11px;
            color: #64748b;
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-hospital"></i>
                </div>
                <h1>Hospital Management System</h1>
                <p>Sign in to access the dashboard</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form" autocomplete="off">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" 
                               placeholder="Enter your username" 
                               value="<?php echo htmlspecialchars($username); ?>" 
                               required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <i class="fas fa-eye toggle-password" id="togglePassword" 
                           style="cursor:pointer; padding-right:14px; color:#94a3b8;"></i>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Remember me</span>
                    </label>
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
            
            <div class="demo-credentials">
                <p><strong>Demo Credentials:</strong></p>
                <div class="credentials-grid">
                    <div class="cred-item">
                        <span class="cred-role">Admin</span>
                        <span class="cred-user">admin</span>
                        <span class="cred-pass">Admin@123</span>
                    </div>
                    <div class="cred-item">
                        <span class="cred-role">Doctor</span>
                        <span class="cred-user">drjohnson</span>
                        <span class="cred-pass">Doctor@123</span>
                    </div>
                    <div class="cred-item">
                        <span class="cred-role">Nurse</span>
                        <span class="cred-user">nurseemily</span>
                        <span class="cred-pass">Nurse@123</span>
                    </div>
                    <div class="cred-item">
                        <span class="cred-role">Reception</span>
                        <span class="cred-user">reception</span>
                        <span class="cred-pass">Reception@123</span>
                    </div>
                    <div class="cred-item">
                        <span class="cred-role">Lab</span>
                        <span class="cred-user">labtech</span>
                        <span class="cred-pass">Lab@123</span>
                    </div>
                    <div class="cred-item">
                        <span class="cred-role">Pharmacy</span>
                        <span class="cred-user">pharmacist</span>
                        <span class="cred-pass">Pharmacy@123</span>
                    </div>
                    <div class="cred-item">
                        <span class="cred-role">Billing</span>
                        <span class="cred-user">billing</span>
                        <span class="cred-pass">Billing@123</span>
                    </div>
                </div>
            </div>
            
            <div class="login-footer">
                <p>&copy; 2026 HMS. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>