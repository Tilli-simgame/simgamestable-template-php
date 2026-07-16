# Phase 11: Käyttäjähallinta - Pattern Map

**Mapped:** 2026-07-16
**Files analyzed:** 7 (1 modified, 6 new)
**Analogs found:** 7 / 7

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|--------------------|------|-----------|-----------------|----------------|
| `public/admin/users.php` | controller (list view) | CRUD (read) | `public/admin/contacts.php` | role-match (list layout differs: table not compact-list, D-01) |
| `public/admin/user_add.php` | controller (create form) | CRUD (create) | `public/admin/contact_add.php` (structure) + `public/admin/change_password.php` (password/hash + inline-success pattern) | role-match |
| `public/admin/user_edit.php` | controller (edit form) | CRUD (update) | `public/admin/contact_edit.php` | exact (structure); adds self-protection check (D-05) |
| `public/admin/user_reset_password.php` | controller (action) | CRUD (update, one-off) | `public/admin/change_password.php` (hash/generation) + `public/admin/contact_delete.php` (inline POST-only action shape) | role-match |
| `public/admin/user_toggle_active.php` | controller (action) | CRUD (update, toggle) | `public/admin/contact_delete.php` (POST-only action shape, no view) | role-match |
| `public/admin/user_delete.php` | controller (action) | CRUD (delete) | `public/admin/contact_delete.php` | exact |
| `public/admin/includes/admin_header.php` | nav/layout (modified) | request-response | itself, existing `settings.php` admin-only link block (lines 334-338) | exact (in-place pattern to replicate) |

## Pattern Assignments

### `public/admin/users.php` (controller, list view)

**Analog:** `public/admin/contacts.php`

**Imports / guard pattern** (lines 1-3):
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');
```
For users.php use `requireRole('admin')` only — this whole file family is admin-only (per CONTEXT.md domain: "Admin saa oman admin-paneelisivun").

**Query + flash pattern** (lines 5-20):
```php
$db = getDB();
$contacts = $db->query('SELECT ... ORDER BY ...')->fetchAll();

$flash = '';
if (isset($_GET['added']))   $flash = '<p class="flash-ok">Yhteystieto lisätty.</p>';
if (isset($_GET['updated'])) $flash = '<p class="flash-ok">Muutokset tallennettu.</p>';
if (isset($_GET['deleted'])) $flash = '<p class="flash-ok">Yhteystieto poistettu.</p>';

$pageTitle = 'Osoitekirja';
require __DIR__ . '/includes/admin_header.php';
```
Note: toggle-active flash (`?activated=1` / `?deactivated=1`) and reset-password flash must NOT reuse this `$_GET` pattern for the reset case — the generated plaintext password cannot travel via `$_GET` (CONTEXT.md code_context note). Toggle/add/update/delete flashes CAN use `$_GET` since they carry no secret; reset-password must redirect back to `users.php` with a `$_GET` flag only (e.g. `?reset=1`) that does NOT contain the password itself — the password itself must be shown once on `user_reset_password.php` or `user_add.php` directly (inline `$success`/`$generatedPassword` var, mirroring `change_password.php`'s inline-`$success` model), not passed through redirect.

**Page header + list wrapper** (lines 22-33):
```php
<div class="admin-page-header">
  <h1>Osoitekirja</h1>
  <div class="page-actions">
    <a href="<?= e(SITE_URL) ?>/admin/contact_add.php" class="btn">+ Lisää yhteystieto</a>
  </div>
</div>
<div class="admin-body">
<?= $flash ?>
```
Reuse this header/flash wrapper exactly; swap in `<h1>Käyttäjähallinta</h1>` and link to `user_add.php`.

**D-01 deviation — use `<table>` not `.compact-list`.** No existing admin table-based list view was found as an analog in `contacts.php`/`horses.php` family (all use `.compact-list`); CONTEXT.md explicitly directs a traditional `<table>` here. Use existing generic `.admin-card`/`.btn-sm`/`.btn-danger` classes for actions but construct a plain `<table><thead><tbody>` markup — no compact-list JS (`adminToggleExpand`) needed since D-01 requires all actions visible per-row without click-to-expand.

**Inline delete/toggle form pattern (per-row, no separate page)** (contacts.php lines 65-77):
```php
<a href="<?= e(SITE_URL) ?>/admin/contact_edit.php?id=<?= (int)$c['id'] ?>" class="btn-sm btn-edit">✏️ Muokkaa</a>
<form method="post" action="<?= e(SITE_URL) ?>/admin/contact_delete.php" style="display:inline">
  <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
  <button type="submit" class="btn-sm btn-danger"
          onclick="return confirm('Poistetaanko yhteystieto <?= e(addslashes($displayName)) ?>?')">🗑 Poista</button>
