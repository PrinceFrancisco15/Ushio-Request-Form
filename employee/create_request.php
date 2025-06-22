<?php
// employee/create_request.php - Create new request form
session_start();
include '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is an employee
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: ../index.php");
    exit;
}

// Get departments list for dropdown
$user_department_id = $_SESSION['department_id']; // Make sure this is set during login
$sql = "SELECT * FROM departments WHERE department_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_department_id);
$stmt->execute();
$result = $stmt->get_result();
$departments = $result->fetch_all(MYSQLI_ASSOC);

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

    $target_department_id = null;
    $target_dept_manager_id = null;

    
    if ($request_category == 'Access Derestriction' && isset($_POST['target_department'])) {
        $target_department_id = mysqli_real_escape_string($conn, $_POST['target_department']);
        
        // Get the target department manager ID
        $target_manager_sql = "SELECT user_id FROM users WHERE department_id = ? AND role = 'manager'";
        $stmt = $conn->prepare($target_manager_sql);
        $stmt->bind_param("i", $target_department_id);
        $stmt->execute();
        $target_result = $stmt->get_result();
        
        if ($target_row = $target_result->fetch_assoc()) {
            $target_dept_manager_id = $target_row['user_id'];
        }
    }

    
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

         // For Access Derestriction, we need target department manager approval after initial approval
         $target_dept_approval_needed = false;
         $target_manager_status = 'pending';
         if ($request_category == 'Access Derestriction' && !empty($target_department_id)) {
             $target_dept_approval_needed = true;
         }

        // Insert request
        $sql = "INSERT INTO request_forms (requestor_id, requestor_name, request_type, request_category, purpose, department_id, manager_id, gm_id, gm_status, manager_status, target_department_id, target_dept_manager_id, target_manager_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssiisssiss", $_SESSION['user_id'], $requestor_name, $request_type, $request_category, $purpose, $department_id, $manager_id, $gm_id, $gm_status, $manager_status, $target_department_id, $target_dept_manager_id, $target_manager_status);
        $stmt->execute();
        $request_id = $conn->insert_id;
        
        // Insert request items
        $sql = "INSERT INTO request_items (request_id, item_type, item_description, quantity) 
        VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issi", $request_id, $item_type, $item_description, $quantity);
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
        
        // Add notification for support requests
        if ($request_type == 'support') {
            // Create notification for manager
            $notification_message = "New support request (#$request_id) from " . $_SESSION['full_name'] . " needs your attention.";
            $sql = "INSERT INTO notifications (user_id, request_id, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $manager_id, $request_id, $notification_message);
            $stmt->execute();
        } elseif ($request_type == 'others') {
            // Create notification directly for General IT
            $notification_message = "New peripherals request (#$request_id) from " . $_SESSION['full_name'] . " needs your attention.";
            $sql = "INSERT INTO notifications (user_id, request_id, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $general_it_id, $request_id, $notification_message);
            $stmt->execute();
        }elseif ($request_category == 'Access Derestriction' && !empty($target_dept_manager_id)) {
            // Create notification for the initial department manager
            $notification_message = "New Access Derestriction request (#$request_id) from " . $_SESSION['full_name'] . " needs your approval first.";
            $sql = "INSERT INTO notifications (user_id, request_id, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $manager_id, $request_id, $notification_message);
            $stmt->execute();
        }
        
        // Log activity
        $action = "Request created";
        $details = "Created a new " . ucfirst($request_type) . " request for " . $request_category;
        $sql = "INSERT INTO activity_logs (request_id, user_id, action, details) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiss", $request_id, $_SESSION['user_id'], $action, $details);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to dashboard with success message
        $_SESSION['success'] = "Request created successfully!";
        header("Location: dashboard.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback if error
        $conn->rollback();
        $error = "Error creating request: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Request - Request Form System</title>
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
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Create New Request</h5>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="" enctype="multipart/form-data" id="requestForm">
                        <div class="mb-3">
    <label class="form-label">Request Type</label>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="request_type" id="support" value="support" checked>
        <label class="form-check-label" for="support">
            Support Request (Downtime - 1 hour resolution)
        </label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="request_type" id="high_level" value="high_level">
        <label class="form-check-label" for="high_level">
            High Level Request (Hardware/Software Installation, etc.)
        </label>
    </div>
         
    <div class="form-check">
        <input class="form-check-input" type="radio" name="request_type" id="others" value="others">
        <label class="form-check-label" for="others">
            Others (Basic peripherals - mouse, keyboard, etc.)
        </label>
    </div>
</div>
<div class="mb-3">
    <label for="request_category" class="form-label">Request Category</label>
    
    <!-- Support/Downtime options (shown when support is selected) -->
    <select class="form-control" id="support_category" name="request_category" style="display:none;">
        <option value="">-- Select Category --</option>
        <option value="Local network">Local network (1 hour)</option>
        <option value="Internet">Internet (1 hour)</option>
        <option value="Hardware">Hardware (1 hour)</option>
        <option value="Software">Software (1 hour)</option>
        <option value="Data">Data (1 hour)</option>
        <option value="Printer">Printer (1 hour)</option>
        <option value="Telephone">Telephone (1 hour)</option>
        <option value="Website">Website (1 hour)</option>
        <option value="E-mail">E-mail (1 hour)</option>
    </select>
    
    <!-- High-level/Leadtime options (shown when high_level is selected) -->
    <select class="form-control" id="high_level_category" name="request_category" style="display:none;">
        <option value="">-- Select Category --</option>
        <option value="Access Restrictions">Access Restrictions (1 day)</option>
        <option value="Access Derestriction">Access Derestriction (1 day)</option>
        <option value="E-mail">E-mail (1 week)</option>
        <option value="Installation of Network/Telephone">Installation of Network/Telephone (1 week)</option>
        <option value="Installation of New PC">Installation of New PC (45 days)</option>
        <option value="Installation of CCTV">Installation of CCTV (45 days)</option>
        <option value="Installation of Other Hardware">Installation of Other Hardware (45 days)</option>
        <option value="Installation of Software">Installation of Software (45 days)</option>
        <option value="CCTV Video Download">CCTV Video Download (1 day)</option>
        <option value="Exact Master Data Maintenance">Exact Master Data Maintenance (1 day)</option>
        <option value="Exact Entry Adjustments">Exact Entry Adjustments (1 day)</option>
        <option value="EagleEye Master Data Maintenance">EagleEye Master Data Maintenance (1 day)</option>
        <option value="EagleEye Entry Adjustments">EagleEye Entry Adjustments (3 days)</option>
        <option value="Automation">Automation (1 week)</option>
        <option value="Restoration of Deleted File">Restoration of Deleted File (1 week)</option>
        <option value="Troubleshooting">Troubleshooting (1 week)</option>
        <option value="Repair">Repair (2 weeks)</option>
        <option value="Events Assistance">Events Assistance (1 week)</option>
        <option value="Relayout">Relayout (2 weeks)</option>
        <option value="Work from Home">Work from Home (1 day)</option>
    </select>
    
    <!-- Others options (shown when others is selected) -->
    <select class="form-control" id="others_category" name="request_category" style="display:none;">
        <option value="">-- Select Category --</option>
        <option value="Mouse">Mouse</option>
        <option value="Keyboard">Keyboard</option>
        <option value="Monitor">Monitor</option>
        <option value="Headset">Headset</option>
        <option value="Cables">Cables</option>
        <option value="Other Peripherals">Other Peripherals</option>
    </select>
</div>
                      
    <div class="mb-3">
    <label for="department" class="form-label">Department</label>
    <input type="hidden" name="department_id" value="<?php echo $departments[0]['department_id']; ?>">
    <input type="text" class="form-control" value="<?php echo $departments[0]['department_name']; ?>" readonly>
</div>

<div class="mb-3">
    <label for="requestor_name" class="form-label">Enter your name</label>
    <input type="text" class="form-control" id="requestor_name" name="requestor_name" value="<?php echo $_SESSION['requestor_name'] ?? ''; ?>" required>
</div>

<div id="target_department_div" style="display:none;" class="mb-3">
    <label for="target_department" class="form-label">Target Department</label>
    <select class="form-control" id="target_department" name="target_department" required>
        <option value="">-- Select Target Department --</option>
        <?php
        // Get all departments from the database
        $dept_sql = "SELECT * FROM departments";
        $dept_result = mysqli_query($conn, $dept_sql);
        while ($dept_row = mysqli_fetch_assoc($dept_result)) {
            echo "<option value='" . $dept_row['department_id'] . "'>" . $dept_row['department_name'] . "</option>";
        }
        ?>
    </select>
</div>
           
                                <div class="mb-3 purpose-container">
                                <label for="purpose" class="form-label">Purpose</label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="3" required></textarea>
                                <div class="paste-instruction">Press Ctrl+V to paste screenshots directly</div>
                            </div>
                            <div id="image-preview-container"></div>
                            <input type="hidden" name="pasted_images" id="pasted-images-data">
                            
                            <div class="mb-3">
                                <label for="attachments" class="form-label">Attachments</label>
                                <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                                <div class="form-text">Upload any relevant files (images, documents, etc.)</div>
                            </div>
                            
                            <div id="file-preview-container" class="mb-3"></div>
                            
                            <div class="mb-3">
                                <label for="item_type" class="form-label">Item Type</label>
                                <input type="text" class="form-control" id="item_type" name="item_type" required>
                                <div class="form-text">E.g., Laptop, PC, Mouse, Keyboard, etc.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="item_description" class="form-label">Item Description</label>
                                <textarea class="form-control" id="item_description" name="item_description" rows="2" required></textarea>
                                <div class="form-text">Provide detailed description of the item or issue</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-success">Submit Request</button>
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
    const targetDepartmentDiv = document.getElementById('target_department_div');

    function updateCategoryDropdowns() {
        // Hide all first
        supportCategory.style.display = 'none';
        highLevelCategory.style.display = 'none';
        othersCategory.style.display = 'none';
        targetDepartmentDiv.style.display = 'none';
    
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
            
            // Check if Access Derestriction is selected
            checkAccessDerestriction();
        } else if (othersRadio.checked) {
            othersCategory.style.display = 'block';
            othersCategory.disabled = false;
            supportCategory.disabled = true;
            highLevelCategory.disabled = true;
        }
    }

    function checkAccessDerestriction() {
        // Show target department dropdown only if Access Derestriction is selected
        if (highLevelRadio.checked && highLevelCategory.value === 'Access Derestriction') {
            targetDepartmentDiv.style.display = 'block';
            document.getElementById('target_department').setAttribute('required', 'required');
        } else {
            targetDepartmentDiv.style.display = 'none';
            document.getElementById('target_department').removeAttribute('required');
        }
    }
    // Initial setup
    updateCategoryDropdowns();
    
    // Add event listeners to the radio buttons
    supportRadio.addEventListener('change', updateCategoryDropdowns);
    highLevelRadio.addEventListener('change', updateCategoryDropdowns);
    othersRadio.addEventListener('change', updateCategoryDropdowns);
    highLevelCategory.addEventListener('change', checkAccessDerestriction);

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