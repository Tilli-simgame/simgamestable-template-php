<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/contacts.php');
}
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(SITE_URL . '/admin/contacts.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) redirect(SITE_URL . '/admin/contacts.php');

$db = getDB();

// Ei saa poistaa jos käytössä
$count = $db->prepare(
    'SELECT COUNT(*) FROM horses
     WHERE owner_contact_id = :id OR breeder_contact_id = :id OR importer_contact_id = :id'
);
$count->execute([':id' => $id]);
if ((int)$count->fetchColumn() > 0) {
    redirect(SITE_URL . '/admin/contacts.php');
}

$db->prepare('DELETE FROM contacts WHERE id = :id')->execute([':id' => $id]);
redirect(SITE_URL . '/admin/contacts.php?deleted=1');
