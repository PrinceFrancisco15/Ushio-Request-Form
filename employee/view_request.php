<?php
// employee/view_request.php - View request details
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


// Check if user is logged in and is an employee
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: ../index.php");
    exit;
}

if(!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$request_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Check if this request belongs to this employee
$sql = "SELECT r.*, d.department_name, requestor_name, 
               u2.full_name as manager_name, u3.full_name as gm_name,
               u4.full_name as it_staff_name, i.item_type, i.item_description, i.quantity,
               r.purpose  /* Add this line if it's missing */
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.requestor_id = u1.user_id
        LEFT JOIN users u2 ON r.manager_id = u2.user_id
        LEFT JOIN users u3 ON r.gm_id = u3.user_id
        LEFT JOIN users u4 ON r.it_staff_id = u4.user_id
        LEFT JOIN request_items i ON r.request_id = i.request_id
        WHERE r.request_id = ? AND r.requestor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Request not found or you don't have permission to view it.";
    header("Location: dashboard.php");
    exit;
}

$request = $result->fetch_assoc();

// Get activity logs for this request
$sql = "SELECT l.*, u.full_name, requestor_name
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.user_id
        WHERE l.request_id = ?
        ORDER BY l.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$logs_result = $stmt->get_result();
$activity_logs = $logs_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Request Details</h5>
                        <span class="badge bg-light text-dark">ID: <?php echo $request['request_id']; ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Request Type:</strong> <?php echo ucfirst($request['request_type']); ?></p>
                                <p><strong>Request Category:</strong> <?php echo ucfirst($request['request_category']); ?></p>
                                <p><strong>Requestor Name:</strong> <?php echo $request['requestor_name']; ?></p>
                                <p><strong>Department:</strong> <?php echo $request['department_name']; ?></p>
                                <p><strong>Created On:</strong> <?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Status:</strong>
                                    <?php 
                                    if ($request['job_order_status'] == 'completed') {
                                        echo '<span class="badge bg-success">Completed</span>';
                                    } elseif ($request['job_order_status'] == 'accepted') {
                                        echo '<span class="badge bg-primary">In Progress</span>';
                                    } elseif ($request['request_type'] == 'support' && $request['manager_status'] == 'approved') {
                                        echo '<span class="badge bg-info">Pending IT</span>';
                                    } elseif ($request['request_type'] == 'high_level' && $request['gm_status'] == 'approved') {
                                        echo '<span class="badge bg-info">Pending IT</span>';
                                    } elseif ($request['manager_status'] == 'approved' && $request['gm_status'] == 'pending') {
                                        echo '<span class="badge bg-warning">Pending GM</span>';
                                    } elseif ($request['manager_status'] == 'pending') {
                                        echo '<span class="badge bg-warning">Pending Manager</span>';
                                    } elseif ($request['manager_status'] == 'rejected') {
                                        echo '<span class="badge bg-danger">Rejected by Manager</span>';
                                    } elseif ($request['gm_status'] == 'rejected') {
                                        echo '<span class="badge bg-danger">Rejected by GM</span>';
                                    }
                                    ?>
                                </p>
                                <p><strong>Manager:</strong> <?php echo $request['manager_name']; ?></p>
                                <?php if($request['request_type'] == 'high_level'): ?>
                                <p><strong>General Manager:</strong> <?php echo $request['gm_name']; ?></p>
                                <?php endif; ?>
                                <?php if($request['it_staff_id']): ?>
                                <p><strong>IT Staff Assigned:</strong> <?php echo $request['it_staff_name']; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Purpose:</h6>
                            <p class="border rounded p-3 bg-light"><?php echo nl2br($request['purpose']); ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Item Details:</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item Type</th>
                                            <th>Description</th>
                                            <th>Quantity</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo $request['item_type']; ?></td>
                                            <td><?php echo $request['item_description']; ?></td>
                                            <td><?php echo $request['quantity']; ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Approval Status:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-2">
                                        <div class="card-header">Manager</div>
                                        <div class="card-body">
                                            <p><strong>Status:</strong> 
                                                <?php if($request['manager_status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif($request['manager_status'] == 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php elseif($request['manager_status'] == 'rejected'): ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </p>
                                            <?php if($request['manager_status'] != 'pending'): ?>
                                                <p><strong>Comment:</strong> <?php echo $request['manager_comment'] ? $request['manager_comment'] : 'No comment'; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if($request['request_type'] == 'high_level'): ?>
                                <div class="col-md-6">
                                    <div class="card mb-2">
                                        <div class="card-header">General Manager</div>
                                        <div class="card-body">
                                            <p><strong>Status:</strong> 
                                                <?php if($request['gm_status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif($request['gm_status'] == 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php elseif($request['gm_status'] == 'rejected'): ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php elseif($request['gm_status'] == 'not_required'): ?>
                                                    <span class="badge bg-secondary">Not Required</span>
                                                <?php endif; ?>
                                            </p>
                                            <?php if($request['gm_status'] != 'pending' && $request['gm_status'] != 'not_required'): ?>
                                                <p><strong>Comment:</strong> <?php echo $request['gm_comment'] ? $request['gm_comment'] : 'No comment'; ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if($request['job_order_status'] != 'pending'): ?>
                        <div class="mb-3">
                            <h6>IT Department Status:</h6>
                            <div class="card">
                                <div class="card-body">
                                    <p><strong>Status:</strong> 
                                        <?php if($request['job_order_status'] == 'accepted'): ?>
                                            <span class="badge bg-primary">In Progress</span>
                                        <?php elseif($request['job_order_status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php endif; ?>
                                    </p>
                                    <p><strong>IT Staff:</strong> <?php echo $request['it_staff_name']; ?></p>
                                    <?php if($request['job_order_status'] == 'completed'): ?>
                                        <p><strong>Completion Notes:</strong> <?php echo $request['completion_notes'] ? $request['completion_notes'] : 'No notes provided'; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                            
                            <?php if($request['manager_status'] == 'pending'): ?>
                            <div>
                                <a href="edit_request.php?id=<?php echo $request_id; ?>" class="btn btn-primary">Edit Request</a>
                                <a href="delete_request.php?id=<?php echo $request_id; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this request?')">Delete</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Activity Log</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if(count($activity_logs) > 0): ?>
                                <?php foreach($activity_logs as $log): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                    <p class="mb-0"><?php echo $request['requestor_name']; ?></p>
                                        <strong><?php echo $log['action']; ?></strong>
                                        <small><?php echo date('M d, h:i A', strtotime($log['created_at'])); ?></small>
                                    </div>               
                                    <?php if($log['details']): ?>
                                    <small class="text-muted"><?php echo $log['details']; ?></small>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item">No activity recorded yet.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>