/**
 * Expenses JavaScript for Mr. Tarpz Printing Shop
 */

$(document).ready(function () {
    loadExpenses();
    loadExpenseCategories();

    // Set default dates for filters
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    $('#fromDate').val(formatDateForInput(firstDay));
    $('#toDate').val(formatDateForInput(today));

    $('#searchExpense').on('keyup', function () {
        loadPayments();
    });

    // Search and filter events
    $('#filterCategory, #fromDate, #toDate').on('input change', function () {
        loadExpenses();
    });

    // Close modals when clicking on X
    $('.close').on('click', function () {
        closeExpenseModal();
        closeDetailsModal();
    });

    // Close modals when clicking outside
    $(window).on('click', function (event) {
        if ($(event.target).hasClass('modal')) {
            closeExpenseModal();
            closeDetailsModal();
        }
    });
});

/**
 * Load expenses based on filters
 */
function loadExpenses() {
    const search = $('#searchExpense').val();
    const category = $('#filterCategory').val();
    const fromDate = $('#fromDate').val();
    const toDate = $('#toDate').val();

    $('#expensesList').html('<tr><td colspan="9" class="text-center">Loading expenses...</td></tr>');

    $.ajax({
        url: 'expenses.php',
        type: 'POST',
        data: {
            action: 'get_expenses',
            search: search,
            category: category,
            from_date: fromDate,
            to_date: toDate
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                displayExpenses(response.data);
                updateExpenseSummary(response.data);
                updateActiveFilters(search, category, fromDate, toDate);
            } else {
                showError('Failed to load expenses');
            }
        },
        error: function () {
            showError('Error loading expenses');
        }
    });
}

/**
 * Display expenses in table
 */
