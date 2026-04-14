<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'add_expense') {
        try {
            // Get and validate data
            $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $reference = $_POST['reference'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $user_id = $_SESSION['user_id'];
            
            // Validate required fields
            if (empty($category)) {
                echo json_encode(['success' => false, 'message' => 'Category is required']);
                exit;
            }
            
            if (empty($description)) {
                echo json_encode(['success' => false, 'message' => 'Description is required']);
                exit;
            }
            
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Amount must be greater than 0']);
                exit;
            }
            
            // First, check what columns exist in the expenses table
            $columns_result = $conn->query("SHOW COLUMNS FROM expenses");
            $existing_columns = [];
            while ($col = $columns_result->fetch_assoc()) {
                $existing_columns[] = $col['Field'];
            }
            
            // Build INSERT query based on existing columns
            $fields = ['expense_date', 'category', 'description', 'amount', 'payment_method', 'user_id'];
            $values = ["'$expense_date'", "'$category'", "'$description'", $amount, "'$payment_method'", $user_id];
            
            if (in_array('reference', $existing_columns)) {
                $fields[] = 'reference';
                $values[] = "'$reference'";
            }
            
            if (in_array('notes', $existing_columns)) {
                $fields[] = 'notes';
                $values[] = "'$notes'";
            }
            
            $sql = "INSERT INTO expenses (" . implode(', ', $fields) . ") 
                    VALUES (" . implode(', ', $values) . ")";
            
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => 'Expense added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_expenses') {
        $search = isset($_POST['search']) ? $conn->real_escape_string($_POST['search']) : '';
        $category = isset($_POST['category']) ? $conn->real_escape_string($_POST['category']) : '';
        
        $sql = "SELECT e.*, u.full_name as user_name 
                FROM expenses e 
                LEFT JOIN users u ON e.user_id = u.user_id 
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (e.description LIKE '%$search%' OR e.category LIKE '%$search%')";
        }
        
        if (!empty($category)) {
            $sql .= " AND e.category = '$category'";
        }
        
        $sql .= " ORDER BY e.expense_date DESC";
        
        $result = $conn->query($sql);
        $expenses = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $expenses[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $expenses]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'delete_expense') {
        $expense_id = intval($_POST['expense_id']);
        
        $sql = "DELETE FROM expenses WHERE expense_id = $expense_id";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Expense deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $conn->error]);
        }
        exit;
    }
}

