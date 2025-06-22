<?php
// it/update_request.php - Update job request status and add comments
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an IT staff
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'it_staff') {
    header("Location: ../index.php");
    exit;
}

if(!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$request_id = $_GET['id'];
$it_staff_id = $_SESSION['user_id'];

// Check if the request exists and is assigned to this IT staff
$sql = "SELECT * FROM request_forms WHERE request_id = ? AND it_staff_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $it_staff_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Request not found or not assigned to you.";
    header("Location: dashboard.php");
    exit;
}

$request = $result->fetch_assoc();

// Process form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = $_POST['status'];
    $comment = $_POST['comment'];
    
    // Validate inputs
    if(empty($comment)) {
        $_SESSION['error'] = "Comment is required";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update request status
            $sql = "UPDATE request_forms SET job_order_status = ?, updated_at = NOW() WHERE request_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $status, $request_id);
            $stmt->execute();
            
            // Add log entry
            $action = "Status updated";
            $details = "Status changed to " . ucfirst($status) . ". Comment: " . $comment;
            $sql = "INSERT INTO activity_logs (request_id, user_id, action, details, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $request_id, $it_staff_id, $action, $details);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Request updated successfully";
            header("Location: view_request.php?id=" . $request_id);
            exit;
        } catch (Exception $e) {
            // Roll back transaction on error
            $conn->rollback();
            $_SESSION['error'] = "Error updating request: " . $e->getMessage();
        }
    }
}

// Get request details for display
$sql = "SELECT r.*, d.department_name, 
        requestor_name, u1.email as requestor_email
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.requestor_id = u1.user_id
        WHERE r.request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

// Get activity logs for this request
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
    <title>Update Request #<?php echo $request_id; ?> - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0">Update Request #<?php echo $request_id; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Request Information</h6>
                                <p><strong>Requestor:</strong> <?php echo htmlspecialchars($request['requestor_name']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($request['department_name']); ?></p>
                                <p><strong>Request Type:</strong> <?php echo ucfirst(htmlspecialchars($request['request_type'])); ?></p>
                                <p><strong>Request Category:</strong> <?php echo htmlspecialchars($request['request_category']); ?></p>
                                </td>
                                <p><strong>Current Status:</strong> 
                                    <span class="badge bg-<?php 
                                        echo $request['job_order_status'] == 'pending' ? 'secondary' : 
                                            ($request['job_order_status'] == 'assigned' ? 'info' : 
                                            ($request['job_order_status'] == 'in_progress' ? 'warning' : 
                                            ($request['job_order_status'] == 'on_hold' ? 'danger' : 'success'))); 
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($request['job_order_status'])); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6>Purpose</h6>
                                <p><?php echo nl2br(htmlspecialchars($request['purpose'])); ?></p>
                            </div>
                        </div>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="status" class="form-label">Update Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="in_progress" <?php echo ($request['job_order_status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="on_hold" <?php echo ($request['job_order_status'] == 'on_hold') ? 'selected' : ''; ?>>On Hold</option>
                                    <option value="assigned" <?php echo ($request['job_order_status'] == 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="comment" class="form-label">Update Comment/Notes</label>
                                <textarea class="form-control" id="comment" name="comment" rows="5" required placeholder="Provide an update about this request..."></textarea>
                                <div class="form-text">
                                    Your comment will be visible to the requestor and in the activity log.
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <a href="view_request.php?id=<?php echo $request_id; ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-success">Save Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Previous Updates</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <?php if(count($logs) > 0): ?>
                                <?php foreach($logs as $log): ?>
                                    <li class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($log['action']); ?></h6>
                                            <small><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($log['details']); ?></p>
                                        <small>By: <?php echo htmlspecialchars($log['full_name']); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item">No activity logs found</li>
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