<?php
// employee/edit_request.php - Edit existing request form
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

// Get departments list for dropdown
$user_department_id = $_SESSION['department_id']; // Make sure this is set during login
$sql = "SELECT * FROM departments WHERE department_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_department_id);
$stmt->execute();
$result = $stmt->get_result();
$departments = $result->fetch_all(MYSQLI_ASSOC);

// Check if this request belongs to this employee and is still pending
$sql = "SELECT r.*, i.item_type, i.item_description, i.quantity, r.request_category
        FROM request_forms r
        LEFT JOIN request_items i ON r.request_id = i.request_id
        WHERE r.request_id = ? AND r.requestor_id = ? AND r.manager_status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error'] = "Request not found, already processed, or you don't have permission to edit it.";
    header("Location: dashboard.php");
    exit;
}

$request = $result->fetch_assoc();

// Get existing attachments
$sql = "SELECT * FROM request_attachments WHERE request_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$attachments = $result->fetch_all(MYSQLI_ASSOC);

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $request_type = $_POST['request_type'];
    $request_category = $_POST['request_category'];
    $requestor_name = mysqli_real_escape_string($conn, $_POST['requestor_name']);
    $purpose = $_POST['purpose'];
    $department_id = $_POST['department_id'];
    $item_type = $_POST['item_type'];
    $item_description = $_POST['item_description'];
    $quantity = $_POST['quantity'];
    
    // Get the department manager ID for the selected department
    $sql = "SELECT user_id FROM users WHERE role = 'manager' AND department_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($manager = $result->fetch_assoc()) {
        $manager_id = $manager['user_id'];
    } else {
        // Handle case when department has no manager
        $_SESSION['error'] = "Selected department has no assigned manager!";
        header("Location: dashboard.php");
        exit;
    }
    
    // Get GM
    $sql = "SELECT user_id FROM users WHERE role = 'general_manager' LIMIT 1";
    $result = $conn->query($sql);
    $gm = $result->fetch_assoc();
    $gm_id = $gm['user_id'];

    $sql = "SELECT user_id FROM users WHERE role = 'general_it' LIMIT 1";
    $result = $conn->query($sql);
    $general_it = $result->fetch_assoc();
    $general_it_id = $general_it['user_id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        $manager_status = 'pending';
        $gm_status = 'pending';
        
        // For support requests, GM approval is not required
        if ($request_type == 'support') {
            $gm_status = 'not_required';
        }
        
        // For "others" requests, only General IT approval is needed
        if ($request_type == 'others') {
            $manager_status = 'not_required';
            $gm_status = 'not_required';
        }

        // Update request
        $sql = "UPDATE request_forms SET 
                request_type = ?,
                request_category = ?,
                requestor_name = ?,
                purpose = ?,
                department_id = ?,
                manager_id = ?,
                gm_id = ?,
                manager_status = ?,
                gm_status = ?
                WHERE request_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiiissi", $request_type, $request_category, $requestor_name, $purpose, $department_id, $manager_id, $gm_id, $manager_status, $gm_status, $request_id);
        $stmt->execute();
        
        // Update request items
        $sql = "UPDATE request_items SET 
                item_type = ?,
                item_description = ?,
                quantity = ?
                WHERE request_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $item_type, $item_description, $quantity, $request_id);
        $stmt->execute();
        
        // Handle file uploads
        if (!empty($_FILES['attachments']['name'][0])) {
            // Create directory if it doesn't exist
            $upload_dir = "../uploads/requests/" . $request_id . "/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Process each uploaded file
            $file_count = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['attachments']['error'][$i] == 0) {
                    $temp_name = $_FILES['attachments']['tmp_name'][$i];
                    $file_name = $_FILES['attachments']['name'][$i];
                    $file_size = $_FILES['attachments']['size'][$i];
                    $file_type = $_FILES['attachments']['type'][$i];
                    
                    // Generate unique filename
                    $new_file_name = uniqid() . '_' . $file_name;
                    $file_path = $upload_dir . $new_file_name;
                    
                    // Move uploaded file
                    if (move_uploaded_file($temp_name, $file_path)) {
                        // Insert file info into database
                        $sql = "INSERT INTO request_attachments (request_id, file_name, file_path, file_type, file_size) 
                                VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("isssi", $request_id, $file_name, $file_path, $file_type, $file_size);
                        $stmt->execute();
                    }
                }
            }
        }
        
        // Handle pasted images
        if (isset($_POST['pasted_images']) && !empty($_POST['pasted_images'])) {
            $pasted_images = json_decode($_POST['pasted_images'], true);
            
            // Create directory if it doesn't exist
            $upload_dir = "../uploads/requests/" . $request_id . "/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($pasted_images as $index => $image_data) {
                // Get image data and decode base64
                list($type, $data) = explode(';', $image_data);
                list(, $data) = explode(',', $data);
                $image_data = base64_decode($data);
                
                // Get image extension from mime type
                list(, $mime) = explode(':', $type);
                $ext = ($mime == 'image/jpeg') ? 'jpg' : 
                      (($mime == 'image/png') ? 'png' : 
                      (($mime == 'image/gif') ? 'gif' : 'png'));
                
                // Generate filename
                $file_name = "pasted_image_" . date('YmdHis') . "_" . $index . "." . $ext;
                $file_path = $upload_dir . $file_name;
                
                // Save image file
                file_put_contents($file_path, $image_data);
                
                // Insert image info into database
                $file_size = strlen($image_data);
                $sql = "INSERT INTO request_attachments (request_id, file_name, file_path, file_type, file_size) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssi", $request_id, $file_name, $file_path, $mime, $file_size);
                $stmt->execute();
            }
        }
        
        // Log activity
        $action = "Request updated";
        $details = "Updated " . ucfirst($request_type) . " request (" . $request_category . ")";
        $sql = "INSERT INTO activity_logs (request_id, user_id, action, details) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $request_id, $_SESSION['user_id'], $action, $details);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to view page with success message
        $_SESSION['success'] = "Request updated successfully!";
        header("Location: view_request.php?id=".$request_id);
        exit;
        
    } catch (Exception $e) {
        // Rollback if error
        $conn->rollback();
        $error = "Error updating request: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Request - Request Form System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        #image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .image-preview {
            position: relative;
            width: 150px;
            height: 150px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255,0,0,0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .file-preview {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
            width: 100%;
        }
        .file-preview i {
            margin-right: 10px;
            font-size: 24px;
        }
        .file-info {
            flex-grow: 1;
        }
        .file-name {
            font-weight: bold;
        }
        .file-size {
            font-size: 12px;
            color: #6c757d;
        }
        .file-remove {
            color: #dc3545;
            cursor: pointer;
            margin-left: 10px;
        }
        .purpose-container {
            position: relative;
        }
        .paste-instruction {
            position: absolute;
            bottom: 10px;
            right: 10px;
            font-size: 12px;
            color: #6c757d;
            pointer-events: none;
        }
        .existing-attachment {
            display: flex;
            align-items: center;
            background: #e9f5ff;
            border: 1px solid #b8daff;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Edit Request #<?php echo $request_id; ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="" enctype="multipart/form-data" id="requestForm">
                            <div class="mb-3">
                                <label class="form-label">Request Type</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="request_type" id="support" value="support" <?php echo ($request['request_type'] == 'support') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="support">
                                        Support Request (Downtime - 1 hour resolution)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="request_type" id="high_level" value="high_level" <?php echo ($request['request_type'] == 'high_level') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="high_level">
                                        High Level Request (Hardware/Software Installation, etc.)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="request_type" id="others" value="others" <?php echo ($request['request_type'] == 'others') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="others">
                                        Others (Basic peripherals - mouse, keyboard, etc.)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="request_category" class="form-label">Request Category</label>
                                
                                <!-- Support/Downtime options -->
                                <select class="form-control" id="support_category" name="request_category" style="display:<?php echo ($request['request_type'] == 'support') ? 'block' : 'none'; ?>;">
                                    <option value="">-- Select Category --</option>
                                    <option value="Local network" <?php echo ($request['request_category'] == 'Local network') ? 'selected' : ''; ?>>Local network (1 hour)</option>
                                    <option value="Internet" <?php echo ($request['request_category'] == 'Internet') ? 'selected' : ''; ?>>Internet (1 hour)</option>
                                    <option value="Hardware" <?php echo ($request['request_category'] == 'Hardware') ? 'selected' : ''; ?>>Hardware (1 hour)</option>
                                    <option value="Software" <?php echo ($request['request_category'] == 'Software') ? 'selected' : ''; ?>>Software (1 hour)</option>
                                    <option value="Data" <?php echo ($request['request_category'] == 'Data') ? 'selected' : ''; ?>>Data (1 hour)</option>
                                    <option value="Printer" <?php echo ($request['request_category'] == 'Printer') ? 'selected' : ''; ?>>Printer (1 hour)</option>
                                    <option value="Telephone" <?php echo ($request['request_category'] == 'Telephone') ? 'selected' : ''; ?>>Telephone (1 hour)</option>
                                    <option value="Website" <?php echo ($request['request_category'] == 'Website') ? 'selected' : ''; ?>>Website (1 hour)</option>
                                    <option value="E-mail" <?php echo ($request['request_category'] == 'E-mail') ? 'selected' : ''; ?>>E-mail (1 hour)</option>
                                </select>
                                
                                <!-- High-level/Leadtime options -->
                                <select class="form-control" id="high_level_category" name="request_category" style="display:<?php echo ($request['request_type'] == 'high_level') ? 'block' : 'none'; ?>;">
                                    <option value="">-- Select Category --</option>
                                    <option value="Access Restrictions" <?php echo ($request['request_category'] == 'Access Restrictions') ? 'selected' : ''; ?>>Access Restrictions (1 day)</option>
                                    <option value="E-mail" <?php echo ($request['request_category'] == 'E-mail') ? 'selected' : ''; ?>>E-mail (1 week)</option>
                                    <option value="Installation of Network/Telephone" <?php echo ($request['request_category'] == 'Installation of Network/Telephone') ? 'selected' : ''; ?>>Installation of Network/Telephone (1 week)</option>
                                    <option value="Installation of New PC" <?php echo ($request['request_category'] == 'Installation of New PC') ? 'selected' : ''; ?>>Installation of New PC (45 days)</option>
                                    <option value="Installation of CCTV" <?php echo ($request['request_category'] == 'Installation of CCTV') ? 'selected' : ''; ?>>Installation of CCTV (45 days)</option>
                                    <option value="Installation of Other Hardware" <?php echo ($request['request_category'] == 'Installation of Other Hardware') ? 'selected' : ''; ?>>Installation of Other Hardware (45 days)</option>
                                    <option value="Installation of Software" <?php echo ($request['request_category'] == 'Installation of Software') ? 'selected' : ''; ?>>Installation of Software (45 days)</option>
                                    <option value="CCTV Video Download" <?php echo ($request['request_category'] == 'CCTV Video Download') ? 'selected' : ''; ?>>CCTV Video Download (1 day)</option>
                                    <option value="Exact Master Data Maintenance" <?php echo ($request['request_category'] == 'Exact Master Data Maintenance') ? 'selected' : ''; ?>>Exact Master Data Maintenance (1 day)</option>
                                    <option value="Exact Entry Adjustments" <?php echo ($request['request_category'] == 'Exact Entry Adjustments') ? 'selected' : ''; ?>>Exact Entry Adjustments (1 day)</option>
                                    <option value="EagleEye Master Data Maintenance" <?php echo ($request['request_category'] == 'EagleEye Master Data Maintenance') ? 'selected' : ''; ?>>EagleEye Master Data Maintenance (1 day)</option>
                                    <option value="EagleEye Entry Adjustments" <?php echo ($request['request_category'] == 'EagleEye Entry Adjustments') ? 'selected' : ''; ?>>EagleEye Entry Adjustments (3 days)</option>
                                    <option value="Automation" <?php echo ($request['request_category'] == 'Automation') ? 'selected' : ''; ?>>Automation (1 week)</option>
                                    <option value="Restoration of Deleted File" <?php echo ($request['request_category'] == 'Restoration of Deleted File') ? 'selected' : ''; ?>>Restoration of Deleted File (1 week)</option>
                                    <option value="Troubleshooting" <?php echo ($request['request_category'] == 'Troubleshooting') ? 'selected' : ''; ?>>Troubleshooting (1 week)</option>
                                    <option value="Repair" <?php echo ($request['request_category'] == 'Repair') ? 'selected' : ''; ?>>Repair (2 weeks)</option>
                                    <option value="Events Assistance" <?php echo ($request['request_category'] == 'Events Assistance') ? 'selected' : ''; ?>>Events Assistance (1 week)</option>
                                    <option value="Relayout" <?php echo ($request['request_category'] == 'Relayout') ? 'selected' : ''; ?>>Relayout (2 weeks)</option>
                                    <option value="Work from Home" <?php echo ($request['request_category'] == 'Work from Home') ? 'selected' : ''; ?>>Work from Home (1 day)</option>
                                </select>
                                
                                <!-- Others options -->
                                <select class="form-control" id="others_category" name="request_category" style="display:<?php echo ($request['request_type'] == 'others') ? 'block' : 'none'; ?>;">
                                    <option value="">-- Select Category --</option>
                                    <option value="Mouse" <?php echo ($request['request_category'] == 'Mouse') ? 'selected' : ''; ?>>Mouse</option>
                                    <option value="Keyboard" <?php echo ($request['request_category'] == 'Keyboard') ? 'selected' : ''; ?>>Keyboard</option>
                                    <option value="Monitor" <?php echo ($request['request_category'] == 'Monitor') ? 'selected' : ''; ?>>Monitor</option>
                                    <option value="Headset" <?php echo ($request['request_category'] == 'Headset') ? 'selected' : ''; ?>>Headset</option>
                                    <option value="Cables" <?php echo ($request['request_category'] == 'Cables') ? 'selected' : ''; ?>>Cables</option>
                                    <option value="Other Peripherals" <?php echo ($request['request_category'] == 'Other Peripherals') ? 'selected' : ''; ?>>Other Peripherals</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="hidden" name="department_id" value="<?php echo $departments[0]['department_id']; ?>">
                                <input type="text" class="form-control" value="<?php echo $departments[0]['department_name']; ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="requestor_name" class="form-label">Enter your name</label>
                                <input type="text" class="form-control" id="requestor_name" name="requestor_name" value="<?php echo $request['requestor_name']; ?>" required>
                            </div>
                            
                            <div class="mb-3 purpose-container">
                                <label for="purpose" class="form-label">Purpose</label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="5" required><?php echo $request['purpose']; ?></textarea>
                                <div class="paste-instruction">Press Ctrl+V to paste screenshots directly</div>
                            </div>
                            
                            <div id="image-preview-container"></div>
                            <input type="hidden" name="pasted_images" id="pasted-images-data">
                            
                            <?php if (count($attachments) > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">Existing Attachments</label>
                                <?php foreach($attachments as $attachment): ?>
                                    <div class="existing-attachment">
                                        <i class="bi <?php echo strpos($attachment['file_type'], 'image') !== false ? 'bi-file-image' : 'bi-file-earmark'; ?>"></i>
                                        <div class="file-info">
                                            <div class="file-name"><?php echo $attachment['file_name']; ?></div>
                                            <div class="file-size"><?php echo round($attachment['file_size'] / 1024, 2); ?> KB</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="attachments" class="form-label">Add More Attachments</label>
                                <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                                <div class="form-text">Upload any relevant files (images, documents, etc.)</div>
                            </div>
                            
                            <div id="file-preview-container" class="mb-3"></div>
                            
                            <div class="mb-3">
                                <label for="item_type" class="form-label">Item Type</label>
                                <input type="text" class="form-control" id="item_type" name="item_type" value="<?php echo $request['item_type']; ?>" required>
                                <div class="form-text">E.g., Laptop, PC, Mouse, Keyboard, etc.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="item_description" class="form-label">Item Description</label>
                                <textarea class="form-control" id="item_description" name="item_description" rows="2" required><?php echo $request['item_description']; ?></textarea>
                                <div class="form-text">Provide detailed description of the item or issue</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="<?php echo $request['quantity']; ?>" required>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="view_request.php?id=<?php echo $request_id; ?>" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Request</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const pastedImagesArray = [];
        const form = document.getElementById('requestForm');
        const purposeTextarea = document.getElementById('purpose');
        const pastedImagesData = document.getElementById('pasted-images-data');
        const imagePreviewContainer = document.getElementById('image-preview-container');
        const fileInput = document.getElementById('attachments');
        const filePreviewContainer = document.getElementById('file-preview-container');

        // Request type selection handling
        const supportRadio = document.getElementById('support');
        const highLevelRadio = document.getElementById('high_level');
        const othersRadio = document.getElementById('others');
        
        const supportCategory = document.getElementById('support_category');
        const highLevelCategory = document.getElementById('high_level_category');
        const othersCategory = document.getElementById('others_category');

        function updateCategoryDropdowns() {
            // Hide all first
            supportCategory.style.display = 'none';
            highLevelCategory.style.display = 'none';
            othersCategory.style.display = 'none';
        
            // Show the one that matches the selected radio
            if (supportRadio.checked) {
                supportCategory.style.display = 'block';
                supportCategory.disabled = false;
                highLevelCategory.disabled = true;
                othersCategory.disabled = true;
            } else if (highLevelRadio.checked) {
                highLevelCategory.style.display = 'block';
                highLevelCategory.disabled = false;
                supportCategory.disabled = true;
                othersCategory.disabled = true;
            } else if (othersRadio.checked) {
                othersCategory.style.display = 'block';
                othersCategory.disabled = false;
                supportCategory.disabled = true;
                highLevelCategory.disabled = true;
            }
        }
        
        // Initial setup
        updateCategoryDropdowns();
        
        // Add event listeners to the radio buttons
        supportRadio.addEventListener('change', updateCategoryDropdowns);
        highLevelRadio.addEventListener('change', updateCategoryDropdowns);
        othersRadio.addEventListener('change', updateCategoryDropdowns);
                
        // Handle pasted images
        document.addEventListener('paste', function(e) {
            // Check if the paste event target is the purpose textarea or its parent
            if (e.target === purposeTextarea || purposeTextarea.contains(e.target)) {
                const items = e.clipboardData.items;
                
                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        const blob = items[i].getAsFile();
                        const reader = new FileReader();
                        
                        reader.onload = function(event) {
                            const imageData = event.target.result;
                            pastedImagesArray.push(imageData);
                            
                            // Update hidden input with JSON string of images
                            pastedImagesData.value = JSON.stringify(pastedImagesArray);
                            
                            // Create image preview
                            const imageIndex = pastedImagesArray.length - 1;
                            const previewDiv = document.createElement('div');
                            previewDiv.className = 'image-preview';
                            previewDiv.dataset.index = imageIndex;
                            
                            const img = document.createElement('img');
                            img.src = imageData;
                            
                            const removeBtn = document.createElement('button');
                            removeBtn.className = 'remove-image';
                                removeBtn.innerHTML = '×';
                                removeBtn.onclick = function() {
                                    removeImage(imageIndex);
                                };
                                
                                previewDiv.appendChild(img);
                                previewDiv.appendChild(removeBtn);
                                imagePreviewContainer.appendChild(previewDiv);
                            };
                            
                            reader.readAsDataURL(blob);
                        }
                    }
                }
            });
            
            // Function to remove pasted image
            function removeImage(index) {
                // Remove from array
                pastedImagesArray[index] = null;
                
                // Update hidden input
                pastedImagesData.value = JSON.stringify(pastedImagesArray.filter(img => img !== null));
                
                // Remove preview
                const imagePreview = document.querySelector(`.image-preview[data-index="${index}"]`);
                if (imagePreview) {
                    imagePreview.remove();
                }
            }
            
            // File input change event for preview
            fileInput.addEventListener('change', function() {
                filePreviewContainer.innerHTML = '';
                
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    const fileSize = (file.size / 1024).toFixed(2) + ' KB';
                    const isImage = file.type.match('image.*');
                    
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'file-preview';
                    
                    // Icon based on file type
                    const icon = document.createElement('i');
                    icon.className = isImage ? 'bi bi-file-image' : 'bi bi-file-earmark';
                    
                    const fileInfo = document.createElement('div');
                    fileInfo.className = 'file-info';
                    
                    const fileName = document.createElement('div');
                    fileName.className = 'file-name';
                    fileName.textContent = file.name;
                    
                    const fileSizeSpan = document.createElement('div');
                    fileSizeSpan.className = 'file-size';
                    fileSizeSpan.textContent = fileSize;
                    
                    fileInfo.appendChild(fileName);
                    fileInfo.appendChild(fileSizeSpan);
                    
                    previewDiv.appendChild(icon);
                    previewDiv.appendChild(fileInfo);
                    
                    filePreviewContainer.appendChild(previewDiv);
                }
            });
            
            // Form submit handler
            form.addEventListener('submit', function() {
                // Final update of pasted images data
                pastedImagesData.value = JSON.stringify(pastedImagesArray.filter(img => img !== null));
            });
        });
    </script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</body>
</html>