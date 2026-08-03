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
    $stockQuantity = intval($_POST['stock_quantity']);
    $minimumStock = intval($_POST['minimum_stock']);
    $category = $_POST['category'];

    $query = "INSERT INTO materials (name, description, unit, unit_price, stock_quantity, minimum_stock, category) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssdiss', $name, $description, $unit, $unitPrice, $stockQuantity, $minimumStock, $category);

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
    $stockQuantity = intval($_POST['stock_quantity']);
    $minimumStock = intval($_POST['minimum_stock']);
    $category = $_POST['category'];

    $query = "UPDATE materials SET name = ?, description = ?, unit = ?, unit_price = ?, stock_quantity = ?, minimum_stock = ?, category = ? 
              WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sssdissi', $name, $description, $unit, $unitPrice, $stockQuantity, $minimumStock, $category, $materialId);

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

    $query = "UPDATE materials SET is_active = 0 WHERE material_id = ?";
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
    
    $query = "SELECT * FROM materials WHERE material_id = ?";
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
// RESTOCK MATERIAL
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restock_material') {
    $materialId = intval($_POST['material_id']);
    $quantityToAdd = intval($_POST['quantity_to_add']);

    $query = "UPDATE materials SET stock_quantity = stock_quantity + ? WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $quantityToAdd, $materialId);

    if ($stmt->execute()) {
        logUserActivity($conn, $_SESSION['user_id'], 'Restocked Material', "Restocked material ID: {$materialId} with {$quantityToAdd} units");
        $message = 'Material restocked successfully!';
    } else {
        $error = 'Failed to restock material.';
    }
}

// ============================================================================
// FETCH MATERIALS
// ============================================================================
$materialsQuery = "SELECT * FROM materials WHERE is_active = 1 ORDER BY name ASC";
$materialsResult = $conn->query($materialsQuery);
$materials = $materialsResult ? $materialsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get material for edit
$editMaterial = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_material' && isset($_GET['id'])) {
    $materialId = intval($_GET['id']);
    $query = "SELECT * FROM materials WHERE material_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $materialId);
    $stmt->execute();
    $editMaterial = $stmt->get_result()->fetch_assoc();
}

// Get low stock materials
$lowStockQuery = "SELECT * FROM materials WHERE is_active = 1 AND stock_quantity <= minimum_stock ORDER BY stock_quantity ASC";
$lowStockResult = $conn->query($lowStockQuery);
$lowStockMaterials = $lowStockResult ? $lowStockResult->fetch_all(MYSQLI_ASSOC) : [];

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

            <?php if (!empty($lowStockMaterials)): ?>
            <div class="low-stock-alert">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Low Stock Alert:</strong> <?php echo count($lowStockMaterials); ?> items need restocking
            </div>
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
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($materials)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #94a3b8; padding: 40px;">
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
                                        <?php echo $material['stock_quantity']; ?>
                                        <?php if ($material['minimum_stock'] > 0): ?>
                                        <small style="color: #64748b;">(Min: <?php echo $material['minimum_stock']; ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $stockStatus = 'stock-ok';
                                        $stockText = 'In Stock';
                                        if ($material['stock_quantity'] == 0) {
                                            $stockStatus = 'stock-critical';
                                            $stockText = 'Out of Stock';
                                        } elseif ($material['stock_quantity'] <= $material['minimum_stock']) {
                                            $stockStatus = 'stock-low';
                                            $stockText = 'Low Stock';
                                        }
                                        ?>
                                        <span class="stock-badge <?php echo $stockStatus; ?>"><?php echo $stockText; ?></span>
                                    </td>
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

                <div class="form-row">
                    <div class="form-group">
                        <label for="unit_price">Unit Price *</label>
                        <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity *</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="minimum_stock">Minimum Stock Alert Level</label>
                    <input type="number" id="minimum_stock" name="minimum_stock" min="0" value="10">
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
                        document.getElementById('stock_quantity').value = data.material.stock_quantity;
                        document.getElementById('minimum_stock').value = data.material.minimum_stock;
                    }
                })
                .catch(error => {
                    alert('Error loading material data');
                });
        }
    </script>
</body>
</html>
