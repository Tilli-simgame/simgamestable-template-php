<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

// Hyväksy vain POST (T-05-05)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/posts.php');
}

// Tarkista CSRF-token (T-05-04)
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(SITE_URL . '/admin/posts.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $db = getDB();
    $db->prepare('DELETE FROM posts WHERE id = :id')->execute([':id' => $id]);
}

redirect(SITE_URL . '/admin/posts.php?deleted=1');
