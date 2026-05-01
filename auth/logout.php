<?php
require_once __DIR__ . '/../config/config.php';

// Unset all session variables
$_SESSION = array();

// Call session_destroy()
if (session_id() !== '' || isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 2592000, '/');
}
session_destroy();

// Start a new session for the flash message
session_start();
$_SESSION['flash_message'] = "You have been fully logged out.";
$_SESSION['flash_type'] = "info";

header("Location: " . BASE_URL . "/auth/login.php");
exit();
