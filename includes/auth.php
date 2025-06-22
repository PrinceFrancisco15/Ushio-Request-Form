<?php


// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (is_array($role)) {
        return in_array($_SESSION['role'], $role);
    } else {
        return $_SESSION['role'] === $role;
    }
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit;
    }
}

// Redirect if not authorized for a role
function requireRole($role) {
    requireLogin();
    
    if (!hasRole($role)) {
        header("Location: /unauthorized.php");
        exit;
    }
}

// Log user activity
function logActivity($requestId, $userId, $action, $description = '') {
    global $pdo;
    
    $sql = "INSERT INTO activity_logs (request_id, user_id, action, description) 
            VALUES (:request_id, :user_id, :action, :description)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':request_id' => $requestId,
        ':user_id' => $userId,
        ':action' => $action,
        ':description' => $description
    ]);
}

// Generate unique reference number
function generateReferenceNumber() {
    return 'REQ-' . date('Ymd') . '-' . rand(1000, 9999);
}

// Generate ticket number for IT department
function generateTicketNumber() {
    return 'IT-' . date('Ymd') . '-' . rand(100, 999);
}

function setUserDepartment($userId) {
    global $conn; // or $pdo depending on your connection variable name
    
    $sql = "SELECT department_id FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $_SESSION['department_id'] = $row['department_id'];
        return true;
    }
    
    return false;
}
?>