<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Get dashboard statistics
$stats = [];

// Total products
$result = $conn->query("SELECT COUNT(*) as count FROM products");
$stats['total_products'] = $result->fetch_assoc()['count'];

// Low stock products
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock_quantity <= reorder_level");
$stats['low_stock'] = $result->fetch_assoc()['count'];

// Today's orders
$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) = CURDATE()");
$stats['today_orders'] = $result->fetch_assoc()['count'];

// Today's sales
$result = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE DATE(order_date) = CURDATE()");
$stats['today_sales'] = $result->fetch_assoc()['total'];

// Pending orders
$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'");
$stats['pending_orders'] = $result->fetch_assoc()['count'];

// Monthly sales
$result = $conn->query("SELECT 
    DATE_FORMAT(order_date, '%Y-%m') as month,
    COUNT(*) as order_count,
    COALESCE(SUM(total_amount), 0) as total_sales
    FROM orders 
    WHERE order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month DESC");
$monthly_sales = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mr. Tarpz Printing Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><img="logo.jpg"></div>
                <h3>Mr. Tarpz</h3>
            </div>
            
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="products.php"><i class="fas fa-box"></i> Products</a>
                <a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory</a>
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
                <a href="payments.php"><i class="fas fa-money-bill"></i> Payments</a>
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
            <div class="content-header">
                <h1>Dashboard</h1>
                <div class="date-display"><?php echo date('F d, Y'); ?></div>
            </div>
            <!-- Mobile Menu Toggle -->
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
                </button>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['total_products']; ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon yellow">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['low_stock']; ?></h3>
                        <p>Low Stock Items</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $stats['today_orders']; ?></h3>
                        <p>Today's Orders</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-details">
                        <h3>₱<?php echo number_format($stats['today_sales'], 2); ?></h3>
                        <p>Today's Sales</p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders and Low Stock -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                        <a href="orders.php" class="btn-link">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="recent-orders">
                                <tr>
                                    <td colspan="4" style="text-align: center;">Loading recent orders...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-circle"></i> Low Stock Alert</h3>
                        <a href="inventory.php" class="btn-link">Manage <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Reorder Level</th>
                                </tr>
                            </thead>
                            <tbody id="low-stock-items">
                                <tr>
                                    <td colspan="3" style="text-align: center;">Loading low stock items...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Sales Chart -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Monthly Sales (Last 6 Months)</h3>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" style="width:100%; height:300px;"></canvas>
                    <?php if (empty($monthly_sales)): ?>
                        <p style="text-align: center; color: #666; margin-top: 20px;">No sales data available yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/dashboard.js"></script>
    <script>
        // Monthly sales data for chart
        const monthlyData = <?php echo json_encode($monthly_sales ?: []); ?>;
    </script>
</body>
</html>