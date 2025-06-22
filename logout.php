<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Clear the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: /request-form-system/index.php");
exit;
?>