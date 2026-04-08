<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'get_products') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
        $category = isset($_POST['category']) ? sanitize($_POST['category']) : '';
        
        $sql = "SELECT p.*, c.category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (p.product_name LIKE '%$search%' OR p.description LIKE '%$search%')";
        }
        
        if (!empty($category)) {
            $sql .= " AND p.category_id = '$category'";
        }
        
        $sql .= " ORDER BY p.product_id DESC";
        
        $result = $conn->query($sql);
        $products = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $products]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_product') {
        $product_id = sanitize($_POST['product_id']);
        
        $sql = "SELECT p.*, c.category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                WHERE p.product_id = '$product_id'";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $product = $result->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
        }
        exit;
    }
    
    if ($_POST['action'] == 'add_product') {
        $category_id = !empty($_POST['category_id']) ? sanitize($_POST['category_id']) : 'NULL';
        $product_name = sanitize($_POST['product_name']);
        $description = sanitize($_POST['description']);
        $unit_price = sanitize($_POST['unit_price']);
        $cost_price = sanitize($_POST['cost_price']);
        $stock_quantity = sanitize($_POST['stock_quantity']);
        $reorder_level = sanitize($_POST['reorder_level']);
        $product_type = sanitize($_POST['product_type']);
        
        $sql = "INSERT INTO products (category_id, product_name, description, unit_price, cost_price, stock_quantity, reorder_level, product_type) 
                VALUES ($category_id, '$product_name', '$description', '$unit_price', '$cost_price', '$stock_quantity', '$reorder_level', '$product_type')";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Product added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding product: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'update_product') {
        $product_id = sanitize($_POST['product_id']);
        $category_id = !empty($_POST['category_id']) ? sanitize($_POST['category_id']) : 'NULL';
        $product_name = sanitize($_POST['product_name']);
        $description = sanitize($_POST['description']);
        $unit_price = sanitize($_POST['unit_price']);
        $cost_price = sanitize($_POST['cost_price']);
        $stock_quantity = sanitize($_POST['stock_quantity']);
        $reorder_level = sanitize($_POST['reorder_level']);
        $product_type = sanitize($_POST['product_type']);
        
        $sql = "UPDATE products SET 
                category_id = $category_id,
                product_name = '$product_name',
                description = '$description',
                unit_price = '$unit_price',
                cost_price = '$cost_price',
                stock_quantity = '$stock_quantity',
                reorder_level = '$reorder_level',
                product_type = '$product_type'
                WHERE product_id = '$product_id'";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating product: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'delete_product') {
        $product_id = sanitize($_POST['product_id']);
        
        // Check if product is used in orders
        $check_sql = "SELECT COUNT(*) as count FROM order_items WHERE product_id = '$product_id'";
        $check_result = $conn->query($check_sql);
        $count = $check_result->fetch_assoc()['count'];
        
        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete product because it has existing orders']);
            exit;
        }
        
        $sql = "DELETE FROM products WHERE product_id = '$product_id'";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting product: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_categories') {
        $result = $conn->query("SELECT * FROM categories ORDER BY category_name");
        $categories = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $categories]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
}

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Mr. Tarpz Printing Shop</title>
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
                <h1><i class="fas fa-box"></i> Products Management</h1>
                <button class="btn btn-primary" onclick="showAddProductModal()">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchProduct" placeholder="Search products...">
                    <i class="fas fa-search"></i>
                </div>
                
                <select id="filterCategory" class="filter-select">
                    <option value="">All Categories</option>
                    <?php if ($categories && $categories->num_rows > 0): ?>
                        <?php while($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category_id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
                
                <button class="btn btn-secondary" onclick="loadProducts()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <!-- Products Table -->
            <div class="table-container">
                <table class="table" id="productsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Selling Price</th>
                            <th>Cost Price</th>
                            <th>Stock</th>
                            <th>Reorder Level</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="productsList">
                        <tr>
                            <td colspan="9" class="text-center">Loading products...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Product</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="productForm" class="modal-body">
                <input type="hidden" id="productId" name="product_id">
                <input type="hidden" id="action" name="action" value="add_product">
                
                <div class="form-group">
                    <label for="category_id">
                        <i class="fas fa-tag"></i> Category
                    </label>
                    <select id="category_id" name="category_id">
                        <option value="">Select Category</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="product_name">
                        <i class="fas fa-box"></i> Product Name *
                    </label>
                    <input type="text" id="product_name" name="product_name" required maxlength="200">
                </div>
                
                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-align-left"></i> Description
                    </label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="unit_price">
                            <i class="fas fa-tag"></i> Selling Price (₱) *
                        </label>
                        <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cost_price">
                            <i class="fas fa-money-bill"></i> Cost Price (₱) *
                        </label>
                        <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="stock_quantity">
                            <i class="fas fa-warehouse"></i> Current Stock
                        </label>
                        <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="reorder_level">
                            <i class="fas fa-exclamation-triangle"></i> Reorder Level
                        </label>
                        <input type="number" id="reorder_level" name="reorder_level" min="0" value="10">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="product_type">
                        <i class="fas fa-cog"></i> Product Type
                    </label>
                    <select id="product_type" name="product_type">
                        <option value="finished">Finished Product</option>
                        <option value="raw_material">Raw Material</option>
                    </select>
                </div>
            </form>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveProduct()">Save Product</button>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/products.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
    </script>
</body>
</html>