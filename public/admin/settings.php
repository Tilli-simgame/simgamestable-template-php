<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

$db = getDB();

// Load all settings into an associative array
$rows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
$s = [];
foreach ($rows as $row) {
    $s[$row['setting_key']] = $row['setting_value'] ?? '';
}

$errors  = [];
$success = false;

// Saatavilla olevat ulkoasuteemat (tarvitaan POST-validoinnissa)
$available_themes = availableLayoutThemes();

// Tallit päätallin valintaa varten
$stables = array_map(
    fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'slug' => $r['slug']],
    $db->query('SELECT id, name, slug FROM stables ORDER BY sort_order, name')->fetchAll()
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        $fields = [
            'owner_nickname'  => ['label' => 'Nimimerkki',  'max' => 100, 'required' => true],
            'owner_vrl_id'    => ['label' => 'VRL-tunnus',  'max' => 50,  'required' => true],
            'owner_email'     => ['label' => 'Sähköposti',  'max' => 255, 'required' => false, 'email' => true],
            'owner_forum_url' => ['label' => 'Foorumilinkki', 'max' => 500, 'required' => false, 'url' => true],
            'stable_name'     => ['label' => 'Tallin nimi', 'max' => 150, 'required' => false],
        ];

        $values = [];
        foreach ($fields as $key => $cfg) {
            $val = sanitize($_POST[$key] ?? '');

            if ($cfg['required'] && $val === '') {
                $errors[] = $cfg['label'] . ' on pakollinen.';
                continue;
            }
            if ($val !== '' && mb_strlen($val) > $cfg['max']) {
                $errors[] = $cfg['label'] . ' on liian pitkä (max ' . $cfg['max'] . ' merkkiä).';
                continue;
            }
            if (!empty($cfg['email']) && $val !== '') {
                $r = validate_email($val);
                if (!$r['valid']) { $errors[] = $cfg['label'] . ': ' . $r['error']; continue; }
                $val = $r['value'];
            }
            if (!empty($cfg['url']) && $val !== '') {
                if (filter_var($val, FILTER_VALIDATE_URL) === false) {
                    $errors[] = $cfg['label'] . ' ei ole kelvollinen URL.';
                    continue;
                }
            }
            $values[$key] = $val;
        }

        // Väriteema: sallitut arvot
        $valid_themes = array_keys(colorThemeDefs());
        $theme_post = $_POST['color_theme'] ?? 'savi';
        $values['color_theme'] = in_array($theme_post, $valid_themes, true) ? $theme_post : 'savi';

        // Aktiivinen ulkoasuteema
        $active_theme_post = $_POST['active_theme'] ?? 'default';
        $values['active_theme'] = array_key_exists($active_theme_post, $available_themes) ? $active_theme_post : 'default';

        // Päätalli: tyhjä tai olemassa olevan tallin id
        $main_post = (int)($_POST['main_stable_id'] ?? 0);
        $values['main_stable_id'] = in_array($main_post, array_column($stables, 'id'), true) ? (string)$main_post : '';

        if (empty($errors)) {
            $stmt = $db->prepare(
                'INSERT INTO settings (setting_key, setting_value)
                 VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach ($values as $key => $val) {
                $stmt->execute([':k' => $key, ':v' => $val]);
            }
            // Reload
            $rows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            foreach ($rows as $row) {
                $s[$row['setting_key']] = $row['setting_value'] ?? '';
            }
            $success = true;
        }
    }
}

$pageTitle = 'Ylläpitäjän tiedot';

$current_active_theme = $s['active_theme'] ?? 'default';

// Teemamäärittely väriesikatselua varten
$theme_defs = colorThemeDefs();
$current_theme = $s['color_theme'] ?? 'savi';

require __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-page-header">
  <h1>Ylläpitäjän tiedot</h1>
</div>
<div class="admin-body">

