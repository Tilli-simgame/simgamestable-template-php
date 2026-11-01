<?php
require_once __DIR__ . '/../src/includes/db.php';

// Päätalli valittu → sivu näytetään päätallin sivuna (teema + tallin rajaus)
if (empty($GLOBALS['vt_stable']) && mainStable()) {
    $_vt_route = ['slug' => mainStable()['slug'], 'sivu' => 'kasvatus'];
    require __DIR__ . '/talli.php';
    exit;
}
require_once __DIR__ . '/../src/includes/theme.php';

// Jos aktiivisella teemalla on oma kasvatussivu, käytetään sitä
$_vt_themeFile = resolveThemePath('kasvatus.php');
if ($_vt_themeFile !== false
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}

$db = getDB();
$stmt = $db->prepare(
    'SELECT f.*,
            sire.name AS sire_name, sire.slug AS sire_slug,
            dam.name  AS dam_name,  dam.slug  AS dam_slug
     FROM foals f
     LEFT JOIN horses sire ON sire.id = f.sire_id AND sire.is_deleted = 0
     LEFT JOIN horses dam  ON dam.id  = f.dam_id  AND dam.is_deleted = 0
     WHERE f.is_deleted = 0'
     // Tallikontekstissa: varsat, joiden tallin hevonen (horse_id, muuten emä) asuu tallissa
     . (!empty($GLOBALS['vt_stable'])
        ? ' AND EXISTS (SELECT 1 FROM horses home
                         WHERE home.id = COALESCE(f.horse_id, f.dam_id) AND home.stable_id = :stable_id)'
        : '') . '
     ORDER BY FIELD(f.status, \'expected\', \'born\'), f.birth_date DESC'
);
$stmt->execute(!empty($GLOBALS['vt_stable']) ? [':stable_id' => $GLOBALS['vt_stable']['id']] : []);
$allFoals = $stmt->fetchAll();

$expected = array_filter($allFoals, fn($f) => $f['status'] === 'expected');
$born     = array_filter($allFoals, fn($f) => $f['status'] === 'born');

$_themePage = resolveThemePath('pages/kasvatus.php');
if ($_themePage === false) {
    http_response_code(404);
    exit;
}
require $_themePage;
