---
phase: 12-sis-lt-tyyppien-roolirajaus
reviewed: 2026-07-17T06:57:29Z
depth: standard
files_reviewed: 4
files_reviewed_list:
  - database/migrate_posts_author.sql
  - database/schema.sql
  - public/admin/posts.php
  - public/src/includes/helpers.php
findings:
  critical: 0
  warning: 4
  info: 3
  total: 7
status: issues_found
---

# Phase 12: Code Review Report

**Reviewed:** 2026-07-17T06:57:29Z
**Depth:** standard
**Files Reviewed:** 4
**Status:** issues_found

## Summary

Reviewed the posts-ownership (author_id) authorization work: schema/migration for `posts.author_id`, the new `requireOwnResourceOrAdmin()` / `requireRole()` helpers, and the wiring in `public/admin/posts.php` (list filtering, GET edit-guard, POST edit-guard, insert stamping).

The core IDOR defense is sound: identity is always derived from `$_SESSION['admin_id']` (never from request input), the GET edit path correctly checks ownership before rendering another author's data, the POST edit path checks ownership before the `UPDATE`, `author_id` is stamped server-side on insert (never client-controlled), and the list query is filtered server-side for the `author` role using a bound parameter. I did not find a way for an `author` to read, modify, or enumerate another author's post content through the reviewed files.

