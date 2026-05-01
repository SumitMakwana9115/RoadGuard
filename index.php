<?php
/**
 * Root Index - Redirects to Login or Dashboard
 */
require_once __DIR__ . '/config/config.php';

if (isset($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    $role = $_SESSION['role'];
    header("Location: " . BASE_URL . "/dashboard/{$role}.php");
    exit();
}

// Clear bad session or redirect guest
header("Location: " . BASE_URL . "/auth/logout.php");
exit();
