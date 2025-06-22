<?php
// it/view_request.php - View request details
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

// Get request details
$sql = "SELECT r.*, d.department_name, 
        requestor_name, u1.email as requestor_email,
        u2.full_name as manager_name,
        u3.full_name as gm_name,
        u4.full_name as it_staff_name,
        r.ticket_number
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

if($result->num_rows == 0) {
    $_SESSION['error'] = "Request not found.";
    header("Location: dashboard.php");
    exit;
}

$request = $result->fetch_assoc();

// Ensure all required keys exist to prevent undefined array key warnings
$defaults = [
    'requestor_name' => 'Not specified',
    'requestor_email' => 'Not specified',
    'department_name' => 'Not specified',
    'request_type' => 'Not specified',
    'request_date' => date('Y-m-d'), 
    'manager_name' => 'Not assigned',
    'manager_status' => 'pending',
    'gm_name' => 'Not assigned',
    'gm_status' => 'not_required',
    'it_staff_id' => NULL,
    'it_staff_name' => 'Not assigned',
    'completion_date' => NULL,
    'purpose' => '',
    'completion_notes' => '',
    'job_order_status' => 'pending',
    'ticket_number' => 'JO-' . date('Ymd') . '-' . $request_id // Default ticket number format if not set
];

// Merge with existing data, keeping existing values where present
foreach ($defaults as $key => $value) {
    if (!isset($request[$key])) {
        $request[$key] = $value;
    }
}

// Check if this is assigned to current IT staff member or is still pending assignment
$is_my_job = ($request['it_staff_id'] == $it_staff_id);
$is_pending = ($request['job_order_status'] == 'pending' && $request['it_staff_id'] == NULL);

if(!$is_my_job && !$is_pending) {
    // IT staff can only view requests that are assigned to them or pending assignment
    $_SESSION['error'] = "You don't have permission to view this request.";
    header("Location: dashboard.php");
    exit;
}

// Get request items
$sql = "SELECT * FROM request_items WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);

// Get request attachments
$sql = "SELECT * FROM request_attachments WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$attachments = $result->fetch_all(MYSQLI_ASSOC);
// Get activity logs
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

// Extract ticket number from logs if available
$ticket_number = $request['ticket_number']; // Use the one from the database if available
foreach($logs as $log) {
    if(strpos($log['details'], 'Ticket number generated:') !== false) {
        $parts = explode('Ticket number generated:', $log['details']);
        if(isset($parts[1])) {
            // Extract just the ticket number pattern (JO-XXXXXXXX-X) without any comments
            $ticket_text = trim($parts[1]);
            if(preg_match('/JO-\d+-\d+/', $ticket_text, $matches)) {
                $ticket_number = $matches[0];
            } else {
                $ticket_number = $ticket_text; // Fallback to the full text if pattern not found
            }
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request <?php echo htmlspecialchars($ticket_number); ?> - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo htmlspecialchars($ticket_number); ?></h5>
                        <span class="badge bg-<?php 
                            echo $request['job_order_status'] == 'pending' ? 'secondary' : 
                                ($request['job_order_status'] == 'assigned' ? 'info' : 
                                ($request['job_order_status'] == 'in_progress' ? 'warning' : 
                                ($request['job_order_status'] == 'completed' ? 'success' : 'danger'))); 
                        ?>">
                            <?php echo ucfirst(htmlspecialchars($request['job_order_status'])); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Request Information</h6>
                                <p><strong>Requestor:</strong> <?php echo htmlspecialchars($request['requestor_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($request['requestor_email']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($request['department_name']); ?></p>
                                <p><strong>Request Type:</strong> <?php echo ucfirst(htmlspecialchars($request['request_type'])); ?></p>
                                <p><strong>Request Category:</strong> <?php echo htmlspecialchars($request['request_category']); ?></p>
                                <p><strong>Date Requested:</strong> 
                                    <?php 
                                    if (!empty($request['request_date'])) {
                                        echo date('M d, Y', strtotime($request['request_date']));
                                    } else {
                                        echo 'Not specified';
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6>Approval Information</h6>
                                <p><strong>Manager:</strong> <?php echo htmlspecialchars($request['manager_name']); ?></p>
                                <p><strong>Manager Status:</strong> 
                                    <span class="badge bg-<?php 
                                        echo $request['manager_status'] == 'approved' ? 'success' : 
                                            ($request['manager_status'] == 'rejected' ? 'danger' : 
                                            ($request['manager_status'] == 'not_required' ? 'info' : 'warning')); 
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($request['manager_status'])); ?>
                                    </span>
                                </p>
                                
                                <?php if($request['gm_status'] != 'not_required'): ?>
                                <p><strong>General Manager:</strong> <?php echo htmlspecialchars($request['gm_name']); ?></p>
                                <p><strong>GM Status:</strong> 
                                    <span class="badge bg-<?php 
                                        echo $request['gm_status'] == 'approved' ? 'success' : 
                                            ($request['gm_status'] == 'rejected' ? 'danger' : 
                                            ($request['gm_status'] == 'not_required' ? 'info' : 'warning')); 
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($request['gm_status'])); ?>
                                    </span>
                                </p>
                                <?php endif; ?>
                                
                                <?php if($request['it_staff_id']): ?>
                                <p><strong>Assigned IT Staff:</strong> <?php echo htmlspecialchars($request['it_staff_name']); ?></p>
                                <?php endif; ?>
                                
                                <?php if(!empty($request['completion_date'])): ?>
                                <p><strong>Completion Date:</strong> <?php echo date('M d, Y', strtotime($request['completion_date'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6>Purpose</h6>
                            <p><?php echo nl2br(htmlspecialchars($request['purpose'])); ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <h6>Request Items</h6>
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
                        
                        <!-- Attachments Section -->
                        <div class="mb-4">
                            <h6 class="mb-3">Attachments</h6>
                            <div class="row">
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
                        </div>
                        
                        <?php if($request['job_order_status'] == 'completed'): ?>
                            <div class="mb-4">
                                <h6>Completion Notes</h6>
                                <p><?php echo nl2br(htmlspecialchars($request['completion_notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                            
                            <?php if($is_pending): ?>
                                <a href="assign_job.php?id=<?php echo $request_id; ?>" class="btn btn-success">Assign to Me</a>
                            <?php elseif($is_my_job): ?>
                                <?php if($request['job_order_status'] == 'assigned'): ?>
                                    <a href="start_job.php?id=<?php echo $request_id; ?>" class="btn btn-warning">Start Work</a>
                                <?php elseif($request['job_order_status'] == 'in_progress'): ?>
                                    <a href="complete_job.php?id=<?php echo $request_id; ?>" class="btn btn-success">Complete Job</a>
                                <?php elseif($request['job_order_status'] == 'completed'): ?>
                                    <a href="print_job_order.php?id=<?php echo $request_id; ?>" class="btn btn-success">Print Job Order</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Activity Log</h5>
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