</form>
```
Replicate this exact shape for each row's action group in `users.php`: one inline `<form>` per action (edit is a link `<a>`, reset-password/toggle-active/delete are inline POST `<form>`s with hidden `csrf_token` + `id`). Toggle-active and reset-password reuse this same inline-form/CSRF shape but POST to `user_toggle_active.php` / `user_reset_password.php` respectively. Only delete needs `confirm()` per D-07 (same convention as `contact_delete.php`).

---

### `public/admin/user_add.php` (controller, create)

**Primary analog:** `public/admin/contact_add.php` (form/CSRF/validation/redirect skeleton)
**Secondary analog:** `public/admin/change_password.php` (password hashing + inline-success display, because generated password cannot travel via `$_GET`)

**Guard + CSRF + validate pattern** (contact_add.php lines 1-18):
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

$db = getDB();
$errors = [];
$f = ['username' => '', 'role' => 'author'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        $f['username'] = sanitize($_POST['username'] ?? '');
        $f['role']     = sanitize($_POST['role'] ?? '');
        $val = validate_string($f['username'], 1, 255); // mirror change_password.php's validate_string use
        if (!$val['valid']) $errors[] = $val['error'];
        if (!in_array($f['role'], ['admin','mod','author'], true)) $errors[] = 'Virheellinen rooli.';
        // uniqueness check: SELECT COUNT(*) FROM admin_users WHERE username = :username
    }
}
```

**Password generation + hash pattern** (change_password.php lines 25-38, adapted — no `current_password` check needed here since admin creates a new account):
```php
$val = validate_string($new, 8, 255); // D-10: min 8 merkkiä
...
$hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
$db->prepare('UPDATE admin_users SET password = :hash WHERE id = :id')
   ->execute([':hash' => $hash, ':id' => $_SESSION['admin_id']]);
```
For `user_add.php`, generate via `random_bytes()`-based function (Claude's Discretion, D-02) producing a string ≥ 8 chars, then `password_hash($generated, PASSWORD_BCRYPT, ['cost' => 12])`, `INSERT INTO admin_users (username, password, role, is_active) VALUES (...)`.

**Insert pattern** (contact_add.php lines 30-44):
```php
if (empty($errors)) {
    $stmt = $db->prepare('INSERT INTO contacts (...) VALUES (...)');
    $stmt->execute([...]);
    redirect(SITE_URL . '/admin/contacts.php?added=1');
}
```
Deviation: do NOT redirect immediately — the generated password must be shown once on this same page (inline `$success` + `$generatedPassword` var), mirroring `change_password.php`'s inline-success pattern (no redirect on success, render success block inline in same request).

**Inline success/error render pattern** (change_password.php lines 45-53):
```php
<div class="admin-card" style="max-width:420px">
  <h2>Vaihda salasana</h2>
  <?php if ($success): ?>
    <p class="flash-ok">Salasana vaihdettu onnistuneesti.</p>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="flash-err"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
```
For `user_add.php`: on success render `<p class="flash-ok">Käyttäjä luotu. Salasana: <code><?= e($generatedPassword) ?></code></p>` instead of redirecting.

**Form markup pattern** (contact_add.php lines 60-105) — reuse `.form-group`/`.form-row`/`<button class="btn">`/`<a class="btn-ghost">` structure; fields reduced to `username` (text) + `role` (select: admin/mod/author) only — no password field (D-02).

---

### `public/admin/user_edit.php` (controller, update)

**Analog:** `public/admin/contact_edit.php` (exact structural match)

**Fetch-by-id-or-redirect pattern** (lines 5-12):
```php
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect(SITE_URL . '/admin/contacts.php');

$db = getDB();
$contact = $db->prepare('SELECT * FROM contacts WHERE id = :id');
$contact->execute([':id' => $id]);
$contact = $contact->fetch();
if (!$contact) redirect(SITE_URL . '/admin/contacts.php');
```
Reuse directly for `admin_users` lookup by id.

**Update pattern with CSRF + validation** (lines 17-56): reuse full shape — sanitize inputs, validate, `UPDATE admin_users SET username=:username, role=:role WHERE id=:id`, redirect to `users.php?updated=1`.

**D-05 self-protection addition (new logic, no existing analog — construct from domain rules):**
```php
if ($id === (int)$_SESSION['admin_id'] && $f['role'] !== 'admin') {
    $errors[] = 'Et voi muuttaa omaa rooliasi pois administilta.';
}
```
Place this check alongside other validation before the `if (empty($errors))` insert block, same position as email/url validations in `contact_edit.php` lines 27-37.

---

### `public/admin/user_reset_password.php` (controller, action)

**Analogs:** `public/admin/contact_delete.php` (POST-only action shape, no separate view needed for CSRF+redirect) + `public/admin/change_password.php` (hash generation)

**POST-only guard pattern** (contact_delete.php lines 1-14):
```php
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
```

**Password generation + hash + inline display (D-04):** Because the plaintext generated password must be shown once and cannot go through `$_GET` (CONTEXT.md explicit constraint), this file cannot simply `redirect()` like `contact_delete.php`. Instead it needs its own minimal HTML output after the `admin_header.php` include (same inline-success approach as `change_password.php`/planned `user_add.php`): generate password, hash via `password_hash($generated, PASSWORD_BCRYPT, ['cost' => 12])`, `UPDATE admin_users SET password=:hash WHERE id=:id`, then render an `admin-card` with `<p class="flash-ok">Uusi salasana: <code><?= e($generatedPassword) ?></code></p>` and a link back to `users.php`.

---

### `public/admin/user_toggle_active.php` (controller, action)

**Analog:** `public/admin/contact_delete.php` (POST-only action shape, pure redirect — this one CAN redirect since no secret is involved)

**Pattern:**
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(SITE_URL . '/admin/users.php');
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) redirect(SITE_URL . '/admin/users.php');

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) redirect(SITE_URL . '/admin/users.php');

