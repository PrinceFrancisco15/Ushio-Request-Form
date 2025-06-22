<?php
// it/generate_report.php - Generate monthly reports for completed requests
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a General IT
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_it') {
    header("Location: ../index.php");
    exit;
}

$general_it_id = $_SESSION['user_id'];

// Default to current month and year if not specified
$filter_month = isset($_POST['month']) ? (int)$_POST['month'] : date('n');
$filter_year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');

// Get available years for completed requests
$sql = "SELECT DISTINCT YEAR(completion_date) as year FROM request_forms WHERE job_order_status = 'completed' ORDER BY year DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$years = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if the form was submitted to generate a report
$report_data = null;
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate'])) {
    // Get the month name
    $month_name = date('F', mktime(0, 0, 0, $filter_month, 1));
    
    // Get stats per department
    $sql = "SELECT 
                d.department_name,
                COUNT(*) as request_count
            FROM request_forms r
            LEFT JOIN departments d ON r.department_id = d.department_id
            WHERE r.job_order_status = 'completed'
            AND MONTH(r.completion_date) = ?
            AND YEAR(r.completion_date) = ?
            GROUP BY r.department_id
            ORDER BY request_count DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $filter_month, $filter_year);
    $stmt->execute();
    $dept_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get stats per IT staff
    $sql = "SELECT 
                u.full_name as staff_name,
                COUNT(*) as completed_count,
                AVG(DATEDIFF(r.completion_date, r.created_at)) as avg_days
            FROM request_forms r
            JOIN users u ON r.it_staff_id = u.user_id
            WHERE r.job_order_status = 'completed'
            AND MONTH(r.completion_date) = ?
            AND YEAR(r.completion_date) = ?
            GROUP BY r.it_staff_id
            ORDER BY completed_count DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $filter_month, $filter_year);
    $stmt->execute();
    $staff_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get stats per request type
    $sql = "SELECT 
                request_type,
                COUNT(*) as type_count
            FROM request_forms
            WHERE job_order_status = 'completed'
            AND MONTH(completion_date) = ?
            AND YEAR(completion_date) = ?
            GROUP BY request_type
            ORDER BY type_count DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $filter_month, $filter_year);
    $stmt->execute();
    $type_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get total requests count for this month
    $sql = "SELECT COUNT(*) as total_count
            FROM request_forms
            WHERE job_order_status = 'completed'
            AND MONTH(completion_date) = ?
            AND YEAR(completion_date) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $filter_month, $filter_year);
    $stmt->execute();
    $total_count = $stmt->get_result()->fetch_assoc()['total_count'];
    
    // Create report data array
    $report_data = [
        'month' => $month_name,
        'year' => $filter_year,
        'total_count' => $total_count,
        'dept_stats' => $dept_stats,
        'staff_stats' => $staff_stats,
        'type_stats' => $type_stats
    ];
    
    // Check if we need to save this report to database
    if(isset($_POST['save_report']) && $_POST['save_report'] == 1) {
        // Format report data as JSON
        $report_json = json_encode($report_data);
        
        // Save to monthly_reports table
        $sql = "INSERT INTO monthly_reports (
                    title, 
                    report_month, 
                    report_year, 
                    report_data,
                    created_by,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())";
        $report_title = "IT Request Report - $month_name $filter_year";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siisi", $report_title, $filter_month, $filter_year, $report_json, $general_it_id);
        
        if($stmt->execute()) {
            $_SESSION['success'] = "Report saved successfully.";
        } else {
            $_SESSION['error'] = "Error saving report: " . $conn->error;
        }
    }
}

// Get a list of previously saved reports
$sql = "SELECT r.*, u.full_name as created_by_name
        FROM monthly_reports r
        LEFT JOIN users u ON r.created_by = u.user_id
        ORDER BY r.created_at DESC
        LIMIT 5";
$saved_reports = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Monthly Report - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-earmark-text"></i> Generate Monthly Report</h2>
            <a href="general_it_dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <!-- Report Generation Form -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Select Month and Year</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="month" class="form-label">Month</label>
                                <select class="form-select" id="month" name="month" required>
                                    <?php for($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($i == $filter_month) ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="year" class="form-label">Year</label>
                                <select class="form-select" id="year" name="year" required>
                                    <?php 
                // Create a range of years (current year down to 5 years ago)
                                $current_year = date('Y');
                                $start_year = $current_year - 100; // Adjust this number to include more years
                
                                for($y = $current_year; $y >= $start_year; $y--): 
                        ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y == $filter_year) ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="save_report" name="save_report" value="1">
                                    <label class="form-check-label" for="save_report">
                                        Save this report
                                    </label>
                                    <div class="form-text">
                                        Save this report to the database to access it later or share with General Manager.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="generate" value="1" class="btn btn-primary">
                                    <i class="bi bi-file-earmark-bar-graph"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Saved Reports -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Recently Saved Reports</h5>
                    </div>
                    <div class="card-body">
                        <?php if(count($saved_reports) > 0): ?>
                            <ul class="list-group">
                                <?php foreach($saved_reports as $report): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($report['title']); ?></h6>
                                            <small class="text-muted">
                                                Created by: <?php echo htmlspecialchars($report['created_by_name']); ?> on
                                                <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                            </small>
                                        </div>
                                        <a href="view_report.php?id=<?php echo $report['report_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-center mb-0">No saved reports found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <?php if($report_data): ?>
                    <!-- Generated Report -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Monthly Report: <?php echo $report_data['month']; ?> <?php echo $report_data['year']; ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Total Completed Requests:</strong> <?php echo $report_data['total_count']; ?>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Requests by Department</h6>
                                    <canvas id="deptChart" height="200"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <h6>Requests by Type</h6>
                                    <canvas id="typeChart" height="200"></canvas>
                                </div>
                            </div>
                            
                            <h6>IT Staff Performance</h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered table-striped">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>Staff Name</th>
                                            <th>Completed Requests</th>
                                            <th>Average Resolution Time (Days)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($report_data['staff_stats']) > 0): ?>
                                            <?php foreach($report_data['staff_stats'] as $staff): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                                                    <td><?php echo $staff['completed_count']; ?></td>
                                                    <td><?php echo round($staff['avg_days'], 1); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center">No data available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <a href="#" class="btn btn-primary me-2" onclick="window.print(); return false;">
                                    <i class="bi bi-printer"></i> Print Report
                                </a>
                                <a href="reports.php" class="btn btn-secondary">
                                    <i class="bi bi-file-earmark-text"></i> All Reports
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <h4 class="text-muted mb-3">No Report Generated Yet</h4>
                            <p>Select a month and year, then click "Generate Report" to view statistics.</p>
                            <i class="bi bi-bar-chart-line display-1 text-muted"></i>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if($report_data): ?>
    <script>
        // Department chart
        const deptCtx = document.getElementById('deptChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($report_data['dept_stats'], 'department_name')); ?>,
                datasets: [{
                    label: 'Number of Requests',
                    data: <?php echo json_encode(array_column($report_data['dept_stats'], 'request_count')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Request type chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'pie',
            data: {
                labels: <?php 
                    $types = array_column($report_data['type_stats'], 'request_type');
                    $types = array_map('ucfirst', $types);
                    echo json_encode($types); 
                ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($report_data['type_stats'], 'type_count')); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>