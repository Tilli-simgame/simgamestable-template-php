<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$db = getDB();
$allHorses    = $db->query('SELECT id, name FROM horses WHERE is_deleted = 0 ORDER BY name')->fetchAll();
$disciplines  = $db->query('SELECT id, name FROM disciplines ORDER BY name')->fetchAll();
$breeds       = $db->query('SELECT id, name FROM breeds ORDER BY name')->fetchAll();
$colors       = $db->query('SELECT id, name FROM colors ORDER BY name')->fetchAll();
$contacts     = $db->query('SELECT * FROM contacts ORDER BY nickname, stable_name')->fetchAll();
$stables      = $db->query('SELECT id, name FROM stables ORDER BY sort_order, name')->fetchAll();

$errors = [];
$f = [
    'name' => '', 'call_name' => '', 'vh_id' => '', 'pkk_id' => '', 'breed_id' => '',
    'birth_date' => '', 'aging_system' => '', 'birth_country' => '', 'gender' => '', 'color_id' => '', 'genes' => '', 'height_cm' => '',
    'owner_contact_id' => '', 'breeder_contact_id' => '', 'importer_contact_id' => '',
    'sire_id' => '', 'dam_id' => '', 'evm' => '', 'profile_url' => '',
    'description' => '', 'pedigree_notes' => '',
    'porrastetut' => '0', 'porrastetut_discipline_id' => '', 'stable_id' => '',
];
$selectedDisciplineIds = [];
$levelInputs = []; // discipline_id => koulutustaso
$validAgingSystems = ['IRL','VHKR','VARL','CAS','KATT','SHS'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        foreach ($f as $k => $_) {
            $f[$k] = sanitize($_POST[$k] ?? '');
        }
        $f['porrastetut'] = isset($_POST['porrastetut']) ? '1' : '0';
        $validDisciplineIds = array_column($disciplines, 'id');
        foreach ($validDisciplineIds as $did) {
            $levelInputs[(int)$did] = mb_substr(sanitize($_POST['levels'][$did] ?? ''), 0, 150);
        }
        $selectedDisciplineIds = array_values(array_filter(
            array_map('intval', $_POST['discipline_ids'] ?? []),
            fn($id) => in_array($id, $validDisciplineIds)
        ));
        if ($f['name'] === '') {
            $errors[] = 'Nimi on pakollinen.';
        }
        if ($f['profile_url'] !== '' && filter_var($f['profile_url'], FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Profiililinkki ei ole kelvollinen URL.';
        }

        // Käsittele uudet inline-yhteystiedot (jos contact_id ei valittu)
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

        $validGenders = ['ori','tamma','ruuna'];
        if (empty($errors)) {
            // Generoi slug
            $slug = slugify($f['name']);
            $base = $slug;
            $n = 2;
            while (true) {
                $chk = $db->prepare('SELECT id FROM horses WHERE slug = :slug');
                $chk->execute([':slug' => $slug]);
                if (!$chk->fetch()) break;
                $slug = $base . '-' . $n++;
            }

            $stmt = $db->prepare(
                'INSERT INTO horses
                 (name, call_name, vh_id, pkk_id, breed_id, birth_date, aging_system, birth_country, gender, color_id, genes, height_cm,
                  owner_contact_id, breeder_contact_id, importer_contact_id,
                  sire_id, dam_id, evm, profile_url,
                  description, pedigree_notes, slug, porrastetut, porrastetut_discipline_id, stable_id)
                 VALUES
                 (:name, :call_name, :vh_id, :pkk_id, :breed_id, :birth_date, :aging_system, :birth_country, :gender, :color_id, :genes, :height_cm,
                  :owner_contact_id, :breeder_contact_id, :importer_contact_id,
                  :sire_id, :dam_id, :evm, :profile_url,
                  :description, :pedigree_notes, :slug, :porrastetut, :porrastetut_discipline_id, :stable_id)'
            );
            $stmt->execute([
                ':name'               => $f['name'],
                ':call_name'          => $f['call_name'] ?: null,
                ':vh_id'              => $f['vh_id'] ?: null,
                ':pkk_id'             => $f['pkk_id'] ?: null,
                ':breed_id'           => $f['breed_id'] !== '' ? (int)$f['breed_id'] : null,
                ':birth_date'         => $f['birth_date'] ?: null,
                ':aging_system'       => in_array($f['aging_system'], $validAgingSystems, true) ? $f['aging_system'] : null,
                ':birth_country'      => $f['birth_country'] ?: null,
                ':gender'             => in_array($f['gender'], $validGenders, true) ? $f['gender'] : 'tamma',
                ':color_id'           => $f['color_id'] !== '' ? (int)$f['color_id'] : null,
                ':genes'              => $f['genes'] ?: null,
                ':height_cm'          => $f['height_cm'] !== '' ? (int)$f['height_cm'] : null,
                ':owner_contact_id'   => $newContactIds['owner'],
                ':breeder_contact_id' => $newContactIds['breeder'],
                ':importer_contact_id'=> $newContactIds['importer'],
                ':sire_id'        => $f['sire_id'] !== '' ? (int)$f['sire_id'] : null,
                ':dam_id'         => $f['dam_id'] !== '' ? (int)$f['dam_id'] : null,
                ':evm'            => $f['evm'] !== '' ? (int)$f['evm'] : 0,
                ':profile_url'    => $f['profile_url'] ?: null,
                ':description'              => $f['description'] ?: null,
                ':pedigree_notes'           => $f['pedigree_notes'] ?: null,
                ':slug'                     => $slug,
                ':porrastetut'              => (int)$f['porrastetut'],
                ':porrastetut_discipline_id' => $f['porrastetut_discipline_id'] !== '' ? (int)$f['porrastetut_discipline_id'] : null,
                ':stable_id'                => in_array((int)$f['stable_id'], array_map('intval', array_column($stables, 'id')), true) ? (int)$f['stable_id'] : null,
            ]);
            $newHorseId = (int)$db->lastInsertId();
            if ($selectedDisciplineIds) {
                $ins = $db->prepare('INSERT IGNORE INTO horse_disciplines (horse_id, discipline_id) VALUES (:h, :d)');
                foreach ($selectedDisciplineIds as $did) {
                    $ins->execute([':h' => $newHorseId, ':d' => $did]);
                }
            }
            $insLevel = $db->prepare('INSERT INTO horse_levels (horse_id, discipline_id, level) VALUES (:h, :d, :l)');
            foreach (array_filter($levelInputs, 'strlen') as $did => $level) {
                $insLevel->execute([':h' => $newHorseId, ':d' => $did, ':l' => $level]);
            }
            redirect(SITE_URL . '/admin/horses.php?added=1');
        }
    }
}

