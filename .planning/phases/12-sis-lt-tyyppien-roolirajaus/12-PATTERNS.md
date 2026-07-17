# Phase 12: Sisältötyyppien roolirajaus - Pattern Map

**Mapped:** 2026-07-16
**Files analyzed:** 3 (1 modified PHP file, 1 new migration file, 0 new PHP files — helpers.php addition is optional per Claude's Discretion)
**Analogs found:** 3 / 3

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|--------------------|------|-----------|-----------------|---------------|
| `public/admin/posts.php` (modify) | controller (list+add+edit combined) | CRUD + request-response | itself (existing file, same file gets ownership filter added) | exact — self-pattern extension |
| `database/migrate_posts_author.sql` (new) | migration | batch (DDL + backfill UPDATE) | `database/migrate_roles.sql` | exact |
| `public/src/includes/helpers.php` (optional addition: `requireOwnResourceOrAdmin()` or inline check) | utility | request-response (auth guard) | `requireRole()` in same file (lines 84-102) | exact |

No other files are created or modified in this phase — `post_delete.php` stays untouched (admin-only, unchanged), and Phase 10's page-level `requireRole()` guards on all other content files remain unmodified (confirmed by CONTEXT.md D-04, verification-only, no new code expected there).

## Pattern Assignments

### `public/admin/posts.php` (controller, CRUD, modify in place)

**Analog:** itself — extend existing patterns already present in the file.

**Role guard already in place** (line 1-3):
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod', 'author');
```
No change needed here — `author` is already allowed onto the page. New work is ownership filtering *within* the already-permitted access.

**INSERT pattern needing `author_id`** (lines 43-47):
```php
} else {
    $db->prepare('INSERT INTO posts (title, slug, content) VALUES (:t, :s, :c)')
       ->execute([':t'=>$title, ':s'=>$slug, ':c'=>$content]);
    $savedId = (int)$db->lastInsertId();
    $redirectParam = 'added=1';
}
```
Per D-02, extend to:
```php
$db->prepare('INSERT INTO posts (title, slug, content, author_id) VALUES (:t, :s, :c, :aid)')
   ->execute([':t'=>$title, ':s'=>$slug, ':c'=>$content, ':aid'=>$_SESSION['admin_id']]);
