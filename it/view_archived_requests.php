<?php
// it/view_archived_request.php
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

// Get the archived request details
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Archived Report</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h2>Archived Report Details</h2>
                <a href="view_archives.php" class="btn btn-secondary mb-3">Back to Archives</a>
                
                <div class="card">
                    <div class="card-header">
                        Report from: <?php echo date('F Y', strtotime($archived_report['archive_date'])); ?>
                    </div>
                    <div class="card-body">
                        <h5>Report Details</h5>
                        <div class="report-content">
                            <?php echo $archived_report['report_content']; ?>
                        </div>
                        
                        <hr>
                        
                        <h5>Original Data</h5>
                        <div class="original-data">
                            <?php 
                            $original_data = json_decode($archived_report['original_data'], true);
                            if(is_array($original_data)) {
                                echo "<table class='table table-bordered'>";
                                echo "<thead><tr>";
                                foreach(array_keys($original_data[0]) as $key) {
                                    echo "<th>".htmlspecialchars($key)."</th>";
                                }
                                echo "</tr></thead><tbody>";
                                
                                foreach($original_data as $row) {
                                    echo "<tr>";
                                    foreach($row as $value) {
                                        echo "<td>".htmlspecialchars($value)."</td>";
                                    }
                                    echo "</tr>";
                                }
                                echo "</tbody></table>";
                            } else {
                                echo "<p>No structured data available.</p>";
                            }
                            ?>
                        </div>
                        
                        <div class="mt-3">
                            <a href="download_archive.php?id=<?php echo $archive_id; ?>" class="btn btn-primary">Download Report</a>
                        </div>
                    </div>
                    <div class="card-footer text-muted">
                        Archived on: <?php echo date('F j, Y', strtotime($archived_report['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>