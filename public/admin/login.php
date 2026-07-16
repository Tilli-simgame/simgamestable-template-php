<?php
require_once __DIR__ . '/../src/includes/db.php';

// Ohjaa jo kirjautunut suoraan dashboardiin
if (isLoggedIn()) {
    redirect(SITE_URL . '/admin/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-tarkistus käyttäen helper-funktiota
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Virheellinen pyyntö.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $db = getDB();
        $stmt = $db->prepare('SELECT id, username, password, role, is_active FROM admin_users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password']) && (int)$row['is_active'] === 1) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']       = $row['id'];
            $_SESSION['admin_username'] = $row['username'];
            $_SESSION['admin_role']     = $row['role'];
            redirect(SITE_URL . '/admin/index.php');
        } else {
            // Sama geneerinen virhe väärälle salasanalle ja deaktivoidulle tilille (information disclosure -esto)
            $error = 'Väärä käyttäjätunnus tai salasana.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kirjaudu sisään — <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(SITE_URL) ?>/assets/css/style.css">
  <style>
    .login-wrap { max-width: 380px; margin: 5rem auto; background: #fff; padding: 2rem; border: 1px solid #e0d5c5; border-radius: 6px; }
    .login-wrap h1 { font-size: 1.3rem; margin-bottom: 1.5rem; color: #3d2b1f; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: 0.85rem; margin-bottom: 0.25rem; font-weight: bold; }
    .form-group input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
    .error { background: #fde8e8; color: #c00; padding: 0.5rem 0.75rem; border-radius: 4px; margin-bottom: 1rem; font-size: 0.9rem; }
  </style>
</head>
<body>
<div class="login-wrap">
  <h1><?= e(SITE_NAME) ?> — Admin</h1>
  <?php if ($error): ?>
    <p class="error"><?= e($error) ?></p>
  <?php endif; ?>
  <form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
    <div class="form-group">
      <label for="username">Käyttäjätunnus</label>
      <input type="text" id="username" name="username" autocomplete="username" required>
    </div>
    <div class="form-group">
      <label for="password">Salasana</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn" style="width:100%">Kirjaudu sisään</button>
  </form>
</div>
</body>
</html>
