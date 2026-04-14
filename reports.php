<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// Create reports directory if it doesn't exist
$reports_dir = __DIR__ . '/reports/';
if (!file_exists($reports_dir)) {
    mkdir($reports_dir, 0777, true);
}

// --- AJAX AND ACTION HANDLING ---
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    // 1. GENERATE NEW REPORT (PHP + Python)
    if ($_POST['action'] == 'generate_report') {
        $range = sanitize($_POST['range']);
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "report_$timestamp.xlsx";
        
        // Save to reports folder
        $filepath = $reports_dir . $filename;

        // Date filter logic
        switch ($range) {
            case 'Today': $filter = "CURDATE()"; break;
            case '1 week': $filter = "DATE_SUB(NOW(), INTERVAL 1 WEEK)"; break;
            case '1 month': $filter = "DATE_SUB(NOW(), INTERVAL 1 MONTH)"; break;
            case '6 months': $filter = "DATE_SUB(NOW(), INTERVAL 6 MONTH)"; break;
            default: $filter = "DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }

        // 1. Fetch Expenses (WITH ERROR CHECKING)
        $exp_res = $conn->query("SELECT * FROM expenses WHERE expense_date >= $filter");
        if (!$exp_res) {
            echo json_encode(['success' => false, 'message' => 'DB Error (Expenses): ' . $conn->error]);
            exit;
        }
        
        $expenses = [];
        $total_expenses = 0;
        while ($row = $exp_res->fetch_assoc()) {
            $expenses[] = $row;
            $total_expenses += $row['amount'];
        }

        // 2. Fetch Payments (WITH ERROR CHECKING)
        $pay_res = $conn->query("SELECT * FROM payments WHERE payment_date >= $filter");
        if (!$pay_res) {
            echo json_encode(['success' => false, 'message' => 'DB Error (Payments): ' . $conn->error]);
            exit;
        }

        $payments = [];
        $total_payments = 0;
        while ($row = $pay_res->fetch_assoc()) {
            $payments[] = $row;
            $total_payments += $row['amount'];
        }

        // Combine into a structured package for Python
        $package = [
            'expenses' => $expenses,
            'payments' => $payments,
            'totals' => [
                'total_expenses' => $total_expenses,
                'total_payments' => $total_payments
            ]
        ];

        file_put_contents(__DIR__ . '/temp_data.json', json_encode($package));
        
        // Try different Python commands
        $python_output = "";
        $python_commands = [
            "py convert_to_excel.py \"$filepath\" 2>&1",
            "python convert_to_excel.py \"$filepath\" 2>&1",
            "python3 convert_to_excel.py \"$filepath\" 2>&1"
        ];
        
        foreach ($python_commands as $cmd) {
            $test_output = shell_exec($cmd);
            if (file_exists($filepath)) {
                $python_output = $test_output;
                break;
            }
        }

        // If Python threw an error or file not created, show error
        if (!file_exists($filepath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to generate report. Python error: ' . $python_output]);
            exit;
        }

        // Store relative path in database (for web access)
        $relative_path = "reports/" . $filename;
        
        // Log in DB 
        $stmt = $conn->prepare("INSERT INTO reports (report_name, file_path, date_range) VALUES (?, ?, ?)");
        $name = "Financial Report ($range)";
        $stmt->bind_param("sss", $name, $relative_path, $range);
        
        if (!$stmt->execute()) {
             echo json_encode(['success' => false, 'message' => 'Failed to save record to Reports table: ' . $conn->error]);
             exit;
        }

        // Clean up temp file
        if (file_exists(__DIR__ . '/temp_data.json')) {
            unlink(__DIR__ . '/temp_data.json');
        }

        echo json_encode(['success' => true, 'file_path' => $relative_path]);
        exit;
    }

    // 2. FETCH PREVIOUS REPORTS
    if ($_POST['action'] == 'get_reports') {
        $search = isset($_POST['search']) ? sanitize($_POST['search']) : '';
        $sql = "SELECT * FROM reports WHERE report_name LIKE '%$search%' OR created_at LIKE '%$search%' ORDER BY created_at DESC";
        $result = $conn->query($sql);
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $reports]);
        exit;
    }

    // 3. DELETE REPORT RECORD
    if ($_POST['action'] == 'delete_report') {
        $id = sanitize($_POST['report_id']);
        $file = sanitize($_POST['file_path']);
        
        // Delete physical file from reports folder
        $full_path = __DIR__ . '/' . $file;
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        
        $conn->query("DELETE FROM reports WHERE report_id = '$id'");
        echo json_encode(['success' => true]);
        exit;
    }
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
    <style>
        .report-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
        }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            margin: 0 2px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>

            <div class="content-header">
                <h1><i class="fas fa-file-invoice"></i> Reports Management</h1>
                <div class="report-actions">
                    <select id="reportRange" class="filter-select" style="margin-bottom: 0;">
                        <option value="Today">Today</option>
                        <option value="1 week">1 Week</option>
                        <option value="1 month" selected>1 Month</option>
                        <option value="6 months">6 Months</option>
                    </select>
                    <button class="btn btn-primary" onclick="generateReport()">
                        <i class="fas fa-cog"></i> Generate Report
                    </button>
                </div>
            </div>

            <div class="filters-bar">
                <div class="search-box">
                    <input type="text" id="searchReport" placeholder="Search by date (YYYY-MM-DD)..." onkeyup="loadReports()">
                    <i class="fas fa-search"></i>
                </div>
                <button class="btn btn-secondary" onclick="loadReports()">
                    <i class="fas fa-sync-alt"></i> Refresh List
                </button>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date Generated</th>
                            <th>Report Name</th>
                            <th>Range</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="reportsList">
                        <tr>
                            <td colspan="4" class="text-center">Loading reports...</td>
                        </tr>
                    </tbody>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            loadReports();
        });

        function loadReports() {
            const search = $('#searchReport').val();
            $.post('reports.php', {
                action: 'get_reports',
                search: search
            }, function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(report => {
                        html += `
                        <tr>
                            <td>${report.created_at}</td>
                            <td>${report.report_name}</td>
                            <td><span class="badge badge-info">${report.date_range}</span></td>
                            <td>
                                <a href="${report.file_path}" class="btn btn-sm btn-secondary" download>
                                    <i class="fas fa-download"></i> Download
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="deleteReport(${report.report_id}, '${report.file_path}')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>`;
                    });
                    $('#reportsList').html(html || '<tr><td colspan="4" class="text-center">No reports found</td></tr>');
                }
            }, 'json');
        }

        function generateReport() {
            const range = $('#reportRange').val();
            const btn = $('.btn-primary');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generating...');

            $.ajax({
                url: 'reports.php',
                type: 'POST',
                data: { action: 'generate_report', range: range },
                dataType: 'json',
                success: function(response) {
                    btn.prop('disabled', false).html('<i class="fas fa-cog"></i> Generate Report');
                    if (response.success) {
                        alert('✅ Report generated successfully!');
                        loadReports();
                    } else {
                        alert('❌ Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    btn.prop('disabled', false).html('<i class="fas fa-cog"></i> Generate Report');
                    alert('Server Error: ' + error);
                    console.error(xhr.responseText);
                }
            });
        }

        function deleteReport(id, path) {
            if (confirm('Delete this report record and file?')) {
                $.post('reports.php', {
                    action: 'delete_report',
                    report_id: id,
                    file_path: path
                }, function(response) {
                    if (response.success) {
                        loadReports();
                        alert('Report deleted successfully');
                    }
                }, 'json');
            }
        }

        function toggleSidebar() {
            $('.sidebar').toggleClass('open');
        }
    </script>
</body>

</html>