```
`$_SESSION['admin_id']` is the established session key for the current user's ID (used throughout `helpers.php`, e.g. line 89 in `requireRole()`).

**UPDATE branch needing ownership check (defense-in-depth, IDOR)** (lines 38-42):
```php
if ($edit_id > 0) {
    $db->prepare('UPDATE posts SET title=:t, slug=:s, content=:c WHERE id=:id')
       ->execute([':t'=>$title, ':s'=>$slug, ':c'=>$content, ':id'=>$edit_id]);
    $savedId = $edit_id;
    $redirectParam = 'updated=1';
}
```
Per Claude's Discretion note (research Pitfall 2 — IDOR via crafted POST), this branch needs an ownership check when `currentRole() === 'author'` BEFORE the UPDATE executes — e.g. fetch `author_id` for `$edit_id` first and redirect to `ei-oikeutta.php` (same target as GET-side check below) if mismatched, or add `AND author_id = :aid` to the WHERE clause when role is author and verify affected row count.

**GET `action=edit` branch needing ownership check for direct-URL access (SC3)** (lines 70-87):
```php
if ($action === 'edit' && $edit_id > 0) {
    $editPost = $db->prepare('SELECT * FROM posts WHERE id = :id');
    $editPost->execute([':id' => $edit_id]);
    $editPost = $editPost->fetch();
    if ($editPost) {
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
```
Per D-03/SC3: after fetching `$editPost`, if `currentRole() === 'author'` and `(int)$editPost['author_id'] !== (int)$_SESSION['admin_id']`, redirect to `SITE_URL . '/admin/ei-oikeutta.php'` — same redirect target `requireRole()` already uses (see `ei-oikeutta.php` pattern below).

**Listing query needing role-based ownership filter (D-03)** (line 105):
```php
$posts = $db->query('SELECT id, title, slug, created_at FROM posts ORDER BY created_at DESC')->fetchAll();
```
Extend to conditionally filter when role is author:
```php
if (currentRole() === 'author') {
    $posts = $db->prepare('SELECT id, title, slug, created_at FROM posts WHERE author_id = :aid ORDER BY created_at DESC');
    $posts->execute([':aid' => $_SESSION['admin_id']]);
    $posts = $posts->fetchAll();
} else {
    $posts = $db->query('SELECT id, title, slug, created_at FROM posts ORDER BY created_at DESC')->fetchAll();
}
```
`currentRole()` is defined in `public/src/includes/helpers.php` line 67.

---

### `database/migrate_posts_author.sql` (migration, batch)

**Analog:** `database/migrate_roles.sql` (full file, 16 lines)

**Header + comment + phpMyAdmin instruction convention:**
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

Apply same structure for the new migration:
- Header comment block explaining purpose + phpMyAdmin import instruction.
- `ALTER TABLE posts ADD COLUMN author_id ...` (nullable initially or with FK to `admin_users.id`, per D-01 requires immediate backfill so no NULLs remain — consider FK constraint `fk_posts_author` referencing `admin_users(id)`).
- Explicit `UPDATE` statement per D-01: `UPDATE posts SET author_id = (SELECT id FROM admin_users WHERE username = 'admin')`.

**Current `posts` table schema for reference** (`database/schema.sql` lines 261-270):
```sql
CREATE TABLE IF NOT EXISTS `posts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255) NOT NULL,
  `slug`       VARCHAR(255) NOT NULL,
  `content`    MEDIUMTEXT   NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_post_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
No `author_id` column exists yet — confirms CONTEXT.md's finding. Note: `database/schema.sql` itself should also be updated to reflect the new column for fresh installs (consistent with how `migrate_roles.sql`'s change is NOT reflected back into `schema.sql`'s `admin_users` block — check current schema.sql for whether role/is_active already appear there; if the project's convention is migrations-only without updating schema.sql, follow that; if schema.sql was updated after migrate_roles.sql, mirror that too).

---

### Ownership guard helper (optional, Claude's Discretion)

**Analog:** `requireRole()` in `public/src/includes/helpers.php` (lines 84-102)

```php
function requireRole(string ...$allowedRoles): void {
    requireLogin();

    $db = getDB();
    $stmt = $db->prepare('SELECT role, is_active FROM admin_users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['admin_id'] ?? 0]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['is_active'] !== 1) {
        session_destroy();
        redirect(SITE_URL . '/admin/login.php');
    }

    $_SESSION['admin_role'] = $row['role'];

    if (!in_array($row['role'], $allowedRoles, true)) {
        redirect(SITE_URL . '/admin/ei-oikeutta.php');
    }
}
```

If a helper is introduced (e.g. `requireOwnResourceOrAdmin(int $resourceAuthorId): void`), follow this file's conventions: no return value, redirects via `redirect(SITE_URL . '/admin/ei-oikeutta.php')` on failure, placed in `public/src/includes/helpers.php` alongside `requireRole()`/`currentRole()`/`isAdmin()`. Signature suggestion consistent with existing helper naming (`isAdmin()`, `currentRole()`):
```php
function requireOwnResourceOrAdmin(int $resourceAuthorId): void {
    if (currentRole() === 'author' && $resourceAuthorId !== (int)($_SESSION['admin_id'] ?? 0)) {
        redirect(SITE_URL . '/admin/ei-oikeutta.php');
    }
}
```
This mirrors `isAdmin()` (lines 74-76) in being a small composable check built on `currentRole()`, and mirrors `requireRole()`'s redirect target/behavior on failure.

---

## Shared Patterns

### "Ei käyttöoikeutta" redirect target
**Source:** `public/admin/ei-oikeutta.php` (full file, 15 lines)
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireLogin(); // mikä tahansa kirjautunut rooli saa laskeutua tänne

$pageTitle = 'Ei käyttöoikeutta';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-body">
  <div class="admin-card">
    <p class="flash-err">Sinulla ei ole käyttöoikeutta tähän sivuun.</p>
    <a class="btn" href="<?= e(SITE_URL) ?>/admin/index.php">← Takaisin</a>
  </div>
</div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
```
**Apply to:** `posts.php`'s `action=edit` branch (GET direct-URL attempt) and POST UPDATE handler (defense-in-depth) — redirect via `redirect(SITE_URL . '/admin/ei-oikeutta.php')` exactly as `requireRole()` does internally (line 100 of `helpers.php`). No new page/message needed — reuse as-is per CONTEXT.md D-04/specifics section.

### Session-based current-user identity
**Source:** `public/src/includes/helpers.php` — `currentRole()` (lines 67-69), `$_SESSION['admin_id']` (used at line 89)
```php
function currentRole(): ?string {
    return $_SESSION['admin_role'] ?? null;
}
```
**Apply to:** all ownership comparisons and `author_id` assignment in `posts.php` — always compare against `$_SESSION['admin_id']`, never trust POST-supplied user IDs.

### CSRF validation
**Source:** `public/admin/posts.php` lines 11-13 (already present, no change needed)
```php
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    $errors[] = 'Virheellinen pyyntö.';
}
```
**Apply to:** No new form needs this — existing form already includes CSRF token. Included here only as a note that any new ownership-check branch must execute AFTER CSRF validation, not bypass it.

### Migration file convention
**Source:** `database/migrate_roles.sql` (full file — see above)
**Apply to:** `database/migrate_posts_author.sql` — same header/comment/phpMyAdmin-instruction/explicit-UPDATE structure.

## No Analog Found

None — all files to be created/modified in this phase have direct analogs in the existing codebase (this phase is explicitly scoped as a narrow extension of already-established patterns per CONTEXT.md's domain section).

## Metadata

**Analog search scope:** `public/admin/`, `public/src/includes/`, `database/`
**Files scanned:** `posts.php`, `helpers.php`, `ei-oikeutta.php`, `migrate_roles.sql`, `schema.sql` (posts/post_horses section)
**Pattern extraction date:** 2026-07-16
