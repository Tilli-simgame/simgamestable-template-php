<?php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/../src/includes/theme.php';

// Jos aktiivisella teemalla on oma yhteystietosivu, käytetään sitä
$_vt_themeFile = resolveThemePath('yhteystiedot.php');
if ($_vt_themeFile !== false
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}

// Haetaan asetukset tietokannasta
$db   = getDB();
$rows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
$s    = [];
foreach ($rows as $row) {
    $s[$row['setting_key']] = $row['setting_value'] ?? '';
}

$stable_name = $s['stable_name'] !== '' ? $s['stable_name'] : SITE_NAME;
$nickname    = $s['owner_nickname']  ?? '';
$vrl_id      = $s['owner_vrl_id']    ?? '';
$email       = $s['owner_email']     ?? '';
$forum_url   = $s['owner_forum_url'] ?? '';

require resolveThemePath('pages/yhteystiedot.php');
