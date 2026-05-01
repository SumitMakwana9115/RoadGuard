<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "Manage Task";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$complaint_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Get complaint details
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name, s.status_name,
           w.name as ward, a.name as area, sp.name as spot
    FROM complaints c
    JOIN complaint_categories cat ON c.category_id = cat.id
    JOIN status_master s ON c.status_id = s.id
    JOIN ward_master w ON c.ward_id = w.id
    JOIN area_master a ON c.area_id = a.id
    JOIN spot_master sp ON c.spot_id = sp.id
    WHERE c.id = ? AND c.assigned_to = ?
");
$stmt->execute([$complaint_id, $user_id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    echo "<div class='card text-center'>Task not found or unauthorized.</div>";
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}

$errors = [];

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remark = trim($_POST['remark'] ?? '');

    if (empty($remark)) {
        $errors[] = "Remark is required.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($action === 'start') {
                if ($complaint['status_id'] != 3) {
                    throw new Exception("Invalid status transition. Task must be assigned first.");
                }
                $update = $pdo->prepare("UPDATE complaints SET status_id = 4 WHERE id = ?");
                $update->execute([$complaint_id]);

                $history = $pdo->prepare("INSERT INTO complaint_history (complaint_id, from_status_id, to_status_id, updated_by, remark) VALUES (?, 3, 4, ?, ?)");
                $history->execute([$complaint_id, $user_id, $remark]);

                $_SESSION['flash_message'] = "Task status updated to In Progress.";
                $_SESSION['flash_type'] = "success";
            } elseif ($action === 'resolve') {
                if ($complaint['status_id'] != 4) {
                    throw new Exception("Invalid status transition. Task must be in progress first.");
                }
                // Handle Action Proof File Upload
                $file_path = null;
                $file_name = null;
                $file_type = null;
                $file_size = 0;

                if (isset($_FILES['proof']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['proof'];
                    $file_size = $file['size'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $file_type = mime_content_type($file['tmp_name']);

                    if ($file_size > MAX_FILE_SIZE) {
                        throw new Exception("File size exceeds 5MB limit.");
                    }
                    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
                        throw new Exception("Invalid file type.");
                    }

                    $file_name = 'action_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $upload_path = ACTION_PROOF_DIR . $file_name;
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $file_path = 'action_proof/' . $file_name;

                        // Insert Attachment
                        $attach = $pdo->prepare("INSERT INTO complaint_attachments (complaint_id, file_name, file_path, file_type, file_size, upload_type, uploaded_by) VALUES (?, ?, ?, ?, ?, 'action_proof', ?)");
                        $attach->execute([$complaint_id, $file['name'], $file_path, $file_type, $file_size, $user_id]);
                    }
                }

                if (!$file_path) {
                    throw new Exception("Action proof attachment is required to resolve.");
                }

                $update = $pdo->prepare("UPDATE complaints SET status_id = 5 WHERE id = ?");
                $update->execute([$complaint_id]);

                $history = $pdo->prepare("INSERT INTO complaint_history (complaint_id, from_status_id, to_status_id, updated_by, remark) VALUES (?, 4, 5, ?, ?)");
                $history->execute([$complaint_id, $user_id, "Resolution Remark: " . $remark]);

                $_SESSION['flash_message'] = "Task marked as Resolved.";
                $_SESSION['flash_type'] = "success";
            }

            $pdo->commit();
            header("Location: task.php?id=$complaint_id");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px;">
        <h2 style="margin:0;">Task Form: #<?= $complaint['complaint_uid'] ?></h2>
        <span class="badge badge-<?= strtolower(str_replace(' ', '', $complaint['status_name'])) ?>" style="font-size: 15px; padding: 8px 16px;"><?= $complaint['status_name'] ?></span>
    </div>

    <?php if (!empty($errors)): ?>
        <div style="background: rgba(247, 37, 133, 0.1); color: var(--danger-color); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <div>
            <h4 style="margin-bottom: 10px; color: var(--text-muted);">Issue Details</h4>
            <p><strong>Title:</strong> <?= htmlspecialchars($complaint['title']) ?></p>
            <p><strong>Category:</strong> <?= htmlspecialchars($complaint['category_name']) ?></p>
            <p><strong>Location:</strong> <?= htmlspecialchars($complaint['area']) ?> > <?= htmlspecialchars($complaint['spot']) ?></p>
            <p><strong>Exact Location:</strong> <?= htmlspecialchars($complaint['exact_location']) ?></p>
            <div style="background: var(--hover-bg); padding: 10px; border-radius: 8px; margin-top: 10px;">
                <small style="color: var(--text-muted); display: block; margin-bottom: 5px;">User Description:</small>
                <?= nl2br(htmlspecialchars($complaint['description'])) ?>
            </div>
        </div>
        <div>
            <h4 style="margin-bottom: 10px; color: var(--text-muted);">SLA Deadlines</h4>
            <?php
            $deadline = strtotime($complaint['resolution_deadline']);
            $is_overdue = time() > $deadline;
            ?>
            <div style="padding: 15px; border-radius: 8px; border: 1px solid <?= $is_overdue && !in_array($complaint['status_id'], [5, 6]) ? 'var(--danger-color)' : 'var(--border-color)' ?>;">
                <p><strong>Resolve By:</strong> <?= date('d M Y, H:i', $deadline) ?></p>
                <?php if ($is_overdue && !in_array($complaint['status_id'], [5, 6])): ?>
                    <p style="color: var(--danger-color); font-weight: bold; margin-top: 5px;"><i class='bx bx-alarm-exclamation'></i> Task is Overdue!</p>
                <?php endif; ?>
            </div>

            <div style="margin-top: 20px;">
                <a href="<?= BASE_URL ?>/complaints/view.php?id=<?= $complaint_id ?>" class="btn" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-color);">View Full History & Attachments</a>
            </div>
        </div>
    </div>

    <!-- Staff Action Forms -->
    <?php if ($complaint['status_id'] == 3): // Assigned 
    ?>
        <div style="background: rgba(67, 97, 238, 0.05); border: 1px solid rgba(67, 97, 238, 0.2); padding: 20px; border-radius: 8px;">
            <h3 style="color: var(--primary-color); margin-bottom: 15px;">Acknowledge & Start Work</h3>
            <form method="POST">
                <input type="hidden" name="action" value="start">
                <div class="form-group">
                    <label class="form-label">Remark / Initial Assessment *</label>
                    <textarea name="remark" class="form-control" rows="2" required placeholder="E.g., Team dispatched, resources allocating..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Start Work (Update to In Progress)</button>
            </form>
        </div>
    <?php elseif ($complaint['status_id'] == 4): // In Progress 
    ?>
        <div style="background: rgba(75, 181, 67, 0.05); border: 1px solid rgba(75, 181, 67, 0.2); padding: 20px; border-radius: 8px;">
            <h3 style="color: var(--success-color); margin-bottom: 15px;">Resolve Task</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="resolve">
                <div class="form-group">
                    <label class="form-label">Resolution Details (Remark) *</label>
                    <textarea name="remark" class="form-control" rows="3" required placeholder="Describe the work done to resolve the issue..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Action Proof Attachment (Image/PDF) *</label>
                    <input type="file" name="proof" class="form-control" accept="image/*,.pdf" required style="padding: 9px 15px;">
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">Mandatory file to prove the work is completed (Max 5MB).</small>
                </div>
                <button type="submit" class="btn" style="background: var(--success-color); color: white;">Mark as Resolved</button>
            </form>
        </div>
    <?php elseif ($complaint['status_id'] == 5): ?>
        <div style="padding: 20px; background: rgba(75, 181, 67, 0.1); border-radius: 8px; text-align: center; color: var(--success-color);">
            <h3><i class='bx bx-check-circle' style="font-size: 24px; vertical-align: middle;"></i> Task Successfully Resolved</h3>
            <p style="margin-top: 10px;">Waiting for Supervisor closure or Complainant feedback.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>