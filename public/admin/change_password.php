<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod', 'author'); // mikä tahansa autentikoitu rooli, D-08

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';

        $db = getDB();
        $stmt = $db->prepare('SELECT password FROM admin_users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password'])) {
            $errors[] = 'Nykyinen salasana on väärä.';
        }

        $val = validate_string($new, 8, 255); // D-10: min 8 merkkiä
        if (!$val['valid']) {
            $errors[] = $val['error'];
        } elseif ($new !== $confirm) {
            $errors[] = 'Uudet salasanat eivät täsmää.';
        }

        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('UPDATE admin_users SET password = :hash WHERE id = :id')
               ->execute([':hash' => $hash, ':id' => $_SESSION['admin_id']]);
            session_regenerate_id(true); // D-09: estä session fixation, pysy kirjautuneena
            $success = true;
        }
    }
}

$pageTitle = 'Vaihda salasana';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-body">
  <div class="admin-card" style="max-width:420px">
    <h2>Vaihda salasana</h2>
    <?php if ($success): ?>
      <p class="flash-ok">Salasana vaihdettu onnistuneesti.</p>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="flash-err"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
      <div class="form-group">
        <label for="current_password">Nykyinen salasana</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
      </div>
      <div class="form-group">
        <label for="new_password">Uusi salasana</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
      </div>
      <div class="form-group">
        <label for="new_password_confirm">Vahvista uusi salasana</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" minlength="8" required>
      </div>
      <button type="submit" class="btn">Vaihda salasana</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
