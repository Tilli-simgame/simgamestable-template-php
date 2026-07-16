<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

$db = getDB();
$users = $db->query(
    'SELECT id, username, role, is_active FROM admin_users ORDER BY role, username'
)->fetchAll();

$flash = '';
if (isset($_GET['updated']))     $flash = '<p class="flash-ok">Muutokset tallennettu.</p>';
if (isset($_GET['deleted']))     $flash = '<p class="flash-ok">Käyttäjä poistettu.</p>';
if (isset($_GET['deactivated'])) $flash = '<p class="flash-ok">Käyttäjä deaktivoitu.</p>';
if (isset($_GET['activated']))   $flash = '<p class="flash-ok">Käyttäjä aktivoitu.</p>';

$errFlash = '';
if (isset($_GET['err'])) {
    if ($_GET['err'] === 'lastadmin') {
        $errFlash = '<div class="flash-err"><p>Viimeistä aktiivista admin-tunnusta ei voi poistaa tai deaktivoida.</p></div>';
    } elseif ($_GET['err'] === 'self') {
        $errFlash = '<div class="flash-err"><p>Et voi poistaa tai deaktivoida omaa tunnustasi.</p></div>';
    }
}

$pageTitle = 'Käyttäjähallinta';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Käyttäjähallinta</h1>
  <div class="page-actions">
    <a href="<?= e(SITE_URL) ?>/admin/user_add.php" class="btn">+ Lisää käyttäjä</a>
  </div>
</div>
<div class="admin-body">
<?= $flash ?>
<?= $errFlash ?>
<?php if (empty($users)): ?>
  <p>Ei käyttäjiä.</p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>Käyttäjänimi</th>
      <th>Rooli</th>
      <th>Tila</th>
      <th>Toiminnot</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e($u['username']) ?></td>
      <td><?= e($u['role']) ?></td>
      <td><?= (int)$u['is_active'] === 1 ? 'Aktiivinen' : 'Deaktivoitu' ?></td>
      <td>
        <a href="<?= e(SITE_URL) ?>/admin/user_edit.php?id=<?= (int)$u['id'] ?>" class="btn-sm btn-edit">✏️ Muokkaa</a>
        <form method="post" action="<?= e(SITE_URL) ?>/admin/user_reset_password.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button type="submit" class="btn-sm">🔑 Nollaa salasana</button>
        </form>
        <form method="post" action="<?= e(SITE_URL) ?>/admin/user_toggle_active.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button type="submit" class="btn-sm"><?= (int)$u['is_active'] === 1 ? 'Deaktivoi' : 'Aktivoi' ?></button>
        </form>
        <form method="post" action="<?= e(SITE_URL) ?>/admin/user_delete.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button type="submit" class="btn-sm btn-danger"
                  onclick="return confirm('Poistetaanko käyttäjä <?= e(addslashes($u['username'])) ?>?')">🗑 Poista</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
