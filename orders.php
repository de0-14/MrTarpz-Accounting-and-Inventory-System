<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'get_recent_orders') {
        $sql = "SELECT o.*, 
                CASE 
                  WHEN o.customer_id IS NOT NULL THEN c.full_name 
                  WHEN o.customer_type = 'walkin' THEN 'Walk-in Customer'
                  WHEN o.customer_type = 'online' THEN 'Online Customer'
                  WHEN o.customer_type = 'regular' THEN 'Regular Customer'
                  ELSE 'Customer'
                END as customer_name
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
        
        $sql = "SELECT o.*, 
                CASE 
                  WHEN o.customer_id IS NOT NULL THEN c.full_name 
                  WHEN o.customer_type = 'walkin' THEN 'Walk-in Customer'
                  WHEN o.customer_type = 'online' THEN 'Online Customer'
                  WHEN o.customer_type = 'regular' THEN 'Regular Customer'
                  ELSE 'Customer'
                END as customer_name,
                (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.customer_id 
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (o.order_id LIKE '%$search%' OR 
                    CASE 
                      WHEN o.customer_id IS NOT NULL THEN c.full_name 
                      WHEN o.customer_type = 'walkin' THEN 'Walk-in Customer'
                      WHEN o.customer_type = 'online' THEN 'Online Customer'
                      WHEN o.customer_type = 'regular' THEN 'Regular Customer'
                      ELSE 'Customer'
                    END LIKE '%$search%')"; 
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
        $sql = "SELECT o.*, 
                CASE 
                  WHEN o.customer_id IS NOT NULL THEN c.full_name 
                  ELSE COALESCE(c.full_name, 
                    CASE o.customer_type
                      WHEN 'walkin' THEN 'Walk-in Customer'
                      WHEN 'online' THEN 'Online Customer'
                      WHEN 'regular' THEN 'Regular Customer'
                      ELSE 'Customer'
                    END)
                END as customer_name,
                COALESCE(c.phone, '') as customer_phone,
                c.email, c.address,
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
        $customer_type = sanitize($_POST['customer_type']);
        $customer_name_custom = !empty($_POST['customer_name']) ? sanitize($_POST['customer_name']) : null;
        $customer_phone_custom = !empty($_POST['customer_phone']) ? sanitize($_POST['customer_phone']) : null;
        $customer_id = (isset($_POST['customer_id']) && !empty($_POST['customer_id'])) ? "'" . sanitize($_POST['customer_id']) . "'" : 'NULL';
        $due_date = !empty($_POST['due_date']) ? "'" . sanitize($_POST['due_date']) . "'" : 'NULL';
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        
        try {
            // Insert order - fixed SQL syntax
            $sql = "INSERT INTO orders (customer_id, customer_type, due_date, notes, user_id, order_status, payment_status) 
                    VALUES ($customer_id, '$customer_type', $due_date, '$notes', '$user_id', 'pending', 'unpaid')";
            
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
                    $specifications = isset($item['specifications']) ? sanitize($item['specifications']) : '';
                    
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
    <style>
        .order-items-container {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        .order-item-row {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            position: relative;
        }
        .remove-item {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
        }
        .remove-item:hover {
            background: #dc2626;
            color: white;
        }
    </style>
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
                        <label for="customer_type">Customer Type *</label>
                        <select id="customer_type" name="customer_type" required onchange="toggleCustomerFields()">
                            <option value="walkin">Walk-in Customer</option>
                            <option value="online">Online Customer</option>
                            <option value="regular">Regular Customer</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="regular_customer_group" style="display: none;">
                        <label for="customer_id">Select Regular Customer</label>
                        <select id="customer_id" name="customer_id">
                            <option value="">Select Customer</option>
                            <?php while($customer = $customers->fetch_assoc()): ?>
                                <option value="<?php echo $customer['customer_id']; ?>">
                                    <?php echo htmlspecialchars($customer['full_name']); ?> - <?php echo $customer['phone']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="walkin_customer_group">
                        <label for="customer_name">Customer Name</label>
                        <input type="text" id="customer_name" name="customer_name" placeholder="Enter customer name">
                    </div>
                    
                    <div class="form-group" id="customer_phone_group">
                        <label for="customer_phone">Phone Number</label>
                        <input type="text" id="customer_phone" name="customer_phone" placeholder="Enter phone number">
                    </div>
                </div>
                
                <div class="form-row">
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
                <div id="orderItems" class="order-items-container">
                    <!-- Order items will be added here -->
                </div>
                
                <button type="button" class="btn btn-secondary" onclick="addOrderItem()">
                    <i class="fas fa-plus"></i> Add Item
                </button>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 10px; text-align: right;">
                    <strong>Total Amount: ₱<span id="orderTotal">0.00</span></strong>
                </div>
                
                <div class="modal-footer" style="margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitOrder()">Create Order</button>
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
    <script>
        let orderItems = [];
        let productsList = <?php 
            $products_array = [];
            $products->data_seek(0);
            while($prod = $products->fetch_assoc()) {
                $products_array[] = $prod;
            }
            echo json_encode($products_array);
        ?>;
        
        $(document).ready(function() {
            loadOrders();
            
            $('#searchOrder').on('keyup', function() {
                loadOrders();
            });
            
            $('#filterStatus').on('change', function() {
                loadOrders();
            });
        });
        
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
        
        function toggleCustomerFields() {
            const type = $('#customer_type').val();
            if (type === 'regular') {
                $('#regular_customer_group').show();
                $('#walkin_customer_group').hide();
                $('#customer_phone_group').hide();
                $('#customer_name').prop('required', false);
                $('#customer_phone').prop('required', false);
            } else {
                $('#regular_customer_group').hide();
                $('#walkin_customer_group').show();
                $('#customer_phone_group').show();
                if (type === 'walkin') {
                    $('#customer_name').prop('required', true);
                } else {
                    $('#customer_name').prop('required', false);
                }
            }
        }
        
        function loadOrders() {
            const search = $('#searchOrder').val();
            const status = $('#filterStatus').val();
            
            $.ajax({
                url: 'orders.php',
                type: 'POST',
                data: {
                    action: 'get_orders',
                    search: search,
                    status: status
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayOrders(response.data);
                    } else {
                        $('#ordersList').html('<tr><td colspan="10" class="text-center">Error loading orders</td></tr>');
                    }
                },
                error: function() {
                    $('#ordersList').html('<tr><td colspan="10" class="text-center">Error loading orders</td></tr>');
                }
            });
        }
        
        function displayOrders(orders) {
            let html = '';
            
            if (orders && orders.length > 0) {
                orders.forEach(function(order) {
                    const date = new Date(order.order_date).toLocaleDateString();
                    const balance = parseFloat(order.total_amount) - parseFloat(order.paid_amount || 0);
                    
                    html += `
                        <tr>
                            <td>#${order.order_id}</td>
                            <td>${escapeHtml(order.customer_name)}</td>
                            <td>${date}</td>
                            <td>${order.item_count || 0}</td>
                            <td>₱${parseFloat(order.total_amount).toFixed(2)}</td>
                            <td>₱${parseFloat(order.paid_amount || 0).toFixed(2)}</td>
                            <td>₱${balance.toFixed(2)}</td>
                            <td><span class="status-badge ${getStatusClass(order.order_status)}">${order.order_status}</span></td>
                            <td><span class="status-badge ${getPaymentStatusClass(order.payment_status)}">${order.payment_status || 'unpaid'}</span></td>
                            <td class="actions">
                                <button class="btn-icon" onclick="viewOrder(${order.order_id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon" onclick="updateOrderStatus(${order.order_id})" title="Update Status">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon" onclick="recordPayment(${order.order_id})" title="Record Payment">
                                    <i class="fas fa-money-bill"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html = '<tr><td colspan="10" class="text-center">No orders found</td></tr>';
            }
            
            $('#ordersList').html(html);
        }
        
        function showAddOrderModal() {
            orderItems = [];
            $('#orderForm')[0].reset();
            $('#orderItems').empty();
            updateOrderTotal();
            toggleCustomerFields();
            $('#orderModal').show();
        }
        
        function closeOrderModal() {
            $('#orderModal').hide();
        }
        
        function addOrderItem() {
            const itemHtml = `
                <div class="order-item-row" id="item-${orderItems.length}">
                    <button type="button" class="remove-item" onclick="removeOrderItem(${orderItems.length})">×</button>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Product *</label>
                            <select class="product-select" data-index="${orderItems.length}" onchange="updateItemPrice(${orderItems.length})">
                                <option value="">Select Product</option>
                                ${productsList.map(p => `<option value="${p.product_id}" data-price="${p.unit_price}">${p.product_name} - ₱${parseFloat(p.unit_price).toFixed(2)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" class="quantity-input" data-index="${orderItems.length}" min="1" value="1" onchange="updateItemSubtotal(${orderItems.length})">
                        </div>
                        <div class="form-group">
                            <label>Unit Price</label>
                            <input type="number" class="price-input" data-index="${orderItems.length}" step="0.01" readonly>
                        </div>
                        <div class="form-group">
                            <label>Subtotal</label>
                            <input type="number" class="subtotal-input" data-index="${orderItems.length}" step="0.01" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Specifications</label>
                        <textarea class="specs-input" data-index="${orderItems.length}" rows="2" placeholder="Size, material, design details, etc."></textarea>
                    </div>
                </div>
            `;
            $('#orderItems').append(itemHtml);
            orderItems.push({
                product_id: '',
                quantity: 1,
                unit_price: 0,
                subtotal: 0,
                specifications: ''
            });
        }
        
        function removeOrderItem(index) {
            $(`#item-${index}`).remove();
            orderItems.splice(index, 1);
            updateOrderTotal();
        }
        
        function updateItemPrice(index) {
            const select = $(`.product-select[data-index="${index}"]`);
            const selectedOption = select.find('option:selected');
            const price = selectedOption.data('price') || 0;
            const productId = select.val();
            
            $(`.price-input[data-index="${index}"]`).val(price);
            orderItems[index].product_id = productId;
            orderItems[index].unit_price = price;
            updateItemSubtotal(index);
        }
        
        function updateItemSubtotal(index) {
            const quantity = parseInt($(`.quantity-input[data-index="${index}"]`).val()) || 0;
            const price = parseFloat($(`.price-input[data-index="${index}"]`).val()) || 0;
            const subtotal = quantity * price;
            
            $(`.subtotal-input[data-index="${index}"]`).val(subtotal.toFixed(2));
            orderItems[index].quantity = quantity;
            orderItems[index].subtotal = subtotal;
            updateOrderTotal();
        }
        
        function updateOrderTotal() {
            let total = 0;
            orderItems.forEach(function(item) {
                total += item.subtotal || 0;
            });
            $('#orderTotal').text(total.toFixed(2));
        }
        
        function submitOrder() {
            // Collect all order items
            $('.order-item-row').each(function(index) {
                const productSelect = $(this).find('.product-select');
                const quantity = $(this).find('.quantity-input').val();
                const price = $(this).find('.price-input').val();
                const specs = $(this).find('.specs-input').val();
                
                orderItems[index] = {
                    product_id: productSelect.val(),
                    quantity: parseInt(quantity),
                    unit_price: parseFloat(price),
                    subtotal: parseInt(quantity) * parseFloat(price),
                    specifications: specs
                };
            });
            
            // Validate at least one item
            const validItems = orderItems.filter(item => item.product_id && item.quantity > 0);
            if (validItems.length === 0) {
                alert('Please add at least one product to the order');
                return;
            }
            
            const formData = {
                action: 'add_order',
                customer_type: $('#customer_type').val(),
                customer_name: $('#customer_name').val(),
                customer_phone: $('#customer_phone').val(),
                customer_id: $('#customer_id').val(),
                due_date: $('#due_date').val(),
                notes: $('#notes').val(),
                items: validItems
            };
            
            const btn = $('#orderModal .btn-primary');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating...');
            
            $.ajax({
                url: 'orders.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Order #' + response.order_id + ' created successfully!');
                        closeOrderModal();
                        loadOrders();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error creating order: ' + error);
                    console.error(xhr.responseText);
                },
                complete: function() {
                    btn.prop('disabled', false).html('Create Order');
                }
            });
        }
        
        function viewOrder(orderId) {
            $.ajax({
                url: 'orders.php',
                type: 'POST',
                data: {
                    action: 'get_order_details',
                    order_id: orderId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayOrderDetails(response.data);
                        $('#orderDetailsModal').show();
                    } else {
                        alert('Error loading order details');
                    }
                }
            });
        }
        
        function displayOrderDetails(data) {
            const order = data.order;
            const items = data.items;
            const payments = data.payments;
            
            let itemsHtml = '';
            items.forEach(function(item) {
                itemsHtml += `
                    <tr>
                        <td>${escapeHtml(item.product_name)}</td>
                        <td>${item.quantity}</td>
                        <td>₱${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td>₱${parseFloat(item.subtotal).toFixed(2)}</td>
                        <td>${escapeHtml(item.specifications || '-')}</td>
                    </tr>
                `;
            });
            
            let paymentsHtml = '';
            if (payments.length > 0) {
                payments.forEach(function(payment) {
                    const date = new Date(payment.payment_date).toLocaleString();
                    paymentsHtml += `
                        <tr>
                            <td>${date}</td>
                            <td>₱${parseFloat(payment.amount).toFixed(2)}</td>
                            <td>${payment.payment_method}</td>
                            <td>${payment.reference_number || '-'}</td>
                        </tr>
                    `;
                });
            } else {
                paymentsHtml = '<tr><td colspan="4" class="text-center">No payments recorded</td></tr>';
            }
            
            const balance = parseFloat(order.total_amount) - parseFloat(order.paid_amount || 0);
            
            const html = `
                <div style="padding: 20px;">
                    <div style="background: #f8fafc; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                        <h4>Order Information</h4>
                        <p><strong>Order #:</strong> ${order.order_id}</p>
                        <p><strong>Customer:</strong> ${escapeHtml(order.customer_name)}</p>
                        <p><strong>Date:</strong> ${new Date(order.order_date).toLocaleString()}</p>
                        <p><strong>Due Date:</strong> ${order.due_date || 'Not set'}</p>
                        <p><strong>Status:</strong> <span class="status-badge ${getStatusClass(order.order_status)}">${order.order_status}</span></p>
                        <p><strong>Payment Status:</strong> <span class="status-badge ${getPaymentStatusClass(order.payment_status)}">${order.payment_status || 'unpaid'}</span></p>
                        <p><strong>Notes:</strong> ${escapeHtml(order.notes || '-')}</p>
                        <p><strong>Staff:</strong> ${escapeHtml(order.staff_name)}</p>
                    </div>
                    
                    <h4>Order Items</h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                                <th>Specifications</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                            <tr style="font-weight: bold; background: #f1f5f9;">
                                <td colspan="3" style="text-align: right;">Total:</td>
                                <td>₱${parseFloat(order.total_amount).toFixed(2)}</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: right;">Paid:</td>
                                <td>₱${parseFloat(order.paid_amount || 0).toFixed(2)}</td>
                                <td></td>
                            </tr>
                            <tr style="font-weight: bold;">
                                <td colspan="3" style="text-align: right;">Balance:</td>
                                <td>₱${balance.toFixed(2)}</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>Payment History</h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${paymentsHtml}
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button class="btn btn-primary" onclick="recordPayment(${order.order_id})">Record Payment</button>
                        <button class="btn btn-secondary" onclick="updateOrderStatus(${order.order_id})">Update Status</button>
                    </div>
                </div>
            `;
            
            $('#orderDetails').html(html);
        }
        
        function updateOrderStatus(orderId) {
            const newStatus = prompt('Enter new status (pending, in_progress, completed, delivered, cancelled):');
            if (newStatus && ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'].includes(newStatus)) {
                $.ajax({
                    url: 'orders.php',
                    type: 'POST',
                    data: {
                        action: 'update_order_status',
                        order_id: orderId,
                        status: newStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Order status updated!');
                            loadOrders();
                            if ($('#orderDetailsModal').is(':visible')) {
                                viewOrder(orderId);
                            }
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                });
            } else if (newStatus) {
                alert('Invalid status');
            }
        }
        
        function recordPayment(orderId) {
            closeDetailsModal();
            if (typeof showRecordPaymentModal === 'function') {
                showRecordPaymentModal(orderId);
            } else {
                window.location.href = 'payments.php';
            }
        }
        
        function closeDetailsModal() {
            $('#orderDetailsModal').hide();
        }
        
        function getStatusClass(status) {
            const classes = {
                'pending': 'status-pending',
                'in_progress': 'status-progress',
                'completed': 'status-completed',
                'delivered': 'status-delivered',
                'cancelled': 'status-cancelled'
            };
            return classes[status] || 'status-pending';
        }
        
        function getPaymentStatusClass(status) {
            const classes = {
                'paid': 'status-completed',
                'unpaid': 'status-pending',
                'partial': 'status-progress'
            };
            return classes[status] || 'status-pending';
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if ($(event.target).hasClass('modal')) {
                $('.modal').hide();
            }
        }
        
        // Close modals with Escape key
        $(document).keydown(function(e) {
            if (e.key === 'Escape') {
                $('.modal').hide();
            }
        });
    </script>
</body>
</html>