<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$db = getDB();

$reviews = $db->query(
    'SELECT q.id, q.review_date, q.country, q.organizer, q.organizer_url, q.placement, q.points,
            h.id AS horse_id, h.name AS horse_name,
            jc.nickname AS judge_nickname, jc.stable_name AS judge_stable_name, jc.email AS judge_email
     FROM quality_reviews q
     JOIN horses h ON h.id = q.horse_id AND h.is_deleted = 0
     LEFT JOIN contacts jc ON jc.id = q.judge_contact_id
     WHERE q.is_deleted = 0
     ORDER BY q.review_date DESC, h.name ASC'
)->fetchAll();

$pageTitle = 'Laatuarvostelut';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Laatuarvostelut</h1>
  <span style="font-size:0.78rem;color:var(--color-text-muted)"><?= count($reviews) ?> merkintää</span>
</div>
<div class="admin-body">

<?php if (empty($reviews)): ?>
  <p style="color:var(--color-text-muted)">Ei laatuarvosteluja. Lisää arvosteluja hevosen omalta Laatuarvostelut-sivulta.</p>
<?php else: ?>
<div class="compact-list">
  <div class="compact-list-header" style="grid-template-columns:1.5fr 1.5fr 1fr 80px 80px 60px">
    <div>Laatuarvostelun jaos</div><div>Hevonen</div><div>Tuomari</div><div>Päivämäärä</div><div>Tulos</div><div>Pisteet</div>
  </div>
  <?php foreach ($reviews as $q):
    $judgeLabel = trim(($q['judge_nickname'] ?? '') . ' ' . ($q['judge_stable_name'] ?? ''));
    $judgeHtml  = $judgeLabel !== '' && !empty($q['judge_email'])
        ? '<a href="mailto:' . e($q['judge_email']) . '">' . e($judgeLabel) . '</a>'
        : e($judgeLabel);
  ?>
  <div class="compact-list-row" style="grid-template-columns:1.5fr 1.5fr 1fr 80px 80px 60px">
    <div>
      <?php if (!empty($q['organizer_url'])): ?>
        <a href="<?= e($q['organizer_url']) ?>" target="_blank" rel="noopener" class="cl-name"><?= e($q['organizer'] ?? '—') ?></a>
      <?php else: ?>
        <div class="cl-name"><?= e($q['organizer'] ?? '—') ?></div>
      <?php endif; ?>
      <?php if ($q['country']): ?><div class="cl-meta"><?= e($q['country']) ?></div><?php endif; ?>
    </div>
    <div>
      <a href="<?= e(SITE_URL) ?>/admin/quality_reviews.php?horse_id=<?= (int)$q['horse_id'] ?>"
         class="cl-name" style="text-decoration:none"><?= e($q['horse_name']) ?></a>
    </div>
    <div class="cl-meta"><?= $judgeLabel !== '' ? $judgeHtml : '—' ?></div>
    <div class="cl-meta"><?= $q['review_date'] ? formatDate($q['review_date']) : '—' ?></div>
    <div><span class="pbadge pbadge-x"><?= ($q['placement'] ?? '') !== '' ? e($q['placement']) : '—' ?></span></div>
    <div class="cl-meta"><?= $q['points'] !== null ? e((string)$q['points']) : '—' ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</div><!-- /.admin-body -->
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
