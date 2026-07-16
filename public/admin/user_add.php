<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

$db = getDB();
$errors = [];
$success = false;
$generatedPassword = '';
$f = ['username' => '', 'role' => 'author'];

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
            $stmt = $db->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :username');
            $stmt->execute([':username' => $f['username']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Käyttäjänimi on jo käytössä.';
            }
        }

        if (empty($errors)) {
            $generatedPassword = generate_password();
            $hash = password_hash($generatedPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare(
                'INSERT INTO admin_users (username, password, role, is_active)
                 VALUES (:username, :hash, :role, 1)'
            );
            try {
                $stmt->execute([
                    ':username' => $f['username'],
                    ':hash'     => $hash,
                    ':role'     => $f['role'],
                ]);
                $success = true;
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

$pageTitle = 'Lisää käyttäjä';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/users.php" class="back-link">← Käyttäjähallinta</a>
  <h1>Lisää käyttäjä</h1>
</div>
<div class="admin-body">
<?php if ($success): ?>
  <div class="admin-card">
    <p class="flash-ok">Käyttäjä luotu. Salasana (näytetään vain kerran): <code><?= e($generatedPassword) ?></code></p>
    <a href="<?= e(SITE_URL) ?>/admin/users.php" class="btn">Takaisin</a>
  </div>
<?php else: ?>
  <?php if ($errors): ?>
    <div class="flash-err"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" style="max-width:680px">
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

    <div class="form-group">
      <label for="username">Käyttäjänimi</label>
      <input type="text" id="username" name="username" value="<?= e($f['username']) ?>" autofocus required>
    </div>
    <div class="form-group">
      <label for="role">Rooli</label>
      <select id="role" name="role">
        <option value="admin" <?= $f['role'] === 'admin' ? 'selected' : '' ?>>admin</option>
        <option value="mod" <?= $f['role'] === 'mod' ? 'selected' : '' ?>>mod</option>
        <option value="author" <?= $f['role'] === 'author' ? 'selected' : '' ?>>author</option>
      </select>
    </div>

    <div style="margin-top:1.5rem;display:flex;gap:0.75rem">
      <button type="submit" class="btn">Tallenna</button>
      <a href="<?= e(SITE_URL) ?>/admin/users.php" class="btn-ghost">Peruuta</a>
    </div>
  </form>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
