<?php
// it/view_archives.php - Interface for viewing archived reports
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and has appropriate role
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'general_it') {
    header("Location: ../index.php");
    exit;
}

// Get list of available archives
$archives_path = dirname(__FILE__) . '/../archives/reports/';
$available_archives = [];

if (file_exists($archives_path)) {
    $directories = scandir($archives_path);
    $directories = array_diff($directories, ['.', '..']);
    
    // Sort directories in reverse order (newest first)
    arsort($directories);
    
    foreach ($directories as $dir) {
        if (is_dir($archives_path . $dir) && preg_match('/^\d{4}-\d{2}$/', $dir)) {
            // Read summary file
            $summary_file = $archives_path . $dir . '/summary.json';
            if (file_exists($summary_file)) {
                $summary = json_decode(file_get_contents($summary_file), true);
                $available_archives[] = [
                    'period' => $dir,
                    'count' => $summary['total_requests'],
                    'archived_date' => $summary['archived_date']
                ];
            } else {
                // If no summary file, just add directory info
                $count = count(glob($archives_path . $dir . '/request_*.json'));
                $available_archives[] = [
                    'period' => $dir,
                    'count' => $count,
                    'archived_date' => 'Unknown'
                ];
            }
        }
    }
}

$selected_period = isset($_GET['period']) ? $_GET['period'] : '';
$requests = [];

// If a period is selected, show its archived reports
if (!empty($selected_period) && preg_match('/^\d{4}-\d{2}$/', $selected_period)) {
    $period_path = $archives_path . $selected_period . '/';
    
    if (file_exists($period_path)) {
        $files = glob($period_path . 'request_*.json');
        
        foreach ($files as $file) {
            $request_data = json_decode(file_get_contents($file), true);
            $requests[] = $request_data;
        }
        
        // Sort by request ID
        usort($requests, function($a, $b) {
            return $a['request_id'] - $b['request_id'];
        });
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Reports - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="general_it_dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Archived Reports</li>
                <?php if (!empty($selected_period)): ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($selected_period); ?></li>
                <?php endif; ?>
            </ol>
        </nav>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-archive"></i> Archived Reports</h2>
            <div>
                <?php if (!empty($selected_period)): ?>
                <a href="download_archive.php?period=<?php echo $selected_period; ?>" class="btn btn-success me-2">
                    <i class="bi bi-download"></i> Download Archive
                </a>
                <a href="view_archives.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Archive List
                </a>
                <?php else: ?>
                <a href="archive_now.php" class="btn btn-primary">
                    <i class="bi bi-archive"></i> Archive Current Month
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (empty($selected_period)): ?>
        <!-- List of available archives -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Available Archives</h5>
            </div>
            <div class="card-body">
                <?php if (empty($available_archives)): ?>
                <div class="alert alert-info">
                    No archived reports found. Use the "Archive Current Month" button to create your first archive.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="archivesTable">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Request Count</th>
                                <th>Archived Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_archives as $archive): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($archive['period']); ?></td>
                                <td><?php echo $archive['count']; ?></td>
                                <td>
                                    <?php 
                                    if ($archive['archived_date'] != 'Unknown') {
                                        echo date('M d, Y H:i', strtotime($archive['archived_date']));
                                    } else {
                                        echo 'Unknown';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="view_archives.php?period=<?php echo $archive['period']; ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="download_archive.php?period=<?php echo $archive['period']; ?>" 
                                       class="btn btn-sm btn-success">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Selected period's archived requests -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Archived Requests: <?php echo htmlspecialchars($selected_period); ?></h5>
            </div>
            <div class="card-body">
                <?php if (empty($requests)): ?>
                <div class="alert alert-info">
                    No request data found for this period.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="requestsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Request Type</th>
                                <th>Department</th>
                                <th>Requestor</th>
                                <th>Staff</th>
                                <th>Completion Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo $request['request_id']; ?></td>
                                <td><?php echo ucfirst(htmlspecialchars($request['request_type'])); ?></td>
                                <td><?php echo htmlspecialchars($request['department_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['requestor_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['staff_name']); ?></td>
                                <td>
                                    <?php 
                                    if (isset($request['completion_date'])) {
                                        echo date('M d, Y', strtotime($request['completion_date']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="view_archived_request.php?period=<?php echo $selected_period; ?>&id=<?php echo $request['request_id']; ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <a href="../archives/reports/<?php echo $selected_period; ?>/request_<?php echo $request['request_id']; ?>.pdf" 
                                       class="btn btn-sm btn-success" download>
                                        <i class="bi bi-download"></i> PDF
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#archivesTable').DataTable({
                order: [[0, 'desc']]
            });
            
            $('#requestsTable').DataTable({
                order: [[0, 'asc']]
            });
        });
    </script>
</body>
</html>