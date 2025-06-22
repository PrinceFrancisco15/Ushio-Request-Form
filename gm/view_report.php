<?php
// gm/view_report.php - Display report details for General Manager
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
// Check if user is logged in and is General Manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_manager') {
    header("Location: ../index.php");
    exit;
}
$report_id = $_GET['id'] ?? null;
if (!$report_id) {
    header("Location: dashboard.php");
    exit;
}
// Fetch report details
$sql = "SELECT m.*, u.full_name as sender_name 
        FROM monthly_reports m
        LEFT JOIN users u ON m.created_by = u.user_id
        WHERE m.report_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();
if (!$report) {
    header("Location: dashboard.php");
    exit;
}
$report_data = json_decode($report['report_data'], true);
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
            <h2><i class="bi bi-file-earmark-text"></i> <?php echo htmlspecialchars($report['title']); ?></h2>
            <a href="dashboard.php" class="btn btn-secondary">
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
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Report Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>From:</strong> <?php echo htmlspecialchars($report['sender_name']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($report['created_at'])); ?></p>
                        <p><strong>Period:</strong> <?php echo $report_data['month'] . ' ' . $report_data['year']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Total Requests:</strong> <?php echo $report_data['total_count'] ?? 0; ?></p>
                        <p><strong>Report Month:</strong> <?php echo $report_data['month']; ?></p>
                        <p><strong>Report Year:</strong> <?php echo $report_data['year']; ?></p>
                    </div>
                </div>
                
                <!-- File Attachments Section -->
                <?php if(!empty($report_data['attachments'])): ?>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="bi bi-paperclip"></i> Attachments</h5>
                            </div>
                            <div class="card-body">
                                <div class="row row-cols-1 row-cols-md-3 g-4">
                                    <?php foreach($report_data['attachments'] as $index => $attachment): ?>
                                    <div class="col">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <?php
                                                $file_ext = pathinfo($attachment['original_name'], PATHINFO_EXTENSION);
                                                $icon_class = 'bi-file-earmark';
                                                
                                                // Set appropriate icon based on file type
                                                if (in_array($file_ext, ['pdf'])) {
                                                    $icon_class = 'bi-file-earmark-pdf';
                                                } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                                    $icon_class = 'bi-file-earmark-word';
                                                } elseif (in_array($file_ext, ['xls', 'xlsx'])) {
                                                    $icon_class = 'bi-file-earmark-excel';
                                                } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                                    $icon_class = 'bi-file-earmark-image';
                                                } elseif (in_array($file_ext, ['ppt', 'pptx'])) {
                                                    $icon_class = 'bi-file-earmark-slides';
                                                } elseif (in_array($file_ext, ['zip', 'rar'])) {
                                                    $icon_class = 'bi-file-earmark-zip';
                                                }
                                                ?>
                                                <div class="text-center mb-3">
                                                    <i class="bi <?php echo $icon_class; ?> display-1"></i>
                                                </div>
                                                <h6 class="card-title text-truncate" title="<?php echo htmlspecialchars($attachment['original_name']); ?>">
                                                    <?php echo htmlspecialchars($attachment['original_name']); ?>
                                                </h6>
                                                <p class="card-text small text-muted">
                                                    <?php
                                                    $size_kb = round($attachment['file_size'] / 1024, 2);
                                                    $size_display = $size_kb > 1024 ? round($size_kb / 1024, 2) . ' MB' : $size_kb . ' KB';
                                                    echo $size_display;
                                                    ?>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <a href="../uploads/reports/<?php echo $attachment['stored_name']; ?>" 
                                                   class="btn btn-sm btn-primary w-100" target="_blank">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <a href="../uploads/reports/<?php echo $attachment['stored_name']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary w-100 mt-2" 
                                                   download="<?php echo $attachment['original_name']; ?>">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Summary Statistics -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Monthly Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card text-white bg-success mb-3">
                                            <div class="card-body text-center">
                                                <h5 class="card-title">Completed Requests</h5>
                                                <p class="display-4"><?php echo $report_data['total_count'] ?? 0; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-white bg-info mb-3">
                                            <div class="card-body text-center">
                                                <h5 class="card-title">Support Requests</h5>
                                                <p class="display-4"><?php echo $report_data['support_count'] ?? 0; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-white bg-secondary mb-3">
                                            <div class="card-body text-center">
                                                <h5 class="card-title">High Level Requests</h5>
                                                <p class="display-4"><?php echo $report_data['high_level_count'] ?? 0; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-white bg-primary mb-3">
                                            <div class="card-body text-center">
                                                <h5 class="card-title">Average Resolution</h5>
                                                <p class="display-4"><?php echo $report_data['avg_resolution'] ?? 0; ?> hrs</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Request Type Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="requestTypeChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Daily Request Volume</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyRequestChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Section -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">IT Staff Performance</h5>
                            </div>
                            <div class="card-body">
                                <?php if(isset($report_data['staff_performance']) && is_array($report_data['staff_performance']) && count($report_data['staff_performance']) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>IT Staff</th>
                                                <th>Completed Requests</th>
                                                <th>In Progress</th>
                                                <th>Pending</th>
                                                <th>Total Assigned</th>
                                                <th>Avg. Resolution Time</th>
                                                <th>Performance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($report_data['staff_performance'] as $staff): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($staff['name']); ?></td>
                                                <td><?php echo $staff['completed_count']; ?></td>
                                                <td><?php echo $staff['in_progress_count']; ?></td>
                                                <td><?php echo $staff['pending_count']; ?></td>
                                                <td><?php echo $staff['total_assigned']; ?></td>
                                                <td><?php echo $staff['avg_resolution']; ?> hours</td>
                                                <td>
                                                    <div class="progress">
                                                        <?php 
                                                        $performance = $staff['performance'] ?? 0;
                                                        $progress_class = 'bg-success';
                                                        if($performance < 70) $progress_class = 'bg-danger';
                                                        else if($performance < 85) $progress_class = 'bg-warning';
                                                        ?>
                                                        <div class="progress-bar <?php echo $progress_class; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $performance; ?>%" 
                                                             aria-valuenow="<?php echo $performance; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?php echo $performance; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    No staff performance data available in this report.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Department Statistics Section -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-warning text-white">
                                <h5 class="mb-0">Department Statistics</h5>
                            </div>
                            <div class="card-body">
                                <?php if(isset($report_data['dept_stats']) && is_array($report_data['dept_stats']) && count($report_data['dept_stats']) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Request Count</th>
                                                <th>Distribution</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $total_requests = array_sum(array_column($report_data['dept_stats'], 'request_count'));
                                            foreach($report_data['dept_stats'] as $dept): 
                                                $percentage = ($total_requests > 0) ? ($dept['request_count'] / $total_requests) * 100 : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                                <td><?php echo $dept['request_count']; ?></td>
                                                <td>
                                                    <div class="progress">
                                                        <div class="progress-bar bg-info" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $percentage; ?>%" 
                                                             aria-valuenow="<?php echo $percentage; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?php echo round($percentage, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    No department statistics available in this report.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Comments Section -->
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Report Comments</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6>General IT Manager's Notes:</h6>
                                    <div class="p-3 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($report_data['comments'] ?? 'No comments provided.')); ?>
                                    </div>
                                </div>
                                
                                <!-- Display existing GM comments -->
                                <?php if(!empty($report_data['gm_comments']) && is_array($report_data['gm_comments'])): ?>
                                <div class="mb-4">
                                    <h6>General Manager's Comments:</h6>
                                    <?php foreach($report_data['gm_comments'] as $comment): ?>
                                    <div class="card mb-2">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                            <span><?php echo htmlspecialchars($comment['name']); ?></span>
                                            <small><?php echo date('M d, Y H:i', strtotime($comment['timestamp'])); ?></small>
                                        </div>
                                        <div class="card-body">
                                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Add your comment form -->
                                <form action="add_report_comment.php" method="post">
                                    <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Add Your Comment</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Enter your comments or feedback..."></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-chat-dots"></i> Submit Comment
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart for Request Type Distribution
        const typeCtx = document.getElementById('requestTypeChart').getContext('2d');
        const requestTypeChart = new Chart(typeCtx, {
            type: 'pie',
            data: {
                labels: ['Support Requests', 'High Level Requests'],
                datasets: [{
                    label: 'Request Types',
                    data: [
                        <?php echo $report_data['support_count'] ?? 0; ?>, 
                        <?php echo $report_data['high_level_count'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        'rgba(23, 162, 184, 0.7)',
                        'rgba(108, 117, 125, 0.7)'
                    ],
                    borderColor: [
                        'rgba(23, 162, 184, 1)',
                        'rgba(108, 117, 125, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Request Type Distribution'
                    }
                }
            }
        });
        
        // Chart for Daily Requests
        <?php 
        // Prepare daily data
        $days = [];
        $counts = [];
        
        if(isset($report_data['daily_data']) && is_array($report_data['daily_data'])) {
            foreach($report_data['daily_data'] as $daily) {
                $days[] = $daily['day'];
                $counts[] = $daily['count'];
            }
        }
        ?>
        
        const dailyCtx = document.getElementById('dailyRequestChart').getContext('2d');
        const dailyRequestChart = new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($days); ?>,
                datasets: [{
                    label: 'Daily Requests',
                    data: <?php echo json_encode($counts); ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
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
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Daily Request Volume'
                    }
                }
            }
        });
    </script>
</body>
</html>