// USER-06/USER-07 guards (new logic, no existing analog):
// - if target is currently the only active admin AND is being deactivated -> block
// - if target id === $_SESSION['admin_id'] AND is being deactivated -> block

$db = getDB();
$db->prepare('UPDATE admin_users SET is_active = NOT is_active WHERE id = :id')->execute([':id' => $id]);
redirect(SITE_URL . '/admin/users.php?toggled=1');
```
Last-admin check pattern (construct fresh, no existing analog — closest conceptual precedent is the `contact_delete.php` in-use guard at lines 18-25 which blocks an action based on a COUNT query before executing):
```php
$count = $db->prepare("SELECT COUNT(*) FROM admin_users WHERE role='admin' AND is_active=1");
$count->execute();
if ((int)$count->fetchColumn() <= 1 && /* target is that last active admin being deactivated */) {
    redirect(SITE_URL . '/admin/users.php?err=lastadmin');
}
```

---

### `public/admin/user_delete.php` (controller, action)

**Analog:** `public/admin/contact_delete.php` (exact match — same POST-only, CSRF, guard-then-delete shape)

**Full pattern to copy** (contact_delete.php lines 1-28):
```php
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

// USER-06/USER-07 guards replace contacts.php's horse-usage COUNT guard:
// - block if target is last active admin
// - block if target id === $_SESSION['admin_id']

$db->prepare('DELETE FROM admin_users WHERE id = :id')->execute([':id' => $id]);
redirect(SITE_URL . '/admin/users.php?deleted=1');
```
The `contact_delete.php` in-use-count guard (lines 18-25) is the structural template for both the last-admin check and self-delete check — same "COUNT query, if condition met redirect without deleting" shape, just different predicate.

---

### `public/admin/includes/admin_header.php` (modified — nav link)

**Analog:** itself, existing admin-only link block (lines 334-338):
```php
<div class="admin-nav-section">Sivusto</div>
<?php if (in_array($role, ['admin'], true)): ?>
<a class="admin-nav-item <?= $_activePage === 'settings' ? 'active' : '' ?>"
   href="<?= e(SITE_URL) ?>/admin/settings.php">⚙️ Asetukset</a>
