<?php
// it/send_report_to_manager.php - Send monthly reports to General Manager
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a General IT
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_it') {
    header("Location: ../index.php");
    exit;
}

$general_it_id = $_SESSION['user_id'];

// If form is submitted to send report
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_report'])) {
    $filter_month = (int)$_POST['month'];
    $filter_year = (int)$_POST['year'];
    $report_message = $_POST['report_message'];
    $month_name = date('F', mktime(0, 0, 0, $filter_month, 1));
    
    // Get IT staff performance data
    $sql = "SELECT 
            u.user_id as staff_id,
            u.full_name as staff_name,
            COUNT(CASE WHEN r.job_order_status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN r.job_order_status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN r.job_order_status = 'pending' THEN 1 END) as pending_count,
            COUNT(r.request_id) as total_assigned,
            AVG(CASE WHEN r.job_order_status = 'completed' 
                THEN DATEDIFF(r.completion_date, r.created_at) 
                ELSE NULL END) as avg_days
        FROM users u
        LEFT JOIN request_forms r ON u.user_id = r.it_staff_id
        WHERE u.role = 'it_staff'
        AND MONTH(r.created_at) = ? AND YEAR(r.created_at) = ?
        GROUP BY u.user_id, u.full_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $filter_month, $filter_year);
    $stmt->execute();
    $staff_result = $stmt->get_result();
    
    // Get department statistics
    $sql = "SELECT 
            d.department_name,
            COUNT(r.request_id) as request_count
        FROM request_forms r
        LEFT JOIN departments d ON r.department_id = d.department_id
        WHERE r.job_order_status = 'completed'
        AND MONTH(r.completion_date) = ? AND YEAR(r.completion_date) = ?
        GROUP BY d.department_id
        ORDER BY request_count DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $filter_month, $filter_year);
    $stmt->execute();
    $dept_result = $stmt->get_result();
    
    // Get request type statistics
    $sql = "SELECT 
            request_type,
            COUNT(*) as type_count
        FROM request_forms
        WHERE job_order_status = 'completed'
        AND MONTH(completion_date) = ? AND YEAR(completion_date) = ?
        GROUP BY request_type
        ORDER BY type_count DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $filter_month, $filter_year);
    $stmt->execute();
    $type_result = $stmt->get_result();
    
    // Get total requests count
    $sql = "SELECT 
            COUNT(*) as total_count,
            COUNT(CASE WHEN request_type = 'support' THEN 1 END) as support_count,
            COUNT(CASE WHEN request_type = 'highlevel' THEN 1 END) as high_level_count,
            AVG(TIMESTAMPDIFF(HOUR, created_at, completion_date)) as avg_resolution
            FROM request_forms
            WHERE job_order_status = 'completed'
            AND MONTH(completion_date) = ? AND YEAR(completion_date) = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $filter_month, $filter_year);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
    
    // Get daily data for chart
    $sql = "SELECT 
            DAY(completion_date) as day,
            COUNT(*) as count
            FROM request_forms
            WHERE job_order_status = 'completed'
            AND MONTH(completion_date) = ? AND YEAR(completion_date) = ?
            GROUP BY DAY(completion_date)
            ORDER BY day";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $filter_month, $filter_year);
    $stmt->execute();
    $daily_result = $stmt->get_result();
    $daily_data = [];
    while ($row = $daily_result->fetch_assoc()) {
        $daily_data[] = $row;
    }
    
    // Handle file uploads
    $attachments = [];
    $upload_dir = '../uploads/reports/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
        $files = $_FILES['attachments'];
        $file_count = count($files['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $tmp_name = $files['tmp_name'][$i];
                $name = $files['name'][$i];
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                
                // Generate unique filename
                $new_filename = uniqid('report_') . '_' . date('Ymd') . '.' . $extension;
                $destination = $upload_dir . $new_filename;
                
                if (move_uploaded_file($tmp_name, $destination)) {
                    $attachments[] = [
                        'original_name' => $name,
                        'stored_name' => $new_filename,
                        'file_path' => $destination,
                        'file_size' => $files['size'][$i],
                        'file_type' => $files['type'][$i]
                    ];
                }
            }
        }
    }
    
    // Prepare staff performance data for the report
    $staff_performance = [];
    while ($staff = $staff_result->fetch_assoc()) {
        // Calculate performance score based on completed tasks and resolution time
        // Simple metric: percentage of completed vs assigned (could be enhanced)
        $performance = ($staff['completed_count'] / max(1, $staff['total_assigned'])) * 100;
        
        $staff_performance[] = [
            'name' => $staff['staff_name'],
            'completed_count' => $staff['completed_count'],
            'in_progress_count' => $staff['in_progress_count'],
            'pending_count' => $staff['pending_count'],
            'total_assigned' => $staff['total_assigned'],
            'avg_resolution' => number_format($staff['avg_days'] * 24, 1), // Convert days to hours
            'performance' => round($performance, 1)
        ];
    }
    
    // Prepare report data
    $report_data = [
        'month' => $month_name,
        'year' => $filter_year,
        'comments' => $report_message,
        'total_count' => $totals['total_count'],
        'support_count' => $totals['support_count'],
        'high_level_count' => $totals['high_level_count'],
        'avg_resolution' => number_format($totals['avg_resolution'], 1),
        'dept_stats' => $dept_result->fetch_all(MYSQLI_ASSOC),
        'staff_stats' => $staff_result->fetch_all(MYSQLI_ASSOC),
        'type_stats' => $type_result->fetch_all(MYSQLI_ASSOC),
        'staff_performance' => $staff_performance,
        'daily_data' => $daily_data,
        'attachments' => $attachments
    ];
    
    $report_json = json_encode($report_data);
    $report_title = "IT Request Report - $month_name $filter_year";
    
    // Save to reports table
    $sql = "INSERT INTO monthly_reports (
            title, 
            report_month, 
            report_year, 
            report_data,
            created_by,
            created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siisi", $report_title, $filter_month, $filter_year, $report_json, $general_it_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Report sent successfully to General Manager!";
        header("Location: general_it_dashboard.php");
        exit();
    } else {
        $_SESSION['error'] = "Error sending report: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Report to General Manager - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-send"></i> Send Report to General Manager</h2>
            <a href="general_it_dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Monthly Report Form</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="month" class="form-label">Report Month</label>
                            <select class="form-select" id="month" name="month" required>
                                <?php for($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($i == date('n')) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="year" class="form-label">Report Year</label>
                            <select class="form-select" id="year" name="year" required>
                                <?php 
                                $current_year = date('Y');
                                for($y = $current_year; $y >= $current_year - 5; $y--): 
                                ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($y == $current_year) ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="report_message" class="form-label">Report Message</label>
                        <textarea class="form-control" id="report_message" name="report_message" rows="4" 
                                required placeholder="Enter any notes or comments about this month's performance..."></textarea>
                    </div>
                    
                    <!-- File Attachment Section -->
                    <div class="mb-3">
                        <label for="attachments" class="form-label">Attach Files (Optional)</label>
                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                        <div class="form-text text-muted">
                            You can attach multiple files (PDFs, Excel sheets, images, etc.). Max size per file: 5MB.
                        </div>
                    </div>
                    
                    <div id="selected-files" class="mb-3">
                        <!-- Selected files will appear here -->
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> This report will include all completed requests for the selected month, 
                        along with IT staff performance statistics and departmental summaries.
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" name="send_report" value="1" class="btn btn-success">
                            <i class="bi bi-send"></i> Send Report
                        </button>
                        <a href="general_it_dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script to display selected file names
        document.getElementById('attachments').addEventListener('change', function(e) {
            const fileList = e.target.files;
            const fileDisplay = document.getElementById('selected-files');
            fileDisplay.innerHTML = '';
            
            if (fileList.length > 0) {
                const fileGroup = document.createElement('div');
                fileGroup.className = 'list-group';
                
                for (let i = 0; i < fileList.length; i++) {
                    const file = fileList[i];
                    const fileItem = document.createElement('div');
                    fileItem.className = 'list-group-item list-group-item-action';
                    
                    // Format file size
                    let fileSize = file.size;
                    let sizeUnit = 'bytes';
                    if (fileSize > 1024) {
                        fileSize = (fileSize / 1024).toFixed(2);
                        sizeUnit = 'KB';
                    }
                    if (fileSize > 1024) {
                        fileSize = (fileSize / 1024).toFixed(2);
                        sizeUnit = 'MB';
                    }
                    
                    fileItem.innerHTML = `
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <h6 class="mb-1">${file.name}</h6>
                            <small>${fileSize} ${sizeUnit}</small>
                        </div>
                        <small class="text-muted">${file.type || 'Unknown file type'}</small>
                    `;
                    
                    fileGroup.appendChild(fileItem);
                }
                
                fileDisplay.appendChild(fileGroup);
            }
        });
    </script>
</body>
</html>