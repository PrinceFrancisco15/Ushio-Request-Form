<?php
// gm/add_report_comment.php - Add comment to a report
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a general manager
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_manager') {
    header("Location: ../index.php");
    exit;
}

// Check if form was submitted
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    // Validate inputs
    if($report_id <= 0) {
        $_SESSION['error'] = "Invalid report ID.";
        header("Location: dashboard.php");
        exit;
    }
    
    if(empty($comment)) {
        $_SESSION['error'] = "Comment cannot be empty.";
        header("Location: view_report.php?id=$report_id");
        exit;
    }
    
    // Fetch current report
    $sql = "SELECT * FROM monthly_reports WHERE report_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_assoc();
    
    if(!$report) {
        $_SESSION['error'] = "Report not found.";
        header("Location: dashboard.php");
        exit;
    }
    
    // Get current report data
    $report_data = json_decode($report['report_data'], true);
    
    // Add GM comment
    $gm_comment = [
        'user_id' => $_SESSION['user_id'],
        'name' => $_SESSION['full_name'],
        'comment' => $comment,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Initialize or append to comments array
    if(!isset($report_data['gm_comments']) || !is_array($report_data['gm_comments'])) {
        $report_data['gm_comments'] = [];
    }
    
    $report_data['gm_comments'][] = $gm_comment;
    
    // Update report with new data
    $updated_data = json_encode($report_data);
    $sql = "UPDATE monthly_reports SET report_data = ? WHERE report_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $updated_data, $report_id);
    
    if($stmt->execute()) {
        // Send notification to General IT manager about the comment
        $notification_message = "General Manager commented on your report from " . $report_data['month'] . " " . $report_data['year'];
        
        // Fixed the query to use correct column names based on your database structure
        // Assuming the notifications table has columns: message instead of notification_text
// This matches your notifications table structure in the screenshot
$sql = "INSERT INTO notifications (
    user_id, 
    message, 
    is_read,
    created_at
) VALUES (?, ?, 0, NOW())";
    
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $report['created_by'], $notification_message);;

        
        $_SESSION['success'] = "Comment added successfully.";
    } else {
        $_SESSION['error'] = "Error adding comment: " . $conn->error;
    }
    
    // Redirect back to report view
    header("Location: view_report.php?id=$report_id");
    exit;
} else {
    // If not POST request, redirect back
    header("Location: dashboard.php");
    exit;
}
?>