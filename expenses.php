<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] == 'get_expenses') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
        $category = isset($_POST['category']) ? sanitize($_POST['category']) : '';
        $from_date = isset($_POST['from_date']) ? sanitize($_POST['from_date']) : '';
        $to_date = isset($_POST['to_date']) ? sanitize($_POST['to_date']) : '';
        
        $sql = "SELECT e.*, u.full_name as user_name 
                FROM expenses e 
                LEFT JOIN users u ON e.user_id = u.user_id 
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (e.description LIKE '%$search%' OR e.category LIKE '%$search%' OR e.reference LIKE '%$search%')";
        }
        
        if (!empty($category)) {
            $sql .= " AND e.category = '$category'";
        }
        
        if (!empty($from_date)) {
            $sql .= " AND e.expense_date >= '$from_date'";
        }
        
        if (!empty($to_date)) {
            $sql .= " AND e.expense_date <= '$to_date'";
        }
        
        $sql .= " ORDER BY e.expense_date DESC, e.created_at DESC";
        
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
    
    if ($_POST['action'] == 'add_expense') {
        $expense_date = sanitize($_POST['expense_date']);
        $category = sanitize($_POST['category']);
        $description = sanitize($_POST['description']);
        $amount = sanitize($_POST['amount']);
        $payment_method = sanitize($_POST['payment_method']);
        $reference = !empty($_POST['reference']) ? sanitize($_POST['reference']) : 'NULL';
        $notes = sanitize($_POST['notes']);
        $user_id = $_SESSION['user_id'];
        
        $sql = "INSERT INTO expenses (expense_date, category, description, amount, payment_method, reference, notes, user_id) 
                VALUES ('$expense_date', '$category', '$description', '$amount', '$payment_method', $reference, '$notes', '$user_id')";
        
        if ($conn->query($sql)) {
            $expense_id = $conn->insert_id;
            echo json_encode(['success' => true, 'message' => 'Expense added successfully', 'expense_id' => $expense_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding expense: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_expense') {
        $expense_id = sanitize($_POST['expense_id']);
        
        $sql = "SELECT e.*, u.full_name as user_name 
                FROM expenses e 
                LEFT JOIN users u ON e.user_id = u.user_id 
                WHERE e.expense_id = '$expense_id'";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $expense = $result->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $expense]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Expense not found']);
        }
        exit;
    }
    
    if ($_POST['action'] == 'update_expense') {
        $expense_id = sanitize($_POST['expense_id']);
        $expense_date = sanitize($_POST['expense_date']);
        $category = sanitize($_POST['category']);
        $description = sanitize($_POST['description']);
        $amount = sanitize($_POST['amount']);
        $payment_method = sanitize($_POST['payment_method']);
        $reference = !empty($_POST['reference']) ? sanitize($_POST['reference']) : 'NULL';
        $notes = sanitize($_POST['notes']);
        
        $sql = "UPDATE expenses SET 
                expense_date = '$expense_date',
                category = '$category',
                description = '$description',
                amount = '$amount',
                payment_method = '$payment_method',
                reference = $reference,
                notes = '$notes'
                WHERE expense_id = '$expense_id'";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Expense updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating expense: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'delete_expense') {
        $expense_id = sanitize($_POST['expense_id']);
        
        $sql = "DELETE FROM expenses WHERE expense_id = '$expense_id'";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting expense: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] == 'get_expense_categories') {
        $sql = "SELECT DISTINCT category FROM expenses WHERE category != '' ORDER BY category";
        $result = $conn->query($sql);
        $categories = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row['category'];
            }
        }
        
        // Add common categories if none exist
        if (empty($categories)) {
            $categories = ['Office Supplies', 'Utilities', 'Rent', 'Salaries', 'Equipment', 'Maintenance', 'Marketing', 'Transportation', 'Others'];
        }
        
        echo json_encode(['success' => true, 'data' => $categories]);
        exit;
    }
}

// Get unique expense categories for filter
$categories_result = $conn->query("SELECT DISTINCT category FROM expenses WHERE category != '' ORDER BY category");
$expense_categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $expense_categories[] = $row['category'];
    }
}

