<?php
// Database migration script to add lab notification columns
require_once __DIR__ . '/../config/database.php';

try {
    // Check if columns already exist
    $checkQuery = "SHOW COLUMNS FROM medical_records LIKE 'lab_results_ready'";
    $result = $conn->query($checkQuery);
    
    if ($result->num_rows > 0) {
        echo "Columns already exist. Migration not needed.<br>";
    } else {
        // Add the columns
        $alterQuery = "ALTER TABLE `medical_records`
                       ADD COLUMN `lab_results_ready` tinyint(1) NOT NULL DEFAULT 0 AFTER `needs_bed`,
                       ADD COLUMN `lab_results_ready_at` datetime DEFAULT NULL AFTER `lab_results_ready`";
        
        if ($conn->query($alterQuery)) {
            echo "Migration successful! Added lab notification columns to medical_records table.<br>";
        } else {
            echo "Migration failed: " . $conn->error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo '<a href="../medical_records.php">Return to Medical Records</a>';
?>
