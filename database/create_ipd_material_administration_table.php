<?php
require_once '../config/database.php';

// Create ipd_material_administration table
$sql = "CREATE TABLE IF NOT EXISTS ipd_material_administration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checkup_id INT NOT NULL,
    visit_id INT NOT NULL,
    material_id INT NOT NULL,
    quantity_used INT NOT NULL DEFAULT 1,
    unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    administered_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (checkup_id) REFERENCES ipd_checkups(checkup_id) ON DELETE CASCADE,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(material_id) ON DELETE CASCADE,
    FOREIGN KEY (administered_by) REFERENCES staff(staff_id)
)";

if ($conn->query($sql)) {
    echo "Table ipd_material_administration created successfully.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Add material_given and material_notes columns to ipd_checkups table if they don't exist
$alterSql1 = "ALTER TABLE ipd_checkups ADD COLUMN IF NOT EXISTS material_given TINYINT(1) DEFAULT 0";
if ($conn->query($alterSql1)) {
    echo "Column material_given added to ipd_checkups table.<br>";
} else {
    echo "Error adding column material_given: " . $conn->error . "<br>";
}

$alterSql2 = "ALTER TABLE ipd_checkups ADD COLUMN IF NOT EXISTS material_notes TEXT";
if ($conn->query($alterSql2)) {
    echo "Column material_notes added to ipd_checkups table.<br>";
} else {
    echo "Error adding column material_notes: " . $conn->error . "<br>";
}
?>
