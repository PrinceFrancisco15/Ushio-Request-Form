<?php
// employee/delete_request.php - Delete a pending request
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


// Check if user is logged in and is an employee
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: ../index.php");
    exit;
}

if(!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$request_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Check if this request belongs to this employee and is still pending
$sql = "SELECT * FROM request_forms WHERE request_id = ? AND requestor_id = ? AND manager_status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Request not found, already processed, or you don't have permission to delete it.";
    header("Location: dashboard.php");
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {

    $sql = "DELETE FROM request_attachments WHERE request_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();

    $sql = "DELETE FROM notifications WHERE request_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();

    // Delete related items first (due to foreign key constraints)
    $sql = "DELETE FROM request_items WHERE request_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    
    // Delete activity logs
    $sql = "DELETE FROM activity_logs WHERE request_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    
    // Delete the request
    $sql = "DELETE FROM request_forms WHERE request_id = ?";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Redirect to dashboard with success message
    $_SESSION['success'] = "Request deleted successfully!";
    header("Location: dashboard.php");
    exit;
    
} catch (Exception $e) {
    // Rollback if error
    $conn->rollback();
    $_SESSION['error'] = "Error deleting request: " . $e->getMessage();
    header("Location: dashboard.php");
    exit;
}
?>