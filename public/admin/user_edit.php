<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect(SITE_URL . '/admin/users.php');

$db = getDB();
$stmt = $db->prepare('SELECT * FROM admin_users WHERE id = :id');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();
if (!$user) redirect(SITE_URL . '/admin/users.php');

$errors = [];
$f = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        $f['username'] = sanitize($_POST['username'] ?? '');
        $f['role']     = sanitize($_POST['role'] ?? '');

        $val = validate_string($f['username'], 1, 50);
        if (!$val['valid']) {
            $errors[] = $val['error'];
        }
        if (!in_array($f['role'], ['admin', 'mod', 'author'], true)) {
            $errors[] = 'Virheellinen rooli.';
        }

        if (empty($errors)) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :username AND id != :id');
            $stmt->execute([':username' => $f['username'], ':id' => $id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Käyttäjänimi on jo käytössä.';
            }
        }

        // D-05 (USER-07): admin ei voi alentaa omaa rooliaan pois administilta
        if ($id === (int)$_SESSION['admin_id'] && $f['role'] !== 'admin') {
            $errors[] = 'Et voi muuttaa omaa rooliasi pois administilta.';
        }

        if (empty($errors)) {
            $stmt = $db->prepare('UPDATE admin_users SET username = :username, role = :role WHERE id = :id');
            try {
                $stmt->execute([
                    ':username' => $f['username'],
                    ':role'     => $f['role'],
                    ':id'       => $id,
                ]);
                redirect(SITE_URL . '/admin/users.php?updated=1');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Käyttäjänimi on jo käytössä.';
                } else {
                    error_log($e->getMessage());
                    $errors[] = 'Käyttäjän tallennus epäonnistui.';
                }
            }
        }
    }
}

$pageTitle = 'Muokkaa käyttäjää';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/users.php" class="back-link">← Käyttäjähallinta</a>
  <h1>Muokkaa käyttäjää</h1>
</div>
<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" style="max-width:680px">
  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

  <div class="form-group">
    <label for="username">Käyttäjänimi</label>
    <input type="text" id="username" name="username" value="<?= e($f['username'] ?? '') ?>" autofocus required>
  </div>
  <div class="form-group">
    <label for="role">Rooli</label>
    <select id="role" name="role">
      <option value="admin" <?= ($f['role'] ?? '') === 'admin' ? 'selected' : '' ?>>admin</option>
      <option value="mod" <?= ($f['role'] ?? '') === 'mod' ? 'selected' : '' ?>>mod</option>
      <option value="author" <?= ($f['role'] ?? '') === 'author' ? 'selected' : '' ?>>author</option>
    </select>
  </div>

  <div style="margin-top:1.5rem;display:flex;gap:0.75rem">
    <button type="submit" class="btn">Tallenna muutokset</button>
    <a href="<?= e(SITE_URL) ?>/admin/users.php" class="btn-ghost">Peruuta</a>
  </div>
</form>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
