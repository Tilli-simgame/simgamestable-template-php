<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/deletions.php');
}
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(SITE_URL . '/admin/deletions.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $db = getDB();
    $db->prepare(
        "UPDATE pending_deletions SET status = 'approved', reviewed_by = :by, reviewed_at = NOW()
         WHERE id = :id AND status = 'pending'"
    )->execute([':by' => $_SESSION['admin_id'], ':id' => $id]);
}
// Sisältöä ei muuteta — se on jo is_deleted=1 (D-05). Vain jonon tila päivittyy.

redirect(SITE_URL . '/admin/deletions.php?approved=1');
