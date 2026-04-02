/**
 * Orders JavaScript for Mr. Tarpz Printing Shop
 */

let itemCount = 0;
let products = [];

$(document).ready(function() {
    loadOrders();
    loadProducts();
    
    // Search and filter
    $('#searchOrder, #filterStatus').on('input change', function() {
        loadOrders();
    });
    
    // Order Form Submit
    $('#orderForm').on('submit', function(e) {
        e.preventDefault();
        saveOrder();
    });
    
    // Close modals when clicking on X
    $('.close').on('click', function() {
        closeOrderModal();
        closeDetailsModal();
    });
    
    // Close modals when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('modal')) {
            closeOrderModal();
            closeDetailsModal();
        }
    });
});

/**
 * Load products for dropdown
 */
function loadProducts() {
    $.ajax({
        url: 'products.php',
        type: 'POST',
        data: {
            action: 'get_products'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                products = response.data;
            }
        },
        error: function() {
            console.error('Error loading products');
        }
    });
}

/**
 * Load orders based on filters
 */
function loadOrders() {
    const search = $('#searchOrder').val();
    const status = $('#filterStatus').val();
    
    $('#ordersList').html('<tr><td colspan="10" class="text-center">Loading orders...</td></tr>');
    
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
                showError('Failed to load orders');
            }
        },
        error: function() {
            showError('Error loading orders');
        }
    });
}

/**
 * Display orders in table
 */
