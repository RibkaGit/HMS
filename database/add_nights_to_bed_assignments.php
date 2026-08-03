<?php
require_once '../config/database.php';

// Add number_of_nights column to bed_assignments table
$checkQuery = "SHOW COLUMNS FROM bed_assignments LIKE 'number_of_nights'";
$checkResult = $conn->query($checkQuery);

if ($checkResult->num_rows == 0) {
    $query = "ALTER TABLE bed_assignments ADD COLUMN number_of_nights INT DEFAULT 1 AFTER notes";
    if ($conn->query($query)) {
        echo "Column 'number_of_nights' added successfully to bed_assignments table.<br>";
    } else {
        echo "Error adding column: " . $conn->error . "<br>";
    }
} else {
    echo "Column 'number_of_nights' already exists in bed_assignments table.<br>";
}

echo "<a href='../bed_management.php'>Go to Bed Management</a>";
?>
