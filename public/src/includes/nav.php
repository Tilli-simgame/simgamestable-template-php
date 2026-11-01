<?php
/**
 * Navigaatio — sisällytetään header.php:n kautta
 * Käytetään REQUEST_URI:ta aktiivisen linkin tunnistamiseen
 *
 * Tallikontekstissa linkit osoittavat tallin sivuille (päätalli: /hevoset, muut: /talli/hevoset).
 */
$_nav_stable = $GLOBALS['vt_stable'] ?? null;
$_nav_url    = fn(string $page) => $_nav_stable ? stablePageUrl($_nav_stable, $page) : SITE_URL . '/' . $page;
$_nav_links  = [
    ['Etusivu',       $_nav_stable ? stableUrl($_nav_stable) : SITE_URL . '/', ['/pages/index']],
    ['Hevoset',       $_nav_url('hevoset'),       ['/pages/hevoset']],
    ['Kasvatus',      $_nav_url('kasvatus'),      ['/pages/kasvatus']],
    ['Ajankohtaista', $_nav_url('ajankohtaista'), ['/pages/ajankohtaista']],
    ['Yhteystiedot',  $_nav_url('yhteystiedot'),  ['/pages/yhteystiedot']],
];
$uri = rtrim(strtok($_SERVER['REQUEST_URI'], '?'), '/');
$_nav_base = rtrim(parse_url(SITE_URL, PHP_URL_PATH) ?? '', '/');
?>
<nav>
  <ul>
    <?php foreach ($_nav_links as $i => [$label, $url, $aliases]):
      $path   = rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
      $active = $uri === $path || in_array(substr($uri, strlen($_nav_base)), $aliases, true)
             || ($i > 0 && str_starts_with($uri, $path . '/')); ?>
    <li><a href="<?= e($url) ?>"<?= $active ? ' class="active"' : '' ?>><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>
</nav>