function displayOrders(orders) {
    let html = '';
    
    if (orders && orders.length > 0) {
        orders.forEach(function(order) {
            const statusClass = getStatusClass(order.order_status);
            const paymentClass = getPaymentStatusClass(order.payment_status);
            const balance = parseFloat(order.total_amount) - parseFloat(order.paid_amount || 0);
            
            html += `
                <tr>
                    <td>#${order.order_id}</td>
                    <td>${escapeHtml(order.customer_name || 'Walk-in Customer')}</td>
                    <td>${formatDate(order.order_date)}</td>
                    <td>${order.item_count || 0}</td>
                    <td>₱${parseFloat(order.total_amount || 0).toFixed(2)}</td>
                    <td>₱${parseFloat(order.paid_amount || 0).toFixed(2)}</td>
                    <td class="${balance > 0 ? 'text-danger' : 'text-success'}">₱${balance.toFixed(2)}</td>
                    <td><span class="status-badge ${statusClass}">${formatStatus(order.order_status)}</span></td>
                    <td><span class="status-badge ${paymentClass}">${formatPaymentStatus(order.payment_status)}</span></td>
                    <td class="actions">
                        <button class="btn-icon" onclick="viewOrder(${order.order_id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon" onclick="updateOrderStatus(${order.order_id})" title="Update Status">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${balance > 0 ? `
                            <button class="btn-icon" style="color: #10b981;" onclick="recordPayment(${order.order_id})" title="Record Payment">
                                <i class="fas fa-money-bill-wave"></i>
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `;
        });
    } else {
        html = '<tr><td colspan="10" class="empty-table">No orders found</td></tr>';
    }
    
    $('#ordersList').html(html);
}

/**
 * Show add order modal
 */
function showAddOrderModal() {
    $('#orderForm')[0].reset();
    $('#orderItems').empty();
    itemCount = 0;
    addOrderItem(); // Add first item by default
    updateOrderTotal();
    $('#orderModal').show();
}

/**
 * Add order item row
 */
function addOrderItem() {
    let productOptions = '<option value="">Select Product</option>';
    if (products && products.length > 0) {
        products.forEach(function(p) {
            productOptions += `<option value="${p.product_id}" data-price="${p.unit_price}">${escapeHtml(p.product_name)}</option>`;
        });
    }
    
    const itemHtml = `
        <div class="order-item" id="item_${itemCount}">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <select class="product-select" name="items[${itemCount}][product_id]" required onchange="updateItemPrice(${itemCount})">
                        ${productOptions}
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <input type="number" class="item-quantity" name="items[${itemCount}][quantity]" placeholder="Qty" min="1" value="1" required onchange="updateItemSubtotal(${itemCount})">
                </div>
                <div class="form-group" style="flex: 1;">
                    <input type="number" class="item-price" name="items[${itemCount}][unit_price]" placeholder="Price" step="0.01" min="0" required onchange="updateItemSubtotal(${itemCount})">
                </div>
                <div class="form-group" style="flex: 1;">
                    <input type="text" class="item-subtotal" value="0.00" readonly style="background: #f8f9fa;">
                </div>
                <div style="flex: 0.5;">
                    <button type="button" class="btn-icon delete" onclick="removeOrderItem(${itemCount})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <input type="text" class="item-specs" name="items[${itemCount}][specifications]" placeholder="Specifications (size, material, etc.)">
            </div>
        </div>
    `;
    
    $('#orderItems').append(itemHtml);
    itemCount++;
}

/**
 * Update item price when product is selected
 */
function updateItemPrice(index) {
    const select = $(`#item_${index} .product-select`);
    const priceInput = $(`#item_${index} .item-price`);
    const selected = select.find('option:selected');
    const price = selected.data('price');
    
    if (price) {
        priceInput.val(price);
    }
    
    updateItemSubtotal(index);
}

/**
 * Update item subtotal
 */
function updateItemSubtotal(index) {
    const quantity = $(`#item_${index} .item-quantity`).val() || 0;
    const price = $(`#item_${index} .item-price`).val() || 0;
    const subtotal = quantity * price;
    
    $(`#item_${index} .item-subtotal`).val(subtotal.toFixed(2));
    updateOrderTotal();
}

/**
 * Update order total
 */
function updateOrderTotal() {
    let total = 0;
    
    $('.item-subtotal').each(function() {
        total += parseFloat($(this).val()) || 0;
    });
    
    $('#orderTotal').text(total.toFixed(2));
}

/**
 * Remove order item
 */
function removeOrderItem(index) {
    $(`#item_${index}`).remove();
    updateOrderTotal();
}

/**
 * Save order
 */
function saveOrder() {
    // Validate items
    if ($('.order-item').length === 0) {
        alert('Please add at least one item to the order');
        return;
    }
    
    // Validate each item
    let valid = true;
    $('.order-item').each(function() {
        const product = $(this).find('.product-select').val();
        const quantity = $(this).find('.item-quantity').val();
        const price = $(this).find('.item-price').val();
        
        if (!product || !quantity || quantity < 1 || !price || price < 0) {
            valid = false;
        }
    });
    
    if (!valid) {
        alert('Please fill in all item details correctly');
        return;
    }
    
    // Prepare data
    const items = [];
    $('.order-item').each(function() {
        const index = $(this).attr('id').split('_')[1];
        items.push({
            product_id: $(`#item_${index} .product-select`).val(),
            quantity: $(`#item_${index} .item-quantity`).val(),
            unit_price: $(`#item_${index} .item-price`).val(),
            specifications: $(`#item_${index} .item-specs`).val()
        });
    });
    
    const data = {
        action: 'add_order',
        customer_id: $('#customer_id').val(),
        due_date: $('#due_date').val(),
        notes: $('#notes').val(),
        items: items
    };
    
    const btn = $('#orderModal .btn-primary');
    const originalText = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin"></i> Creating...').prop('disabled', true);
    
    $.ajax({
        url: 'orders.php',
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('✅ Order created successfully! Order #: ' + response.order_id);
                closeOrderModal();
                loadOrders();
            } else {
                alert('❌ ' + (response.message || 'Error creating order'));
            }
        },
        error: function() {
            alert('❌ Error creating order');
        },
        complete: function() {
            btn.html(originalText).prop('disabled', false);
        }
    });
}

/**
 * View order details
 */
function viewOrder(id) {
    $.ajax({
        url: 'orders.php',
        type: 'POST',
        data: {
            action: 'get_order_details',
            order_id: id
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayOrderDetails(response.data);
            } else {
                alert('❌ Error loading order details');
            }
        },
        error: function() {
            alert('❌ Error loading order details');
        }
    });
}

