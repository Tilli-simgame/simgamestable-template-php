<?php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/includes/horse_filters.php';
requireRole('admin', 'mod');

$db = getDB();
$horses = $db->query(
    'SELECT h.id, h.name, h.call_name, h.slug, h.gender, h.birth_date, h.vh_id, h.stable_id,
            b.name AS breed_name, s.name AS stable_name,
            GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR \'|\') AS disciplines
     FROM horses h
     LEFT JOIN breeds b ON b.id = h.breed_id
     LEFT JOIN stables s ON s.id = h.stable_id
     LEFT JOIN horse_disciplines hd ON hd.horse_id = h.id
     LEFT JOIN disciplines d ON d.id = hd.discipline_id
     WHERE h.is_deleted = 0 AND h.ancestor = 0 AND h.evm = 0
     GROUP BY h.id
     ORDER BY h.name ASC'
)->fetchAll();
foreach ($horses as &$horse) {
    $horse['discipline_list'] = $horse['disciplines'] ? explode('|', $horse['disciplines']) : [];
}
unset($horse);
$stables = $db->query('SELECT id, name FROM stables ORDER BY sort_order, name')->fetchAll();
$filterOpts = horseFilterOptions($horses, $stables);

$flash = '';
if (isset($_GET['added']))   $flash = '<p class="flash-ok">Hevonen lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Muutokset tallennettu.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Hevonen poistettu.</p>';

$gridCols = '2fr 1.4fr 90px 80px 130px 28px';
$pageTitle = 'Hevoset';
require __DIR__ . '/includes/admin_header.php';
?>
<style>
  @media (max-width: 760px) {
    .hl-col-vh, .hl-col-birth { display: none; }
    #horse-list .compact-list-header, #horse-list .compact-list-row { grid-template-columns: 2fr 1.2fr 70px 28px !important; }
  }
</style>
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

<?php renderHorseFilterToolbar($filterOpts); ?>

<div class="compact-list" id="horse-list" data-hf-list>
  <div class="compact-list-header" style="grid-template-columns:<?= $gridCols ?>">
    <div><button type="button" class="hl-sort" data-sort="name">Nimi</button> / <button type="button" class="hl-sort" data-sort="breed">Rotu</button></div>
    <div><button type="button" class="hl-sort" data-sort="disc">Painotus</button></div>
    <div><button type="button" class="hl-sort" data-sort="gender">Sukupuoli</button></div>
    <div class="hl-col-birth"><button type="button" class="hl-sort" data-sort="birth" data-sort-default="desc">Syntymä</button></div>
    <div class="hl-col-vh"><button type="button" class="hl-sort" data-sort="vh">VH-tunnus</button></div>
    <div></div>
  </div>
  <?php foreach ($horses as $horse):
    $gClass = match(mb_strtolower($horse['gender'])) { 'ori' => 'gbadge-ori', 'tamma' => 'gbadge-tamma', default => 'gbadge-ruuna' };
  ?>
  <div class="compact-list-row" style="grid-template-columns:<?= $gridCols ?>"
       <?= horseFilterRowAttrs($horse, ['birth' => $horse['birth_date'], 'vh' => $horse['vh_id']]) ?>
       onclick="adminToggleExpand(<?= (int)$horse['id'] ?>)">
    <div>
      <div class="cl-name"><?= e($horse['name']) ?></div>
      <div class="cl-meta"><?= e(implode(' · ', array_filter([$horse['breed_name'], $horse['stable_name']]))) ?></div>
    </div>
    <div class="hl-tags">
      <?php foreach ($horse['discipline_list'] as $dn): ?><span class="hl-tag"><?= e($dn) ?></span><?php endforeach; ?>
      <?php if (!$horse['discipline_list']): ?><span class="cl-meta">—</span><?php endif; ?>
    </div>
    <div><span class="gbadge <?= $gClass ?>"><?= e($horse['gender']) ?></span></div>
    <div class="cl-meta hl-col-birth"><?= $horse['birth_date'] ? formatDate($horse['birth_date']) : '—' ?></div>
    <div class="cl-mono hl-col-vh"><?= e($horse['vh_id'] ?? '') ?></div>
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
  <div class="hl-empty">Ei hakuehtoja vastaavia hevosia.</div>
</div>
<script src="<?= e(SITE_URL) ?>/assets/js/admin-horse-filter.js"></script>
<?php endif; ?>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