// Common expense categories
$common_categories = [
    'Rent',
    'Utilities',
    'Salaries',
    'Office Supplies',
    'Equipment',
    'Maintenance',
    'Marketing',
    'Transportation',
    'Raw Materials',
    'Printing Supplies',
    'Packaging',
    'Insurance',
    'Taxes',
    'Legal Fees',
    'Consulting',
    'Training',
    'Travel',
    'Meals',
    'Software',
    'Internet',
    'Phone',
    'Water',
    'Electricity',
    'Others'
];
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
        
        .summary-item {
            text-align: center;
        }
        
        .summary-item h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .summary-item .amount {
            font-size: 24px;
            font-weight: bold;
        }
        
        .date-range {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .date-range input {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .filter-badge {
            background: #e2e8f0;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .filter-badge i {
            cursor: pointer;
            color: #ef4444;
        }
        
        .expense-category-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .category-rent { background: #fee2e2; color: #b91c1c; }
        .category-utilities { background: #fff3cd; color: #856404; }
        .category-salaries { background: #d1e7dd; color: #0f5132; }
        .category-supplies { background: #cfe2ff; color: #084298; }
        .category-equipment { background: #e2d5f3; color: #581c87; }
        .category-marketing { background: #f8d7da; color: #842029; }
        .category-others { background: #e2e8f0; color: #334155; }
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
                <h1><i class="fas fa-chart-line"></i> Expenses Management</h1>
                <button class="btn btn-primary" onclick="showAddExpenseModal()">
                    <i class="fas fa-plus"></i> Add Expense
                </button>
            </div>
            
            <!-- Expense Summary -->
            <div class="expense-summary" id="expenseSummary">
                <div class="summary-item">
                    <h3>Today</h3>
                    <div class="amount" id="todayTotal">₱0.00</div>
                </div>
                <div class="summary-item">
                    <h3>This Week</h3>
                    <div class="amount" id="weekTotal">₱0.00</div>
                </div>
                <div class="summary-item">
                    <h3>This Month</h3>
                    <div class="amount" id="monthTotal">₱0.00</div>
                </div>
                <div class="summary-item">
                    <h3>Total</h3>
                    <div class="amount" id="grandTotal">₱0.00</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchExpense" placeholder="Search expenses...">
                    <i class="fas fa-search"></i>
                </div>
                
                <select id="filterCategory" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach ($common_categories as $cat): ?>
                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
                
                <div class="date-range">
                    <input type="date" id="fromDate" placeholder="From">
                    <span>to</span>
                    <input type="date" id="toDate" placeholder="To">
                </div>
                
                <button class="btn btn-secondary" onclick="loadExpenses()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                
                <button class="btn btn-success" onclick="exportExpenses()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
            
            <!-- Active Filters -->
            <div id="activeFilters" style="margin-bottom: 15px; display: none;">
                <!-- Dynamic filters will appear here -->
            </div>
            
            <!-- Expenses Table -->
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
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="expensesList">
                        <tr>
                            <td colspan="9" class="text-center">Loading expenses...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Expense Modal -->
    <div id="expenseModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-plus-circle"></i> Add Expense</h3>
                <span class="close" onclick="closeExpenseModal()">&times;</span>
            </div>
            
            <div class="modal-body">
                <form id="expenseForm">
                    <input type="hidden" id="expense_id" name="expense_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="expense_date">
                                <i class="fas fa-calendar"></i> Date *
                            </label>
                            <input type="date" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">
                                <i class="fas fa-tag"></i> Category *
                            </label>
                            <select id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($common_categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">
                            <i class="fas fa-align-left"></i> Description *
                        </label>
                        <input type="text" id="description" name="description" placeholder="e.g., Office supplies purchase" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="amount">
                                <i class="fas fa-money-bill"></i> Amount (₱) *
                            </label>
                            <input type="number" id="amount" name="amount" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">
                                <i class="fas fa-credit-card"></i> Payment Method *
                            </label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit">Credit</option>
                                <option value="check">Check</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reference">
                                <i class="fas fa-hashtag"></i> Reference #
                            </label>
                            <input type="text" id="reference" name="reference" placeholder="Receipt/Invoice #">
                        </div>
                        
                        <div class="form-group">
                            <label for="expense_notes">
                                <i class="fas fa-sticky-note"></i> Notes
                            </label>
                            <input type="text" id="expense_notes" name="notes" placeholder="Additional notes">
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeExpenseModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveExpense()">
                    <i class="fas fa-save"></i> Save Expense
                </button>
            </div>
        </div>
    </div>
    
    <!-- Expense Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Expense Details</h3>
                <span class="close" onclick="closeDetailsModal()">&times;</span>
            </div>
            <div class="modal-body" id="expenseDetails">
                <!-- Loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
                <button class="btn btn-primary" onclick="editFromDetails()" id="editFromDetailsBtn">Edit</button>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/expenses.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
    </script>
</body>
</html>