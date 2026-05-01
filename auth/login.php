<?php
require_once __DIR__ . '/../config/config.php';

// Redirect if already logged in with a valid role
if (isset($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    $role = $_SESSION['role'];
    header("Location: " . BASE_URL . "/dashboard/{$role}.php");
    exit();
} elseif (isset($_SESSION['user_id'])) {
    // Bad session state, clear it
    session_destroy();
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Setup Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            $_SESSION['flash_message'] = "Welcome back, " . $user['full_name'];
            $_SESSION['flash_type'] = "success";
            
            header("Location: " . BASE_URL . "/dashboard/{$user['role']}.php");
            exit();
        } else {
            $error = "Invalid username or password, or account is inactive.";
        }
    }
}

$public_page = true;
$page_title = "Login";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-card">
    <div class="auth-header">
        <i class='bx bx-map-pin logo-icon' style="font-size: 48px; margin-bottom: 10px;"></i>
        <h2>Welcome to <?= APP_NAME ?></h2>
        <p><?= APP_TAGLINE ?></p>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(247, 37, 133, 0.1); color: var(--danger-color); padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center;">
            <i class='bx bx-error-circle'></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off">
        <!-- Hidden fake fields to trick Chrome autofill -->
        <input style="display:none" type="text" name="fakeusernameremembered"/>
        <input style="display:none" type="password" name="fakepasswordremembered"/>
        
        <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="off" autocorrect="off" autocapitalize="none">
        </div>
        
        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password">
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Login</button>
    </form>
    
    <div style="margin-top: 20px; background: var(--hover-bg); padding: 15px; border-radius: 8px; font-size: 13px;">
        <p style="margin-bottom: 12px; font-weight: 600; text-align: center; color: var(--text-color);">All Demo Accounts (Click a username to instantly login)</p>
        <table style="width: 100%; border-collapse: collapse; text-align: left; color: var(--text-color);">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <th style="padding: 5px 0;">Role</th>
                    <th style="padding: 5px 0;">Usernames</th>
                    <th style="padding: 5px 0;">Password</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-bottom: 1px dashed var(--border-color);">
                    <td style="padding: 6px 0;">Supervisor</td>
                    <td style="padding: 6px 0; color: var(--primary-color);">
                        <span style="cursor: pointer; text-decoration: underline;" onclick="fillLogin('admin', 'admin123')" title="Instantly login as admin">admin</span>
                    </td>
                    <td style="padding: 6px 0;">admin123</td>
                </tr>
                <tr style="border-bottom: 1px dashed var(--border-color);">
                    <td style="padding: 6px 0;">Staff</td>
                    <td style="padding: 6px 0; color: var(--primary-color);">
                        <span style="cursor: pointer; text-decoration: underline;" onclick="fillLogin('staff1', 'staff123')" title="Instantly login as staff1">staff1</span>, 
                        <span style="cursor: pointer; text-decoration: underline;" onclick="fillLogin('staff2', 'staff123')" title="Instantly login as staff2">staff2</span>, 
                        <span style="cursor: pointer; text-decoration: underline;" onclick="fillLogin('staff3', 'staff123')" title="Instantly login as staff3">staff3</span>
                    </td>
                    <td style="padding: 6px 0;">staff123</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0;">Complainant</td>
                    <td style="padding: 6px 0; color: var(--primary-color);">
                        <span style="cursor: pointer; text-decoration: underline;" onclick="fillLogin('user1', 'user123')" title="Instantly login as user1">user1</span>, 
                        <span style="cursor: pointer; text-decoration: underline;" onclick="fillLogin('user2', 'user123')" title="Instantly login as user2">user2</span>
                    </td>
                    <td style="padding: 6px 0;">user123</td>
                </tr>
            </tbody>
        </table>
    </div>
    <script>
        function fillLogin(user, pass) {
            document.querySelector('input[name="username"]').value = user;
            document.querySelector('input[name="password"]').value = pass;
        }
    </script>
    
    <div style="text-align: center; margin-top: 25px; font-size: 14px; color: var(--text-muted);">
        <p>System Personalized for U = 35</p>
        <p>Ward → Area → Spot | Road & Pathway Damage</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
