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
    $pd = $db->prepare("SELECT entity_type, entity_id FROM pending_deletions WHERE id = :id AND status = 'pending'");
    $pd->execute([':id' => $id]);
    $row = $pd->fetch();
    if ($row) {
        // Whitelist-mappaus taulunimeen — EI dynaamista SQL:ää syötteestä (T-13-10)
        $table = entityTypeToTable($row['entity_type']);

        $db->beginTransaction();
        $db->prepare("UPDATE `$table` SET is_deleted = 0, deleted_at = NULL WHERE id = :eid")
           ->execute([':eid' => $row['entity_id']]);
        $db->prepare(
            "UPDATE pending_deletions SET status = 'rejected', reviewed_by = :by, reviewed_at = NOW() WHERE id = :id"
        )->execute([':by' => $_SESSION['admin_id'], ':id' => $id]);
        $db->commit();
    }
}
// Rivi säilytetään aina auditointia varten (D-05) — ei koskaan DELETE pending_deletions-riviltä.

redirect(SITE_URL . '/admin/deletions.php?rejected=1');
