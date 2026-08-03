<?php
session_start();
include 'config/database.php';
include 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize message variables
$message = '';
$error = '';

// ============================================================================
// CREATE MATERIAL
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_material') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $unit = $_POST['unit'];
    $unitPrice = floatval($_POST['unit_price']);
    $category = $_POST['category'];

    $query = "INSERT INTO lookup_materials (name, description, unit, unit_price, category, is_active) 
              VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssdsd', $name, $description, $unit, $unitPrice, $category);

    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Created Material', "Created material: {$name}");
        $message = 'Material created successfully!';
    } else {
        $error = 'Failed to create material.';
    }
}

// ============================================================================
// UPDATE MATERIAL
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_material') {
    $materialId = intval($_POST['material_id']);
    $name = $_POST['name'];
    $description = $_POST['description'];
    $unit = $_POST['unit'];
    $unitPrice = floatval($_POST['unit_price']);
    $category = $_POST['category'];

    $query = "UPDATE lookup_materials SET name = ?, description = ?, unit = ?, unit_price = ?, category = ? 
              WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssdsdi', $name, $description, $unit, $unitPrice, $category, $materialId);

    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Updated Material', "Updated material ID: {$materialId}");
        $message = 'Material updated successfully!';
    } else {
        $error = 'Failed to update material.';
    }
}

// ============================================================================
// DELETE MATERIAL
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete_material' && isset($_GET['id'])) {
    $materialId = intval($_GET['id']);

    $query = "UPDATE lookup_materials SET is_active = 0 WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $materialId);

    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Deleted Material', "Deleted material ID: {$materialId}");
        $message = 'Material deleted successfully!';
        header('Location: materials.php?message=' . urlencode($message));
        exit();
    } else {
        $error = 'Failed to delete material.';
    }
}

// ============================================================================
// GET MATERIAL (AJAX)
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_material' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $materialId = intval($_GET['id']);
    
    $query = "SELECT * FROM lookup_materials WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $materialId);
    $stmt->execute();
    $material = $stmt->get_result()->fetch_assoc();
    
    if ($material) {
        echo json_encode(['success' => true, 'material' => $material]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Material not found']);
    }
    exit();
}

// ============================================================================
// RESTOCK MATERIAL (Not applicable for lookup table - removed)
// ============================================================================


// ============================================================================
// ENSURE LOOKUP_MATERIALS TABLE EXISTS
// ============================================================================
$conn->query("CREATE TABLE IF NOT EXISTS lookup_materials (
    material_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ============================================================================
// FETCH MATERIALS FROM LOOKUP TABLE
// ============================================================================
$materialsQuery = "SELECT * FROM lookup_materials WHERE is_active = 1 ORDER BY name ASC";
$materialsResult = $conn->query($materialsQuery);
$materials = $materialsResult ? $materialsResult->fetch_all(MYSQLI_ASSOC) : [];

// ============================================================================
// INSERT SAMPLE MATERIALS IF EMPTY
// ============================================================================
if (empty($materials)) {
    $sampleMaterials = [
        ['Surgical Gloves', 'Disposable latex surgical gloves, size M', 'pair', 5.00, 'Surgical'],
        ['Surgical Masks', 'Medical grade disposable face masks', 'piece', 2.50, 'Surgical'],
        ['Sterile Gauze Pads', '4x4 inch sterile gauze pads', 'piece', 1.50, 'General'],
        ['Alcohol Swabs', '70% isopropyl alcohol swabs', 'piece', 0.50, 'General'],
        ['Syringes 5ml', 'Disposable syringes with needle, 5ml', 'piece', 3.00, 'Surgical'],
        ['IV Catheter 20G', 'Intravenous catheter, 20 gauge', 'piece', 8.00, 'Surgical'],
        ['Blood Collection Tubes', 'Vacutainer blood collection tubes, EDTA', 'piece', 1.00, 'Lab'],
        ['Thermometer Digital', 'Digital medical thermometer', 'piece', 15.00, 'Equipment'],
        ['Blood Pressure Cuff', 'Adult size blood pressure cuff', 'piece', 25.00, 'Equipment'],
        ['Bandage Roll', 'Cotton elastic bandage roll, 4 inch', 'roll', 4.00, 'General'],
        ['Antiseptic Solution', 'Povidone-iodine antiseptic solution 500ml', 'bottle', 12.00, 'General'],
        ['Sutures Absorbable', 'Absorbable surgical sutures, 3-0', 'pack', 45.00, 'Surgical']
    ];

    foreach ($sampleMaterials as $m) {
        $query = "INSERT INTO lookup_materials (name, description, unit, unit_price, category, is_active) 
                  VALUES (?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param('ssdsd', $m[0], $m[1], $m[2], $m[3], $m[4]);
            $stmt->execute();
        }
    }

    // Refresh materials list after insertion
    $materialsResult = $conn->query($materialsQuery);
    $materials = $materialsResult ? $materialsResult->fetch_all(MYSQLI_ASSOC) : [];
}

// Get material for edit
$editMaterial = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_material' && isset($_GET['id'])) {
    $materialId = intval($_GET['id']);
    $query = "SELECT * FROM lookup_materials WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $materialId);
    $stmt->execute();
    $editMaterial = $stmt->get_result()->fetch_assoc();
}

