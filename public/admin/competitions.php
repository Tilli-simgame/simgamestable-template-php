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

$edit_id = (int)($_GET['edit'] ?? 0);
$errors  = [];
$flash   = '';

// POST-käsittely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $comp_id = (int)($_POST['comp_id'] ?? 0);

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        if ($action === 'add') {
                $stmt = $db->prepare(
                    'INSERT INTO competitions (horse_id, competition_date, discipline, country, organizer, organizer_url, class, placement, points, notes)
                     VALUES (:horse_id, :competition_date, :discipline, :country, :organizer, :organizer_url, :class, :placement, :points, :notes)'
                );
                $stmt->execute([
                    ':horse_id'        => $horse_id,
                    ':competition_date'=> sanitize($_POST['competition_date'] ?? '') ?: null,
                    ':discipline'      => sanitize($_POST['discipline'] ?? '') ?: null,
                    ':country'         => sanitize($_POST['country'] ?? '') ?: null,
                    ':organizer'       => sanitize($_POST['organizer'] ?? '') ?: null,
                    ':organizer_url'   => sanitize($_POST['organizer_url'] ?? '') ?: null,
                    ':class'           => sanitize($_POST['class'] ?? '') ?: null,
                    ':placement'       => sanitize($_POST['placement'] ?? '') ?: null,
                    ':points'          => is_numeric($_POST['points'] ?? '') ? (float)$_POST['points'] : null,
                    ':notes'           => sanitize($_POST['notes'] ?? '') ?: null,
                ]);
                redirect(SITE_URL . '/admin/competitions.php?horse_id=' . $horse_id . '&added=1');
        } elseif ($action === 'edit' && $comp_id > 0) {
            // Omistajuustarkistus
            $own = $db->prepare('SELECT id FROM competitions WHERE id = :comp_id AND horse_id = :horse_id');
            $own->execute([':comp_id' => $comp_id, ':horse_id' => $horse_id]);
            if ($own->fetch()) {
                    $stmt = $db->prepare(
                        'UPDATE competitions SET competition_date=:competition_date,
                         discipline=:discipline, country=:country, organizer=:organizer,
                         organizer_url=:organizer_url, class=:class, placement=:placement,
                         points=:points, notes=:notes WHERE id=:comp_id'
                    );
                    $stmt->execute([
                        ':competition_date'=> sanitize($_POST['competition_date'] ?? '') ?: null,
                        ':discipline'      => sanitize($_POST['discipline'] ?? '') ?: null,
                        ':country'         => sanitize($_POST['country'] ?? '') ?: null,
                        ':organizer'       => sanitize($_POST['organizer'] ?? '') ?: null,
                        ':organizer_url'   => sanitize($_POST['organizer_url'] ?? '') ?: null,
                        ':class'           => sanitize($_POST['class'] ?? '') ?: null,
                        ':placement'       => sanitize($_POST['placement'] ?? '') ?: null,
                        ':points'          => is_numeric($_POST['points'] ?? '') ? (float)$_POST['points'] : null,
                        ':notes'           => sanitize($_POST['notes'] ?? '') ?: null,
                        ':comp_id'         => $comp_id,
                    ]);
                    redirect(SITE_URL . '/admin/competitions.php?horse_id=' . $horse_id . '&updated=1');
            }
        } elseif ($action === 'delete' && $comp_id > 0) {
            requireRole('admin', 'mod');
            $own = $db->prepare('SELECT id FROM competitions WHERE id = :comp_id AND horse_id = :horse_id');
            $own->execute([':comp_id' => $comp_id, ':horse_id' => $horse_id]);
            if ($own->fetch()) {
                $delStmt = $db->prepare('UPDATE competitions SET is_deleted = 1, deleted_at = NOW() WHERE id = :comp_id AND is_deleted = 0');
                $delStmt->execute([':comp_id' => $comp_id]);
                if ($delStmt->rowCount() > 0 && currentRole() === 'mod') {
                    insertPendingDeletion('competition', $comp_id, (int)$_SESSION['admin_id']);
                }
            }
            redirect(SITE_URL . '/admin/competitions.php?horse_id=' . $horse_id . '&deleted=1');
        }
    }
}

