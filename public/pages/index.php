<?php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/../src/includes/theme.php';

// Jos aktiivisella teemalla on oma etusivu, käytetään sitä
$_vt_themeFile = resolveThemePath('index.php');
if ($_vt_themeFile !== false
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}

$db = getDB();

// Hevosmäärä
$stmtCount = $db->query('SELECT COUNT(*) FROM horses WHERE is_deleted = 0 AND evm = 0 AND ancestor = 0');
$horseCount = (int)$stmtCount->fetchColumn();

// Varsoja tänä vuonna
$thisYear = (int)date('Y');
$stmtFoals = $db->prepare('SELECT COUNT(*) FROM foals WHERE YEAR(birth_date) = :y');
$stmtFoals->execute([':y' => $thisYear]);
$foalCount = (int)$stmtFoals->fetchColumn();

// Uusin postaus etusivun korttia varten (T-05-07: try/catch graceful degradation)
$latestPost = null;
try {
    $stmtPost = $db->query(
        'SELECT title, slug, content, created_at FROM posts ORDER BY created_at DESC LIMIT 1'
    );
    $latestPost = $stmtPost->fetch() ?: null;
} catch (PDOException $e) {
    // Taulu ei vielä olemassa — näytetään placeholder
    $latestPost = null;
}

$_themePage = resolveThemePath('pages/index.php');
if ($_themePage === false) {
    http_response_code(404);
    exit;
}
require $_themePage;