<?php endif; ?>
```
`$role` is already set at line 290 via `$role = currentRole();`. Add a new admin-only `<a class="admin-nav-item ...">` for `users.php` following this exact conditional-wrap pattern (Claude's Discretion on exact placement per CONTEXT.md — either alongside `settings.php` in the "Sivusto" section or a new nav item near top "Päävalikko" section which already checks `in_array($role, ['admin','mod','author'], true)` at line 293 for broader-role links).

---

## Shared Patterns

### Role guard
**Source:** `public/src/includes/helpers.php` lines 84-89 (`requireRole()`), 67-69 (`currentRole()`), 74-76 (`isAdmin()`)
**Apply to:** All 6 new controller files — use `requireRole('admin')` only (this entire file family is admin-exclusive per phase domain, unlike `contacts.php` which allows `admin, mod`).
```php
function requireRole(string ...$allowedRoles): void {
    requireLogin();
    if (!in_array(currentRole(), $allowedRoles, true)) {
        redirect(SITE_URL . '/admin/ei-oikeutta.php');
    }
}
```

### CSRF
**Source:** `public/admin/contact_add.php` line 10 (`validate_csrf_token`), all forms line `generate_csrf_token()`
**Apply to:** All POST forms in `user_add.php`/`user_edit.php`/`user_reset_password.php`/`user_toggle_active.php`/`user_delete.php`.
```php
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) { $errors[] = 'Virheellinen pyyntö.'; }
```
and in markup:
```php
<input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
```

### Password hashing
**Source:** `public/admin/change_password.php` line 33
**Apply to:** `user_add.php`, `user_reset_password.php`
```php
$hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
```

### Input validation
**Source:** `public/src/includes/helpers.php` line 274 (`validate_string`)
**Apply to:** username field in `user_add.php`/`user_edit.php`; generated password length check
```php
function validate_string($input, int $min = 1, int $max = 255): array { ... }
```
Returns `['valid' => bool, 'error' => string, 'value' => string]`.

### Flash messaging
**Source:** `public/admin/contacts.php` lines 14-17 (`$_GET`-based, for non-secret outcomes: added/updated/deleted/toggled) and `public/admin/change_password.php` lines 6, 48-53 (inline `$success`/`$errors` var, for outcomes carrying a one-time secret — password display)
**Apply to:** `users.php` (GET-based flash for add/update/delete/toggle), `user_add.php` + `user_reset_password.php` (inline flash carrying generated password — MUST NOT use `$_GET`).

### Delete confirmation
**Source:** `public/admin/contacts.php` line 72
**Apply to:** delete button only (per D-07), not toggle-active or reset-password buttons
```php
onclick="return confirm('Poistetaanko yhteystieto <?= e(addslashes($displayName)) ?>?')"
```

### CSS classes (no new CSS needed)
`.admin-card`, `.form-group`, `.form-row`, `.flash-ok`, `.flash-err`, `.btn`, `.btn-sm`, `.btn-ghost`, `.btn-danger`, `.admin-page-header`, `.admin-body`, `.page-actions` — all already defined in existing admin stylesheet, reused as-is across all new files.

## No Analog Found

| File/Logic | Role | Data Flow | Reason |
|------------|------|-----------|--------|
| Last-admin protection logic (USER-06) | validation | CRUD guard | No existing codebase feature protects a "last row of a kind" — must be built fresh using `contact_delete.php`'s in-use-COUNT-guard shape as structural template (see `user_delete.php`/`user_toggle_active.php` sections above) |
| Self-protection logic (USER-07, D-05) | validation | CRUD guard | No existing codebase pattern checks `$_SESSION['admin_id'] === $targetId`; build fresh, same conditional-then-error-then-skip-action shape as other validations |
| Random password generator | utility | transform | No existing `random_bytes`-based generator in `helpers.php`; must add new helper function (Claude's Discretion on length/charset per D-02) |
| `<table>`-based list markup | component | CRUD (read) | All existing list views (`contacts.php`, `horses.php` family) use `.compact-list` div-grid, not `<table>`; D-01 explicitly diverges — build fresh HTML table using existing `.admin-card`/button classes for cell content only |

## Metadata

**Analog search scope:** `public/admin/*.php`, `public/admin/includes/admin_header.php`, `public/src/includes/helpers.php`
**Files scanned:** 6 (contacts.php, contact_add.php, contact_edit.php, contact_delete.php, change_password.php, admin_header.php) + helpers.php grep
**Pattern extraction date:** 2026-07-16
