<?php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/includes/horse_filters.php';
requireRole('admin', 'mod');

$db = getDB();

$ancestors = $db->query(
    'SELECT h.id, h.name, h.call_name, h.gender, h.birth_date, h.vh_id, h.profile_url,
            b.name AS breed_name
     FROM horses h
     LEFT JOIN breeds b ON b.id = h.breed_id
     WHERE h.is_deleted = 0 AND h.ancestor = 1
     ORDER BY h.name ASC'
)->fetchAll();

$evmHorses = $db->query(
    'SELECT h.id, h.name, h.call_name, h.gender, h.color_id, h.height_cm,
            b.name AS breed_name, c.name AS color_name,
            p.name AS sire_name, m.name AS dam_name
     FROM horses h
     LEFT JOIN breeds b ON b.id = h.breed_id
     LEFT JOIN colors c ON c.id = h.color_id
     LEFT JOIN horses p ON p.id = h.sire_id
     LEFT JOIN horses m ON m.id = h.dam_id
     WHERE h.is_deleted = 0 AND h.evm = 1 AND h.ancestor = 0
     ORDER BY h.name ASC'
)->fetchAll();

// Yksi työkalurivi suodattaa molemmat listat
$filterOpts = horseFilterOptions(array_merge($ancestors, $evmHorses));

$flash = '';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Muutokset tallennettu.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Hevonen poistettu.</p>';

$csrf = e(generate_csrf_token());
$ancCols = '2fr 1.2fr 80px 140px 28px';
$evmCols = '2fr 1.2fr 1.5fr 2fr 28px';
$pageTitle = 'Sukulaiset';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Sukulaiset</h1>
  <div class="page-actions">
    <span style="font-size:0.78rem;color:var(--color-text-muted,#6b5e52)">Tallin ulkopuolella asuvat hevoset</span>
  </div>
</div>
<div class="admin-body">
<?= $flash ?>

<?php if ($ancestors || $evmHorses) renderHorseFilterToolbar($filterOpts, 'Hevosen nimi…'); ?>

<h2 style="margin:0 0 0.75rem">Toisen tallin hevoset
  <?php if ($ancestors): ?><span class="hl-count">(<span data-hf-list-count="anc"><?= count($ancestors) ?></span> / <?= count($ancestors) ?>)</span><?php endif; ?></h2>
<p style="margin:0 0 1rem;font-size:0.85rem;color:var(--color-text-muted,#6b5e52)">Sukulaiset, hevoset joilla on profiilisivu toisessa tallissa.</p>
<?php if (empty($ancestors)): ?>
  <p>Ei toisen tallin sukulaishevosia.</p>
<?php else: ?>
<div class="compact-list" style="margin-bottom:2rem" data-hf-list data-hf-key="anc">
  <div class="compact-list-header" style="grid-template-columns:<?= $ancCols ?>">
    <div><button type="button" class="hl-sort" data-sort="name">Nimi</button> / <button type="button" class="hl-sort" data-sort="breed">Rotu</button></div>
    <div><button type="button" class="hl-sort" data-sort="gender">Sukupuoli</button></div>
    <div><button type="button" class="hl-sort" data-sort="birth" data-sort-default="desc">Syntymä</button></div>
    <div><button type="button" class="hl-sort" data-sort="vh">VH-tunnus</button></div>
    <div></div>
  </div>
  <?php foreach ($ancestors as $horse):
    $gClass = match(mb_strtolower($horse['gender'])) { 'ori' => 'gbadge-ori', 'tamma' => 'gbadge-tamma', default => 'gbadge-ruuna' };
  ?>
  <div class="compact-list-row" style="grid-template-columns:<?= $ancCols ?>"
       <?= horseFilterRowAttrs($horse, ['birth' => $horse['birth_date'], 'vh' => $horse['vh_id']]) ?>
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
      <?php if (!empty($horse['profile_url'])): ?>
        <a href="<?= e($horse['profile_url']) ?>" class="btn-sm btn-view" target="_blank" rel="noopener noreferrer">🔗 Ulkopuolinen profiili</a>
      <?php endif; ?>
      <form method="post" action="<?= e(SITE_URL) ?>/admin/horse_delete.php" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="id" value="<?= (int)$horse['id'] ?>">
        <button type="submit" class="btn-sm btn-danger"
                onclick="return confirm('Poistetaanko hevonen <?= e(addslashes($horse['name'])) ?>?')">🗑 Poista</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="hl-empty">Ei hakuehtoja vastaavia hevosia.</div>
