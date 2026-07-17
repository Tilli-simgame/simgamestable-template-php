<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod', 'author');

// Hyväksy vain POST (T-05-05)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/posts.php');
}

// Tarkista CSRF-token (T-05-04)
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(SITE_URL . '/admin/posts.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    redirect(SITE_URL . '/admin/posts.php');
}

$db = getDB();
$ownerChk = $db->prepare('SELECT author_id FROM posts WHERE id = :id');
$ownerChk->execute([':id' => $id]);
$ownerRow = $ownerChk->fetch();
if (!$ownerRow) {
    redirect(SITE_URL . '/admin/posts.php');
}

// IDOR-esto — sama malli kuin posts.php rivit 39-46: author läpäisee vain oman postauksensa
requireOwnResourceOrAdmin((int)$ownerRow['author_id']);

$stmt = $db->prepare('UPDATE posts SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0');
$stmt->execute([':id' => $id]);

if ($stmt->rowCount() > 0 && currentRole() === 'mod') {
    insertPendingDeletion('post', $id, (int)$_SESSION['admin_id']);
}
// admin ja author-oman-postauksen-poisto: suora soft-delete, ei pending-riviä (D-04, AUTHOR-03)

redirect(SITE_URL . '/admin/posts.php?deleted=1');
