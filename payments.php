<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'get_payments') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
        
        $sql = "SELECT p.*, o.order_id, o.total_amount, o.paid_amount, 
                c.full_name as customer_name
                FROM payments p 
                LEFT JOIN orders o ON p.order_id = o.order_id
                LEFT JOIN customers c ON o.customer_id = c.customer_id
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (p.payment_id LIKE '%$search%' 
                    OR o.order_id LIKE '%$search%' 
                    OR c.full_name LIKE '%$search%'
                    OR p.reference_number LIKE '%$search%')";
        }
        
        $sql .= " ORDER BY p.payment_date DESC";
        
        $result = $conn->query($sql);
        $payments = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $payments]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_unpaid_orders') {
        $sql = "SELECT o.*, c.full_name as customer_name,
                (o.total_amount - COALESCE(o.paid_amount, 0)) as balance
                FROM orders o 
                LEFT JOIN customers c ON o.customer_id = c.customer_id 
                WHERE o.payment_status != 'paid' 
                AND o.order_status != 'cancelled'
                ORDER BY o.order_date DESC";
        
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
    
    if ($_POST['action'] == 'get_order_payments') {
        $order_id = sanitize($_POST['order_id']);
        
        $sql = "SELECT p.*, u.full_name as staff_name 
                FROM payments p 
                LEFT JOIN users u ON p.user_id = u.user_id 
                WHERE p.order_id = '$order_id' 
                ORDER BY p.payment_date DESC";
        
        $result = $conn->query($sql);
        $payments = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $payments]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'record_payment') {
        $order_id = sanitize($_POST['order_id']);
        $amount = floatval(sanitize($_POST['amount']));
        $payment_method = sanitize($_POST['payment_method']);
        $reference_number = !empty($_POST['reference_number']) ? sanitize($_POST['reference_number']) : 'NULL';
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get order details
            $order_sql = "SELECT total_amount, paid_amount, payment_status FROM orders WHERE order_id = '$order_id'";
            $order_result = $conn->query($order_sql);
            $order = $order_result->fetch_assoc();
            
            $current_paid = floatval($order['paid_amount'] ?? 0);
            $total = floatval($order['total_amount']);
            $new_paid = $current_paid + $amount;
            
            // Check if payment exceeds total
            if ($new_paid > $total + 0.01) { // Small tolerance for floating point
                throw new Exception('Payment amount exceeds order total');
            }
            
            // Insert payment record
            $payment_sql = "INSERT INTO payments (order_id, amount, payment_method, reference_number, notes, user_id) 
                           VALUES ('$order_id', '$amount', '$payment_method', $reference_number, '$notes', '$user_id')";
            
            if (!$conn->query($payment_sql)) {
                throw new Exception('Error recording payment: ' . $conn->error);
            }
            
            // Update order paid amount and status
            $payment_status = 'partial';
            if (abs($new_paid - $total) < 0.01) { // Within 1 cent tolerance
                $payment_status = 'paid';
            }
            
            $update_sql = "UPDATE orders SET 
                          paid_amount = '$new_paid',
                          payment_status = '$payment_status'
                          WHERE order_id = '$order_id'";
            
            if (!$conn->query($update_sql)) {
                throw new Exception('Error updating order: ' . $conn->error);
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Payment recorded successfully',
                'new_balance' => $total - $new_paid,
                'payment_status' => $payment_status
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'delete_payment') {
        $payment_id = sanitize($_POST['payment_id']);
        
        $conn->begin_transaction();
        
        try {
            // Get payment details
            $payment_sql = "SELECT * FROM payments WHERE payment_id = '$payment_id'";
            $payment_result = $conn->query($payment_sql);
            $payment = $payment_result->fetch_assoc();
            
            // Get order details
            $order_sql = "SELECT * FROM orders WHERE order_id = '{$payment['order_id']}'";
            $order_result = $conn->query($order_sql);
            $order = $order_result->fetch_assoc();
            
            // Calculate new paid amount
            $new_paid = floatval($order['paid_amount']) - floatval($payment['amount']);
            
            // Determine new payment status
            $payment_status = 'unpaid';
            if ($new_paid > 0) {
                $payment_status = 'partial';
            }
            
            // Delete payment
            $delete_sql = "DELETE FROM payments WHERE payment_id = '$payment_id'";
            if (!$conn->query($delete_sql)) {
                throw new Exception('Error deleting payment: ' . $conn->error);
            }
            
            // Update order
            $update_sql = "UPDATE orders SET 
                          paid_amount = '$new_paid',
                          payment_status = '$payment_status'
                          WHERE order_id = '{$payment['order_id']}'";
            
            if (!$conn->query($update_sql)) {
                throw new Exception('Error updating order: ' . $conn->error);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Payment deleted successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Get payment methods for dropdown
$payment_methods = ['cash', 'gcash', 'bank_transfer', 'credit'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">MT</div>
                <h3>Mr. Tarpz</h3>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="products.php"><i class="fas fa-box"></i> Products</a>
                <a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory</a>
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="payments.php" class="active"><i class="fas fa-money-bill"></i> Payments</a>
                <a href="expenses.php"><i class="fas fa-chart-line"></i> Expenses</a>
                <a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
            
            <div class="sidebar-footer">
                <p><i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?></p>
                <p><small><?php echo $_SESSION['role']; ?></small></p>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="content-header">
                <h1><i class="fas fa-money-bill-wave"></i> Payments Management</h1>
                <button class="btn btn-primary" onclick="showRecordPaymentModal()">
                    <i class="fas fa-plus"></i> Record Payment
                </button>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchPayment" placeholder="Search payments...">
                    <i class="fas fa-search"></i>
                </div>
                
                <button class="btn btn-secondary" onclick="loadPayments()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <!-- Payments Table -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Date</th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsList">
                        <tr>
                            <td colspan="9" class="text-center">Loading payments...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Record Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3><i class="fas fa-money-bill-wave"></i> Record Payment</h3>
                <span class="close" onclick="closePaymentModal()">&times;</span>
            </div>
            
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="order_id" name="order_id">
                    
                    <div class="form-group">
                        <label for="select_order">Select Order *</label>
                        <select id="select_order" name="select_order" required onchange="updateOrderDetails()">
                            <option value="">Choose an order</option>
                        </select>
                    </div>
                    
                    <div id="orderDetails" style="background: #f8fafc; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: none;">
                        <h4 style="margin-bottom: 10px; color: #1e293b;">Order Details</h4>
                        <p><strong>Customer:</strong> <span id="order_customer"></span></p>
                        <p><strong>Total Amount:</strong> ₱<span id="order_total">0.00</span></p>
                        <p><strong>Paid Amount:</strong> ₱<span id="order_paid">0.00</span></p>
                        <p><strong>Balance:</strong> ₱<span id="order_balance">0.00</span></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Payment Amount (₱) *</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method *</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Select method</option>
                            <?php foreach ($payment_methods as $method): ?>
                                <option value="<?php echo $method; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="reference_number">Reference Number</label>
                        <input type="text" id="reference_number" name="reference_number" placeholder="e.g., GCash ref #">
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_notes">Notes</label>
                        <textarea id="payment_notes" name="notes" rows="2" placeholder="Additional notes..."></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                <button type="button" class="btn btn-success" onclick="recordPayment()">
                    <i class="fas fa-check"></i> Record Payment
                </button>
            </div>
        </div>
    </div>
    
    <!-- Payment Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Payment Details</h3>
                <span class="close" onclick="closeDetailsModal()">&times;</span>
            </div>
            <div class="modal-body" id="paymentDetails">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            loadPayments();
            loadUnpaidOrders();
            
            // Search functionality
            $('#searchPayment').on('keyup', function() {
                loadPayments();
            });
        });
        
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
        
        // Load payments
        function loadPayments() {
            const search = $('#searchPayment').val();
            
            $.ajax({
                url: 'payments.php',
                type: 'POST',
                data: {
                    action: 'get_payments',
                    search: search
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayPayments(response.data);
                    } else {
                        showError('Failed to load payments');
                    }
                },
                error: function() {
                    showError('Error loading payments');
                }
            });
        }
        
        // Display payments in table
        function displayPayments(payments) {
            let html = '';
            
            if (payments && payments.length > 0) {
                payments.forEach(function(p) {
                    const date = new Date(p.payment_date).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    html += `
                        <tr>
                            <td>#${p.payment_id}</td>
                            <td>${date}</td>
                            <td><a href="#" onclick="viewOrderPayments(${p.order_id})">#${p.order_id}</a></td>
                            <td>${escapeHtml(p.customer_name || 'N/A')}</td>
                            <td><strong>₱${parseFloat(p.amount).toFixed(2)}</strong></td>
                            <td><span class="status-badge status-completed">${formatMethod(p.payment_method)}</span></td>
                            <td>${p.reference_number || '-'}</td>
                            <td>${escapeHtml(p.notes || '-')}</td>
                            <td class="actions">
                                <button class="btn-icon" onclick="viewPayment(${p.payment_id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon delete" onclick="deletePayment(${p.payment_id})" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html = '<tr><td colspan="9" class="empty-table">No payments found</td></tr>';
            }
            
            $('#paymentsList').html(html);
        }
        
        // Load unpaid orders for dropdown
        function loadUnpaidOrders() {
            $.ajax({
                url: 'payments.php',
                type: 'POST',
                data: {
                    action: 'get_unpaid_orders'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">Choose an order</option>';
                        response.data.forEach(function(order) {
                            options += `<option value="${order.order_id}" 
                                data-customer="${escapeHtml(order.customer_name || 'Walk-in')}"
                                data-total="${order.total_amount}"
                                data-paid="${order.paid_amount || 0}"
                                data-balance="${order.balance}">
                                #${order.order_id} - ${order.customer_name || 'Walk-in'} (₱${parseFloat(order.balance).toFixed(2)})
                            </option>`;
                        });
                        $('#select_order').html(options);
                    }
                }
            });
        }
        
        // Show record payment modal
        function showRecordPaymentModal(orderId = null) {
            loadUnpaidOrders();
            $('#paymentForm')[0].reset();
            $('#orderDetails').hide();
            
            if (orderId) {
                $('#select_order').val(orderId);
                setTimeout(updateOrderDetails, 100);
            }
            
            $('#paymentModal').show();
        }
        
        // Update order details when order is selected
        function updateOrderDetails() {
            const select = $('#select_order');
            const selected = select.find('option:selected');
            
            if (select.val()) {
                const customer = selected.data('customer');
                const total = parseFloat(selected.data('total')).toFixed(2);
                const paid = parseFloat(selected.data('paid')).toFixed(2);
                const balance = parseFloat(selected.data('balance')).toFixed(2);
                
                $('#order_customer').text(customer);
                $('#order_total').text(total);
                $('#order_paid').text(paid);
                $('#order_balance').text(balance);
                
                // Set max amount to balance
                $('#amount').attr('max', balance);
                
                $('#orderDetails').show();
            } else {
                $('#orderDetails').hide();
            }
        }
        
        // Record payment
        function recordPayment() {
            const orderId = $('#select_order').val();
            const amount = $('#amount').val();
            const method = $('#payment_method').val();
            const balance = parseFloat($('#order_balance').text());
            
            if (!orderId) {
                alert('Please select an order');
                return;
            }
            
            if (!amount || amount <= 0) {
                alert('Please enter a valid amount');
                return;
            }
            
            if (parseFloat(amount) > balance) {
                alert(`Amount cannot exceed balance of ₱${balance.toFixed(2)}`);
                return;
            }
            
            if (!method) {
                alert('Please select payment method');
                return;
            }
            
            const btn = $('#paymentModal .btn-success');
            btn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);
            
            const data = {
                action: 'record_payment',
                order_id: orderId,
                amount: amount,
                payment_method: method,
                reference_number: $('#reference_number').val(),
                notes: $('#payment_notes').val()
            };
            
            $.ajax({
                url: 'payments.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('✅ Payment recorded successfully!');
                        closePaymentModal();
                        loadPayments();
                        
                        // If called from orders page, refresh order view
                        if (typeof loadOrderDetails === 'function') {
                            loadOrderDetails(orderId);
                        }
                    } else {
                        alert('❌ ' + response.message);
                    }
                },
                error: function() {
                    alert('Error recording payment');
                },
                complete: function() {
                    btn.html('<i class="fas fa-check"></i> Record Payment').prop('disabled', false);
                }
            });
        }
        
        // View payment details
        function viewPayment(paymentId) {
            // Find payment from table
            const row = $(`button[onclick="viewPayment(${paymentId})"]`).closest('tr');
            const cells = row.find('td');
            
            const html = `
                <div style="text-align: center;">
                    <h2 style="color: #10b981; font-size: 32px; margin: 20px 0;">
                        ₱${cells.eq(4).text().replace('₱', '')}
                    </h2>
                    
                    <div style="background: #f8fafc; padding: 15px; border-radius: 10px; margin: 20px 0;">
                        <p><strong>Payment ID:</strong> ${cells.eq(0).text()}</p>
                        <p><strong>Date:</strong> ${cells.eq(1).text()}</p>
                        <p><strong>Order:</strong> ${cells.eq(2).text()}</p>
                        <p><strong>Customer:</strong> ${cells.eq(3).text()}</p>
                        <p><strong>Method:</strong> ${cells.eq(5).text()}</p>
                        <p><strong>Reference:</strong> ${cells.eq(6).text()}</p>
                        <p><strong>Notes:</strong> ${cells.eq(7).text()}</p>
                    </div>
                </div>
            `;
            
            $('#paymentDetails').html(html);
            $('#detailsModal').show();
        }
        
        // View all payments for an order
        function viewOrderPayments(orderId) {
            $.ajax({
                url: 'payments.php',
                type: 'POST',
                data: {
                    action: 'get_order_payments',
                    order_id: orderId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let html = '<h4 style="margin-bottom: 15px;">Payment History</h4>';
                        
                        response.data.forEach(function(p) {
                            const date = new Date(p.payment_date).toLocaleDateString();
                            html += `
                                <div style="background: #f8fafc; padding: 10px; border-radius: 8px; margin-bottom: 10px;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <span><strong>₱${parseFloat(p.amount).toFixed(2)}</strong></span>
                                        <span>${date}</span>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b; margin-top: 5px;">
                                        ${formatMethod(p.payment_method)} 
                                        ${p.reference_number ? '· ' + p.reference_number : ''}
                                    </div>
                                </div>
                            `;
                        });
                        
                        $('#paymentDetails').html(html);
                        $('#detailsModal').show();
                    }
                }
            });
        }
        
        // Delete payment
        function deletePayment(paymentId) {
            if (confirm('Are you sure you want to delete this payment? This action cannot be undone.')) {
                $.ajax({
                    url: 'payments.php',
                    type: 'POST',
                    data: {
                        action: 'delete_payment',
                        payment_id: paymentId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Payment deleted successfully');
                            loadPayments();
                        } else {
                            alert(response.message || 'Error deleting payment');
                        }
                    },
                    error: function() {
                        alert('Error deleting payment');
                    }
                });
            }
        }
        
        // Format payment method
        function formatMethod(method) {
            const methods = {
                'cash': '💵 Cash',
                'gcash': '📱 GCash',
                'bank_transfer': '🏦 Bank Transfer',
                'credit': '💳 Credit'
            };
            return methods[method] || method;
        }
        
        // Close modals
        function closePaymentModal() {
            $('#paymentModal').hide();
        }
        
        function closeDetailsModal() {
            $('#detailsModal').hide();
        }
        
        // Show error message
        function showError(message) {
            $('#paymentsList').html(`<tr><td colspan="9" class="error-message">${message}</td></tr>`);
        }
        
        // Escape HTML
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