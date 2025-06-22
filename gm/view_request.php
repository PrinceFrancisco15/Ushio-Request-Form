<?php
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_manager') {
    header("Location: ../index.php");
    exit;
}

if(!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$request_id = $_GET['id'];
$gm_id = $_SESSION['user_id'];

$sql = "SELECT * FROM request_forms WHERE request_id = ? AND gm_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $gm_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "You don't have permission to view this request.";
    header("Location: dashboard.php");
    exit;
}

$sql = "SELECT r.*, d.department_name, requestor_name, u2.full_name as manager_name, u3.full_name as gm_name, u4.full_name as it_staff_name
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.requestor_id = u1.user_id
        LEFT JOIN users u2 ON r.manager_id = u2.user_id
        LEFT JOIN users u3 ON r.gm_id = u3.user_id
        LEFT JOIN users u4 ON r.it_staff_id = u4.user_id
        WHERE r.request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

$sql = "SELECT * FROM request_items WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);

$sql = "SELECT * FROM request_attachments WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$attachments = $result->fetch_all(MYSQLI_ASSOC);

$sql = "SELECT l.*, u.full_name, u.role
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.user_id
        WHERE l.request_id = ?
        ORDER BY l.created_at DESC";
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
    <title>View Request - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .attachment-preview {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .file-icon {
            font-size: 4rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Request #<?php echo $request_id; ?> Details</h5>
                        <div>
                            <?php if($request['manager_status'] == 'approved' && $request['gm_status'] == 'pending'): ?>
                                <a href="approve_request.php?id=<?php echo $request_id; ?>" class="btn btn-success btn-sm">Approve</a>
                                <a href="reject_request.php?id=<?php echo $request_id; ?>" class="btn btn-danger btn-sm">Reject</a>
                            <?php endif; ?>
                            <a href="dashboard.php" class="btn btn-light btn-sm">Back to Dashboard</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Request Information</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th class="table-light" width="40%">Requestor</th>
                                        <td><?php echo $request['requestor_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th class="table-light">Department</th>
                                        <td><?php echo $request['department_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th class="table-light">Request Type</th>
                                        <td><?php echo ucfirst($request['request_type']); ?></td>
                                    </tr>
                                    <th class="table-light">Request Category</th>
                                    <td><?php echo ucfirst($request['request_category']); ?></td>
                                    <tr>
                                        <th class="table-light">Date Created</th>
                                        <td><?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="table-light">Purpose</th>
                                        <td><?php echo $request['purpose']; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Status Information</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th class="table-light" width="40%">Manager Approval</th>
                                        <td>
                                            <?php 
                                            switch($request['manager_status']) {
                                                case 'pending':
                                                    echo '<span class="badge bg-warning">Pending</span>';
                                                    break;
                                                case 'approved':
                                                    echo '<span class="badge bg-success">Approved</span>';
                                                    break;
                                                case 'rejected':
                                                    echo '<span class="badge bg-danger">Rejected</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="table-light">Manager Comment</th>
                                        <td><?php echo $request['manager_comment'] ?: 'No comment'; ?></td>
                                    </tr>
                                    <tr>
                                        <th class="table-light">GM Approval</th>
                                        <td>
                                            <?php 
                                            if($request['request_type'] == 'support') {
                                                echo '<span class="badge bg-secondary">Not Required</span>';
                                            } else {
                                                switch($request['gm_status']) {
                                                    case 'pending':
                                                        echo '<span class="badge bg-warning">Pending</span>';
                                                        break;
                                                    case 'approved':
                                                        echo '<span class="badge bg-success">Approved</span>';
                                                        break;
                                                    case 'rejected':
                                                        echo '<span class="badge bg-danger">Rejected</span>';
                                                        break;
                                                    case 'not_required':
                                                        echo '<span class="badge bg-secondary">Not Required</span>';
                                                        break;
                                                }
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="table-light">GM Comment</th>
                                        <td><?php echo $request['gm_comment'] ?: 'No comment'; ?></td>
                                    </tr>
                                    <tr>
                                        <th class="table-light">IT Status</th>
                                        <td>
                                            <?php 
                                            switch($request['job_order_status']) {
                                                case 'pending':
                                                    echo '<span class="badge bg-warning">Pending</span>';
                                                    break;
                                                case 'accepted':
                                                    echo '<span class="badge bg-info">Accepted</span>';
                                                    break;
                                                case 'completed':
                                                    echo '<span class="badge bg-success">Completed</span>';
                                                    break;
                                                case 'cancelled':
                                                    echo '<span class="badge bg-danger">Cancelled</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <h6 class="mb-3">Request Items</h6>
                        <table class="table table-bordered mb-4">
                            <thead class="table-light">
                                <tr>
                                    <th>Item Type</th>
                                    <th>Description</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($items as $item): ?>
                                    <tr>
                                        <td><?php echo $item['item_type']; ?></td>
                                        <td><?php echo $item['item_description']; ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <h6 class="mb-3">Attachments</h6>
                        <div class="row mb-4">
                            <?php if(count($attachments) > 0): ?>
                                <?php foreach($attachments as $attachment): ?>
                                    <div class="col-md-3 mb-3">
                                        <div class="card">
                                            <?php 
                                            $file_path = $attachment['file_path'];
                                            $file_name = $attachment['file_name'];
                                            $file_type = strtolower($attachment['file_type']);
                                            
                                            if(strpos($file_type, 'image') !== false): ?>
                                                <img src="<?php echo $file_path; ?>" class="card-img-top attachment-preview" alt="<?php echo $file_name; ?>">
                                            <?php else: ?>
                                                <div class="card-img-top text-center pt-3">
                                                    <i class="bi bi-file-earmark-text file-icon"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="card-body p-2">
                                                <p class="card-text small text-truncate"><?php echo $file_name; ?></p>
                                                <a href="<?php echo $file_path; ?>" class="btn btn-sm btn-primary w-100" target="_blank">View</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <p class="text-center">No attachments found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h6 class="mb-3">Activity Log</h6>
                        <table class="table table-bordered table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                                        <td><?php echo $log['full_name']; ?></td>
                                        <td><?php echo ucfirst($log['role']); ?></td>
                                        <td><?php echo $log['action']; ?></td>
                                        <td><?php echo $log['details']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>