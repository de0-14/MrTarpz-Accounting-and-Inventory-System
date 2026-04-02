<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <img src="images/logo.png" alt="Logo" class="sidebar-logo">
                <h3>Mr. Tarpz</h3>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a>
                <a href="products.php"><i class="fas fa-box"></i> Products</a>
                <a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory</a>
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="payments.php"><i class="fas fa-money-bill"></i> Payments</a>
                <a href="expenses.php"><i class="fas fa-chart-line"></i> Expenses</a>
                <a href="reports.php" class="active"><i class="fas fa-file-alt"></i> Reports</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
            
            <div class="sidebar-footer">
                <p>Welcome, <?php echo $_SESSION['full_name']; ?></p>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="content-header">
                <h1>Reports</h1>
            </div>
            
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Sales Report</h3>
                    </div>
                    <div class="card-body">
                        <p>Generate sales reports by date range</p>
                        <button class="btn btn-primary">Generate Report</button>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Inventory Report</h3>
                    </div>
                    <div class="card-body">
                        <p>View current inventory status</p>
                        <button class="btn btn-primary">Generate Report</button>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Financial Report</h3>
                    </div>
                    <div class="card-body">
                        <p>Profit and loss statement</p>
                        <button class="btn btn-primary">Generate Report</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>