// Hae kilpailut
$compsStmt = $db->prepare('SELECT * FROM competitions WHERE horse_id = :horse_id AND is_deleted = 0 ORDER BY competition_date DESC');
$compsStmt->execute([':horse_id' => $horse_id]);
$competitions = $compsStmt->fetchAll();

// Muokkaustila
$editComp = null;
if ($edit_id > 0) {
    $editStmt = $db->prepare('SELECT * FROM competitions WHERE id = :id AND horse_id = :horse_id AND is_deleted = 0');
    $editStmt->execute([':id' => $edit_id, ':horse_id' => $horse_id]);
    $editComp = $editStmt->fetch();
}

if (isset($_GET['added']))   $flash = '<p class="flash-ok">Kilpailu lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Kilpailu päivitetty.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Kilpailu poistettu.</p>';

$pageTitle = 'Kilpailut';
require __DIR__ . '/includes/admin_header.php';

// Tilastot
$wins = count(array_filter($competitions, fn($c) => $c['placement'] === '1.'));
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="back-link">← Hevoset</a>
  <h1>Kilpailut</h1>
  <div class="page-actions">
    <button class="btn" onclick="adminOpenModal('comp')">+ Lisää kilpailu</button>
  </div>
</div>

<div class="horse-ctx-banner">
  <span class="hcb-name">🏆 <?= e($horse['name']) ?></span>
  <span class="hcb-meta"><?= count($competitions) ?> kilpailua</span>
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="hcb-back">← Hevoslistaan</a>
</div>

<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $emsg): ?><li><?= e($emsg) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?= $flash ?>

<div class="comp-stat-row">
  <div class="comp-stat-card">
    <div class="cs-num"><?= count($competitions) ?></div>
    <div class="cs-label">Kilpailua</div>
  </div>
  <div class="comp-stat-card">
    <div class="cs-num"><?= $wins ?></div>
    <div class="cs-label">Voittoa</div>
  </div>
</div>

