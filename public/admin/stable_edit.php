<?php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/includes/stable_form.php';
requireRole('admin', 'mod');

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM stables WHERE id = :id');
$stmt->execute([':id' => $id]);
$stable = $stmt->fetch();
if (!$stable) {
    redirect(SITE_URL . '/admin/stables.php');
}
$categories = $db->query('SELECT DISTINCT category FROM stables WHERE category IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);

$f = array_map(fn($v) => $v ?? '', $stable);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        [$f, $errors] = stableFormRead();
        if (empty($errors)) {
            // Slug päivittyy vain jos nimi muuttuu
            $slug = $f['name'] === $stable['name'] ? $stable['slug'] : stableUniqueSlug($db, $f['name'], $id);
            $upd = $db->prepare(
                'UPDATE stables SET name = :name, slug = :slug, stable_number = :stable_number, category = :category,
                        description = :description, layout_theme = :layout_theme, color_theme = :color_theme,
                        sort_order = :sort_order
                 WHERE id = :id'
            );
            $upd->execute(stableFormParams($f) + [':slug' => $slug, ':id' => $id]);
            redirect(SITE_URL . '/admin/stables.php?updated=1');
        }
    }
}

$pageTitle = 'Muokkaa tallia';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/stables.php" class="back-link">← Tallit</a>
  <h1>Muokkaa tallia: <?= e($stable['name']) ?></h1>
  <div class="page-actions">
    <a href="<?= e(stableUrl($stable)) ?>" class="btn-ghost" target="_blank">🔗 Näytä tallin sivu</a>
  </div>
</div>
<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $e_msg): ?><li><?= e($e_msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" action="">
  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
  <?php renderStableFormFields($f, $categories); ?>
  <div style="margin-top:0.25rem; margin-bottom:1.5rem">
    <button type="submit" class="btn">Tallenna muutokset</button>
  </div>
</form>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