That said, there are real correctness/robustness gaps: the POST edit path silently no-ops (and still reports "success") when the target post doesn't exist, because the ownership check is skipped entirely in that branch instead of erroring out like the GET path does; the migration's backfill silently fails (leaves rows unowned) if no `admin_users.username = 'admin'` row exists, with no verification step; and the posts list still renders a "Poista" (delete) affordance for `mod`/`author` roles even though `post_delete.php` (not in this phase's file set, but directly reachable from this page) restricts deletion to `admin` only — so clicking it always bounces those roles to `ei-oikeutta.php`, which is a UX/quality defect on a page whose stated purpose this phase is role-based access wiring.

## Warnings

### WR-01: POST edit path skips ownership check and silently no-ops when the target post doesn't exist

**File:** `public/admin/posts.php:38-49`
**Issue:** The GET edit path (`posts.php:79-99`) explicitly handles the "post not found" case with `redirect(SITE_URL . '/admin/posts.php')`. The POST edit path does not: when `$ownerRow` is falsy (the crafted `edit_id` doesn't match any row), `requireOwnResourceOrAdmin()` is never called, and execution falls through to `UPDATE posts ... WHERE id=:id` (matches 0 rows, no error) and then to the `post_horses` DELETE/INSERT block using the bogus `$savedId`. The request still redirects to `?updated=1` and shows "Muutokset tallennettu" even though nothing was saved. This isn't an ownership bypass (no real row is touched), but it's a real logic bug: silent no-op reported as success, and an inconsistency between the GET and POST handling of the same "resource not found" condition.
**Fix:**
```php
$ownerChk = $db->prepare('SELECT author_id FROM posts WHERE id = :id');
$ownerChk->execute([':id' => $edit_id]);
$ownerRow = $ownerChk->fetch();
if (!$ownerRow) {
    redirect(SITE_URL . '/admin/posts.php');
}
requireOwnResourceOrAdmin((int)$ownerRow['author_id']);
```

### WR-02: Migration backfill silently no-ops if no `admin_users.username = 'admin'` row exists

**File:** `database/migrate_posts_author.sql:15`
**Issue:** `UPDATE posts SET author_id = (SELECT id FROM admin_users WHERE username = 'admin') WHERE author_id IS NULL` relies on a hardcoded username. If the installation's admin account has a different username (renamed, or multiple admin accounts with none named exactly `admin`), the subquery returns `NULL` and the `UPDATE` is a complete no-op — every existing post is left with `author_id IS NULL`, with no warning or error surfaced. Combined with the `author` role list filter (`WHERE author_id = :aid`), those legacy posts become permanently invisible to any `author` account and can only be reached by `admin`/`mod`. (This mirrors the same fragile pattern already used in `migrate_roles.sql`, so it's a pre-existing convention in this codebase, but it's worth hardening now that a second migration depends on it.)
**Fix:** Verify the target account exists before backfilling, or fail loudly:
```sql
-- Aja tarvittaessa ensin ja tarkista tulos > 0:
SELECT COUNT(*) FROM `admin_users` WHERE `username` = 'admin';
```
or backfill to the lowest-id `admin`-role account instead of a hardcoded username:
```sql
UPDATE `posts` SET `author_id` = (SELECT MIN(id) FROM `admin_users` WHERE `role` = 'admin') WHERE `author_id` IS NULL;
```

### WR-03: Posts list still shows a delete action to roles that cannot use it

**File:** `public/admin/posts.php:254-263`
**Issue:** The list view unconditionally renders a "Poista" button/form for every post row, regardless of `currentRole()`. Since this phase changed the page guard from `requireLogin()` to `requireRole('admin', 'mod', 'author')`, both `mod` and `author` users now reach this page and see the delete control — but `public/admin/post_delete.php` calls `requireRole('admin')`, so submitting it as `mod` or `author` always redirects to `ei-oikeutta.php` without deleting anything. This is not an authorization bypass (the endpoint enforces itself correctly), but it is a broken/misleading affordance directly tied to this phase's role wiring on this exact page.
**Fix:** Gate the delete column on role, consistent with the rest of the page's role-aware rendering:
```php
<?php if (isAdmin()): ?>
  <td>
    <form method="post" action="<?= e(SITE_URL) ?>/admin/post_delete.php" style="display:inline;">
      ...
    </form>
  </td>
<?php endif; ?>
```
(and adjust the `<thead>` "Poista" column similarly, or collapse the column entirely for non-admin viewers).

### WR-04: `requireOwnResourceOrAdmin()` trusts a zero/absent session id as equal to a NULL-derived `author_id`

**File:** `public/src/includes/helpers.php:86-90`
**Issue:** `$resourceAuthorId !== (int)($_SESSION['admin_id'] ?? 0)` treats a missing `$_SESSION['admin_id']` as `0`. If a resource's `author_id` is `NULL` (cast to `0` by the caller, e.g. from an unbackfilled legacy post per WR-02) and `$_SESSION['admin_id']` were ever unset while `currentRole()` still returned `'author'`, the comparison `0 !== 0` is `false` and the function would silently grant access instead of denying it. In the current codebase `login.php` always sets `admin_id` and `admin_logged_in` together, so this is not currently reachable, but the function's fail-open shape (defaulting the "current user" side to a value that can coincidentally match an "unowned" resource) is fragile defense-in-depth for a function whose whole purpose is defense-in-depth.
**Fix:** Fail closed instead of defaulting to a value that can match:
```php
function requireOwnResourceOrAdmin(int $resourceAuthorId): void {
    $currentId = $_SESSION['admin_id'] ?? null;
    if (currentRole() === 'author' && ($currentId === null || $resourceAuthorId !== (int)$currentId)) {
        redirect(SITE_URL . '/admin/ei-oikeutta.php');
    }
}
```

## Info

### IN-01: `session_destroy()` in `requireRole()` doesn't clear `$_SESSION` or the session cookie

**File:** `public/src/includes/helpers.php:106-109`
**Issue:** When a deactivated/deleted account is detected, `session_destroy()` is called and the script redirects. `session_destroy()` only removes the server-side session store entry; it does not unset the `$_SESSION` superglobal or expire the session cookie. In this specific code path it's harmless because `redirect()` exits immediately afterward, but it's a latent trap if this pattern is copied elsewhere without an immediate `exit`.
**Fix:**
```php
if (!$row || (int)$row['is_active'] !== 1) {
    $_SESSION = [];
    session_destroy();
    redirect(SITE_URL . '/admin/login.php');
}
```

### IN-02: `author_id` bound param not cast on insert

**File:** `public/admin/posts.php:53-54`
**Issue:** Every other place in this diff casts session/user-derived ids with `(int)` before use (e.g. `requireOwnResourceOrAdmin((int)$ownerRow['author_id'])`), but the insert path binds `$_SESSION['admin_id']` directly: `':aid' => $_SESSION['admin_id']`. It's not exploitable (PDO parameter binding, and the value only ever comes from `login.php`'s own `$row['id']`), but it's an inconsistency with the surrounding defensive-casting style.
**Fix:** `':aid' => (int)$_SESSION['admin_id']`.

### IN-03: Migration is not idempotent / has no re-run guard

**File:** `database/migrate_posts_author.sql:8-12`
**Issue:** `ALTER TABLE posts ADD COLUMN author_id ... ADD CONSTRAINT fk_posts_author ...` will error if run twice (duplicate column / duplicate constraint name), unlike `schema.sql`'s `CREATE TABLE IF NOT EXISTS` guards. This matches the pre-existing convention of other `migrate_*.sql` files in this repo (e.g. `migrate_roles.sql`), so it's not a regression introduced by this phase, but is worth calling out since a re-run (e.g. accidentally importing it twice against the same database) will fail loudly rather than being a safe no-op.
**Fix:** Optional — add an `information_schema` guard, or document clearly (as the header comment already does) that this is a one-time script.

---

_Reviewed: 2026-07-17T06:57:29Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
