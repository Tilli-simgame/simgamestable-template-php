<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$horse_id = (int)($_GET['horse_id'] ?? 0);
if ($horse_id <= 0) {
    redirect(SITE_URL . '/admin/horses.php');
}

$db = getDB();
$horseStmt = $db->prepare('SELECT id, name FROM horses WHERE id = :id AND is_deleted = 0');
$horseStmt->execute([':id' => $horse_id]);
$horse = $horseStmt->fetch();
if (!$horse) {
    redirect(SITE_URL . '/admin/horses.php');
}

$contacts     = $db->query('SELECT * FROM contacts ORDER BY nickname, stable_name')->fetchAll();
$contactsJson = json_encode(array_map(fn($c) => [
    'id'          => $c['id'],
    'label'       => trim(($c['nickname'] ?? '') . ' ' . ($c['stable_name'] ?? '')),
    'nickname'    => $c['nickname']    ?? '',
    'stable_name' => $c['stable_name'] ?? '',
    'stable_url'  => $c['stable_url']  ?? '',
    'vrl_id'      => $c['vrl_id']      ?? '',
    'email'       => $c['email']       ?? '',
    'country'     => $c['country']     ?? '',
], $contacts), JSON_UNESCAPED_UNICODE);

$errors  = [];
$flash   = '';

// POST-käsittely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $review_id = (int)($_POST['review_id'] ?? 0);

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        $params = [
            ':review_date'      => sanitize($_POST['review_date'] ?? '') ?: null,
            ':country'          => sanitize($_POST['country'] ?? '') ?: null,
            ':organizer'        => sanitize($_POST['organizer'] ?? '') ?: null,
            ':organizer_url'    => sanitize($_POST['organizer_url'] ?? '') ?: null,
            ':placement'        => sanitize($_POST['placement'] ?? '') ?: null,
            ':points'           => is_numeric($_POST['points'] ?? '') ? (float)$_POST['points'] : null,
            ':judge_contact_id' => ($_POST['judge_contact_id'] ?? '') !== '' ? (int)$_POST['judge_contact_id'] : null,
            ':review'           => sanitize($_POST['review'] ?? '') ?: null,
            ':notes'            => sanitize($_POST['notes'] ?? '') ?: null,
        ];
        if ($action === 'add') {
            $stmt = $db->prepare(
                'INSERT INTO quality_reviews (horse_id, review_date, country, organizer, organizer_url, placement, points, judge_contact_id, review, notes)
                 VALUES (:horse_id, :review_date, :country, :organizer, :organizer_url, :placement, :points, :judge_contact_id, :review, :notes)'
            );
            $stmt->execute($params + [':horse_id' => $horse_id]);
            redirect(SITE_URL . '/admin/quality_reviews.php?horse_id=' . $horse_id . '&added=1');
        } elseif ($action === 'edit' && $review_id > 0) {
            // Omistajuustarkistus
            $own = $db->prepare('SELECT id FROM quality_reviews WHERE id = :review_id AND horse_id = :horse_id');
            $own->execute([':review_id' => $review_id, ':horse_id' => $horse_id]);
            if ($own->fetch()) {
                $stmt = $db->prepare(
                    'UPDATE quality_reviews SET review_date=:review_date, country=:country, organizer=:organizer,
                     organizer_url=:organizer_url, placement=:placement, points=:points,
                     judge_contact_id=:judge_contact_id, review=:review, notes=:notes WHERE id=:review_id'
                );
                $stmt->execute($params + [':review_id' => $review_id]);
                redirect(SITE_URL . '/admin/quality_reviews.php?horse_id=' . $horse_id . '&updated=1');
            }
        } elseif ($action === 'delete' && $review_id > 0) {
            $own = $db->prepare('SELECT id FROM quality_reviews WHERE id = :review_id AND horse_id = :horse_id');
            $own->execute([':review_id' => $review_id, ':horse_id' => $horse_id]);
            if ($own->fetch()) {
                $delStmt = $db->prepare('UPDATE quality_reviews SET is_deleted = 1, deleted_at = NOW() WHERE id = :review_id AND is_deleted = 0');
                $delStmt->execute([':review_id' => $review_id]);
                if ($delStmt->rowCount() > 0 && currentRole() === 'mod') {
                    insertPendingDeletion('qualityreview', $review_id, (int)$_SESSION['admin_id']);
                }
            }
            redirect(SITE_URL . '/admin/quality_reviews.php?horse_id=' . $horse_id . '&deleted=1');
        }
    }
}

// Hae laatuarvostelut
$reviewsStmt = $db->prepare(
    'SELECT q.*, jc.nickname AS judge_nickname, jc.stable_name AS judge_stable_name, jc.email AS judge_email
     FROM quality_reviews q LEFT JOIN contacts jc ON jc.id = q.judge_contact_id
     WHERE q.horse_id = :horse_id AND q.is_deleted = 0 ORDER BY q.review_date DESC'
);
$reviewsStmt->execute([':horse_id' => $horse_id]);
$reviews = $reviewsStmt->fetchAll();

