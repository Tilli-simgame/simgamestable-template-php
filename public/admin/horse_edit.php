<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect(SITE_URL . '/admin/horses.php');
}

$db = getDB();
$horse = $db->prepare('SELECT * FROM horses WHERE id = :id AND is_deleted = 0');
$horse->execute([':id' => $id]);
$horse = $horse->fetch();
if (!$horse) {
    redirect(SITE_URL . '/admin/horses.php');
}

$allHorses   = $db->query('SELECT id, name FROM horses WHERE is_deleted = 0 ORDER BY name')->fetchAll();
$disciplines = $db->query('SELECT id, name FROM disciplines ORDER BY name')->fetchAll();
$breeds      = $db->query('SELECT id, name FROM breeds ORDER BY name')->fetchAll();
$colors      = $db->query('SELECT id, name FROM colors ORDER BY name')->fetchAll();
$contacts    = $db->query('SELECT * FROM contacts ORDER BY nickname, stable_name')->fetchAll();

$errors = [];
$f = $horse; // prefill from DB

// Hae hevosen nykyiset lajit pivot-taulusta
$hdStmt = $db->prepare('SELECT discipline_id FROM horse_disciplines WHERE horse_id = :id');
$hdStmt->execute([':id' => $id]);
$horseDisciplineIds = array_column($hdStmt->fetchAll(), 'discipline_id');
$selectedDisciplineIds = array_map('intval', $horseDisciplineIds);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        $fields = ['name','call_name','vh_id','pkk_id','breed_id','birth_date','aging_system','gender','color_id','genes','height_cm',
                   'level_ko','level_re','level_ke',
                   'owner_contact_id','breeder_contact_id','importer_contact_id',
                   'sire_id','dam_id','evm','profile_url',
                   'description','pedigree_notes','porrastetut_discipline_id'];
        $validAgingSystems = ['IRL','VHKR','VARL','CAS','KATT','SHS'];
        $validGenders = ['ori','tamma','ruuna'];
        foreach ($fields as $k) {
            $f[$k] = sanitize($_POST[$k] ?? '');
        }
        $f['porrastetut'] = isset($_POST['porrastetut']) ? '1' : '0';
        $validDisciplineIds = array_column($disciplines, 'id');
        $selectedDisciplineIds = array_values(array_filter(
            array_map('intval', $_POST['discipline_ids'] ?? []),
            fn($did) => in_array($did, $validDisciplineIds)
        ));
        if ($f['name'] === '') {
            $errors[] = 'Nimi on pakollinen.';
        }
        if ($f['profile_url'] !== '' && filter_var($f['profile_url'], FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Profiililinkki ei ole kelvollinen URL.';
        }

        // Käsittele uudet inline-yhteystiedot
        $newContactIds = [];
        foreach (['owner' => 'Omistaja', 'breeder' => 'Kasvattaja', 'importer' => 'Tuoja'] as $role => $label) {
            $cid = $f[$role.'_contact_id'] ? (int)$f[$role.'_contact_id'] : 0;
            if (!$cid) {
                $nn  = sanitize($_POST[$role.'_new_nickname'] ?? '');
                $sn  = sanitize($_POST[$role.'_new_stable_name'] ?? '');
                $su  = sanitize($_POST[$role.'_new_stable_url'] ?? '');
                $vrl = sanitize($_POST[$role.'_new_vrl_id'] ?? '');
                $em  = sanitize($_POST[$role.'_new_email'] ?? '');
                $co  = sanitize($_POST[$role.'_new_country'] ?? '');
                if ($nn || $sn || $em) {
                    if ($em !== '') {
                        $r = validate_email($em);
                        if (!$r['valid']) $errors[] = $label . ': ' . strtolower($r['error']);
                        else $em = $r['value'];
                    }
                    if ($su !== '' && filter_var($su, FILTER_VALIDATE_URL) === false) {
                        $errors[] = $label . ': tallin URL ei ole kelvollinen.';
                    }
                    if (empty($errors)) {
                        $cstmt = $db->prepare(
                            'INSERT INTO contacts (nickname, stable_name, stable_url, vrl_id, email, country)
                             VALUES (:nn, :sn, :su, :vrl, :em, :co)'
                        );
                        $cstmt->execute([':nn'=>$nn?:null,':sn'=>$sn?:null,':su'=>$su?:null,':vrl'=>$vrl?:null,':em'=>$em?:null,':co'=>$co?:null]);
                        $cid = (int)$db->lastInsertId();
                        $contacts = $db->query('SELECT * FROM contacts ORDER BY nickname, stable_name')->fetchAll();
                    }
                }
            }
            $newContactIds[$role] = $cid ?: null;
        }

        if (empty($errors)) {
            // Regeneroi slug vain jos nimi muuttui
            $slug = $horse['slug'];
            if ($f['name'] !== $horse['name']) {
                $slug = slugify($f['name']);
                $base = $slug;
                $n = 2;
                while (true) {
                    $chk = $db->prepare('SELECT id FROM horses WHERE slug = :slug AND id != :id');
                    $chk->execute([':slug' => $slug, ':id' => $id]);
                    if (!$chk->fetch()) break;
                    $slug = $base . '-' . $n++;
                }
            }

            $stmt = $db->prepare(
                'UPDATE horses SET
                 name=:name, call_name=:call_name, vh_id=:vh_id, pkk_id=:pkk_id, breed_id=:breed_id, porrastetut=:porrastetut, porrastetut_discipline_id=:porrastetut_discipline_id,
                 birth_date=:birth_date, aging_system=:aging_system, gender=:gender, color_id=:color_id, genes=:genes, height_cm=:height_cm,
                 level_ko=:level_ko, level_re=:level_re, level_ke=:level_ke,
                 owner_contact_id=:owner_contact_id, breeder_contact_id=:breeder_contact_id, importer_contact_id=:importer_contact_id,
                 sire_id=:sire_id, dam_id=:dam_id, evm=:evm, profile_url=:profile_url,
                 description=:description, pedigree_notes=:pedigree_notes, slug=:slug
                 WHERE id=:id AND is_deleted=0'
            );
            $stmt->execute([
                ':name'                => $f['name'],
                ':call_name'           => $f['call_name'] ?: null,
                ':vh_id'               => $f['vh_id'] ?: null,
                ':pkk_id'              => $f['pkk_id'] ?: null,
                ':breed_id'            => $f['breed_id'] !== '' ? (int)$f['breed_id'] : null,
                ':birth_date'          => $f['birth_date'] ?: null,
                ':aging_system'        => in_array($f['aging_system'], $validAgingSystems, true) ? $f['aging_system'] : null,
                ':gender'              => in_array($f['gender'], $validGenders, true) ? $f['gender'] : 'tamma',
                ':color_id'            => $f['color_id'] !== '' ? (int)$f['color_id'] : null,
                ':genes'               => $f['genes'] ?: null,
                ':height_cm'           => $f['height_cm'] !== '' ? (int)$f['height_cm'] : null,
                ':level_ko'            => $f['level_ko'] ?: null,
                ':level_re'            => $f['level_re'] ?: null,
                ':level_ke'            => $f['level_ke'] ?: null,
                ':owner_contact_id'    => $newContactIds['owner'],
                ':breeder_contact_id'  => $newContactIds['breeder'],
                ':importer_contact_id' => $newContactIds['importer'],
                ':sire_id'        => $f['sire_id'] !== '' ? (int)$f['sire_id'] : null,
                ':dam_id'         => $f['dam_id'] !== '' ? (int)$f['dam_id'] : null,
                ':evm'            => $f['evm'] !== '' ? (int)$f['evm'] : 0,
                ':profile_url'    => $f['profile_url'] ?: null,
                ':description'               => $f['description'] ?: null,
                ':pedigree_notes'            => $f['pedigree_notes'] ?: null,
                ':slug'                      => $slug,
                ':porrastetut'               => (int)$f['porrastetut'],
                ':porrastetut_discipline_id' => $f['porrastetut_discipline_id'] !== '' ? (int)$f['porrastetut_discipline_id'] : null,
                ':id'                        => $id,
            ]);
            $del = $db->prepare('DELETE FROM horse_disciplines WHERE horse_id = :id');
            $del->execute([':id' => $id]);
            if ($selectedDisciplineIds) {
                $ins = $db->prepare('INSERT IGNORE INTO horse_disciplines (horse_id, discipline_id) VALUES (:h, :d)');
                foreach ($selectedDisciplineIds as $did) {
                    $ins->execute([':h' => $id, ':d' => $did]);
                }
            }
            redirect(SITE_URL . '/admin/horses.php?updated=1');
        }
    }
}

