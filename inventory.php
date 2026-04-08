<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'get_low_stock') {
        $sql = "SELECT * FROM products WHERE stock_quantity <= reorder_level ORDER BY stock_quantity ASC LIMIT 5";
        
        $result = $conn->query($sql);
        $items = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $items]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_inventory') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
        $type = isset($_POST['type']) ? sanitize($_POST['type']) : '';
        
        $sql = "SELECT p.*, c.category_name,
                (SELECT COUNT(*) FROM inventory_transactions WHERE product_id = p.product_id) as transaction_count
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (p.product_name LIKE '%$search%' OR p.description LIKE '%$search%')";
        }
        
        if (!empty($type)) {
            $sql .= " AND p.product_type = '$type'";
        }
        
        $sql .= " ORDER BY p.stock_quantity ASC";
        
        $result = $conn->query($sql);
        $items = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $items]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'add_stock') {
        $product_id = sanitize($_POST['product_id']);
        $quantity = sanitize($_POST['quantity']);
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        
        try {
            // Get current stock
            $stock_sql = "SELECT stock_quantity FROM products WHERE product_id = '$product_id'";
            $stock_result = $conn->query($stock_sql);
            $current_stock = $stock_result->fetch_assoc()['stock_quantity'];
            
            // Update product stock
            $new_stock = $current_stock + $quantity;
            $update_sql = "UPDATE products SET stock_quantity = '$new_stock' WHERE product_id = '$product_id'";
            
            if (!$conn->query($update_sql)) {
                throw new Exception('Error updating stock: ' . $conn->error);
            }
            
            // Record transaction
            $trans_sql = "INSERT INTO inventory_transactions (product_id, transaction_type, quantity, notes, user_id) 
                         VALUES ('$product_id', 'in', '$quantity', '$notes', '$user_id')";
            
            if (!$conn->query($trans_sql)) {
                throw new Exception('Error recording transaction: ' . $conn->error);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Stock added successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'remove_stock') {
        $product_id = sanitize($_POST['product_id']);
        $quantity = sanitize($_POST['quantity']);
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        
        try {
            // Get current stock
            $stock_sql = "SELECT stock_quantity FROM products WHERE product_id = '$product_id'";
            $stock_result = $conn->query($stock_sql);
            $current_stock = $stock_result->fetch_assoc()['stock_quantity'];
            
            // Check if enough stock
            if ($current_stock < $quantity) {
                throw new Exception('Insufficient stock. Available: ' . $current_stock);
            }
            
            // Update product stock
            $new_stock = $current_stock - $quantity;
            $update_sql = "UPDATE products SET stock_quantity = '$new_stock' WHERE product_id = '$product_id'";
            
            if (!$conn->query($update_sql)) {
                throw new Exception('Error updating stock: ' . $conn->error);
            }
            
            // Record transaction
            $trans_sql = "INSERT INTO inventory_transactions (product_id, transaction_type, quantity, notes, user_id) 
                         VALUES ('$product_id', 'out', '$quantity', '$notes', '$user_id')";
            
            if (!$conn->query($trans_sql)) {
                throw new Exception('Error recording transaction: ' . $conn->error);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Stock removed successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_transactions') {
        $product_id = isset($_POST['product_id']) ? sanitize($_POST['product_id']) : '';
        
        $sql = "SELECT t.*, p.product_name, u.full_name as user_name 
                FROM inventory_transactions t 
                LEFT JOIN products p ON t.product_id = p.product_id 
                LEFT JOIN users u ON t.user_id = u.user_id";
        
        if (!empty($product_id)) {
            $sql .= " WHERE t.product_id = '$product_id'";
        }
        
        $sql .= " ORDER BY t.transaction_date DESC LIMIT 50";
        
        $result = $conn->query($sql);
        $transactions = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $transactions[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $transactions]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
}

// Check for product ID in URL
$selected_product_id = isset($_GET['add_stock']) ? intval($_GET['add_stock']) : 0;

// Get products for dropdown
$products = $conn->query("SELECT product_id, product_name, stock_quantity FROM products ORDER BY product_name");

// Get categories for new product form
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="content-header">
                <h1><i class="fas fa-warehouse"></i> Inventory Management</h1>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="showAddStockModal()">
                        <i class="fas fa-plus"></i> Add Stock
                    </button>
                    <button class="btn btn-warning" onclick="showRemoveStockModal()">
                        <i class="fas fa-minus"></i> Remove Stock
                    </button>
                    <button class="btn btn-success" onclick="showNewProductModal()">
                        <i class="fas fa-box"></i> New Product
                    </button>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchInventory" placeholder="Search products...">
                    <i class="fas fa-search"></i>
                </div>
                
                <select id="filterType" class="filter-select">
                    <option value="">All Types</option>
                    <option value="finished">Finished Products</option>
                    <option value="raw_material">Raw Materials</option>
                </select>
                
                <button class="btn btn-secondary" onclick="loadInventory()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <!-- Inventory Table -->
            <div class="table-container" style="margin-bottom: 20px;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Current Stock</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Stock Value</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryList">
                        <tr>
                            <td colspan="8" class="text-center">Loading inventory...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Recent Transactions -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Transactions</h3>
                    <button class="btn-link" onclick="loadTransactions()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Quantity</th>
                                <th>Notes</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsList">
                            <tr>
                                <td colspan="6" class="text-center">Loading transactions...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Stock Modal -->
    <div id="addStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Add Stock</h3>
                <span class="close" onclick="closeAddStockModal()">&times;</span>
            </div>
            <form id="addStockForm" class="modal-body">
                <div class="form-group">
                    <label for="add_product_id">Select Product *</label>
                    <select id="add_product_id" name="product_id" required>
                        <option value="">Choose Product</option>
                        <?php 
                        $products->data_seek(0);
                        while($product = $products->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $product['product_id']; ?>" <?php echo $product['product_id'] == $selected_product_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['product_name']); ?> 
                                (Current: <?php echo $product['stock_quantity']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="add_quantity">Quantity to Add *</label>
                    <input type="number" id="add_quantity" name="quantity" min="1" value="1" required>
                </div>
                
                <div class="form-group">
                    <label for="add_notes">Notes</label>
                    <textarea id="add_notes" name="notes" rows="3" placeholder="e.g., Received from supplier, Production completed, etc."></textarea>
                </div>
            </form>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddStockModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addStock()">Add Stock</button>
            </div>
        </div>
    </div>
    
    <!-- Remove Stock Modal -->
    <div id="removeStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-minus-circle"></i> Remove Stock</h3>
                <span class="close" onclick="closeRemoveStockModal()">&times;</span>
            </div>
            <form id="removeStockForm" class="modal-body">
                <div class="form-group">
                    <label for="remove_product_id">Select Product *</label>
                    <select id="remove_product_id" name="product_id" required onchange="updateAvailableStock()">
                        <option value="">Choose Product</option>
                        <?php 
                        $products->data_seek(0);
                        while($product = $products->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $product['product_id']; ?>" data-stock="<?php echo $product['stock_quantity']; ?>">
                                <?php echo htmlspecialchars($product['product_name']); ?> 
                                (Available: <?php echo $product['stock_quantity']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="remove_quantity">Quantity to Remove *</label>
                    <input type="number" id="remove_quantity" name="quantity" min="1" value="1" required oninput="updateAvailableStock()">
                    <small id="stockWarning" style="color: #e74c3c; display: none;">Insufficient stock!</small>
                </div>
                
                <div class="form-group">
                    <label for="remove_notes">Notes</label>
                    <textarea id="remove_notes" name="notes" rows="3" placeholder="e.g., Used for order #123, Damaged, etc."></textarea>
                </div>
            </form>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRemoveStockModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="removeStock()">Remove Stock</button>
            </div>
        </div>
    </div>
    
    <!-- New Product Modal -->
    <div id="newProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-box"></i> Add New Product</h3>
                <span class="close" onclick="closeNewProductModal()">&times;</span>
            </div>
            <form id="newProductForm" class="modal-body">
                <div class="form-group">
                    <label for="new_category_id">Category</label>
                    <select id="new_category_id" name="category_id">
                        <option value="">Select Category</option>
                        <?php 
                        $categories->data_seek(0);
                        while($cat = $categories->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $cat['category_id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="new_product_name">Product Name *</label>
                    <input type="text" id="new_product_name" name="product_name" required maxlength="200">
                </div>
                
                <div class="form-group">
                    <label for="new_description">Description</label>
                    <textarea id="new_description" name="description" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_unit_price">Selling Price (₱) *</label>
                        <input type="number" id="new_unit_price" name="unit_price" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_cost_price">Cost Price (₱) *</label>
                        <input type="number" id="new_cost_price" name="cost_price" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="new_stock_quantity">Initial Stock</label>
                        <input type="number" id="new_stock_quantity" name="stock_quantity" min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_reorder_level">Reorder Level</label>
                        <input type="number" id="new_reorder_level" name="reorder_level" min="0" value="10">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_product_type">Product Type</label>
                    <select id="new_product_type" name="product_type">
                        <option value="finished">Finished Product</option>
                        <option value="raw_material">Raw Material</option>
                    </select>
                </div>
            </form>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeNewProductModal()">Cancel</button>
                <button type="button" class="btn btn-success" onclick="saveNewProduct()">Save Product</button>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/inventory.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
        
        // Auto-open add stock modal if product ID is in URL
        <?php if ($selected_product_id > 0): ?>
        $(document).ready(function() {
            showAddStockModal();
        });
        <?php endif; ?>
    </script>
</body>
</html>