<?php
// manager/reject_request.php - Reject request
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a manager
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    header("Location: ../index.php");
    exit;
}

if(!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$request_id = $_GET['id'];
$manager_id = $_SESSION['user_id'];

// Get request details first
$sql = "SELECT * FROM request_forms WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Request not found.";
    header("Location: dashboard.php");
    exit;
}

$request = $result->fetch_assoc();

// Check if this is an Access Derestriction request
if ($request['request_category'] == 'Access Derestriction') {
    // Check if this manager has permission to access this request
    $hasAccess = false;
    
    // Original department manager
    if ($manager_id == $request['manager_id']) {
        $hasAccess = true;
    }
    
    // Target department manager (only if original manager approved)
    if ($manager_id == $request['target_dept_manager_id']) {
        if ($request['manager_status'] == 'approved') {
            $hasAccess = true;
        }
    }
    
    if (!$hasAccess) {
        $_SESSION['error'] = "You don't have permission to view this request.";
        header("Location: dashboard.php");
        exit;
    }
    
    // Process form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $comment = $_POST['comment'] ?? '';
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // If this is the original department manager rejecting
            if ($manager_id == $request['manager_id']) {
                // Update manager_status to rejected
                $sql = "UPDATE request_forms SET manager_status = 'rejected', manager_comment = ? WHERE request_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $comment, $request_id);
                $stmt->execute();
                
                // Create notification for requestor
                $notification_message = "Your Access Derestriction request (#$request_id) has been rejected by your department manager.";
                $sql = "INSERT INTO notifications (user_id, request_id, message) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $request['requestor_id'], $request_id, $notification_message);
                $stmt->execute();
                
                // Log activity
                $action = "Request rejected by department manager";
                $details = "Department manager rejected Access Derestriction request.";
                $sql = "INSERT INTO activity_logs (request_id, user_id, action, details) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiss", $request_id, $_SESSION['user_id'], $action, $details);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "Request has been rejected.";
                header("Location: dashboard.php");
                exit;
            }
            // If this is the target department manager rejecting
            elseif ($manager_id == $request['target_dept_manager_id'] && $request['manager_status'] == 'approved') {
                // Update target manager status to rejected
                $sql = "UPDATE request_forms SET target_manager_status = 'rejected', target_manager_comment = ? WHERE request_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $comment, $request_id);
                $stmt->execute();
                
                // Create notification for requestor
                $notification_message = "Your Access Derestriction request (#$request_id) has been rejected by the target department manager.";
                $sql = "INSERT INTO notifications (user_id, request_id, message) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $request['requestor_id'], $request_id, $notification_message);
                $stmt->execute();
                
                // Also notify the original department manager
                $notification_message = "Access Derestriction request (#$request_id) from " . $request['requestor_name'] . " has been rejected by the target department manager.";
                $sql = "INSERT INTO notifications (user_id, request_id, message) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $request['manager_id'], $request_id, $notification_message);
                $stmt->execute();
                
                // Log activity
                $action = "Request rejected by target department manager";
                $details = "Target department manager rejected Access Derestriction request.";
                $sql = "INSERT INTO activity_logs (request_id, user_id, action, details) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiss", $request_id, $_SESSION['user_id'], $action, $details);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success'] = "Request has been rejected.";
                header("Location: dashboard.php");
                exit;
            }
        } catch (Exception $e) {
            // Rollback if error
            $conn->rollback();
            $_SESSION['error'] = "Error rejecting request: " . $e->getMessage();
            header("Location: dashboard.php");
            exit;
        }
    }
} else {
    // For regular requests - Check if this request belongs to this manager
    $sql = "SELECT * FROM request_forms WHERE request_id = ? AND manager_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $request_id, $manager_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows == 0) {
        $_SESSION['error'] = "You don't have permission to reject this request.";
        header("Location: dashboard.php");
        exit;
    }
    
    // Process form submission for regular requests
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $comment = $_POST['comment'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update request status
            $sql = "UPDATE request_forms SET manager_status = 'rejected', manager_comment = ? WHERE request_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $comment, $request_id);
            $stmt->execute();
            
            // Log activity
            $action = "Request rejected by manager";
            $sql = "INSERT INTO activity_logs (request_id, user_id, action, details) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiss", $request_id, $manager_id, $action, $comment);
            $stmt->execute();
            
            // Create notification for requestor
            $notification_message = "Your request (#$request_id) has been rejected by your department manager.";
            $sql = "INSERT INTO notifications (user_id, request_id, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $request['requestor_id'], $request_id, $notification_message);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Redirect back to dashboard with success message
            $_SESSION['success'] = "Request rejected successfully!";
            header("Location: dashboard.php");
            exit;
            
        } catch (Exception $e) {
            // Rollback if error
            $conn->rollback();
            $error = "Error rejecting request: " . $e->getMessage();
        }
    }
}

// Get request items
$sql = "SELECT * FROM request_items WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reject Request - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Reject Request #<?php echo $request_id; ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Requestor:</strong> <?php echo $request['requestor_name']; ?></p>
                                <p><strong>Department:</strong> <?php echo $request['department_name']; ?></p>
                                <p><strong>Request Type:</strong> <?php echo ucfirst($request['request_type']); ?></p>
                                <p><strong>Request Category:</strong> <?php echo ucfirst($request['request_category']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Date Created:</strong> <?php echo date('M d, Y', strtotime($request['created_at'])); ?></p>
                                <p><strong>Purpose:</strong> <?php echo $request['purpose']; ?></p>
                                
                                <?php if($request['request_category'] == 'Access Derestriction'): ?>
                                    <?php if($_SESSION['user_id'] == $request['manager_id']): ?>
                                        <p><strong>Your Role:</strong> <span class="badge bg-info">Department Manager</span></p>
                                    <?php elseif($_SESSION['user_id'] == $request['target_dept_manager_id']): ?>
                                        <p><strong>Your Role:</strong> <span class="badge bg-warning">Target Department Manager</span></p>
                                        <?php if($request['manager_status'] == 'approved'): ?>
                                            <p><strong>Department Manager Status:</strong> <span class="badge bg-success">Approved</span></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
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
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="comment" class="form-label">Reason for Rejection</label>
                                <textarea class="form-control" id="comment" name="comment" rows="3" required></textarea>
                                <div class="form-text">Please provide a reason for rejecting this request</div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary">Back</a>
                                <button type="submit" class="btn btn-danger">Reject Request</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>