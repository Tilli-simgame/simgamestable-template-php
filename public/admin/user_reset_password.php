<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/users.php');
}
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(SITE_URL . '/admin/users.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) redirect(SITE_URL . '/admin/users.php');

$db = getDB();
$stmt = $db->prepare('SELECT id, username FROM admin_users WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) redirect(SITE_URL . '/admin/users.php');

$generatedPassword = generate_password();
$hash = password_hash($generatedPassword, PASSWORD_BCRYPT, ['cost' => 12]);
$db->prepare('UPDATE admin_users SET password = :hash WHERE id = :id')
   ->execute([':hash' => $hash, ':id' => $id]);

$pageTitle = 'Nollaa salasana';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-body">
  <div class="admin-card">
    <p class="flash-ok">Käyttäjän <?= e($row['username']) ?> uusi salasana (näytetään vain kerran): <code><?= e($generatedPassword) ?></code></p>
    <a href="<?= e(SITE_URL) ?>/admin/users.php" class="btn">Takaisin</a>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
