<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'get_recent_orders') {
        $sql = "SELECT o.*, c.full_name as customer_name 
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.customer_id 
                ORDER BY o.order_date DESC 
                LIMIT 5";
        
        $result = $conn->query($sql);
        $orders = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $orders]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_orders') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
        $status = isset($_POST['status']) ? sanitize($_POST['status']) : '';
        
        $sql = "SELECT o.*, c.full_name as customer_name,
                (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.customer_id 
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (o.order_id LIKE '%$search%' OR c.full_name LIKE '%$search%')";
        }
        
        if (!empty($status)) {
            $sql .= " AND o.order_status = '$status'";
        }
        
        $sql .= " ORDER BY o.order_date DESC";
        
        $result = $conn->query($sql);
        $orders = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $orders]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_order_details') {
        $order_id = sanitize($_POST['order_id']);
        
        // Get order info
        $sql = "SELECT o.*, c.full_name as customer_name, c.phone, c.email, c.address,
                u.full_name as staff_name
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.customer_id 
                LEFT JOIN users u ON o.user_id = u.user_id
                WHERE o.order_id = '$order_id'";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $order = $result->fetch_assoc();
            
            // Get order items
            $items_sql = "SELECT oi.*, p.product_name 
                          FROM order_items oi 
                          LEFT JOIN products p ON oi.product_id = p.product_id 
                          WHERE oi.order_id = '$order_id'";
            $items_result = $conn->query($items_sql);
            $items = [];
            
            while ($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }
            
            // Get payments
            $payments_sql = "SELECT * FROM payments WHERE order_id = '$order_id' ORDER BY payment_date DESC";
            $payments_result = $conn->query($payments_sql);
            $payments = [];
            
            while ($payment = $payments_result->fetch_assoc()) {
                $payments[] = $payment;
            }
            
            echo json_encode([
                'success' => true, 
                'data' => [
                    'order' => $order,
                    'items' => $items,
                    'payments' => $payments
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
        exit;
    }
    
    if ($_POST['action'] == 'add_order') {
        $customer_id = isset($_POST['customer_id']) && !empty($_POST['customer_id']) ? sanitize($_POST['customer_id']) : 'NULL';
        $due_date = !empty($_POST['due_date']) ? "'" . sanitize($_POST['due_date']) . "'" : 'NULL';
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        
        try {
            // Insert order
            $sql = "INSERT INTO orders (customer_id, due_date, notes, user_id, order_status, payment_status) 
                    VALUES ($customer_id, $due_date, '$notes', '$user_id', 'pending', 'unpaid')";
            
            if (!$conn->query($sql)) {
                throw new Exception('Error creating order: ' . $conn->error);
            }
            
            $order_id = $conn->insert_id;
            $total_amount = 0;
            
            // Insert order items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    $product_id = sanitize($item['product_id']);
                    $quantity = sanitize($item['quantity']);
                    $unit_price = sanitize($item['unit_price']);
                    $subtotal = $quantity * $unit_price;
                    $specifications = sanitize($item['specifications']);
                    
                    $item_sql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, specifications) 
                                VALUES ('$order_id', '$product_id', '$quantity', '$unit_price', '$subtotal', '$specifications')";
                    
                    if (!$conn->query($item_sql)) {
                        throw new Exception('Error adding order item: ' . $conn->error);
                    }
                    
                    $total_amount += $subtotal;
                }
            }
            
            // Update order total
            $update_sql = "UPDATE orders SET total_amount = '$total_amount' WHERE order_id = '$order_id'";
            if (!$conn->query($update_sql)) {
                throw new Exception('Error updating order total: ' . $conn->error);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Order created successfully', 'order_id' => $order_id]);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'update_order_status') {
        $order_id = sanitize($_POST['order_id']);
        $status = sanitize($_POST['status']);
        
        $sql = "UPDATE orders SET order_status = '$status' WHERE order_id = '$order_id'";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Order status updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $conn->error]);
        }
        exit;
    }
}

// Get customers for dropdown
$customers = $conn->query("SELECT customer_id, full_name, phone FROM customers ORDER BY full_name");

// Get products for order items
$products = $conn->query("SELECT product_id, product_name, unit_price FROM products WHERE stock_quantity > 0 ORDER BY product_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Mr. Tarpz Printing Shop</title>
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
                <h1><i class="fas fa-shopping-cart"></i> Orders Management</h1>
                <button class="btn btn-primary" onclick="showAddOrderModal()">
                    <i class="fas fa-plus"></i> New Order
                </button>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchOrder" placeholder="Search order # or customer...">
                    <i class="fas fa-search"></i>
                </div>
                
                <select id="filterStatus" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                
                <button class="btn btn-secondary" onclick="loadOrders()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <!-- Orders Table -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersList">
                        <tr>
                            <td colspan="10" style="text-align: center;">Loading orders...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Order Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3>Create New Order</h3>
                <span class="close" onclick="closeOrderModal()">&times;</span>
            </div>
            <form id="orderForm" style="padding: 20px;">
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer_id">Customer</label>
                        <select id="customer_id" name="customer_id">
                            <option value="">Walk-in Customer</option>
                            <?php if ($customers && $customers->num_rows > 0): ?>
                                <?php while($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['customer_id']; ?>">
                                        <?php echo htmlspecialchars($customer['full_name']); ?> 
                                        (<?php echo htmlspecialchars($customer['phone']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="2" style="resize: none;"></textarea>
                </div>
                
                <h4>Order Items</h4>
                <div id="orderItems">
                    <!-- Order items will be added here -->
                </div>
                
                <button type="button" class="btn btn-secondary" onclick="addOrderItem()">
                    <i class="fas fa-plus"></i> Add Item
                </button>
                
                <div style="margin-bottom: 10px; text-align: right;">
                    <strong>Total Amount: ₱<span id="orderTotal">0.00</span></strong>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Order</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3>Order Details</h3>
                <span class="close" onclick="closeDetailsModal()">&times;</span>
            </div>
            <div id="orderDetails"></div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/orders.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
    </script>
</body>
</html>