/**
 * Dashboard JavaScript for Mr. Tarpz Printing Shop
 */

$(document).ready(function() {
    console.log('Dashboard initialized');
    loadRecentOrders();
    loadLowStockItems();
    
    // Initialize chart if we have data
    if (typeof monthlyData !== 'undefined' && monthlyData.length > 0) {
        initSalesChart();
    }
});

/**
 * Load recent orders via AJAX
 */
function loadRecentOrders() {
    $.ajax({
        url: 'orders.php',
        type: 'POST',
        data: {
            action: 'get_recent_orders'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayRecentOrders(response.data);
            } else {
                showError('#recent-orders', 'Failed to load orders');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('#recent-orders', 'Error loading orders');
        }
    });
}

/**
 * Display recent orders
 */
function displayRecentOrders(orders) {
    let html = '';
    
    if (orders && orders.length > 0) {
        orders.forEach(function(order) {
            const statusClass = getStatusClass(order.order_status || 'pending');
            const customerName = order.customer_name || 'Walk-in Customer';
            const totalAmount = parseFloat(order.total_amount || 0).toFixed(2);
            
            html += `
                <tr>
                    <td>#${order.order_id}</td>
                    <td>${escapeHtml(customerName)}</td>
                    <td>₱${totalAmount}</td>
                    <td><span class="status-badge ${statusClass}">${formatStatus(order.order_status || 'pending')}</span></td>
                </tr>
            `;
        });
    } else {
        html = '<tr><td colspan="4" class="empty-table">No recent orders found</td></tr>';
    }
    
    $('#recent-orders').html(html);
}

/**
 * Load low stock items via AJAX
 */
function loadLowStockItems() {
    $.ajax({
        url: 'inventory.php',
        type: 'POST',
        data: {
            action: 'get_low_stock'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayLowStockItems(response.data);
            } else {
                showError('#low-stock-items', 'Failed to load low stock items');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('#low-stock-items', 'Error loading inventory');
        }
    });
}

/**
 * Display low stock items
 */
function displayLowStockItems(items) {
    let html = '';
    
    if (items && items.length > 0) {
        items.forEach(function(item) {
            const stockClass = parseInt(item.stock_quantity) <= parseInt(item.reorder_level) ? 'low-stock' : '';
            
            html += `
                <tr>
                    <td>${escapeHtml(item.product_name)}</td>
                    <td class="${stockClass}">${item.stock_quantity}</td>
                    <td>${item.reorder_level}</td>
                </tr>
            `;
        });
    } else {
        html = '<tr><td colspan="3" class="empty-table">No low stock items found</td></tr>';
    }
    
    $('#low-stock-items').html(html);
}

/**
 * Get CSS class for status
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
 * Format status for display
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
 * Show error message
 */
function showError(selector, message) {
    const cols = $(selector).closest('table').find('thead th').length;
    $(selector).html(`<tr><td colspan="${cols}" class="error-message">${message}</td></tr>`);
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Initialize sales chart
 */
function initSalesChart() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    // Prepare data
    const months = monthlyData.map(item => {
        const [year, month] = item.month.split('-');
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${monthNames[parseInt(month) - 1]} ${year}`;
    }).reverse();
    
    const sales = monthlyData.map(item => parseFloat(item.total_sales)).reverse();
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Monthly Sales',
                data: sales,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                borderWidth: 2,
                pointBackgroundColor: '#3498db',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '₱' + context.parsed.y.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
}