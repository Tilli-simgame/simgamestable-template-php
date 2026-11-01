<?php
require_once __DIR__ . '/../src/includes/db.php';

// Päätalli valittu → sivu näytetään päätallin sivuna (teema + tallin rajaus)
if (empty($GLOBALS['vt_stable']) && mainStable()) {
    $_vt_route = ['slug' => mainStable()['slug'], 'sivu' => 'hevoset'];
    require __DIR__ . '/talli.php';
    exit;
}
require_once __DIR__ . '/../src/includes/theme.php';

// Jos aktiivisella teemalla on oma hevoslistaussivu, käytetään sitä
$_vt_themeFile = resolveThemePath('hevoset.php');
if ($_vt_themeFile !== false
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}

$db = getDB();
$stmt = $db->prepare(
    'SELECT h.id, h.name, h.slug, h.gender, h.birth_date,
            b.name AS breed_name,
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
     WHERE h.is_deleted = 0 AND h.evm = 0 AND h.ancestor = 0'
     . (!empty($GLOBALS['vt_stable']) ? ' AND h.stable_id = :stable_id' : '') . '
     ORDER BY h.name ASC'
);
$stmt->execute(!empty($GLOBALS['vt_stable']) ? [':stable_id' => $GLOBALS['vt_stable']['id']] : []);
$horses = $stmt->fetchAll();

$_themePage = resolveThemePath('pages/hevoset.php');
if ($_themePage === false) {
    http_response_code(404);
    exit;
}
require $_themePage;
