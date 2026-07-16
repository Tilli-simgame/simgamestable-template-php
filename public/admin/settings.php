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

// Lue saatavilla olevat ulkoasuteeman kansiot (tarvitaan POST-validoinnissa)
$available_themes = [];
$themes_dir = __DIR__ . '/../themes';
foreach (glob($themes_dir . '/*/theme.json') ?: [] as $json_path) {
    $theme_key = basename(dirname($json_path));
    $meta = json_decode(file_get_contents($json_path), true);
    $available_themes[$theme_key] = $meta['name'] ?? $theme_key;
}
if (empty($available_themes)) {
    $available_themes['default'] = 'Default';
}

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
        $valid_themes = ['savi','metsa','yo','ruusu','kivikko','arktinen','aurinko','laventeli','talvi','kulta'];
        $theme_post = $_POST['color_theme'] ?? 'savi';
        $values['color_theme'] = in_array($theme_post, $valid_themes, true) ? $theme_post : 'savi';

        // Aktiivinen ulkoasuteema
        $active_theme_post = $_POST['active_theme'] ?? 'default';
        $values['active_theme'] = array_key_exists($active_theme_post, $available_themes) ? $active_theme_post : 'default';

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
$theme_defs = [
    'savi'      => ['nimi' => 'Savi',       'p' => '#3d2b1f', 'a' => '#a0633a', 'g' => '#c9a84c', 'bg' => '#f9f7f4'],
    'metsa'     => ['nimi' => 'Metsä',      'p' => '#1a3d2b', 'a' => '#5a8a3a', 'g' => '#8db44a', 'bg' => '#f4f7f4'],
    'yo'        => ['nimi' => 'Yö',         'p' => '#1a1f3d', 'a' => '#4a6fa0', 'g' => '#7090c8', 'bg' => '#f4f5f9'],
    'ruusu'     => ['nimi' => 'Ruusu',      'p' => '#4a1f30', 'a' => '#c06080', 'g' => '#d8a0b8', 'bg' => '#fdf5f7'],
    'kivikko'   => ['nimi' => 'Kivikko',    'p' => '#2c3030', 'a' => '#5a7070', 'g' => '#b0a860', 'bg' => '#f5f5f5'],
    'arktinen'  => ['nimi' => 'Arktinen',   'p' => '#1a3a4d', 'a' => '#3d9abf', 'g' => '#70c0e0', 'bg' => '#f4f8fc'],
    'aurinko'   => ['nimi' => 'Aurinko',    'p' => '#4d2c1a', 'a' => '#c06030', 'g' => '#e0a040', 'bg' => '#fdf8f4'],
    'laventeli' => ['nimi' => 'Laventeli',  'p' => '#3a2060', 'a' => '#8060c0', 'g' => '#c0a8e0', 'bg' => '#f8f5fd'],
    'talvi'     => ['nimi' => 'Talvi',      'p' => '#2c3840', 'a' => '#4a7888', 'g' => '#9ab8c8', 'bg' => '#f5f7f8'],
    'kulta'     => ['nimi' => 'Kultainen',  'p' => '#3d3010', 'a' => '#b88c20', 'g' => '#e8c840', 'bg' => '#fdf9f0'],
];
$current_theme = $s['color_theme'] ?? 'savi';

require __DIR__ . '/includes/admin_header.php';
?>
<style>
  .theme-picker { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 0.75rem; margin-top: 1rem; }
  .theme-opt { cursor: pointer; user-select: none; }
  .theme-opt input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
  .theme-card {
    border: 2px solid var(--color-border, #e0d5c5);
    border-radius: 10px; overflow: hidden;
    transition: border-color 0.15s, box-shadow 0.15s;
  }
  .theme-opt:hover .theme-card { border-color: var(--color-accent, #a0633a); }
  .theme-opt input:checked ~ .theme-card {
    border-color: var(--color-primary, #3d2b1f);
    box-shadow: 0 0 0 2px var(--color-primary, #3d2b1f);
  }
  .theme-swatches { display: flex; height: 36px; }
  .theme-swatches span { flex: 1; display: block; }
  .theme-label {
    padding: 0.35rem 0.5rem;
    font-size: 0.72rem; text-align: center;
    background: #fff; color: var(--color-text-muted, #6b5e52);
    font-weight: 600; letter-spacing: 0.03em;
    border-top: 1px solid var(--color-border, #e0d5c5);
  }
  .theme-opt input:checked ~ .theme-card .theme-label {
    color: var(--color-primary, #3d2b1f);
  }
  .theme-check {
    display: none; text-align: center; font-size: 0.7rem;
    color: var(--color-primary, #3d2b1f); font-weight: 700;
    padding: 0 0 0.25rem;
    background: #fff;
  }
  .theme-opt input:checked ~ .theme-card .theme-check { display: block; }
</style>

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
