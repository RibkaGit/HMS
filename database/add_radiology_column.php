<?php
require_once __DIR__ . '/../config/database.php';

echo "Starting database migration for radiology flag...\n";

// Check if needs_radiology column exists in medical_records
$existingColumns = [];
$result = $conn->query("SHOW COLUMNS FROM medical_records");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existingColumns[] = strtolower($row['Field']);
    }
} else {
    die("Error: Could not retrieve medical_records table columns. " . $conn->error . "\n");
}

if (!in_array('needs_radiology', $existingColumns)) {
    echo "Adding column `needs_radiology` to `medical_records` table...\n";
    $alterQuery = "ALTER TABLE medical_records ADD COLUMN `needs_radiology` TINYINT(1) NOT NULL DEFAULT 0 AFTER `needs_lab`";
    if ($conn->query($alterQuery)) {
        echo "SUCCESS: Column `needs_radiology` added successfully.\n";
    } else {
        die("FAILED: " . $conn->error . "\n");
    }
} else {
    echo "Column `needs_radiology` already exists in `medical_records`. Skipping.\n";
}

echo "Migration finished.\n";
?>
