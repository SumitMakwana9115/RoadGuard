<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'complainant') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "Register New Complaint";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get Categories and Wards for dropdowns
$categories = $pdo->query("SELECT id, name FROM complaint_categories WHERE status = 'active' ORDER BY name")->fetchAll();
$wards = $pdo->query("SELECT id, name FROM ward_master WHERE status = 'active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] ?? 0;
    $ward_id = $_POST['ward_id'] ?? 0;
    $area_id = $_POST['area_id'] ?? 0;
    $spot_id = $_POST['spot_id'] ?? 0;
    $exact_location = trim($_POST['exact_location'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    
    // Server-side validation
    $errors = [];
    if (empty($title) || strlen($title) < 5) $errors[] = "Title must be at least 5 characters.";
    if (empty($description)) $errors[] = "Description is required.";
    if (empty($category_id) || empty($ward_id) || empty($area_id) || empty($spot_id)) $errors[] = "Please select all location fields.";
    
    // File upload handling
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
            $errors[] = "File size exceeds 5MB limit.";
        }
        if (!in_array($ext, ALLOWED_EXTENSIONS) || !in_array($file_type, ALLOWED_MIME_TYPES)) {
            $errors[] = "Invalid file type. Allowed: JPG, PNG, PDF.";
        }
        
        if (empty($errors)) {
            $file_name = 'proof_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_path = COMPLAINT_PROOF_DIR . $file_name;
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $file_path = 'complaint_proof/' . $file_name;
            } else {
                $errors[] = "Failed to upload file.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Generate UID (Format: RG-YYYYMMDD-ID)
            $stmt = $pdo->query("SELECT MAX(id) FROM complaints");
            $max_id = $stmt->fetchColumn() + 1;
            $uid = 'RG-' . date('Ymd') . '-' . str_pad($max_id, 3, '0', STR_PAD_LEFT);
            
            // Personalized Rules Application (U=35, Odd: Repeated complaint flagging)
            $is_repeated = 0;
            if (SPECIAL_RULE == 'repeated_flagging') {
                $checkRepeated = $pdo->prepare("
                    SELECT id FROM complaints 
                    WHERE category_id = ? AND area_id = ? 
                    AND complaint_date > DATE_SUB(NOW(), INTERVAL ? DAY)
                    LIMIT 1
                ");
                $checkRepeated->execute([$category_id, $area_id, REPEATED_COMPLAINT_DAYS]);
                if ($checkRepeated->fetch()) {
                    $is_repeated = 1; // Flag as repeated
                }
            }

            // Insert Complaint
            $insert = $pdo->prepare("
                INSERT INTO complaints 
                (complaint_uid, title, description, category_id, ward_id, area_id, spot_id, exact_location, priority, complaint_date, complainant_id, is_repeated) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            
            $insert->execute([
                $uid, $title, $description, $category_id, $ward_id, $area_id, $spot_id, $exact_location, $priority, $user_id, $is_repeated
            ]);
            
            $complaint_id = $pdo->lastInsertId();
            
            // Insert History (Status 1 = Submitted)
            $history = $pdo->prepare("INSERT INTO complaint_history (complaint_id, to_status_id, updated_by, remark) VALUES (?, 1, ?, 'Complaint submitted')");
            $history->execute([$complaint_id, $user_id]);
            
            // Insert Attachment if present
            if ($file_path) {
                $attach = $pdo->prepare("INSERT INTO complaint_attachments (complaint_id, file_name, file_path, file_type, file_size, upload_type, uploaded_by) VALUES (?, ?, ?, ?, ?, 'complaint_proof', ?)");
                $attach->execute([$complaint_id, $file['name'], $file_path, $file_type, $file_size, $user_id]);
            }
            
            $pdo->commit();
            
            $_SESSION['flash_message'] = "Complaint Registered Successfully. ID: $uid";
            $_SESSION['flash_type'] = "success";
            header("Location: " . BASE_URL . "/dashboard/complainant.php");
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "System Error: " . $e->getMessage();
        }
    }
}
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-title">Register New Complaint (Road & Pathway Damage)</div>
    
    <?php if(!empty($errors)): ?>
        <div style="background: rgba(247, 37, 133, 0.1); color: var(--danger-color); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" id="complaint_form">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label class="form-label">Complaint Title *</label>
                <input type="text" name="title" id="title" class="form-control" required minlength="5" placeholder="Short description of issue">
            </div>
            
            <div class="form-group">
                <label class="form-label">Category *</label>
                <select name="category_id" id="category_id" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Detailed Description *</label>
            <textarea name="description" class="form-control" rows="4" required placeholder="Provide full details of the issue..."></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label class="form-label">Ward *</label>
                <select name="ward_id" id="ward_id" class="form-control" required>
                    <option value="">-- Select Ward --</option>
                    <?php foreach($wards as $w): ?>
                        <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Area *</label>
                <select name="area_id" id="area_id" class="form-control" required>
                    <option value="">Select Ward First</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Spot *</label>
                <select name="spot_id" id="spot_id" class="form-control" required>
                    <option value="">Select Area First</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
            <div class="form-group">
                <label class="form-label">Exact Location / Landmark (Optional)</label>
                <input type="text" name="exact_location" class="form-control" placeholder="E.g., In front of ABC shop">
            </div>
            
            <div class="form-group">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-control">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical (Safety Hazard)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Proof Attachment (Image/Document) - Optional</label>
            <input type="file" name="proof" class="form-control" accept="image/*,.pdf,.doc,.docx" style="padding: 9px 15px;">
            <small style="color: var(--text-muted); display: block; margin-top: 5px;">Max size: 5MB. Formats: JPG, PNG, PDF</small>
        </div>

        <div style="text-align: right; margin-top: 30px;">
            <a href="<?= BASE_URL ?>/dashboard/complainant.php" class="btn" style="background: transparent; color: var(--text-color); border: 1px solid var(--border-color); margin-right: 10px;">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class='bx bx-send'></i> Submit Complaint</button>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
