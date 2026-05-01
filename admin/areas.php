<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "Manage Area Hierarchy";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$errors = [];

// ─── HANDLE POST ACTIONS ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type   = $_POST['type']   ?? '';   // ward | area | spot

    // ── ADD ──────────────────────────────────────────────────────────────────
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $errors[] = "Name is required.";
        } else {
            if ($type === 'ward') {
                $ins = $pdo->prepare("INSERT INTO ward_master (name, description) VALUES (?, ?)");
                $ins->execute([$name, $desc]);
            } elseif ($type === 'area') {
                $ward_id = (int)($_POST['ward_id'] ?? 0);
                if (!$ward_id) { $errors[] = "Select a Ward."; }
                else {
                    $ins = $pdo->prepare("INSERT INTO area_master (ward_id, name, description) VALUES (?, ?, ?)");
                    $ins->execute([$ward_id, $name, $desc]);
                }
            } elseif ($type === 'spot') {
                $area_id = (int)($_POST['area_id'] ?? 0);
                if (!$area_id) { $errors[] = "Select an Area."; }
                else {
                    $ins = $pdo->prepare("INSERT INTO spot_master (area_id, name, description) VALUES (?, ?, ?)");
                    $ins->execute([$area_id, $name, $desc]);
                }
            }
            if (empty($errors)) {
                $_SESSION['flash_message'] = ucfirst($type) . " '{$name}' added successfully.";
                $_SESSION['flash_type'] = 'success';
                header("Location: areas.php");
                exit();
            }
        }
    }

    // ── EDIT ─────────────────────────────────────────────────────────────────
    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if ($id < 1 || empty($name)) {
            $errors[] = "Invalid data.";
        } else {
            if ($type === 'ward') {
                $upd = $pdo->prepare("UPDATE ward_master SET name=?, description=? WHERE id=?");
            } elseif ($type === 'area') {
                $upd = $pdo->prepare("UPDATE area_master SET name=?, description=? WHERE id=?");
            } elseif ($type === 'spot') {
                $upd = $pdo->prepare("UPDATE spot_master SET name=?, description=? WHERE id=?");
            }
            $upd->execute([$name, $desc, $id]);
            $_SESSION['flash_message'] = ucfirst($type) . " updated.";
            $_SESSION['flash_type'] = 'success';
            header("Location: areas.php");
            exit();
        }
    }

    // ── TOGGLE STATUS ─────────────────────────────────────────────────────────
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $table = ($type === 'ward') ? 'ward_master' : (($type === 'area') ? 'area_master' : 'spot_master');
            $curr = $pdo->prepare("SELECT status FROM {$table} WHERE id=?");
            $curr->execute([$id]);
            $row = $curr->fetch();
            $ns  = ($row['status'] === 'active') ? 'inactive' : 'active';
            $upd = $pdo->prepare("UPDATE {$table} SET status=? WHERE id=?");
            $upd->execute([$ns, $id]);
            $_SESSION['flash_message'] = ucfirst($type) . " status changed to '{$ns}'.";
            $_SESSION['flash_type'] = 'success';
            header("Location: areas.php");
            exit();
        }
    }
}

// ─── EDIT MODE (GET) ─────────────────────────────────────────────────────────
$editItem = null;
$editType = null;
if (isset($_GET['edit_ward'])) {
    $stmt = $pdo->prepare("SELECT * FROM ward_master WHERE id=?");
    $stmt->execute([(int)$_GET['edit_ward']]);
    $editItem = $stmt->fetch(); $editType = 'ward';
} elseif (isset($_GET['edit_area'])) {
    $stmt = $pdo->prepare("SELECT * FROM area_master WHERE id=?");
    $stmt->execute([(int)$_GET['edit_area']]);
    $editItem = $stmt->fetch(); $editType = 'area';
} elseif (isset($_GET['edit_spot'])) {
    $stmt = $pdo->prepare("SELECT * FROM spot_master WHERE id=?");
    $stmt->execute([(int)$_GET['edit_spot']]);
    $editItem = $stmt->fetch(); $editType = 'spot';
}

