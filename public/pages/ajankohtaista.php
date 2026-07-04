<?php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/../src/includes/theme.php';

// Jos aktiivisella teemalla on oma ajankohtaista-sivu, käytetään sitä
$_vt_themeFile = resolveThemePath('ajankohtaista.php');
if ($_vt_themeFile !== false
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}

$db = getDB();

// Arkistosuodatus — parametrit validoidaan (int)-castingilla (T-05-08)
$yearFilter  = isset($_GET['year'])  ? (int)$_GET['year']  : 0;
$monthFilter = isset($_GET['month']) ? (int)$_GET['month'] : 0;

if ($yearFilter > 0 && $monthFilter > 0) {
    $stmt = $db->prepare(
        'SELECT id, title, slug, content, created_at
         FROM posts
         WHERE YEAR(created_at) = :y AND MONTH(created_at) = :m
         ORDER BY created_at DESC'
    );
    $stmt->execute([':y' => $yearFilter, ':m' => $monthFilter]);
} elseif ($yearFilter > 0) {
    $stmt = $db->prepare(
        'SELECT id, title, slug, content, created_at
         FROM posts
         WHERE YEAR(created_at) = :y
         ORDER BY created_at DESC'
    );
    $stmt->execute([':y' => $yearFilter]);
} else {
    $stmt = $db->query(
        'SELECT id, title, slug, content, created_at
         FROM posts
         ORDER BY created_at DESC'
    );
}
$posts = $stmt->fetchAll();

// Arkistokysely sidebarille
$stmtArchive = $db->query(
    'SELECT YEAR(created_at) AS yr, MONTH(created_at) AS mo, COUNT(*) AS cnt
     FROM posts
     GROUP BY YEAR(created_at), MONTH(created_at)
     ORDER BY yr DESC, mo DESC'
);
$archive = [];
foreach ($stmtArchive->fetchAll() as $row) {
    $archive[$row['yr']][$row['mo']] = (int)$row['cnt'];
}

$_themePage = resolveThemePath('pages/ajankohtaista.php');
if ($_themePage === false) {
    http_response_code(404);
    exit;
}
require $_themePage;
