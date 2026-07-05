# Phase 10: Roolit ja autentikaation perusta - Pattern Map

**Mapped:** 2026-07-05
**Files analyzed:** 33 (2 new pages, 1 new migration, 2 modified core files, 28 existing `admin/*.php` one-line gate swaps)
**Analogs found:** 33 / 33 (this phase is entirely additive/extensive on top of existing, already-analogous code — no external pattern needed)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `public/src/includes/helpers.php` (add `requireRole()`, `currentRole()`, `isAdmin()`) | utility/middleware | request-response | `isLoggedIn()`/`requireLogin()` in same file (lines 51-62) | exact |
| `public/admin/login.php` (extend session write + `is_active` check) | controller | request-response (auth) | itself (existing lines 20-33) | exact (self-modification) |
| `public/admin/includes/admin_header.php` (nav role-gating + sidebar footer link) | component/layout | request-response | itself (existing nav block lines 290-317, footer 318-324) | exact (self-modification) |
| `database/migrate_roles.sql` (NEW) | migration | batch/DDL | `database/migrate_theme.sql` | exact |
| `public/admin/change_password.php` (NEW) | controller | request-response (CRUD-like: read+update one row) | `public/admin/login.php` (auth verify pattern) + `helpers.php` CSRF/validate_string | role-match (composite) |
| `public/admin/ei-oikeutta.php` (NEW) | controller/component | request-response | any simple existing admin page using `admin_header.php` (e.g. `settings.php`'s minimal shape) | role-match |
| 24 plain content-page files (see table below) — swap `requireLogin();` → `requireRole(...)` | controller | request-response | `public/admin/horses.php` / `public/admin/contacts.php` (one-line call site pattern) | exact |
| `foals.php`, `kasvatus_all.php`, `competitions.php`, `showrecords.php` — file-level gate + inline delete sub-gate | controller | CRUD (mixed add/edit/delete dispatch) | `public/admin/foals.php` (verified lines 3, 59-129) is itself the reference for the other 3 | exact (self-referential pattern, verified against foals.php) |
| `horse_delete.php`, `post_delete.php`, `photo_delete.php`, `contact_delete.php` — file-level `requireRole()` | controller | request-response (single-purpose delete endpoint) | same call-site swap as content pages | exact |

## Pattern Assignments

### `public/src/includes/helpers.php` (utility/middleware, request-response)

**Analog:** same file, `isLoggedIn()`/`requireLogin()` (lines 51-62)

**Existing pattern to mirror** (verified, lines 48-62):
```php
/**
 * Tarkistaa onko admin kirjautunut sisään
 */
function isLoggedIn(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Vaatii admin-kirjautumista — ohjaa login-sivulle jos ei kirjautunut
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/admin/login.php');
    }
}
```

**Redirect helper already available** (line 40-46, reuse verbatim — do not reimplement):
```php
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
```

**New functions to add immediately after `requireLogin()` (after line 62), matching doc-comment style and PHP 8.2 typed-signature convention used throughout this file:**
```php
/**
 * Palauttaa kirjautuneen käyttäjän roolin, tai null jos ei kirjautunut.
 */
function currentRole(): ?string {
    return $_SESSION['admin_role'] ?? null;
}

/**
 * Oikotie: onko kirjautunut käyttäjä admin.
 */
function isAdmin(): bool {
    return currentRole() === 'admin';
}

/**
 * Vaatii kirjautumisen JA että käyttäjän rooli on jokin sallituista.
 * Käytetään requireLogin()-kutsun tilalla jokaisen suojatun sivun alussa.
 *
 * @param string ...$allowedRoles esim. requireRole('admin', 'mod')
 */
function requireRole(string ...$allowedRoles): void {
    requireLogin();
    if (!in_array(currentRole(), $allowedRoles, true)) {
        redirect(SITE_URL . '/admin/ei-oikeutta.php');
    }
}
```

**Reusable for `change_password.php`** — CSRF pair (verified lines 212-237):
```php
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(?string $token): bool {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token ?? '');
}
```

**Reusable for `change_password.php`** — length validator (verified lines 239-271), returns `['valid'=>bool,'value'=>string,'error'=>?string]`:
```php
function validate_string($input, int $min = 1, int $max = 255): array {
    $input = is_string($input) ? trim($input) : '';
    $len = strlen($input);
    if ($len < $min) {
        return ['valid' => false, 'error' => "Teksti on liian lyhyt (vähintään $min merkkiä).", 'value' => ''];
    }
    if ($len > $max) {
        return ['valid' => false, 'error' => "Teksti on liian pitkä (enintään $max merkkiä).", 'value' => ''];
    }
    return ['valid' => true, 'value' => $input, 'error' => null];
}
```
Call as `validate_string($new, 8, 255)` per D-10 (min 8 chars).

---

### `public/admin/login.php` (controller, request-response — auth)

**Analog:** itself. Full file read (73 lines) — extend, don't restructure.

**Current session/DB block (verified lines 19-33, exact text):**
```php
$db = getDB();
$stmt = $db->prepare('SELECT id, username, password FROM admin_users WHERE username = :username LIMIT 1');
$stmt->execute([':username' => $username]);
$row = $stmt->fetch();

if ($row && password_verify($password, $row['password'])) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id']       = $row['id'];
    $_SESSION['admin_username'] = $row['username'];
    redirect(SITE_URL . '/admin/index.php');
} else {
    $error = 'Väärä käyttäjätunnus tai salasana.';
}
```

**Required diff** (add `role`, `is_active` to SELECT; check `is_active`; write `admin_role`):
```php
$stmt = $db->prepare('SELECT id, username, password, role, is_active FROM admin_users WHERE username = :username LIMIT 1');
$stmt->execute([':username' => $username]);
$row = $stmt->fetch();

if ($row && password_verify($password, $row['password']) && (int)$row['is_active'] === 1) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id']       = $row['id'];
    $_SESSION['admin_username'] = $row['username'];
    $_SESSION['admin_role']     = $row['role'];
    redirect(SITE_URL . '/admin/index.php');
} else {
    $error = 'Väärä käyttäjätunnus tai salasana.'; // deliberately generic — do not distinguish deactivated vs wrong password
}
```

**Everything else in the file (CSRF check lines 12-14, `sanitize()` on username line 16, HTML form lines 36-73) stays untouched** — form markup and `generate_csrf_token()` usage in the hidden input (line 59) are the model for `change_password.php`'s form.

---

### `public/admin/includes/admin_header.php` (component/layout)

**Analog:** itself, verified 328-line file, sections read in full.

**`$_activePage` detection (verified line 7), reuse for new pages' active-nav state if needed:**
```php
$_activePage = basename($_SERVER['PHP_SELF'], '.php');
```

**Current nav block shape (verified lines 290-317) — one link per line, `in_array($_activePage, [...])` drives the `active` CSS class only, no role gating today:**
```php
<nav>
  <div class="admin-nav-section">Päävalikko</div>
  <a class="admin-nav-item <?= in_array($_activePage, ['index']) ? 'active' : '' ?>"
     href="<?= e(SITE_URL) ?>/admin/">⊞ Dashboard</a>
  <a class="admin-nav-item <?= in_array($_activePage, ['horses','horse_add','horse_edit','horse_delete']) ? 'active' : '' ?>"
     href="<?= e(SITE_URL) ?>/admin/horses.php">🐎 Hevoset</a>
  ... (Osoitekirja, Sukulaiset, Tuo VRL:stä, Kasvatus, Kilpailut, Näyttelyt, Kuvat, Postaukset, Asetukset, Julkinen sivu)
</nav>
```

**Required pattern — wrap each item in a role check using the SAME allowed-role array as that page's `requireRole()` call** (per audit table below), e.g.:
```php
<?php $role = currentRole(); ?>
<?php if (in_array($role, ['admin','mod'], true)): ?>
  <a class="admin-nav-item <?= in_array($_activePage, ['horses','horse_add','horse_edit','horse_delete']) ? 'active' : '' ?>"
     href="<?= e(SITE_URL) ?>/admin/horses.php">🐎 Hevoset</a>
<?php endif; ?>
```
`Postaukset` link is `admin`+`mod`+`author` (all roles, no wrap needed — or wrap in `in_array($role, ['admin','mod','author'], true)` for consistency). `Asetukset` (settings.php) wraps in `admin`-only. `Julkinen sivu` external link stays unwrapped (not an admin resource).

**Sidebar footer (verified lines 318-324) — insertion point for `change_password.php` link, per D-08 ("between username and Kirjaudu ulos"):**
```php
<div class="admin-sidebar-footer">
  <div class="sb-username"><?= e($_SESSION['admin_username'] ?? '') ?></div>
  <!-- NEW: insert change_password.php link here -->
  <a href="<?= e(SITE_URL) ?>/admin/change_password.php" class="sb-logout-btn" style="display:block;margin-top:0.3rem;">Vaihda salasana</a>
  <form method="post" action="<?= e(SITE_URL) ?>/admin/logout.php" style="margin-top:0.3rem">
    <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
    <button type="submit" class="sb-logout-btn">Kirjaudu ulos →</button>
  </form>
</div>
```
Reuses the existing `.sb-logout-btn` CSS class (verified lines 74-80) for visual consistency — no new CSS needed.

**CRITICAL ordering note (Pitfall 4):** `admin_header.php` is `require`d *after* other logic in some pages (`index.php`, `contacts.php`, `horse_import_vrl.php`, `kilpailut_all.php` per research). `requireRole()` must stay at the top of each page script (its `requireLogin();` call site), never moved into this header file — this header only *reads* `currentRole()` for nav rendering.

---

### `database/migrate_roles.sql` (NEW, migration)

**Analog:** `database/migrate_theme.sql` (verified, full 10-line file)
```sql
-- ============================================================
-- Teema-infrastruktuuri — active_theme-asetus
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

-- settings-taulu on jo olemassa (migrate_settings.sql loi sen).
-- INSERT IGNORE ei ylikirjoita olemassa olevaa arvoa toistuvilla ajoilla.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('active_theme', 'default');
```

**Current `admin_users` schema (verified `database/schema.sql` lines 245-252 — no `role`/`is_active` columns yet):**
```sql
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'bcrypt-tiiviste',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**New migration file to write, matching the comment-header + phpMyAdmin-instruction + explicit-statement convention above (per D-06/D-07):**
```sql
-- ============================================================
-- Roolit ja aktiivisuustila — admin_users.role, admin_users.is_active
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

ALTER TABLE `admin_users`
  ADD COLUMN `role` ENUM('admin','mod','author') NOT NULL DEFAULT 'author'
    COMMENT 'admin = kaikki oikeudet, mod = rajattu sisällönhallinta, author = vain omat postaukset'
    AFTER `username`,
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Deaktivoitu tunnus ei voi kirjautua sisään'
    AFTER `role`;

-- Nosta olemassa oleva admin-tunnus eksplisiittisesti admin-rooliin.
UPDATE `admin_users` SET `role` = 'admin' WHERE `username` = 'admin';
```
Note: this migration should also update `database/schema.sql`'s `CREATE TABLE admin_users` block (lines 245-252) directly, adding `role`/`is_active` columns there too, so a fresh install matches an upgraded install — matching how other `migrate_*.sql` files in this project pair with schema.sql updates.

---

### `public/admin/change_password.php` (NEW, controller, request-response)

**Analog composite:** `public/admin/login.php` (auth verify pattern, `session_regenerate_id`) + `helpers.php` (CSRF, `validate_string`) + `public/admin/settings.php`-style page shell (`admin_header.php` include with `$pageTitle`, `.admin-card` wrapper)

**Full pattern (verified against login.php's CSRF/session_regenerate_id idiom and helpers.php's validate_string/CSRF functions):**
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod', 'author'); // any authenticated role, per D-08

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';

        $db = getDB();
        $stmt = $db->prepare('SELECT password FROM admin_users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password'])) {
            $errors[] = 'Nykyinen salasana on väärä.';
        }

        $val = validate_string($new, 8, 255); // D-10: min 8 merkkiä
        if (!$val['valid']) {
            $errors[] = $val['error'];
        } elseif ($new !== $confirm) {
            $errors[] = 'Uudet salasanat eivät täsmää.';
        }

        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('UPDATE admin_users SET password = :hash WHERE id = :id')
               ->execute([':hash' => $hash, ':id' => $_SESSION['admin_id']]);
            session_regenerate_id(true); // D-09: prevent session fixation, stay logged in
            $success = true;
        }
    }
}

$pageTitle = 'Vaihda salasana';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-body">
  <div class="admin-card" style="max-width:420px">
    <h2>Vaihda salasana</h2>
    <?php if ($success): ?>
      <p class="flash-ok">Salasana vaihdettu onnistuneesti.</p>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="flash-err"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
      <div class="form-group">
        <label for="current_password">Nykyinen salasana</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
      </div>
      <div class="form-group">
        <label for="new_password">Uusi salasana</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
      </div>
      <div class="form-group">
        <label for="new_password_confirm">Vahvista uusi salasana</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" minlength="8" required>
      </div>
      <button type="submit" class="btn">Vaihda salasana</button>
    </form>
  </div>
</div>
</div></div>
</body>
</html>
```
Uses existing `.admin-card`, `.form-group`, `.flash-ok`/`.flash-err`, `.btn` CSS classes already defined in `admin_header.php` (verified lines 226-260) — no new styling needed, per CONTEXT.md `<specifics>`.

**Page-closing markup note:** every existing `admin/*.php` file that includes `admin_header.php` closes with `</div></div>\n</body>\n</html>` (closing `.admin-main` and `.admin-shell` opened in the header) — verify this exact closing shape against a currently-passing page (e.g. `settings.php`) before finalizing, since it isn't shown in the header excerpt itself (header only opens the divs at line 283/327).

---

### `public/admin/ei-oikeutta.php` (NEW, controller/component)

**Analog:** same `admin_header.php` shell pattern as any minimal existing admin page.

```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireLogin(); // any authenticated role may land here

$pageTitle = 'Ei käyttöoikeutta';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-body">
  <div class="admin-card">
    <p class="flash-err">Sinulla ei ole käyttöoikeutta tähän sivuun.</p>
    <a class="btn" href="<?= e(SITE_URL) ?>/admin/index.php">← Takaisin</a>
  </div>
</div>
</div></div>
</body>
</html>
```
Per D-04/D-05: fixed message, fixed "Takaisin" target `admin/index.php` regardless of role — no `HTTP_REFERER`, no role→homepage mapping.

**Not added to nav** (no `admin-nav-item` entry) — only reached via `requireRole()`'s `redirect()`.

---

### 24 plain content-page files — one-line gate swap (controller, request-response)

**Analog:** `public/admin/foals.php` line 3 / any of the 28 files sharing the identical call-site shape (verified via grep — all currently read `requireLogin();` at or near the top, immediately after `require_once .../db.php;`).

**Pattern — before:**
```php
require_once __DIR__ . '/../src/includes/db.php';
requireLogin();
```
**Pattern — after** (role list per audit table below):
```php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod'); // example; see audit table for exact list per file
```

**Full file-to-role audit table** (verified via `grep -rl "requireLogin()" public/admin/` → 28 files total, cross-checked against RESEARCH.md's table):

| File | Final Role Gate | Notes |
|---|---|---|
| `index.php` | `admin`,`mod`,`author` | Same dashboard all roles (D-05); `admin_header.php` included partway through — confirm `requireRole()` precedes DB queries |
| `horses.php` | `admin`,`mod` | |
| `horse_add.php` | `admin`,`mod` | |
| `horse_edit.php` | `admin`,`mod` | |
| `horse_delete.php` | `admin` only | D-02 |
| `horse_import_vrl.php` | `admin`,`mod` | header included partway through — verify gate precedes VRL logic |
| `api/vrl_import_save.php` | `admin`,`mod` | JSON API backing endpoint |
| `contacts.php` | `admin`,`mod` | |
| `contact_add.php` | `admin`,`mod` | |
| `contact_edit.php` | `admin`,`mod` | |
| `contact_delete.php` | `admin`,`mod` | outside Phase-13 approval scope — `[ASSUMED, confirm before/at plan time]` |
| `sukulaiset.php` | `admin`,`mod` | |
| `kasvatus_all.php` | `admin`,`mod` (file); `admin` only (inline `delete` branch) | Pattern 2 — mixed file |
| `foals.php` | `admin`,`mod` (file); `admin` only (inline `delete` branch, verified line 122) | Pattern 2 — reference file |
| `foal_add.php` | `admin`,`mod` | |
| `foal_edit.php` | `admin`,`mod` | |
| `kilpailut_all.php` | `admin`,`mod` | read-only list, no POST — file gate only |
| `competitions.php` | `admin`,`mod` (file); `admin` only (inline `delete` branch) | Pattern 2 |
| `showrecords_all.php` | `admin`,`mod` | read-only list, no POST — file gate only |
| `showrecords.php` | `admin`,`mod` (file); `admin` only (inline `delete` branch) | Pattern 2 |
| `kuvat_all.php` | `admin`,`mod` | |
| `photos.php` | `admin`,`mod` | |
| `photo_update.php` | `admin`,`mod` | |
| `photo_delete.php` | `admin`,`mod` | outside Phase-13 scope, MOD-01 grants photo mgmt — `[ASSUMED]` |
| `post_delete.php` | `admin` only | D-02 explicit |
| `posts.php` | `admin`,`mod`,`author` | D-03 — author access now, ownership filter deferred to Phase 12 |
| `settings.php` | `admin` only | |
| `logout.php` | `admin`,`mod`,`author` (unchanged `requireLogin()`) | no role distinction needed |
| `login.php` | n/a (pre-auth) | modified for session write only |

Two rows flagged `[ASSUMED]` (`contact_delete.php`, `photo_delete.php`) — recommend confirming with user during planning per RESEARCH.md Open Questions, or accept the admin+mod default.

---

### Mixed create/edit/delete files — inline sub-gate (Pattern 2)

**Analog:** `public/admin/foals.php` (verified in full, lines 1-131)

**Verified exact structure to replicate in `kasvatus_all.php`, `competitions.php`, `showrecords.php`:**
```php
// Top of file — file-level gate (foals.php line 3, replace requireLogin()):
requireRole('admin', 'mod');

// ... unchanged GET rendering / form display ...

if ($_SERVER['REQUEST_METHOD'] === 'POST') {          // foals.php line 59
    // ... CSRF check unchanged (line 63) ...
    if ($action === 'add') {                            // line 66 — unchanged, admin+mod reach here
        // ...
    } elseif ($action === 'edit' && $foal_id > 0) {      // line 91 — unchanged, admin+mod reach here
        // ...
    } elseif ($action === 'delete' && $foal_id > 0) {    // line 122
        requireRole('admin'); // NEW — narrower in-branch gate, D-02
        $own = $db->prepare('SELECT id FROM foals WHERE id = :foal_id AND horse_id = :horse_id');
        $own->execute([':foal_id' => $foal_id, ':horse_id' => $horse_id]);
        if ($own->fetch()) {
            $db->prepare('DELETE FROM foals WHERE id = :foal_id')->execute([':foal_id' => $foal_id]);
        }
        redirect(SITE_URL . '/admin/foals.php?horse_id=' . $horse_id . '&deleted=1');
    }
}
```
The narrower `requireRole('admin')` call goes as the *first statement* inside the `delete` branch, before any DB read/write in that branch.

## Shared Patterns

### Authentication / Role Gate
**Source:** `public/src/includes/helpers.php` `isLoggedIn()`/`requireLogin()` (lines 51-62), extended with `requireRole()`/`currentRole()`/`isAdmin()`.
**Apply to:** All 28 existing `admin/*.php` files (call-site swap) + both new pages (`change_password.php` uses the "any role" form `requireRole('admin','mod','author')`, `ei-oikeutta.php` uses bare `requireLogin()`).
```php
function requireRole(string ...$allowedRoles): void {
    requireLogin();
    if (!in_array(currentRole(), $allowedRoles, true)) {
        redirect(SITE_URL . '/admin/ei-oikeutta.php');
    }
}
```

### CSRF Protection
**Source:** `public/src/includes/helpers.php` `generate_csrf_token()`/`validate_csrf_token()` (lines 218-237) — already used by every existing form (verified in `login.php` line 59, `admin_header.php` logout form line 321).
**Apply to:** `change_password.php`'s form (only new form introduced this phase).

### Session Regeneration on Security-Sensitive State Change
**Source:** `public/admin/login.php` line 25 (`session_regenerate_id(true)` after successful auth).
**Apply to:** `change_password.php` — call `session_regenerate_id(true)` immediately after the successful `UPDATE admin_users SET password = ...`, per Pitfall 5.

### Nav Visibility Mirrors Page Gate
**Source:** `public/admin/includes/admin_header.php` nav block (lines 290-317).
**Apply to:** every nav item — wrap in `in_array(currentRole(), [...same list as that page's requireRole()...], true)` so the two lists cannot silently drift (Pitfall/Anti-Pattern in RESEARCH.md).

### Page Shell / Card Layout
**Source:** `.admin-card`, `.form-group`, `.flash-ok`/`.flash-err`, `.btn` CSS classes defined in `admin_header.php` (lines 226-260).
**Apply to:** both new pages (`change_password.php`, `ei-oikeutta.php`) — no new CSS needed, per CONTEXT.md `<specifics>`.

## No Analog Found

None — every file in scope either already exists (call-site one-line swap) or has a directly composable analog from existing files (`login.php`, `helpers.php`, `migrate_theme.sql`, `admin_header.php`). This phase introduces no new architectural shape.

## Metadata

**Analog search scope:** `public/admin/`, `public/admin/includes/`, `public/src/includes/`, `database/`
**Files scanned:** `helpers.php`, `login.php`, `admin_header.php`, `foals.php`, `migrate_theme.sql`, `schema.sql` (admin_users block), plus a `grep -rl "requireLogin()" public/admin/` sweep (28 files confirmed)
**Pattern extraction date:** 2026-07-05
