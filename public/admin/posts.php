<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod', 'author');

$db = getDB();
$errors = [];
$success = '';

// ── POST-käsittelijä: lisää tai muokkaa ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        $title   = sanitize($_POST['title']   ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $edit_id = (int)($_POST['edit_id'] ?? 0);

        if ($title === '') $errors[] = 'Otsikko on pakollinen.';
        if ($content === '') $errors[] = 'Sisältö on pakollinen.';

        if (empty($errors)) {
            // Generoi slug (sama logiikka kuin horse_add.php) — T-05-06
            $slug = slugify($title);
            $base = $slug;
            $n = 2;
            while (true) {
                $chk = $db->prepare(
                    'SELECT id FROM posts WHERE slug = :slug' .
                    ($edit_id ? ' AND id != :id' : '')
                );
                $params = [':slug' => $slug];
                if ($edit_id) $params[':id'] = $edit_id;
                $chk->execute($params);
                if (!$chk->fetch()) break;
                $slug = $base . '-' . $n++;
            }

            if ($edit_id > 0) {
                // Omistajuustarkistus ENNEN UPDATE-lausetta (IDOR defense-in-depth, Pitfall 2):
                // crafted POST toisen postauksen edit_id:llä ohjautuu ei-oikeutta.php:hen.
                $ownerChk = $db->prepare('SELECT author_id FROM posts WHERE id = :id');
                $ownerChk->execute([':id' => $edit_id]);
                $ownerRow = $ownerChk->fetch();
                if ($ownerRow) {
                    requireOwnResourceOrAdmin((int)$ownerRow['author_id']);
                }

                $db->prepare('UPDATE posts SET title=:t, slug=:s, content=:c WHERE id=:id')
                   ->execute([':t'=>$title, ':s'=>$slug, ':c'=>$content, ':id'=>$edit_id]);
                $savedId = $edit_id;
                $redirectParam = 'updated=1';
            } else {
                $db->prepare('INSERT INTO posts (title, slug, content, author_id) VALUES (:t, :s, :c, :aid)')
                   ->execute([':t'=>$title, ':s'=>$slug, ':c'=>$content, ':aid'=>$_SESSION['admin_id']]);
                $savedId = (int)$db->lastInsertId();
                $redirectParam = 'added=1';
            }

            // Tallenna hevoslinkitykset
            $horseIds = array_filter(array_map('intval', $_POST['horse_ids'] ?? []));
            $db->prepare('DELETE FROM post_horses WHERE post_id = :pid')->execute([':pid' => $savedId]);
            foreach ($horseIds as $hid) {
                $db->prepare('INSERT IGNORE INTO post_horses (post_id, horse_id) VALUES (:pid, :hid)')
                   ->execute([':pid' => $savedId, ':hid' => $hid]);
            }

            redirect(SITE_URL . '/admin/posts.php?' . $redirectParam);
        }
    }
}

// ── GET: määritä näkymä ──────────────────────────────────────────────────────
$action  = $_GET['action'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);

// Lomakkeen oletusarvot
$f = ['title' => '', 'content' => '', 'edit_id' => 0, 'horse_ids' => []];

if ($action === 'edit' && $edit_id > 0) {
    $editPost = $db->prepare('SELECT * FROM posts WHERE id = :id');
    $editPost->execute([':id' => $edit_id]);
    $editPost = $editPost->fetch();
    if ($editPost) {
        // Suora-URL-omistajuustarkistus (SC3): author joka arvaa/kirjoittaa vieraan
        // postauksen ID:n URL:iin ohjautuu ei-oikeutta.php:hen.
        requireOwnResourceOrAdmin((int)$editPost['author_id']);

        $linkedHorses = $db->prepare('SELECT horse_id FROM post_horses WHERE post_id = :pid');
        $linkedHorses->execute([':pid' => $edit_id]);
        $linkedHorseIds = array_column($linkedHorses->fetchAll(), 'horse_id');
        $f = [
            'title'     => $editPost['title'],
            'content'   => $editPost['content'],
            'edit_id'   => $edit_id,
            'horse_ids' => $linkedHorseIds,
        ];
    } else {
        redirect(SITE_URL . '/admin/posts.php');
    }
}

