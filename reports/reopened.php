<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "Mandatory Report: Reopened Complaints Summary";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();

// Fetch summary stats for reopened complaints (U=35 -> R=5 Reopened complaints summary)
$stmt = $pdo->query("
    SELECT c.id, c.complaint_uid, c.title, c.reopen_count, c.exact_location,
           cat.name as category_name, s.status_name,
           u.full_name as complainant, a.name as area
    FROM complaints c
    JOIN complaint_categories cat ON c.category_id = cat.id
    JOIN status_master s ON c.status_id = s.id
    JOIN users u ON c.complainant_id = u.id
    JOIN area_master a ON c.area_id = a.id
    WHERE c.is_reopened = 1 OR c.reopen_count > 0
    ORDER BY c.reopen_count DESC, c.updated_at DESC
");
$reopened = $stmt->fetchAll();

// Get area-wise breakdown for reopened charts
$area_stmt = $pdo->query("
    SELECT a.name as area, count(c.id) as count
    FROM complaints c
    JOIN area_master a ON c.area_id = a.id
    WHERE c.is_reopened = 1
    GROUP BY a.id
    ORDER BY count DESC
");
$area_stats = $area_stmt->fetchAll();
?>

<div class="card" style="background: linear-gradient(135deg, rgba(67, 97, 238, 0.05), rgba(76, 201, 240, 0.05)); border: 1px solid rgba(67, 97, 238, 0.2);">
    <h2 style="color: var(--primary-color); margin-bottom: 5px;"><i class='bx bx-bar-chart-alt-2'></i> Mandatory Custom Report</h2>
    <p style="color: var(--text-muted);">Configuration: R = ((34) mod 6) + 1 = 5 → <strong>Reopened complaints summary</strong></p>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
    <!-- Data Table -->
    <div class="card">
        <div class="card-title">Reopened Complaints Log</div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>UID</th>
                        <th>Category</th>
                        <th>Area</th>
                        <th>Reopen Count</th>
                        <th>Current Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($reopened)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 20px;">No complaints have been reopened yet.</td></tr>
                    <?php else: ?>
                        <?php foreach($reopened as $r): ?>
                            <tr>
                                <td><strong><?= $r['complaint_uid'] ?></strong></td>
                                <td><?= htmlspecialchars($r['category_name']) ?></td>
                                <td><?= htmlspecialchars($r['area']) ?></td>
                                <td style="text-align: center;"><span style="background: var(--danger-color); color: white; border-radius: 50%; padding: 2px 8px; font-weight: bold; font-size: 13px;"><?= $r['reopen_count'] ?></span></td>
                                <td><span class="badge badge-<?= strtolower(str_replace(' ', '', $r['status_name'])) ?>"><?= $r['status_name'] ?></span></td>
                                <td><a href="<?= BASE_URL ?>/complaints/view.php?id=<?= $r['id'] ?>" class="btn btn-primary" style="padding: 4px 10px; font-size: 12px;">Review</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Analytics Side -->
    <div>
        <div class="card">
            <div class="card-title">Area-wise Breakdown</div>
            <?php if(empty($area_stats)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 20px;">No data available.</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0;">
                    <?php 
                    $max = $area_stats[0]['count']; 
                    foreach($area_stats as $as): 
                        $pct = ($as['count'] / $max) * 100;
                    ?>
                        <li style="margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px;">
                                <span><?= htmlspecialchars($as['area']) ?></span>
                                <strong><?= $as['count'] ?></strong>
                            </div>
                            <div style="height: 8px; background: var(--border-color); border-radius: 4px; overflow: hidden;">
                                <div style="height: 100%; width: <?= $pct ?>%; background: var(--danger-color); border-radius: 4px;"></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div class="card" style="background: var(--hover-bg);">
            <div class="card-title" style="margin-bottom: 10px; border:none;">Analysis</div>
            <p style="font-size: 14px; line-height: 1.6;">
                This report tracks complaints that were marked as Resolved by staff but were subsequently rejected/reopened by the complainant due to unsatisfactory work. High reopen counts in specific areas or categories indicate poor quality of initial resolution.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
