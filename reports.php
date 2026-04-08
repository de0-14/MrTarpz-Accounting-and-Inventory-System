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
        <?php include 'sidebar.php'; ?>
        
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