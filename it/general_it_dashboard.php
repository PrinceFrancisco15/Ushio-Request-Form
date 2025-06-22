<?php
// it/general_it_dashboard.php - Dashboard for General IT manager
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

// Get count of all requests
// Get count of all requests including those with NULL status
$sql = "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN (job_order_status = 'pending' OR job_order_status IS NULL) THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN job_order_status = 'assigned' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN job_order_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN job_order_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN job_order_status = 'on_hold' THEN 1 ELSE 0 END) as on_hold
        FROM request_forms";
$result = $conn->query($sql);
$counts = $result->fetch_assoc();

// Get monthly completed requests for chart (last 6 months)
$sql = "SELECT 
            YEAR(completion_date) as year,
            MONTH(completion_date) as month,
            MONTHNAME(completion_date) as month_name,
            COUNT(*) as completed_count
        FROM request_forms
        WHERE job_order_status = 'completed'
        AND completion_date IS NOT NULL
        AND completion_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(completion_date), MONTH(completion_date)
        ORDER BY YEAR(completion_date), MONTH(completion_date)";
$result = $conn->query($sql);
$monthly_data = [];
$months = [];
$counts_data = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $months[] = $row['month_name'];
        $counts_data[] = $row['completed_count'];
        $monthly_data[] = $row;
    }
}

// Get recent completed requests (last 10)
$sql = "SELECT r.*, d.department_name, u1.full_name as requestor_name, u2.full_name as staff_name
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.requestor_id = u1.user_id
        LEFT JOIN users u2 ON r.it_staff_id = u2.user_id
        WHERE r.job_order_status = 'completed'
        ORDER BY r.completion_date DESC LIMIT 10";
$result = $conn->query($sql);
$recent_completed = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $recent_completed[] = $row;
    }
}

// Get IT staff performance
$sql = "SELECT 
            u.user_id,
            u.full_name,
            COUNT(r.request_id) as total_completed,
            AVG(DATEDIFF(r.completion_date, r.created_at)) as avg_days_to_complete
        FROM users u
        LEFT JOIN request_forms r ON u.user_id = r.it_staff_id AND r.job_order_status = 'completed'
        WHERE u.role = 'it_staff'
        GROUP BY u.user_id
        ORDER BY total_completed DESC";
$result = $conn->query($sql);
$staff_performance = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $staff_performance[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General IT Dashboard - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-speedometer2"></i> General IT Dashboard</h2>
            <a href="generate_report.php" class="btn btn-success">
                <i class="bi bi-file-earmark-text"></i> Generate Monthly Report
            </a>
        </div>
        
        <!-- Stats Overview -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-center bg-primary text-white">
                    <div class="card-body">
                        <h3><?php echo $counts['total_requests']; ?></h3>
                        <p class="mb-0">Total Requests</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center bg-secondary text-white">
                    <div class="card-body">
                        <h3><?php echo $counts['pending']; ?></h3>
                        <p class="mb-0">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <h3><?php echo $counts['assigned']; ?></h3>
                        <p class="mb-0">Assigned</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <h3><?php echo $counts['in_progress']; ?></h3>
                        <p class="mb-0">In Progress</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <h3><?php echo $counts['completed']; ?></h3>
                        <p class="mb-0">Completed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center bg-danger text-white">
                    <div class="card-body">
                        <h3><?php echo $counts['on_hold']; ?></h3>
                        <p class="mb-0">On Hold</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <!-- Monthly Completed Requests Chart -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Monthly Completed Requests</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- IT Staff Performance -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">IT Staff Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Staff Name</th>
                                        <th>Completed</th>
                                        <th>Avg Days</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($staff_performance) > 0): ?>
                                        <?php foreach($staff_performance as $staff): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($staff['full_name']); ?></td>
                                                <td><?php echo $staff['total_completed']; ?></td>
                                                <td><?php echo round($staff['avg_days_to_complete'], 1); ?></td>
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
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Completed Requests -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Recent Completed Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Request Type</th>
                                <th>Department</th>
                                <th>Requestor</th>
                                <th>Assigned To</th>
                                <th>Completed Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($recent_completed) > 0): ?>
                                <?php foreach($recent_completed as $request): ?>
                                    <tr>
                                        <td><?php echo $request['request_id']; ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($request['request_type'])); ?></td>
                                        <td><?php echo htmlspecialchars($request['department_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['requestor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['staff_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($request['completion_date'])); ?></td>
                                        <td>
                                            <a href="view_completed_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No recent completed requests</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Archive Section -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-archive mr-1"></i>
        Monthly Report Archives
    </div>
    <div class="card-body">
        <p>Access previously archived monthly reports:</p>
        <a href="view_archives.php" class="btn btn-primary">View Archives</a>
    </div>
</div>
                <div class="text-end mt-3">
                    <a href="completed_history.php" class="btn btn-success">View All Completed Requests</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart for monthly completed requests
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Completed Requests',
                    data: <?php echo json_encode($counts_data); ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
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
    </script>
</body>
</html>