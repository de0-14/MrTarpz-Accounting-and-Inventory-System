<?php
require_once 'config.php';
if (!isLoggedIn()) {
    redirect('index.php');
}

// --- AJAX AND ACTION HANDLING ---
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    // 1. GENERATE NEW REPORT (PHP + Python)
    if ($_POST['action'] == 'generate_report') {
        $range = sanitize($_POST['range']);
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "report_$timestamp.xlsx";

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

        file_put_contents('temp_data.json', json_encode($package));
        
        // WINDOWS FIX: Use the exact absolute path to your Python installation
        $python_path = "C:/Program Files (x86)/Python314-32/python.exe";
        
        // Execute the script using the path wrapped in quotes
        $python_output = shell_exec("\"$python_path\" convert_to_excel.py $filename 2>&1");

        // If Python threw an error, stop and show it in the browser!
        if (trim($python_output) != "") {
             echo json_encode(['success' => false, 'message' => 'Python Error: ' . $python_output]);
             exit;
        }

        // Log in DB 
        $stmt = $conn->prepare("INSERT INTO reports (report_name, file_path, date_range) VALUES (?, ?, ?)");
        $name = "Financial Report ($range)";
        $stmt->bind_param("sss", $name, $filename, $range);
        
        if (!$stmt->execute()) {
             echo json_encode(['success' => false, 'message' => 'Failed to save record to Reports table: ' . $conn->error]);
             exit;
        }

        echo json_encode(['success' => true]);
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
        if (file_exists($file)) unlink($file); // Remove physical file
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
                <div class="report-actions" style="display: flex; gap: 10px;">
                    <select id="reportRange" class="filter-select" style="margin-bottom: 0;">
                        <option value="Today">Today</option>
                        <option value="1 week">1 Week</option>
                        <option value="1 month">1 Month</option>
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
                </table>
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
                                    <i class="fas fa-download"></i>
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="deleteReport(${report.report_id}, '${report.file_path}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                    });
                    $('#reportsList').html(html || '<tr><td colspan="4" class="text-center">No reports found</td></tr>');
                }
            });
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
                        alert('Report generated successfully!');
                        loadReports();
                    } else {
                        // This will now print the EXACT error from PHP or Python
                        alert('Error: ' + response.message); 
                    }
                },
                error: function(xhr, status, error) {
                    btn.prop('disabled', false).html('<i class="fas fa-cog"></i> Generate Report');
                    alert('Server Error: Check your PHP error logs. Something crashed.');
                    console.error(xhr.responseText); // Look in your browser's Developer Tools Console for this
                }
            });
        }

        function deleteReport(id, path) {
            if (confirm('Delete this report record and file?')) {
                $.post('reports.php', {
                    action: 'delete_report',
                    report_id: id,
                    file_path: path
                }, function() {
                    loadReports();
                });
            }
        }

        function toggleSidebar() {
            $('.sidebar').toggleClass('open');
        }
    </script>
</body>

</html>