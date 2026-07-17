<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$db = getDB();
$errors = [];
$flash  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $foal_id = (int)($_POST['foal_id'] ?? 0);

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } elseif ($action === 'delete' && $foal_id > 0) {
        requireRole('admin', 'mod');
        $db->prepare('UPDATE foals SET is_deleted = 1, deleted_at = NOW() WHERE id = :foal_id AND is_deleted = 0')
           ->execute([':foal_id' => $foal_id]);
        if (currentRole() === 'mod') {
            insertPendingDeletion('foal', $foal_id, (int)$_SESSION['admin_id']);
        }
        redirect(SITE_URL . '/admin/kasvatus_all.php?deleted=1');
    }
}

$foals = $db->query(
    'SELECT f.id, f.foal_name, f.birth_date, f.gender, f.status,
            f.foal_horse_id,
            s.name AS sire_name, d.name AS dam_name,
            fh.name AS foal_horse_name, fh.slug AS foal_horse_slug
     FROM foals f
     LEFT JOIN horses   s  ON s.id  = f.sire_id       AND s.is_deleted = 0
     LEFT JOIN horses   d  ON d.id  = f.dam_id        AND d.is_deleted = 0
     LEFT JOIN horses   fh ON fh.id = f.foal_horse_id AND fh.is_deleted = 0
     WHERE f.is_deleted = 0
     ORDER BY f.birth_date DESC, f.foal_name ASC'
)->fetchAll();

$statusLabels = ['expected' => 'Odotettu', 'born' => 'Syntynyt'];

if (isset($_GET['added']))   $flash = '<p class="flash-ok">Varsamerkintä lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Varsamerkintä päivitetty.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Varsamerkintä poistettu.</p>';

$pageTitle = 'Kasvatus';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Kasvatus</h1>
  <span style="font-size:0.78rem;color:var(--color-text-muted)"><?= count($foals) ?> varsamerkintää</span>
  <div class="page-actions">
    <a href="<?= e(SITE_URL) ?>/admin/foal_add.php" class="btn">+ Lisää varsamerkintä</a>
  </div>
</div>
<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $emsg): ?><li><?= e($emsg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?= $flash ?>

<?php if (empty($foals)): ?>
  <p style="color:var(--color-text-muted)">Ei varsamerkintöjä. Lisää ensimmäinen painamalla "+ Lisää varsamerkintä".</p>
<?php else: ?>
<div class="compact-list">
  <div class="compact-list-header" style="grid-template-columns:2fr 1fr 1fr 90px 80px 28px">
    <div>Varsa</div><div>Isä</div><div>Emä</div><div>Syntymäpäivä</div><div>Status</div><div></div>
  </div>
  <?php foreach ($foals as $fo):
    $sClass = $fo['status'] === 'expected' ? 'sbadge-expected' : 'sbadge-born';
    $gClass = match($fo['gender']) { 'ori' => 'gbadge-ori', 'tamma' => 'gbadge-tamma', default => 'gbadge-ruuna' };
  ?>
  <div class="compact-list-row" style="grid-template-columns:2fr 1fr 1fr 90px 80px 28px"
       onclick="adminToggleExpand('f<?= (int)$fo['id'] ?>')">
    <div>
      <div class="cl-name"><?= e($fo['foal_name'] ?? '—') ?></div>
      <?php if ($fo['gender']): ?>
        <span class="gbadge <?= $gClass ?>"><?= e($fo['gender']) ?></span>
      <?php endif; ?>
      <?php if ($fo['foal_horse_name']): ?>
        <a href="<?= e(horseUrl(['slug' => $fo['foal_horse_slug'], 'name' => $fo['foal_horse_name']])) ?>"
           style="font-size:0.72rem;color:var(--color-text-muted)" target="_blank" onclick="event.stopPropagation()">→ <?= e($fo['foal_horse_name']) ?></a>
      <?php endif; ?>
    </div>
    <div class="cl-meta"><?= e($fo['sire_name'] ?? '—') ?></div>
    <div class="cl-meta"><?= e($fo['dam_name']  ?? '—') ?></div>
    <div class="cl-mono"><?= $fo['birth_date'] ? date('d.m.Y', strtotime($fo['birth_date'])) : '—' ?></div>
    <div><span class="sbadge <?= $sClass ?>"><?= e($statusLabels[$fo['status']] ?? $fo['status']) ?></span></div>
    <div>
      <button class="cl-expand-btn" id="cl-btn-f<?= (int)$fo['id'] ?>"
              onclick="event.stopPropagation();adminToggleExpand('f<?= (int)$fo['id'] ?>')">▸</button>
    </div>
  </div>
  <div class="cl-expanded" id="cl-exp-f<?= (int)$fo['id'] ?>">
    <div class="cl-expanded-actions">
      <a href="<?= e(SITE_URL) ?>/admin/foal_edit.php?id=<?= (int)$fo['id'] ?>" class="btn-sm btn-edit">✏️ Muokkaa</a>
      <form method="post" action="kasvatus_all.php" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action"  value="delete">
        <input type="hidden" name="foal_id" value="<?= (int)$fo['id'] ?>">
        <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Poistetaanko varsamerkintä?')">🗑 Poista</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
