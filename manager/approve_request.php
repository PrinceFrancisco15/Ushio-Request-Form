<?php
// manager/approve_request.php - Approve request
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

// Check if this request belongs to this manager
$sql = "SELECT * FROM request_forms WHERE request_id = ? AND manager_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $manager_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "You don't have permission to approve this request.";
    header("Location: dashboard.php");
    exit;
}

$request = $result->fetch_assoc();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $comment = $_POST['comment'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update request status
        $sql = "UPDATE request_forms SET manager_status = 'approved', manager_comment = ? WHERE request_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $comment, $request_id);
        $stmt->execute();
        
        // Log activity
        $action = "Request approved by manager";
        $sql = "INSERT INTO activity_logs (request_id, user_id, action, details) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $request_id, $manager_id, $action, $comment);
        $stmt->execute();
        
        // If it's a support request, it should go directly to IT
        if($request['request_type'] == 'support') {
            // Update request to skip GM approval
            $sql = "UPDATE request_forms SET gm_status = 'not_required' WHERE request_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
        }

        if ($stmt->execute()) {
            // Create notification for IT staff
            $message = "New request #$request_id has been approved by manager and needs IT attention.";
            createITNotification($request_id, $message);
            
          
        }
        
        // Commit transaction
        $conn->commit();
        
        // Redirect back to dashboard with success message
        $_SESSION['success'] = "Request approved successfully!";
        header("Location: dashboard.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback if error
        $conn->rollback();
        $error = "Error approving request: " . $e->getMessage();
    }
}

// Get request details for the form
$sql = "SELECT r.*, d.department_name, requestor_name
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u ON r.requestor_id = u.user_id
        WHERE r.request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

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
    <title>Approve Request - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Approve Request #<?php echo $request_id; ?></h5>
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
                                <label for="comment" class="form-label">Comment</label>
                                <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
                                <div class="form-text">Optional: Provide any comments for this approval</div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary">Back</a>
                                <button type="submit" class="btn btn-success">Approve Request</button>
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