// If not AJAX, show the HTML page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expenses - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .expense-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .summary-item { text-align: center; }
        .summary-item h3 { font-size: 14px; opacity: 0.9; margin-bottom: 5px; }
        .summary-item .amount { font-size: 24px; font-weight: bold; }
        .filters-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-box { flex: 1; position: relative; }
        .search-box input { width: 100%; padding: 10px 40px 10px 15px; border: 2px solid #e2e8f0; border-radius: 10px; }
        .search-box i { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .filter-select { padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 10px; }
        .btn-icon { background: none; border: none; cursor: pointer; padding: 5px 8px; margin: 0 2px; border-radius: 5px; }
        .btn-icon:hover { background: #f1f5f9; }
        .btn-icon.delete:hover { background: #fee2e2; color: #dc2626; }
        
        /* Scrollable Modal Styles */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 1000;
            overflow-y: auto;
        }
        .modal-content { 
            background: white; 
            max-width: 550px; 
            width: 90%;
            margin: 30px auto; 
            border-radius: 15px; 
            overflow: hidden;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        .modal-header { 
            padding: 15px 20px; 
            background: #1e293b; 
            color: white; 
            display: flex; 
            justify-content: space-between;
            flex-shrink: 0;
        }
        .modal-body { 
            padding: 20px; 
            overflow-y: auto;
            flex: 1;
        }
        .modal-footer { 
            padding: 15px 20px; 
            background: #f8fafc; 
            display: flex; 
            justify-content: flex-end; 
            gap: 10px;
            flex-shrink: 0;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 10px; 
            border: 2px solid #e2e8f0; 
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-secondary { background: #e2e8f0; color: #475569; }
        .close { font-size: 24px; cursor: pointer; }
        .text-center { text-align: center; }
        .table-container { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .table th { background: #f8fafc; font-weight: 600; }
        
        /* Scrollbar styling */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .modal-body::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1><i class="fas fa-chart-line"></i> Expenses Management</h1>
                <button class="btn btn-primary" onclick="showAddExpenseModal()">
                    <i class="fas fa-plus"></i> Add Expense
                </button>
            </div>
            
            <div class="expense-summary">
                <div class="summary-item"><h3>Today</h3><div class="amount" id="todayTotal">₱0.00</div></div>
                <div class="summary-item"><h3>This Week</h3><div class="amount" id="weekTotal">₱0.00</div></div>
                <div class="summary-item"><h3>This Month</h3><div class="amount" id="monthTotal">₱0.00</div></div>
                <div class="summary-item"><h3>Total</h3><div class="amount" id="grandTotal">₱0.00</div></div>
            </div>
            
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchExpense" placeholder="Search expenses...">
                    <i class="fas fa-search"></i>
                </div>
                <select id="filterCategory" class="filter-select">
                    <option value="">All Categories</option>
                    <option value="Rent">Rent</option>
                    <option value="Utilities">Utilities</option>
                    <option value="Salaries">Salaries</option>
                    <option value="Office Supplies">Office Supplies</option>
                    <option value="Equipment">Equipment</option>
                    <option value="Maintenance">Maintenance</option>
                    <option value="Marketing">Marketing</option>
                    <option value="Transportation">Transportation</option>
                    <option value="Raw Materials">Raw Materials</option>
                    <option value="Others">Others</option>
                </select>
                <button class="btn btn-secondary" onclick="loadExpenses()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Reference</th>
                            <th>Notes</th>
                            <th>User</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="expensesList">
                        <tr><td colspan="9" class="text-center">Loading expenses...<\/td><\/tr>
                    </tbody>
            </div>
        </div>
    </div>
    
    <!-- Add Expense Modal - Scrollable -->
    <div id="expenseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Expense</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="expenseForm">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" id="expense_date" required>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select id="category" required>
                            <option value="">Select Category</option>
                            <option value="Rent">Rent</option>
                            <option value="Utilities">Utilities</option>
                            <option value="Salaries">Salaries</option>
                            <option value="Office Supplies">Office Supplies</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Transportation">Transportation</option>
                            <option value="Raw Materials">Raw Materials</option>
                            <option value="Printing Supplies">Printing Supplies</option>
                            <option value="Packaging">Packaging</option>
                            <option value="Internet">Internet</option>
                            <option value="Electricity">Electricity</option>
                            <option value="Water">Water</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <input type="text" id="description" required placeholder="What was this expense for?">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount (₱) *</label>
                            <input type="number" id="amount" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Payment Method *</label>
                            <select id="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Reference Number</label>
                            <input type="text" id="reference" placeholder="Receipt/invoice #">
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea id="notes" rows="3" placeholder="Additional notes..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveExpense()">Save Expense</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            loadExpenses();
            
            $('#searchExpense').on('keyup', function() {
                loadExpenses();
            });
            
            $('#filterCategory').on('change', function() {
                loadExpenses();
            });
        });
        
        function loadExpenses() {
            const search = $('#searchExpense').val();
            const category = $('#filterCategory').val();
            
            $.ajax({
                url: 'expenses.php',
                type: 'POST',
                data: {
                    action: 'get_expenses',
                    search: search,
                    category: category
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayExpenses(response.data);
                        updateSummary(response.data);
                    } else {
                        $('#expensesList').html('<tr><td colspan="9" class="text-center">Error: ' + response.message + '<\/td><\/tr>');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX Error:', xhr.responseText);
                    $('#expensesList').html('<tr><td colspan="9" class="text-center">Error loading expenses. Check console.<\/td><\/tr>');
                }
            });
        }
        
        function displayExpenses(expenses) {
            let html = '';
            
            if (expenses && expenses.length > 0) {
                expenses.forEach(function(expense) {
                    html += `
                        <tr>
                            <td>${expense.expense_date}<\/td>
                            <td><span style="background: #e0e7ff; padding: 4px 12px; border-radius: 20px; display: inline-block;">${escapeHtml(expense.category)}<\/span><\/td>
                            <td>${escapeHtml(expense.description)}<\/td>
                            <td><strong>₱${parseFloat(expense.amount).toFixed(2)}<\/strong><\/td>
                            <td>${escapeHtml(expense.payment_method)}<\/td>
                            <td>${escapeHtml(expense.reference || '-')}<\/td>
                            <td>${escapeHtml(expense.notes || '-')}<\/td>
                            <td>${escapeHtml(expense.user_name || 'System')}<\/td>
                            <td>
                                <button class="btn-icon delete" onclick="deleteExpense(${expense.expense_id})">
                                    <i class="fas fa-trash"><\/i>
                                <\/button>
                            <\/td>
                        <\/tr>
                    `;
                });
            } else {
                html = '<tr><td colspan="9" class="text-center">No expenses found<\/td><\/tr>';
            }
            
            $('#expensesList').html(html);
        }
        
        function updateSummary(expenses) {
            const today = new Date().toISOString().split('T')[0];
            const weekAgo = new Date();
            weekAgo.setDate(weekAgo.getDate() - 7);
            const weekAgoStr = weekAgo.toISOString().split('T')[0];
            const monthAgo = new Date();
            monthAgo.setMonth(monthAgo.getMonth() - 1);
            const monthAgoStr = monthAgo.toISOString().split('T')[0];
            
            let todayTotal = 0;
            let weekTotal = 0;
            let monthTotal = 0;
            let grandTotal = 0;
            
            expenses.forEach(function(expense) {
                const amount = parseFloat(expense.amount);
                const date = expense.expense_date;
                
                grandTotal += amount;
                
                if (date === today) todayTotal += amount;
                if (date >= weekAgoStr) weekTotal += amount;
                if (date >= monthAgoStr) monthTotal += amount;
            });
            
            $('#todayTotal').text('₱' + todayTotal.toFixed(2));
            $('#weekTotal').text('₱' + weekTotal.toFixed(2));
            $('#monthTotal').text('₱' + monthTotal.toFixed(2));
            $('#grandTotal').text('₱' + grandTotal.toFixed(2));
        }
        
        function showAddExpenseModal() {
            $('#expenseForm')[0].reset();
            $('#expense_date').val(new Date().toISOString().split('T')[0]);
            $('#expenseModal').show();
            $('body').css('overflow', 'hidden');
        }
        
        function saveExpense() {
            const formData = {
                action: 'add_expense',
                expense_date: $('#expense_date').val(),
                category: $('#category').val(),
                description: $('#description').val(),
                amount: $('#amount').val(),
                payment_method: $('#payment_method').val(),
                reference: $('#reference').val(),
                notes: $('#notes').val()
            };
            
            if (!formData.category || !formData.description || !formData.amount) {
                alert('Please fill in all required fields');
                return;
            }
            
            const btn = $('#expenseModal .btn-primary');
            btn.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: 'expenses.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Expense saved successfully!');
                        closeModal();
                        loadExpenses();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error saving expense. Check console for details.');
                    console.log('Response:', xhr.responseText);
                },
                complete: function() {
                    btn.prop('disabled', false).text('Save Expense');
                }
            });
        }
        
        function deleteExpense(id) {
            if (confirm('Are you sure you want to delete this expense?')) {
                $.ajax({
                    url: 'expenses.php',
                    type: 'POST',
                    data: {
                        action: 'delete_expense',
                        expense_id: id
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Expense deleted');
                            loadExpenses();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                });
            }
        }
        
        function closeModal() {
            $('#expenseModal').hide();
            $('body').css('overflow', 'auto');
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if ($(event.target).hasClass('modal')) {
                closeModal();
            }
        }
        
        // Close modal with Escape key
        $(document).keydown(function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>