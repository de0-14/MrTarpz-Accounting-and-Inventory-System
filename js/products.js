/**
 * Products JavaScript for Mr. Tarpz Printing Shop
 */

$(document).ready(function() {
    loadProducts();
    loadCategories();
    
    // Search and filter
    $('#searchProduct, #filterCategory').on('input change', function() {
        loadProducts();
    });
    
    // Close modal when clicking on X
    $('.close').on('click', function() {
        closeModal();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('modal')) {
            closeModal();
        }
    });
});

/**
 * Load products based on filters
 */
function loadProducts() {
    const search = $('#searchProduct').val();
    const category = $('#filterCategory').val();
    
    $('#productsList').html('<tr><td colspan="9" class="text-center">Loading products...</td></tr>');
    
    $.ajax({
        url: 'products.php',
        type: 'POST',
        data: {
            action: 'get_products',
            search: search,
            category: category
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayProducts(response.data);
            } else {
                showError('Failed to load products: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            showError('Error loading products');
        }
    });
}

/**
 * Display products in table
 */
function displayProducts(products) {
    let html = '';
    
    if (products && products.length > 0) {
        products.forEach(function(product) {
            const stockClass = parseInt(product.stock_quantity) <= parseInt(product.reorder_level) ? 'low-stock' : '';
            const profit = parseFloat(product.unit_price) - parseFloat(product.cost_price);
            const profitClass = profit > 0 ? 'text-success' : 'text-danger';
            
            html += `
                <tr>
                    <td>#${product.product_id}</td>
                    <td><strong>${escapeHtml(product.product_name)}</strong></td>
                    <td>${escapeHtml(product.category_name || 'Uncategorized')}</td>
                    <td>₱${parseFloat(product.unit_price).toFixed(2)}</td>
                    <td>₱${parseFloat(product.cost_price).toFixed(2)} <small class="${profitClass}">(₱${profit.toFixed(2)})</small></td>
                    <td class="${stockClass}"><strong>${product.stock_quantity}</strong></td>
                    <td>${product.reorder_level}</td>
                    <td>${formatProductType(product.product_type)}</td>
                    <td class="actions">
                        <button class="btn-icon" onclick="editProduct(${product.product_id})" title="Edit Product">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete" onclick="deleteProduct(${product.product_id})" title="Delete Product">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="btn-icon" onclick="showAddStockModal(${product.product_id})" title="Add Stock">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    } else {
        html = '<tr><td colspan="9" class="empty-table">No products found</td></tr>';
    }
    
    $('#productsList').html(html);
}

/**
 * Load categories for dropdown
 */
function loadCategories() {
    $.ajax({
        url: 'products.php',
        type: 'POST',
        data: {
            action: 'get_categories'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let options = '<option value="">Select Category</option>';
                response.data.forEach(function(category) {
                    options += `<option value="${category.category_id}">${escapeHtml(category.category_name)}</option>`;
                });
                $('#category_id').html(options);
            }
        },
        error: function() {
            console.error('Error loading categories');
        }
    });
}

/**
 * Show add product modal
 */
function showAddProductModal() {
    $('#productForm')[0].reset();
    $('#productId').val('');
    $('#action').val('add_product');
    $('#modalTitle').text('Add New Product');
    $('#productModal').show();
}

/**
 * Edit product
 */
function editProduct(id) {
    $('#productsList').parent().addClass('loading');
    
    $.ajax({
        url: 'products.php',
        type: 'POST',
        data: {
            action: 'get_product',
            product_id: id
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const product = response.data;
                $('#productId').val(product.product_id);
                $('#category_id').val(product.category_id || '');
                $('#product_name').val(product.product_name);
                $('#description').val(product.description);
                $('#unit_price').val(product.unit_price);
                $('#cost_price').val(product.cost_price);
                $('#stock_quantity').val(product.stock_quantity);
                $('#reorder_level').val(product.reorder_level);
                $('#product_type').val(product.product_type);
                $('#action').val('update_product');
                $('#modalTitle').text('Edit Product');
                $('#productModal').show();
            } else {
                alert('Error loading product details: ' + response.message);
            }
        },
        error: function() {
            alert('Error loading product details');
        },
        complete: function() {
            $('#productsList').parent().removeClass('loading');
        }
    });
}

/**
 * Save product (add or update)
 */
function saveProduct() {
    // Basic validation
    if (!$('#product_name').val().trim()) {
        alert('Please enter product name');
        $('#product_name').focus();
        return;
    }
    
    if (!$('#unit_price').val() || parseFloat($('#unit_price').val()) < 0) {
        alert('Please enter a valid selling price');
        $('#unit_price').focus();
        return;
    }
    
    if (!$('#cost_price').val() || parseFloat($('#cost_price').val()) < 0) {
        alert('Please enter a valid cost price');
        $('#cost_price').focus();
        return;
    }
    
    // Show loading state
    const btn = $('#productModal .btn-primary');
    const originalText = btn.text();
    btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
    
    $.ajax({
        url: 'products.php',
        type: 'POST',
        data: $('#productForm').serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(response.message, 'success');
                closeModal();
                loadProducts();
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
 * Delete product
 */
function deleteProduct(id) {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        // Show loading
        const row = $(`button[onclick="deleteProduct(${id})"]`).closest('tr');
        row.addClass('loading');
        
        $.ajax({
            url: 'products.php',
            type: 'POST',
            data: {
                action: 'delete_product',
                product_id: id
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification(response.message, 'success');
                    loadProducts();
                } else {
                    alert(response.message || 'Error deleting product');
                    row.removeClass('loading');
                }
            },
            error: function() {
                alert('Error deleting product');
                row.removeClass('loading');
            }
        });
    }
}

/**
 * Show add stock modal (redirects to inventory)
 */
function showAddStockModal(productId) {
    window.location.href = `inventory.php?add_stock=${productId}`;
}

/**
 * Close modal
 */
function closeModal() {
    $('#productModal').hide();
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
 * Show error message
 */
function showError(message) {
    $('#productsList').html(`<tr><td colspan="9" class="error-message">${message}</td></tr>`);
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    const notification = $(`
        <div class="notification notification-${type}" style="position: fixed; top: 20px; right: 20px; z-index: 9999; background: white; padding: 1rem 1.5rem; border-radius: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-left: 4px solid ${type === 'success' ? '#22c55e' : '#3b82f6'};">
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