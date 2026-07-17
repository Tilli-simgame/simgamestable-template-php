<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect(SITE_URL . '/admin/kasvatus_all.php');
}

$db = getDB();
$foalStmt = $db->prepare(
    'SELECT f.*, b.name AS breed_name, b.abbreviation AS breed_abbr,
            s.name AS sire_name, d.name AS dam_name,
            fh.name AS foal_horse_name
     FROM foals f
     LEFT JOIN breeds b  ON b.id  = f.breed_id
     LEFT JOIN horses s  ON s.id  = f.sire_id AND s.is_deleted = 0
     LEFT JOIN horses d  ON d.id  = f.dam_id  AND d.is_deleted = 0
     LEFT JOIN horses fh ON fh.id = f.foal_horse_id AND fh.is_deleted = 0
     WHERE f.id = :id AND f.is_deleted = 0'
);
$foalStmt->execute([':id' => $id]);
$foal = $foalStmt->fetch();
if (!$foal) {
    redirect(SITE_URL . '/admin/kasvatus_all.php');
}

$allHorses = $db->query("SELECT id, name FROM horses WHERE is_deleted = 0 ORDER BY name")->fetchAll();
$breeds    = $db->query('SELECT id, name, abbreviation FROM breeds ORDER BY name')->fetchAll();
$contacts  = $db->query('SELECT * FROM contacts ORDER BY nickname, stable_name')->fetchAll();

$horsesJson   = json_encode(array_map(fn($h) => ['id' => $h['id'], 'label' => $h['name']], $allHorses), JSON_UNESCAPED_UNICODE);
$breedsJson   = json_encode(array_map(fn($b) => ['id' => $b['id'], 'label' => $b['name'] . ($b['abbreviation'] ? ' (' . $b['abbreviation'] . ')' : '')], $breeds), JSON_UNESCAPED_UNICODE);
$contactsJson = json_encode(array_map(fn($c) => [
    'id'          => $c['id'],
    'label'       => trim(($c['nickname'] ?? '') . ($c['stable_name'] ? ' / ' . $c['stable_name'] : '')),
    'nickname'    => $c['nickname']    ?? '',
    'stable_name' => $c['stable_name'] ?? '',
    'stable_url'  => $c['stable_url']  ?? '',
    'vrl_id'      => $c['vrl_id']      ?? '',
    'email'       => $c['email']       ?? '',
    'country'     => $c['country']     ?? '',
], $contacts), JSON_UNESCAPED_UNICODE);

// Prepopulate owner label for ac-wrap
$ownerLabel = '';
if ($foal['owner_contact_id']) {
    $oc = $db->prepare('SELECT nickname, stable_name, vrl_id FROM contacts WHERE id = :id');
    $oc->execute([':id' => $foal['owner_contact_id']]);
    $oc = $oc->fetch();
    if ($oc) {
        $ownerLabel = trim(($oc['nickname'] ?? '') . ($oc['stable_name'] ? ' / ' . $oc['stable_name'] : ''));
    }
}