function displayExpenses(expenses) {
    let html = '';
    let grandTotal = 0;

    if (expenses && expenses.length > 0) {
        expenses.forEach(function (expense) {
            grandTotal += parseFloat(expense.amount);

            const categoryClass = getCategoryClass(expense.category);
            const date = formatDate(expense.expense_date);

            html += `
                <tr>
                    <td>${date}</td>
                    <td>
                        <span class="expense-category-badge ${categoryClass}">
                            ${escapeHtml(expense.category)}
                        </span>
                    </td>
                    <td>${escapeHtml(expense.description)}</td>
                    <td><strong>₱${parseFloat(expense.amount).toFixed(2)}</strong></td>
                    <td>${formatPaymentMethod(expense.payment_method)}</td>
                    <td>${escapeHtml(expense.reference || '-')}</td>
                    <td>${escapeHtml(expense.notes || '-')}</td>
                    <td>${escapeHtml(expense.user_name || 'System')}</td>
                    <td class="actions">
                        <button class="btn-icon" onclick="viewExpense(${expense.expense_id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon" onclick="editExpense(${expense.expense_id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete" onclick="deleteExpense(${expense.expense_id})" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    } else {
        html = '<tr><td colspan="9" class="empty-table">No expenses found</td></tr>';
    }

    $('#expensesList').html(html);
    $('#grandTotal').text('₱' + grandTotal.toFixed(2));
}

/**
 * Update expense summary
 */
function updateExpenseSummary(expenses) {
    const today = new Date().toISOString().split('T')[0];
    const todayTotal = expenses
        .filter(e => e.expense_date === today)
        .reduce((sum, e) => sum + parseFloat(e.amount), 0);

    // This week
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    const weekAgoStr = weekAgo.toISOString().split('T')[0];
    const weekTotal = expenses
        .filter(e => e.expense_date >= weekAgoStr)
        .reduce((sum, e) => sum + parseFloat(e.amount), 0);

    // This month
    const monthAgo = new Date();
    monthAgo.setMonth(monthAgo.getMonth() - 1);
    const monthAgoStr = monthAgo.toISOString().split('T')[0];
    const monthTotal = expenses
        .filter(e => e.expense_date >= monthAgoStr)
        .reduce((sum, e) => sum + parseFloat(e.amount), 0);

    $('#todayTotal').text('₱' + todayTotal.toFixed(2));
    $('#weekTotal').text('₱' + weekTotal.toFixed(2));
    $('#monthTotal').text('₱' + monthTotal.toFixed(2));
}

/**
 * Update active filters display
 */
function updateActiveFilters(search, category, fromDate, toDate) {
    let html = '';

    if (search) {
        html += `<span class="filter-badge">Search: "${escapeHtml(search)}" <i class="fas fa-times" onclick="clearSearch()"></i></span> `;
    }

    if (category) {
        html += `<span class="filter-badge">Category: ${escapeHtml(category)} <i class="fas fa-times" onclick="clearCategory()"></i></span> `;
    }

    if (fromDate || toDate) {
        html += `<span class="filter-badge">Date: ${fromDate || 'Any'} to ${toDate || 'Any'} <i class="fas fa-times" onclick="clearDates()"></i></span> `;
    }

    if (html) {
        $('#activeFilters').html(html).show();
    } else {
        $('#activeFilters').hide();
    }
}

/**
 * Load expense categories for dropdown
 */
function loadExpenseCategories() {
    $.ajax({
        url: 'expenses.php',
        type: 'POST',
        data: {
            action: 'get_expense_categories'
        },
        dataType: 'json',
        success: function (response) {
            if (response.success && response.data.length > 0) {
                let options = '<option value="">Select Category</option>';
                response.data.forEach(function (cat) {
                    options += `<option value="${cat}">${cat}</option>`;
                });
                $('#category').html(options);
            }
        }
    });
}

/**
 * Show add expense modal
 */
function showAddExpenseModal() {
    $('#expenseForm')[0].reset();
    $('#expense_id').val('');
    $('#expense_date').val(new Date().toISOString().split('T')[0]);
    $('#modalTitle').html('<i class="fas fa-plus-circle"></i> Add Expense');
    $('#expenseModal').show();
}

/**
 * View expense details
 */
function viewExpense(id) {
    $.ajax({
        url: 'expenses.php',
        type: 'POST',
        data: {
            action: 'get_expense',
            expense_id: id
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                const e = response.data;
                const html = `
                    <div style="text-align: center;">
                        <h2 style="color: #ef4444; font-size: 32px; margin: 20px 0;">
                            ₱${parseFloat(e.amount).toFixed(2)}
                        </h2>
                        
                        <div style="background: #f8fafc; padding: 15px; border-radius: 10px; margin: 20px 0; text-align: left;">
                            <p><strong>Date:</strong> ${formatDate(e.expense_date)}</p>
                            <p><strong>Category:</strong> ${escapeHtml(e.category)}</p>
                            <p><strong>Description:</strong> ${escapeHtml(e.description)}</p>
                            <p><strong>Payment Method:</strong> ${formatPaymentMethod(e.payment_method)}</p>
                            <p><strong>Reference:</strong> ${escapeHtml(e.reference || 'N/A')}</p>
                            <p><strong>Notes:</strong> ${escapeHtml(e.notes || 'N/A')}</p>
                            <p><strong>Recorded By:</strong> ${escapeHtml(e.user_name || 'System')}</p>
                            <p><strong>Recorded On:</strong> ${formatDate(e.created_at)}</p>
                        </div>
                    </div>
                `;

                $('#expenseDetails').html(html);
                $('#editFromDetailsBtn').attr('onclick', `editExpense(${e.expense_id})`);
                $('#detailsModal').show();
            }
        }
    });
}

/**
 * Edit expense
 */
function editExpense(id) {
    closeDetailsModal();

    $.ajax({
        url: 'expenses.php',
        type: 'POST',
        data: {
            action: 'get_expense',
            expense_id: id
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                const e = response.data;
                $('#expense_id').val(e.expense_id);
                $('#expense_date').val(e.expense_date);
                $('#category').val(e.category);
                $('#description').val(e.description);
                $('#amount').val(e.amount);
                $('#payment_method').val(e.payment_method);
                $('#reference').val(e.reference || '');
                $('#expense_notes').val(e.notes || '');
                $('#modalTitle').html('<i class="fas fa-edit"></i> Edit Expense');
                $('#expenseModal').show();
            }
        }
    });
}

/**
 * Save expense (add or update)
 */
function saveExpense() {
    // Validation
    if (!$('#expense_date').val()) {
        alert('Please select a date');
        return;
    }

    if (!$('#category').val()) {
        alert('Please select a category');
        return;
    }

    if (!$('#description').val().trim()) {
        alert('Please enter a description');
        return;
    }

    if (!$('#amount').val() || parseFloat($('#amount').val()) <= 0) {
        alert('Please enter a valid amount');
        return;
    }

    if (!$('#payment_method').val()) {
        alert('Please select a payment method');
        return;
    }

    const expenseId = $('#expense_id').val();
    const action = expenseId ? 'update_expense' : 'add_expense';

    const btn = $('#expenseModal .btn-primary');
    const originalText = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);

    const data = {
        action: action,
        expense_id: expenseId,
        expense_date: $('#expense_date').val(),
        category: $('#category').val(),
        description: $('#description').val(),
        amount: $('#amount').val(),
        payment_method: $('#payment_method').val(),
        reference: $('#reference').val(),
        notes: $('#expense_notes').val()
    };

    $.ajax({
        url: 'expenses.php',
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                alert('✅ ' + response.message);
                closeExpenseModal();
                loadExpenses();
            } else {
                alert('❌ ' + response.message);
            }
        },
        error: function () {
            alert('❌ Error saving expense');
        },
        complete: function () {
            btn.html(originalText).prop('disabled', false);
        }
    });
}

/**
 * Delete expense
 */
function deleteExpense(id) {
    if (confirm('Are you sure you want to delete this expense? This action cannot be undone.')) {
        const row = $(`button[onclick="deleteExpense(${id})"]`).closest('tr');
        row.addClass('loading');

        $.ajax({
            url: 'expenses.php',
            type: 'POST',
            data: {
                action: 'delete_expense',
                expense_id: id
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    alert('✅ Expense deleted successfully');
                    loadExpenses();
                } else {
                    alert('❌ ' + response.message);
                    row.removeClass('loading');
                }
            },
            error: function () {
                alert('❌ Error deleting expense');
                row.removeClass('loading');
            }
        });
    }
}

/**
 * Export expenses to CSV
 */
function exportExpenses() {
    const search = $('#searchExpense').val();
    const category = $('#filterCategory').val();
    const fromDate = $('#fromDate').val();
    const toDate = $('#toDate').val();

    $.ajax({
        url: 'expenses.php',
        type: 'POST',
        data: {
            action: 'get_expenses',
            search: search,
            category: category,
            from_date: fromDate,
            to_date: toDate
        },
        dataType: 'json',
        success: function (response) {
            if (response.success && response.data.length > 0) {
                let csv = 'Date,Category,Description,Amount,Payment Method,Reference,Notes\n';

                response.data.forEach(function (e) {
                    csv += `"${e.expense_date}","${e.category}","${e.description}","${e.amount}","${e.payment_method}","${e.reference || ''}","${e.notes || ''}"\n`;
                });

                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `expenses_${fromDate || 'all'}_to_${toDate || 'all'}.csv`;
                a.click();
                window.URL.revokeObjectURL(url);
            } else {
                alert('No data to export');
            }
        }
    });
}

/**
 * Edit from details modal
 */
function editFromDetails() {
    closeDetailsModal();
    // The edit button will be triggered by the onclick attribute we set
}

/**
 * Clear filters
 */
function clearSearch() {
    $('#searchExpense').val('');
    loadExpenses();
}

function clearCategory() {
    $('#filterCategory').val('');
    loadExpenses();
}

function clearDates() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    $('#fromDate').val(formatDateForInput(firstDay));
    $('#toDate').val(formatDateForInput(today));
    loadExpenses();
}

/**
 * Get CSS class for category
 */
function getCategoryClass(category) {
    const cat = (category || '').toLowerCase();
    if (cat.includes('rent')) return 'category-rent';
    if (cat.includes('utility') || cat.includes('electric') || cat.includes('water')) return 'category-utilities';
    if (cat.includes('salary') || cat.includes('wage')) return 'category-salaries';
    if (cat.includes('supply') || cat.includes('office')) return 'category-supplies';
    if (cat.includes('equip') || cat.includes('machine')) return 'category-equipment';
    if (cat.includes('market') || cat.includes('advert')) return 'category-marketing';
    return 'category-others';
}

/**
 * Format payment method
 */
function formatPaymentMethod(method) {
    const methods = {
        'cash': '💵 Cash',
        'gcash': '📱 GCash',
        'bank_transfer': '🏦 Bank Transfer',
        'credit': '💳 Credit',
        'check': '📝 Check'
    };
    return methods[method] || method;
}

/**
 * Format date for display
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Format date for input field
 */
function formatDateForInput(date) {
    return date.toISOString().split('T')[0];
}

/**
 * Close expense modal
 */
function closeExpenseModal() {
    $('#expenseModal').hide();
}

/**
 * Close details modal
 */
function closeDetailsModal() {
    $('#detailsModal').hide();
}

/**
 * Show error message
 */
function showError(message) {
    $('#expensesList').html(`<tr><td colspan="9" class="error-message">${message}</td></tr>`);
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    if (!text && text !== 0) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}