<!-- includes/navbar.php -->
<?php
// Get current page URL to highlight active link
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';


// Get unread notifications count for managers and IT staff
$unread_count = 0;
if(isset($_SESSION['role']) && ($_SESSION['role'] == 'manager' || $_SESSION['role'] == 'it_staff' || $_SESSION['role'] == 'general_it') && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $unread_count = $row['count'];
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="#">Request Form System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if(isset($_SESSION['user_id'])): ?>
                <ul class="navbar-nav me-auto">
                    <?php if($user_role == 'employee'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="/request-form-system/employee/dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'create_request.php') ? 'active' : ''; ?>" href="/request-form-system/employee/create_request.php">New Request</a>
                        </li>
                    <?php elseif($user_role == 'manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="/request-form-system/manager/dashboard.php">Dashboard</a>
                        </li>
                    <?php elseif($user_role == 'general_manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="/request-form-system/gm/dashboard.php">Dashboard</a>
                        </li>
                    <?php elseif($user_role == 'it_staff'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="/request-form-system/it/dashboard.php">Dashboard</a>
                        </li>
                    <?php elseif($user_role == 'general_it'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'general_it_dashboard.php') ? 'active' : ''; ?>" href="/request-form-system/it/general_it_dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                    <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'completed_history.php') ? 'active' : ''; ?>" href="/request-form-system/it/completed_history.php">
                                <i class="bi bi-check-circle"></i> Completed Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'generate_report.php') ? 'active' : ''; ?>" href="/request-form-system/it/generate_report.php">
                                <i class="bi bi-file-earmark-text"></i> Generate Reports
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if($user_role == 'manager' || $user_role == 'it_staff' || $user_role == 'general_it'): ?>
                    <!-- Notification Dropdown for Managers and IT Staff -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell-fill"></i>
                            <?php if($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $unread_count; ?>
                                <span class="visually-hidden">unread notifications</span>
                            </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown" style="width: 300px; max-height: 400px; overflow-y: auto;">
                            <li><h6 class="dropdown-header">Notifications</h6></li>
                            <div id="notifications-container">
                                <!-- Notifications will be loaded here by JavaScript -->
                                <li class="dropdown-item text-center">Loading notifications...</li>
                            </div>
                            <?php if($unread_count > 0): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#" id="mark-all-read">Mark all as read</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo $user_name; ?> (<?php echo ucfirst(str_replace('_', ' ', $user_role)); ?>)
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/request-form-system/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Add notification styles -->
<style>
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

<?php if(isset($_SESSION['role']) && ($_SESSION['role'] == 'manager' || $_SESSION['role'] == 'it_staff' || $_SESSION['role'] == 'general_it')): ?>
<!-- Add notification JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load notifications
    function loadNotifications() {
        fetch('/request-form-system/includes/notifications.php')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('notifications-container');
                if (data.length === 0) {
                    container.innerHTML = '<li class="dropdown-item text-center">No notifications</li>';
                } else {
                    let html = '';
                    data.forEach(notification => {
                        const isRead = notification.is_read == 1;
                        
                        // Determine the correct URL based on user role
                        let linkUrl = '/request-form-system/manager/view_request.php?id=' + notification.request_id;
                        if ('<?php echo $user_role; ?>' === 'it_staff' || '<?php echo $user_role; ?>' === 'general_it') {
                            linkUrl = '/request-form-system/it/view_request.php?id=' + notification.request_id;
                        }
                        
                        html += `
                            <li>
                                <a href="${linkUrl}" 
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
        
        fetch('/request-form-system/includes/notifications.php', {
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
            
            fetch('/request-form-system/includes/notifications.php', {
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
        fetch('/request-form-system/includes/notifications.php')
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
<?php endif; ?>