// Jos POST-virheitä, täytä lomake lähetetyillä arvoilla
if (!empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $f['title']     = sanitize($_POST['title'] ?? '');
    $f['content']   = sanitize($_POST['content'] ?? '');
    $f['edit_id']   = (int)($_POST['edit_id'] ?? 0);
    $f['horse_ids'] = array_filter(array_map('intval', $_POST['horse_ids'] ?? []));
    $action = $f['edit_id'] > 0 ? 'edit' : 'new';
}

// Flash-viestit
$flash = '';
if (isset($_GET['added']))   $flash = '<p class="flash-ok">Postaus lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Muutokset tallennettu.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Postaus poistettu.</p>';

// Haetaan kaikki postaukset listanäkymää varten
// Author näkee VAIN omat postauksensa (D-03) — admin/mod näkevät kaikki, ei omistajuusrajausta.
if (currentRole() === 'author') {
    $posts = $db->prepare('SELECT id, title, slug, created_at FROM posts WHERE author_id = :aid ORDER BY created_at DESC');
    $posts->execute([':aid' => $_SESSION['admin_id']]);
    $posts = $posts->fetchAll();
} else {
    $posts = $db->query('SELECT id, title, slug, created_at FROM posts ORDER BY created_at DESC')->fetchAll();
}

// Haetaan kaikki hevoset lomakevalitsinta varten
$allHorses = $db->query(
    'SELECT id, name, call_name FROM horses WHERE is_deleted = 0 ORDER BY name ASC'
)->fetchAll();

// JSON-data AC-widgetille
$horsesJson = json_encode(array_map(function($h) {
    $label = $h['name'];
    if ($h['call_name']) $label .= ' ("' . $h['call_name'] . '")';
    return ['id' => (int)$h['id'], 'label' => $label];
}, $allHorses), JSON_UNESCAPED_UNICODE);

// Esivalitut hevoset (muokkaus tai POST-virhe)
$selectedHorsesJson = '[]';
if (!empty($f['horse_ids'])) {
    $sel = [];
    foreach ($allHorses as $h) {
        if (in_array((int)$h['id'], $f['horse_ids'], true)) {
            $label = $h['name'];
            if ($h['call_name']) $label .= ' ("' . $h['call_name'] . '")';
            $sel[] = ['id' => (int)$h['id'], 'label' => $label];
        }
    }
    $selectedHorsesJson = json_encode($sel, JSON_UNESCAPED_UNICODE);
}

$pageTitle = 'Postaukset';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <h1>Postaukset</h1>
  <div class="page-actions">
    <?php if ($action !== 'list'): ?>
      <a href="<?= e(SITE_URL) ?>/admin/posts.php" class="btn-ghost">← Takaisin listaan</a>
    <?php else: ?>
      <a href="<?= e(SITE_URL) ?>/admin/posts.php?action=new" class="btn">+ Uusi postaus</a>
    <?php endif; ?>
  </div>