$pageTitle = 'Muokkaa: ' . $horse['name'];
$breedsJson = json_encode(array_map(fn($b) => ['id' => $b['id'], 'label' => $b['name']], $breeds), JSON_UNESCAPED_UNICODE);
$colorsJson = json_encode(array_map(fn($c) => ['id' => $c['id'], 'label' => $c['name']], $colors), JSON_UNESCAPED_UNICODE);
// Hevoset isä/emä-hakuun — suodatetaan nykyinen hevonen pois
$horsesJson = json_encode(
    array_values(array_map(
        fn($h) => ['id' => $h['id'], 'label' => $h['name']],
        array_filter($allHorses, fn($h) => (int)$h['id'] !== $id)
    )), JSON_UNESCAPED_UNICODE
);
// Hae nykyiset nimet näytölle
$currentSireLabel = '';
if (!empty($f['sire_id'])) {
    foreach ($allHorses as $h) { if ((int)$h['id'] === (int)$f['sire_id']) { $currentSireLabel = $h['name']; break; } }
}
$currentDamLabel = '';
if (!empty($f['dam_id'])) {
    foreach ($allHorses as $h) { if ((int)$h['id'] === (int)$f['dam_id']) { $currentDamLabel = $h['name']; break; } }
}
// Hae nykyisen rodun, värin ja lajin nimet näytöllä
$currentBreedLabel = '';
if (!empty($f['breed_id'])) {
    foreach ($breeds as $b) { if ((int)$b['id'] === (int)$f['breed_id']) { $currentBreedLabel = $b['name']; break; } }
}
$currentColorLabel = '';
if (!empty($f['color_id'])) {
    foreach ($colors as $c) { if ((int)$c['id'] === (int)$f['color_id']) { $currentColorLabel = $c['name']; break; } }
}
// Yhteystiedot
$contactsJson = json_encode(array_map(function($c) {
    $label = trim(($c['nickname'] ?? '') . ($c['stable_name'] ? ' / ' . $c['stable_name'] : ''));
    return ['id' => $c['id'], 'label' => $label ?: '#'.$c['id'],
            'nickname' => $c['nickname'] ?? '', 'stable_name' => $c['stable_name'] ?? '',
            'stable_url' => $c['stable_url'] ?? '', 'vrl_id' => $c['vrl_id'] ?? '',
            'email' => $c['email'] ?? '', 'country' => $c['country'] ?? ''];
}, $contacts), JSON_UNESCAPED_UNICODE);
$contactsById = array_column($contacts, null, 'id');
$currentContactLabels = [];
foreach (['owner', 'breeder', 'importer'] as $role) {
    $cid = (int)($f[$role.'_contact_id'] ?? 0);
    if ($cid && isset($contactsById[$cid])) {
        $c = $contactsById[$cid];
        $currentContactLabels[$role] = trim(($c['nickname'] ?? '') . ($c['stable_name'] ? ' / ' . $c['stable_name'] : ''));
    } else {
        $currentContactLabels[$role] = '';
    }
}
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="back-link">← Hevoset</a>
  <h1>Muokkaa: <?= e($horse['name']) ?></h1>
