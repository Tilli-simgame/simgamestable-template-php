---
phase: 11-k-ytt-j-hallinta
reviewed: 2026-07-16T16:54:49Z
depth: standard
files_reviewed: 8
files_reviewed_list:
  - public/src/includes/helpers.php
  - public/admin/includes/admin_header.php
  - public/admin/users.php
  - public/admin/user_add.php
  - public/admin/user_edit.php
  - public/admin/user_reset_password.php
  - public/admin/user_toggle_active.php
  - public/admin/user_delete.php
findings:
  critical: 2
  warning: 3
  info: 2
  total: 7
status: issues_found
---

# Phase 11: Code Review Report

**Reviewed:** 2026-07-16T16:54:49Z
**Depth:** standard
**Files Reviewed:** 8
**Status:** issues_found

## Summary

Reviewed the käyttäjähallinta (user management) CRUD, password-reset, activate/deactivate toggle, and delete flows. CSRF protection is applied consistently on every state-changing POST endpoint (`validate_csrf_token()` + `hash_equals`), and every SQL statement uses PDO prepared statements with bound parameters — no SQL injection was found. The self-lockout guards (`err=self`, `err=lastadmin`) are implemented correctly for delete and deactivate, and the one-time generated password is rendered only in the POST response body, never placed in a GET query string, redirect target, or log call.

However, two structural gaps undermine the security intent of this phase:

1. Access-control state (`admin_logged_in`, `admin_role`, `admin_id`) is cached in `$_SESSION` at login time and is never re-validated against the database on subsequent requests. This means deactivating, deleting, or demoting a user does **not** revoke that user's already-authenticated session — they keep full access under their old role until they happen to log out or their session expires. This directly defeats the purpose of the deactivate/delete/role-change features implemented in this phase.
2. The username length allowed by `validate_string($f['username'], 1, 255)` in `user_add.php`/`user_edit.php` does not match the `admin_users.username VARCHAR(50)` schema column, so a 51–255 character username triggers an uncaught `PDOException` (crash) instead of a clean validation message.

## Critical Issues

### CR-01: Deactivating/deleting/demoting a user does not invalidate their live session

**File:** `public/src/includes/helpers.php:58-89` (also `public/src/includes/db.php:60-80`, and by consequence `public/admin/user_toggle_active.php`, `public/admin/user_delete.php`, `public/admin/user_edit.php`)
**Issue:**
`requireLogin()` / `requireRole()` only inspect `$_SESSION['admin_logged_in']` / `$_SESSION['admin_role']` / `$_SESSION['admin_id']`. These values are populated once, at login (`public/admin/login.php:24-29`), and are never re-checked against the `admin_users` table on subsequent requests. There is no server-side session store keyed to the DB row (no session invalidation table, no "session version" column, no per-request `SELECT ... WHERE id = :admin_id AND is_active = 1`).

Consequence: an admin uses `user_toggle_active.php` to deactivate a compromised/departing account, or `user_delete.php` to delete it, or `user_edit.php` to demote it from `admin` to `mod`/`author` — but if that target user already has an open session (browser tab still logged in), they retain full access under their **old** role/active-state for the remainder of that session (up to `session.gc_maxlifetime` = 1800s, and in practice potentially much longer since `gc_maxlifetime` is a probabilistic garbage-collection hint, not a hard per-session expiry enforced by the app).

This is precisely the threat the phase's self-lockout / last-admin guards are meant to mitigate (revoking a rogue or ex-admin's access), so the feature does not deliver its stated guarantee.

