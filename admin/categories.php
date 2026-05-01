<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "Manage Categories";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$errors = [];
$success = '';

// ─── HANDLE POST ACTIONS ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD new category
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $errors[] = "Category name is required.";
        } elseif (strlen($name) < 3) {
            $errors[] = "Name must be at least 3 characters.";
        } else {
            // Check duplicate name
            $dup = $pdo->prepare("SELECT id FROM complaint_categories WHERE name = ?");
            $dup->execute([$name]);
            if ($dup->fetch()) {
                $errors[] = "A category with this name already exists.";
            } else {
                $ins = $pdo->prepare("INSERT INTO complaint_categories (name, description) VALUES (?, ?)");
                $ins->execute([$name, $desc]);
                $success = "Category '{$name}' added successfully.";
            }
        }
    }

    // EDIT existing category
    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($id < 1 || empty($name)) {
            $errors[] = "Invalid data for editing.";
        } else {
            $upd = $pdo->prepare("UPDATE complaint_categories SET name = ?, description = ? WHERE id = ?");
            $upd->execute([$name, $desc, $id]);
            $success = "Category updated successfully.";
        }
    }

    // TOGGLE status (active ↔ inactive)
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $curr = $pdo->prepare("SELECT status FROM complaint_categories WHERE id = ?");
            $curr->execute([$id]);
            $row = $curr->fetch();
            $newStatus = ($row['status'] === 'active') ? 'inactive' : 'active';
            $upd = $pdo->prepare("UPDATE complaint_categories SET status = ? WHERE id = ?");
            $upd->execute([$newStatus, $id]);
            $success = "Category status changed to '{$newStatus}'.";
        }
    }

    if (empty($errors) && $success) {
        $_SESSION['flash_message'] = $success;
        $_SESSION['flash_type']    = 'success';
        header("Location: categories.php");
        exit();
    }
}

// ─── FETCH EDIT TARGET (GET) ─────────────────────────────────────────────────
$editCategory = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM complaint_categories WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCategory = $stmt->fetch();
}

// ─── FETCH ALL CATEGORIES ─────────────────────────────────────────────────────
$categories = $pdo->query("SELECT * FROM complaint_categories ORDER BY status DESC, name ASC")->fetchAll();
?>

<div class="card" style="max-width:700px; margin: 0 auto 25px;">
    <div class="card-title"><?= $editCategory ? 'Edit Category' : 'Add New Category' ?></div>

    <?php if (!empty($errors)): ?>
        <div style="background:rgba(247,37,133,0.1);color:var(--danger-color);padding:12px;border-radius:8px;margin-bottom:16px;">
            <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="cat_form">
        <input type="hidden" name="action" value="<?= $editCategory ? 'edit' : 'add' ?>">
        <?php if ($editCategory): ?>
            <input type="hidden" name="id" value="<?= $editCategory['id'] ?>">
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:2fr 1fr; gap:15px;">
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" name="name" class="form-control" required minlength="3"
                    placeholder="E.g. Pothole on Road"
                    value="<?= htmlspecialchars($editCategory['name'] ?? '') ?>">
            </div>
            <div style="display:flex; align-items:flex-end; gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">
                    <i class='bx <?= $editCategory ? "bx-save" : "bx-plus" ?>'></i>
                    <?= $editCategory ? 'Save Changes' : 'Add Category' ?>
                </button>
                <?php if ($editCategory): ?>
                    <a href="categories.php" class="btn" style="background:transparent;border:1px solid var(--border-color);color:var(--text-color);">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group" style="margin-top:10px;">
            <label class="form-label">Description (Optional)</label>
            <input type="text" name="description" class="form-control"
                placeholder="Brief description of this category"
                value="<?= htmlspecialchars($editCategory['description'] ?? '') ?>">
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">All Categories (<?= count($categories) ?> total)</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:20px;">No categories found.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $i => $cat): ?>
                        <tr style="<?= $cat['status'] === 'inactive' ? 'opacity:0.55;' : '' ?>">
                            <td><?= $i + 1 ?></td>
                            <td>
                                <strong><?= htmlspecialchars($cat['name']) ?></strong>
                            </td>
                            <td style="color:var(--text-muted); font-size:13px;">
                                <?= htmlspecialchars($cat['description'] ?: '—') ?>
                            </td>
                            <td>
                                <span class="badge" style="background:<?= $cat['status'] === 'active' ? 'var(--success-color)' : '#6c757d' ?>;color:white;">
                                    <?= ucfirst($cat['status']) ?>
                                </span>
                            </td>
                            <td style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a href="categories.php?edit=<?= $cat['id'] ?>"
                                   class="btn" style="padding:5px 12px;font-size:12px;background:var(--hover-bg);border:1px solid var(--border-color);color:var(--text-color);">
                                    <i class='bx bx-edit'></i> Edit
                                </a>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Toggle status for this category?')">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn"
                                        style="padding:5px 12px;font-size:12px;background:<?= $cat['status']==='active' ? 'rgba(247,37,133,0.1)' : 'rgba(25,135,84,0.1)' ?>;border:1px solid var(--border-color);color:<?= $cat['status']==='active' ? 'var(--danger-color)' : 'var(--success-color)' ?>;">
                                        <i class='bx <?= $cat['status']==='active' ? 'bx-block' : 'bx-check' ?>'></i>
                                        <?= $cat['status'] === 'active' ? 'Disable' : 'Enable' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