if (isset($_GET['added']))   $flash = '<p class="flash-ok">Laatuarvostelu lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Laatuarvostelu päivitetty.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Laatuarvostelu poistettu.</p>';

$pageTitle = 'Laatuarvostelut';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="back-link">← Hevoset</a>
  <h1>Laatuarvostelut</h1>
  <div class="page-actions">
    <button class="btn" onclick="resetReviewModal();adminOpenModal('review')">+ Lisää laatuarvostelu</button>
  </div>
</div>

<div class="horse-ctx-banner">
  <span class="hcb-name">⭐ <?= e($horse['name']) ?></span>
  <span class="hcb-meta"><?= count($reviews) ?> laatuarvostelua</span>
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="hcb-back">← Hevoslistaan</a>
</div>

<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $emsg): ?><li><?= e($emsg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?= $flash ?>

<div class="comp-stat-row">
  <div class="comp-stat-card">
    <div class="cs-num"><?= count($reviews) ?></div>
    <div class="cs-label">Laatuarvostelua</div>
  </div>
</div>

<?php if ($reviews): ?>
<div class="compact-list">
  <div class="compact-list-header" style="grid-template-columns:1fr 1fr 80px 80px 28px">
    <div>Laatuarvostelun jaos</div><div>Tuomari</div><div>Päivämäärä</div><div>Tulos</div><div></div>
  </div>
  <?php foreach ($reviews as $q):
    $judgeLabel = trim(($q['judge_nickname'] ?? '') . ' ' . ($q['judge_stable_name'] ?? ''));
    $judgeHtml  = $judgeLabel !== '' && !empty($q['judge_email'])
        ? '<a href="mailto:' . e($q['judge_email']) . '">' . e($judgeLabel) . '</a>'
        : e($judgeLabel);
  ?>
  <div class="compact-list-row" style="grid-template-columns:1fr 1fr 80px 80px 28px"
       onclick="adminToggleExpand('q<?= (int)$q['id'] ?>')">
    <div class="cl-name"><?= e($q['organizer'] ?? '—') ?></div>
    <div class="cl-meta"><?= $judgeLabel !== '' ? e($judgeLabel) : '—' ?></div>
    <div class="cl-meta"><?= $q['review_date'] ? formatDate($q['review_date']) : '—' ?></div>
    <div><span class="pbadge pbadge-x"><?= ($q['placement'] ?? '') !== '' ? e($q['placement']) : '—' ?></span></div>
    <div>
      <button class="cl-expand-btn" id="cl-btn-q<?= (int)$q['id'] ?>"
              onclick="event.stopPropagation();adminToggleExpand('q<?= (int)$q['id'] ?>')">▸</button>
    </div>
  </div>
  <div class="cl-expanded" id="cl-exp-q<?= (int)$q['id'] ?>">
    <?php if ($q['country'] || $q['organizer_url'] || $q['points'] !== null || $judgeLabel || $q['review'] || $q['notes']): ?>
      <dl style="font-size:0.8rem;color:var(--color-text-muted);margin:0 0 0.5rem;display:flex;flex-wrap:wrap;gap:0.25rem 1.5rem">
        <?php if ($q['country']): ?><div><dt style="display:inline;font-weight:600">Maa:</dt> <dd style="display:inline"><?= e($q['country']) ?></dd></div><?php endif; ?>
        <?php if ($q['organizer_url']): ?><div><dt style="display:inline;font-weight:600">Tilaisuus:</dt> <dd style="display:inline"><a href="<?= e($q['organizer_url']) ?>" target="_blank" rel="noopener"><?= e($q['organizer_url']) ?></a></dd></div><?php endif; ?>
        <?php if ($q['points'] !== null): ?><div><dt style="display:inline;font-weight:600">Pisteet:</dt> <dd style="display:inline"><?= e((string)$q['points']) ?></dd></div><?php endif; ?>
        <?php if ($judgeLabel): ?><div><dt style="display:inline;font-weight:600">Tuomari:</dt> <dd style="display:inline"><?= $judgeHtml ?></dd></div><?php endif; ?>
        <?php if ($q['review']): ?><div style="width:100%"><dt style="display:inline;font-weight:600">Arvostelu:</dt> <dd style="display:inline"><?= nl2br(e($q['review'])) ?></dd></div><?php endif; ?>
        <?php if ($q['notes']): ?><div style="width:100%"><dt style="display:inline;font-weight:600">Huom:</dt> <dd style="display:inline"><?= e($q['notes']) ?></dd></div><?php endif; ?>
      </dl>
    <?php endif; ?>
    <div class="cl-expanded-actions">
      <button class="btn-sm btn-edit" onclick="openEditReview(<?= (int)$q['id'] ?>, <?= htmlspecialchars(json_encode($q + ['judge_label' => $judgeLabel]), ENT_QUOTES) ?>)">✏️ Muokkaa</button>
      <form method="post" action="?horse_id=<?= $horse_id ?>" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action"    value="delete">
        <input type="hidden" name="review_id" value="<?= (int)$q['id'] ?>">
        <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Poistetaanko laatuarvostelu?')">🗑 Poista</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <p style="color:var(--color-text-muted);margin:1rem 0">Ei laatuarvosteluja.</p>
<?php endif; ?>
</div><!-- /.admin-body -->

<!-- ── MODAL: Lisää/muokkaa laatuarvostelu ── -->
<div class="admin-modal-overlay" id="modal-overlay-review">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2 id="modal-review-title">Lisää laatuarvostelu</h2>
      <button class="admin-modal-close" onclick="adminCloseModal('review')">×</button>
    </div>
    <form method="post" action="?horse_id=<?= $horse_id ?>">
      <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
      <input type="hidden" name="action"    id="modal-review-action" value="add">
      <input type="hidden" name="review_id" id="modal-review-id"     value="">
      <div class="admin-modal-body">
        <div class="form-row">
          <div class="form-group">
            <label for="review_date">PVM</label>
            <input type="date" id="review_date" name="review_date">
          </div>
          <div class="form-group">
            <label for="country">Maa</label>
            <input type="text" id="country" name="country" placeholder="esim. Suomi, Ruotsi…">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="organizer">Laatuarvostelun jaos</label>
            <input type="text" id="organizer" name="organizer" placeholder="esim. KTK, YLA…">
          </div>
          <div class="form-group">
            <label for="organizer_url">Tilaisuuden osoite</label>
            <input type="url" id="organizer_url" name="organizer_url" placeholder="https://…">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="placement">Tulos</label>
            <input type="text" id="placement" name="placement" placeholder="esim. KTK-III, YLA2…">
          </div>
          <div class="form-group">
            <label for="points">Pisteet</label>
            <input type="number" id="points" name="points" step="0.01" min="0" placeholder="esim. 64">
          </div>
        </div>
        <div class="form-group">
          <label>Tuomari</label>
          <div class="ac-wrap contact-ac"
               data-items='<?= htmlspecialchars($contactsJson, ENT_QUOTES) ?>'
               data-input-id="judge_contact"
               data-hidden-name="judge_contact_id"
               data-current-id=""
               data-current-label=""
               data-preview-target="judge-preview"
               data-placeholder="Hae osoitekirjasta nimimerkillä tai tallin nimellä..."></div>
          <div id="judge-preview" class="contact-preview" style="display:none"></div>
        </div>
        <div class="form-group">
          <label for="review">Sanallinen arvostelu</label>
          <textarea id="review" name="review" rows="4" placeholder="Tuomarin sanallinen arvio…"></textarea>
        </div>
        <div class="form-group">
          <label for="notes">Huom</label>
          <textarea id="notes" name="notes"></textarea>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="submit" class="btn" id="modal-review-btn">Lisää laatuarvostelu</button>
        <button type="button" class="btn-ghost" onclick="adminCloseModal('review')">Peruuta</button>
      </div>
    </form>
  </div>
</div>

<script>
function setAcValue(inputId, id, label) {
  var textEl   = document.getElementById(inputId + '_text');
  var hiddenEl = document.getElementById(inputId);
  if (textEl)   textEl.value   = label || '';
  if (hiddenEl) hiddenEl.value = id    || '';
}

function resetReviewModal() {
  document.getElementById('modal-review-title').textContent = 'Lisää laatuarvostelu';
  document.getElementById('modal-review-action').value      = 'add';
  document.getElementById('modal-review-id').value          = '';
  ['review_date','country','organizer','organizer_url','placement','points','review','notes']
    .forEach(f => { const el = document.getElementById(f); if (el) el.value = ''; });
  setAcValue('judge_contact', '', '');
  document.getElementById('judge-preview').style.display = 'none';
  document.getElementById('modal-review-btn').textContent = 'Lisää laatuarvostelu';
}

function openEditReview(id, data) {
  document.getElementById('modal-review-title').textContent = 'Muokkaa laatuarvostelua';
  document.getElementById('modal-review-action').value      = 'edit';
  document.getElementById('modal-review-id').value          = id;
  ['review_date','country','organizer','organizer_url','placement','points','review','notes']
    .forEach(f => { document.getElementById(f).value = data[f] || ''; });
  setAcValue('judge_contact', data.judge_contact_id, data.judge_label || '');
  var preview = document.getElementById('judge-preview');
  if (data.judge_contact_id) {
    var card = document.createElement('div');
    card.className = 'contact-card';
    if (data.judge_email) {
      var link = document.createElement('a');
      link.href = 'mailto:' + data.judge_email;
      link.textContent = data.judge_label || '';
      card.appendChild(link);
    } else {
      card.textContent = data.judge_label || '';
    }
    preview.replaceChildren(card);
    preview.style.display = 'block';
  } else {
    preview.style.display = 'none';
  }
  document.getElementById('modal-review-btn').textContent = 'Tallenna muutokset';
  adminOpenModal('review');
}
</script>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
