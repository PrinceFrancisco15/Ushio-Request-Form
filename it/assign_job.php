<?php
// it/assign_job.php - Assign job to current IT staff member
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

// Check if request exists and is pending assignment
$sql = "SELECT * FROM request_forms 
        WHERE request_id = ? 
        AND ((manager_status = 'approved' AND gm_status = 'approved') OR 
             (manager_status = 'approved' AND gm_status = 'not_required'))
        AND (job_order_status = 'pending' OR job_order_status IS NULL)
        AND it_staff_id IS NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Request not found or already assigned.";
    header("Location: dashboard.php");
    exit;
}

$request = $result->fetch_assoc();

// Process form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $comment = trim($_POST['comment'] ?? ''); // Optional comment
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update request to assign to current IT staff
        $sql = "UPDATE request_forms SET 
                it_staff_id = ?, 
                job_order_status = 'assigned', 
                updated_at = NOW() 
                WHERE request_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $it_staff_id, $request_id);
        $stmt->execute();
        
        // Get IT staff name for the log
        $sql = "SELECT full_name FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $it_staff_id);
        $stmt->execute();
        $staff_result = $stmt->get_result();
        $staff = $staff_result->fetch_assoc();
        $staff_name = $staff['full_name'];
        
        // Create activity log
        $action = "Request Assigned";
        $details = "Request assigned to " . $staff_name;
        
        // Add comment if provided
        if(!empty($comment)) {
            $details .= ". Comment: " . $comment;
        }
        
        $sql = "INSERT INTO activity_logs (request_id, user_id, action, details, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $request_id, $it_staff_id, $action, $details);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Request assigned to you successfully.";
        header("Location: view_request.php?id=" . $request_id);
        exit;
    } catch (Exception $e) {
        // Roll back transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Error assigning request: " . $e->getMessage();
    }
}

// Get request details for display
$sql = "SELECT r.*, d.department_name, 
        requestor_name, u1.email as requestor_email,
        r.ticket_number
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.requestor_id = u1.user_id
        WHERE r.request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();

// Ensure ticket_number has a default value if not set
if (!isset($request['ticket_number']) || empty($request['ticket_number'])) {
    $request['ticket_number'] = 'JO-' . date('Ymd') . '-' . $request_id;
}

// Get activity logs to check for ticket number
$sql = "SELECT * FROM activity_logs
        WHERE request_id = ? AND details LIKE '%Ticket number generated:%'
        ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$log_result = $stmt->get_result();

if ($log_result->num_rows > 0) {
    $log = $log_result->fetch_assoc();
    if(strpos($log['details'], 'Ticket number generated:') !== false) {
        $parts = explode('Ticket number generated:', $log['details']);
        if(isset($parts[1])) {
            // Extract just the ticket number pattern (JO-XXXXXXXX-X) without any comments
            $ticket_text = trim($parts[1]);
            if(preg_match('/JO-\d+-\d+/', $ticket_text, $matches)) {
                $request['ticket_number'] = $matches[0];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Job - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo htmlspecialchars($request['ticket_number']); ?></h5>
                        <span class="badge bg-secondary">Pending</span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Request Information</h6>
                                <p><strong>Requestor:</strong> <?php echo htmlspecialchars($request['requestor_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($request['requestor_email']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($request['department_name']); ?></p>
                                <p><strong>Request Type:</strong> <?php echo ucfirst(htmlspecialchars($request['request_type'])); ?></p>
                                <p><strong>Request Category:</strong> <?php echo htmlspecialchars($request['request_category']); ?></p>
                                <p><strong>Date Requested:</strong> <?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?></p>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <p class="mb-0">You are about to assign this job to yourself. This will make you responsible for completing this request.</p>
                        </div>

                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="comment" class="form-label">Comment (Optional)</label>
                                <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Add any notes or comments about this assignment"></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-success">Assign to Me</button>
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