$errors = [];
$f = $foal; // prefill from DB
$statusLabels = ['expected' => 'Odotettu', 'born' => 'Syntynyt'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        $foal_name     = sanitize($_POST['foal_name'] ?? '');
        $breed_id      = ($_POST['breed_id']      ?? '') !== '' ? (int)$_POST['breed_id']      : null;
        $sire_id       = ($_POST['sire_id']        ?? '') !== '' ? (int)$_POST['sire_id']        : null;
        $dam_id        = ($_POST['dam_id']         ?? '') !== '' ? (int)$_POST['dam_id']         : null;
        $foal_horse_id = ($_POST['foal_horse_id']  ?? '') !== '' ? (int)$_POST['foal_horse_id']  : null;
        $birth_date    = sanitize($_POST['birth_date'] ?? '') ?: null;
        $gender        = sanitize($_POST['gender']     ?? '') ?: null;
        $status        = sanitize($_POST['status']     ?? 'born');
        $merits        = sanitize($_POST['merits']     ?? '') ?: null;

        $f['foal_name']     = $foal_name;
        $f['breed_id']      = $breed_id;
        $f['sire_id']       = $sire_id;
        $f['dam_id']        = $dam_id;
        $f['foal_horse_id'] = $foal_horse_id;
        $f['birth_date']    = $birth_date;
        $f['gender']        = $gender;
        $f['status']        = $status;
        $f['merits']        = $merits;

        if ($foal_name === '' && $foal_horse_id === null) {
            $errors[] = 'Varsan nimi tai linkki hevoseen on pakollinen.';
        }

        if (empty($errors)) {
            $ownerCid = null;
            $cid = (int)($_POST['owner_contact_id'] ?? 0);
            if ($cid > 0) {
                $ownerCid = $cid;
            } else {
                $nn  = sanitize($_POST['owner_new_nickname']    ?? '');
                $sn  = sanitize($_POST['owner_new_stable_name'] ?? '');
                $vrl = sanitize($_POST['owner_new_vrl_id']      ?? '');
                $em  = sanitize($_POST['owner_new_email']       ?? '');
                $co  = sanitize($_POST['owner_new_country']     ?? '');
                if ($nn || $sn || $em) {
                    $cstmt = $db->prepare(
                        'INSERT INTO contacts (nickname, stable_name, vrl_id, email, country)
                         VALUES (:nn, :sn, :vrl, :em, :co)'
                    );
                    $cstmt->execute([':nn'=>$nn?:null,':sn'=>$sn?:null,':vrl'=>$vrl?:null,':em'=>$em?:null,':co'=>$co?:null]);
                    $ownerCid = (int)$db->lastInsertId();
                }
            }

            $stmt = $db->prepare(
                'UPDATE foals SET foal_name=:foal_name, breed_id=:breed_id, sire_id=:sire_id, dam_id=:dam_id,
                 birth_date=:birth_date, gender=:gender, status=:status,
                 owner_contact_id=:owner_contact_id, merits=:merits, foal_horse_id=:foal_horse_id
                 WHERE id=:id'
            );
            $stmt->execute([
                ':foal_name'        => $foal_name ?: null,
                ':breed_id'         => $breed_id,
                ':sire_id'          => $sire_id,
                ':dam_id'           => $dam_id,
                ':birth_date'       => $birth_date,
                ':gender'           => $gender,
                ':status'           => $status,
                ':owner_contact_id' => $ownerCid,
                ':merits'           => $merits,
                ':foal_horse_id'    => $foal_horse_id,
                ':id'               => $id,
            ]);
            redirect(SITE_URL . '/admin/kasvatus_all.php?updated=1');
        }
    }
}

$pageTitle = 'Muokkaa varsamerkintää';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/kasvatus_all.php" class="back-link">← Kasvatus</a>
  <h1>Muokkaa varsamerkintää</h1>
