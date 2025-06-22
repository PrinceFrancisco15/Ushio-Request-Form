<?php
// manager/dashboard.php - Manager Dashboard
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a manager
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    header("Location: ../index.php");
    exit;
}

// Get filter parameters
$request_type = isset($_GET['request_type']) ? $_GET['request_type'] : 'all';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination settings
$items_per_page = 5; // You can adjust this number
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $items_per_page;

// Get requests for manager's department with filters
$manager_id = $_SESSION['user_id'];

// Build the SQL query with filters
$sql = "SELECT r.*, d.department_name, requestor_name, td.department_name as target_dept_name 
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        LEFT JOIN users u ON r.requestor_id = u.user_id
        LEFT JOIN departments td ON r.target_department_id = td.department_id
        WHERE (r.manager_id = ? OR 
              (r.target_dept_manager_id = ? AND r.manager_status = 'approved' AND r.request_category = 'Access Derestriction'))";

// Update parameters
$params = array($manager_id, $manager_id);
$types = "ii";

// Request type filter
if ($request_type == 'support') {
    $sql .= " AND r.request_type = 'support'";
} elseif ($request_type == 'high-level') {
    $sql .= " AND r.request_type = 'high_level'";
}

// Status filter
if ($status == 'pending') {
    $sql .= " AND r.manager_status = 'pending'";
} elseif ($status == 'approved') {
    $sql .= " AND r.manager_status = 'approved'";
} elseif ($status == 'rejected') {
    $sql .= " AND r.manager_status = 'rejected'";
}

// Search function
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

