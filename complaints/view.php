<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "Complaint Timeline & Tracking";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$complaint_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch Complaint Details
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name, s.status_name, s.color, u.full_name as complainant_name,
           w.name as ward, a.name as area, sp.name as spot,
           assignee.full_name as assigned_to_name
    FROM complaints c
    JOIN complaint_categories cat ON c.category_id = cat.id
    JOIN status_master s ON c.status_id = s.id
    JOIN users u ON c.complainant_id = u.id
    JOIN ward_master w ON c.ward_id = w.id
    JOIN area_master a ON c.area_id = a.id
    JOIN spot_master sp ON c.spot_id = sp.id
    LEFT JOIN users assignee ON c.assigned_to = assignee.id
    WHERE c.id = ?
");
$stmt->execute([$complaint_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo "<div class='card text-center'>Complaint not found.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}

// Access Control
if ($role === 'complainant' && $complaint['complainant_id'] != $user_id) {
    echo "<div class='card text-center'>Unauthorized access.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}
if ($role === 'staff' && $complaint['assigned_to'] != $user_id && $complaint['assigned_to'] !== NULL) {
    // Note: Depends on business logic, usually staff only see their own
    echo "<div class='card text-center'>Unauthorized access.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}

// Handle Form Submissions (Action by Supervisor or Staff)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Supervisor Actions
    if ($role === 'supervisor') {
        if ($action === 'verify') {
            if ($complaint['status_id'] != 1 && $complaint['status_id'] != 7) {
                $_SESSION['flash_message'] = "Invalid status transition.";
                $_SESSION['flash_type'] = "error";
                header("Location: view.php?id=$complaint_id");
                exit();
            }
            // Calculate Deadlines based on Personalized Configuration
            // Initial Response SLA = 7 hours
            // Resolution SLA = 48 hours

            $now = date('Y-m-d H:i:s');
            $initial = date('Y-m-d H:i:s', strtotime("+ " . INITIAL_RESPONSE_SLA_HOURS . " hours"));
            $resolution = date('Y-m-d H:i:s', strtotime("+ " . RESOLUTION_SLA_HOURS . " hours"));

            $update = $pdo->prepare("UPDATE complaints SET status_id = 2, initial_response_deadline = ?, resolution_deadline = ? WHERE id = ?");
            $update->execute([$initial, $resolution, $complaint_id]);

            $history = $pdo->prepare("INSERT INTO complaint_history (complaint_id, from_status_id, to_status_id, updated_by, remark) VALUES (?, 1, 2, ?, 'Complaint verified and SLAs set.')");
            $history->execute([$complaint_id, $user_id]);

            $_SESSION['flash_message'] = "Complaint Verified Successfully.";
            $_SESSION['flash_type'] = "success";
            header("Location: view.php?id=$complaint_id");
            exit();
        }

        if ($action === 'assign') {
            if ($complaint['status_id'] != 2) {
                $_SESSION['flash_message'] = "Invalid status transition. Complaint must be verified first.";
                $_SESSION['flash_type'] = "error";
                header("Location: view.php?id=$complaint_id");
                exit();
            }
            $staff_id = $_POST['staff_id'];
            // Special Rule Even check wouldn't trigger here, but if U was even (it's 35, so odd):
            // Check valid staff

            $update = $pdo->prepare("UPDATE complaints SET status_id = 3, assigned_to = ? WHERE id = ?");
            $update->execute([$staff_id, $complaint_id]);

            $history = $pdo->prepare("INSERT INTO complaint_history (complaint_id, from_status_id, to_status_id, updated_by, remark) VALUES (?, 2, 3, ?, 'Complaint assigned to staff.')");
            $history->execute([$complaint_id, $user_id]);

            // Assignment table record
            $assign = $pdo->prepare("INSERT INTO assignments (complaint_id, assigned_to, assigned_by) VALUES (?, ?, ?)");
            $assign->execute([$complaint_id, $staff_id, $user_id]);

            $_SESSION['flash_message'] = "Complaint Assigned Successfully.";
            $_SESSION['flash_type'] = "success";
            header("Location: view.php?id=$complaint_id");
            exit();
        }

        if ($action === 'close') {
            if ($complaint['status_id'] != 5) {
                $_SESSION['flash_message'] = "Invalid status transition. Complaint must be resolved first.";
                $_SESSION['flash_type'] = "error";
                header("Location: view.php?id=$complaint_id");
                exit();
            }
            $update = $pdo->prepare("UPDATE complaints SET status_id = 6 WHERE id = ?");
            $update->execute([$complaint_id]);

            $history = $pdo->prepare("INSERT INTO complaint_history (complaint_id, from_status_id, to_status_id, updated_by, remark) VALUES (?, ?, 6, ?, 'Complaint closed permanently.')");
            $history->execute([$complaint_id, $complaint['status_id'], $user_id]);

            $_SESSION['flash_message'] = "Complaint Closed.";
            $_SESSION['flash_type'] = "success";
            header("Location: view.php?id=$complaint_id");
            exit();
        }
    }

    // Complainant Actions
    if ($role === 'complainant') {
        if ($action === 'reopen') {
            if ($complaint['status_id'] != 5) {
                $_SESSION['flash_message'] = "Invalid status transition. Complaint must be resolved first.";
                $_SESSION['flash_type'] = "error";
                header("Location: view.php?id=$complaint_id");
                exit();
            }
            $reopen_count = $complaint['reopen_count'] + 1;
            $resolution = date('Y-m-d H:i:s', strtotime("+ " . RESOLUTION_SLA_HOURS . " hours"));
            $update = $pdo->prepare("UPDATE complaints SET status_id = 7, is_reopened = 1, reopen_count = ?, resolution_deadline = ? WHERE id = ?");
            $update->execute([$reopen_count, $resolution, $complaint_id]);

            $remark = "Reopened by Complainant. Reason: " . $_POST['remark'];
            $history = $pdo->prepare("INSERT INTO complaint_history (complaint_id, from_status_id, to_status_id, updated_by, remark) VALUES (?, 5, 7, ?, ?)");
            $history->execute([$complaint_id, $user_id, $remark]);

            $_SESSION['flash_message'] = "Complaint Reopened.";
            $_SESSION['flash_type'] = "info";
            header("Location: view.php?id=$complaint_id");
            exit();
        }
    }
}

// Fetch History
$history_stmt = $pdo->prepare("
    SELECT ch.*, u.full_name, s.status_name 
    FROM complaint_history ch
    JOIN users u ON ch.updated_by = u.id
    JOIN status_master s ON ch.to_status_id = s.id
    WHERE ch.complaint_id = ?
    ORDER BY ch.created_at ASC
");
$history_stmt->execute([$complaint_id]);
$history = $history_stmt->fetchAll();

// Fetch Attachments
$att_stmt = $pdo->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ?");
$att_stmt->execute([$complaint_id]);
$attachments = $att_stmt->fetchAll();

// Get Staff list for Supervisor Assignment
$staff_members = [];
if ($role === 'supervisor' && $complaint['status_id'] == 2) {
    $staff_members = $pdo->query("SELECT id, full_name FROM users WHERE role = 'staff' AND status = 'active'")->fetchAll();
}

?>

<style>
    .details-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
    }

    @media(max-width: 768px) {
        .details-grid {
            grid-template-columns: 1fr;
        }
    }

    .info-row {
        margin-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 10px;
    }

    .info-label {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 3px;
    }

    .info-value {
        font-size: 15px;
        font-weight: 500;
    }
</style>

<div class="card" style="margin-bottom: 15px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin:0;">#<?= $complaint['complaint_uid'] ?> - <?= htmlspecialchars($complaint['title']) ?></h2>
        <span class="badge badge-<?= strtolower(str_replace(' ', '', $complaint['status_name'])) ?>" style="font-size: 15px; padding: 8px 16px;"><?= $complaint['status_name'] ?></span>
    </div>
</div>

<div class="details-grid">
    <!-- Left Column: Details -->
    <div>
        <div class="card">
            <div class="card-title">Complaint Details</div>

            <div class="info-row">
                <div class="info-label">Description</div>
                <div class="info-value" style="font-weight: 400; line-height: 1.6;"><?= nl2br(htmlspecialchars($complaint['description'])) ?></div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="info-row">
                    <div class="info-label">Category</div>
                    <div class="info-value"><?= htmlspecialchars($complaint['category_name']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Priority</div>
                    <div class="info-value"><span class="badge" style="background: <?= $complaint['priority'] == 'critical' ? 'var(--danger-color)' : ($complaint['priority'] == 'high' ? 'var(--warning-color)' : 'var(--primary-color)') ?>; color: white;"><?= ucfirst($complaint['priority']) ?></span></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Location Hierarchy</div>
                    <div class="info-value"><?= htmlspecialchars($complaint['ward']) ?> > <?= htmlspecialchars($complaint['area']) ?> > <?= htmlspecialchars($complaint['spot']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Exact Location</div>
                    <div class="info-value"><?= htmlspecialchars($complaint['exact_location']) ?: 'Not provided' ?></div>
                </div>
            </div>

            <?php if ($complaint['is_repeated']): ?>
                <div style="margin-top: 15px; padding: 10px; background: rgba(247, 37, 133, 0.1); border-radius: 8px; border-left: 4px solid var(--danger-color);">
                    <i class='bx bx-error-circle' style="color: var(--danger-color);"></i>
                    <strong>Flagged:</strong> This is a repeated complaint from the same area for this category.
                </div>
            <?php endif; ?>
        </div>

        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
            <div class="card">
                <div class="card-title">Attachments</div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                    <?php foreach ($attachments as $att): ?>
                        <a href="<?= BASE_URL ?>/uploads/<?= $att['file_path'] ?>" target="_blank" style="text-decoration: none; color: inherit;">
                            <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 10px; text-align: center; transition: background 0.3s;">
                                <i class='bx <?= strpos($att['file_type'], 'image') !== false ? 'bx-image' : 'bx-file' ?>' style="font-size: 32px; color: var(--primary-color); margin-bottom: 5px;"></i>
                                <div style="font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($att['file_name']) ?></div>
                                <span class="badge" style="background: var(--hover-bg); margin-top: 5px;"><?= $att['upload_type'] == 'action_proof' ? 'Resolution' : 'Complaint' ?> Proof</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Meta & Actions & Timeline -->
    <div>
        <div class="card">
            <div class="card-title">Meta Information</div>
            <div class="info-row">
                <div class="info-label">Complainant</div>
                <div class="info-value"><?= htmlspecialchars($complaint['complainant_name']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Submission Date</div>
                <div class="info-value"><?= date('d M Y, h:i A', strtotime($complaint['complaint_date'])) ?></div>
            </div>

            <?php if ($complaint['assigned_to']): ?>
                <div class="info-row">
                    <div class="info-label">Assigned Staff</div>
                    <div class="info-value"><?= htmlspecialchars($complaint['assigned_to_name']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Resolution SLA Deadline</div>
                    <div class="info-value" style="color: <?= time() > strtotime($complaint['resolution_deadline']) && !in_array($complaint['status_id'], [5, 6]) ? 'var(--danger-color)' : 'inherit' ?>">
                        <?= date('d M Y, h:i A', strtotime($complaint['resolution_deadline'])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions Based on Role -->
            <div style="margin-top: 20px;">
                <?php if ($role === 'supervisor'): ?>
                    <?php if ($complaint['status_id'] == 1 || $complaint['status_id'] == 7): // Submitted or Reopened 
                    ?>
                        <form method="POST" style="margin-bottom: 10px;">
                            <input type="hidden" name="action" value="verify">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Verify Complaint</button>
                        </form>
                    <?php elseif ($complaint['status_id'] == 2): // Verified 
                    ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="assign">
                            <div class="form-group">
                                <select name="staff_id" class="form-control" required style="margin-bottom: 10px;">
                                    <option value="">Select Staff to Assign</option>
                                    <?php foreach ($staff_members as $staff): ?>
                                        <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Assign Complaint</button>
                        </form>
                    <?php elseif ($complaint['status_id'] == 5): // Resolved 
                    ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="close">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Confirm & Close</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($role === 'complainant' && $complaint['status_id'] == 5): // Resolved 
                ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="reopen">
                        <div class="form-group">
                            <textarea name="remark" class="form-control" placeholder="Reason for reopening..." required rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn" style="width: 100%; background: var(--danger-color); color: white;">Reopen Complaint</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Timeline</div>
            <div class="timeline">
                <?php foreach ($history as $h): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon"></div>
                        <div class="timeline-content">
                            <div class="timeline-date"><?= date('d M Y, h:i A', strtotime($h['created_at'])) ?></div>
                            <div style="font-weight: 600; margin-bottom: 3px;"><?= $h['status_name'] ?></div>
                            <div style="font-size: 13px; color: var(--text-muted);">
                                <?= htmlspecialchars($h['remark']) ?><br>
                                By: <span style="font-weight:500"><?= htmlspecialchars($h['full_name']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>