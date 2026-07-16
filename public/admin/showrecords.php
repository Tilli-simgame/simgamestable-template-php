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

$photosStmt = $db->prepare('SELECT id, filename, original_name, title FROM horse_photos WHERE horse_id = :horse_id ORDER BY sort_order ASC');
$photosStmt->execute([':horse_id' => $horse_id]);
$horsePhotos = $photosStmt->fetchAll();
$horsePhotoIds = array_column($horsePhotos, 'id');

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

$edit_id = (int)($_GET['edit'] ?? 0);
$errors  = [];
$flash   = '';

// POST-käsittely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $show_id = (int)($_POST['show_id'] ?? 0);

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        if ($action === 'add') {
                $photoId = ($_POST['photo_id'] ?? '') !== '' ? (int)$_POST['photo_id'] : null;
                if ($photoId !== null && !in_array($photoId, $horsePhotoIds, true)) $photoId = null;
                $stmt = $db->prepare(
                    'INSERT INTO showrecords (horse_id, show_date, discipline, country, organizer, organizer_url, class, placement, points, judge_contact_id, photo_id, review, notes)
                     VALUES (:horse_id, :show_date, :discipline, :country, :organizer, :organizer_url, :class, :placement, :points, :judge_contact_id, :photo_id, :review, :notes)'
                );
                $stmt->execute([
                    ':horse_id'          => $horse_id,
                    ':show_date'         => sanitize($_POST['show_date'] ?? '') ?: null,
                    ':discipline'        => sanitize($_POST['discipline'] ?? '') ?: null,
                    ':country'           => sanitize($_POST['country'] ?? '') ?: null,
                    ':organizer'         => sanitize($_POST['organizer'] ?? '') ?: null,
                    ':organizer_url'     => sanitize($_POST['organizer_url'] ?? '') ?: null,
                    ':class'             => sanitize($_POST['class'] ?? '') ?: null,
                    ':placement'         => sanitize($_POST['placement'] ?? '') ?: null,
                    ':points'            => is_numeric($_POST['points'] ?? '') ? (float)$_POST['points'] : null,
                    ':judge_contact_id'  => ($_POST['judge_contact_id'] ?? '') !== '' ? (int)$_POST['judge_contact_id'] : null,
                    ':photo_id'          => $photoId,
                    ':review'            => sanitize($_POST['review'] ?? '') ?: null,
                    ':notes'             => sanitize($_POST['notes'] ?? '') ?: null,
                ]);
                redirect(SITE_URL . '/admin/showrecords.php?horse_id=' . $horse_id . '&added=1');
        } elseif ($action === 'edit' && $show_id > 0) {
            // Omistajuustarkistus
            $own = $db->prepare('SELECT id FROM showrecords WHERE id = :show_id AND horse_id = :horse_id');
            $own->execute([':show_id' => $show_id, ':horse_id' => $horse_id]);
            if ($own->fetch()) {
                    $photoId = ($_POST['photo_id'] ?? '') !== '' ? (int)$_POST['photo_id'] : null;
                    if ($photoId !== null && !in_array($photoId, $horsePhotoIds, true)) $photoId = null;
                    $stmt = $db->prepare(
                        'UPDATE showrecords SET show_date=:show_date,
                         discipline=:discipline, country=:country, organizer=:organizer,
                         organizer_url=:organizer_url, class=:class, placement=:placement,
                         points=:points, judge_contact_id=:judge_contact_id, photo_id=:photo_id, review=:review, notes=:notes WHERE id=:show_id'
                    );
                    $stmt->execute([
                        ':show_date'         => sanitize($_POST['show_date'] ?? '') ?: null,
                        ':discipline'        => sanitize($_POST['discipline'] ?? '') ?: null,
                        ':country'           => sanitize($_POST['country'] ?? '') ?: null,
                        ':organizer'         => sanitize($_POST['organizer'] ?? '') ?: null,
                        ':organizer_url'     => sanitize($_POST['organizer_url'] ?? '') ?: null,
                        ':class'             => sanitize($_POST['class'] ?? '') ?: null,
                        ':placement'         => sanitize($_POST['placement'] ?? '') ?: null,
                        ':points'            => is_numeric($_POST['points'] ?? '') ? (float)$_POST['points'] : null,
                        ':judge_contact_id'  => ($_POST['judge_contact_id'] ?? '') !== '' ? (int)$_POST['judge_contact_id'] : null,
                        ':photo_id'          => $photoId,
                        ':review'            => sanitize($_POST['review'] ?? '') ?: null,
                        ':notes'             => sanitize($_POST['notes'] ?? '') ?: null,
                        ':show_id'           => $show_id,
                    ]);
                    redirect(SITE_URL . '/admin/showrecords.php?horse_id=' . $horse_id . '&updated=1');
            }
        } elseif ($action === 'delete' && $show_id > 0) {
            requireRole('admin');
            $own = $db->prepare('SELECT id FROM showrecords WHERE id = :show_id AND horse_id = :horse_id');
            $own->execute([':show_id' => $show_id, ':horse_id' => $horse_id]);
            if ($own->fetch()) {
                $db->prepare('DELETE FROM showrecords WHERE id = :show_id')->execute([':show_id' => $show_id]);
            }
            redirect(SITE_URL . '/admin/showrecords.php?horse_id=' . $horse_id . '&deleted=1');
        }
    }
}