<?php if ($competitions): ?>
<div class="compact-list">
  <div class="compact-list-header" style="grid-template-columns:1fr 1fr 1fr 80px 60px 28px">
    <div>Järjestäjä</div><div>Laji</div><div>Luokka</div><div>Päivämäärä</div><div>Tulos</div><div></div>
  </div>
  <?php foreach ($competitions as $c):
    $pl = $c['placement'] ?? '';
    $pbClass = match($pl) { '1.' => 'pbadge-1', '2.' => 'pbadge-2', '3.' => 'pbadge-3', default => 'pbadge-x' };
  ?>
  <div class="compact-list-row" style="grid-template-columns:1fr 1fr 1fr 80px 60px 28px"
       onclick="adminToggleExpand('c<?= (int)$c['id'] ?>')">
    <div class="cl-name"><?= e($c['organizer'] ?? '—') ?></div>
    <div class="cl-meta"><?= e($c['discipline'] ?? '—') ?></div>
    <div class="cl-meta"><?= e($c['class'] ?? '—') ?></div>
    <div class="cl-meta"><?= $c['competition_date'] ? formatDate($c['competition_date']) : '—' ?></div>
    <div><span class="pbadge <?= $pbClass ?>"><?= $pl !== '' ? e($pl) : '—' ?></span></div>
    <div>
      <button class="cl-expand-btn" id="cl-btn-c<?= (int)$c['id'] ?>"
              onclick="event.stopPropagation();adminToggleExpand('c<?= (int)$c['id'] ?>')">▸</button>
    </div>
  </div>
  <div class="cl-expanded" id="cl-exp-c<?= (int)$c['id'] ?>">
    <?php if ($c['country'] || $c['organizer_url'] || $c['points'] !== null || $c['notes']): ?>
      <dl style="font-size:0.8rem;color:var(--color-text-muted);margin:0 0 0.5rem;display:flex;flex-wrap:wrap;gap:0.25rem 1.5rem">
        <?php if ($c['country']): ?><div><dt style="display:inline;font-weight:600">Maa:</dt> <dd style="display:inline"><?= e($c['country']) ?></dd></div><?php endif; ?>
        <?php if ($c['organizer_url']): ?><div><dt style="display:inline;font-weight:600">URL:</dt> <dd style="display:inline"><a href="<?= e($c['organizer_url']) ?>" target="_blank" rel="noopener"><?= e($c['organizer_url']) ?></a></dd></div><?php endif; ?>
        <?php if ($c['points'] !== null): ?><div><dt style="display:inline;font-weight:600">Pisteet:</dt> <dd style="display:inline"><?= e((string)$c['points']) ?></dd></div><?php endif; ?>
        <?php if ($c['notes']): ?><div style="width:100%"><dt style="display:inline;font-weight:600">Huom:</dt> <dd style="display:inline"><?= e($c['notes']) ?></dd></div><?php endif; ?>
      </dl>
    <?php endif; ?>
    <div class="cl-expanded-actions">
      <button class="btn-sm btn-edit" onclick="openEditComp(<?= (int)$c['id'] ?>, <?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)">✏️ Muokkaa</button>
      <form method="post" action="?horse_id=<?= $horse_id ?>" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="action"  value="delete">
        <input type="hidden" name="comp_id" value="<?= (int)$c['id'] ?>">
        <button type="submit" class="btn-sm btn-danger" onclick="return confirm('Poistetaanko kilpailu?')">🗑 Poista</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <p style="color:var(--color-text-muted);margin:1rem 0">Ei kilpailumerkintöjä.</p>
<?php endif; ?>
</div><!-- /.admin-body -->

<!-- ── MODAL: Lisää/muokkaa kilpailu ── -->
<div class="admin-modal-overlay" id="modal-overlay-comp">
  <div class="admin-modal">
    <div class="admin-modal-header">
      <h2 id="modal-comp-title">Lisää kilpailu</h2>
      <button class="admin-modal-close" onclick="adminCloseModal('comp')">×</button>
    </div>
    <form method="post" action="?horse_id=<?= $horse_id ?>">
      <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
      <input type="hidden" name="action"  id="modal-comp-action" value="add">
      <input type="hidden" name="comp_id" id="modal-comp-id"     value="">
      <div class="admin-modal-body">
        <div class="form-row">
          <div class="form-group">
            <label for="competition_date">PVM</label>
            <input type="date" id="competition_date" name="competition_date">
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
          <label for="notes">Huom</label>
          <textarea id="notes" name="notes"></textarea>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button type="submit" class="btn" id="modal-comp-btn">Lisää kilpailu</button>
        <button type="button" class="btn-ghost" onclick="adminCloseModal('comp')">Peruuta</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditComp(id, data) {
  document.getElementById('modal-comp-title').textContent  = 'Muokkaa kilpailua';
  document.getElementById('modal-comp-action').value       = 'edit';
  document.getElementById('modal-comp-id').value           = id;
  document.getElementById('competition_date').value        = data.competition_date  || '';
  document.getElementById('discipline').value              = data.discipline        || '';
  document.getElementById('country').value                 = data.country           || '';
  document.getElementById('organizer').value               = data.organizer         || '';
  document.getElementById('organizer_url').value           = data.organizer_url     || '';
  document.getElementById('class').value                   = data.class             || '';
  document.getElementById('placement').value               = data.placement         || '';
  document.getElementById('points').value                  = data.points            || '';
  document.getElementById('notes').value                   = data.notes             || '';
  document.getElementById('modal-comp-btn').textContent    = 'Tallenna muutokset';
  adminOpenModal('comp');
}
</script>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
