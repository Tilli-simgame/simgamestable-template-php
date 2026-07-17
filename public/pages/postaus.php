<?php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/../src/includes/theme.php';

// Jos aktiivisella teemalla on oma postaussivu, käytetään sitä
$_vt_themeFile = resolveThemePath('postaus.php');
if ($_vt_themeFile !== false
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}

$db = getDB();

// Hae postaus slugin tai id:n perusteella (T-05-01: slug sanitoitu preg_replace)
if (!empty($_GET['slug'])) {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['slug'])));
    $stmt = $db->prepare('SELECT * FROM posts WHERE slug = :slug AND is_deleted = 0');
    $stmt->execute([':slug' => $slug]);
} elseif (!empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare('SELECT * FROM posts WHERE id = :id AND is_deleted = 0');
    $stmt->execute([':id' => $id]);
} else {
    http_response_code(404);
    exit;
}

$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    exit;
}

$page_title = $post['title'];

// Edellinen postaus (vanhempi)
$stmtPrev = $db->prepare(
    'SELECT id, title, slug FROM posts
     WHERE created_at < :created_at AND is_deleted = 0 ORDER BY created_at DESC LIMIT 1'
);
$stmtPrev->execute([':created_at' => $post['created_at']]);
$prev = $stmtPrev->fetch();

// Seuraava postaus (uudempi)
$stmtNext = $db->prepare(
    'SELECT id, title, slug FROM posts
     WHERE created_at > :created_at AND is_deleted = 0 ORDER BY created_at ASC LIMIT 1'
);
$stmtNext->execute([':created_at' => $post['created_at']]);
$next = $stmtNext->fetch();

// Arkistokysely
$stmtArchive = $db->query(
    'SELECT YEAR(created_at) AS yr, MONTH(created_at) AS mo, COUNT(*) AS cnt
     FROM posts
     WHERE is_deleted = 0
     GROUP BY YEAR(created_at), MONTH(created_at)
     ORDER BY yr DESC, mo DESC'
);
$archiveRows = $stmtArchive->fetchAll();

// Rakenna nested array: $archive[$yr][$mo] = $cnt
$archive = [];
foreach ($archiveRows as $row) {
    $archive[$row['yr']][$row['mo']] = (int)$row['cnt'];
}

$_themePage = resolveThemePath('pages/postaus.php');
if ($_themePage === false) {
    http_response_code(404);
    exit;
}
require $_themePage;
