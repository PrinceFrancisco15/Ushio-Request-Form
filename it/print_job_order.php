<?php
// it/print_job_order.php - Print a completed job order
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

// Get job order details
$sql = "SELECT r.*, d.department_name, 
        u1.full_name as requestor_name, u1.email as requestor_email,
        u2.full_name as manager_name,
        u3.full_name as gm_name,
        u4.full_name as it_staff_name
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.requestor_id = u1.user_id
        LEFT JOIN users u2 ON r.manager_id = u2.user_id
        LEFT JOIN users u3 ON r.gm_id = u3.user_id
        LEFT JOIN users u4 ON r.it_staff_id = u4.user_id
        WHERE r.request_id = ? AND r.job_order_status = 'completed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Job order not found or not completed.";
    header("Location: dashboard.php");
    exit;
}

$job = $result->fetch_assoc();

// Ensure all required keys exist to prevent undefined array key warnings
$defaults = [
    'requestor_name' => 'Not specified',
    'requestor_email' => 'Not specified',
    'department_name' => 'Not specified',
    'request_type' => 'Not specified',
    'created_at' => date('Y-m-d'), // Default to current date if missing
    'request_date' => date('Y-m-d'), // Default to current date if missing
    'manager_name' => 'Not assigned',
    'manager_status' => 'pending',
    'gm_name' => 'Not assigned',
    'gm_status' => 'not_required',
    'it_staff_id' => NULL,
    'it_staff_name' => 'Not assigned',
    'completion_date' => NULL,
    'purpose' => '',
    'completion_notes' => ''
];

// Merge with existing data, keeping existing values where present
foreach ($defaults as $key => $value) {
    if (!isset($job[$key])) {
        $job[$key] = $value;
    }
}

// Get request items
$sql = "SELECT * FROM request_items WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);

// Get activity logs for this job order
$sql = "SELECT al.*, u.full_name 
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE al.request_id = ?
        ORDER BY al.created_at ASC";
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
    <title>Job Order #<?php echo $request_id; ?> - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 20px;
            }
            .container {
                width: 100%;
                max-width: 100%;
            }
            .card {
                border: none !important;
            }
            .card-header {
                background-color: #f8f9fa !important;
                color: #000 !important;
            }
            .table {
                width: 100%;
            }
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo {
            max-height: 80px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
            text-align: center;
            width: 200px;
            display: inline-block;
            margin-right: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="no-print mb-3">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            <button onclick="window.print()" class="btn btn-primary">Print Job Order</button>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="logo-container">
                    <img src="../assets/img/logo.png" alt="Company Logo" class="logo" onerror="this.style.display='none'">
                    <h4 class="mt-2">IT DEPARTMENT JOB ORDER</h4>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>Job Order #:</strong> <?php echo $request_id; ?></p>
                        <p><strong>Date Requested:</strong> <?php echo !empty($job['created_at']) ? date('F d, Y', strtotime($job['created_at'])) : 'N/A'; ?></p>
                        <p><strong>Date Completed:</strong> <?php echo !empty($job['completion_date']) ? date('F d, Y', strtotime($job['completion_date'])) : 'N/A'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Request Type:</strong> <?php echo ucfirst(htmlspecialchars($job['request_type'])); ?></p>
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($job['department_name']); ?></p>
                        <p><strong>Status:</strong> Completed</p>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5>Requestor Information</h5>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($job['requestor_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($job['requestor_email']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Manager:</strong> <?php echo htmlspecialchars($job['manager_name']); ?></p>
                                <p><strong>IT Staff:</strong> <?php echo htmlspecialchars($job['it_staff_name']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5>Purpose</h5>
                        <hr>
                        <p><?php echo nl2br(htmlspecialchars($job['purpose'])); ?></p>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5>Request Items</h5>
                        <hr>
                        <table class="table table-bordered">
                            <thead>
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
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5>Work Performed</h5>
                        <hr>
                        <p><?php echo nl2br(htmlspecialchars($job['completion_notes'])); ?></p>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5>Activity Timeline</h5>
                        <hr>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Action</th>
                                    <th>By</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($logs) > 0): ?>
                                    <?php foreach($logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                                            <td><?php echo htmlspecialchars($log['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No activity logs found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-5">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="signature-line">
                                    <?php echo htmlspecialchars($job['requestor_name']); ?>
                                </div>
                                <p class="text-center">Requestor's Signature</p>
                            </div>
                            
                            <div>
                                <div class="signature-line">
                                    <?php echo htmlspecialchars($job['it_staff_name']); ?>
                                </div>
                                <p class="text-center">IT Staff Signature</p>
                            </div>
                            
                            <div>
                                <div class="signature-line">
                                    <?php echo htmlspecialchars($job['manager_name']); ?>
                                </div>
                                <p class="text-center">Manager's Signature</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>