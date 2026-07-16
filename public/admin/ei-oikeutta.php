<?php
require_once __DIR__ . '/../src/includes/db.php';
requireLogin(); // mikä tahansa kirjautunut rooli saa laskeutua tänne

$pageTitle = 'Ei käyttöoikeutta';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-body">
  <div class="admin-card">
    <p class="flash-err">Sinulla ei ole käyttöoikeutta tähän sivuun.</p>
    <a class="btn" href="<?= e(SITE_URL) ?>/admin/index.php">← Takaisin</a>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
