<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$horse_id = (int)($_GET['horse_id'] ?? 0);
if ($horse_id <= 0) {
    redirect(SITE_URL . '/admin/horses.php');
}

$db = getDB();
$horseStmt = $db->prepare('SELECT id, name FROM horses WHERE id = :id AND is_deleted = 0');
$horseStmt->execute([':id' => $horse_id]);
$horse = $horseStmt->fetch();
if (!$horse) {
    redirect(SITE_URL . '/admin/horses.php');
}

$sireHorses = $db->query("SELECT id, name FROM horses WHERE is_deleted = 0 AND gender = 'ori'  ORDER BY name")->fetchAll();
$damHorses  = $db->query("SELECT id, name FROM horses WHERE is_deleted = 0 AND gender = 'tamma' ORDER BY name")->fetchAll();
$allHorses  = $db->query("SELECT id, name FROM horses WHERE is_deleted = 0 ORDER BY name")->fetchAll();
$horsesJson = json_encode(array_map(fn($h) => ['id' => $h['id'], 'label' => $h['name']], $allHorses), JSON_UNESCAPED_UNICODE);
$breeds     = $db->query('SELECT id, name, abbreviation FROM breeds ORDER BY name')->fetchAll();
$contacts   = $db->query('SELECT * FROM contacts ORDER BY nickname, stable_name')->fetchAll();

$contactsById  = array_column($contacts, null, 'id');
$contactsJson  = json_encode(array_map(fn($c) => [
    'id'          => $c['id'],
    'label'       => trim(($c['nickname'] ?? '') . ' ' . ($c['stable_name'] ?? '')),
    'nickname'    => $c['nickname']    ?? '',
    'stable_name' => $c['stable_name'] ?? '',
    'stable_url'  => $c['stable_url']  ?? '',
    'vrl_id'      => $c['vrl_id']      ?? '',
    'email'       => $c['email']       ?? '',
    'country'     => $c['country']     ?? '',
], $contacts), JSON_UNESCAPED_UNICODE);

$edit_id = (int)($_GET['edit'] ?? 0);
$errors  = [];
$flash   = '';