/**
 * Display order details
 */
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
    if (payments && payments.length > 0) {
        payments.forEach(function(payment) {
            paymentsHtml += `
                <tr>
                    <td>${formatDate(payment.payment_date)}</td>
                    <td>₱${parseFloat(payment.amount).toFixed(2)}</td>
                    <td>${formatPaymentMethod(payment.payment_method)}</td>
                    <td>${escapeHtml(payment.reference_number || '-')}</td>
                </tr>
            `;
        });
    } else {
        paymentsHtml = '<tr><td colspan="4" class="empty-table">No payments recorded</td></tr>';
    }
    
    const balance = parseFloat(order.total_amount) - parseFloat(order.paid_amount || 0);
    
    const html = `
        <div style="padding: 20px;">
            <div style="margin-bottom: 20px;">
                <h4>Order #${order.order_id}</h4>
                <p><strong>Date:</strong> ${formatDate(order.order_date)}</p>
                <p><strong>Due Date:</strong> ${order.due_date ? formatDate(order.due_date) : 'Not set'}</p>
                <p><strong>Customer:</strong> ${escapeHtml(order.customer_name || 'Walk-in Customer')}</p>
                ${order.phone ? `<p><strong>Phone:</strong> ${escapeHtml(order.phone)}</p>` : ''}
                ${order.email ? `<p><strong>Email:</strong> ${escapeHtml(order.email)}</p>` : ''}
                <p><strong>Status:</strong> <span class="status-badge ${getStatusClass(order.order_status)}">${formatStatus(order.order_status)}</span></p>
                <p><strong>Payment Status:</strong> <span class="status-badge ${getPaymentStatusClass(order.payment_status)}">${formatPaymentStatus(order.payment_status)}</span></p>
                ${order.notes ? `<p><strong>Notes:</strong> ${escapeHtml(order.notes)}</p>` : ''}
            </div>
            
            <h4>Order Items</h4>
            <table class="table" style="margin-bottom: 20px;">
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
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" style="text-align: right;">Total:</th>
                        <th>₱${parseFloat(order.total_amount).toFixed(2)}</th>
                        <th></th>
                    </tr>
                    <tr>
                        <th colspan="3" style="text-align: right;">Paid:</th>
                        <th>₱${parseFloat(order.paid_amount || 0).toFixed(2)}</th>
                        <th></th>
                    </tr>
                    <tr>
                        <th colspan="3" style="text-align: right;">Balance:</th>
                        <th class="${balance > 0 ? 'text-danger' : 'text-success'}">₱${balance.toFixed(2)}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
            
            <h4>Payment History</h4>
            <table class="table" style="margin-bottom: 20px;">
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
                ${balance > 0 ? `
                    <button class="btn btn-success" onclick="recordPayment(${order.order_id})">
                        <i class="fas fa-money-bill-wave"></i> Record Payment (₱${balance.toFixed(2)})
                    </button>
                ` : ''}
                <button class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
            </div>
        </div>
    `;
    
    $('#orderDetails').html(html);
    $('#orderDetailsModal').show();
}

/**
 * Record payment function
 */
function recordPayment(orderId) {
    // Close details modal first
    closeDetailsModal();
    
    // Try to use the payment modal from payments.php
    if (typeof window.showRecordPaymentModal === 'function') {
        window.showRecordPaymentModal(orderId);
    } else {
        // Fallback: redirect to payments page with order ID
        window.location.href = `payments.php?record=${orderId}`;
    }
}

/**
 * Update order status
 */
function updateOrderStatus(id) {
    const status = prompt('Enter new status (pending, in_progress, completed, delivered, cancelled):');
    
    if (status && ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'].includes(status)) {
        $.ajax({
            url: 'orders.php',
            type: 'POST',
            data: {
                action: 'update_order_status',
                order_id: id,
                status: status
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('✅ Order status updated');
                    loadOrders();
                } else {
                    alert('❌ ' + (response.message || 'Error updating status'));
                }
            },
            error: function() {
                alert('❌ Error updating status');
            }
        });
    } else if (status) {
        alert('❌ Invalid status. Please use: pending, in_progress, completed, delivered, cancelled');
    }
}

/**
 * Get CSS class for order status
 */
function getStatusClass(status) {
    const statusMap = {
        'pending': 'status-pending',
        'in_progress': 'status-progress',
        'completed': 'status-completed',
        'delivered': 'status-delivered',
        'cancelled': 'status-cancelled'
    };
    return statusMap[status] || 'status-pending';
}

/**
 * Get CSS class for payment status
 */
function getPaymentStatusClass(status) {
    const statusMap = {
        'unpaid': 'status-pending',
        'partial': 'status-progress',
        'paid': 'status-completed'
    };
    return statusMap[status] || 'status-pending';
}

/**
 * Format order status
 */
function formatStatus(status) {
    const statusMap = {
        'pending': 'Pending',
        'in_progress': 'In Progress',
        'completed': 'Completed',
        'delivered': 'Delivered',
        'cancelled': 'Cancelled'
    };
    return statusMap[status] || status;
}

/**
 * Format payment status
 */
function formatPaymentStatus(status) {
    const statusMap = {
        'unpaid': 'Unpaid',
        'partial': 'Partial',
        'paid': 'Paid'
    };
    return statusMap[status] || status;
}

/**
 * Format payment method
 */
function formatPaymentMethod(method) {
    const methods = {
        'cash': '💵 Cash',
        'gcash': '📱 GCash',
        'bank_transfer': '🏦 Bank Transfer',
        'credit': '💳 Credit'
    };
    return methods[method] || method;
}

/**
 * Format date
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Close order modal
 */
function closeOrderModal() {
    $('#orderModal').hide();
}

/**
 * Close details modal
 */
function closeDetailsModal() {
    $('#orderDetailsModal').hide();
}

/**
 * Show error message
 */
function showError(message) {
    $('#ordersList').html(`<tr><td colspan="10" class="error-message">${message}</td></tr>`);
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}