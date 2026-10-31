<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$db = getDB();
$stables = $db->query(
    'SELECT s.*,
            (SELECT COUNT(*) FROM horses h
              WHERE h.stable_id = s.id AND h.is_deleted = 0) AS horse_count
     FROM stables s
     ORDER BY s.sort_order, s.name'
)->fetchAll();
$unassigned = (int)$db->query(
    'SELECT COUNT(*) FROM horses WHERE stable_id IS NULL AND is_deleted = 0 AND ancestor = 0 AND evm = 0'
)->fetchColumn();

$layouts = availableLayoutThemes();
$colors  = colorThemeDefs();
$isAdmin = currentRole() === 'admin';

$flash = '';
if (isset($_GET['added']))   $flash = '<p class="flash-ok">Talli lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Muutokset tallennettu.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Talli poistettu. Sen hevoset ovat nyt ilman tallia.</p>';

$cols = '2fr 1fr 1.2fr 1.4fr 70px 28px';
$pageTitle = 'Tallit';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Tallit</h1>
  <div class="page-actions">
    <a href="<?= e(SITE_URL) ?>/admin/stable_add.php" class="btn">+ Lisää talli</a>
  </div>
</div>
<div class="admin-body">
<?= $flash ?>
<?php if (empty($stables)): ?>
  <p>Ei talleja. <a href="<?= e(SITE_URL) ?>/admin/stable_add.php">Lisää ensimmäinen talli.</a></p>
<?php else: ?>
<div class="compact-list">
  <div class="compact-list-header" style="grid-template-columns:<?= $cols ?>">
    <div>Nimi / Kategoria</div>
    <div>Tallinumero</div>
    <div>Ulkoasu</div>
    <div>Väriteema</div>
    <div>Hevosia</div>
    <div></div>
  </div>
  <?php foreach ($stables as $s):
    $color = $s['color_theme'] && isset($colors[$s['color_theme']]) ? $colors[$s['color_theme']] : null;
  ?>
  <div class="compact-list-row" style="grid-template-columns:<?= $cols ?>"
       onclick="adminToggleExpand(<?= (int)$s['id'] ?>)">
    <div>
      <div class="cl-name"><?= e($s['name']) ?></div>
      <div class="cl-meta"><?= e($s['category'] ?? '') ?></div>
    </div>
    <div class="cl-mono"><?= e($s['stable_number'] ?? '—') ?></div>
    <div class="cl-meta"><?= $s['layout_theme'] ? e($layouts[$s['layout_theme']] ?? $s['layout_theme']) : 'Sivuston oletus' ?></div>
    <div class="cl-meta" style="display:flex;align-items:center;gap:6px">
      <?php if ($color): ?>
        <span style="display:inline-flex;border-radius:4px;overflow:hidden;border:1px solid var(--color-border,#e0d5c5)">
          <span style="width:10px;height:14px;background:<?= e($color['p']) ?>"></span>
          <span style="width:10px;height:14px;background:<?= e($color['a']) ?>"></span>
          <span style="width:10px;height:14px;background:<?= e($color['g']) ?>"></span>
        </span>
        <?= e($color['nimi']) ?>
      <?php else: ?>Sivuston oletus<?php endif; ?>
    </div>
    <div class="cl-meta"><?= (int)$s['horse_count'] ?></div>
    <div>
      <button class="cl-expand-btn" id="cl-btn-<?= (int)$s['id'] ?>"
              onclick="event.stopPropagation();adminToggleExpand(<?= (int)$s['id'] ?>)">▸</button>
    </div>
  </div>
  <div class="cl-expanded" id="cl-exp-<?= (int)$s['id'] ?>">
    <?php if ($s['description']): ?>
      <p class="cl-meta" style="margin:0 0 0.6rem;white-space:pre-line"><?= e(mb_strimwidth($s['description'], 0, 300, '…')) ?></p>
    <?php endif; ?>
    <div class="cl-expanded-actions">
      <a href="<?= e(SITE_URL) ?>/admin/stable_edit.php?id=<?= (int)$s['id'] ?>" class="btn-sm btn-edit">✏️ Muokkaa</a>
      <a href="<?= e(stableUrl($s)) ?>" class="btn-sm btn-view" target="_blank">🔗 Näytä</a>
      <a href="<?= e(SITE_URL) ?>/admin/horses.php?talli=<?= (int)$s['id'] ?>" class="btn-sm">🐎 Hevoset</a>
      <?php if ($isAdmin): ?>
      <form method="post" action="<?= e(SITE_URL) ?>/admin/stable_delete.php" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button type="submit" class="btn-sm btn-danger"
                onclick="return confirm('Poistetaanko talli <?= e(addslashes($s['name'])) ?>?<?= $s['horse_count'] ? ' Sen ' . (int)$s['horse_count'] . ' hevosta jäävät ilman tallia (hevosia ei poisteta).' : '' ?>')">🗑 Poista</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($unassigned): ?>
  <p class="cl-meta" style="margin-top:1rem">
    <a href="<?= e(SITE_URL) ?>/admin/horses.php?talli=-"><?= $unassigned ?> hevosta</a> ei ole vielä liitetty mihinkään talliin.
    Tallin voi valita hevosen muokkauslomakkeessa.
  </p>
<?php endif; ?>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
