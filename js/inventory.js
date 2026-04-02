/**
 * Inventory JavaScript for Mr. Tarpz Printing Shop
 */

$(document).ready(function() {
    loadInventory();
    loadTransactions();
    
    // Search and filter
    $('#searchInventory, #filterType').on('input change', function() {
        loadInventory();
    });
    
    // Close modals when clicking on X
    $('.close').on('click', function() {
        closeAllModals();
    });
    
    // Close modals when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('modal')) {
            closeAllModals();
        }
    });
});

/**
 * Load inventory based on filters
 */
function loadInventory() {
    const search = $('#searchInventory').val();
    const type = $('#filterType').val();
    
    $('#inventoryList').html('<tr><td colspan="8" class="text-center">Loading inventory...</td></tr>');
    
    $.ajax({
        url: 'inventory.php',
        type: 'POST',
        data: {
            action: 'get_inventory',
            search: search,
            type: type
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayInventory(response.data);
            } else {
                showError('Failed to load inventory');
            }
        },
        error: function() {
            showError('Error loading inventory');
        }
    });
}

/**
 * Display inventory in table
 */
function displayInventory(items) {
    let html = '';
    
    if (items && items.length > 0) {
        items.forEach(function(item) {
            const status = getStockStatus(item.stock_quantity, item.reorder_level);
            const statusClass = status === 'Low Stock' ? 'low-stock' : (status === 'Out of Stock' ? 'text-danger' : 'text-success');
            const stockValue = item.stock_quantity * item.cost_price;
            
            html += `
                <tr>
                    <td><strong>${escapeHtml(item.product_name)}</strong></td>
                    <td>${escapeHtml(item.category_name || 'Uncategorized')}</td>
                    <td>${formatProductType(item.product_type)}</td>
                    <td class="${statusClass}"><strong>${item.stock_quantity}</strong></td>
                    <td>${item.reorder_level}</td>
                    <td><span class="status-badge ${status === 'Low Stock' ? 'status-pending' : (status === 'Out of Stock' ? 'status-cancelled' : 'status-completed')}">${status}</span></td>
                    <td>₱${stockValue.toFixed(2)}</td>
                    <td class="actions">
                        <button class="btn-icon" onclick="showAddStockModalForProduct(${item.product_id})" title="Add Stock">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        <button class="btn-icon" onclick="showRemoveStockModalForProduct(${item.product_id}, ${item.stock_quantity})" title="Remove Stock">
                            <i class="fas fa-minus-circle"></i>
                        </button>
                        <button class="btn-icon" onclick="showProductHistory(${item.product_id})" title="View History">
                            <i class="fas fa-history"></i>
                        </button>
                        <button class="btn-icon" onclick="window.location.href='products.php?edit=${item.product_id}'" title="Edit Product">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    } else {
        html = '<tr><td colspan="8" class="empty-table">No inventory items found</td></tr>';
    }
    
    $('#inventoryList').html(html);
}

/**
 * Load recent transactions
 */
function loadTransactions() {
    $.ajax({
        url: 'inventory.php',
        type: 'POST',
        data: {
            action: 'get_transactions'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayTransactions(response.data);
            }
        },
        error: function() {
            $('#transactionsList').html('<tr><td colspan="6" class="error-message">Error loading transactions</td></tr>');
        }
    });
}

/**
 * Display transactions
 */
function displayTransactions(transactions) {
    let html = '';
    
    if (transactions && transactions.length > 0) {
        transactions.forEach(function(trans) {
            const typeClass = trans.transaction_type === 'in' ? 'text-success' : 
                            (trans.transaction_type === 'out' ? 'text-danger' : 'text-warning');
            const typeIcon = trans.transaction_type === 'in' ? 'fa-arrow-down' : 
                           (trans.transaction_type === 'out' ? 'fa-arrow-up' : 'fa-adjust');
            
            html += `
                <tr>
                    <td>${formatDate(trans.transaction_date)}</td>
                    <td>${escapeHtml(trans.product_name || 'Unknown Product')}</td>
                    <td class="${typeClass}">
                        <i class="fas ${typeIcon}"></i> 
                        ${trans.transaction_type === 'in' ? 'Added' : (trans.transaction_type === 'out' ? 'Removed' : 'Adjusted')}
                    </td>
                    <td class="${typeClass}"><strong>${trans.quantity}</strong></td>
                    <td>${escapeHtml(trans.notes || '-')}</td>
                    <td>${escapeHtml(trans.user_name || 'System')}</td>
                </tr>
            `;
        });
    } else {
        html = '<tr><td colspan="6" class="empty-table">No transactions found</td></tr>';
    }
    
    $('#transactionsList').html(html);
}

/**
 * Show add stock modal
 */
function showAddStockModal() {
    $('#addStockForm')[0].reset();
    $('#addStockModal').show();
}

/**
 * Show add stock modal for specific product
 */
function showAddStockModalForProduct(productId) {
    $('#addStockForm')[0].reset();
    $('#add_product_id').val(productId);
    $('#addStockModal').show();
}

/**
 * Show remove stock modal
 */
function showRemoveStockModal() {
    $('#removeStockForm')[0].reset();
    $('#stockWarning').hide();
    $('#removeStockModal').show();
}

/**
 * Show remove stock modal for specific product
 */
function showRemoveStockModalForProduct(productId, currentStock) {
    $('#removeStockForm')[0].reset();
    $('#remove_product_id').val(productId);
    $('#stockWarning').hide();
    $('#removeStockModal').show();
}

/**
 * Show new product modal
 */
function showNewProductModal() {
    $('#newProductForm')[0].reset();
    $('#newProductModal').show();
}

/**
 * Update available stock in remove modal
 */
function updateAvailableStock() {
    const select = $('#remove_product_id');
    const selected = select.find('option:selected');
    const available = selected.data('stock') || 0;
    const quantity = $('#remove_quantity').val();
    
    if (quantity && parseInt(quantity) > available) {
        $('#stockWarning').show();
    } else {
        $('#stockWarning').hide();
    }
}

/**
 * Add stock
 */
function addStock() {
    const productId = $('#add_product_id').val();
    const quantity = $('#add_quantity').val();
    
    if (!productId) {
        alert('Please select a product');
        return;
    }
    
    if (!quantity || quantity < 1) {
        alert('Please enter a valid quantity');
        return;
    }
    
    const btn = $('#addStockModal .btn-primary');
    const originalText = btn.text();
    btn.html('<i class="fas fa-spinner fa-spin"></i> Adding...').prop('disabled', true);
    
    $.ajax({
        url: 'inventory.php',
        type: 'POST',
        data: $('#addStockForm').serialize() + '&action=add_stock',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(response.message, 'success');
                closeAddStockModal();
                loadInventory();
                loadTransactions();
            } else {
                alert(response.message || 'Error adding stock');
            }
        },
        error: function() {
            alert('Error adding stock');
        },
        complete: function() {
            btn.html(originalText).prop('disabled', false);
        }
    });
}

/**
 * Remove stock
 */
function removeStock() {
    const productId = $('#remove_product_id').val();
    const quantity = $('#remove_quantity').val();
    const select = $('#remove_product_id option:selected');
    const available = select.data('stock') || 0;
    
    if (!productId) {
        alert('Please select a product');
        return;
    }
    
    if (!quantity || quantity < 1) {
        alert('Please enter a valid quantity');
        return;
    }
    
    if (parseInt(quantity) > available) {
        alert('Insufficient stock! Available: ' + available);
        return;
    }
    
    if (!confirm('Are you sure you want to remove ' + quantity + ' items from stock?')) {
        return;
    }
    
    const btn = $('#removeStockModal .btn-danger');
    const originalText = btn.text();
    btn.html('<i class="fas fa-spinner fa-spin"></i> Removing...').prop('disabled', true);
    
    $.ajax({
        url: 'inventory.php',
        type: 'POST',
        data: $('#removeStockForm').serialize() + '&action=remove_stock',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(response.message, 'success');
                closeRemoveStockModal();
                loadInventory();
                loadTransactions();
            } else {
                alert(response.message || 'Error removing stock');
            }
        },
        error: function() {
            alert('Error removing stock');
        },
        complete: function() {
            btn.html(originalText).prop('disabled', false);
        }
    });
}

/**
 * Save new product
 */
function saveNewProduct() {
    // Validation
    if (!$('#new_product_name').val().trim()) {
        alert('Please enter product name');
        $('#new_product_name').focus();
        return;
    }
    
    if (!$('#new_unit_price').val() || parseFloat($('#new_unit_price').val()) < 0) {
        alert('Please enter a valid selling price');
        $('#new_unit_price').focus();
        return;
    }
    
    if (!$('#new_cost_price').val() || parseFloat($('#new_cost_price').val()) < 0) {
        alert('Please enter a valid cost price');
        $('#new_cost_price').focus();
        return;
    }
    
    const btn = $('#newProductModal .btn-success');
    const originalText = btn.text();
    btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
    
    const formData = $('#newProductForm').serialize() + '&action=add_product';
    
    $.ajax({
        url: 'products.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(response.message, 'success');
                closeNewProductModal();
                loadInventory(); // Refresh inventory to show new product
            } else {
                alert(response.message || 'Error saving product');
            }
        },
        error: function() {
            alert('Error saving product');
        },
        complete: function() {
            btn.html(originalText).prop('disabled', false);
        }
    });
}

/**
 * Show product history
 */
function showProductHistory(productId) {
    $.ajax({
        url: 'inventory.php',
        type: 'POST',
        data: {
            action: 'get_transactions',
            product_id: productId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data.length > 0) {
                let historyHtml = '<div style="max-height: 400px; overflow-y: auto;">';
                historyHtml += '<table class="table">';
                historyHtml += '<thead><tr><th>Date</th><th>Type</th><th>Quantity</th><th>Notes</th></tr></thead>';
                historyHtml += '<tbody>';
                
                response.data.forEach(function(trans) {
                    const typeClass = trans.transaction_type === 'in' ? 'text-success' : 'text-danger';
                    historyHtml += `
                        <tr>
                            <td>${formatDate(trans.transaction_date)}</td>
                            <td class="${typeClass}">${trans.transaction_type === 'in' ? 'Added' : 'Removed'}</td>
                            <td class="${typeClass}">${trans.quantity}</td>
                            <td>${escapeHtml(trans.notes || '-')}</td>
                        </tr>
                    `;
                });
                
                historyHtml += '</tbody></table></div>';
                
                const modal = `
                    <div class="modal" style="display: block;" id="historyModal">
                        <div class="modal-content" style="max-width: 600px;">
                            <div class="modal-header">
                                <h3>Transaction History</h3>
                                <span class="close" onclick="document.getElementById('historyModal').remove()">&times;</span>
                            </div>
                            <div class="modal-body">
                                ${historyHtml}
                            </div>
                        </div>
                    </div>
                `;
                
                $('body').append(modal);
            } else {
                alert('No transaction history for this product');
            }
        }
    });
}

/**
 * Get stock status
 */
function getStockStatus(stock, reorderLevel) {
    if (stock <= 0) return 'Out of Stock';
    if (stock <= reorderLevel) return 'Low Stock';
    return 'In Stock';
}

/**
 * Format product type
 */
function formatProductType(type) {
    const types = {
        'finished': 'Finished',
        'raw_material': 'Raw Material'
    };
    return types[type] || type;
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
 * Close add stock modal
 */
function closeAddStockModal() {
    $('#addStockModal').hide();
}

/**
 * Close remove stock modal
 */
function closeRemoveStockModal() {
    $('#removeStockModal').hide();
}

/**
 * Close new product modal
 */
function closeNewProductModal() {
    $('#newProductModal').hide();
}

/**
 * Close all modals
 */
function closeAllModals() {
    $('.modal').hide();
}

/**
 * Show error message
 */
function showError(message) {
    $('#inventoryList').html(`<tr><td colspan="8" class="error-message">${message}</td></tr>`);
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const notification = $(`
        <div class="notification notification-${type}" style="position: fixed; top: 20px; right: 20px; z-index: 9999; background: white; padding: 1rem 1.5rem; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-left: 4px solid ${type === 'success' ? '#22c55e' : '#3b82f6'}; animation: slideIn 0.3s ease;">
            ${message}
        </div>
    `);
    
    $('body').append(notification);
    
    setTimeout(() => {
        notification.fadeOut(() => notification.remove());
    }, 3000);
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

// Add animation style
$('head').append(`
    <style>
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
`);