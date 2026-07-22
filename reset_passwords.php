<?php
// ============================================================================
// RESET ALL PASSWORDS - RUN THIS TO FIX LOGIN ISSUES
// ============================================================================

require_once 'config/database.php';

echo "<h1>Resetting All Passwords</h1>";
echo "<pre>";

// Define users and their passwords
$users = [
    ['table' => 'users', 'username' => 'admin', 'password' => 'Admin@123'],
    ['table' => 'users', 'username' => 'drjohnson', 'password' => 'Doctor@123'],
    ['table' => 'users', 'username' => 'drsmith', 'password' => 'Doctor@123'],
    ['table' => 'users', 'username' => 'nurseemily', 'password' => 'Nurse@123'],
    ['table' => 'users', 'username' => 'reception', 'password' => 'Reception@123'],
    ['table' => 'users', 'username' => 'labtech', 'password' => 'Lab@123'],
    ['table' => 'users', 'username' => 'pharmacist', 'password' => 'Pharmacy@123'],
    ['table' => 'users', 'username' => 'billing', 'password' => 'Billing@123'],
    ['table' => 'staff', 'username' => 'admin', 'password' => 'Admin@123'],
    ['table' => 'staff', 'username' => 'drjohnson', 'password' => 'Doctor@123'],
    ['table' => 'staff', 'username' => 'drsmith', 'password' => 'Doctor@123'],
    ['table' => 'staff', 'username' => 'nurseemily', 'password' => 'Nurse@123'],
    ['table' => 'staff', 'username' => 'reception', 'password' => 'Reception@123'],
    ['table' => 'staff', 'username' => 'labtech', 'password' => 'Lab@123'],
    ['table' => 'staff', 'username' => 'pharmacist', 'password' => 'Pharmacy@123'],
    ['table' => 'staff', 'username' => 'billing', 'password' => 'Billing@123'],
];

foreach ($users as $user) {
    $table = $user['table'];
    $username = $user['username'];
    $password = $user['password'];
    
    // Generate new hash
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update password
    $idField = $table === 'users' ? 'user_id' : 'staff_id';
    $query = "UPDATE {$table} SET password_hash = ? WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $hash, $username);
    
    if ($stmt->execute()) {
        echo "✓ Updated {$table}.{$username} password to: {$password}\n";
    } else {
        echo "✗ Failed to update {$table}.{$username}: " . $conn->error . "\n";
    }
}

echo "\n========================================\n";
echo "ALL PASSWORDS HAVE BEEN RESET!\n";
echo "========================================\n";
echo "\nTry logging in with:\n";
echo "Username: admin\n";
echo "Password: Admin@123\n";
echo "\n</pre>";
?>