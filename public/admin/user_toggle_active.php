<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL . '/admin/users.php');
}
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(SITE_URL . '/admin/users.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) redirect(SITE_URL . '/admin/users.php');

$db = getDB();
$stmt = $db->prepare('SELECT id, role, is_active FROM admin_users WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) redirect(SITE_URL . '/admin/users.php');

$isDeactivating = ((int)$row['is_active'] === 1);

if ($isDeactivating) {
    // Self-guard (USER-07): admin ei voi deaktivoida omaa tunnustaan
    if ($id === (int)$_SESSION['admin_id']) {
        redirect(SITE_URL . '/admin/users.php?err=self');
    }

    // Last-admin-guard (USER-06): viimeistä aktiivista admin-tunnusta ei saa deaktivoida
    if ($row['role'] === 'admin') {
        $count = $db->prepare("SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND is_active = 1");
        $count->execute();
        if ((int)$count->fetchColumn() <= 1) {
            redirect(SITE_URL . '/admin/users.php?err=lastadmin');
        }
    }
}

$db->prepare('UPDATE admin_users SET is_active = 1 - is_active WHERE id = :id')
   ->execute([':id' => $id]);

redirect(SITE_URL . '/admin/users.php?' . ($isDeactivating ? 'deactivated=1' : 'activated=1'));
