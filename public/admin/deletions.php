<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');
$_activePage = 'deletions';

$db = getDB();
$pending = $db->query(
    "SELECT pd.id, pd.entity_type, pd.entity_id, pd.requested_at, u.username AS requested_by_name,
            COALESCE(h.name, f.foal_name,
                     CONCAT(c.discipline, ' ', c.competition_date),
                     CONCAT(s.discipline, ' ', s.show_date),
                     p.title,
                     CONCAT(pd.entity_type, ' #', pd.entity_id)) AS entity_label
     FROM pending_deletions pd
     JOIN admin_users u ON u.id = pd.requested_by
     LEFT JOIN horses h ON pd.entity_type = 'horse' AND h.id = pd.entity_id
     LEFT JOIN foals f ON pd.entity_type = 'foal' AND f.id = pd.entity_id
     LEFT JOIN competitions c ON pd.entity_type = 'competition' AND c.id = pd.entity_id
     LEFT JOIN showrecords s ON pd.entity_type = 'showrecord' AND s.id = pd.entity_id
     LEFT JOIN posts p ON pd.entity_type = 'post' AND p.id = pd.entity_id
     WHERE pd.status = 'pending'
     ORDER BY pd.requested_at DESC"
)->fetchAll();

$entityTypeLabels = [
    'horse'       => 'Hevonen',
    'foal'        => 'Varsa',
    'competition' => 'Kilpailu',
    'showrecord'  => 'Näyttelytulos',
    'post'        => 'Postaus',
];

$flash = '';
if (isset($_GET['approved'])) $flash = '<p class="flash-ok">Poistopyyntö hyväksytty.</p>';
if (isset($_GET['rejected'])) $flash = '<p class="flash-ok">Poistopyyntö hylätty, sisältö palautettu näkyväksi.</p>';

$pageTitle = 'Poistopyynnöt';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Poistopyynnöt</h1>
</div>
<div class="admin-body">
<?= $flash ?>
<?php if (empty($pending)): ?>
  <p>Ei odottavia poistopyyntöjä.</p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>Tyyppi</th>
      <th>Kohde</th>
      <th>Pyytäjä</th>
      <th>Pyydetty</th>
      <th>Toiminnot</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($pending as $row): ?>
    <tr>
      <td><?= e($entityTypeLabels[$row['entity_type']] ?? $row['entity_type']) ?></td>
      <td><?= e($row['entity_label'] ?? ('#' . (int)$row['entity_id'])) ?></td>
      <td><?= e($row['requested_by_name']) ?></td>
      <td><?= formatDate($row['requested_at']) ?></td>
      <td>
        <form method="post" action="<?= e(SITE_URL) ?>/admin/deletion_approve.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <button type="submit" class="btn-sm">Hyväksy</button>
        </form>
        <form method="post" action="<?= e(SITE_URL) ?>/admin/deletion_reject.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <button type="submit" class="btn-sm btn-danger">Hylkää</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
