<?php
/**
 * Juuri: päätallin etusivu, tai jos päätallia ei ole valittu → pages/index.php
 */
require_once __DIR__ . '/src/includes/db.php';

$_vt_main = mainStable();
if ($_vt_main) {
    $_vt_route = ['slug' => $_vt_main['slug'], 'sivu' => ''];
    require __DIR__ . '/pages/talli.php';
    exit;
}
header('Location: ' . SITE_URL . '/pages/index.php', true, 302);
exit;