// Hae näyttelytulokset
$showsStmt = $db->prepare(
    'SELECT s.*, jc.nickname AS judge_nickname, jc.stable_name AS judge_stable_name, jc.email AS judge_email,
            p.filename AS photo_filename, p.title AS photo_title, p.original_name AS photo_original_name
     FROM showrecords s LEFT JOIN contacts jc ON jc.id = s.judge_contact_id
     LEFT JOIN horse_photos p ON p.id = s.photo_id
     WHERE s.horse_id = :horse_id ORDER BY s.show_date DESC'
);
$showsStmt->execute([':horse_id' => $horse_id]);
$showrecords = $showsStmt->fetchAll();

if (isset($_GET['added']))   $flash = '<p class="flash-ok">Näyttelytulos lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Näyttelytulos päivitetty.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Näyttelytulos poistettu.</p>';

$pageTitle = 'Näyttelyt';
require __DIR__ . '/includes/admin_header.php';

// Tilastot
$wins = count(array_filter($showrecords, fn($s) => $s['placement'] === '1.'));
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="back-link">← Hevoset</a>
  <h1>Näyttelyt</h1>
  <div class="page-actions">
    <button class="btn" onclick="resetShowModal();adminOpenModal('show')">+ Lisää näyttelytulos</button>
  </div>
</div>

<div class="horse-ctx-banner">
  <span class="hcb-name">🎀 <?= e($horse['name']) ?></span>
  <span class="hcb-meta"><?= count($showrecords) ?> näyttelytulosta</span>
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="hcb-back">← Hevoslistaan</a>
</div>