**Fix:** Re-validate the session against the DB on every privileged request (or at minimum once per admin page load), e.g.:
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

    // Keep session role in sync with DB (covers role demotion mid-session)
    $_SESSION['admin_role'] = $row['role'];

    if (!in_array($row['role'], $allowedRoles, true)) {
        redirect(SITE_URL . '/admin/ei-oikeutta.php');
    }
}
```
If a per-request DB round trip is undesirable, at minimum invalidate sessions explicitly from `user_toggle_active.php`/`user_delete.php`/`user_edit.php` (e.g., a `session_version` column bumped on every mutation, checked against a value cached in `$_SESSION`).

### CR-02: Username length validation does not match DB column, causing an unhandled crash

**File:** `public/admin/user_add.php:18`, `public/admin/user_edit.php:24`
**Issue:** `validate_string($f['username'], 1, 255)` accepts usernames up to 255 characters, but the schema defines `admin_users.username VARCHAR(50)` (`database/schema.sql:247`). A username between 51 and 255 characters passes application validation and reaches the `INSERT`/`UPDATE`. Under strict SQL mode (MySQL 5.7+/8 default for InnoDB) this throws `PDOException: Data too long for column 'username'`. Since `PDO::ATTR_ERRMODE_EXCEPTION` is set (`public/src/includes/db.php:39`) and neither `user_add.php` nor `user_edit.php` wraps the `execute()` call in try/catch, the exception propagates uncaught — a fatal error page (potentially exposing the SQL statement/schema in dev environments with `display_errors` on) instead of the intended `flash-err` validation message.
**Fix:** Align the validator with the schema (and defensively catch DB-level violations):
```php
$val = validate_string($f['username'], 1, 50); // matches VARCHAR(50)
```
Optionally also wrap the write in try/catch to convert any residual constraint violation into a user-facing error rather than a crash:
```php
try {
    $stmt->execute([...]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $errors[] = 'Käyttäjän tallennus epäonnistui.';
}
```

## Warnings

### WR-01: Duplicate-username check has a TOCTOU race that surfaces as an unhandled crash

**File:** `public/admin/user_add.php:26-32`, `public/admin/user_edit.php:32-38`
**Issue:** The uniqueness check (`SELECT COUNT(*) ... WHERE username = :username`) and the subsequent `INSERT`/`UPDATE` are not run in a transaction, so two concurrent requests creating/renaming to the same username can both pass the `COUNT(*) === 0` check and both proceed to write. The second write then violates the DB `UNIQUE KEY uk_admin_username` constraint and, since there is no try/catch around the `execute()` call, raises an uncaught `PDOException` instead of the intended "Käyttäjänimi on jo käytössä." message.
**Fix:** Wrap the insert/update in a try/catch and translate a unique-constraint violation (SQLSTATE 23000) into the existing validation error:
```php
try {
    $stmt->execute([...]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        $errors[] = 'Käyttäjänimi on jo käytössä.';
    } else {
        throw $e;
    }
}
```

### WR-02: Last-admin guard is subject to a TOCTOU race under concurrent requests

**File:** `public/admin/user_toggle_active.php:29-36`, `public/admin/user_delete.php:26-33`
**Issue:** The "is this the last active admin?" check (`SELECT COUNT(*) FROM admin_users WHERE role='admin' AND is_active=1`) and the subsequent `UPDATE`/`DELETE` are two separate statements with no transaction/row locking between them. Two admins simultaneously deactivating/deleting two different admin accounts (each of which individually passes the `count > 1` check because the other hasn't committed yet) can leave the system with zero active admins, permanently locking everyone out of `requireRole('admin')`-gated pages (including `users.php` itself).
**Fix:** Wrap the check-then-act sequence in a transaction with `SELECT ... FOR UPDATE`, or add the guard as an application-level advisory lock / re-check the count immediately after the write and roll back if it now equals zero:
```php
$db->beginTransaction();
$count = $db->query("SELECT COUNT(*) FROM admin_users WHERE role='admin' AND is_active=1 FOR UPDATE")->fetchColumn();
// ... guard + write inside the same transaction ...
$db->commit();
```

### WR-03: Role-demotion of another admin has no "last admin" safety net if paired with a stale/incorrect session

**File:** `public/admin/user_edit.php:40-43`
**Issue:** The only guard against removing the last `admin` is "you cannot demote yourself" (`$id === (int)$_SESSION['admin_id']`). This relies on the assumption that the currently logged-in actor's cached `$_SESSION['admin_role']` (used by `requireRole('admin')` to even reach this page) is still accurate. Combined with CR-01 (stale session role), it is possible for an actor whose own account has already been demoted or deactivated by someone else to still hold an `admin`-gated session and demote the last *other* admin, since the code only ever protects the acting session's own row, not the actual current DB state of "how many active admins remain, of which this actor may no longer really be one." This is a secondary consequence of CR-01 rather than an independent bug, but is called out separately because the fix for CR-01 must also cover this code path.
**Fix:** Once CR-01 is fixed (role revalidated from DB per request), this scenario is closed automatically. If CR-01 cannot be fixed immediately, add an explicit "would this leave zero active admins" check to `user_edit.php` mirroring the one in `user_toggle_active.php`/`user_delete.php` whenever `$f['role'] !== 'admin'` and the target's current role is `admin`.

## Info

### IN-01: Last-admin-count query duplicated across two files

**File:** `public/admin/user_toggle_active.php:31-32`, `public/admin/user_delete.php:28-29`
**Issue:** The identical `SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND is_active = 1` query and `<= 1` guard logic is copy-pasted in both files. Any future change to the "last admin" rule (e.g., accounting for a super-admin flag) requires editing both places and is easy to miss.
**Fix:** Extract to a shared helper in `helpers.php`, e.g. `function countActiveAdmins(PDO $db): int { ... }`, and call it from both endpoints.

### IN-02: `user_add.php`/`user_edit.php` do not lowercase-normalize or otherwise canonicalize usernames

**File:** `public/admin/user_add.php:15`, `public/admin/user_edit.php:21`
**Issue:** `sanitize()` only trims and strips tags; it does not normalize case. The uniqueness check (`WHERE username = :username`) is therefore case-sensitive at the DB layer (depends on collation, but `utf8mb4_unicode_ci` used elsewhere in the schema is case-insensitive by default, so this is likely fine in practice) while the login lookup (`login.php`) also does a case-sensitive-looking match. There's no functional bug given the `_ci` collation, but the inconsistency (no explicit normalization policy for usernames) is worth documenting/deciding intentionally rather than relying on collation behavior.
**Fix:** Either explicitly document that usernames are case-insensitive (relying on `_unicode_ci` collation) or normalize with `mb_strtolower()` in `sanitize()`/on insert for clarity and portability if the collation ever changes.

---

_Reviewed: 2026-07-16T16:54:49Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
