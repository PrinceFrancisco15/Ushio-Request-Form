<?php
// index.php - Login page
session_start();
include 'config/database.php';

// Check if user is already logged in
if(isset($_SESSION['user_id'])) {
    // Redirect based on role
    switch($_SESSION['role']) {
        case 'employee':
            header("Location: employee/dashboard.php");
            break;
        case 'manager':
            header("Location: manager/dashboard.php");
            break;
        case 'general_manager':
            header("Location: gm/dashboard.php");
            break;
        case 'it_staff':
            header("Location: it/dashboard.php");
            break;
    }
    exit;
}

// Process login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];
    
    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password (in a real app, use password_verify with hashed passwords)
        if (sha1($password) == $user["password"]) {
        // Set session variables
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["role"] = $user["role"];
            $_SESSION["department_id"] = $user["department_id"];
            
            // Redirect based on role
            switch($user["role"]) {
                case 'employee':
                    header("Location: employee/dashboard.php");
                    break;
                case 'manager':
                    header("Location: manager/dashboard.php");
                    break;
                case 'general_manager':
                    header("Location: gm/dashboard.php");
                    break;
                case 'it_staff':
                    header("Location: it/dashboard.php");
                    break;
                    case 'general_it': // ✅ ADD THIS
                        header("Location: it/general_it_dashboard.php");
                        break;
            }
            exit;
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found";
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USHIO Request Form System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --ushio-green: #009977; /* USHIO brand green color */
        }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            background: url('assets/images/ushio-building.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.65); /* This creates a semi-transparent white overlay */
            z-index: -1;
        }
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 500px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .card-header {
            background-color: var(--ushio-green) !important;
            color: white;
            text-align: center;
            padding: 1rem;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .card-body {
            padding: 2rem;
        }
        .ushio-logo {
            text-align: center;
            margin-bottom: 2rem;
            font-size: 4rem;
            color: var(--ushio-green);
            font-weight: bold;
            letter-spacing: 2px;
        }
        .tagline {
            text-align: center;
            color: #666;
            font-style: italic;
            margin-bottom: 2rem;
        }
        .form-control {
            padding: 0.75rem;
            border-radius: 5px;
        }
        .btn-login {
            background-color: var(--ushio-green);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 0.75rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
        }
        .btn-login:hover {
            background-color: #00815e;
        }
        .alert {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="card-header">
                Request Form System
            </div>
            <div class="card-body">
                <div class="ushio-logo">USHIO</div>
                <div class="tagline">Applying Light to Life</div>
                
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <span class="toggle-password" onclick="togglePassword()" 
                              style="position:absolute; top: 38px; right: 10px; cursor: pointer;">
                            👁️
                        </span>
                    </div>
                    <button type="submit" class="btn btn-login">Login</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById("password");
            const icon = document.querySelector(".toggle-password");
            if (passwordField.type === "password") {
                passwordField.type = "text";
                icon.textContent = "🙈";
            } else {
                passwordField.type = "password";
                icon.textContent = "👁️";
            }
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>