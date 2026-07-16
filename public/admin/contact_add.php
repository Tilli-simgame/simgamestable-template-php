<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

$db = getDB();
$errors = [];
$f = ['nickname' => '', 'stable_name' => '', 'stable_url' => '', 'character_url' => '', 'vrl_id' => '', 'email' => '', 'country' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        foreach ($f as $k => $_) {
            $f[$k] = sanitize($_POST[$k] ?? '');
        }
        if (!$f['nickname'] && !$f['stable_name']) {
            $errors[] = 'Anna vähintään nimimerkki tai tallin nimi.';
        }
        if ($f['email'] !== '') {
            $r = validate_email($f['email']);
            if (!$r['valid']) $errors[] = $r['error'];
            else $f['email'] = $r['value'];
        }
        if ($f['stable_url'] !== '' && filter_var($f['stable_url'], FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Tallin URL ei ole kelvollinen.';
        }
        if ($f['character_url'] !== '' && filter_var($f['character_url'], FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Hahmon sivujen URL ei ole kelvollinen.';
        }
        if (empty($errors)) {
            $stmt = $db->prepare(
                'INSERT INTO contacts (nickname, stable_name, stable_url, character_url, vrl_id, email, country)
                 VALUES (:nickname, :stable_name, :stable_url, :character_url, :vrl_id, :email, :country)'
            );
            $stmt->execute([
                ':nickname'      => $f['nickname'] ?: null,
                ':stable_name'   => $f['stable_name'] ?: null,
                ':stable_url'    => $f['stable_url'] ?: null,
                ':character_url' => $f['character_url'] ?: null,
                ':vrl_id'        => $f['vrl_id'] ?: null,
                ':email'         => $f['email'] ?: null,
                ':country'       => $f['country'] ?: null,
            ]);
            redirect(SITE_URL . '/admin/contacts.php?added=1');
        }
    }
}

$pageTitle = 'Lisää yhteystieto';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/contacts.php" class="back-link">← Osoitekirja</a>
  <h1>Lisää yhteystieto</h1>
</div>
<div class="admin-body">
<?php if ($errors): ?>
  <div class="flash-err"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" style="max-width:680px">
  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

  <div class="form-row">
    <div class="form-group">
      <label for="nickname">Nimimerkki</label>
      <input type="text" id="nickname" name="nickname" value="<?= e($f['nickname']) ?>" autofocus>
    </div>
    <div class="form-group">
      <label for="stable_name">Tallin nimi</label>
      <input type="text" id="stable_name" name="stable_name" value="<?= e($f['stable_name']) ?>">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="stable_url">Tallin URL</label>
      <input type="url" id="stable_url" name="stable_url" value="<?= e($f['stable_url']) ?>" placeholder="https://...">
    </div>
    <div class="form-group">
      <label for="character_url">Hahmon sivujen URL</label>
      <input type="url" id="character_url" name="character_url" value="<?= e($f['character_url']) ?>" placeholder="https://...">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="vrl_id">VRL-tunnus</label>
      <input type="text" id="vrl_id" name="vrl_id" value="<?= e($f['vrl_id']) ?>" placeholder="VRL-XXXXX">
    </div>
    <div class="form-group"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label for="email">Sähköposti</label>
      <input type="email" id="email" name="email" value="<?= e($f['email']) ?>">
    </div>
    <div class="form-group">
      <label for="country">Maa</label>
      <input type="text" id="country" name="country" value="<?= e($f['country']) ?>">
    </div>
  </div>

  <div style="margin-top:1.5rem;display:flex;gap:0.75rem">
    <button type="submit" class="btn">Tallenna</button>
    <a href="<?= e(SITE_URL) ?>/admin/contacts.php" class="btn-ghost">Peruuta</a>
  </div>
</form>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
