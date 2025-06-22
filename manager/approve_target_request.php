<?php
// manager/approve_target_request.php - Approve target department access request
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a manager
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'manager') {
    header("Location: ../index.php");
    exit;
}

// Get request ID
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid request ID.";
    header("Location: dashboard.php");
    exit;
}

$request_id = $_GET['id'];
$manager_id = $_SESSION['user_id'];

// Check if this manager is the target department manager for this request
$sql = "SELECT * FROM request_forms WHERE request_id = ? AND target_manager_id = ? AND request_category = 'Access Derestriction'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $manager_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "You are not authorized to approve this request or it's not an Access Derestriction request.";
    header("Location: dashboard.php");
    exit;
}

$request = $result->fetch_assoc();

// Update request target manager status to approved
$sql = "UPDATE request_forms SET target_manager_status = 'approved', updated_at = NOW() WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);

if($stmt->execute()) {
    // Check if GM approval is required and send notification to GM
    if($request['gm_status'] == 'pending') {
        // Create notification for GM
        $notification_message = "Access Derestriction request (#$request_id) has been approved by target department manager and needs your approval.";
        $sql = "INSERT INTO notifications (user_id, request_id, message) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $request['gm_id'], $request_id, $notification_message);
        $stmt->execute();
    }
    
    // Log the activity
    $action = "Target Manager Approval";
    $details = "Approved Access Derestriction request for department access";
    $sql = "INSERT INTO activity_logs (request_id, user_id, action, details) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $request_id, $manager_id, $action, $details);
    $stmt->execute();
    
    $_SESSION['success'] = "Request approved successfully!";
} else {
    $_SESSION['error'] = "Error approving request.";
}

header("Location: dashboard.php");
exit;
?>
