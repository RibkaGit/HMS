<?php
// ============================================================================
// CREATE MATERIALS TABLE
// ============================================================================

include '../config/database.php';

// Create materials table
$query = "CREATE TABLE IF NOT EXISTS materials (
    material_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    stock_quantity INT NOT NULL DEFAULT 0,
    minimum_stock INT NOT NULL DEFAULT 10,
    category VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($query)) {
    echo "Materials table created successfully.<br>";
} else {
    echo "Error creating materials table: " . $conn->error . "<br>";
}

// Create material_usage table to track usage
$query = "CREATE TABLE IF NOT EXISTS material_usage (
    usage_id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    visit_id INT,
    patient_id INT,
    quantity_used INT NOT NULL,
    unit_cost DECIMAL(10, 2) NOT NULL,
    total_cost DECIMAL(10, 2) NOT NULL,
    used_by INT NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (material_id) REFERENCES materials(material_id),
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id),
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (used_by) REFERENCES staff(staff_id)
)";

if ($conn->query($query)) {
    echo "Material usage table created successfully.<br>";
} else {
    echo "Error creating material usage table: " . $conn->error . "<br>";
}

// Insert sample materials
$sampleMaterials = [
    ['Surgical Gloves', 'Latex surgical gloves', 'pair', 2.50, 500, 50, 'Surgical'],
    ['Syringe 5ml', '5ml disposable syringe', 'piece', 0.50, 1000, 100, 'Injection'],
    ['Syringe 10ml', '10ml disposable syringe', 'piece', 0.75, 500, 50, 'Injection'],
    ['IV Cannula 20G', '20G IV cannula', 'piece', 3.00, 200, 30, 'IV'],
    ['IV Cannula 22G', '22G IV cannula', 'piece', 3.00, 200, 30, 'IV'],
    ['IV Set', 'IV infusion set', 'piece', 1.50, 300, 50, 'IV'],
    ['Cotton Roll', 'Absorbent cotton roll', 'roll', 1.00, 100, 20, 'General'],
    ['Bandage', 'Cotton bandage', 'piece', 0.75, 150, 30, 'General'],
    ['Alcohol Swab', 'Alcohol prep pad', 'piece', 0.10, 1000, 100, 'General'],
    ['Gauze Pad', 'Sterile gauze pad', 'piece', 0.50, 500, 50, 'Dressing'],
    ['Face Mask', 'Medical face mask', 'piece', 0.30, 500, 100, 'PPE'],
    ['Surgical Cap', 'Disposable surgical cap', 'piece', 0.20, 300, 50, 'PPE']
];

foreach ($sampleMaterials as $material) {
    $query = "INSERT INTO materials (name, description, unit, unit_price, stock_quantity, minimum_stock, category) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssdiss', $material[0], $material[1], $material[2], $material[3], $material[4], $material[5], $material[6]);
    $stmt->execute();
}

echo "Sample materials inserted successfully.<br>";
echo "Materials system setup complete!";