// Add sorting and pagination
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
    <title>Manager Dashboard - Request Form System</title>
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
        .notification-dropdown {
        padding: 0.5rem 0;
        }
        .notification-item {
        white-space: normal;
        border-bottom: 1px solid #f1f1f1;
        padding: 0.7rem 1rem;
        }
        .notification-item:hover {
        background-color: #f8f9fa;
        }
        .dropdown-menu-notification {
        max-height: 400px;
        overflow-y: auto;
        }
        .dropdown-toggle::after {
        display: none;
    }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Manager Dashboard</h5>
                    </div>
                    <div class="card-body">
                        <!-- Filter Section -->
                        <div class="filter-section">
                            <div class="row mb-3">
                                <!-- Search Bar -->
                                <div class="col-md-12">
                                    <form method="get" class="d-flex">
                                        <!-- Keep other filters when searching -->
                                        <input type="hidden" name="request_type" value="<?php echo $request_type; ?>">
                                        <input type="hidden" name="status" value="<?php echo $status; ?>">
                                        
                                        <div class="input-group">
                                            <input type="text" name="search" class="form-control" placeholder="Search by ID, purpose, or requestor..." value="<?php echo $search; ?>">
                                            <button type="submit" class="btn btn-primary">Search</button>
                                            <?php if (!empty($search)): ?>
                                                <a href="?request_type=<?php echo $request_type; ?>&status=<?php echo $status; ?>" class="btn btn-outline-secondary">Clear Search</a>
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
                                        <a href="?request_type=all&status=<?php echo $status; ?>&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $request_type == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?> btn-filter">
                                           All Types
                                        </a>
                                        <a href="?request_type=support&status=<?php echo $status; ?>&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $request_type == 'support' ? 'btn-info' : 'btn-outline-info'; ?> btn-filter">
                                           Support Requests
                                        </a>
                                        <a href="?request_type=high-level&status=<?php echo $status; ?>&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $request_type == 'high-level' ? 'btn-secondary' : 'btn-outline-secondary'; ?> btn-filter">
                                           High-Level Requests
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Status Filter -->
                                <div class="col-md-6">
                                    <label class="form-label">Status:</label>
                                    <div>
                                        <a href="?request_type=<?php echo $request_type; ?>&status=all&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $status == 'all' ? 'btn-primary' : 'btn-outline-primary'; ?> btn-filter">
                                           All Status
                                        </a>
                                        <a href="?request_type=<?php echo $request_type; ?>&status=pending&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $status == 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?> btn-filter">
                                           Pending
                                        </a>
                                        <a href="?request_type=<?php echo $request_type; ?>&status=approved&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $status == 'approved' ? 'btn-success' : 'btn-outline-success'; ?> btn-filter">
                                           Approved
                                        </a>
                                        <a href="?request_type=<?php echo $request_type; ?>&status=rejected&search=<?php echo $search; ?>" 
                                           class="btn btn-sm <?php echo $status == 'rejected' ? 'btn-danger' : 'btn-outline-danger'; ?> btn-filter">
                                           Rejected
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($request_type != 'all' || $status != 'all' || !empty($search)): ?>
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
                                            <th>Requestor</th>
                                            <th>Request Type</th>
                                            <th>Category</th>
                                            <th>Target Department</th>
                                            <th>Purpose</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($requests as $request): ?>
                                            <tr>
                                                <td><?php echo $request['request_id']; ?></td>
                                                <td><?php echo $request['requestor_name']; ?></td>
                                                <td>
                                                    <?php if($request['request_type'] == 'support'): ?>
                                                        <span class="badge bg-info">Support</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">High-Level</span>
                                                    <?php endif; ?>
                                                </td>
                                                </td>
                                                <td><?php echo ucfirst($request['request_category']); ?></td>
                                                <td>
                                                <?php 
                                                if($request['request_category'] == 'Access Derestriction' && !empty($request['target_dept_name'])) {
                                                echo $request['target_dept_name'];
                                                } else {
                                                echo '-';
                                        }
                                        ?>
                                        </td>
                                                <td><?php echo substr($request['purpose'], 0, 50) . (strlen($request['purpose']) > 50 ? '...' : ''); ?></td>
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
                                                        default:
                                                            echo '<span class="badge bg-warning">Pending</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                                <td>
                                                    <a href="view_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">View</a>
                                                    <?php if(empty($request['manager_status']) || $request['manager_status'] == 'pending'): ?>
                                                    <a href="approve_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-success">Approve</a>
                                                    <a href="reject_request.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-danger">Reject</a>
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
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>&request_type=<?php echo $request_type; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo; Previous</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <li class="page-item disabled">
                                            <span class="page-link">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                                        </li>
                                        
                                        <?php if($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page+1; ?>&request_type=<?php echo $request_type; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>" aria-label="Next">
                                                    <span aria-hidden="true">Next &raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="alert alert-info">
                                <?php if($request_type != 'all' || $status != 'all' || !empty($search)): ?>
                                    No requests found with the selected filters. <a href="dashboard.php">Clear all filters</a>.
                                <?php else: ?>
                                    No requests found.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Add this before closing body tag in manager/dashboard.php -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load notifications
    function loadNotifications() {
        fetch('../includes/notifications.php')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('notifications-container');
                if (data.length === 0) {
                    container.innerHTML = '<li class="dropdown-item text-center">No notifications</li>';
                } else {
                    let html = '';
                    data.forEach(notification => {
                        const isRead = notification.is_read == 1;
                        html += `
                            <li>
                                <a href="view_request.php?id=${notification.request_id}" 
                                   class="dropdown-item notification-item ${!isRead ? 'fw-bold bg-light' : ''}"
                                   data-id="${notification.notification_id}">
                                    <div class="d-flex justify-content-between">
                                        <span>${notification.message}</span>
                                        <small class="text-muted">${timeAgo(new Date(notification.created_at))}</small>
                                    </div>
                                </a>
                            </li>
                        `;
                    });
                    container.innerHTML = html;
                    
                    // Add click event to mark as read
                    document.querySelectorAll('.notification-item').forEach(item => {
                        item.addEventListener('click', function(e) {
                            const notificationId = this.dataset.id;
                            markAsRead(notificationId);
                        });
                    });
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
    }
    
    // Mark notification as read
    function markAsRead(notificationId) {
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('notification_id', notificationId);
        
        fetch('../includes/notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI or notification count
                updateNotificationCount();
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    }
    
    // Mark all as read
    const markAllBtn = document.getElementById('mark-all-read');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'mark_all_read');
            
            fetch('../includes/notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    updateNotificationCount();
                    loadNotifications();
                }
            })
            .catch(error => console.error('Error marking all as read:', error));
        });
    }
    
    // Update notification count
    function updateNotificationCount() {
        fetch('../includes/notifications.php')
            .then(response => response.json())
            .then(data => {
                const unreadCount = data.filter(n => n.is_read == 0).length;
                const badge = document.querySelector('#notificationDropdown .badge');
                
                if (badge) {
                    if (unreadCount === 0) {
                        badge.style.display = 'none';
                    } else {
                        badge.textContent = unreadCount;
                        badge.style.display = 'block';
                    }
                }
            });
    }
    
    // Time ago function
    function timeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        
        let interval = Math.floor(seconds / 31536000);
        if (interval > 1) return interval + " years ago";
        if (interval === 1) return "1 year ago";
        
        interval = Math.floor(seconds / 2592000);
        if (interval > 1) return interval + " months ago";
        if (interval === 1) return "1 month ago";
        
        interval = Math.floor(seconds / 86400);
        if (interval > 1) return interval + " days ago";
        if (interval === 1) return "1 day ago";
        
        interval = Math.floor(seconds / 3600);
        if (interval > 1) return interval + " hours ago";
        if (interval === 1) return "1 hour ago";
        
        interval = Math.floor(seconds / 60);
        if (interval > 1) return interval + " minutes ago";
        if (interval === 1) return "1 minute ago";
        
        return "just now";
    }
    
    // Initial load
    loadNotifications();
    
    // Periodically check for new notifications (every 30 seconds)
    setInterval(loadNotifications, 30000);
});
</script>
</body>
</html>