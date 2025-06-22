<?php
// it/view_report.php - View a specific monthly report
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a General IT
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_it') {
    header("Location: ../index.php");
    exit;
}

// Check if report ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid report ID";
    header("Location: reports.php");
    exit;
}

$report_id = $_GET['id'];

// Get report details
$sql = "SELECT r.*, u.full_name as created_by_name
        FROM monthly_reports r
        LEFT JOIN users u ON r.created_by = u.user_id
        WHERE r.report_id = ?";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Report not found";
    header("Location: reports.php");
    exit;
}

$report = $result->fetch_assoc();
$report_data = json_decode($report['report_data'], true);

// Re-fetch data if needed for current structure
$sql = "SELECT rf.*, u.full_name as it_staff_name, d.department_name 
        FROM request_forms rf
        LEFT JOIN users u ON rf.it_staff_id = u.user_id
        LEFT JOIN departments d ON rf.department_id = d.department_id
        WHERE rf.job_order_status = 'completed'
        AND MONTH(rf.completion_date) = ? 
        AND YEAR(rf.completion_date) = ?
        ORDER BY rf.completion_date DESC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $report['report_month'], $report['report_year']);
$stmt->execute();
$completed_result = $stmt->get_result();
$completed_requests = [];

if($completed_result->num_rows > 0) {
    while($row = $completed_result->fetch_assoc()) {
        $completed_requests[] = $row;
    }
}

// Get staff performance summary - FIXED QUERY
$sql = "SELECT u.user_id, u.full_name, 
        COUNT(rf.request_id) as completed_count,
        AVG(DATEDIFF(rf.completion_date, rf.created_at)) as avg_days
        FROM users u
        LEFT JOIN request_forms rf ON u.user_id = rf.it_staff_id 
            AND rf.job_order_status = 'completed'
            AND MONTH(rf.completion_date) = ? 
            AND YEAR(rf.completion_date) = ?
        WHERE u.role = 'it_staff'
        GROUP BY u.user_id
        ORDER BY completed_count DESC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $report['report_month'], $report['report_year']);
$stmt->execute();
$staff_result = $stmt->get_result();
$staff_performance = [];

if($staff_result->num_rows > 0) {
    while($row = $staff_result->fetch_assoc()) {
        $staff_performance[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Report - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-earmark-text"></i> Report Details</h2>
            <div>
                <a href="reports.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to Reports
                </a>
                <a href="download_report.php?id=<?= $report_id ?>" class="btn btn-success">
                    <i class="bi bi-download"></i> Download Report
                </a>
                <a href="send_report_to_manager.php" class="btn btn-success">Send Report to General Manager</a>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= htmlspecialchars($report['title']) ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Period:</strong> <?= date('F', mktime(0, 0, 0, $report['report_month'], 1)) ?> <?= $report['report_year'] ?></p>
                        <p><strong>Created By:</strong> <?= htmlspecialchars($report['created_by_name']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created At:</strong> <?= date('M d, Y h:i A', strtotime($report['created_at'])) ?></p>
                        <p><strong>Total Completed Requests:</strong> <?= $report_data['total_count'] ?? count($completed_requests) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Department and Request Type Charts -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Requests by Department</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="deptChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Requests by Type</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="typeChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">IT Staff Performance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Staff Name</th>
                                <th>Completed Requests</th>
                                <th>Avg. Resolution Time (Days)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($staff_performance as $staff): ?>
                                <tr>
                                    <td><?= htmlspecialchars($staff['full_name']) ?></td>
                                    <td><?= $staff['completed_count'] ?? 0 ?></td>
                                    <td><?= is_null($staff['avg_days']) ? 0 : round($staff['avg_days'], 1) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Completed Requests</h5>
            </div>
            <div class="card-body">
                <?php if(count($completed_requests) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Purpose</th>
                                    <th>Department</th>
                                    <th>Type</th>
                                    <th>IT Staff</th>
                                    <th>Completion Date</th>
                                    <th>Resolution Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($completed_requests as $req): ?>
                                    <tr>
                                        <td>#<?= str_pad($req['request_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                        <td><?= htmlspecialchars($req['purpose']) ?></td>
                                        <td><?= htmlspecialchars($req['department_name']) ?></td>
                                        <td>
                                            <?php 
                                            $badge_class = "badge bg-";
                                            $priority_display = "";
                                            switch($req['request_type']) {
                                                case 'high_level':
                                                    $badge_class .= "danger";
                                                    $priority_display = "High Level";
                                                    break;
                                                case 'support':
                                                    $badge_class .= "success";
                                                    $priority_display = "Support";
                                                    break;
                                                default:
                                                    $badge_class .= "warning";
                                                    $priority_display = ucfirst($req['request_type']);
                                            }
                                            ?>
                                            <span class="<?= $badge_class ?>"><?= $priority_display ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($req['it_staff_name']) ?></td>
                                        <td><?= date('M d, Y', strtotime($req['completion_date'])) ?></td>
                                        <td>
                                            <?php
                                                $request_date = new DateTime($req['created_at']);
                                                $completion_date = new DateTime($req['completion_date']);
                                                $interval = $request_date->diff($completion_date);
                                                echo $interval->days . " days";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No completed requests found for this period.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Charts Script -->
    <script>
    <?php if(isset($report_data['dept_stats']) && $report_data['dept_stats']): ?>
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
    <?php endif; ?>
    
    <?php if(isset($report_data['type_stats']) && $report_data['type_stats']): ?>
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
    <?php endif; ?>
    </script>
</body>
</html>