// Apufunktio: käsittele omistaja (olemassa oleva tai uusi kontakti)
function resolveOwnerContact(PDO $db, array $post): ?int {
    $cid = (int)($post['owner_contact_id'] ?? 0);
    if ($cid > 0) return $cid;
    $nn  = sanitize($post['owner_new_nickname']    ?? '');
    $sn  = sanitize($post['owner_new_stable_name'] ?? '');
    $vrl = sanitize($post['owner_new_vrl_id']      ?? '');
    $em  = sanitize($post['owner_new_email']        ?? '');
    $co  = sanitize($post['owner_new_country']      ?? '');
    if (!$nn && !$sn && !$em) return null;
    $stmt = $db->prepare(
        'INSERT INTO contacts (nickname, stable_name, vrl_id, email, country)
         VALUES (:nn, :sn, :vrl, :em, :co)'
    );
    $stmt->execute([':nn'=>$nn?:null,':sn'=>$sn?:null,':vrl'=>$vrl?:null,':em'=>$em?:null,':co'=>$co?:null]);
    return (int)$db->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $foal_id = (int)($_POST['foal_id'] ?? 0);

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        if ($action === 'add') {
            $foal_name     = sanitize($_POST['foal_name'] ?? '');
            $foal_horse_id = ($_POST['foal_horse_id'] ?? '') !== '' ? (int)$_POST['foal_horse_id'] : null;
            if ($foal_name === '' && $foal_horse_id === null) $errors[] = 'Varsan nimi tai linkki hevoseen on pakollinen.';
            if (empty($errors)) {
                $ownerCid = resolveOwnerContact($db, $_POST);
                $stmt = $db->prepare(
                    'INSERT INTO foals (horse_id, foal_name, breed_id, sire_id, dam_id, birth_date, gender, status, owner_contact_id, merits, foal_horse_id)
                     VALUES (:horse_id, :foal_name, :breed_id, :sire_id, :dam_id, :birth_date, :gender, :status, :owner_contact_id, :merits, :foal_horse_id)'
                );
                $stmt->execute([
                    ':horse_id'         => $horse_id,
                    ':foal_name'        => $foal_name ?: null,
                    ':breed_id'         => ($_POST['breed_id'] ?? '') !== '' ? (int)$_POST['breed_id'] : null,
                    ':sire_id'          => ($_POST['sire_id']  ?? '') !== '' ? (int)$_POST['sire_id']  : null,
                    ':dam_id'           => ($_POST['dam_id']   ?? '') !== '' ? (int)$_POST['dam_id']   : null,
                    ':birth_date'       => sanitize($_POST['birth_date'] ?? '') ?: null,
                    ':gender'           => sanitize($_POST['gender']     ?? '') ?: null,
                    ':status'           => sanitize($_POST['status']     ?? 'born'),
                    ':owner_contact_id' => $ownerCid,
                    ':merits'           => sanitize($_POST['merits']     ?? '') ?: null,
                    ':foal_horse_id'    => $foal_horse_id,
                ]);
                redirect(SITE_URL . '/admin/foals.php?horse_id=' . $horse_id . '&added=1');
            }
        } elseif ($action === 'edit' && $foal_id > 0) {
            $own = $db->prepare('SELECT id FROM foals WHERE id = :foal_id AND horse_id = :horse_id');
            $own->execute([':foal_id' => $foal_id, ':horse_id' => $horse_id]);
            if ($own->fetch()) {
                $foal_name     = sanitize($_POST['foal_name'] ?? '');
                $foal_horse_id = ($_POST['foal_horse_id'] ?? '') !== '' ? (int)$_POST['foal_horse_id'] : null;
                if ($foal_name === '' && $foal_horse_id === null) $errors[] = 'Varsan nimi tai linkki hevoseen on pakollinen.';
                if (empty($errors)) {
                    $ownerCid = resolveOwnerContact($db, $_POST);
                    $stmt = $db->prepare(
                        'UPDATE foals SET foal_name=:foal_name, breed_id=:breed_id, sire_id=:sire_id, dam_id=:dam_id,
                         birth_date=:birth_date, gender=:gender, status=:status,
                         owner_contact_id=:owner_contact_id, merits=:merits, foal_horse_id=:foal_horse_id
                         WHERE id=:foal_id'
                    );
                    $stmt->execute([
                        ':foal_name'        => $foal_name ?: null,
                        ':breed_id'         => ($_POST['breed_id'] ?? '') !== '' ? (int)$_POST['breed_id'] : null,
                        ':sire_id'          => ($_POST['sire_id']  ?? '') !== '' ? (int)$_POST['sire_id']  : null,
                        ':dam_id'           => ($_POST['dam_id']   ?? '') !== '' ? (int)$_POST['dam_id']   : null,
                        ':birth_date'       => sanitize($_POST['birth_date'] ?? '') ?: null,
                        ':gender'           => sanitize($_POST['gender']     ?? '') ?: null,
                        ':status'           => sanitize($_POST['status']     ?? 'born'),
                        ':owner_contact_id' => $ownerCid,
                        ':merits'           => sanitize($_POST['merits']     ?? '') ?: null,
                        ':foal_horse_id'    => $foal_horse_id,
                        ':foal_id'          => $foal_id,
                    ]);
                    redirect(SITE_URL . '/admin/foals.php?horse_id=' . $horse_id . '&updated=1');
                }
            }
        } elseif ($action === 'delete' && $foal_id > 0) {
            requireRole('admin', 'mod');
            $own = $db->prepare('SELECT id FROM foals WHERE id = :foal_id AND horse_id = :horse_id');
            $own->execute([':foal_id' => $foal_id, ':horse_id' => $horse_id]);
            if ($own->fetch()) {
                $delStmt = $db->prepare('UPDATE foals SET is_deleted = 1, deleted_at = NOW() WHERE id = :foal_id AND is_deleted = 0');
                $delStmt->execute([':foal_id' => $foal_id]);
                if ($delStmt->rowCount() > 0 && currentRole() === 'mod') {
                    insertPendingDeletion('foal', $foal_id, (int)$_SESSION['admin_id']);
                }
            }
            redirect(SITE_URL . '/admin/foals.php?horse_id=' . $horse_id . '&deleted=1');
        }
    }
}

