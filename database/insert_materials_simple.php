<?php
require_once '../config/database.php';

// Simple direct insert without duplicate checking
$materials = [
    ['Surgical Gloves', 'Disposable latex surgical gloves, size M', 'pair', 5.00, 500, 50, 'Surgical'],
    ['Surgical Masks', 'Medical grade disposable face masks', 'piece', 2.50, 1000, 100, 'Surgical'],
    ['Sterile Gauze Pads', '4x4 inch sterile gauze pads', 'piece', 1.50, 800, 80, 'General'],
    ['Alcohol Swabs', '70% isopropyl alcohol swabs', 'piece', 0.50, 2000, 200, 'General'],
    ['Syringes 5ml', 'Disposable syringes with needle, 5ml', 'piece', 3.00, 600, 60, 'Surgical'],
    ['IV Catheter 20G', 'Intravenous catheter, 20 gauge', 'piece', 8.00, 300, 30, 'Surgical'],
    ['Blood Collection Tubes', 'Vacutainer blood collection tubes, EDTA', 'piece', 1.00, 1500, 150, 'Lab'],
    ['Thermometer Digital', 'Digital medical thermometer', 'piece', 15.00, 50, 10, 'Equipment'],
    ['Blood Pressure Cuff', 'Adult size blood pressure cuff', 'piece', 25.00, 30, 5, 'Equipment'],
    ['Bandage Roll', 'Cotton elastic bandage roll, 4 inch', 'roll', 4.00, 400, 40, 'General'],
    ['Antiseptic Solution', 'Povidone-iodine antiseptic solution 500ml', 'bottle', 12.00, 100, 20, 'General'],
    ['Sutures Absorbable', 'Absorbable surgical sutures, 3-0', 'pack', 45.00, 50, 10, 'Surgical']
];

$count = 0;
foreach ($materials as $m) {
    $query = "INSERT INTO materials (name, description, unit, unit_price, stock_quantity, minimum_stock, category, is_active) 
              VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssdiss', $m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
    if ($stmt->execute()) {
        $count++;
    }
}

echo "Inserted $count materials. <a href='../materials.php'>View Materials</a>";
?>
