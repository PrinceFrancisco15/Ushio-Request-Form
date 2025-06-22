<?php
// it/dashboard.php - IT Staff Dashboard
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an IT staff
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'it_staff') {
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

$it_staff_id = $_SESSION['user_id'];

// BUILD QUERY FOR PENDING REQUESTS WAITING FOR ASSIGNMENT
$sql = "SELECT r.*, d.department_name, requestor_name, u2.full_name as manager_name 
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.requestor_id = u1.user_id
        LEFT JOIN users u2 ON r.manager_id = u2.user_id
        WHERE ((r.manager_status = 'approved' AND r.gm_status = 'approved') OR 
              (r.manager_status = 'approved' AND r.gm_status = 'not_required'))
        AND (r.job_order_status = 'pending' OR r.job_order_status IS NULL)
        AND (r.it_staff_id IS NULL)";

// Add filters
$params = array();
$types = "";

// Department filter
if ($department_filter > 0) {
    $sql .= " AND r.department_id = ?";
    $params[] = $department_filter;
    $types .= "i";
}

// Request type filter
if ($request_type == 'support') {
    $sql .= " AND r.request_type = 'support'";
} elseif ($request_type == 'high-level') {
    $sql .= " AND r.request_type = 'high_level'";
}

// Search function
if (!empty($search)) {
    $sql .= " AND (r.request_id LIKE ? OR r.purpose LIKE ? OR d.department_name LIKE ? OR u1.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Count total pending requests for pagination
$count_sql = $sql;
if (!empty($types)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $count_result = $stmt->get_result();
} else {
    $count_result = $conn->query($count_sql);
}
$total_pending = $count_result->num_rows;
$total_pending_pages = ceil($total_pending / $items_per_page);

// Add pagination to pending requests
$sql .= " ORDER BY r.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $items_per_page;
$types .= "ii";

// Execute the main query for pending requests
if (!empty($types)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
$pending_requests = $result->fetch_all(MYSQLI_ASSOC);

// MY ASSIGNED REQUESTS
$assigned_sql = "SELECT r.*, d.department_name, u1.full_name as requestor_name
                FROM request_forms r
                LEFT JOIN departments d ON r.department_id = d.department_id
                LEFT JOIN users u1 ON r.requestor_id = u1.user_id
                WHERE r.it_staff_id = ? AND r.job_order_status != 'completed'";

// Add filters
$assigned_params = array($it_staff_id);
$assigned_types = "i";

// Department filter
if ($department_filter > 0) {
    $assigned_sql .= " AND r.department_id = ?";
    $assigned_params[] = $department_filter;
    $assigned_types .= "i";
}

// Request type filter
if ($request_type == 'support') {
    $assigned_sql .= " AND r.request_type = 'support'";
} elseif ($request_type == 'high-level') {$assigned_sql .= " AND r.request_type = 'high_level'";
}

// Status filter
if ($status == 'in_progress') {
    $assigned_sql .= " AND r.job_order_status = 'in_progress'";
} elseif ($status == 'on_hold') {
    $assigned_sql .= " AND r.job_order_status = 'on_hold'";
}

// Search function for assigned requests
if (!empty($search)) {
    $sql .= " AND (LOWER(r.request_id) LIKE LOWER(?) OR LOWER(r.purpose) LIKE LOWER(?) OR LOWER(d.department_name) LIKE LOWER(?))";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

// Count total assigned requests for pagination
$assigned_count_sql = $assigned_sql;
$stmt = $conn->prepare($assigned_count_sql);
$stmt->bind_param($assigned_types, ...$assigned_params);
$stmt->execute();
$assigned_count_result = $stmt->get_result();
$total_assigned = $assigned_count_result->num_rows;
$total_assigned_pages = ceil($total_assigned / $items_per_page);

// Add pagination to assigned requests
$assigned_sql .= " ORDER BY r.updated_at DESC LIMIT ?, ?";
$assigned_params[] = $offset;
$assigned_params[] = $items_per_page;
$assigned_types .= "ii";

// Execute the assigned requests query
$stmt = $conn->prepare($assigned_sql);
$stmt->bind_param($assigned_types, ...$assigned_params);
$stmt->execute();
$assigned_result = $stmt->get_result();
$assigned_requests = $assigned_result->fetch_all(MYSQLI_ASSOC);

// Completed requests history
$completed_sql = "SELECT r.*, d.department_name, u1.full_name as requestor_name
                FROM request_forms r
                LEFT JOIN departments d ON r.department_id = d.department_id
                LEFT JOIN users u1 ON r.requestor_id = u1.user_id
                WHERE r.it_staff_id = ? AND r.job_order_status = 'completed'";

// Add filters
$completed_params = array($it_staff_id);
$completed_types = "i";

// Department filter
if ($department_filter > 0) {
    $completed_sql .= " AND r.department_id = ?";
    $completed_params[] = $department_filter;
    $completed_types .= "i";
}

// Request type filter
if ($request_type == 'support') {
    $completed_sql .= " AND r.request_type = 'support'";
} elseif ($request_type == 'high-level') {
    $completed_sql .= " AND r.request_type = 'high_level'";
}

// Search function for completed requests
if (!empty($search)) {
    $completed_sql .= " AND (r.request_id LIKE ? OR r.purpose LIKE ? OR d.department_name LIKE ? OR u1.full_name LIKE ?)";
    $search_param = "%$search%";
    $completed_params[] = $search_param;
    $completed_params[] = $search_param;
    $completed_params[] = $search_param;
    $completed_params[] = $search_param;
    $completed_types .= "ssss";
}

// Count total completed requests for pagination
$completed_count_sql = $completed_sql;
$stmt = $conn->prepare($completed_count_sql);
$stmt->bind_param($completed_types, ...$completed_params);
$stmt->execute();
$completed_count_result = $stmt->get_result();
$total_completed = $completed_count_result->num_rows;
$total_completed_pages = ceil($total_completed / $items_per_page);

// Add pagination to completed requests
$completed_sql .= " ORDER BY r.updated_at DESC LIMIT ?, ?";
$completed_params[] = $offset;
$completed_params[] = $items_per_page;
$completed_types .= "ii";

// Execute the completed requests query
$stmt = $conn->prepare($completed_sql);
$stmt->bind_param($completed_types, ...$completed_params);
$stmt->execute();
$completed_result = $stmt->get_result();
$completed_requests = $completed_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Staff Dashboard - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
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
                <!-- Filter Section -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Filter Options</h5>
                    </div>
                    <div class="card-body filter-section">
                        <div class="row mb-3">
                            <!-- Department Filter -->
                            <div class="col-md-4">
                                <form method="get" id="deptForm">
                                    <!-- Keep other filters when department changes -->
                                    <input type="hidden" name="request_type" value="<?php echo $request_type; ?>">
                                    <input type="hidden" name="status" value="<?php echo $status; ?>">
                                    <input type="hidden" name="search" value="<?php echo $search; ?>">
                                    
                                    <label for="department" class="form-label">Department:</label>
                                    <select name="department" id="department" class="form-select" onchange="document.getElementById('deptForm').submit()">
                                        <option value="0">All Departments</option>
                                        <?php foreach($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_id']; ?>" <?php echo ($department_filter == $dept['department_id']) ? 'selected' : ''; ?>>
                                                <?php echo $dept['department_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            
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
                                    <a href="?department=<?php echo $department_filter; ?>&request_type=high-level&status=<?php echo $status; ?>&search=<?php echo $search; ?>" 
                                       class="btn btn-sm <?php echo $request_type == 'high-level' ? 'btn-secondary' : 'btn-outline-secondary'; ?> btn-filter">
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
                                    <a href="?department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=in_progress&search=<?php echo $search; ?>" 
                                       class="btn btn-sm <?php echo $status == 'in_progress' ? 'btn-warning' : 'btn-outline-warning'; ?> btn-filter">
                                       In Progress
                                    </a>
                                    <a href="?department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=on_hold&search=<?php echo $search; ?>" 
                                       class="btn btn-sm <?php echo $status == 'on_hold' ? 'btn-danger' : 'btn-outline-danger'; ?> btn-filter">
                                       On Hold
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
                </div>
                
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Pending Requests For Assignment</h5>
                    </div>
                    <div class="card-body">
                        <!-- Results count -->
                        <div class="mb-3">
                            <strong>Found: </strong> <?php echo $total_pending; ?> pending request(s)
                        </div>
                    
                        <?php if(count($pending_requests) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Requestor</th>
                                            <th>Department</th>
                                            <th>Type</th>
                                            <th>Category</th>
                                            <th>Purpose</th>
                                            <th>Manager</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($pending_requests as $request): ?>
                                            <tr>
                                                <td><?php echo $request['request_id']; ?></td>
                                                <td><?php echo $request['requestor_name']; ?></td>
                                                <td><?php echo $request['department_name']; ?></td>
                                                <td>
                                                    <?php if($request['request_type'] == 'support'): ?>
                                                        <span class="badge bg-info">Support</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">High Level</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo ucfirst($request['request_category']); ?></td>
                                                <td><?php echo substr($request['purpose'], 0, 30) . (strlen($request['purpose']) > 30 ? '...' : ''); ?></td>
                                                <td><?php echo $request['manager_name']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                                <td>
                                                    <a href="view_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">View</a>
                                                    <a href="update_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-warning">Update</a>
                                                    
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination for pending requests -->
                            <?php if($total_pending_pages > 1): ?>
                                <nav aria-label="Pending requests pagination">
                                    <ul class="pagination">
                                        <?php if($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>&department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo; Previous</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item disabled">
                                            <span class="page-link">Page <?php echo $page; ?> of <?php echo $total_pending_pages; ?></span>
                                        </li>
                                        
                                        <?php if($page < $total_pending_pages): ?>
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
                                    No pending requests found with the selected filters. <a href="dashboard.php">Clear all filters</a>.
                                <?php else: ?>
                                    No pending requests found that require assignment.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- My Assigned Requests -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0">My Assigned Requests</h5>
                    </div>
                    <div class="card-body">
                        <!-- Results count -->
                        <div class="mb-3">
                            <strong>Found: </strong> <?php echo $total_assigned; ?> assigned request(s)
                        </div>
                    
                        <?php if(count($assigned_requests) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Requestor</th>
                                            <th>Department</th>
                                            <th>Type</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($assigned_requests as $request): ?>
                                            <tr>
                                                <td><?php echo $request['request_id']; ?></td>
                                                <td><?php echo $request['requestor_name']; ?></td>
                                                <td><?php echo $request['department_name']; ?></td>
                                                <td>
                                                    <?php if($request['request_type'] == 'support'): ?>
                                                        <span class="badge bg-info">Support</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">High Level</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo substr($request['purpose'], 0, 30) . (strlen($request['purpose']) > 30 ? '...' : ''); ?></td>
                                                <td>
                                                    <?php 
                                                    switch($request['job_order_status']) {
                                                        case 'in_progress':
                                                            echo '<span class="badge bg-warning text-dark">In Progress</span>';
                                                            break;
                                                        case 'on_hold':
                                                            echo '<span class="badge bg-danger">On Hold</span>';
                                                            break;
                                                        case 'pending':
                                                            echo '<span class="badge bg-secondary">Pending</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($request['updated_at'])); ?></td>
                                                <td>
                                                    <a href="view_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">View</a>
                                                    <a href="update_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-warning">Update</a>
                                                    <a href="complete_job.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-success">Complete</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination for assigned requests -->
                            <?php if($total_assigned_pages > 1): ?>
                                <nav aria-label="Assigned requests pagination">
                                    <ul class="pagination">
                                        <?php if($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>&department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo; Previous</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item disabled">
                                            <span class="page-link">Page <?php echo $page; ?> of <?php echo $total_assigned_pages; ?></span>
                                        </li>
                                        
                                        <?php if($page < $total_assigned_pages): ?>
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
                                    No assigned requests found with the selected filters. <a href="dashboard.php">Clear all filters</a>.
                                <?php else: ?>
                                    You don't have any assigned requests at the moment.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Completed Requests History -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Completed Requests History</h5>
                    </div>
                    <div class="card-body">
                        <!-- Results count -->
                        <div class="mb-3">
                            <strong>Found: </strong> <?php echo $total_completed; ?> completed request(s)
                        </div>
                    
                        <?php if(count($completed_requests) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Requestor</th>
                                            <th>Department</th>
                                            <th>Type</th>
                                            <th>Purpose</th>
                                            <th>Completed Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($completed_requests as $request): ?>
                                            <tr>
                                                <td><?php echo $request['request_id']; ?></td>
                                                <td><?php echo $request['requestor_name']; ?></td>
                                                <td><?php echo $request['department_name']; ?></td>
                                                <td>
                                                    <?php if($request['request_type'] == 'support'): ?>
                                                        <span class="badge bg-info">Support</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">High Level</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo substr($request['purpose'], 0, 30) . (strlen($request['purpose']) > 30 ? '...' : ''); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($request['completion_date'])); ?></td>
                                                <td>
                                                    <a href="view_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination for completed requests -->
                            <?php if($total_completed_pages > 1): ?>
                                <nav aria-label="Completed requests pagination">
                                    <ul class="pagination">
                                        <?php if($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>&department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo; Previous</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item disabled">
                                            <span class="page-link">Page <?php echo $page; ?> of <?php echo $total_completed_pages; ?></span>
                                        </li>
                                        
                                        <?php if($page < $total_completed_pages): ?>
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
                                    No completed requests found with the selected filters. <a href="dashboard.php">Clear all filters</a>.
                                <?php else: ?>
                                    You don't have any completed requests yet.
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