$foalsStmt = $db->prepare(
    'SELECT f.*, b.abbreviation AS breed_abbr,
            s.name AS sire_name, d.name AS dam_name,
            oc.nickname AS owner_nickname, oc.stable_name AS owner_stable, oc.vrl_id AS owner_vrl,
            fh.name AS foal_horse_name
     FROM foals f
     LEFT JOIN breeds   b  ON b.id  = f.breed_id
     LEFT JOIN horses   s  ON s.id  = f.sire_id          AND s.is_deleted = 0
     LEFT JOIN horses   d  ON d.id  = f.dam_id           AND d.is_deleted = 0
     LEFT JOIN contacts oc ON oc.id = f.owner_contact_id
     LEFT JOIN horses   fh ON fh.id = f.foal_horse_id    AND fh.is_deleted = 0
     WHERE f.horse_id = :horse_id AND f.is_deleted = 0
     ORDER BY f.birth_date DESC, f.foal_name ASC'
);
$foalsStmt->execute([':horse_id' => $horse_id]);
$foals = $foalsStmt->fetchAll();

if (isset($_GET['added']))   $flash = '<p class="flash-ok">Varsamerkintä lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Varsamerkintä päivitetty.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Varsamerkintä poistettu.</p>';

$statusLabels = ['expected' => 'Odotettu', 'born' => 'Syntynyt'];

$pageTitle = 'Kasvatus';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="back-link">← Hevoset</a>
  <h1>Kasvatus</h1>
  <div class="page-actions">
    <button class="btn" onclick="adminOpenSlide('foal')">+ Lisää varsa</button>
  </div>
