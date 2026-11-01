<?php
require_once __DIR__ . '/../src/includes/db.php';

$db = getDB();
$_vt_clean = fn($v) => preg_replace('/[^a-z0-9\-]/', '', strtolower(trim((string)$v)));
$_vt_findStable = function (string $slug) use ($db) {
    $stmt = $db->prepare('SELECT * FROM stables WHERE slug = :slug');
    $stmt->execute([':slug' => $slug]);
    return $stmt->fetch() ?: null;
};
$_vt_main = mainStable();

// Reitti: sisäinen kutsu (index.php, hevoset.php …) asettaa $_vt_route, muuten .htaccess:
//   /{talli}          → ?p=talli     (muu kuin päätalli)
//   /{sivu}           → ?p=sivu      (päätallin alasivu)
//   /{talli}/{sivu}   → ?slug=talli&sivu=sivu
//   /talli/{talli}... → ?slug=…&legacy=1 (vanha osoite → ohjataan uuteen)
$_vt_internal = isset($_vt_route);
if ($_vt_internal) {
    $stable = $_vt_findStable($_vt_route['slug']);
    $_GET['sivu'] = $_vt_route['sivu'];
} elseif (isset($_GET['p'])) {
    $_vt_p  = $_vt_clean($_GET['p']);
    $stable = $_vt_findStable($_vt_p);
    if (!$stable || isMainStable($stable)) {
        // Ei tallin slug (tai päätallin oma slug) → päätallin alasivu / etusivu
        $_GET['sivu'] = $stable ? '' : $_vt_p;
        $stable = $_vt_main;
    } else {
        $_GET['sivu'] = '';
    }
} else {
    $stable = $_vt_findStable($_vt_clean($_GET['slug'] ?? ''));
}
if (!$stable) {
    http_response_code(404);
    exit;
}

// Kanoninen osoite: vanhat /talli/…-osoitteet ja päätallin /slug/… → uusi osoite
$_vt_sivuParam = $_vt_clean($_GET['sivu'] ?? '');
if (!$_vt_internal && (!empty($_GET['legacy']) || isset($_GET['p']) || isMainStable($stable))) {
    $_vt_canonical = $_vt_sivuParam !== '' ? stablePageUrl($stable, $_vt_sivuParam) : stableUrl($stable);
    $_vt_reqPath   = rtrim(strtok($_SERVER['REQUEST_URI'], '?'), '/');
    if (rtrim(parse_url($_vt_canonical, PHP_URL_PATH) ?? '', '/') !== $_vt_reqPath) {
        $_vt_qs = array_diff_key($_GET, array_flip(['p', 'slug', 'sivu', 'legacy']));
        header('Location: ' . $_vt_canonical . ($_vt_qs ? '?' . http_build_query($_vt_qs) : ''), true, 302);
        exit;
    }
}

// Tallin ulkoasu- ja väriteema → asetettava ennen theme.php:tä
setStableContext($stable);
require_once __DIR__ . '/../src/includes/theme.php';

// Jos tallin teemalla on oma tallisivu, käytetään sitä
$_vt_themeFile = resolveThemePath('talli.php');
if ($_vt_themeFile !== false
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}

$stmt = $db->prepare(
    'SELECT h.id, h.name, h.slug, h.gender, h.birth_date, h.sire_id, h.dam_id,
            b.name AS breed_name, b.abbreviation AS breed_abbr,
            (SELECT GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR \', \')
             FROM horse_disciplines hd
             JOIN disciplines d ON d.id = hd.discipline_id
             WHERE hd.horse_id = h.id) AS discipline_names,
            hp.filename
     FROM horses h
     LEFT JOIN breeds b ON b.id = h.breed_id
     LEFT JOIN horse_photos hp
            ON hp.horse_id = h.id
           AND hp.sort_order = (SELECT MIN(sort_order) FROM horse_photos WHERE horse_id = h.id)
     WHERE h.is_deleted = 0 AND h.evm = 0 AND h.ancestor = 0 AND h.stable_id = :stable_id
     ORDER BY h.name ASC'
);
$stmt->execute([':stable_id' => (int)$stable['id']]);
$horses = $stmt->fetchAll();

// Teeman omat tallin alasivut (theme.json):
//   "stable_pages": {"hevoset": {"title": "Hevoset", "file": "pages/talli-hevoset.php"}, ...}
//   "stable_home":  "etusivu"   ← /talli/slug ilman alasivua
// Alasivupohja saa käyttöönsä $stable, $horses, $db ja $stablePage (sivun avain + asetukset).
$_vt_stablePages = getThemeManifest()['stable_pages'] ?? [];
$_vt_sivu = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['sivu'] ?? '')));
// Sivuston vakiosivut tallin rajaamana, jos teema ei määrittele samannimistä alasivua
$_vt_builtinPages = [
    'hevoset'       => 'hevoset.php',
    'kasvatus'      => 'kasvatus.php',
    'ajankohtaista' => 'ajankohtaista.php',
    'yhteystiedot'  => 'yhteystiedot.php',
];
if ($_vt_sivu === '') {
    $_vt_sivu = getThemeManifest()['stable_home'] ?? '';
} elseif (!isset($_vt_stablePages[$_vt_sivu])) {
    if (isset($_vt_builtinPages[$_vt_sivu])) {
        require __DIR__ . '/' . $_vt_builtinPages[$_vt_sivu];
        exit;
    }
    http_response_code(404);
    exit;
}
if ($_vt_sivu !== '' && !empty($_vt_stablePages[$_vt_sivu]['file'])) {
    $stablePage = ['key' => $_vt_sivu] + $_vt_stablePages[$_vt_sivu];
    $_themePage = resolveThemePath($stablePage['file']);
    if ($_themePage === false) {
        http_response_code(404);
        exit;
    }
    require $_themePage;
    exit;
}

// Tallin sivu käyttää teeman hevoslistapohjaa ($stable asetettu → tallin otsikko ja kuvaus)
$_themePage = resolveThemePath('pages/talli.php') ?: resolveThemePath('pages/hevoset.php');
if ($_themePage === false) {
    http_response_code(404);
    exit;
}
require $_themePage;
