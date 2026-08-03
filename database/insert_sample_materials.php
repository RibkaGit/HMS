<?php
// ============================================================================
// INSERT SAMPLE MATERIALS INTO DATABASE
// ============================================================================

require_once '../config/database.php';

$sampleMaterials = [
    [
        'name' => 'Surgical Gloves',
        'description' => 'Disposable latex surgical gloves, size M',
        'unit' => 'pair',
        'unit_price' => 5.00,
        'stock_quantity' => 500,
        'minimum_stock' => 50,
        'category' => 'Surgical'
    ],
    [
        'name' => 'Surgical Masks',
        'description' => 'Medical grade disposable face masks',
        'unit' => 'piece',
        'unit_price' => 2.50,
        'stock_quantity' => 1000,
        'minimum_stock' => 100,
        'category' => 'Surgical'
    ],
    [
        'name' => 'Sterile Gauze Pads',
        'description' => '4x4 inch sterile gauze pads',
        'unit' => 'piece',
        'unit_price' => 1.50,
        'stock_quantity' => 800,
        'minimum_stock' => 80,
        'category' => 'General'
    ],
    [
        'name' => 'Alcohol Swabs',
        'description' => '70% isopropyl alcohol swabs',
        'unit' => 'piece',
        'unit_price' => 0.50,
        'stock_quantity' => 2000,
        'minimum_stock' => 200,
        'category' => 'General'
    ],
    [
        'name' => 'Syringes 5ml',
        'description' => 'Disposable syringes with needle, 5ml',
        'unit' => 'piece',
        'unit_price' => 3.00,
        'stock_quantity' => 600,
        'minimum_stock' => 60,
        'category' => 'Surgical'
    ],
    [
        'name' => 'IV Catheter 20G',
        'description' => 'Intravenous catheter, 20 gauge',
        'unit' => 'piece',
        'unit_price' => 8.00,
        'stock_quantity' => 300,
        'minimum_stock' => 30,
        'category' => 'Surgical'
    ],
    [
        'name' => 'Blood Collection Tubes',
        'description' => 'Vacutainer blood collection tubes, EDTA',
        'unit' => 'piece',
        'unit_price' => 1.00,
        'stock_quantity' => 1500,
        'minimum_stock' => 150,
        'category' => 'Lab'
    ],
    [
        'name' => 'Thermometer Digital',
        'description' => 'Digital medical thermometer',
        'unit' => 'piece',
        'unit_price' => 15.00,
        'stock_quantity' => 50,
        'minimum_stock' => 10,
        'category' => 'Equipment'
    ],
    [
        'name' => 'Blood Pressure Cuff',
        'description' => 'Adult size blood pressure cuff',
        'unit' => 'piece',
        'unit_price' => 25.00,
        'stock_quantity' => 30,
        'minimum_stock' => 5,
        'category' => 'Equipment'
    ],
    [
        'name' => 'Bandage Roll',
        'description' => 'Cotton elastic bandage roll, 4 inch',
        'unit' => 'roll',
        'unit_price' => 4.00,
        'stock_quantity' => 400,
        'minimum_stock' => 40,
        'category' => 'General'
    ],
    [
        'name' => 'Antiseptic Solution',
        'description' => 'Povidone-iodine antiseptic solution 500ml',
        'unit' => 'bottle',
        'unit_price' => 12.00,
        'stock_quantity' => 100,
        'minimum_stock' => 20,
        'category' => 'General'
    ],
    [
        'name' => 'Sutures Absorbable',
        'description' => 'Absorbable surgical sutures, 3-0',
        'unit' => 'pack',
        'unit_price' => 45.00,
        'stock_quantity' => 50,
        'minimum_stock' => 10,
        'category' => 'Surgical'
    ]
];

$query = "INSERT INTO materials (name, description, unit, unit_price, stock_quantity, minimum_stock, category, is_active) 
          VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
$stmt = $conn->prepare($query);

$insertedCount = 0;
$skippedCount = 0;

foreach ($sampleMaterials as $material) {
    // Check if material already exists
    $checkQuery = "SELECT material_id FROM materials WHERE name = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param('s', $material['name']);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    
    if ($existing) {
        $skippedCount++;
        continue;
    }
    
    $stmt->bind_param('sssdiss', 
        $material['name'], 
        $material['description'], 
        $material['unit'], 
        $material['unit_price'], 
        $material['stock_quantity'], 
        $material['minimum_stock'], 
        $material['category']
    );
    
    if ($stmt->execute()) {
        $insertedCount++;
    }
}

echo "<h2>Sample Materials Insertion Results</h2>";
echo "<p><strong>Inserted:</strong> $insertedCount materials</p>";
echo "<p><strong>Skipped (already exist):</strong> $skippedCount materials</p>";
echo "<p><a href='../materials.php'>Go to Materials Page</a></p>";
?>