</div>
<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $e_msg): ?><li><?= e($e_msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="admin-card">
<form method="post" action="">
  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

  <div class="form-row">
    <div class="form-group">
      <label for="foal_name">Varsan nimi</label>
      <input type="text" id="foal_name" name="foal_name" value="<?= e($f['foal_name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="birth_date">Syntymäpäivä</label>
      <input type="date" id="birth_date" name="birth_date" value="<?= e($f['birth_date'] ?? '') ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="breed_id_text">Rotu</label>
      <div class="ac-wrap"
           data-items='<?= htmlspecialchars($breedsJson, ENT_QUOTES) ?>'
           data-input-id="breed_id"
           data-hidden-name="breed_id"
           data-current-id="<?= (int)($f['breed_id'] ?? 0) ?: '' ?>"
           data-current-label="<?= e(($f['breed_name'] ?? '') . ($f['breed_abbr'] ? ' (' . $f['breed_abbr'] . ')' : '')) ?>"
           data-placeholder="Hae rotua..."></div>
    </div>
    <div class="form-group">
      <label for="gender">Sukupuoli</label>
      <select id="gender" name="gender">
        <option value="">— valitse —</option>
        <?php foreach (['ori', 'tamma', 'ruuna', 'tuntematon'] as $g): ?>
          <option value="<?= e($g) ?>" <?= ($f['gender'] ?? '') === $g ? 'selected' : '' ?>><?= ucfirst(e($g)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <h3 style="margin-top:1.5rem">Sukutaulu</h3>
  <div class="form-row">
    <div class="form-group">
      <label for="sire_id_text">Isä (ori)</label>
      <div class="ac-wrap"
           data-items='<?= htmlspecialchars($horsesJson, ENT_QUOTES) ?>'
           data-input-id="sire_id"
           data-hidden-name="sire_id"
           data-current-id="<?= (int)($f['sire_id'] ?? 0) ?: '' ?>"
           data-current-label="<?= e($f['sire_name'] ?? '') ?>"
           data-placeholder="Hae ori..."></div>
    </div>
    <div class="form-group">
      <label for="dam_id_text">Emä (tamma)</label>
      <div class="ac-wrap"
           data-items='<?= htmlspecialchars($horsesJson, ENT_QUOTES) ?>'
           data-input-id="dam_id"
           data-hidden-name="dam_id"
           data-current-id="<?= (int)($f['dam_id'] ?? 0) ?: '' ?>"
           data-current-label="<?= e($f['dam_name'] ?? '') ?>"
           data-placeholder="Hae tamma..."></div>
    </div>
  </div>

  <h3 style="margin-top:1.5rem">Linkki hevoseen</h3>
  <p style="font-size:0.85rem;color:var(--color-text-muted);margin-bottom:0.75rem">
    Jos varsa on jo tallennettu hevosiin (tallin asukit tai ulkopuoliset hevoset), voit linkittää varsamerkinnän siihen tässä.
  </p>
  <div class="form-group">
    <label for="foal_horse_id_text">Varsan sivut</label>
    <div class="ac-wrap"
         data-items='<?= htmlspecialchars($horsesJson, ENT_QUOTES) ?>'
         data-input-id="foal_horse_id"
         data-hidden-name="foal_horse_id"
         data-current-id="<?= (int)($f['foal_horse_id'] ?? 0) ?: '' ?>"
         data-current-label="<?= e($f['foal_horse_name'] ?? '') ?>"
         data-placeholder="Hae hevosen nimellä..."></div>
  </div>

  <h3 style="margin-top:1.5rem">Omistaja</h3>
  <fieldset style="border:1px solid var(--color-border,#e0d5c5);border-radius:8px;padding:1rem;margin-bottom:1rem">
    <legend style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-muted,#6b5e52);padding:0 0.4rem">Omistaja</legend>
    <div class="form-group" style="margin-bottom:0.75rem">
      <label>Hae osoitekirjasta</label>
      <div class="ac-wrap contact-ac"
           data-items='<?= htmlspecialchars($contactsJson, ENT_QUOTES) ?>'
           data-input-id="owner_contact"
           data-hidden-name="owner_contact_id"
           data-current-id="<?= (int)($f['owner_contact_id'] ?? 0) ?: '' ?>"
           data-current-label="<?= e($ownerLabel) ?>"
           data-preview-target="owner-preview"
           data-new-target="owner-new"
           data-placeholder="Hae nimimerkillä tai tallin nimellä..."></div>
    </div>
    <div id="owner-preview" class="contact-preview" style="display:<?= $f['owner_contact_id'] ? 'block' : 'none' ?>">
      <?php if ($f['owner_contact_id'] && $ownerLabel): ?>
        <div class="contact-card"><strong><?= e($ownerLabel) ?></strong></div>
      <?php endif; ?>
    </div>
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

  <h3 style="margin-top:1.5rem">Lisätiedot</h3>
  <div class="form-group">
    <label for="status">Status</label>
    <select id="status" name="status">
      <?php foreach ($statusLabels as $val => $lbl): ?>
        <option value="<?= e($val) ?>" <?= ($f['status'] ?? '') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label for="merits">Meriitit</label>
    <textarea id="merits" name="merits" rows="4" placeholder="Esim. kilpailutulokset, tittelit..."><?= e($f['merits'] ?? '') ?></textarea>
  </div>

  <div style="display:flex;gap:0.75rem;align-items:center;margin-top:1rem">
    <button type="submit" class="btn">Tallenna muutokset</button>
    <a href="<?= e(SITE_URL) ?>/admin/kasvatus_all.php" class="btn-ghost">Peruuta</a>
  </div>
</form>
</div><!-- /.admin-card -->
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
