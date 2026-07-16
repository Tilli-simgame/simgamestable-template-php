<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$horse_id = (int)($_GET['horse_id'] ?? 0);
if ($horse_id <= 0) {
    redirect(SITE_URL . '/admin/horses.php');
}

$db = getDB();
$horseStmt = $db->prepare('SELECT id, name FROM horses WHERE id = :id AND is_deleted = 0');
$horseStmt->execute([':id' => $horse_id]);
$horse = $horseStmt->fetch();
if (!$horse) {
    redirect(SITE_URL . '/admin/horses.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Virheellinen pyyntö.';
    } else {
        // Tarkista max kuvamäärä
        $countStmt = $db->prepare('SELECT COUNT(*) FROM horse_photos WHERE horse_id = :horse_id');
        $countStmt->execute([':horse_id' => $horse_id]);
        $photoCount = (int)$countStmt->fetchColumn();

        if ($photoCount >= MAX_PHOTOS_PER_HORSE) {
            $error = 'Hevosella on jo ' . MAX_PHOTOS_PER_HORSE . ' kuvaa. Poista ensin vanha kuva.';
        } elseif (!isset($_FILES['photo'])) {
            $error = 'Tiedoston lataus epäonnistui. Tarkista tiedosto ja yritä uudelleen.';
        } else {
            $uploadResult = validate_image_upload($_FILES['photo'], MAX_UPLOAD_SIZE);
            if (!$uploadResult['valid']) {
                $error = $uploadResult['error'];
            } else {
                $ext      = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $filename = generate_safe_filename($ext);
                $dest     = UPLOADS_DIR . $filename;

                if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                    $error = 'Tiedoston tallentaminen epäonnistui.';
                } else {
                    $maxOrderStmt = $db->prepare('SELECT MAX(sort_order) FROM horse_photos WHERE horse_id = :horse_id');
                    $maxOrderStmt->execute([':horse_id' => $horse_id]);
                    $nextOrder = (int)$maxOrderStmt->fetchColumn() + 1;

                    $newTitle   = sanitize($_POST['title']   ?? '');
                    $newCaption = sanitize($_POST['caption'] ?? '');

                    $insStmt = $db->prepare(
                        'INSERT INTO horse_photos (horse_id, filename, original_name, title, caption, sort_order)
                         VALUES (:horse_id, :filename, :original_name, :title, :caption, :sort_order)'
                    );
                    $insStmt->execute([
                        ':horse_id'      => $horse_id,
                        ':filename'      => $filename,
                        ':original_name' => sanitize($_FILES['photo']['name']),
                        ':title'         => $newTitle   !== '' ? $newTitle   : null,
                        ':caption'       => $newCaption !== '' ? $newCaption : null,
                        ':sort_order'    => $nextOrder,
                    ]);
                    redirect(SITE_URL . '/admin/photos.php?horse_id=' . $horse_id . '&uploaded=1');
                }
            }
        }
    }
}

// Hae nykyiset kuvat
$photosStmt = $db->prepare('SELECT id, filename, original_name, title, caption, sort_order FROM horse_photos WHERE horse_id = :horse_id ORDER BY sort_order ASC');
$photosStmt->execute([':horse_id' => $horse_id]);
$photos = $photosStmt->fetchAll();

if (isset($_GET['uploaded'])) $success = 'Kuva ladattu.';
if (isset($_GET['deleted']))  $success = 'Kuva poistettu.';
if (isset($_GET['updated']))  $success = 'Kuvan tiedot tallennettu.';

$pageTitle = 'Kuvat — ' . $horse['name'];
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="back-link">← Hevoset</a>
  <h1>Kuvat</h1>
</div>

<div class="horse-ctx-banner">
  <span class="hcb-name">📷 <?= e($horse['name']) ?></span>
  <span class="hcb-meta"><?= count($photos) ?> / <?= MAX_PHOTOS_PER_HORSE ?> kuvaa</span>
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="hcb-back">← Hevoslistaan</a>
</div>

<div class="admin-body">
<?php if ($error):   ?><p class="flash-err"><?= e($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="flash-ok"><?= e($success) ?></p><?php endif; ?>

<?php $pct = count($photos) / max(1, MAX_PHOTOS_PER_HORSE) * 100; ?>
<div class="photo-upload-limit">
  <span><?= count($photos) ?>/<?= MAX_PHOTOS_PER_HORSE ?> kuvaa</span>
  <div class="photo-limit-track">
    <div class="photo-limit-fill <?= $pct >= 100 ? 'full' : '' ?>" style="width:<?= min(100, $pct) ?>%"></div>
  </div>
</div>

<?php if ($photos): ?>
<div class="admin-photo-grid">
  <?php foreach ($photos as $idx => $photo): ?>
    <div class="admin-photo-card">
      <div class="admin-photo-thumb">
        <img src="<?= e(UPLOADS_URL . $photo['filename']) ?>" alt="<?= e($photo['original_name']) ?>">
        <span class="photo-order-badge"><?= (int)$photo['sort_order'] ?></span>
        <?php if ($idx === 0): ?><span class="photo-profile-badge">Profiili</span><?php endif; ?>
        <form class="photo-delete-form" method="post" action="<?= e(SITE_URL) ?>/admin/photo_delete.php">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="photo_id"  value="<?= (int)$photo['id'] ?>">
          <input type="hidden" name="horse_id"  value="<?= (int)$horse_id ?>">
          <button type="submit" class="photo-delete-btn"
                  onclick="return confirm('Poistetaanko kuva?')">×</button>
        </form>
      </div>
      <form class="photo-meta-form" method="post" action="<?= e(SITE_URL) ?>/admin/photo_update.php">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="photo_id"  value="<?= (int)$photo['id'] ?>">
        <input type="hidden" name="horse_id"  value="<?= (int)$horse_id ?>">
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
<?php else: ?>
  <p style="color:var(--color-text-muted);margin:1rem 0">Ei kuvia vielä.</p>
<?php endif; ?>

<?php if (count($photos) < MAX_PHOTOS_PER_HORSE): ?>
<div class="admin-card" style="margin-top:1.5rem;max-width:480px">
  <h2>Lataa uusi kuva</h2>
  <form method="post" enctype="multipart/form-data" action="">
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
    <div class="form-group">
      <label for="photo">Kuvatiedosto (JPEG, PNG, GIF, WebP — max 5 Mt)</label>
      <input type="file" id="photo" name="photo" accept="image/*" required>
    </div>
    <div class="form-group">
      <label for="title">Otsikko <span style="color:var(--color-text-muted);font-weight:normal">(valinnainen)</span></label>
      <input type="text" id="title" name="title" maxlength="255" class="form-control">
    </div>
    <div class="form-group">
      <label for="caption">Kuvateksti <span style="color:var(--color-text-muted);font-weight:normal">(valinnainen)</span></label>
      <textarea id="caption" name="caption" rows="2" class="form-control"></textarea>
    </div>
    <button type="submit" class="btn">Lataa kuva</button>
  </form>
</div>
<?php else: ?>
  <p class="flash-err" style="max-width:480px">Hevosella on jo <?= MAX_PHOTOS_PER_HORSE ?> kuvaa. Poista vanha kuva ennen uuden lataamista.</p>
<?php endif; ?>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
