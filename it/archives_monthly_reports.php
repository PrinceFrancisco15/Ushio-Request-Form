<?php
// /it/archive_monthly_reports.php - Core functionality for archiving monthly reports
require_once '../config/database.php';
require_once '../includes/functions.php';

/**
 * Archive monthly reports to a structured folder system
 * @param string $year_month Format: YYYY-MM (if null, uses previous month)
 * @return array Information about the archiving process
 */
function archiveMonthlyReports($year_month = null) {
    global $conn;
    
    // If no specific month provided, use previous month
    if ($year_month === null) {
        $year_month = date('Y-m', strtotime('-1 month'));
    }
    
    // Extract year and month
    list($year, $month) = explode('-', $year_month);
    
    // Create directory structure if it doesn't exist
    $archive_base_path = dirname(__FILE__) . '/../archives/reports/';
    $archive_path = $archive_base_path . $year_month;
    
    if (!file_exists($archive_base_path)) {
        mkdir($archive_base_path, 0755, true);
    }
    
    if (!file_exists($archive_path)) {
        mkdir($archive_path, 0755, true);
    }
    
    // Define date range for the month
    $start_date = "$year-$month-01 00:00:00";
    $end_date = date('Y-m-t 23:59:59', strtotime($start_date));
    
    // Get all completed requests for that month
    $sql = "SELECT r.*, d.department_name, 
            u1.full_name as requestor_name, u2.full_name as staff_name,
            u1.email as requestor_email, u1.contact_number as requestor_contact
            FROM request_forms r
            LEFT JOIN departments d ON r.department_id = d.department_id
            LEFT JOIN users u1 ON r.requestor_id = u1.user_id
            LEFT JOIN users u2 ON r.it_staff_id = u2.user_id
            WHERE r.job_order_status = 'completed'
            AND r.completion_date BETWEEN ? AND ?
            ORDER BY r.completion_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $archived_count = 0;
    $report_summary = [];
    
    // Check if any data exists for this month
    if ($result->num_rows == 0) {
        return [
            'status' => 'empty',
            'message' => "No completed requests found for $year_month",
            'archived_count' => 0
        ];
    }
    
    // Process each completed request
    while ($request = $result->fetch_assoc()) {
        $request_id = $request['request_id'];
        
        // Create JSON file with request data
        $json_filename = $archive_path . "/request_" . $request_id . ".json";
        file_put_contents($json_filename, json_encode($request, JSON_PRETTY_PRINT));
        
        // Generate PDF report for this request (simplified version)
        generateRequestPDF($request, $archive_path . "/request_" . $request_id . ".pdf");
        
        $archived_count++;
        
        // Collect summary information
        $report_summary[] = [
            'request_id' => $request_id,
            'request_type' => $request['request_type'],
            'department' => $request['department_name'],
            'requestor' => $request['requestor_name'],
            'staff' => $request['staff_name'],
            'completion_date' => $request['completion_date']
        ];
    }
    
    // Create a monthly summary JSON file
    $summary = [
        'year_month' => $year_month,
        'archived_date' => date('Y-m-d H:i:s'),
        'total_requests' => $archived_count,
        'requests' => $report_summary
    ];
    
    $summary_filename = $archive_path . "/summary.json";
    file_put_contents($summary_filename, json_encode($summary, JSON_PRETTY_PRINT));
    
    // Generate overall monthly report PDF
    generateMonthlyReportPDF($summary, $archive_path . "/monthly_report_" . $year_month . ".pdf");
    
    // Log the archiving action
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $log_sql = "INSERT INTO activity_logs (user_id, action_type, action_details, action_timestamp) 
                VALUES (?, 'archive', ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $log_details = "Archived $archived_count requests for $year_month";
    $log_stmt->bind_param("is", $user_id, $log_details);
    $log_stmt->execute();
    
    return [
        'status' => 'success',
        'message' => "Successfully archived $archived_count requests for $year_month",
        'archived_count' => $archived_count,
        'archive_path' => $archive_path
    ];
}

/**
 * Generate a PDF for a single request
 * Note: This is a placeholder function - implement with a PDF library 
 * like FPDF or TCPDF in production
 */
function generateRequestPDF($request, $output_path) {
    // This is a simplified version - in production you would use a PDF library
    // For now, we'll just create a text file as a placeholder
    $content = "REQUEST DETAILS\n";
    $content .= "==============\n\n";
    $content .= "Request ID: " . $request['request_id'] . "\n";
    $content .= "Type: " . $request['request_type'] . "\n";
    $content .= "Description: " . $request['request_description'] . "\n";
    $content .= "Department: " . $request['department_name'] . "\n";
    $content .= "Requestor: " . $request['requestor_name'] . "\n";
    $content .= "Assigned To: " . $request['staff_name'] . "\n";
    $content .= "Submitted: " . $request['created_at'] . "\n";
    $content .= "Completed: " . $request['completion_date'] . "\n";
    
    // In a real implementation, you would use a PDF library like:
    /*
    require_once('../vendor/fpdf/fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(40, 10, 'Request #' . $request['request_id']);
    // Add more details here
    $pdf->Output($output_path, 'F');
    */
    
    file_put_contents($output_path, $content);
}

/**
 * Generate a PDF for the monthly report summary
 * Note: This is a placeholder function - implement with a PDF library 
 */
function generateMonthlyReportPDF($summary, $output_path) {
    // Simplified placeholder version
    $content = "MONTHLY REPORT: " . $summary['year_month'] . "\n";
    $content .= "==========================\n\n";
    $content .= "Total Completed Requests: " . $summary['total_requests'] . "\n";
    $content .= "Generated on: " . $summary['archived_date'] . "\n\n";
    $content .= "REQUEST SUMMARY\n";
    $content .= "---------------\n\n";
    
    foreach ($summary['requests'] as $request) {
        $content .= "ID: " . $request['request_id'] . "\n";
        $content .= "Type: " . $request['request_type'] . "\n";
        $content .= "Department: " . $request['department'] . "\n";
        $content .= "Requestor: " . $request['requestor'] . "\n";
        $content .= "Staff: " . $request['staff'] . "\n";
        $content .= "Completed: " . $request['completion_date'] . "\n\n";
    }
    
    // In a real implementation, you would use a PDF library as shown above
    
    file_put_contents($output_path, $content);
}

// If executed directly, run the archive function for testing
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    $archive_result = archiveMonthlyReports();
    echo "Archive Status: " . $archive_result['status'] . "\n";
    echo "Message: " . $archive_result['message'] . "\n";
}
?>