<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $emsg): ?><li><?= e($emsg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?= $flash ?>

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

<?php if ($showrecords): ?>
<div class="compact-list">
  <div class="compact-list-header" style="grid-template-columns:1fr 1fr 1fr 80px 60px 28px">
    <div>Järjestäjä</div><div>Laji</div><div>Luokka</div><div>Päivämäärä</div><div>Tulos</div><div></div>
  </div>
  <?php foreach ($showrecords as $s):
    $pl = $s['placement'] ?? '';
    $pbClass = match($pl) { '1.' => 'pbadge-1', '2.' => 'pbadge-2', '3.' => 'pbadge-3', default => 'pbadge-x' };
    $judgeLabel = trim(($s['judge_nickname'] ?? '') . ' ' . ($s['judge_stable_name'] ?? ''));
    $judgeHtml  = $judgeLabel !== '' && !empty($s['judge_email'])
        ? '<a href="mailto:' . e($s['judge_email']) . '">' . e($judgeLabel) . '</a>'
        : e($judgeLabel);
  ?>
  <div class="compact-list-row" style="grid-template-columns:1fr 1fr 1fr 80px 60px 28px"
       onclick="adminToggleExpand('s<?= (int)$s['id'] ?>')">
    <div class="cl-name">
      <?php if (!empty($s['photo_filename'])): ?>
        <img class="sr-photo-thumb" src="<?= e(UPLOADS_URL . $s['photo_filename']) ?>" alt="" title="<?= e($s['photo_title'] ?? $s['photo_original_name'] ?? '') ?>">
      <?php endif; ?>
      <?= e($s['organizer'] ?? '—') ?>
    </div>
    <div class="cl-meta"><?= e($s['discipline'] ?? '—') ?></div>
    <div class="cl-meta"><?= e($s['class'] ?? '—') ?></div>
    <div class="cl-meta"><?= $s['show_date'] ? formatDate($s['show_date']) : '—' ?></div>
    <div><span class="pbadge <?= $pbClass ?>"><?= $pl !== '' ? e($pl) : '—' ?></span></div>
    <div>
      <button class="cl-expand-btn" id="cl-btn-s<?= (int)$s['id'] ?>"
              onclick="event.stopPropagation();adminToggleExpand('s<?= (int)$s['id'] ?>')">▸</button>
    </div>
  </div>
  <div class="cl-expanded" id="cl-exp-s<?= (int)$s['id'] ?>">
    <?php if ($s['country'] || $s['organizer_url'] || $s['points'] !== null || $judgeLabel || $s['review'] || $s['notes']): ?>
      <dl style="font-size:0.8rem;color:var(--color-text-muted);margin:0 0 0.5rem;display:flex;flex-wrap:wrap;gap:0.25rem 1.5rem">
        <?php if ($s['country']): ?><div><dt style="display:inline;font-weight:600">Maa:</dt> <dd style="display:inline"><?= e($s['country']) ?></dd></div><?php endif; ?>
        <?php if ($s['organizer_url']): ?><div><dt style="display:inline;font-weight:600">URL:</dt> <dd style="display:inline"><a href="<?= e($s['organizer_url']) ?>" target="_blank" rel="noopener"><?= e($s['organizer_url']) ?></a></dd></div><?php endif; ?>
        <?php if ($s['points'] !== null): ?><div><dt style="display:inline;font-weight:600">Pisteet:</dt> <dd style="display:inline"><?= e((string)$s['points']) ?></dd></div><?php endif; ?>
        <?php if ($judgeLabel): ?><div><dt style="display:inline;font-weight:600">Tuomari:</dt> <dd style="display:inline"><?= $judgeHtml ?></dd></div><?php endif; ?>
        <?php if ($s['review']): ?><div style="width:100%"><dt style="display:inline;font-weight:600">Arvostelu:</dt> <dd style="display:inline"><?= nl2br(e($s['review'])) ?></dd></div><?php endif; ?>
        <?php if ($s['notes']): ?><div style="width:100%"><dt style="display:inline;font-weight:600">Huom:</dt> <dd style="display:inline"><?= e($s['notes']) ?></dd></div><?php endif; ?>
      </dl>
    <?php endif; ?>
    <div class="cl-expanded-actions">
      <button class="btn-sm btn-edit" onclick="openEditShow(<?= (int)$s['id'] ?>, <?= htmlspecialchars(json_encode($s + ['judge_label' => $judgeLabel]), ENT_QUOTES) ?>)">✏️ Muokkaa</button>
      <form method="post" action="?horse_id=<?= $horse_id ?>" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action"  value="delete">
        <input type="hidden" name="show_id" value="<?= (int)$s['id'] ?>">
        <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Poistetaanko näyttelytulos?')">🗑 Poista</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <p style="color:var(--color-text-muted);margin:1rem 0">Ei näyttelytuloksia.</p>
<?php endif; ?>
</div><!-- /.admin-body -->

<!-- ── MODAL: Lisää/muokkaa näyttelytulos ── -->
<div class="admin-modal-overlay" id="modal-overlay-show">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2 id="modal-show-title">Lisää näyttelytulos</h2>
      <button class="admin-modal-close" onclick="adminCloseModal('show')">×</button>
    </div>
    <form method="post" action="?horse_id=<?= $horse_id ?>">
      <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
      <input type="hidden" name="action"  id="modal-show-action" value="add">
      <input type="hidden" name="show_id" id="modal-show-id"     value="">
      <div class="admin-modal-body">
        <div class="form-row">
          <div class="form-group">
            <label for="show_date">PVM</label>
            <input type="date" id="show_date" name="show_date">
          </div>
          <div class="form-group">
            <label for="discipline">Laji</label>
            <input type="text" id="discipline" name="discipline" placeholder="esim. Koulu, Rata, Länsi…">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="country">Maa</label>
            <input type="text" id="country" name="country" placeholder="esim. Suomi, Ruotsi…">
          </div>
          <div class="form-group">
            <label for="class">Luokka</label>
            <input type="text" id="class" name="class" placeholder="esim. EA, EP, Helppo A…">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="organizer">Järjestäjän nimi</label>
            <input type="text" id="organizer" name="organizer" placeholder="Järjestävä talli">
          </div>
          <div class="form-group">
            <label for="organizer_url">Järjestäjän URL</label>
            <input type="url" id="organizer_url" name="organizer_url" placeholder="https://…">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="placement">Tulos</label>
            <input type="text" id="placement" name="placement" placeholder="esim. 1., 2., DNS, DQ…">
          </div>
          <div class="form-group">
            <label for="points">Pisteet</label>
            <input type="number" id="points" name="points" step="0.01" min="0" placeholder="esim. 65.5">
          </div>
        </div>
        <div class="form-group">
          <label>Kuva</label>
          <?php if ($horsePhotos): ?>
            <div class="photo-pick-grid" id="photo-pick-grid">
              <div class="photo-pick-thumb photo-pick-none selected" data-photo-id="" onclick="selectShowPhoto(this,'')" title="Ei kuvaa">
                <span>Ei kuvaa</span>
              </div>
              <?php foreach ($horsePhotos as $p): ?>
                <div class="photo-pick-thumb" data-photo-id="<?= (int)$p['id'] ?>" onclick="selectShowPhoto(this,<?= (int)$p['id'] ?>)" title="<?= e($p['title'] ?? $p['original_name'] ?? '') ?>">
                  <img src="<?= e(UPLOADS_URL . $p['filename']) ?>" alt="<?= e($p['title'] ?? $p['original_name'] ?? '') ?>">
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p style="color:var(--color-text-muted);font-size:0.8rem;margin:0">Hevosella ei ole vielä kuvia. <a href="<?= e(SITE_URL) ?>/admin/photos.php?horse_id=<?= $horse_id ?>" target="_blank">Lisää kuva</a></p>
          <?php endif; ?>
          <input type="hidden" name="photo_id" id="modal-show-photo-id" value="">
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
        <button type="submit" class="btn" id="modal-show-btn">Lisää näyttelytulos</button>
        <button type="button" class="btn-ghost" onclick="adminCloseModal('show')">Peruuta</button>
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

function selectShowPhoto(el, id) {
  document.getElementById('modal-show-photo-id').value = id || '';
  document.querySelectorAll('#photo-pick-grid .photo-pick-thumb').forEach(t => t.classList.remove('selected'));
  el.classList.add('selected');
}

function resetShowModal() {
  document.getElementById('modal-show-title').textContent = 'Lisää näyttelytulos';
  document.getElementById('modal-show-action').value      = 'add';
  document.getElementById('modal-show-id').value          = '';
  ['show_date','discipline','country','organizer','organizer_url','class','placement','points','review','notes']
    .forEach(f => { const el = document.getElementById(f); if (el) el.value = ''; });
  setAcValue('judge_contact', '', '');
  document.getElementById('judge-preview').style.display = 'none';
  const noneThumb = document.querySelector('#photo-pick-grid .photo-pick-none');
  if (noneThumb) selectShowPhoto(noneThumb, '');
  document.getElementById('modal-show-btn').textContent = 'Lisää näyttelytulos';
}

function openEditShow(id, data) {
  document.getElementById('modal-show-title').textContent  = 'Muokkaa näyttelytulosta';
  document.getElementById('modal-show-action').value       = 'edit';
  document.getElementById('modal-show-id').value           = id;
  document.getElementById('show_date').value                = data.show_date       || '';
  document.getElementById('discipline').value               = data.discipline      || '';
  document.getElementById('country').value                  = data.country         || '';
  document.getElementById('organizer').value                = data.organizer       || '';
  document.getElementById('organizer_url').value             = data.organizer_url   || '';
  document.getElementById('class').value                    = data.class           || '';
  document.getElementById('placement').value                = data.placement       || '';
  document.getElementById('points').value                   = data.points          || '';
  document.getElementById('review').value                   = data.review          || '';
  document.getElementById('notes').value                    = data.notes           || '';
  const photoId = data.photo_id ? String(data.photo_id) : '';
  document.getElementById('modal-show-photo-id').value = photoId;
  document.querySelectorAll('#photo-pick-grid .photo-pick-thumb').forEach(t => {
    t.classList.toggle('selected', (t.dataset.photoId || '') === photoId);
  });
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
  document.getElementById('modal-show-btn').textContent    = 'Tallenna muutokset';
  adminOpenModal('show');
}
</script>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