<?php if ($success): ?>
  <div class="flash-ok">Tiedot tallennettu.</div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $e_msg): ?><li><?= e($e_msg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="">
  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

  <div class="admin-card">
    <h2>Tallin ylläpitäjä</h2>

    <div class="form-row">
      <div class="form-group">
        <label for="owner_nickname">Nimimerkki *</label>
        <input type="text" id="owner_nickname" name="owner_nickname"
               value="<?= e($s['owner_nickname'] ?? '') ?>"
               maxlength="100" required>
      </div>
      <div class="form-group">
        <label for="owner_vrl_id">VRL-tunnus *</label>
        <input type="text" id="owner_vrl_id" name="owner_vrl_id"
               value="<?= e($s['owner_vrl_id'] ?? '') ?>"
               maxlength="50" placeholder="Esim. VRL-12345" required>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="stable_name">Tallin nimi</label>
        <input type="text" id="stable_name" name="stable_name"
               value="<?= e($s['stable_name'] ?? '') ?>"
               maxlength="150">
      </div>
      <div class="form-group">
        <label for="owner_email">Sähköposti</label>
        <input type="email" id="owner_email" name="owner_email"
               value="<?= e($s['owner_email'] ?? '') ?>"
               maxlength="255">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="owner_forum_url">Foorumilinkki</label>
        <input type="url" id="owner_forum_url" name="owner_forum_url"
               value="<?= e($s['owner_forum_url'] ?? '') ?>"
               maxlength="500" placeholder="https://...">
      </div>
      <div class="form-group"></div>
    </div>
  </div>

  <?php if ($stables): ?>
  <div class="admin-card">
    <h2>Päätalli</h2>
    <p style="font-size:0.85rem; color:var(--color-text-muted); margin-bottom:0.75rem">
      Päätalli näytetään sivuston juuresta (<code><?= e(SITE_URL) ?>/</code>), muut tallit omista
      osoitteistaan (<code><?= e(SITE_URL) ?>/tallin-slug</code>).
    </p>
    <div class="form-group">
      <label for="main_stable_id">Juuressa näytettävä talli</label>
      <select id="main_stable_id" name="main_stable_id">
        <option value="">— Ei päätallia (vakiosivut, kaikkien tallien tiedot) —</option>
        <?php foreach ($stables as $st): ?>
        <option value="<?= $st['id'] ?>"<?= (string)$st['id'] === ($s['main_stable_id'] ?? '') ? ' selected' : '' ?>>
          <?= e($st['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <?php endif; ?>

  <div class="admin-card">
    <h2>Ulkoasuteema</h2>
    <p style="font-size:0.85rem; color:var(--color-text-muted); margin-bottom:0.75rem">
      Valitse käytettävä sivupohja. <strong>Default</strong> on järjestelmän vakiopohja.
    </p>
    <div style="display:flex; flex-wrap:wrap; gap:0.75rem">
      <?php foreach ($available_themes as $key => $label): ?>
      <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.9rem">
        <input type="radio" name="active_theme" value="<?= e($key) ?>"
               <?= $current_active_theme === $key ? 'checked' : '' ?>>
        <?= e($label) ?>
        <?php if ($key === 'default'): ?>
          <span style="font-size:0.75rem; color:var(--color-text-muted)">(vakio)</span>
        <?php endif; ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="admin-card">
    <h2>Väriteema</h2>
    <p style="font-size:0.85rem; color:var(--color-text-muted); margin-bottom:0.25rem">
      Valitse sivuston yleisilme. Teema vaikuttaa sekä julkiseen sivuun että hallintapaneeliin.
    </p>
    <div class="theme-picker">
      <?php foreach ($theme_defs as $key => $t): ?>
      <label class="theme-opt">
        <input type="radio" name="color_theme" value="<?= e($key) ?>"
               <?= $current_theme === $key ? 'checked' : '' ?>>
        <div class="theme-card">
          <div class="theme-swatches">
            <span style="background:<?= e($t['p']) ?>"></span>
            <span style="background:<?= e($t['a']) ?>"></span>
            <span style="background:<?= e($t['g']) ?>"></span>
            <span style="background:<?= e($t['bg']) ?>"></span>
          </div>
          <div class="theme-label"><?= e($t['nimi']) ?></div>
          <div class="theme-check">✓ valittu</div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="margin-top:0.25rem; margin-bottom:1.5rem">
    <button type="submit" class="btn">Tallenna asetukset</button>
  </div>
</form>

</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
