<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(SITE_URL . '/admin/stables.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    // Hevosia ei poisteta: FK (ON DELETE SET NULL) irrottaa ne tallista
    $del = getDB()->prepare('DELETE FROM stables WHERE id = :id');
    $del->execute([':id' => $id]);
}
redirect(SITE_URL . '/admin/stables.php?deleted=1');
