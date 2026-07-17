<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$db = getDB();

$showrecords = $db->query(
    'SELECT s.id, s.show_date, s.discipline, s.country, s.organizer, s.organizer_url, s.class, s.placement, s.points, s.review, s.notes,
            h.id AS horse_id, h.name AS horse_name,
            jc.nickname AS judge_nickname, jc.stable_name AS judge_stable_name, jc.email AS judge_email,
            p.filename AS photo_filename, p.title AS photo_title, p.original_name AS photo_original_name
     FROM showrecords s
     JOIN horses h ON h.id = s.horse_id AND h.is_deleted = 0
     LEFT JOIN contacts jc ON jc.id = s.judge_contact_id
     LEFT JOIN horse_photos p ON p.id = s.photo_id
     WHERE s.is_deleted = 0
     ORDER BY s.show_date DESC, h.name ASC'
)->fetchAll();

$wins = count(array_filter($showrecords, fn($s) => $s['placement'] === '1.'));

$pageTitle = 'Näyttelyt';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Näyttelyt</h1>
  <span style="font-size:0.78rem;color:var(--color-text-muted)"><?= count($showrecords) ?> merkintää</span>
</div>
<div class="admin-body">

<div class="comp-stat-row">
  <div class="comp-stat-card">
    <div class="cs-num"><?= count($showrecords) ?></div>
    <div class="cs-label">Näyttelytulosta</div>
  </div>
  <div class="comp-stat-card">
    <div class="cs-num"><?= $wins ?></div>
    <div class="cs-label">Voittoa</div>
  </div>
</div>

<?php if (empty($showrecords)): ?>
  <p style="color:var(--color-text-muted)">Ei näyttelytuloksia. Lisää tuloksia hevosen omalta Näyttelyt-sivulta.</p>
<?php else: ?>
<div class="compact-list">
  <div class="compact-list-header" style="grid-template-columns:1.5fr 1.5fr 1fr 1fr 1fr 80px 60px">
    <div>Järjestäjä</div><div>Hevonen</div><div>Laji</div><div>Luokka</div><div>Tuomari</div><div>Päivämäärä</div><div>Tulos</div>
  </div>
  <?php foreach ($showrecords as $s):
    $pl = $s['placement'] ?? '';
    $pbClass = match($pl) { '1.' => 'pbadge-1', '2.' => 'pbadge-2', '3.' => 'pbadge-3', default => 'pbadge-x' };
    $judgeLabel = trim(($s['judge_nickname'] ?? '') . ' ' . ($s['judge_stable_name'] ?? ''));
    $judgeHtml  = $judgeLabel !== '' && !empty($s['judge_email'])
        ? '<a href="mailto:' . e($s['judge_email']) . '">' . e($judgeLabel) . '</a>'
        : e($judgeLabel);
  ?>
  <div class="compact-list-row" style="grid-template-columns:1.5fr 1.5fr 1fr 1fr 1fr 80px 60px">
    <div>
      <?php if (!empty($s['photo_filename'])): ?>
        <img class="sr-photo-thumb" src="<?= e(UPLOADS_URL . $s['photo_filename']) ?>" alt="" title="<?= e($s['photo_title'] ?? $s['photo_original_name'] ?? '') ?>">
      <?php endif; ?>
      <?php if (!empty($s['organizer_url'])): ?>
        <a href="<?= e($s['organizer_url']) ?>" target="_blank" rel="noopener" class="cl-name"><?= e($s['organizer'] ?? '—') ?></a>
      <?php else: ?>
        <div class="cl-name"><?= e($s['organizer'] ?? '—') ?></div>
      <?php endif; ?>
      <?php if ($s['country']): ?><div class="cl-meta"><?= e($s['country']) ?></div><?php endif; ?>
    </div>
    <div>
      <a href="<?= e(SITE_URL) ?>/admin/showrecords.php?horse_id=<?= (int)$s['horse_id'] ?>"
         class="cl-name" style="text-decoration:none"><?= e($s['horse_name']) ?></a>
    </div>
    <div class="cl-meta"><?= e($s['discipline'] ?? '—') ?></div>
    <div class="cl-meta"><?= e($s['class'] ?? '—') ?></div>
    <div class="cl-meta"><?= $judgeLabel !== '' ? $judgeHtml : '—' ?></div>
    <div class="cl-meta"><?= $s['show_date'] ? formatDate($s['show_date']) : '—' ?></div>
    <div><span class="pbadge <?= $pbClass ?>"><?= $pl !== '' ? e($pl) : '—' ?></span></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
