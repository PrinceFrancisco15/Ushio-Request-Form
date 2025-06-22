<?php
// Get user details by ID
function getUserById($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch();
}

// Get department details by ID
function getDepartmentById($departmentId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = :department_id");
    $stmt->execute([':department_id' => $departmentId]);
    return $stmt->fetch();
}

// Get request type details by ID
function getRequestTypeById($typeId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM request_types WHERE type_id = :type_id");
    $stmt->execute([':type_id' => $typeId]);
    return $stmt->fetch();
}

// Get all active departments
function getAllDepartments() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY department_name");
    return $stmt->fetchAll();
}

// Get all request types
function getAllRequestTypes() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT * FROM request_types ORDER BY priority, type_name");
    return $stmt->fetchAll();
}

// Get department manager
function getDepartmentManager($departmentId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT u.* FROM users u 
                           JOIN departments d ON u.user_id = d.manager_id
                           WHERE d.department_id = :department_id");
    $stmt->execute([':department_id' => $departmentId]);
    return $stmt->fetch();
}

// Get general manager
function getGeneralManager() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'general_manager' LIMIT 1");
    $stmt->execute();
    return $stmt->fetch();
}

// Get all IT staff
function getAllITStaff() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'it_staff'");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get request details by ID
function getRequestById($requestId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT r.*, 
                          u_req.full_name as requestor_name,
                          d.department_name,
                          rt.type_name,
                          rt.priority as type_priority,
                          u_mgr.full_name as manager_name,
                          u_it.full_name as it_staff_name
                          FROM requests r
                          JOIN users u_req ON r.requestor_id = u_req.user_id
                          JOIN departments d ON r.department_id = d.department_id
                          JOIN request_types rt ON r.type_id = rt.type_id
                          LEFT JOIN users u_mgr ON r.manager_id = u_mgr.user_id
                          LEFT JOIN users u_it ON r.it_staff_id = u_it.user_id
                          WHERE r.request_id = :request_id");
    $stmt->execute([':request_id' => $requestId]);
    return $stmt->fetch();
}

// Get status label with appropriate color class
function getStatusLabel($status) {
    $labels = [
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'manager_approved' => '<span class="badge bg-info">Manager Approved</span>',
        'manager_rejected' => '<span class="badge bg-danger">Manager Rejected</span>',
        'gm_approved' => '<span class="badge bg-primary">GM Approved</span>',
        'gm_rejected' => '<span class="badge bg-danger">GM Rejected</span>',
        'it_assigned' => '<span class="badge bg-secondary">IT Assigned</span>',
        'in_progress' => '<span class="badge bg-info">In Progress</span>',
        'completed' => '<span class="badge bg-success">Completed</span>',
        'cancelled' => '<span class="badge bg-dark">Cancelled</span>'
    ];
    
    return $labels[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
}

// Format date and time
function formatDateTime($dateTime) {
    if (!$dateTime) return '-';
    $dt = new DateTime($dateTime);
    return $dt->format('M d, Y h:i A');
}

// Add this to includes/functions.php
function getMonthName($month_number) {
    $months = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
    ];
    
    return $months[$month_number] ?? 'Unknown';
}

// Add this to your functions.php file
function createITNotification($request_id, $message) {
    global $conn;
    
    // Get all IT staff members
    $sql = "SELECT user_id FROM users WHERE role = 'it_staff' OR role = 'general_it'";
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        $it_user_id = $row['user_id'];
        
        // Create notification for each IT staff
        $insert_sql = "INSERT INTO notifications (user_id, request_id, message, created_at) 
                       VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iis", $it_user_id, $request_id, $message);
        $stmt->execute();
    }
}
?>