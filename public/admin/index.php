<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod', 'author');

$db = getDB();
$horseCount = $db->query('SELECT COUNT(*) FROM horses WHERE is_deleted = 0')->fetchColumn();
$photoCount = $db->query('SELECT COUNT(*) FROM horse_photos')->fetchColumn();
$compCount  = $db->query('SELECT COUNT(*) FROM competitions')->fetchColumn();
$showCount  = $db->query('SELECT COUNT(*) FROM showrecords')->fetchColumn();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Dashboard</h1>
  <div class="page-actions">
    <a href="<?= e(SITE_URL) ?>/admin/horse_add.php" class="btn">+ Lisää hevonen</a>
  </div>
</div>
<div class="admin-body">
  <p style="font-size:0.8rem;color:var(--color-text-muted);margin-bottom:1.25rem">
    Tervetuloa, <strong><?= e($_SESSION['admin_username']) ?></strong>!
  </p>

  <div class="admin-stat-row">
    <div class="admin-stat-card">
      <div class="stat-icon">🐎</div>
      <div class="stat-num"><?= (int)$horseCount ?></div>
      <div class="stat-label">Hevosta</div>
    </div>
    <div class="admin-stat-card">
      <div class="stat-icon">📷</div>
      <div class="stat-num"><?= (int)$photoCount ?></div>
      <div class="stat-label">Kuvaa</div>
    </div>
    <div class="admin-stat-card">
      <div class="stat-icon">🏆</div>
      <div class="stat-num"><?= (int)$compCount ?></div>
      <div class="stat-label">Kilpailua</div>
    </div>
    <div class="admin-stat-card">
      <div class="stat-icon">🎀</div>
      <div class="stat-num"><?= (int)$showCount ?></div>
      <div class="stat-label">Näyttelytulosta</div>
    </div>
  </div>

  <div style="display:flex;gap:0.75rem;flex-wrap:wrap">
    <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="btn">🐎 Hevosten hallinta</a>
    <a href="<?= e(SITE_URL) ?>/admin/horse_add.php" class="btn-ghost">+ Lisää hevonen</a>
  </div>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