$pageTitle = 'Lisää hevonen';
$breedsJson = json_encode(array_map(fn($b) => ['id' => $b['id'], 'label' => $b['name']], $breeds), JSON_UNESCAPED_UNICODE);
$colorsJson = json_encode(array_map(fn($c) => ['id' => $c['id'], 'label' => $c['name']], $colors), JSON_UNESCAPED_UNICODE);
$horsesJson = json_encode(array_map(fn($h) => ['id' => $h['id'], 'label' => $h['name']], $allHorses), JSON_UNESCAPED_UNICODE);
$contactsJson = json_encode(array_map(function($c) {
    $label = trim(($c['nickname'] ?? '') . ($c['stable_name'] ? ' / ' . $c['stable_name'] : ''));
    return ['id' => $c['id'], 'label' => $label ?: '#'.$c['id'],
            'nickname' => $c['nickname'] ?? '', 'stable_name' => $c['stable_name'] ?? '',
            'stable_url' => $c['stable_url'] ?? '', 'vrl_id' => $c['vrl_id'] ?? '',
            'email' => $c['email'] ?? '', 'country' => $c['country'] ?? ''];
}, $contacts), JSON_UNESCAPED_UNICODE);
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="back-link">← Hevoset</a>
  <h1>Lisää hevonen</h1>
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
      <label for="name">Nimi *</label>
      <input type="text" id="name" name="name" value="<?= e($f['name']) ?>" required>
    </div>
    <div class="form-group">
      <label for="call_name">Kutsumanimi</label>
      <input type="text" id="call_name" name="call_name" value="<?= e($f['call_name']) ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="vh_id">VH-tunnus</label>
      <input type="text" id="vh_id" name="vh_id" value="<?= e($f['vh_id']) ?>">
    </div>
    <div class="form-group">
      <label for="pkk_id">PKK-tunnus</label>
      <input type="text" id="pkk_id" name="pkk_id" value="<?= e($f['pkk_id']) ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="breed_id_text">Rotu</label>
      <div class="ac-wrap"
           data-items='<?= htmlspecialchars($breedsJson, ENT_QUOTES) ?>'
           data-input-id="breed_id"
           data-hidden-name="breed_id"
           data-current-id=""
           data-current-label=""
           data-placeholder="Hae rotua..."></div>
    </div>
    <div class="form-group">
      <label for="stable_id">Talli</label>
      <select id="stable_id" name="stable_id">
        <option value="">— ei tallia —</option>
        <?php foreach ($stables as $st): ?>
          <option value="<?= (int)$st['id'] ?>" <?= (int)($f['stable_id'] ?? 0) === (int)$st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="birth_date">Syntymäaika</label>
      <input type="date" id="birth_date" name="birth_date" value="<?= e($f['birth_date']) ?>">
    </div>
    <div class="form-group">
      <label for="aging_system">Ikääntymisjärjestelmä</label>
      <select id="aging_system" name="aging_system">
        <option value="">— ei valittu —</option>
        <?php foreach (['IRL','VHKR','VARL','CAS','KATT','SHS'] as $sys): ?>
          <option value="<?= e($sys) ?>" <?= $f['aging_system'] === $sys ? 'selected' : '' ?>><?= e($sys) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group" style="flex:0 0 calc(50% - 0.5rem)">
      <label for="birth_country">Syntymämaa</label>
      <input type="text" id="birth_country" name="birth_country" value="<?= e($f['birth_country']) ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group" style="flex:0 0 calc(50% - 0.5rem)">
      <label for="gender">Sukupuoli</label>
      <select id="gender" name="gender">
        <option value="">— valitse —</option>
        <?php foreach (['ori', 'tamma', 'ruuna'] as $g): ?>
          <option value="<?= e($g) ?>" <?= $f['gender'] === $g ? 'selected' : '' ?>><?= ucfirst(e($g)) ?></option>
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
           data-current-id=""
           data-current-label=""
           data-placeholder="Hae väriä..."></div>
    </div>
    <div class="form-group">
      <label for="height_cm">Säkäkorkeus (cm)</label>
      <input type="number" id="height_cm" name="height_cm" min="100" max="200" value="<?= e($f['height_cm']) ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="genes">Geenit</label>
      <input type="text" id="genes" name="genes" value="<?= e($f['genes']) ?>" placeholder="esim. Ee Aa">
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

  <div class="form-group">
    <label>Koulutustasot</label>
    <?php $levelPlaceholders = [2 => 'esim. Vaativa A', 1 => 'esim. 130cm', 3 => 'esim. CCI2*-S', 6 => 'esim. Vaikea', 7 => 'esim. Show']; ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0.5rem 1rem">
      <?php foreach ($disciplines as $d): $did = (int)$d['id']; ?>
        <div class="form-group" style="margin:0">
          <label for="level_<?= $did ?>" style="font-weight:normal"><?= e(ucfirst($d['name'])) ?></label>
          <input type="text" id="level_<?= $did ?>" name="levels[<?= $did ?>]" maxlength="150"
                 value="<?= e($levelInputs[$did] ?? '') ?>" placeholder="<?= e($levelPlaceholders[$did] ?? '') ?>">
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="form-row" style="align-items:flex-start">
    <div class="form-group" style="flex:0 0 auto;padding-top:1.6rem">
      <label class="checkbox-label">
        <input type="checkbox" id="porrastetut" name="porrastetut" value="1" <?= $f['porrastetut'] === '1' ? 'checked' : '' ?> onchange="document.getElementById('porrastetut-disc').style.display=this.checked?'':'none'">
        Kilpailee porrastetuissa
      </label>
    </div>
    <div class="form-group" id="porrastetut-disc" style="<?= $f['porrastetut'] !== '1' ? 'display:none' : '' ?>">
      <label for="porrastetut_discipline_id">Porrastettu-laji</label>
      <select id="porrastetut_discipline_id" name="porrastetut_discipline_id">
        <option value="">— valitse laji —</option>
        <?php foreach ($disciplines as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= (int)$f['porrastetut_discipline_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
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

  <h3 style="margin-top:1.5rem">Omistaja &amp; kasvattaja</h3>
  <?php foreach ([
    ['role'=>'owner',    'label'=>'Omistaja'],
    ['role'=>'breeder',  'label'=>'Kasvattaja'],
    ['role'=>'importer', 'label'=>'Tuoja'],
  ] as $rdef): $role = $rdef['role']; $rlabel = $rdef['label']; ?>
  <fieldset style="border:1px solid var(--color-border,#e0d5c5);border-radius:8px;padding:1rem;margin-bottom:1rem">
    <legend style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-text-muted,#6b5e52);padding:0 0.4rem"><?= $rlabel ?></legend>
    <div class="form-group" style="margin-bottom:0.75rem">
      <label>Hae osoitekirjasta</label>
      <div class="ac-wrap contact-ac"
           data-items='<?= htmlspecialchars($contactsJson, ENT_QUOTES) ?>'
           data-input-id="<?= $role ?>_contact"
           data-hidden-name="<?= $role ?>_contact_id"
           data-current-id=""
           data-current-label=""
           data-preview-target="<?= $role ?>-preview"
           data-new-target="<?= $role ?>-new"
           data-placeholder="Hae nimimerkillä tai tallin nimellä..."></div>
    </div>
    <div id="<?= $role ?>-preview" class="contact-preview" style="display:none"></div>
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
      <label for="evm">EVM (1=evm, 0=ei)</label>
      <select id="evm" name="evm">
        <option value="0" <?= $f['evm'] !== '1' ? 'selected' : '' ?>>Ei</option>
        <option value="1" <?= $f['evm'] === '1' ? 'selected' : '' ?>>Kyllä</option>
      </select>
    </div>
    <div class="form-group">
      <label for="profile_url">Profiililinkki (ulkoinen)</label>
      <input type="url" id="profile_url" name="profile_url" value="<?= e($f['profile_url']) ?>">
    </div>
  </div>
  <div class="form-group">
    <label for="description">Kuvaus</label>
    <textarea id="description" name="description"><?= e($f['description']) ?></textarea>
    <div class="hl-hint">Muotoilu: **lihavointi**, *kursiivi*, [linkki](https://...). Tyhjä rivi aloittaa uuden kappaleen.</div>
  </div>
  <div class="form-group">
    <label for="pedigree_notes">Sukuselvitys</label>
    <textarea id="pedigree_notes" name="pedigree_notes"><?= e($f['pedigree_notes']) ?></textarea>
    <div class="hl-hint">Muotoilu: **lihavointi**, *kursiivi*, [linkki](https://...). Tyhjä rivi aloittaa uuden kappaleen.</div>
  </div>

  <div style="display:flex;gap:0.75rem;align-items:center;margin-top:1rem">
    <button type="submit" class="btn">Tallenna hevonen</button>
    <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="btn-ghost">Peruuta</a>
  </div>
</form>
</div><!-- /.admin-card -->
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