</div>
<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $e_msg): ?><li><?= e($e_msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="admin-card">
<form method="post" action="">
  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="form-row">
    <div class="form-group">
      <label for="name">Nimi *</label>
      <input type="text" id="name" name="name" value="<?= e($f['name']) ?>" required>
    </div>
    <div class="form-group">
      <label for="call_name">Kutsumanimi</label>
      <input type="text" id="call_name" name="call_name" value="<?= e($f['call_name'] ?? '') ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="vh_id">VH-tunnus</label>
      <input type="text" id="vh_id" name="vh_id" value="<?= e($f['vh_id'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="pkk_id">PKK-tunnus</label>
      <input type="text" id="pkk_id" name="pkk_id" value="<?= e($f['pkk_id'] ?? '') ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="breed_id_text">Rotu</label>
      <div class="ac-wrap"
           data-items='<?= htmlspecialchars($breedsJson, ENT_QUOTES) ?>'
           data-input-id="breed_id"
           data-hidden-name="breed_id"
           data-current-id="<?= (int)($f['breed_id'] ?? 0) ?>"
           data-current-label="<?= e($currentBreedLabel) ?>"
           data-placeholder="Hae rotua..."></div>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="birth_date">Syntymäaika</label>
      <input type="date" id="birth_date" name="birth_date" value="<?= e($f['birth_date'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="aging_system">Ikääntymisjärjestelmä</label>
      <select id="aging_system" name="aging_system">
        <option value="">— ei valittu —</option>
        <?php foreach (['IRL','VHKR','VARL','CAS','KATT','SHS'] as $sys): ?>
          <option value="<?= e($sys) ?>" <?= ($f['aging_system'] ?? '') === $sys ? 'selected' : '' ?>><?= e($sys) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group" style="flex:0 0 calc(50% - 0.5rem)">
      <label for="gender">Sukupuoli</label>
      <select id="gender" name="gender">
        <option value="">— valitse —</option>
        <?php foreach (['ori', 'tamma', 'ruuna'] as $g): ?>
          <option value="<?= e($g) ?>" <?= ($f['gender'] ?? '') === $g ? 'selected' : '' ?>><?= ucfirst(e($g)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="color_id_text">Väri</label>
      <div class="ac-wrap"
           data-items='<?= htmlspecialchars($colorsJson, ENT_QUOTES) ?>'
           data-input-id="color_id"
           data-hidden-name="color_id"
           data-current-id="<?= (int)($f['color_id'] ?? 0) ?>"
           data-current-label="<?= e($currentColorLabel) ?>"
           data-placeholder="Hae väriä..."></div>
    </div>
    <div class="form-group">
      <label for="height_cm">Säkäkorkeus (cm)</label>
      <input type="number" id="height_cm" name="height_cm" min="100" max="200" value="<?= e($f['height_cm'] ?? '') ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="genes">Geenit</label>
      <input type="text" id="genes" name="genes" value="<?= e($f['genes'] ?? '') ?>" placeholder="esim. Ee Aa">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group" style="grid-column:1/-1">
      <label>Lajit</label>
      <div class="checkbox-grid">
        <?php foreach ($disciplines as $d): ?>
          <label class="checkbox-label">
            <input type="checkbox" name="discipline_ids[]" value="<?= (int)$d['id'] ?>"
                   <?= in_array((int)$d['id'], $selectedDisciplineIds) ? 'checked' : '' ?>>
            <?= e($d['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="level_ko">Taso — ko</label>
      <input type="text" id="level_ko" name="level_ko" value="<?= e($f['level_ko'] ?? '') ?>" placeholder="esim. Vaativa A">
    </div>
    <div class="form-group">
      <label for="level_re">Taso — re</label>
      <input type="text" id="level_re" name="level_re" value="<?= e($f['level_re'] ?? '') ?>" placeholder="esim. 130cm, CIC5">
    </div>
    <div class="form-group">
      <label for="level_ke">Taso — ke</label>
      <input type="text" id="level_ke" name="level_ke" value="<?= e($f['level_ke'] ?? '') ?>" placeholder="esim. CCI2*-S">
    </div>
  </div>

  <div class="form-row" style="align-items:flex-start">
    <div class="form-group" style="flex:0 0 auto;padding-top:1.6rem">
      <label class="checkbox-label">
        <input type="checkbox" id="porrastetut" name="porrastetut" value="1" <?= ($f['porrastetut'] ?? 0) == 1 ? 'checked' : '' ?> onchange="document.getElementById('porrastetut-disc').style.display=this.checked?'':'none'">
        Kilpailee porrastetuissa
      </label>
    </div>
    <div class="form-group" id="porrastetut-disc" style="<?= ($f['porrastetut'] ?? 0) != 1 ? 'display:none' : '' ?>">
      <label for="porrastetut_discipline_id">Porrastettu-laji</label>
      <select id="porrastetut_discipline_id" name="porrastetut_discipline_id">
        <option value="">— valitse laji —</option>
        <?php foreach ($disciplines as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= (int)($f['porrastetut_discipline_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
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
           data-current-id="<?= (int)($f['sire_id'] ?? 0) ?>"
           data-current-label="<?= e($currentSireLabel) ?>"
           data-placeholder="Hae ori..."></div>
    </div>
    <div class="form-group">
      <label for="dam_id_text">Emä (tamma)</label>
      <div class="ac-wrap"
           data-items='<?= htmlspecialchars($horsesJson, ENT_QUOTES) ?>'
           data-input-id="dam_id"
           data-hidden-name="dam_id"
           data-current-id="<?= (int)($f['dam_id'] ?? 0) ?>"
           data-current-label="<?= e($currentDamLabel) ?>"
           data-placeholder="Hae tamma..."></div>
    </div>
  </div>

  <h3 style="margin-top:1.5rem">Omistaja &amp; kasvattaja</h3>
  <?php foreach ([
    ['role'=>'owner',    'label'=>'Omistaja'],
    ['role'=>'breeder',  'label'=>'Kasvattaja'],
    ['role'=>'importer', 'label'=>'Tuoja'],
  ] as $rdef): $role = $rdef['role']; $rlabel = $rdef['label'];
    $currentCid = (int)($f[$role.'_contact_id'] ?? 0);
    $currentLabel = $currentContactLabels[$role] ?? '';
  ?>
  <fieldset style="border:1px solid var(--color-border,#e0d5c5);border-radius:8px;padding:1rem;margin-bottom:1rem">
    <legend style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-muted,#6b5e52);padding:0 0.4rem"><?= $rlabel ?></legend>
    <div class="form-group" style="margin-bottom:0.75rem">
      <label>Hae osoitekirjasta</label>
      <div class="ac-wrap contact-ac"
           data-items='<?= htmlspecialchars($contactsJson, ENT_QUOTES) ?>'
           data-input-id="<?= $role ?>_contact"
           data-hidden-name="<?= $role ?>_contact_id"
           data-current-id="<?= $currentCid ?>"
           data-current-label="<?= e($currentLabel) ?>"
           data-preview-target="<?= $role ?>-preview"
           data-new-target="<?= $role ?>-new"
           data-placeholder="Hae nimimerkillä tai tallin nimellä..."></div>
    </div>
    <div id="<?= $role ?>-preview" class="contact-preview" style="display:<?= $currentCid ? 'block' : 'none' ?>">
      <?php if ($currentCid && isset($contactsById[$currentCid])): $cc = $contactsById[$currentCid]; ?>
        <div class="contact-card">
          <?php if ($cc['nickname']): ?><strong><?= e($cc['nickname']) ?></strong><?php endif; ?>
          <?php if ($cc['stable_name']): ?><?php if ($cc['stable_url']): ?> / <a href="<?= e($cc['stable_url']) ?>" target="_blank" rel="noopener"><?= e($cc['stable_name']) ?></a><?php else: ?> / <?= e($cc['stable_name']) ?><?php endif; ?><?php endif; ?>
          <?php if ($cc['vrl_id']): ?> &middot; <?= e($cc['vrl_id']) ?><?php endif; ?>
          <?php if ($cc['email']): ?> &middot; <?= e($cc['email']) ?><?php endif; ?>
          <?php if ($cc['country']): ?> &middot; <?= e($cc['country']) ?><?php endif; ?>
          &nbsp;<a href="<?= e(SITE_URL) ?>/admin/contact_edit.php?id=<?= $currentCid ?>" style="font-size:0.75rem" target="_blank">✏️ muokkaa</a>
        </div>
      <?php endif; ?>
    </div>
    <div style="margin:0.5rem 0;font-size:0.78rem;color:var(--color-text-muted,#6b5e52)">— tai —</div>
    <button type="button" class="btn-sm" onclick="toggleContactNew('<?= $role ?>')">+ Luo uusi yhteystieto osoitekirjaan</button>
    <div id="<?= $role ?>-new" style="display:none;margin-top:0.75rem">
      <div class="form-row">
        <div class="form-group">
          <label for="<?= $role ?>_new_nickname">Nimimerkki</label>
          <input type="text" id="<?= $role ?>_new_nickname" name="<?= $role ?>_new_nickname">
        </div>
        <div class="form-group">
          <label for="<?= $role ?>_new_stable_name">Tallin nimi</label>
          <input type="text" id="<?= $role ?>_new_stable_name" name="<?= $role ?>_new_stable_name">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="<?= $role ?>_new_stable_url">Tallin URL</label>
          <input type="url" id="<?= $role ?>_new_stable_url" name="<?= $role ?>_new_stable_url" placeholder="https://...">
        </div>
        <div class="form-group">
          <label for="<?= $role ?>_new_vrl_id">VRL-tunnus</label>
          <input type="text" id="<?= $role ?>_new_vrl_id" name="<?= $role ?>_new_vrl_id" placeholder="VRL-XXXXX">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="<?= $role ?>_new_email">Sähköposti</label>
          <input type="email" id="<?= $role ?>_new_email" name="<?= $role ?>_new_email">
        </div>
        <div class="form-group">
          <label for="<?= $role ?>_new_country">Maa</label>
          <input type="text" id="<?= $role ?>_new_country" name="<?= $role ?>_new_country">
        </div>
      </div>
    </div>
  </fieldset>
  <?php endforeach; ?>

  <h3 style="margin-top:1.5rem">Lisätiedot</h3>
  <div class="form-row">
    <div class="form-group">
      <label for="evm">EVM</label>
      <select id="evm" name="evm">
        <option value="0" <?= ($f['evm'] ?? 0) != 1 ? 'selected' : '' ?>>Ei</option>
        <option value="1" <?= ($f['evm'] ?? 0) == 1 ? 'selected' : '' ?>>Kyllä</option>
      </select>
    </div>
    <div class="form-group">
      <label for="profile_url">Profiililinkki (ulkoinen)</label>
      <input type="url" id="profile_url" name="profile_url" value="<?= e($f['profile_url'] ?? '') ?>">
    </div>
  </div>
  <div class="form-group">
    <label for="description">Kuvaus</label>
    <textarea id="description" name="description"><?= e($f['description'] ?? '') ?></textarea>
  </div>
  <div class="form-group">
    <label for="pedigree_notes">Sukutaulun lisätiedot</label>
    <textarea id="pedigree_notes" name="pedigree_notes"><?= e($f['pedigree_notes'] ?? '') ?></textarea>
  </div>

  <div style="display:flex;gap:0.75rem;align-items:center;margin-top:1rem">
    <button type="submit" class="btn">Tallenna muutokset</button>
    <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="btn-ghost">Peruuta</a>
  </div>
</form>
</div><!-- /.admin-card -->
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