// ─── FETCH DATA ───────────────────────────────────────────────────────────────
$wards = $pdo->query("SELECT * FROM ward_master ORDER BY status DESC, name ASC")->fetchAll();
$areas = $pdo->query("SELECT a.*, w.name as ward_name FROM area_master a JOIN ward_master w ON a.ward_id=w.id ORDER BY a.status DESC, w.name, a.name")->fetchAll();
$spots = $pdo->query("SELECT sp.*, a.name as area_name, w.name as ward_name FROM spot_master sp JOIN area_master a ON sp.area_id=a.id JOIN ward_master w ON a.ward_id=w.id ORDER BY sp.status DESC, w.name, a.name, sp.name")->fetchAll();

// For dropdowns
$ward_list  = $pdo->query("SELECT id, name FROM ward_master WHERE status='active' ORDER BY name")->fetchAll();
$area_list  = $pdo->query("SELECT id, name, ward_id FROM area_master WHERE status='active' ORDER BY name")->fetchAll();
?>

<?php if (!empty($errors)): ?>
    <div class="card" style="background:rgba(247,37,133,0.1);color:var(--danger-color);margin-bottom:15px;">
        <?php foreach($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ═══ ADD / EDIT FORM ═══════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:25px;">
    <div class="card-title"><?= $editItem ? 'Edit ' . ucfirst($editType) : 'Add New Location' ?></div>

    <form method="POST" id="area_form">
        <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
        <?php if ($editItem): ?>
            <input type="hidden" name="id"   value="<?= $editItem['id'] ?>">
            <input type="hidden" name="type" value="<?= $editType ?>">
        <?php endif; ?>

        <?php if (!$editItem): ?>
        <div class="form-group">
            <label class="form-label">Level *</label>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                    <input type="radio" name="type" value="ward" checked onchange="toggleAreaFields(this.value)"> Ward (Level 1)
                </label>
                <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                    <input type="radio" name="type" value="area" onchange="toggleAreaFields(this.value)"> Area (Level 2)
                </label>
                <label style="cursor:pointer;display:flex;align-items:center;gap:6px;">
                    <input type="radio" name="type" value="spot" onchange="toggleAreaFields(this.value)"> Spot (Level 3)
                </label>
            </div>
        </div>

        <div id="ward_select_wrap" style="display:none;" class="form-group">
            <label class="form-label">Parent Ward *</label>
            <select name="ward_id" id="parent_ward" class="form-control" onchange="loadAreasForSpot(this.value)">
                <option value="">-- Select Ward --</option>
                <?php foreach($ward_list as $w): ?>
                    <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="area_select_wrap" style="display:none;" class="form-group">
            <label class="form-label">Parent Area *</label>
            <select name="area_id" id="parent_area" class="form-control">
                <option value="">-- Select Ward First --</option>
            </select>
        </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:15px;">
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-control" required minlength="2"
                    placeholder="Location name"
                    value="<?= htmlspecialchars($editItem['name'] ?? '') ?>">
            </div>
            <div style="display:flex;align-items:flex-end;gap:8px;">
                <button type="submit" class="btn btn-primary" style="flex:1;">
                    <i class='bx <?= $editItem ? "bx-save" : "bx-plus" ?>'></i>
                    <?= $editItem ? 'Save Changes' : 'Add' ?>
                </button>
                <?php if ($editItem): ?>
                    <a href="areas.php" class="btn" style="background:transparent;border:1px solid var(--border-color);color:var(--text-color);">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group" style="margin-top:8px;">
            <label class="form-label">Description (Optional)</label>
            <input type="text" name="description" class="form-control"
                placeholder="Brief description"
                value="<?= htmlspecialchars($editItem['description'] ?? '') ?>">
        </div>
    </form>
</div>

<!-- ═══ WARD TABLE ═══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-title">Wards — Level 1 (<?= count($wards) ?> records)</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>#</th><th>Ward Name</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach($wards as $i => $w): ?>
                <tr style="<?= $w['status']==='inactive'?'opacity:0.55;':'' ?>">
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($w['name']) ?></strong></td>
                    <td style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($w['description']?:'—') ?></td>
                    <td><span class="badge" style="background:<?= $w['status']==='active'?'var(--success-color)':'#6c757d' ?>;color:white;"><?= ucfirst($w['status']) ?></span></td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a href="areas.php?edit_ward=<?= $w['id'] ?>" class="btn" style="padding:5px 10px;font-size:12px;background:var(--hover-bg);border:1px solid var(--border-color);color:var(--text-color);"><i class='bx bx-edit'></i> Edit</a>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Toggle status?')">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="type"   value="ward">
                            <input type="hidden" name="id"     value="<?= $w['id'] ?>">
                            <button type="submit" class="btn" style="padding:5px 10px;font-size:12px;background:<?= $w['status']==='active'?'rgba(247,37,133,0.1)':'rgba(25,135,84,0.1)' ?>;border:1px solid var(--border-color);color:<?= $w['status']==='active'?'var(--danger-color)':'var(--success-color)' ?>;">
                                <i class='bx <?= $w['status']==='active'?'bx-block':'bx-check' ?>'></i> <?= $w['status']==='active'?'Disable':'Enable' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══ AREA TABLE ═══════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-title">Areas — Level 2 (<?= count($areas) ?> records)</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>#</th><th>Area Name</th><th>Ward</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach($areas as $i => $a): ?>
                <tr style="<?= $a['status']==='inactive'?'opacity:0.55;':'' ?>">
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
                    <td style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($a['ward_name']) ?></td>
                    <td style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($a['description']?:'—') ?></td>
                    <td><span class="badge" style="background:<?= $a['status']==='active'?'var(--success-color)':'#6c757d' ?>;color:white;"><?= ucfirst($a['status']) ?></span></td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a href="areas.php?edit_area=<?= $a['id'] ?>" class="btn" style="padding:5px 10px;font-size:12px;background:var(--hover-bg);border:1px solid var(--border-color);color:var(--text-color);"><i class='bx bx-edit'></i> Edit</a>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Toggle status?')">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="type"   value="area">
                            <input type="hidden" name="id"     value="<?= $a['id'] ?>">
                            <button type="submit" class="btn" style="padding:5px 10px;font-size:12px;background:<?= $a['status']==='active'?'rgba(247,37,133,0.1)':'rgba(25,135,84,0.1)' ?>;border:1px solid var(--border-color);color:<?= $a['status']==='active'?'var(--danger-color)':'var(--success-color)' ?>;">
                                <i class='bx <?= $a['status']==='active'?'bx-block':'bx-check' ?>'></i> <?= $a['status']==='active'?'Disable':'Enable' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══ SPOT TABLE ═══════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-title">Spots — Level 3 (<?= count($spots) ?> records)</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>#</th><th>Spot Name</th><th>Area</th><th>Ward</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach($spots as $i => $sp): ?>
                <tr style="<?= $sp['status']==='inactive'?'opacity:0.55;':'' ?>">
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($sp['name']) ?></strong></td>
                    <td style="color:var(--text-muted);"><?= htmlspecialchars($sp['area_name']) ?></td>
                    <td style="color:var(--text-muted);font-size:12px;"><?= htmlspecialchars($sp['ward_name']) ?></td>
                    <td><span class="badge" style="background:<?= $sp['status']==='active'?'var(--success-color)':'#6c757d' ?>;color:white;"><?= ucfirst($sp['status']) ?></span></td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a href="areas.php?edit_spot=<?= $sp['id'] ?>" class="btn" style="padding:5px 10px;font-size:12px;background:var(--hover-bg);border:1px solid var(--border-color);color:var(--text-color);"><i class='bx bx-edit'></i> Edit</a>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Toggle status?')">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="type"   value="spot">
                            <input type="hidden" name="id"     value="<?= $sp['id'] ?>">
                            <button type="submit" class="btn" style="padding:5px 10px;font-size:12px;background:<?= $sp['status']==='active'?'rgba(247,37,133,0.1)':'rgba(25,135,84,0.1)' ?>;border:1px solid var(--border-color);color:<?= $sp['status']==='active'?'var(--danger-color)':'var(--success-color)' ?>;">
                                <i class='bx <?= $sp['status']==='active'?'bx-block':'bx-check' ?>'></i> <?= $sp['status']==='active'?'Disable':'Enable' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// All area data for client-side filtering
const allAreas = <?= json_encode($area_list) ?>;

function toggleAreaFields(type) {
    document.getElementById('ward_select_wrap').style.display = (type === 'area' || type === 'spot') ? 'block' : 'none';
    document.getElementById('area_select_wrap').style.display = (type === 'spot') ? 'block' : 'none';
}

function loadAreasForSpot(wardId) {
    const sel = document.getElementById('parent_area');
    sel.innerHTML = '<option value="">-- Select Area --</option>';
    allAreas.filter(a => a.ward_id == wardId).forEach(a => {
        sel.innerHTML += `<option value="${a.id}">${a.name}</option>`;
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
