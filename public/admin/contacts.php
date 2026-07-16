<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$db = getDB();
$contacts = $db->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM horses WHERE owner_contact_id = c.id
              OR breeder_contact_id = c.id OR importer_contact_id = c.id) AS horse_count
     FROM contacts c
     ORDER BY c.nickname, c.stable_name'
)->fetchAll();

$flash = '';
if (isset($_GET['added']))   $flash = '<p class="flash-ok">Yhteystieto lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Muutokset tallennettu.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Yhteystieto poistettu.</p>';

$pageTitle = 'Osoitekirja';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Osoitekirja</h1>
  <div class="page-actions">
    <a href="<?= e(SITE_URL) ?>/admin/contact_add.php" class="btn">+ Lisää yhteystieto</a>
  </div>
</div>
<div class="admin-body">
<?= $flash ?>
<?php if (empty($contacts)): ?>
  <p>Ei yhteystietoja. <a href="<?= e(SITE_URL) ?>/admin/contact_add.php">Lisää ensimmäinen.</a></p>
<?php else: ?>
<div class="compact-list">
  <div class="compact-list-header" style="grid-template-columns:2fr 1.5fr 1fr 60px 28px">
    <div>Nimimerkki / Talli</div>
    <div>VRL-tunnus</div>
    <div>Maa</div>
    <div>Hevosia</div>
    <div></div>
  </div>
  <?php foreach ($contacts as $c):
    $displayName = trim(($c['nickname'] ?? '') . ($c['stable_name'] ? ' / ' . $c['stable_name'] : ''));
    if (!$displayName) $displayName = '(nimetön #' . $c['id'] . ')';
  ?>
  <div class="compact-list-row" style="grid-template-columns:2fr 1.5fr 1fr 60px 28px"
       onclick="adminToggleExpand(<?= (int)$c['id'] ?>)">
    <div>
      <div class="cl-name"><?= e($displayName) ?></div>
      <?php if ($c['email']): ?><div class="cl-meta"><?= e($c['email']) ?></div><?php endif; ?>
    </div>
    <div class="cl-mono"><?= e($c['vrl_id'] ?? '') ?></div>
    <div class="cl-meta"><?= e($c['country'] ?? '') ?></div>
    <div class="cl-meta" style="text-align:center"><?= (int)$c['horse_count'] ?></div>
    <div>
      <button class="cl-expand-btn" id="cl-btn-<?= (int)$c['id'] ?>"
              onclick="event.stopPropagation();adminToggleExpand(<?= (int)$c['id'] ?>)">▸</button>
    </div>
  </div>
  <div class="cl-expanded" id="cl-exp-<?= (int)$c['id'] ?>">
    <?php if ($c['stable_url']): ?>
      <div class="cl-meta" style="margin-bottom:0.5rem">
        <a href="<?= e($c['stable_url']) ?>" target="_blank" rel="noopener"><?= e($c['stable_url']) ?></a>
      </div>
    <?php endif; ?>
    <div class="cl-expanded-actions">
      <a href="<?= e(SITE_URL) ?>/admin/contact_edit.php?id=<?= (int)$c['id'] ?>" class="btn-sm btn-edit">✏️ Muokkaa</a>
      <?php if ((int)$c['horse_count'] === 0): ?>
        <form method="post" action="<?= e(SITE_URL) ?>/admin/contact_delete.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button type="submit" class="btn-sm btn-danger"
                  onclick="return confirm('Poistetaanko yhteystieto <?= e(addslashes($displayName)) ?>?')">🗑 Poista</button>
        </form>
      <?php else: ?>
        <span class="cl-meta" style="font-size:0.75rem">Käytössä <?= (int)$c['horse_count'] ?> hevosella — ei voi poistaa</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
