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

if ($photo_id <= 0 || $horse_id <= 0) {
    redirect(SITE_URL . '/admin/horses.php');
}

$db = getDB();
$stmt = $db->prepare('SELECT id, horse_id FROM horse_photos WHERE id = :id');
$stmt->execute([':id' => $photo_id]);
$photo = $stmt->fetch();

if (!$photo || (int)$photo['horse_id'] !== $horse_id) {
    redirect(SITE_URL . '/admin/horses.php');
}

$title   = sanitize($_POST['title']   ?? '');
$caption = sanitize($_POST['caption'] ?? '');

$upd = $db->prepare(
    'UPDATE horse_photos SET title = :title, caption = :caption WHERE id = :id'
);
$upd->execute([
    ':title'   => $title   !== '' ? $title   : null,
    ':caption' => $caption !== '' ? $caption : null,
    ':id'      => $photo_id,
]);

$redirect = $_POST['redirect'] ?? '';
if ($redirect === 'kuvat_all') {
    redirect(SITE_URL . '/admin/kuvat_all.php?updated=1');
} else {
    redirect(SITE_URL . '/admin/photos.php?horse_id=' . $horse_id . '&updated=1');
}
