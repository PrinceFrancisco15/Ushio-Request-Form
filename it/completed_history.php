<?php
// it/completed_history.php - View all completed requests for General IT
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a General IT
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_it') {
    header("Location: ../index.php");
    exit;
}

$general_it_id = $_SESSION['user_id'];

// Pagination settings
$records_per_page = 15;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter options
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$department = isset($_GET['department']) ? $_GET['department'] : '';
$staff = isset($_GET['staff']) ? $_GET['staff'] : '';

// Build query conditions
$conditions = ["r.job_order_status = 'completed'"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $conditions[] = "(r.request_id LIKE ? OR u1.full_name LIKE ? OR u2.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if (!empty($date_from)) {
    $conditions[] = "r.completion_date >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $conditions[] = "r.completion_date <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

if (!empty($department)) {
    $conditions[] = "r.department_id = ?";
    $params[] = $department;
    $param_types .= "i";
}

if (!empty($staff)) {
    $conditions[] = "r.it_staff_id = ?";
    $params[] = $staff;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $conditions);

// Get total records count
$count_sql = "SELECT COUNT(*) as total 
              FROM request_forms r
              LEFT JOIN users u1 ON r.requestor_id = u1.user_id
              LEFT JOIN users u2 ON r.it_staff_id = u2.user_id
              WHERE $where_clause";

$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get completed requests
$sql = "SELECT r.*, d.department_name, u1.full_name as requestor_name, u2.full_name as staff_name
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u1 ON r.requestor_id = u1.user_id
        LEFT JOIN users u2 ON r.it_staff_id = u2.user_id
        WHERE $where_clause
        ORDER BY r.completion_date DESC
        LIMIT $offset, $records_per_page";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$requests = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}

// Get all departments for filter
$sql = "SELECT * FROM departments ORDER BY department_name";
$departments = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get all IT staff for filter
$sql = "SELECT user_id, full_name FROM users WHERE role = 'it_staff' ORDER BY full_name";
$it_staff = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Requests History - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-check-circle"></i> Completed Requests History</h2>
            <a href="generate_report.php" class="btn btn-success">
                <i class="bi bi-file-earmark-text"></i> Generate Monthly Report
            </a>
        </div>
        
        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" placeholder="Search by ID or Name" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date_from" placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" name="date_to" placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="department">
                            <option value="">All Departments</option>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" <?php echo ($department == $dept['department_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="staff">
                            <option value="">All IT Staff</option>
                            <?php foreach($it_staff as $staff_member): ?>
                                <option value="<?php echo $staff_member['user_id']; ?>" <?php echo ($staff == $staff_member['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff_member['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Completed Requests Table -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Completed Requests (<?php echo $total_records; ?> total)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Request Type</th>
                                <th>Department</th>
                                <th>Requestor</th>
                                <th>Assigned To</th>
                                <th>Completion Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($requests) > 0): ?>
                                <?php foreach($requests as $request): ?>
                                    <tr>
                                        <td><?php echo $request['request_id']; ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($request['request_type'])); ?></td>
                                        <td><?php echo htmlspecialchars($request['department_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['requestor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['staff_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($request['completion_date'])); ?></td>
                                        <td>
                                            <a href="view_completed_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="print_job_order.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-secondary" target="_blank">
                                                <i class="bi bi-printer"></i> Print
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No completed requests found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mt-4">
                            <?php if($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($date_from)) ? '&date_from='.$date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to='.$date_to : ''; ?><?php echo (!empty($department)) ? '&department='.$department : ''; ?><?php echo (!empty($staff)) ? '&staff='.$staff : ''; ?>">First</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($date_from)) ? '&date_from='.$date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to='.$date_to : ''; ?><?php echo (!empty($department)) ? '&department='.$department : ''; ?><?php echo (!empty($staff)) ? '&staff='.$staff : ''; ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($date_from)) ? '&date_from='.$date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to='.$date_to : ''; ?><?php echo (!empty($department)) ? '&department='.$department : ''; ?><?php echo (!empty($staff)) ? '&staff='.$staff : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($date_from)) ? '&date_from='.$date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to='.$date_to : ''; ?><?php echo (!empty($department)) ? '&department='.$department : ''; ?><?php echo (!empty($staff)) ? '&staff='.$staff : ''; ?>">Next</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo (!empty($search)) ? '&search='.$search : ''; ?><?php echo (!empty($date_from)) ? '&date_from='.$date_from : ''; ?><?php echo (!empty($date_to)) ? '&date_to='.$date_to : ''; ?><?php echo (!empty($department)) ? '&department='.$department : ''; ?><?php echo (!empty($staff)) ? '&staff='.$staff : ''; ?>">Last</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>