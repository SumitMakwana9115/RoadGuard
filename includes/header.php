<?php
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in for protected pages
$is_protected = !isset($public_page) || !$public_page;
if ($is_protected && !isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$theme_mode = isset($_COOKIE['theme_mode']) ? $_COOKIE['theme_mode'] : 'dark';
?>
<!DOCTYPE html>
<html lang="en" class="<?= $theme_mode ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?><?= APP_NAME ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Boxicons for modern icons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <!-- Toastr for notifications (via CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <i class='bx bx-map-pin logo-icon'></i>
            <span class="logo-text"><?= APP_NAME ?></span>
            <i class='bx bx-menu toggle-btn'></i>
        </div>

        <ul class="nav-links">
            <?php if ($_SESSION['role'] == 'supervisor'): ?>
                <li>
                    <a href="<?= BASE_URL ?>/dashboard/supervisor.php" <?= basename($_SERVER['PHP_SELF']) == 'supervisor.php' ? 'class="active"' : '' ?>>
                        <i class='bx bx-grid-alt'></i>
                        <span class="link-name">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/complaints/manage.php" <?= basename($_SERVER['PHP_SELF']) == 'manage.php' ? 'class="active"' : '' ?>>
                        <i class='bx bx-list-ul'></i>
                        <span class="link-name">Manage Complaints</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/admin/categories.php" <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'class="active"' : '' ?>>
                        <i class='bx bx-purchase-tag-alt'></i>
                        <span class="link-name">Manage Categories</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/admin/areas.php" <?= basename($_SERVER['PHP_SELF']) == 'areas.php' ? 'class="active"' : '' ?>>
                        <i class='bx bx-map-alt'></i>
                        <span class="link-name">Manage Areas</span>
                    </a>
                </li>
            <?php elseif ($_SESSION['role'] == 'staff'): ?>
                <li>
                    <a href="<?= BASE_URL ?>/dashboard/staff.php" <?= basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'class="active"' : '' ?>>
                        <i class='bx bx-grid-alt'></i>
                        <span class="link-name">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/complaints/assigned.php" <?= basename($_SERVER['PHP_SELF']) == 'assigned.php' ? 'class="active"' : '' ?>>
                        <i class='bx bx-task'></i>
                        <span class="link-name">My Tasks</span>
                    </a>
                </li>
            <?php else: // complainant ?>
                <li>
                    <a href="<?= BASE_URL ?>/dashboard/complainant.php" <?= basename($_SERVER['PHP_SELF']) == 'complainant.php' ? 'class="active"' : '' ?>>
                        <i class='bx bx-grid-alt'></i>
                        <span class="link-name">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/complaints/register.php" <?= basename($_SERVER['PHP_SELF']) == 'register.php' ? 'class="active"' : '' ?>>
                        <i class='bx bx-plus-circle'></i>
                        <span class="link-name">New Complaint</span>
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/complaints/my_complaints.php" <?= basename($_SERVER['PHP_SELF']) == 'my_complaints.php' ? 'class="active"' : '' ?>>
                        <i class='bx bx-history'></i>
                        <span class="link-name">My History</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Topbar -->
    <section class="main-content">
        <div class="topbar">
            <div class="page-title"><?= isset($page_title) ? $page_title : 'Dashboard' ?></div>
            <div class="user-info" style="display: flex; align-items: center; gap: 15px;">
                <a href="#" id="toggleTheme" style="color: var(--text-color); font-size: 22px; display: flex; align-items: center; text-decoration: none; margin-right: 15px;" title="Toggle Theme">
                    <i class='bx bx-moon moon'></i>
                    <i class='bx bx-sun sun'></i>
                </a>
                
                <span class="user-role"><?= ucfirst($_SESSION['role']) ?></span>
                <span class="user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
                
                <a href="<?= BASE_URL ?>/auth/logout.php" style="color: var(--danger-color); font-size: 24px; display: flex; align-items: center; text-decoration: none; margin-left: 15px;" title="Logout">
                    <i class='bx bx-log-out'></i>
                </a>
            </div>
        </div>
        
        <div class="content-wrapper">
<?php else: ?>
    <!-- For public pages like login -->
    <div class="public-wrapper">
        <div class="theme-toggle-public" id="toggleThemePublic" style="position: absolute; top: 20px; right: 20px; cursor: pointer; color: var(--text-color); font-size: 24px;">
            <i class='bx <?= $theme_mode == 'dark' ? 'bx-sun' : 'bx-moon' ?>'></i>
        </div>
<?php endif; ?>
