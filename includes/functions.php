<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - CORE FUNCTIONS
// ============================================================================

// ============================================================================
// USER MANAGEMENT FUNCTIONS
// ============================================================================

/**
 * Get user by ID
 */
function getUserById($conn, $userId) {
    $query = "SELECT u.*, r.name as role_name 
              FROM users u
              JOIN lookup_roles r ON u.role_id = r.role_id
              WHERE u.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get user by username
 */
function getUserByUsername($conn, $username) {
    $query = "SELECT u.*, r.name as role_name 
              FROM users u
              JOIN lookup_roles r ON u.role_id = r.role_id
              WHERE u.username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Create new user
 */
function createUser($conn, $data) {
    $query = "INSERT INTO users (username, email, password_hash, full_name, role_id, is_active) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssii', 
        $data['username'],
        $data['email'],
        $data['password_hash'],
        $data['full_name'],
        $data['role_id'],
        $data['is_active']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

/**
 * Update user
 */
function updateUser($conn, $userId, $data) {
    $query = "UPDATE users SET 
              email = ?, 
              full_name = ?, 
              role_id = ?, 
              is_active = ? 
              WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssiii', 
        $data['email'],
        $data['full_name'],
        $data['role_id'],
        $data['is_active'],
        $userId
    );
    return $stmt->execute();
}

/**
 * Update user password
 */
function updateUserPassword($conn, $userId, $newPasswordHash) {
    $query = "UPDATE users SET password_hash = ? WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $newPasswordHash, $userId);
    return $stmt->execute();
}

/**
 * Get all users with role names
 */
function getAllUsers($conn) {
    $query = "SELECT u.*, r.name as role_name 
              FROM users u
              JOIN lookup_roles r ON u.role_id = r.role_id
              ORDER BY u.created_at DESC";
    return $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get users by role
 */
function getUsersByRole($conn, $roleId) {
    $query = "SELECT u.*, r.name as role_name 
              FROM users u
              JOIN lookup_roles r ON u.role_id = r.role_id
              WHERE u.role_id = ?
              ORDER BY u.full_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $roleId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Check if username exists
 */
function usernameExists($conn, $username, $excludeUserId = null) {
    $query = "SELECT COUNT(*) as count FROM users WHERE username = ?";
    if ($excludeUserId) {
        $query .= " AND user_id != ?";
    }
    $stmt = $conn->prepare($query);
    if ($excludeUserId) {
        $stmt->bind_param('si', $username, $excludeUserId);
    } else {
        $stmt->bind_param('s', $username);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'] > 0;
}

/**
 * Check if email exists
 */
function emailExists($conn, $email, $excludeUserId = null) {
    $query = "SELECT COUNT(*) as count FROM users WHERE email = ?";
    if ($excludeUserId) {
        $query .= " AND user_id != ?";
    }
    $stmt = $conn->prepare($query);
    if ($excludeUserId) {
        $stmt->bind_param('si', $email, $excludeUserId);
    } else {
        $stmt->bind_param('s', $email);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'] > 0;
}

/**
 * Log user activity
 */
function logUserActivity($conn, $userId, $action, $details = null) {
    $query = "INSERT INTO activity_log (staff_id, action, details, ip_address) 
              VALUES (?, ?, ?, ?)";
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    if ($details) {
        $details .= " | User Agent: " . $userAgent;
    } else {
        $details = "User Agent: " . $userAgent;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isss', $userId, $action, $details, $ipAddress);
    return $stmt->execute();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
}

/**
 * Check if user has specific role
 */
function hasRole($roleName) {
    if (!isLoggedIn()) return false;
    return $_SESSION['role'] === $roleName;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roles) {
    if (!isLoggedIn()) return false;
    return in_array($_SESSION['role'], $roles);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user name
 */
function getCurrentUserName() {
    return $_SESSION['full_name'] ?? 'Guest';
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? 'Guest';
}

/**
 * Move a visit to a workflow status when a related action completes.
 */
function updateVisitStatus($conn, $visitId, $statusName) {
    $query = "UPDATE visits SET visit_status_id = (SELECT visit_status_id FROM lookup_visit_statuses WHERE name = ?) WHERE visit_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $statusName, $visitId);
    return $stmt->execute();
}

/**
 * Require login - redirects if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Require specific role - redirects if not authorized
 */
function requireRole($roleName) {
    requireLogin();
    if (!hasRole($roleName)) {
        header('Location: index.php?error=unauthorized');
        exit();
    }
}

// ============================================================================
// DASHBOARD STATISTICS FUNCTIONS
// ============================================================================

function getDashboardStats($conn) {
    $stats = [];
    
    // Total Patients
    $query = "SELECT COUNT(*) as total FROM patients WHERE is_active = 1";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $stats['total_patients'] = $row['total'];
    
    // Patients Growth (compared to last month)
    $query = "SELECT COUNT(*) as total FROM patients WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $thisMonth = $row['total'];
    
    $query = "SELECT COUNT(*) as total FROM patients WHERE registered_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 MONTH) AND DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $lastMonth = $row['total'];
    
    $stats['patients_growth'] = $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100, 1) : 100;
    
    // Today's Visits
    $query = "SELECT COUNT(*) as total FROM visits WHERE DATE(admitted_at) = CURDATE()";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $stats['today_visits'] = $row['total'];
    
    // Visits Growth
    $query = "SELECT COUNT(*) as total FROM visits WHERE admitted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $today = $row['total'];
    
    $query = "SELECT COUNT(*) as total FROM visits WHERE admitted_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND DATE_SUB(NOW(), INTERVAL 1 DAY)";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $yesterday = $row['total'];
    
    $stats['visits_growth'] = $yesterday > 0 ? round(($today - $yesterday) / $yesterday * 100, 1) : 100;
    
    // Today's Appointments
    $query = "SELECT COUNT(*) as total FROM appointments WHERE DATE(scheduled_at) = CURDATE() AND status != 'Cancelled'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $stats['today_appointments'] = $row['total'];
    
    // Appointments Change
    $query = "SELECT COUNT(*) as total FROM appointments WHERE DATE(scheduled_at) = CURDATE() AND status != 'Cancelled'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $todayAppts = $row['total'];
    
    $query = "SELECT COUNT(*) as total FROM appointments WHERE DATE(scheduled_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status != 'Cancelled'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $yesterdayAppts = $row['total'];
    
    $stats['appointments_change'] = $yesterdayAppts > 0 ? round(($todayAppts - $yesterdayAppts) / $yesterdayAppts * 100, 1) : 100;
    
    // Today's Revenue
    $query = "SELECT COALESCE(SUM(total), 0) as total FROM invoices WHERE DATE(created_at) = CURDATE() AND invoice_status_id = (SELECT invoice_status_id FROM lookup_invoice_statuses WHERE name = 'Paid')";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $stats['today_revenue'] = $row['total'];
    
    // Revenue Growth
    $query = "SELECT COALESCE(SUM(total), 0) as total FROM invoices WHERE DATE(created_at) = CURDATE() AND invoice_status_id = (SELECT invoice_status_id FROM lookup_invoice_statuses WHERE name = 'Paid')";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $todayRev = $row['total'];
    
    $query = "SELECT COALESCE(SUM(total), 0) as total FROM invoices WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND invoice_status_id = (SELECT invoice_status_id FROM lookup_invoice_statuses WHERE name = 'Paid')";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $yesterdayRev = $row['total'];
    
    $stats['revenue_growth'] = $yesterdayRev > 0 ? round(($todayRev - $yesterdayRev) / $yesterdayRev * 100, 1) : 100;
    
    return $stats;
}

// ============================================================================
// IMPORTANT: These are the functions used by index.php
// ============================================================================

/**
 * Get recent patients for dashboard
 */
function getRecentPatients($conn, $limit = 5) {
    $query = "SELECT 
                p.patient_id, p.patient_code, p.first_name, p.last_name,
                v.visit_id, vt.name as visit_type, vs.name as visit_status,
                v.admitted_at
              FROM patients p
              LEFT JOIN visits v ON p.patient_id = v.patient_id
              LEFT JOIN lookup_visit_types vt ON v.visit_type_id = vt.visit_type_id
              LEFT JOIN lookup_visit_statuses vs ON v.visit_status_id = vs.visit_status_id
              ORDER BY v.admitted_at DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $patients = $result->fetch_all(MYSQLI_ASSOC);
    
    // If no visits found, return patients without visit data
    if (empty($patients) || !isset($patients[0]['visit_id'])) {
        $query = "SELECT 
                    p.patient_id, p.patient_code, p.first_name, p.last_name,
                    NULL as visit_id, NULL as visit_type, NULL as visit_status,
                    NULL as admitted_at
                  FROM patients p
                  ORDER BY p.registered_at DESC
                  LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    return $patients;
}

/**
 * Get today's appointments count
 */
function getTodayAppointments($conn) {
    $query = "SELECT COUNT(*) as total FROM appointments WHERE DATE(scheduled_at) = CURDATE() AND status != 'Cancelled'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    return $row['total'];
}

/**
 * Get pending lab orders count
 */
function getPendingLabOrders($conn) {
    $query = "SELECT COUNT(*) as total FROM lab_orders WHERE order_status_id = (SELECT order_status_id FROM lookup_order_statuses WHERE name = 'Ordered')";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    return $row['total'];
}

/**
 * Get low stock medications count
 */
function getLowStockMedications($conn) {
    $query = "SELECT COUNT(*) as total FROM medications WHERE stock_quantity <= reorder_level AND is_active = 1";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    return $row['total'];
}

/**
 * Get bed occupancy
 */
function getBedOccupancy($conn) {
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied
              FROM beds";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    return [
        'total' => $row['total'] ?? 0,
        'occupied' => $row['occupied'] ?? 0
    ];
}

/**
 * Get recent activities
 */
function getRecentActivities($conn, $limit = 5) {
    $query = "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get today's appointments list
 */
function getTodayAppointmentsList($conn) {
    $query = "SELECT COUNT(*) as total FROM appointments WHERE DATE(scheduled_at) = CURDATE() AND status != 'Cancelled'";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    return $row['total'];
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get lookup ID from any lookup table
 */
function getLookupId($conn, $table, $column, $value) {
    $idColumn = '';
    
    switch ($table) {
        case 'lookup_departments':
            $idColumn = 'department_id';
            break;
        case 'lookup_roles':
            $idColumn = 'role_id';
            break;
        case 'lookup_visit_types':
            $idColumn = 'visit_type_id';
            break;
        case 'lookup_visit_statuses':
            $idColumn = 'visit_status_id';
            break;
        case 'lookup_genders':
            $idColumn = 'gender_id';
            break;
        case 'lookup_test_types':
            $idColumn = 'test_type_id';
            break;
        case 'lookup_order_statuses':
            $idColumn = 'order_status_id';
            break;
        case 'lookup_payment_methods':
            $idColumn = 'payment_method_id';
            break;
        case 'lookup_invoice_statuses':
            $idColumn = 'invoice_status_id';
            break;
        default:
            $idColumn = rtrim($table, 's') . '_id';
            break;
    }
    
    $query = "SELECT {$idColumn} as id FROM {$table} WHERE {$column} = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Error preparing statement: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param('s', $value);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row ? $row['id'] : null;
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone
 */
function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s()]+$/', $phone);
}

/**
 * Get status color class
 */
function getStatusColor($status) {
    $colors = [
        'Registered' => 'status-registered',
        'Triage' => 'status-triage',
        'In Consultation' => 'status-inconsultation',
        'Awaiting Results' => 'status-awaitingresults',
        'Awaiting Billing' => 'status-awaitingbilling',
        'Discharged' => 'status-discharged',
        'Cancelled' => 'status-cancelled',
        'Scheduled' => 'status-scheduled',
        'Checked-in' => 'status-checkedin',
        'Completed' => 'status-completed',
        'No-show' => 'status-noshow',
        'Unknown' => 'status-unknown'
    ];
    return isset($colors[$status]) ? $colors[$status] : 'status-unknown';
}

/**
 * Get role icon
 */
function getRoleIcon($role) {
    $icons = [
        'Receptionist' => 'fa-user-tie',
        'Nurse' => 'fa-user-nurse',
        'Doctor' => 'fa-user-md',
        'Lab Technician' => 'fa-flask',
        'Pharmacist' => 'fa-prescription-bottle',
        'Billing Staff' => 'fa-file-invoice-dollar',
        'System Administrator' => 'fa-cog'
    ];
    return isset($icons[$role]) ? $icons[$role] : 'fa-user';
}

/**
 * Get user color based on name
 */
function getUserColor($name) {
    $colors = ['#2563eb', '#8b5cf6', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4', '#ec4899'];
    $index = abs(crc32($name)) % count($colors);
    return $colors[$index];
}

/**
 * Get activity icon
 */
function getActivityIcon($action) {
    $icons = [
        'Login' => 'sign-in-alt',
        'Logout' => 'sign-out-alt',
        'Created Patient' => 'user-plus',
        'Updated Patient' => 'user-edit',
        'Created Visit' => 'notes-medical',
        'Updated Visit' => 'edit',
        'Created Appointment' => 'calendar-plus',
        'Updated Appointment' => 'calendar-edit',
        'Created Lab Order' => 'flask',
        'Updated Lab Result' => 'check-circle',
        'Created Prescription' => 'prescription-bottle',
        'Dispensed Medication' => 'pills',
        'Created Invoice' => 'file-invoice',
        'Processed Payment' => 'hand-holding-usd',
        'Failed Login' => 'exclamation-triangle'
    ];
    
    foreach ($icons as $key => $icon) {
        if (stripos($action, $key) !== false) {
            return $icon;
        }
    }
    return 'clipboard-list';
}

/**
 * Format time ago
 */
function timeAgo($dateString) {
    if (!$dateString) return 'N/A';
    
    $now = new DateTime();
    $past = new DateTime($dateString);
    $diff = $now->diff($past);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

/**
 * Generate random string
 */
function generateRandomString($length = 8) {
    return bin2hex(random_bytes($length));
}

// ============================================================================
// PATIENT FUNCTIONS
// ============================================================================

function generatePatientCode($conn) {
    $year = date('Y');
    $query = "SELECT COUNT(*) as total FROM patients WHERE YEAR(registered_at) = YEAR(CURDATE())";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $number = str_pad($row['total'] + 1, 6, '0', STR_PAD_LEFT);
    return "PT-{$year}-{$number}";
}

function getPatientById($conn, $patientId) {
    $query = "SELECT p.*, g.name as gender_name 
              FROM patients p
              LEFT JOIN lookup_genders g ON p.gender_id = g.gender_id
              WHERE p.patient_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function searchPatients($conn, $searchTerm) {
    $searchTerm = "%{$searchTerm}%";
    $query = "SELECT p.*, 
              (SELECT COUNT(*) FROM visits WHERE patient_id = p.patient_id) as total_visits
              FROM patients p
              WHERE p.first_name LIKE ? 
              OR p.last_name LIKE ? 
              OR p.patient_code LIKE ?
              OR p.phone LIKE ?
              OR p.email LIKE ?
              ORDER BY p.last_name, p.first_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function createPatient($conn, $data) {
    $query = "INSERT INTO patients (patient_code, first_name, last_name, date_of_birth, 
              gender_id, phone, email, address, national_id, blood_group, 
              emergency_contact_name, emergency_contact_phone) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssisssssss',
        $data['patient_code'],
        $data['first_name'],
        $data['last_name'],
        $data['date_of_birth'],
        $data['gender_id'],
        $data['phone'],
        $data['email'],
        $data['address'],
        $data['national_id'],
        $data['blood_group'],
        $data['emergency_contact_name'],
        $data['emergency_contact_phone']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

// ============================================================================
// VISIT FUNCTIONS
// ============================================================================

function generateVisitCode($conn) {
    $year = date('Y');
    $query = "SELECT COUNT(*) as total FROM visits WHERE YEAR(admitted_at) = YEAR(CURDATE())";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $number = str_pad($row['total'] + 1, 6, '0', STR_PAD_LEFT);
    return "VS-{$year}-{$number}";
}

function createVisit($conn, $data) {
    $query = "INSERT INTO visits (visit_code, patient_id, visit_type_id, department_id, 
              attending_doctor_id, visit_status_id, notes) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('siiiiis', 
        $data['visit_code'],
        $data['patient_id'],
        $data['visit_type_id'],
        $data['department_id'],
        $data['attending_doctor_id'],
        $data['visit_status_id'],
        $data['notes']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

function getVisitById($conn, $visitId) {
    $query = "SELECT v.*, 
              p.first_name, p.last_name, p.patient_code,
              vt.name as visit_type, vs.name as visit_status,
              d.name as department,
              CONCAT(s.first_name, ' ', s.last_name) as doctor_name
              FROM visits v
              JOIN patients p ON v.patient_id = p.patient_id
              JOIN lookup_visit_types vt ON v.visit_type_id = vt.visit_type_id
              JOIN lookup_visit_statuses vs ON v.visit_status_id = vs.visit_status_id
              JOIN lookup_departments d ON v.department_id = d.department_id
              LEFT JOIN staff s ON v.attending_doctor_id = s.staff_id
              WHERE v.visit_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getPatientVisits($conn, $patientId) {
    $query = "SELECT v.*, vt.name as visit_type, vs.name as visit_status,
              CONCAT(s.first_name, ' ', s.last_name) as doctor_name
              FROM visits v
              JOIN lookup_visit_types vt ON v.visit_type_id = vt.visit_type_id
              JOIN lookup_visit_statuses vs ON v.visit_status_id = vs.visit_status_id
              LEFT JOIN staff s ON v.attending_doctor_id = s.staff_id
              WHERE v.patient_id = ?
              ORDER BY v.admitted_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================================================
// APPOINTMENT FUNCTIONS
// ============================================================================

function getDoctorAppointments($conn, $doctorId, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $query = "SELECT a.*, p.first_name, p.last_name, p.patient_code,
              d.name as department
              FROM appointments a
              JOIN patients p ON a.patient_id = p.patient_id
              JOIN lookup_departments d ON a.department_id = d.department_id
              WHERE a.doctor_id = ? AND DATE(a.scheduled_at) = ?
              ORDER BY a.scheduled_at";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('is', $doctorId, $date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function createAppointment($conn, $data) {
    $query = "INSERT INTO appointments (patient_id, doctor_id, department_id, 
              scheduled_at, status) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiiss', 
        $data['patient_id'],
        $data['doctor_id'],
        $data['department_id'],
        $data['scheduled_at'],
        $data['status']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

/**
 * Queue an SMS notification for an appointment.
 * A provider worker can send pending rows from sms_notifications.
 */
function queueAppointmentSms($conn, $appointmentId) {
    $query = "SELECT a.scheduled_at, a.status, p.phone, p.first_name, p.last_name,
                     CONCAT(s.first_name, ' ', s.last_name) as doctor_name,
                     d.name as department
              FROM appointments a
              JOIN patients p ON a.patient_id = p.patient_id
              LEFT JOIN staff s ON a.doctor_id = s.staff_id
              JOIN lookup_departments d ON a.department_id = d.department_id
              WHERE a.appointment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $appointmentId);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();

    if (!$appointment || trim((string) $appointment['phone']) === '') {
        return false;
    }

    $message = sprintf(
        'HMS appointment for %s %s: %s at %s with %s in %s. Status: %s.',
        $appointment['first_name'],
        $appointment['last_name'],
        date('M j, Y', strtotime($appointment['scheduled_at'])),
        date('g:i A', strtotime($appointment['scheduled_at'])),
        $appointment['doctor_name'] ?: 'the hospital team',
        $appointment['department'],
        $appointment['status']
    );

    $insert = $conn->prepare("INSERT INTO sms_notifications
        (appointment_id, patient_id, phone_number, message, status)
        SELECT appointment_id, patient_id, ?, ?, 'Pending'
        FROM appointments WHERE appointment_id = ?");
    $insert->bind_param('ssi', $appointment['phone'], $message, $appointmentId);
    return $insert->execute();
}

// ============================================================================
// BILLING FUNCTIONS
// ============================================================================

function generateInvoiceCode($conn) {
    $year = date('Y');
    $query = "SELECT COUNT(*) as total FROM invoices WHERE YEAR(created_at) = YEAR(CURDATE())";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $number = str_pad($row['total'] + 1, 6, '0', STR_PAD_LEFT);
    return "INV-{$year}-{$number}";
}

function createInvoice($conn, $visitId) {
    $visit = getVisitById($conn, $visitId);
    if (!$visit) return false;
    
    $invoiceCode = generateInvoiceCode($conn);
    $statusId = getLookupId($conn, 'lookup_invoice_statuses', 'name', 'Unpaid');
    $subtotal = 0.00;
    $total = 0.00;
    
    $query = "INSERT INTO invoices (invoice_code, visit_id, patient_id, 
              invoice_status_id, subtotal, total) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('siiidd', 
        $invoiceCode,
        $visitId,
        $visit['patient_id'],
        $statusId,
        $subtotal,
        $total
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

function getInvoiceByVisit($conn, $visitId) {
    $query = "SELECT i.*, is_l.name as status_name,
              p.first_name, p.last_name
              FROM invoices i
              JOIN lookup_invoice_statuses is_l ON i.invoice_status_id = is_l.invoice_status_id
              JOIN patients p ON i.patient_id = p.patient_id
              WHERE i.visit_id = ?
              ORDER BY i.created_at DESC
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function addInvoiceCharge($conn, $visitId, $description, $itemType, $quantity, $unitPrice) {
    $invoice = getInvoiceByVisit($conn, $visitId);
    if (!$invoice) {
        $invoiceId = createInvoice($conn, $visitId);
        if (!$invoiceId) {
            return false;
        }
    } else {
        $invoiceId = (int) $invoice['invoice_id'];
    }

    $check = $conn->prepare("SELECT invoice_item_id FROM invoice_items WHERE invoice_id = ? AND description = ? LIMIT 1");
    $check->bind_param('is', $invoiceId, $description);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        return $invoiceId;
    }

    $lineTotal = (float) $quantity * (float) $unitPrice;
    $item = $conn->prepare("INSERT INTO invoice_items (invoice_id, description, item_type, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)");
    $item->bind_param('issidd', $invoiceId, $description, $itemType, $quantity, $unitPrice, $lineTotal);
    if (!$item->execute()) {
        return false;
    }

    $update = $conn->prepare("UPDATE invoices SET subtotal = (SELECT COALESCE(SUM(line_total), 0) FROM invoice_items WHERE invoice_id = ?), total = (SELECT COALESCE(SUM(line_total), 0) FROM invoice_items WHERE invoice_id = ?) WHERE invoice_id = ?");
    $update->bind_param('iii', $invoiceId, $invoiceId, $invoiceId);
    if (!$update->execute()) {
        return false;
    }

    $unpaidStatusId = getLookupId($conn, 'lookup_invoice_statuses', 'name', 'Unpaid');
    $status = $conn->prepare("UPDATE invoices SET invoice_status_id = ? WHERE invoice_id = ? AND total > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?)");
    $status->bind_param('iii', $unpaidStatusId, $invoiceId, $invoiceId);
    $status->execute();
    return $invoiceId;
}

function isVisitPaid($conn, $visitId) {
    $query = "SELECT i.total, COALESCE(SUM(p.amount), 0) as paid_amount
              FROM invoices i
              LEFT JOIN payments p ON p.invoice_id = i.invoice_id
              WHERE i.visit_id = ?
              GROUP BY i.invoice_id, i.total
              ORDER BY i.invoice_id DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    return $invoice && (float) $invoice['paid_amount'] >= (float) $invoice['total'];
}

// ============================================================================
// LAB FUNCTIONS
// ============================================================================

function createLabOrder($conn, $data) {
    $query = "INSERT INTO lab_orders (visit_id, test_type_id, ordered_by, 
              order_status_id) 
              VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiii', 
        $data['visit_id'],
        $data['test_type_id'],
        $data['ordered_by'],
        $data['order_status_id']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

function getLabOrdersByVisit($conn, $visitId) {
    $query = "SELECT lo.*, tt.name as test_name, tt.category,
              os.name as status_name,
              CONCAT(s.first_name, ' ', s.last_name) as ordered_by_name
              FROM lab_orders lo
              JOIN lookup_test_types tt ON lo.test_type_id = tt.test_type_id
              JOIN lookup_order_statuses os ON lo.order_status_id = os.order_status_id
              JOIN staff s ON lo.ordered_by = s.staff_id
              WHERE lo.visit_id = ?
              ORDER BY lo.ordered_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $visitId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function updateLabResult($conn, $orderId, $resultData) {
    $query = "INSERT INTO lab_results (order_id, entered_by, result_value, result_notes) 
              VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iiss', 
        $orderId,
        $resultData['entered_by'],
        $resultData['result_value'],
        $resultData['result_notes']
    );
    
    if ($stmt->execute()) {
        $statusId = getLookupId($conn, 'lookup_order_statuses', 'name', 'Result Ready');
        $query = "UPDATE lab_orders SET order_status_id = ? WHERE order_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $statusId, $orderId);
        return $stmt->execute();
    }
    return false;
}

// ============================================================================
// PHARMACY FUNCTIONS
// ============================================================================

function createPrescription($conn, $data) {
    $query = "INSERT INTO prescriptions (visit_id, prescribed_by, status) 
              VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iis', 
        $data['visit_id'],
        $data['prescribed_by'],
        $data['status']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

function addPrescriptionItem($conn, $data) {
    $query = "INSERT INTO prescription_items (prescription_id, medication_id, 
              dosage, duration_days, quantity) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iisii', 
        $data['prescription_id'],
        $data['medication_id'],
        $data['dosage'],
        $data['duration_days'],
        $data['quantity']
    );
    
    return $stmt->execute();
}

function updateMedicationStock($conn, $medicationId, $quantity) {
    $query = "UPDATE medications SET stock_quantity = stock_quantity - ? 
              WHERE medication_id = ? AND stock_quantity >= ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $quantity, $medicationId, $quantity);
    return $stmt->execute();
}

function getMedicationsByPrescription($conn, $prescriptionId) {
    $query = "SELECT pi.*, m.name as medication_name, m.strength, m.unit
              FROM prescription_items pi
              JOIN medications m ON pi.medication_id = m.medication_id
              WHERE pi.prescription_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $prescriptionId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getPatientHistory($conn, $patientId) {
    $patientId = intval($patientId);
    $patient = getPatientById($conn, $patientId);
    if (!$patient) {
        return null;
    }

    $visits = getPatientVisits($conn, $patientId);

    $recordsQuery = "SELECT mr.*, CONCAT(d.first_name, ' ', d.last_name) as doctor_name, v.visit_code
                     FROM medical_records mr
                     JOIN staff d ON mr.doctor_id = d.staff_id
                     LEFT JOIN visits v ON mr.visit_id = v.visit_id
                     WHERE mr.patient_id = ?
                     ORDER BY mr.created_at DESC";
    $recordsStmt = $conn->prepare($recordsQuery);
    $recordsStmt->bind_param('i', $patientId);
    $recordsStmt->execute();
    $medicalRecords = $recordsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $labQuery = "SELECT lo.*, vt.name as test_name, os.name as status_name, v.visit_code
                 FROM lab_orders lo
                 JOIN lookup_test_types vt ON lo.test_type_id = vt.test_type_id
                 JOIN lookup_order_statuses os ON lo.order_status_id = os.order_status_id
                 JOIN visits v ON lo.visit_id = v.visit_id
                 WHERE v.patient_id = ?
                 ORDER BY lo.ordered_at DESC";
    $labStmt = $conn->prepare($labQuery);
    $labStmt->bind_param('i', $patientId);
    $labStmt->execute();
    $labOrders = $labStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $vitalsQuery = "SELECT vs.*, v.visit_code, CONCAT(s.first_name, ' ', s.last_name) as recorded_by_name
                    FROM vital_signs vs
                    JOIN visits v ON vs.visit_id = v.visit_id
                    LEFT JOIN staff s ON vs.recorded_by = s.staff_id
                    WHERE v.patient_id = ?
                    ORDER BY vs.recorded_at DESC
                    LIMIT 20";
    $vitalsStmt = $conn->prepare($vitalsQuery);
    $vitalsStmt->bind_param('i', $patientId);
    $vitalsStmt->execute();
    $vitalSigns = $vitalsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $rxQuery = "SELECT pr.*, v.visit_code, CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                FROM prescriptions pr
                JOIN visits v ON pr.visit_id = v.visit_id
                JOIN staff d ON pr.prescribed_by = d.staff_id
                WHERE v.patient_id = ?
                ORDER BY pr.prescribed_at DESC";
    $rxStmt = $conn->prepare($rxQuery);
    $rxStmt->bind_param('i', $patientId);
    $rxStmt->execute();
    $prescriptions = $rxStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $bedQuery = "SELECT ba.*, b.bed_number, w.name as ward_name, v.visit_code
                 FROM bed_assignments ba
                 JOIN beds b ON ba.bed_id = b.bed_id
                 JOIN wards w ON b.ward_id = w.ward_id
                 JOIN visits v ON ba.visit_id = v.visit_id
                 WHERE ba.patient_id = ?
                 ORDER BY ba.assigned_at DESC";
    $bedStmt = $conn->prepare($bedQuery);
    $bedStmt->bind_param('i', $patientId);
    $bedStmt->execute();
    $bedAssignments = $bedStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return [
        'patient' => $patient,
        'visits' => $visits,
        'medical_records' => $medicalRecords,
        'lab_orders' => $labOrders,
        'vital_signs' => $vitalSigns,
        'prescriptions' => $prescriptions,
        'bed_assignments' => $bedAssignments,
    ];
}
?>