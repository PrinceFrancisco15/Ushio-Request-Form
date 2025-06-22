<?php
// gm/dashboard.php - General Manager Dashboard
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a general manager
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_manager') {
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

// Get pending requests that require GM approval
$gm_id = $_SESSION['user_id'];

// Build the SQL query with filters for pending requests
$sql = "SELECT r.*, d.department_name, u1.full_name as requestor_name, u2.full_name as manager_name 
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.requestor_id = u1.user_id
        LEFT JOIN users u2 ON r.manager_id = u2.user_id
        WHERE r.gm_id = ? AND r.manager_status = 'approved' 
        AND (r.gm_status = 'pending' OR r.gm_status IS NULL)";

// Add filters
$params = array($gm_id);
$types = "i";

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
$stmt = $conn->prepare($count_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$count_result = $stmt->get_result();
$total_pending = $count_result->num_rows;
$total_pending_pages = ceil($total_pending / $items_per_page);

// Add pagination to pending requests
$sql .= " ORDER BY r.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $items_per_page;
$types .= "ii";

// Execute the main query for pending requests
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$pending_requests = $result->fetch_all(MYSQLI_ASSOC);

// Get all requests history with filters
$history_sql = "SELECT r.*, d.department_name, u1.full_name as requestor_name, u2.full_name as manager_name 
               FROM request_forms r
               LEFT JOIN departments d ON r.department_id = d.department_id
               LEFT JOIN users u1 ON r.requestor_id = u1.user_id
               LEFT JOIN users u2 ON r.manager_id = u2.user_id
               WHERE r.gm_id = ? AND r.gm_status != 'pending'";

// Reset params and types for history query
$history_params = array($gm_id);
$history_types = "i";

// Apply filters to history
if ($department_filter > 0) {
    $history_sql .= " AND r.department_id = ?";
    $history_params[] = $department_filter;
    $history_types .= "i";
}

// Request type filter for history
if ($request_type == 'support') {
    $history_sql .= " AND r.request_type = 'support'";
} elseif ($request_type == 'high-level') {
    $history_sql .= " AND r.request_type = 'high_level'";
}

// Status filter for history
if ($status == 'approved') {
    $history_sql .= " AND r.gm_status = 'approved'";
} elseif ($status == 'rejected') {
    $history_sql .= " AND r.gm_status = 'rejected'";
}

// Search function for history
if (!empty($search)) {
    $history_sql .= " AND (LOWER(r.request_id) LIKE LOWER(?) OR LOWER(r.purpose) LIKE LOWER(?) OR LOWER(d.department_name) LIKE LOWER(?))";
    $search_param = "%$search%";
    $history_params[] = $search_param;
    $history_params[] = $search_param;
    $history_params[] = $search_param;
    $history_types .= "sss";
}   

// Count total history records for pagination
$history_count_sql = $history_sql;
$stmt = $conn->prepare($history_count_sql);
$stmt->bind_param($history_types, ...$history_params);
$stmt->execute();
$history_count_result = $stmt->get_result();
$total_history = $history_count_result->num_rows;
$total_history_pages = ceil($total_history / $items_per_page);

// Add pagination to history
$history_sql .= " ORDER BY r.updated_at DESC LIMIT ?, ?";
$history_params[] = $offset;
$history_params[] = $items_per_page;
$history_types .= "ii";

// Execute the history query
$stmt = $conn->prepare($history_sql);
$stmt->bind_param($history_types, ...$history_params);
$stmt->execute();
$history_result = $stmt->get_result();
$request_history = $history_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Manager Dashboard - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
                                    <a href="?department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=approved&search=<?php echo $search; ?>" 
                                       class="btn btn-sm <?php echo $status == 'approved' ? 'btn-success' : 'btn-outline-success'; ?> btn-filter">
                                       Approved
                                    </a>
                                    <a href="?department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=rejected&search=<?php echo $search; ?>" 
                                       class="btn btn-sm <?php echo $status == 'rejected' ? 'btn-danger' : 'btn-outline-danger'; ?> btn-filter">
                                       Rejected
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
                        <h5 class="mb-0">Pending Requests For Approval</h5>
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
                                                    <a href="approve_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-success">Approve</a>
                                                    <a href="reject_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-danger">Reject</a>
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
                                    No pending requests found that require your approval.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Request History</h5>
                    </div>
                    <div class="card-body">
                        <!-- Results count -->
                        <div class="mb-3">
                            <strong>Found: </strong> <?php echo $total_history; ?> history record(s)
                        </div>
                    
                        <?php if(count($request_history) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Request ID</th>
                                            <th>Requestor</th>
                                            <th>Department</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($request_history as $request): ?>
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
                                                <td>
                                                    <?php 
                                                    switch($request['gm_status']) {
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
                                                <td><?php echo date('M d, Y', strtotime($request['updated_at'])); ?></td>
                                                <td>
                                                    <a href="view_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination for history -->
                            <?php if($total_history_pages > 1): ?>
                                <nav aria-label="History pagination">
                                    <ul class="pagination">
                                        <?php if($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>&department=<?php echo $department_filter; ?>&request_type=<?php echo $request_type; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo; Previous</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item disabled">
                                            <span class="page-link">Page <?php echo $page; ?> of <?php echo $total_history_pages; ?></span>
                                        </li>
                                        
                                        <?php if($page < $total_history_pages): ?>
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
                                    No request history found with the selected filters. <a href="dashboard.php">Clear all filters</a>.
                                <?php else: ?>
                                    No request history found.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Reports from General IT Section -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Reports from General IT</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $sql_reports = "SELECT r.*, u.full_name as sender_name 
                                        FROM monthly_reports r
                                        LEFT JOIN users u ON r.created_by = u.user_id
                                        WHERE r.title LIKE 'IT Request Report%'
                                        ORDER BY r.created_at DESC
                                        LIMIT 5";
                        $report_result = $conn->query($sql_reports);
                                    
                        if ($report_result->num_rows > 0):
                        ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>From</th>
                                        <th>Title</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($report = $report_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($report['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($report['sender_name']); ?></td>
                                        <td><?php echo htmlspecialchars($report['title']); ?></td>
                                        <td>
                                            <a href="view_report.php?id=<?php echo $report['report_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted">No reports available at this time.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>