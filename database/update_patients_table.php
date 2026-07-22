<?php
require_once __DIR__ . '/../config/database.php';

// List of columns to add with their SQL definitions
$columns = [
    'title' => "VARCHAR(20) DEFAULT NULL AFTER `last_name`",
    'middle_name' => "VARCHAR(60) DEFAULT NULL AFTER `title`",
    'uhid_date' => "DATE DEFAULT NULL AFTER `patient_code`",
    'new_born' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `uhid_date`",
    'marital_status' => "VARCHAR(30) DEFAULT NULL AFTER `gender_id`",
    'occupation' => "VARCHAR(100) DEFAULT NULL AFTER `marital_status`",
    'language' => "VARCHAR(50) DEFAULT NULL AFTER `occupation`",
    'religion' => "VARCHAR(50) DEFAULT NULL AFTER `language`",
    'nationality' => "VARCHAR(50) DEFAULT NULL AFTER `religion`",
    'loyalty_name' => "VARCHAR(100) DEFAULT NULL AFTER `email`",
    'loyalty_card_no' => "VARCHAR(50) DEFAULT NULL AFTER `loyalty_name`",
    'loyalty_expiry_date' => "DATE DEFAULT NULL AFTER `loyalty_card_no`",
    'identity_type' => "VARCHAR(50) DEFAULT NULL AFTER `national_id`",
    'visa_validity' => "DATE DEFAULT NULL AFTER `identity_type`",
    'address_village' => "VARCHAR(255) DEFAULT NULL AFTER `address`",
    'province' => "VARCHAR(100) DEFAULT NULL AFTER `address_village`",
    'district_khan' => "VARCHAR(100) DEFAULT NULL AFTER `province`",
    'commune_sangkat' => "VARCHAR(100) DEFAULT NULL AFTER `district_khan`",
    'postal_code' => "VARCHAR(20) DEFAULT NULL AFTER `commune_sangkat`",
    'postal_address_same' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `postal_code`",
    'telephone_2' => "VARCHAR(20) DEFAULT NULL AFTER `phone`",
    'relative_same_address' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `emergency_contact_phone`",
    'relative_relationship' => "VARCHAR(50) DEFAULT NULL AFTER `relative_same_address`",
    'relative_name' => "VARCHAR(100) DEFAULT NULL AFTER `relative_relationship`",
    'relative_phone' => "VARCHAR(20) DEFAULT NULL AFTER `relative_name`",
    'relative_address_village' => "VARCHAR(255) DEFAULT NULL AFTER `relative_phone`",
    'relative_province' => "VARCHAR(100) DEFAULT NULL AFTER `relative_address_village`",
    'relative_district_khan' => "VARCHAR(100) DEFAULT NULL AFTER `relative_province`",
    'relative_commune_sangkat' => "VARCHAR(100) DEFAULT NULL AFTER `relative_district_khan`",
    'relative_postal_code' => "VARCHAR(20) DEFAULT NULL AFTER `relative_commune_sangkat`",
    'relative_telephone_2' => "VARCHAR(20) DEFAULT NULL AFTER `relative_postal_code`"
];

echo "Starting database migration...\n";

// Get existing columns
$existingColumns = [];
$result = $conn->query("SHOW COLUMNS FROM patients");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existingColumns[] = strtolower($row['Field']);
    }
} else {
    die("Error: Could not retrieve patients table columns. " . $conn->error . "\n");
}

$addedCount = 0;
foreach ($columns as $columnName => $definition) {
    if (!in_array(strtolower($columnName), $existingColumns)) {
        echo "Adding column `{$columnName}`...";
        $alterQuery = "ALTER TABLE patients ADD COLUMN `{$columnName}` {$definition}";
        if ($conn->query($alterQuery)) {
            echo " SUCCESS\n";
            $addedCount++;
        } else {
            echo " FAILED: " . $conn->error . "\n";
        }
    } else {
        echo "Column `{$columnName}` already exists. Skipping.\n";
    }
}

echo "Migration finished. Total columns added: {$addedCount}.\n";
?>
