<?php
// it/view_completed_request.php - View completed request details
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is either General IT or IT staff
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'general_it' && $_SESSION['role'] != 'it_staff')) {
    header("Location: ../index.php");
    exit;
}

if(!isset($_GET['id'])) {
    // Redirect based on role
    if($_SESSION['role'] == 'general_it') {
        header("Location: completed_history.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$request_id = $_GET['id'];

// Get request details
$sql = "SELECT r.*, d.department_name, 
        u1.full_name as requestor_name, u1.email as requestor_email,
        u2.full_name as it_staff_name, u2.email as it_staff_email
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.requestor_id = u1.user_id
        LEFT JOIN users u2 ON r.it_staff_id = u2.user_id
        WHERE r.request_id = ? AND r.job_order_status = 'completed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Completed request not found.";
    if($_SESSION['role'] == 'general_it') {
        header("Location: completed_history.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$request = $result->fetch_assoc();

// Get request items
$sql = "SELECT * FROM request_items WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);

// Get activity logs for this request - Fixed duplicated ORDER BY
$sql = "SELECT al.*, u.full_name 
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE al.request_id = ?
        ORDER BY al.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Completed Request #<?php echo $request_id; ?> - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-check-circle"></i> Completed Request #<?php echo $request_id; ?></h2>
            <div>
                <?php if($_SESSION['role'] == 'general_it'): ?>
                    <a href="completed_history.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                <?php endif; ?>
                <a href="print_job_order.php?id=<?php echo $request_id; ?>" class="btn btn-success" target="_blank">
                    <i class="bi bi-printer"></i> Print Job Order
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Request Details Card -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Request Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Request Type:</strong> <?php echo ucfirst(htmlspecialchars($request['request_type'])); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($request['department_name']); ?></p>
                                <p><strong>Requestor:</strong> <?php echo htmlspecialchars($request['requestor_name']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-success">Completed</span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Submission Date:</strong> <?php echo date('M d, Y', strtotime($request['created_at'])); ?></p>
                                <p><strong>Assigned Date:</strong> <?php echo !empty($request['date_assigned']) ? date('M d, Y', strtotime($request['date_assigned'])) : 'N/A'; ?></p>
                                <p><strong>Completion Date:</strong> <?php echo date('M d, Y', strtotime($request['completion_date'])); ?></p>
                                <p><strong>Assigned To:</strong> <?php echo htmlspecialchars($request['it_staff_name']); ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Purpose:</h6>
                            <div class="border rounded p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($request['purpose'])); ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Completion Notes:</h6>
                            <div class="border rounded p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($request['completion_notes'])); ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Request Items:</h6>
                            <table class="table table-bordered table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item Type</th>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($items) > 0): ?>
                                        <?php foreach($items as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['item_type']); ?></td>
                                                <td><?php echo htmlspecialchars($item['item_description']); ?></td>
                                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No items found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Activity Log Card -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Activity Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php if(count($logs) > 0): ?>
                                <?php foreach($logs as $log): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($log['action']); ?></h6>
                                            <p class="mb-1"><?php echo htmlspecialchars($log['details']); ?></p>
                                            <div class="text-muted small">
                                                <span><?php echo htmlspecialchars($log['full_name']); ?></span> &bull;
                                                <span><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center">No activity logs found</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
            border-left: 2px solid #ccc;
        }
        .timeline-marker {
            position: absolute;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #0dcaf0;
            left: -7px;
            top: 4px;
        }
        .timeline-content {
            margin-left: 15px;
            padding-bottom: 10px;
        }
    </style>
</body>
</html>