</div>
<div class="horse-ctx-banner">
  <span class="hcb-name">🌱 <?= e($horse['name']) ?></span>
  <span class="hcb-meta"><?= count($foals) ?> varsamerkintää</span>
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="hcb-back">← Hevoslistaan</a>
</div>
<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $emsg): ?><li><?= e($emsg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?= $flash ?>

<?php if ($foals): ?>
<div class="compact-list">
  <div class="compact-list-header" style="grid-template-columns:2fr 1fr 1fr 90px 80px 28px">
    <div>Nimi</div><div>Isä</div><div>Emä</div><div>Syntymäpäivä</div><div>Status</div><div></div>
  </div>
  <?php foreach ($foals as $fo):
    $sClass = $fo['status'] === 'expected' ? 'sbadge-expected' : 'sbadge-born';
  ?>
  <div class="compact-list-row" style="grid-template-columns:2fr 1fr 1fr 90px 80px 28px"
       onclick="adminToggleExpand('f<?= (int)$fo['id'] ?>')">
    <div class="cl-name"><?= e($fo['foal_name']) ?></div>
    <div class="cl-meta"><?= e($fo['sire_name'] ?? '—') ?></div>
    <div class="cl-meta"><?= e($fo['dam_name']  ?? '—') ?></div>
    <div class="cl-mono"><?= $fo['birth_date'] ? date('d.m.Y', strtotime($fo['birth_date'])) : '—' ?></div>
    <div><span class="sbadge <?= $sClass ?>"><?= e($statusLabels[$fo['status']] ?? $fo['status']) ?></span></div>
    <div>
      <button class="cl-expand-btn" id="cl-btn-f<?= (int)$fo['id'] ?>"
              onclick="event.stopPropagation();adminToggleExpand('f<?= (int)$fo['id'] ?>')">▸</button>
    </div>
  </div>
  <div class="cl-expanded" id="cl-exp-f<?= (int)$fo['id'] ?>">
    <div class="cl-expanded-actions">
      <button class="btn-sm btn-edit" onclick="openEditFoal(<?= (int)$fo['id'] ?>, <?= htmlspecialchars(json_encode($fo), ENT_QUOTES) ?>)">✏️ Muokkaa</button>
      <form method="post" action="?horse_id=<?= $horse_id ?>" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action"  value="delete">
        <input type="hidden" name="foal_id" value="<?= (int)$fo['id'] ?>">
        <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Poistetaanko varsamerkintä?')">🗑 Poista</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <p style="color:var(--color-text-muted);margin:1rem 0">Ei varsamerkintöjä. Lisää ensimmäinen varsa painamalla "+ Lisää varsa".</p>
<?php endif; ?>
</div><!-- /.admin-body -->

<!-- ── SLIDE PANEL: Lisää/muokkaa varsa ── -->
<div class="admin-slide-overlay" id="slide-overlay-foal" onclick="adminCloseSlide('foal')"></div>
<div class="admin-slide-panel" id="slide-panel-foal">
  <div class="admin-slide-header">
    <h2 id="slide-foal-title">Lisää varsamerkintä</h2>
    <button class="admin-slide-close" onclick="adminCloseSlide('foal')">×</button>
  </div>
  <form method="post" action="?horse_id=<?= $horse_id ?>">
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
    <input type="hidden" name="action"  id="slide-action"  value="add">
    <input type="hidden" name="foal_id" id="slide-foal-id" value="">
    <div class="admin-slide-body">
      <div class="form-row">
        <div class="form-group">
          <label for="foal_name">Varsan nimi</label>
          <input type="text" id="foal_name" name="foal_name">
        </div>
        <div class="form-group">
          <label for="birth_date">Syntymäpäivä</label>
          <input type="date" id="birth_date" name="birth_date">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="breed_id">Rotu</label>
          <select id="breed_id" name="breed_id">
            <option value="">— valitse —</option>
            <?php foreach ($breeds as $br): ?>
              <option value="<?= (int)$br['id'] ?>"><?= e($br['name']) ?><?= $br['abbreviation'] ? ' (' . e($br['abbreviation']) . ')' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="gender">Sukupuoli</label>
          <select id="gender" name="gender">
            <option value="">— valitse —</option>
            <?php foreach (['ori', 'tamma', 'ruuna', 'tuntematon'] as $g): ?>
              <option value="<?= e($g) ?>"><?= ucfirst(e($g)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="sire_id_text">Isä (ori)</label>
          <div class="ac-wrap"
               data-items='<?= htmlspecialchars($horsesJson, ENT_QUOTES) ?>'
               data-input-id="sire_id"
               data-hidden-name="sire_id"
               data-current-id=""
               data-current-label=""
               data-placeholder="Hae ori..."></div>
        </div>
        <div class="form-group">
          <label for="dam_id_text">Emä (tamma)</label>
          <div class="ac-wrap"
               data-items='<?= htmlspecialchars($horsesJson, ENT_QUOTES) ?>'
               data-input-id="dam_id"
               data-hidden-name="dam_id"
               data-current-id=""
               data-current-label=""
               data-placeholder="Hae tamma..."></div>
        </div>
      </div>

      <!-- Omistaja -->
      <fieldset style="border:1px solid var(--color-border,#e0d5c5);border-radius:8px;padding:1rem;margin-bottom:0">
        <legend style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-muted,#6b5e52);padding:0 0.4rem">Omistaja</legend>
        <div class="form-group" style="margin-bottom:0.75rem">
          <label>Hae osoitekirjasta</label>
          <div class="ac-wrap contact-ac"
               data-items='<?= htmlspecialchars($contactsJson, ENT_QUOTES) ?>'
               data-input-id="owner_contact"
               data-hidden-name="owner_contact_id"
               data-current-id=""
               data-current-label=""
               data-preview-target="owner-preview"
               data-new-target="owner-new"
               data-placeholder="Hae nimimerkillä tai tallin nimellä..."></div>
        </div>
        <div id="owner-preview" class="contact-preview" style="display:none"></div>
        <div style="margin:0.5rem 0;font-size:0.78rem;color:var(--color-text-muted,#6b5e52)">— tai —</div>
        <button type="button" class="btn-sm" onclick="toggleContactNew('owner')">+ Luo uusi yhteystieto osoitekirjaan</button>
        <div id="owner-new" style="display:none;margin-top:0.75rem">
          <div class="form-row">
            <div class="form-group">
              <label for="owner_new_nickname">Nimimerkki</label>
              <input type="text" id="owner_new_nickname" name="owner_new_nickname">
            </div>
            <div class="form-group">
              <label for="owner_new_stable_name">Tallin nimi</label>
              <input type="text" id="owner_new_stable_name" name="owner_new_stable_name">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="owner_new_vrl_id">VRL-tunnus</label>
              <input type="text" id="owner_new_vrl_id" name="owner_new_vrl_id" placeholder="VRL-XXXXX">
            </div>
            <div class="form-group">
              <label for="owner_new_email">Sähköposti</label>
              <input type="email" id="owner_new_email" name="owner_new_email">
            </div>
          </div>
          <div class="form-group">
            <label for="owner_new_country">Maa</label>
            <input type="text" id="owner_new_country" name="owner_new_country">
          </div>
        </div>
      </fieldset>

      <div class="form-group" style="margin-top:1rem">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach ($statusLabels as $val => $label): ?>
            <option value="<?= e($val) ?>"><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="foal_horse_id_text">Varsan sivut</label>
        <div class="ac-wrap"
             data-items='<?= htmlspecialchars($horsesJson, ENT_QUOTES) ?>'
             data-input-id="foal_horse_id"
             data-hidden-name="foal_horse_id"
             data-current-id=""
             data-current-label=""
             data-placeholder="Hae varsa..."></div>
      </div>
      <div class="form-group">
        <label for="merits">Meriitit</label>
        <textarea id="merits" name="merits" rows="3" placeholder="Esim. kilpailutulokset, tittelit..."></textarea>
      </div>
    </div><!-- /.admin-slide-body -->
    <div class="admin-slide-footer">
      <button type="submit" class="btn" id="slide-submit-btn">Lisää varsamerkintä</button>
      <button type="button" class="btn-ghost" onclick="adminCloseSlide('foal')">Peruuta</button>
    </div>
  </form>
</div>

<script>
function setAcValue(inputId, id, label) {
  var textEl   = document.getElementById(inputId + '_text');
  var hiddenEl = document.getElementById(inputId);
  if (textEl)   textEl.value   = label || '';
  if (hiddenEl) hiddenEl.value = id    || '';
}

function openEditFoal(id, data) {
  document.getElementById('slide-foal-title').textContent = 'Muokkaa varsamerkintää';
  document.getElementById('slide-action').value  = 'edit';
  document.getElementById('slide-foal-id').value = id;
  document.getElementById('foal_name').value     = data.foal_name  || '';
  document.getElementById('birth_date').value    = data.birth_date || '';
  document.getElementById('breed_id').value      = data.breed_id   || '';
  document.getElementById('gender').value        = data.gender     || '';
  document.getElementById('status').value        = data.status     || 'born';
  document.getElementById('merits').value        = data.merits     || '';
  setAcValue('sire_id',       data.sire_id,       data.sire_name       || '');
  setAcValue('dam_id',        data.dam_id,        data.dam_name        || '');
  setAcValue('foal_horse_id', data.foal_horse_id, data.foal_horse_name || '');
  document.getElementById('slide-submit-btn').textContent = 'Tallenna muutokset';
  adminOpenSlide('foal');
}
</script>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
