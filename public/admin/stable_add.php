<?php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/includes/stable_form.php';
requireRole('admin', 'mod');

$db = getDB();
$categories = $db->query('SELECT DISTINCT category FROM stables WHERE category IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);

$f = stableFormDefaults();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        [$f, $errors] = stableFormRead();
        if (empty($errors)) {
            $stmt = $db->prepare(
                'INSERT INTO stables (name, slug, stable_number, category, description, layout_theme, color_theme, sort_order)
                 VALUES (:name, :slug, :stable_number, :category, :description, :layout_theme, :color_theme, :sort_order)'
            );
            $stmt->execute(stableFormParams($f) + [':slug' => stableUniqueSlug($db, $f['name'])]);
            redirect(SITE_URL . '/admin/stables.php?added=1');
        }
    }
}

$pageTitle = 'Lisää talli';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/stables.php" class="back-link">← Tallit</a>
  <h1>Lisää talli</h1>
</div>
<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $e_msg): ?><li><?= e($e_msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" action="">
  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
  <?php renderStableFormFields($f, $categories); ?>
  <div style="margin-top:0.25rem; margin-bottom:1.5rem">
    <button type="submit" class="btn">Lisää talli</button>
  </div>
</form>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