if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materials Management - HMS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .low-stock-alert {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .stock-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .stock-ok {
            background: #dcfce7;
            color: #166534;
        }
        .stock-low {
            background: #fef3c7;
            color: #92400e;
        }
        .stock-critical {
            background: #fee2e2;
            color: #991b1b;
        }
        .btn-create {
            padding: 10px 20px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-create:hover {
            background: #1d4ed8;
        }
        .form-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }
        .form-modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
        }
        .close-btn:hover {
            color: #334155;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #334155;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-family: inherit;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn-submit {
            padding: 10px 20px;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-submit:hover {
            background: #15803d;
        }
        .btn-cancel {
            padding: 10px 20px;
            background: #64748b;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-cancel:hover {
            background: #475569;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <h1>Materials Management</h1>
                </div>
                <div class="top-bar-right">
                    <div class="date-display">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo date('F j, Y'); ?></span>
                    </div>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>


            <div class="table-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Materials Inventory (<?php echo count($materials); ?>)</h2>
                    <button class="btn-create" onclick="openMaterialModal()">
                        <i class="fas fa-plus"></i> Add Material
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th>Unit Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($materials)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #94a3b8; padding: 40px;">
                                        <i class="fas fa-boxes" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                                        No materials found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($materials as $material): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($material['name']); ?></strong>
                                        <?php if ($material['description']): ?>
                                        <br><small style="color: #64748b;"><?php echo htmlspecialchars($material['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($material['category'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($material['unit']); ?></td>
                                    <td>$<?php echo number_format($material['unit_price'], 2); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-edit" onclick="editMaterial(<?php echo $material['material_id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="materials.php?action=delete_material&id=<?php echo $material['material_id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this material?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Create/Edit Material Modal -->
    <div class="form-modal" id="materialModal" style="display: none;">
        <div class="form-modal-content">
            <button class="close-btn" onclick="closeMaterialModal()">&times;</button>
            <h2 style="margin-bottom: 24px;" id="materialModalTitle">Add New Material</h2>

            <form method="POST" action="" id="materialForm">
                <input type="hidden" name="action" id="formAction" value="create_material">
                <input type="hidden" name="material_id" id="materialId">

                <div class="form-group">
                    <label for="name">Material Name *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="unit">Unit *</label>
                        <input type="text" id="unit" name="unit" required placeholder="e.g., piece, pair, roll">
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" placeholder="e.g., Surgical, General">
                    </div>
                </div>

                <div class="form-group">
                    <label for="unit_price">Unit Price *</label>
                    <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> <span id="submitBtnText">Create Material</span>
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeMaterialModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
        });

        function openMaterialModal() {
            document.getElementById('materialModal').style.display = 'flex';
            document.getElementById('materialModalTitle').textContent = 'Add New Material';
            document.getElementById('formAction').value = 'create_material';
            document.getElementById('materialId').value = '';
            document.getElementById('submitBtnText').textContent = 'Create Material';
            document.getElementById('materialForm').reset();
        }

        function closeMaterialModal() {
            document.getElementById('materialModal').style.display = 'none';
        }

        function editMaterial(materialId) {
            // Fetch material data and populate form
            fetch('materials.php?action=get_material&id=' + materialId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('materialModal').style.display = 'flex';
                        document.getElementById('materialModalTitle').textContent = 'Edit Material';
                        document.getElementById('formAction').value = 'update_material';
                        document.getElementById('materialId').value = materialId;
                        document.getElementById('submitBtnText').textContent = 'Update Material';
                        
                        document.getElementById('name').value = data.material.name;
                        document.getElementById('description').value = data.material.description || '';
                        document.getElementById('unit').value = data.material.unit;
                        document.getElementById('category').value = data.material.category || '';
                        document.getElementById('unit_price').value = data.material.unit_price;
                    }
                })
                .catch(error => {
                    alert('Error loading material data');
                });
        }
    </script>
</body>
</html>