</div>
<?php endif; ?>

<h2 style="margin:0 0 0.75rem">EVM-sukupuuhevoset
  <?php if ($evmHorses): ?><span class="hl-count">(<span data-hf-list-count="evm"><?= count($evmHorses) ?></span> / <?= count($evmHorses) ?>)</span><?php endif; ?></h2>
<p style="margin:0 0 1rem;font-size:0.85rem;color:var(--color-text-muted,#6b5e52)">Hevoset, jotka eivät ole virtuaalimaailmassa — pelkkä sukutieto.</p>
<?php if (empty($evmHorses)): ?>
  <p>Ei EVM-sukupuuhevosia.</p>
<?php else: ?>
<div class="compact-list" data-hf-list data-hf-key="evm">
  <div class="compact-list-header" style="grid-template-columns:<?= $evmCols ?>">
    <div><button type="button" class="hl-sort" data-sort="name">Nimi</button> / <button type="button" class="hl-sort" data-sort="breed">Rotu</button></div>
    <div><button type="button" class="hl-sort" data-sort="gender">Sukupuoli</button></div>
    <div><button type="button" class="hl-sort" data-sort="color">Väri</button> / <button type="button" class="hl-sort" data-sort="height">Säkä</button></div>
    <div><button type="button" class="hl-sort" data-sort="parents">Isä / Emä</button></div>
    <div></div>
  </div>
  <?php foreach ($evmHorses as $horse):
    $gClass = match(mb_strtolower($horse['gender'])) { 'ori' => 'gbadge-ori', 'tamma' => 'gbadge-tamma', default => 'gbadge-ruuna' };
  ?>
  <div class="compact-list-row" style="grid-template-columns:<?= $evmCols ?>"
       <?= horseFilterRowAttrs($horse, [
           'color'   => $horse['color_name'],
           'height'  => $horse['height_cm'],
           'parents' => trim(($horse['sire_name'] ?? '') . ' / ' . ($horse['dam_name'] ?? ''), ' /'),
       ]) ?>
       onclick="adminToggleExpand(<?= (int)$horse['id'] ?>)">
    <div>
      <div class="cl-name"><?= e($horse['name']) ?></div>
      <div class="cl-meta"><?= e($horse['breed_name'] ?? '') ?></div>
    </div>
    <div><span class="gbadge <?= $gClass ?>"><?= e($horse['gender']) ?></span></div>
    <div>
      <div class="cl-meta"><?= e($horse['color_name'] ?? '—') ?></div>
      <div class="cl-meta"><?= $horse['height_cm'] ? e($horse['height_cm']) . ' cm' : '—' ?></div>
    </div>
    <div class="cl-meta">
      <?= e($horse['sire_name'] ?? '—') ?> /
      <?= e($horse['dam_name'] ?? '—') ?>
    </div>
    <div>
      <button class="cl-expand-btn" id="cl-btn-<?= (int)$horse['id'] ?>"
              onclick="event.stopPropagation();adminToggleExpand(<?= (int)$horse['id'] ?>)">▸</button>
    </div>
  </div>
  <div class="cl-expanded" id="cl-exp-<?= (int)$horse['id'] ?>">
    <div class="cl-expanded-actions">
      <a href="<?= e(SITE_URL) ?>/admin/horse_edit.php?id=<?= (int)$horse['id'] ?>" class="btn-sm btn-edit">✏️ Muokkaa</a>
      <form method="post" action="<?= e(SITE_URL) ?>/admin/horse_delete.php" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="id" value="<?= (int)$horse['id'] ?>">
        <button type="submit" class="btn-sm btn-danger"
                onclick="return confirm('Poistetaanko hevonen <?= e(addslashes($horse['name'])) ?>?')">🗑 Poista</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="hl-empty">Ei hakuehtoja vastaavia hevosia.</div>
</div>
<?php endif; ?>
<?php if ($ancestors || $evmHorses): ?>
<script src="<?= e(SITE_URL) ?>/assets/js/admin-horse-filter.js"></script>
<?php endif; ?>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
