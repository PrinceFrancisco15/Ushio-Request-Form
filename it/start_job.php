<?php
// it/start_job.php - Start working on an assigned job
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
// Check if user is logged in and is an IT staff
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'it_staff') {
    header("Location: ../index.php");
    exit;
}
if(!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}
$request_id = $_GET['id'];
$it_staff_id = $_SESSION['user_id'];
// Check if the request exists, is assigned to this IT staff, and has status as assigned
$stmt = $pdo->prepare("SELECT * FROM requests WHERE request_id = ? AND assigned_to = ? AND status = 'assigned'");
$stmt->execute([$request_id, $it_staff_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$request) {
    $_SESSION['error'] = "Invalid request or not assigned to you.";
    header("Location: dashboard.php");
    exit;
}

// Update the status to "in progress"
$stmt = $pdo->prepare("UPDATE requests SET status = 'in progress' WHERE request_id = ?");
$stmt->execute([$request_id]);

$_SESSION['success'] = "You have started working on this request.";
header("Location: dashboard.php");
exit;
?>