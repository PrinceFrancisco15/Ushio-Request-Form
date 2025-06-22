<?php
// it/download_report.php - Download a monthly report as PDF
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_once 'fpdf/fpdf.php';

// Check if user is logged in and is a General IT
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_it') {
    header("Location: ../index.php");
    exit;
}

// Check if report ID is provided
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid report ID";
    header("Location: reports.php");
    exit;
}

$report_id = $_GET['id'];

// Get report details
$sql = "SELECT r.*, u.full_name as created_by_name
        FROM monthly_reports r
        LEFT JOIN users u ON r.created_by = u.user_id
        WHERE r.report_id = ?";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Report not found";
    header("Location: reports.php");
    exit;
}

$report = $result->fetch_assoc();
$report_data = json_decode($report['report_data'], true);

// Get completed requests for this report period - FIXED QUERY
$sql = "SELECT rf.*, u.full_name as it_staff_name, d.department_name 
        FROM request_forms rf
        LEFT JOIN users u ON rf.it_staff_id = u.user_id
        LEFT JOIN departments d ON rf.department_id = d.department_id
        WHERE rf.job_order_status = 'completed'
        AND MONTH(rf.completion_date) = ? 
        AND YEAR(rf.completion_date) = ?
        ORDER BY rf.completion_date DESC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $report['report_month'], $report['report_year']);
$stmt->execute();
$completed_result = $stmt->get_result();
$completed_requests = [];

if($completed_result->num_rows > 0) {
    while($row = $completed_result->fetch_assoc()) {
        $completed_requests[] = $row;
    }
}

// Get staff performance summary - FIXED QUERY
$sql = "SELECT u.user_id, u.full_name, 
        COUNT(rf.request_id) as completed_count,
        AVG(DATEDIFF(rf.completion_date, rf.created_at)) as avg_days
        FROM users u
        LEFT JOIN request_forms rf ON u.user_id = rf.it_staff_id 
            AND rf.job_order_status = 'completed'
            AND MONTH(rf.completion_date) = ? 
            AND YEAR(rf.completion_date) = ?
        WHERE u.role = 'it_staff'
        GROUP BY u.user_id
        ORDER BY completed_count DESC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $report['report_month'], $report['report_year']);
$stmt->execute();
$staff_result = $stmt->get_result();
$staff_performance = [];

if($staff_result->num_rows > 0) {
    while($row = $staff_result->fetch_assoc()) {
        $staff_performance[] = $row;
    }
}

// Generate PDF Report
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'IT Department Monthly Report', 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Report Information
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, $report['title'], 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Period: ' . date('F', mktime(0, 0, 0, $report['report_month'], 1)) . ' ' . $report['report_year'], 0, 1);
$pdf->Cell(0, 6, 'Created By: ' . $report['created_by_name'], 0, 1);
$pdf->Cell(0, 6, 'Created At: ' . date('M d, Y h:i A', strtotime($report['created_at'])), 0, 1);
$pdf->Cell(0, 6, 'Total Completed Requests: ' . ($report_data['total_count'] ?? count($completed_requests)), 0, 1);
$pdf->Ln(5);

// Staff Performance
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'IT Staff Performance', 0, 1);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 7, 'Staff Name', 1);
$pdf->Cell(60, 7, 'Completed Requests', 1);
$pdf->Cell(60, 7, 'Avg. Resolution Time (Days)', 1);
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);
foreach($staff_performance as $staff) {
    $pdf->Cell(60, 7, $staff['full_name'], 1);
    $pdf->Cell(60, 7, $staff['completed_count'] ?? 0, 1);
    $pdf->Cell(60, 7, is_null($staff['avg_days']) ? 0 : round($staff['avg_days'], 1), 1);
    $pdf->Ln();
}
$pdf->Ln(5);

// Completed Requests
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Completed Requests', 0, 1);

if(count($completed_requests) > 0) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(40, 7, 'Request ID', 1);
    $pdf->Cell(60, 7, 'Purpose', 1);
    $pdf->Cell(30, 7, 'Department', 1);
    $pdf->Cell(20, 7, 'Type', 1);
    $pdf->Cell(40, 7, 'IT Staff', 1);
    $pdf->Ln();
    
    $pdf->SetFont('Arial', '', 9);
    foreach($completed_requests as $req) {
        $pdf->Cell(40, 7, '#' . str_pad($req['request_id'], 4, '0', STR_PAD_LEFT), 1);
        $pdf->Cell(60, 7, substr($req['purpose'], 0, 30), 1);
        $pdf->Cell(30, 7, substr($req['department_name'], 0, 15), 1);
        
        // Map request type to a more readable form
        $request_type = $req['request_type'];
        if ($request_type == 'high_level') {
            $request_type = 'High Level';
        } elseif ($request_type == 'support') {
            $request_type = 'Support';
        }
        
        $pdf->Cell(20, 7, ucfirst($request_type), 1);
        $pdf->Cell(40, 7, substr($req['it_staff_name'], 0, 20), 1);
        $pdf->Ln();
    }
    
    // Second row for each request
    $pdf->Cell(40, 7, 'Created Date', 1);
    $pdf->Cell(40, 7, 'Completion Date', 1);
    $pdf->Cell(60, 7, 'Resolution Time', 1);
    $pdf->Cell(50, 7, 'Status', 1);
    $pdf->Ln();
    
    foreach($completed_requests as $req) {
        $request_date = new DateTime($req['created_at']);
        $completion_date = new DateTime($req['completion_date']);
        $interval = $request_date->diff($completion_date);
        
        $pdf->Cell(40, 7, date('M d, Y', strtotime($req['created_at'])), 1);
        $pdf->Cell(40, 7, date('M d, Y', strtotime($req['completion_date'])), 1);
        $pdf->Cell(60, 7, $interval->days . ' days', 1);
        $pdf->Cell(50, 7, 'Completed', 1);
        $pdf->Ln();
    }
} else {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 10, 'No completed requests found for this period.', 0, 1);
}

// Output PDF
$filename = 'IT_Report_' . date('F', mktime(0, 0, 0, $report['report_month'], 1)) . '_' . $report['report_year'] . '.pdf';
$pdf->Output('D', $filename);