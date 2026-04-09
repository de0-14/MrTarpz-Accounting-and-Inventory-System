<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">MT</div>
        <h3>Mr. Tarpz</h3>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="products.php"><i class="fas fa-box"></i> Products</a>
        <a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory</a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="payments.php"><i class="fas fa-money-bill"></i> Payments</a>
        <a href="expenses.php"><i class="fas fa-chart-line"></i> Expenses</a>
        <a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <div class="sidebar-footer" style="margin-top: 15px">
        <p><i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?></p>
        <p><small><?php echo $_SESSION['role']; ?></small></p>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Get the current page filename from the URL (e.g., "orders.php")
    let currentPage = window.location.pathname.split('/').pop();

    // Default to dashboard if viewing the root directory
    if (currentPage === '') {
        currentPage = 'dashboard.php'; 
    }

    // Select all the links inside the sidebar navigation
    const navLinks = document.querySelectorAll('.sidebar-nav a');

    // Loop through each link
    navLinks.forEach(link => {
        // Remove any hardcoded 'active' classes to reset the state
        link.classList.remove('active');

        // Get the value of the href attribute (e.g., "products.php")
        const linkHref = link.getAttribute('href');

        // If the link's href matches the current page, add the 'active' class
        if (linkHref === currentPage) {
            link.classList.add('active');
        }
    });
});
</script>