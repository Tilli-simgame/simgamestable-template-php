<?php
require_once __DIR__ . '/../src/includes/db.php';
requireLogin();

$db = getDB();
$horses = $db->query(
    'SELECT h.id, h.name, h.slug, h.gender, h.birth_date, h.vh_id,
            b.name AS breed_name
     FROM horses h
     LEFT JOIN breeds b ON b.id = h.breed_id
     WHERE h.is_deleted = 0 AND h.ancestor = 0 AND h.evm = 0
     ORDER BY h.name ASC'
)->fetchAll();

$flash = '';
if (isset($_GET['added']))   $flash = '<p class="flash-ok">Hevonen lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Muutokset tallennettu.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Hevonen poistettu.</p>';

$pageTitle = 'Hevoset';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Hevoset</h1>
  <div class="page-actions">
    <a href="<?= e(SITE_URL) ?>/admin/horse_import_vrl.php" class="btn-ghost">📥 Tuo VRL:stä</a>
    <a href="<?= e(SITE_URL) ?>/admin/horse_add.php" class="btn">+ Lisää hevonen</a>
  </div>
</div>
<div class="admin-body">
<?= $flash ?>
<?php if (empty($horses)): ?>
  <p>Ei hevosia. <a href="<?= e(SITE_URL) ?>/admin/horse_add.php">Lisää ensimmäinen hevonen.</a></p>
<?php else: ?>
<div class="compact-list">
  <div class="compact-list-header" style="grid-template-columns:2fr 1.2fr 80px 140px 28px">
    <div>Nimi / Rotu</div>
    <div>Sukupuoli</div>
    <div>Syntymä</div>
    <div>VH-tunnus</div>
    <div></div>
  </div>
  <?php foreach ($horses as $horse):
    $gClass = match(mb_strtolower($horse['gender'])) { 'ori' => 'gbadge-ori', 'tamma' => 'gbadge-tamma', default => 'gbadge-ruuna' };
  ?>
  <div class="compact-list-row" style="grid-template-columns:2fr 1.2fr 80px 140px 28px"
       onclick="adminToggleExpand(<?= (int)$horse['id'] ?>)">
    <div>
      <div class="cl-name"><?= e($horse['name']) ?></div>
      <div class="cl-meta"><?= e($horse['breed_name'] ?? '') ?></div>
    </div>
    <div><span class="gbadge <?= $gClass ?>"><?= e($horse['gender']) ?></span></div>
    <div class="cl-meta"><?= $horse['birth_date'] ? formatDate($horse['birth_date']) : '—' ?></div>
    <div class="cl-mono"><?= e($horse['vh_id'] ?? '') ?></div>
    <div>
      <button class="cl-expand-btn" id="cl-btn-<?= (int)$horse['id'] ?>"
              onclick="event.stopPropagation();adminToggleExpand(<?= (int)$horse['id'] ?>)">▸</button>
    </div>
  </div>
  <div class="cl-expanded" id="cl-exp-<?= (int)$horse['id'] ?>">
    <div class="cl-expanded-actions">
      <a href="<?= e(SITE_URL) ?>/admin/horse_edit.php?id=<?= (int)$horse['id'] ?>" class="btn-sm btn-edit">✏️ Muokkaa</a>
      <a href="<?= e(horseUrl($horse)) ?>" class="btn-sm btn-view" target="_blank">🔗 Näytä</a>
      <a href="<?= e(SITE_URL) ?>/admin/photos.php?horse_id=<?= (int)$horse['id'] ?>" class="btn-sm btn-photos">📷 Kuvat</a>
      <a href="<?= e(SITE_URL) ?>/admin/foals.php?horse_id=<?= (int)$horse['id'] ?>" class="btn-sm">🌱 Kasvatus</a>
      <a href="<?= e(SITE_URL) ?>/admin/competitions.php?horse_id=<?= (int)$horse['id'] ?>" class="btn-sm">🏆 Kilpailut</a>
      <a href="<?= e(SITE_URL) ?>/admin/showrecords.php?horse_id=<?= (int)$horse['id'] ?>" class="btn-sm">🎀 Näyttelyt</a>
      <form method="post" action="<?= e(SITE_URL) ?>/admin/horse_delete.php" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$horse['id'] ?>">
        <button type="submit" class="btn-sm btn-danger"
                onclick="return confirm('Poistetaanko hevonen <?= e(addslashes($horse['name'])) ?>?')">🗑 Poista</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
