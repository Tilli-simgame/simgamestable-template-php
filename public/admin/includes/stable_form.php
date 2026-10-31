<?php
/**
 * Tallin lisäys- ja muokkauslomake (stable_add.php, stable_edit.php).
 */

const STABLE_CATEGORY_SUGGESTIONS = [
    'Harrastetalli', 'Kasvattamo', 'Kilpailutalli', 'Myyntitalli', 'Oriasema',
    'Ratsastuskoulu', 'Ravitalli', 'Siittola', 'Valmennustalli', 'Yksityistalli',
];

function stableFormDefaults(): array {
    return [
        'name' => '', 'stable_number' => '', 'category' => '', 'description' => '',
        'layout_theme' => '', 'color_theme' => '', 'sort_order' => '0',
    ];
}

/**
 * Lukee ja validoi POST-datan. Palauttaa [$f, $errors].
 * Tyhjä layout_theme / color_theme = käytetään sivuston oletusta.
 */
function stableFormRead(): array {
    $f = stableFormDefaults();
    $errors = [];
    foreach (['name', 'stable_number', 'category', 'layout_theme', 'color_theme', 'sort_order'] as $k) {
        $f[$k] = sanitize($_POST[$k] ?? '');
    }
    // Kuvaus saa sisältää rivinvaihtoja — vain trim + tagien poisto
    $f['description'] = trim(strip_tags($_POST['description'] ?? ''));

    if ($f['name'] === '') {
        $errors[] = 'Tallin nimi on pakollinen.';
    } elseif (mb_strlen($f['name']) > 150) {
        $errors[] = 'Tallin nimi on liian pitkä (max 150 merkkiä).';
    }
    if (mb_strlen($f['stable_number']) > 50) {
        $errors[] = 'Tallinumero on liian pitkä (max 50 merkkiä).';
    }
    if (mb_strlen($f['category']) > 100) {
        $errors[] = 'Kategoria on liian pitkä (max 100 merkkiä).';
    }
    if ($f['layout_theme'] !== '' && !array_key_exists($f['layout_theme'], availableLayoutThemes())) {
        $f['layout_theme'] = '';
    }
    if ($f['color_theme'] !== '' && !array_key_exists($f['color_theme'], colorThemeDefs())) {
        $f['color_theme'] = '';
    }
    $f['sort_order'] = (string)(int)$f['sort_order'];
    return [$f, $errors];
}

/** Yksilöllinen slug talleille (oma rivi ohitetaan muokatessa). */
function stableUniqueSlug(PDO $db, string $name, ?int $exceptId = null): string {
    $base = slugify($name) ?: 'talli';
    $slug = $base;
    $n = 2;
    $chk = $db->prepare('SELECT id FROM stables WHERE slug = :slug AND id <> :id');
    while (true) {
        $chk->execute([':slug' => $slug, ':id' => $exceptId ?? 0]);
        if (!$chk->fetch()) return $slug;
        $slug = $base . '-' . $n++;
    }
}

function stableFormParams(array $f): array {
    return [
        ':name'          => $f['name'],
        ':stable_number' => $f['stable_number'] ?: null,
        ':category'      => $f['category'] ?: null,
        ':description'   => $f['description'] ?: null,
        ':layout_theme'  => $f['layout_theme'] ?: null,
        ':color_theme'   => $f['color_theme'] ?: null,
        ':sort_order'    => (int)$f['sort_order'],
    ];
}

/** Lomakkeen kentät (ilman <form>-tagia). $categories = kannassa jo käytetyt kategoriat. */
function renderStableFormFields(array $f, array $categories): void {
    $suggestions = array_values(array_unique(array_merge($categories, STABLE_CATEGORY_SUGGESTIONS)));
    sort($suggestions, SORT_LOCALE_STRING);
    $layouts = availableLayoutThemes();
    ?>
  <div class="admin-card">
    <h2>Perustiedot</h2>
    <div class="form-row">
      <div class="form-group">
        <label for="name">Tallin nimi *</label>
        <input type="text" id="name" name="name" value="<?= e($f['name']) ?>" maxlength="150" required>
      </div>
      <div class="form-group">
        <label for="stable_number">Tallinumero</label>
        <input type="text" id="stable_number" name="stable_number" value="<?= e($f['stable_number'] ?? '') ?>"
               maxlength="50" placeholder="esim. TIIK1234">
      </div>
    </div>
    <div class="form-row" style="margin-top:1rem">
      <div class="form-group">
        <label for="category">Kategoria</label>
        <input type="text" id="category" name="category" value="<?= e($f['category'] ?? '') ?>"
               maxlength="100" list="stable-categories" placeholder="Valitse tai kirjoita…" autocomplete="off">
        <datalist id="stable-categories">
          <?php foreach ($suggestions as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label for="sort_order">Järjestys</label>
        <input type="number" id="sort_order" name="sort_order" value="<?= e((string)($f['sort_order'] ?? '0')) ?>" step="1">
      </div>
    </div>
    <div class="form-group" style="margin-top:1rem">
      <label for="description">Kuvaus</label>
      <textarea id="description" name="description" rows="6"><?= e($f['description'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="admin-card">
    <h2>Ulkoasuteema</h2>
    <p style="font-size:0.85rem; color:var(--color-text-muted); margin-bottom:0.75rem">
      Sivupohja, jota käytetään tallin sivulla ja tallissa asuvien hevosten sivuilla.
    </p>
    <div style="display:flex; flex-wrap:wrap; gap:0.75rem">
      <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.9rem">
        <input type="radio" name="layout_theme" value="" <?= ($f['layout_theme'] ?? '') === '' ? 'checked' : '' ?>>
        Sivuston oletus
      </label>
      <?php foreach ($layouts as $key => $label): ?>
      <label style="display:flex; align-items:center; gap:0.4rem; cursor:pointer; font-size:0.9rem">
        <input type="radio" name="layout_theme" value="<?= e($key) ?>" <?= ($f['layout_theme'] ?? '') === $key ? 'checked' : '' ?>>
        <?= e($label) ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="admin-card">
    <h2>Väriteema</h2>
    <p style="font-size:0.85rem; color:var(--color-text-muted); margin-bottom:0.25rem">
      Tallin sivun ja sen hevosten värit. "Sivuston oletus" käyttää asetuksissa valittua teemaa.
    </p>
    <div class="theme-picker">
      <label class="theme-opt">
        <input type="radio" name="color_theme" value="" <?= ($f['color_theme'] ?? '') === '' ? 'checked' : '' ?>>
        <div class="theme-card">
          <div class="theme-swatches" style="background:repeating-linear-gradient(45deg,#eee,#eee 6px,#fff 6px,#fff 12px)"></div>
          <div class="theme-label">Sivuston oletus</div>
          <div class="theme-check">✓ valittu</div>
        </div>
      </label>
      <?php foreach (colorThemeDefs() as $key => $t): ?>
      <label class="theme-opt">
        <input type="radio" name="color_theme" value="<?= e($key) ?>" <?= ($f['color_theme'] ?? '') === $key ? 'checked' : '' ?>>
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
    <?php
}
