<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$db = getDB();

// Ryhmittele kuvat hevosen mukaan
$photosStmt = $db->query(
    'SELECT p.id, p.filename, p.original_name, p.title, p.caption, p.sort_order,
            h.id AS horse_id, h.name AS horse_name
     FROM horse_photos p
     JOIN horses h ON h.id = p.horse_id AND h.is_deleted = 0
     ORDER BY h.name ASC, p.sort_order ASC'
);
$allPhotos = $photosStmt->fetchAll();

// Ryhmittele hevosen id:n mukaan
$byHorse = [];
foreach ($allPhotos as $p) {
    $byHorse[$p['horse_id']] ??= ['name' => $p['horse_name'], 'photos' => []];
    $byHorse[$p['horse_id']]['photos'][] = $p;
}

if (isset($_GET['deleted']))  $flash = '<p class="flash-ok">Kuva poistettu.</p>';
elseif (isset($_GET['updated'])) $flash = '<p class="flash-ok">Kuvan tiedot tallennettu.</p>';
else $flash = '';

$pageTitle = 'Kuvat';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Kuvat</h1>
  <span style="font-size:0.78rem;color:var(--color-text-muted)"><?= count($allPhotos) ?> kuvaa</span>
</div>
<div class="admin-body">
<?= $flash ?>

<?php if (empty($byHorse)): ?>
  <p style="color:var(--color-text-muted)">Ei kuvia. Lataa kuvia hevosen omalta Kuvat-sivulta.</p>
<?php else: ?>
  <?php foreach ($byHorse as $horseId => $group): ?>
  <div style="margin-bottom:2rem">
    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem">
      <h2 style="font-family:var(--font-serif);font-size:1.1rem;color:var(--color-primary);font-weight:normal;margin:0">
        🐎 <?= e($group['name']) ?>
      </h2>
      <span style="font-size:0.72rem;color:var(--color-text-muted)"><?= count($group['photos']) ?> kuvaa</span>
      <a href="<?= e(SITE_URL) ?>/admin/photos.php?horse_id=<?= (int)$horseId ?>"
         class="btn-sm" style="margin-left:auto">+ Lataa kuva</a>
    </div>
    <div class="admin-photo-grid">
      <?php foreach ($group['photos'] as $idx => $photo): ?>
      <div class="admin-photo-card">
        <div class="admin-photo-thumb">
          <img src="<?= e(UPLOADS_URL . $photo['filename']) ?>"
               alt="<?= e($photo['original_name'] ?? '') ?>">
          <span class="photo-order-badge"><?= (int)$photo['sort_order'] ?></span>
          <?php if ($idx === 0): ?>
            <span class="photo-profile-badge">Profiili</span>
          <?php endif; ?>
          <form class="photo-delete-form" method="post"
                action="<?= e(SITE_URL) ?>/admin/photo_delete.php">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="photo_id"  value="<?= (int)$photo['id'] ?>">
            <input type="hidden" name="horse_id"  value="<?= (int)$horseId ?>">
            <input type="hidden" name="redirect"  value="kuvat_all">
            <button type="submit" class="photo-delete-btn"
                    onclick="return confirm('Poistetaanko kuva?')">×</button>
          </form>
        </div>
        <form class="photo-meta-form" method="post"
              action="<?= e(SITE_URL) ?>/admin/photo_update.php">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="photo_id"  value="<?= (int)$photo['id'] ?>">
          <input type="hidden" name="horse_id"  value="<?= (int)$horseId ?>">
          <input type="hidden" name="redirect"  value="kuvat_all">
          <div class="form-group" style="margin-bottom:0.4rem">
            <input type="text" name="title" placeholder="Otsikko"
                   value="<?= e($photo['title'] ?? '') ?>"
                   maxlength="255" class="form-control form-control-sm">
          </div>
          <div class="form-group" style="margin-bottom:0.4rem">
            <textarea name="caption" placeholder="Kuvateksti" rows="2"
                      class="form-control form-control-sm"><?= e($photo['caption'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-sm">Tallenna</button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
