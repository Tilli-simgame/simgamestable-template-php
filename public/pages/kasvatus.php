<?php
require_once __DIR__ . '/../src/includes/db.php';
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
     WHERE f.is_deleted = 0
     ORDER BY FIELD(f.status, \'expected\', \'born\'), f.birth_date DESC'
);
$stmt->execute();
$allFoals = $stmt->fetchAll();

$expected = array_filter($allFoals, fn($f) => $f['status'] === 'expected');
$born     = array_filter($allFoals, fn($f) => $f['status'] === 'born');

$_themePage = resolveThemePath('pages/kasvatus.php');
if ($_themePage === false) {
    http_response_code(404);
    exit;
}
require $_themePage;