</div>
<div class="admin-body">

  <?php if ($flash): echo $flash; endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="flash-err">
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($action === 'new' || $action === 'edit'): ?>
    <!-- ── LOMAKE ──────────────────────────────────────────────── -->
    <div class="admin-card">
      <h2><?= $action === 'edit' ? 'Muokkaa postausta' : 'Uusi postaus' ?></h2>
      <form method="post" action="<?= e(SITE_URL) ?>/admin/posts.php" class="post-admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <input type="hidden" name="edit_id" value="<?= (int)$f['edit_id'] ?>">

        <div class="form-group" style="margin-bottom:1rem;">
          <label for="post-title">Otsikko *</label>
          <input type="text" id="post-title" name="title"
                 value="<?= e($f['title']) ?>" required
                 placeholder="Postauksen otsikko">
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
          <label for="post-content">Sisältö *</label>
          <textarea id="post-content" name="content" required
                    placeholder="Kirjoita postauksen sisältö tähän..."><?= e($f['content']) ?></textarea>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
          <label>Liitetyt hevoset</label>
          <div id="horse-multi-wrap" class="horse-multi-wrap">
            <div id="horse-multi-tags" class="horse-multi-tags"></div>
            <div style="position:relative;">
              <input type="text" id="horse-multi-text" class="ac-text horse-multi-input"
                     autocomplete="off" placeholder="Hae hevosta nimellä...">
              <ul id="horse-multi-list" class="ac-list" role="listbox"></ul>
            </div>
          </div>
          <p style="font-size:var(--text-xs);color:var(--color-text-muted);margin-top:.3rem;">Kirjoita hevosen nimi ja valitse ehdotuksesta. Postaus näkyy valittujen hevosten profiilissa.</p>
        </div>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem;">
          <button type="submit" class="btn">
            <?= $action === 'edit' ? 'Tallenna muutokset' : 'Julkaise postaus' ?>
          </button>
          <a href="<?= e(SITE_URL) ?>/admin/posts.php" class="btn-ghost">Peruuta</a>
        </div>
      </form>
    </div>

  <?php else: ?>
    <!-- ── LISTA ───────────────────────────────────────────────── -->
    <div class="admin-card">
      <h2>Kaikki postaukset (<?= count($posts) ?>)</h2>
      <?php if (empty($posts)): ?>
        <p style="color:var(--color-text-muted);">Ei vielä postauksia. <a href="<?= e(SITE_URL) ?>/admin/posts.php?action=new">Lisää ensimmäinen →</a></p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Otsikko</th>
              <th>Luotu</th>
              <th>Muokkaa</th>
              <th>Poista</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($posts as $p): ?>
              <tr>
                <td>
                  <a href="<?= e(SITE_URL) ?>/pages/postaus.php?slug=<?= rawurlencode($p['slug']) ?>"
                     target="_blank" class="btn-sm btn-view" style="margin-right:.35rem;">
                    Katso
                  </a>
                  <?= e($p['title']) ?>
                </td>
                <td><?= formatDate($p['created_at']) ?></td>
                <td>
                  <a href="<?= e(SITE_URL) ?>/admin/posts.php?action=edit&amp;id=<?= (int)$p['id'] ?>"
                     class="btn-sm btn-edit">Muokkaa</a>
                </td>
                <td>
                  <form method="post" action="<?= e(SITE_URL) ?>/admin/post_delete.php"
                        style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn-sm btn-danger"
                            onclick="return confirm('Poistetaanko postaus \'<?= e(addslashes($p['title'])) ?>\'?')">
                      Poista
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>
<style>
.horse-multi-wrap {
  border: 1px solid var(--color-border, #e0d5c5);
  border-radius: 6px;
  padding: .35rem .5rem;
  background: var(--color-surface, #fff);
  cursor: text;
  transition: border-color .15s;
}
.horse-multi-wrap:focus-within {
  border-color: var(--color-accent, #a0633a);
}
.horse-multi-tags {
  display: flex;
  flex-wrap: wrap;
  gap: .3rem;
  margin-bottom: .25rem;
}
.horse-multi-tags:empty { margin-bottom: 0; }
.horse-multi-chip {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  background: var(--color-surface-accent, #f5ede0);
  border: 1px solid var(--color-border, #e0d5c5);
  border-radius: 4px;
  padding: .15rem .45rem .15rem .55rem;
  font-size: .8rem;
  color: var(--color-primary, #3d2b1f);
  line-height: 1.4;
}
.horse-multi-chip button {
  background: none;
  border: none;
  padding: 0;
  font-size: 1rem;
  line-height: 1;
  cursor: pointer;
  color: var(--color-text-muted, #6b5e52);
  display: flex;
  align-items: center;
}
.horse-multi-chip button:hover { color: var(--color-danger, #8a3030); }
.horse-multi-input {
  border: none !important;
  outline: none !important;
  padding: .25rem .2rem !important;
  min-width: 180px;
  box-shadow: none !important;
}
</style>
<script>
(function() {
  var items       = <?= $horsesJson ?>;
  var preSelected = <?= $selectedHorsesJson ?>;

  var tagsEl = document.getElementById('horse-multi-tags');
  var textEl = document.getElementById('horse-multi-text');
  var listEl = document.getElementById('horse-multi-list');
  var selected = {}; // id -> label
  var activeIdx = -1;

  // Klikkaaminen wrappiin fokusoittaa inputin
  document.getElementById('horse-multi-wrap').addEventListener('click', function(e) {
    if (e.target !== textEl) textEl.focus();
  });

  // Esitäytä muokatessa
  preSelected.forEach(function(h) { addChip(String(h.id), h.label); });

  function addChip(id, label) {
    id = String(id);
    if (selected[id]) return;
    selected[id] = label;

    var chip = document.createElement('span');
    chip.className = 'horse-multi-chip';

    var txt = document.createElement('span');
    txt.textContent = label;

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Poista');
    btn.textContent = '×';
    btn.addEventListener('click', function() {
      delete selected[id];
      chip.remove();
    });

    var hidden = document.createElement('input');
    hidden.type  = 'hidden';
    hidden.name  = 'horse_ids[]';
    hidden.value = id;

    chip.appendChild(txt);
    chip.appendChild(btn);
    chip.appendChild(hidden);
    tagsEl.appendChild(chip);
  }

  function escapeRe(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  function escHtml(s)  { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  function render(q) {
    var lower   = q.toLowerCase();
    var matches = q.length === 0 ? [] :
      items.filter(function(i) {
        return i.label.toLowerCase().indexOf(lower) !== -1 && !selected[String(i.id)];
      }).slice(0, 25);
    listEl.innerHTML = matches.map(function(m, idx) {
      var hi = m.label.replace(new RegExp('(' + escapeRe(q) + ')', 'gi'), '<strong>$1</strong>');
      return '<li class="ac-item" data-idx="' + idx + '" data-id="' + m.id + '"' +
             ' data-label="' + escHtml(m.label) + '" role="option">' + hi + '</li>';
    }).join('');
    activeIdx = -1;
    listEl.classList.toggle('open', matches.length > 0);
    listEl.querySelectorAll('.ac-item').forEach(function(li) {
      li.addEventListener('mousedown', function(e) {
        e.preventDefault();
        addChip(li.dataset.id, li.dataset.label);
        textEl.value = '';
        listEl.classList.remove('open');
      });
    });
  }

  textEl.addEventListener('input',  function() { render(textEl.value.trim()); });
  textEl.addEventListener('focus',  function() { if (textEl.value.trim()) render(textEl.value.trim()); });
  textEl.addEventListener('blur',   function() { setTimeout(function() { listEl.classList.remove('open'); }, 150); });
  textEl.addEventListener('keydown', function(e) {
    var lis = listEl.querySelectorAll('.ac-item');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIdx = Math.min(activeIdx + 1, lis.length - 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIdx = Math.max(activeIdx - 1, 0);
    } else if (e.key === 'Enter' && activeIdx >= 0) {
      e.preventDefault();
      var li = lis[activeIdx];
      addChip(li.dataset.id, li.dataset.label);
      textEl.value = '';
      listEl.classList.remove('open');
      return;
    } else if (e.key === 'Escape') {
      listEl.classList.remove('open');
      return;
    }
    lis.forEach(function(li, i) { li.classList.toggle('ac-active', i === activeIdx); });
    if (activeIdx >= 0) lis[activeIdx].scrollIntoView({ block: 'nearest' });
  });
})();
</script>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
