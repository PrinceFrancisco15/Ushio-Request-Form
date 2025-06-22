<?php
// employee/dashboard.php - Employee Dashboard
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an employee
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: ../index.php");
    exit;
}

// Get all departments for filter dropdown
$dept_sql = "SELECT * FROM departments ORDER BY department_name";
$dept_result = $conn->query($dept_sql);
$departments = $dept_result->fetch_all(MYSQLI_ASSOC);

// Get filter parameters
$department_filter = isset($_GET['department']) ? intval($_GET['department']) : 0;
$request_type = isset($_GET['request_type']) ? $_GET['request_type'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination settings
$items_per_page = 5;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

// Get user's requests with filters
$user_id = $_SESSION['user_id'];

// Build the SQL query with filters
// In your SQL query for fetching requests
$sql = "SELECT r.*, d.department_name, u1.full_name as manager_name, u2.full_name as gm_name, u3.full_name as it_staff_name
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.manager_id = u1.user_id
        LEFT JOIN users u2 ON r.gm_id = u2.user_id
        LEFT JOIN users u3 ON r.it_staff_id = u3.user_id
        WHERE r.requestor_id = ?";
// Add filters
$params = array($user_id);
$types = "i";

// Department filter (keeping original functionality)
if ($department_filter > 0) {
    $sql .= " AND r.department_id = ?";
    $params[] = $department_filter;
    $types .= "i";
}

// Request type filter
// Change this line in the filter section:
if ($request_type == 'support') {
    $sql .= " AND r.request_type = 'support'";
} elseif ($request_type == 'high_level') {
    $sql .= " AND r.request_type = 'high_level'"; 
}

// Status filter
if ($status == 'pending') {
    $sql .= " AND (r.manager_status = 'pending' OR r.gm_status = 'pending' OR r.job_order_status = 'pending')";
} elseif ($status == 'approved') {
    $sql .= " AND ((r.manager_status = 'approved' AND (r.gm_status = 'approved' OR r.gm_status = 'not_required')) OR r.job_order_status = 'completed')";
}

if (!empty($search)) {
    $sql .= " AND (LOWER(r.request_id) LIKE LOWER(?) OR LOWER(r.purpose) LIKE LOWER(?) OR LOWER(d.department_name) LIKE LOWER(?))";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Count total records for pagination
$count_sql = $sql;
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$count_result = $stmt->get_result();
$total_records = $count_result->num_rows;
$total_pages = ceil($total_records / $items_per_page);

// Add pagination limit
$sql .= " ORDER BY r.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $items_per_page;
$types .= "ii";

// Execute the main query
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn-filter {
            padding: 8px 15px;
            margin-right: 5px;
            border-radius: 20px;
        }
        .btn-filter.active {
            background-color: #0d6efd;
            color: white;
        }
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">My Requests</h5>
                        <a href="create_request.php" class="btn btn-light btn-sm">Create New Request</a>
                    </div>
                    <div class="card-body">
                        <!-- Filter Section -->
                        <div class="filter-section">
                            <div class="row mb-3">
                                
                                
                                <!-- Search Bar -->
                                <div class="col-md-8">
                                    <form method="get" class="d-flex">
                                        <!-- Keep other filters when searching -->
                                        <input type="hidden" name="department" value="<?php echo $department_filter; ?>">
                                        <input type="hidden" name="request_type" value="<?php echo $request_type; ?>">
                                        <input type="hidden" name="status" value="<?php echo $status; ?>">
                                        
                                        <div class="input-group">
                                            <input type="text" name="search" class="form-control" placeholder="Search requests..." value="<?php echo $search; ?>">
                                            <button type="submit" class="btn btn-primary">Search</button>
                                            <?php if (!empty($search)): ?>
                                                <a href="?department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=<?php echo $status; ?>" class="btn btn-outline-secondary">Clear Search</a>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Request Type Filter -->
                                <div class="col-md-6">
                                    <label class="form-label">Request Type:</label>
                                    <div>
                                        <a href="?department=<?php echo $department_filter; ?>&request_type=all&status=<?php echo $status; ?>&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $request_type == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?> btn-filter">
                                           All Types
                                        </a>
                                        <a href="?department=<?php echo $department_filter; ?>&request_type=support&status=<?php echo $status; ?>&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $request_type == 'support' ? 'btn-info' : 'btn-outline-info'; ?> btn-filter">
                                           Support Requests
                                        </a>
                                        <a href="?department=<?php echo $department_filter; ?>&request_type=high_level&status=<?php echo $status; ?>&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $request_type == 'high_level' ? 'btn-secondary' : 'btn-outline-secondary'; ?> btn-filter">
                                           High-Level Requests
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Status Filter -->
                                <div class="col-md-6">
                                    <label class="form-label">Status:</label>
                                    <div>
                                        <a href="?department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=all&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $status == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?> btn-filter">
                                           All Status
                                        </a>
                                        <a href="?department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=pending&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $status == 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?> btn-filter">
                                           Pending
                                        </a>
                                        <a href="?department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=approved&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $status == 'approved' ? 'btn-success' : 'btn-outline-success'; ?> btn-filter">
                                           Approved
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($department_filter > 0 || $request_type != 'all' || $status != 'all' || !empty($search)): ?>
                                <div class="mt-3">
                                    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Clear All Filters</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Results count -->
                        <div class="mb-3">
                            <strong>Found: </strong> <?php echo $total_records; ?> request(s)
                        </div>
                        
                        <?php if(count($requests) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Department</th>
                                            <th>Type</th>
                                            <th>Category</th>
                                            <th>Purpose</th>
                                            <th>Manager Status</th>
                                            <th>GM Status</th>
                                            <th>IT Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
    <?php foreach($requests as $request): ?>
        <tr>
            <td><?php echo $request['request_id']; ?></td>
            <td><?php echo $request['department_name']; ?></td>
            <td>
    <?php if($request['request_type'] == 'support'): ?>
        <span class="badge bg-info">Support</span>
    <?php elseif($request['request_type'] == 'high_level'): ?>
        <span class="badge bg-secondary">High Level</span>
    <?php elseif($request['request_type'] == 'others'): ?>
        <span class="badge bg-primary">Others</span>
    <?php else: ?>
        <span class="badge bg-secondary"><?php echo ucfirst($request['request_type']); ?></span>
    <?php endif; ?>
</td>
            <td><?php echo ucfirst($request['request_category']); ?>
</td>
<td><?php echo $request['purpose']; ?></td>
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
        case 'not_required':
            echo '<span class="badge bg-secondary">Not Required</span>';
            break;
    }
    ?>
</td>
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
            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
            <td>
                <a href="view_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">View</a>
                <?php if($request['manager_status'] == 'pending'): ?>
                    <a href="edit_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                    <a href="delete_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this request?')">Delete</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
</table>
</div>
                            
                            <!-- Pagination -->
                            <?php if($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination">
                                        <?php if($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>&department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo; Previous</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item disabled">
                                            <span class="page-link">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                                        </li>
                                        
                                        <?php if($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page+1; ?>&department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>" aria-label="Next">
                                                    <span aria-hidden="true">Next &raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="alert alert-info">
                                <?php if($department_filter > 0 || $request_type != 'all' || $status != 'all' || !empty($search)): ?>
                                    No requests found with the selected filters. <a href="dashboard.php">Clear all filters</a> or <a href="create_request.php">create a new request</a>.
                                <?php else: ?>
                                    You haven't created any requests yet. <a href="create_request.php">Create your first request</a>.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>