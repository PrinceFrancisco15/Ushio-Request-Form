<?php
// it/download_archive.php
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a General IT
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_it') {
    header("Location: ../login.php");
    exit();
}

// Check if archive ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No archive ID provided";
    header("Location: view_archives.php");
    exit();
}

$archive_id = $_GET['id'];

// Get the archived report details
$stmt = $conn->prepare("SELECT * FROM archived_reports WHERE id = ?");
$stmt->bind_param("i", $archive_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Archived report not found";
    header("Location: view_archives.php");
    exit();
}

$archived_report = $result->fetch_assoc();

// Create a file name based on the archive date
$file_name = 'Monthly_Report_' . date('F_Y', strtotime($archived_report['archive_date'])) . '.csv';

// Set headers for file download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $file_name . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Get the original data
$original_data = json_decode($archived_report['original_data'], true);

if(is_array($original_data) && !empty($original_data)) {
    // Add header row with column names
    fputcsv($output, array_keys($original_data[0]));
    
    // Add data rows
    foreach($original_data as $row) {
        fputcsv($output, $row);
    }
} else {
    // If no structured data, just output the report content
    fputcsv($output, ['Report Content']);
    fputcsv($output, [$archived_report['report_content']]);
}

// Close output stream
fclose($output);
exit();
?>