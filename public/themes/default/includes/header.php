<?php
/**
 * Sivupohjan yläosa — sisällytetään joka sivulla
 *
 * Vaatii ennen include:a:
 *   $page_title = 'Sivun otsikko';
 *   require_once __DIR__ . '/../src/includes/header.php';
 */

// Varmista että config on ladattu
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/config.php';
}

// Haetaan tallin nimi ja väriteema tietokannasta (kerran per pyyntö)
// Käytetään erillistä lippua jotta sivu-tason $stable_name-muuttuja ei sekoita $GLOBALS-tarkistusta
if (!isset($GLOBALS['_vt_settings_loaded'])) {
    try {
        $db = getDB();
        $rows = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('stable_name','color_theme')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $GLOBALS['stable_name']         = (!empty($rows['stable_name']))  ? $rows['stable_name']  : SITE_NAME;
        $GLOBALS['color_theme']         = (!empty($rows['color_theme']))  ? $rows['color_theme']  : 'savi';
        $GLOBALS['_vt_settings_loaded'] = true;
    } catch (Exception $e) {
        $GLOBALS['stable_name']         = SITE_NAME;
        $GLOBALS['color_theme']         = 'savi';
        $GLOBALS['_vt_settings_loaded'] = true;
    }
}
$site_display_name = $GLOBALS['stable_name'];
$color_theme       = $GLOBALS['color_theme'];

// XSS-suojaus: sanitoi $page_title
$page_title = isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') : $site_display_name;
?>
<!DOCTYPE html>
<html lang="fi" data-theme="<?= htmlspecialchars($color_theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?> — <?= htmlspecialchars($site_display_name, ENT_QUOTES, 'UTF-8') ?></title>
  <!-- TODO WR-02: Replace hardcoded path with THEME_URL once theme CSS integration is complete.
       Current: always loads /assets/css/style.css regardless of active theme.
       Target:  e(THEME_URL) . 'assets/css/style.css'  (requires theme.php to be loaded first)
       Until then, theme.php constants (THEME_PATH, THEME_URL, THEMES_ROOT) are defined
       but have no effect on rendered output. -->
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<header>
  <div class="site-header">
    <a href="<?= SITE_URL ?>/" class="site-title"><?= htmlspecialchars($site_display_name, ENT_QUOTES, 'UTF-8') ?></a>
  </div>
  <?php require_once __DIR__ . '/nav.php'; ?>
</header>
