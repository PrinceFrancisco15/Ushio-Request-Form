<?php
// it/reports.php - View all saved monthly reports
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a General IT
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_it') {
    header("Location: ../index.php");
    exit;
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter options
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_year = isset($_GET['year']) ? $_GET['year'] : '';
$filter_month = isset($_GET['month']) ? $_GET['month'] : '';

// Build query conditions
$conditions = ["1=1"];
$params = [];
$param_types = "";

if (!empty($search)) {
    $conditions[] = "title LIKE ?";
    $params[] = "%$search%";
    $param_types .= "s";
}

if (!empty($filter_year)) {
    $conditions[] = "report_year = ?";
    $params[] = $filter_year;
    $param_types .= "i";
}

if (!empty($filter_month)) {
    $conditions[] = "report_month = ?";
    $params[] = $filter_month;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $conditions);

// Get total records count
$count_sql = "SELECT COUNT(*) as total FROM monthly_reports WHERE $where_clause";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get reports
$sql = "SELECT r.*, u.full_name as created_by_name
        FROM monthly_reports r
        LEFT JOIN users u ON r.created_by = u.user_id
        WHERE $where_clause
        ORDER BY r.created_at DESC
        LIMIT $offset, $records_per_page";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$reports = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Get available years for filter
$sql = "SELECT DISTINCT report_year FROM monthly_reports ORDER BY report_year DESC";
$years = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Reports - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-earmark-text"></i> Monthly Reports</h2>
            <a href="generate_report.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Generate New Report
            </a>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search report title..." name="search" value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="year">
                            <option value="">Filter by Year</option>
                            <?php foreach($years as $year): ?>
                                <option value="<?= $year['report_year'] ?>" <?= ($filter_year == $year['report_year']) ? 'selected' : '' ?>>
                                    <?= $year['report_year'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="month">
                            <option value="">Filter by Month</option>
                            <?php for($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= ($filter_month == $i) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                    </div>
                    <div class="col-md-2">
                        <a href="reports.php" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Reports Table -->
        <div class="card">
            <div class="card-body">
                <?php if(count($reports) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Period</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($reports as $report): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($report['title']) ?></td>
                                        <td>
                                            <?= date('F', mktime(0, 0, 0, $report['report_month'], 1)) ?> 
                                            <?= $report['report_year'] ?>
                                        </td>
                                        <td><?= htmlspecialchars($report['created_by_name']) ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($report['created_at'])) ?></td>
                                        <td>
                                            <a href="view_report.php?id=<?= $report['report_id'] ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="download_report.php?id=<?= $report['report_id'] ?>" class="btn btn-sm btn-success">
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= ($page-1) ?>&search=<?= urlencode($search) ?>&year=<?= $filter_year ?>&month=<?= $filter_month ?>">
                                            Previous
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&year=<?= $filter_year ?>&month=<?= $filter_month ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= ($page+1) ?>&search=<?= urlencode($search) ?>&year=<?= $filter_year ?>&month=<?= $filter_month ?>">
                                            Next
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        No monthly reports found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/scripts.js"></script>
</body>
</html>