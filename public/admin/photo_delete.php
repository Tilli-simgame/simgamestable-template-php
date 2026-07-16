<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/horses.php');
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(SITE_URL . '/admin/horses.php');
}

$photo_id = (int)($_POST['photo_id'] ?? 0);
$horse_id = (int)($_POST['horse_id'] ?? 0);

if ($photo_id <= 0) {
    redirect(SITE_URL . '/admin/horses.php');
}

$db = getDB();
$stmt = $db->prepare('SELECT id, filename, horse_id FROM horse_photos WHERE id = :photo_id');
$stmt->execute([':photo_id' => $photo_id]);
$photo = $stmt->fetch();

if (!$photo) {
    redirect(SITE_URL . '/admin/horses.php');
}

// Omistajuustarkistus — varmista että kuva kuuluu oikealle hevoselle
if ((int)$photo['horse_id'] !== $horse_id) {
    redirect(SITE_URL . '/admin/horses.php');
}

// Poista tiedosto levyltä
$filepath = UPLOADS_DIR . $photo['filename'];
if (file_exists($filepath)) {
    unlink($filepath);
}

// Poista DB-rivi
$del = $db->prepare('DELETE FROM horse_photos WHERE id = :photo_id');
$del->execute([':photo_id' => $photo_id]);

$redirect = $_POST['redirect'] ?? '';
if ($redirect === 'kuvat_all') {
    redirect(SITE_URL . '/admin/kuvat_all.php?deleted=1');
} else {
    redirect(SITE_URL . '/admin/photos.php?horse_id=' . $horse_id . '&deleted=1');
}
