---
phase: 11-k-ytt-j-hallinta
verified: 2026-07-16T17:30:00Z
status: passed
score: 9/9 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 8/9
  gaps_closed:
    - "Admin voi muokata olemassa olevan käyttäjän roolia ja käyttäjänimeä, ja muutos vaikuttaa käyttäjän oikeuksiin viimeistään seuraavalla pyynnöllä (ROADMAP SC2 / USER-02)."
    - "Käyttäjän luonti/muokkaus ei kaadu palvelinvirheeseen kelvollisella-näköisellä syötteellä (USER-01/USER-02)."
  gaps_remaining: []
  regressions: []
deferred: []
---

# Phase 11: Käyttäjähallinta Verification Report

**Phase Goal:** Admin voi hallita kaikkia käyttäjätunnuksia turvallisesti — talli ei voi koskaan jäädä ilman toimivaa admin-tiliä.
**Verified:** 2026-07-16T17:30:00Z
**Status:** passed
**Re-verification:** Yes — after gap closure (plan 11-04)

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | USER-01/SC1: Admin creates a user (username, generated password, role); new user can log in immediately with the given role | ✓ VERIFIED | `user_add.php` inserts with `is_active=1` and a bcrypt hash of `generate_password()`'s output; `login.php:24` accepts any row where `password_verify()` + `is_active===1` — new accounts can authenticate on the very next login. |
| 2 | USER-02/SC2: Admin edits an existing user's role and username; the change takes effect by the user's **next request at the latest** | ✓ VERIFIED (re-checked, gap closed) | `requireRole()` (`public/src/includes/helpers.php:84-102`) now runs `SELECT role, is_active FROM admin_users WHERE id = :id` keyed on `$_SESSION['admin_id']` on **every** call — i.e. on every protected page load. If the row is missing or `is_active !== 1`, it calls `session_destroy()` and redirects to `login.php` (forced logout). Otherwise it syncs `$_SESSION['admin_role'] = $row['role']` from the fresh DB value **before** evaluating `in_array($row['role'], $allowedRoles, true)`. A role demotion is therefore read from the DB and enforced on the very next request; a deactivation/deletion forces logout on the very next request. Confirmed by direct reading of the current file content (not SUMMARY claims) plus `php -l` syntax pass. |
| 3 | USER-03: Admin deactivates a user; deactivated account cannot log in, but the account row (and any future content) is preserved | ✓ VERIFIED | Unchanged since prior verification — `user_toggle_active.php` only flips `is_active`; `login.php` requires `is_active===1`. Now additionally reinforced: an *already-open* session for the deactivated user is force-logged-out on its next protected request via `requireRole()`, closing the prior caveat. |
| 4 | USER-04/USER-05: Admin permanently deletes an account, or resets another user's password without knowing the old one | ✓ VERIFIED | Unchanged — `user_delete.php` runs `DELETE FROM admin_users WHERE id = :id`; `user_reset_password.php` never reads/verifies the old password. |
| 5 | USER-06/USER-07/SC5: System blocks deletion/deactivation of the last active admin and of the admin's own account; shows an error, performs no mutation | ✓ VERIFIED | Unchanged — guard code precedes mutation code in both `user_toggle_active.php` and `user_delete.php`; confirmed by re-reading current files. |
| 6 | [Plan 11-01] `users.php` shows a table of all `admin_users` with per-row edit/reset/toggle/delete actions all visible inline | ✓ VERIFIED | Unchanged since prior verification; file not touched by gap-closure plan. |
| 7 | [Plan 11-01] Only the admin role sees the "Käyttäjät" nav link; mod/author do not | ✓ VERIFIED (staleness caveat closed) | `admin_header.php` gating logic unchanged, but the `$role`/`currentRole()` value it reads is now kept fresh by `requireRole()` on every request, so the prior "since-demoted session" caveat no longer applies. |
| 8 | [Plan 11-01] `generate_password()` produces a CSPRNG-based password ≥8 chars | ✓ VERIFIED | Unchanged — `helpers.php:441-449`, `random_int()`-based, default length 16. |
| 9 | [Plan 11-02] D-05/USER-07: Admin cannot change their own role away from `admin` | ✓ VERIFIED | Unchanged — `user_edit.php:41` guard by ID comparison, independent of the CR-01 fix, still correct. |

**Score:** 9/9 truths verified (0 failed, 0 behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `public/src/includes/helpers.php` | `requireRole()` re-validates role/is_active per request; `generate_password()` CSPRNG helper | ✓ VERIFIED | Present, substantive, wired. `requireRole()` (lines 84-102) confirmed to contain the prepared SELECT, `session_destroy()`+redirect branch, and `$_SESSION['admin_role']` sync, read directly from the current file (not from SUMMARY). `php -l` clean. |
| `public/admin/includes/admin_header.php` | Admin-only nav link | ✓ VERIFIED | Unchanged, previously verified, not touched by 11-04. |
| `public/admin/users.php` | List view, all actions inline | ✓ VERIFIED | Unchanged, previously verified, not touched by 11-04. |
| `public/admin/user_add.php` | Creation form, one-time password display, safe on oversized username | ✓ VERIFIED (defect closed) | `validate_string($f['username'], 1, 50)` at line 18 now matches `VARCHAR(50)`; `$stmt->execute()` at line 42 wrapped in `try/catch (PDOException $e)` mapping SQLSTATE 23000 to the duplicate-username message and all other errors to `error_log()` + generic flash-err. `$success = true` only set inside the try block after a successful execute. `php -l` clean. |
| `public/admin/user_edit.php` | Username/role edit, D-05 guard, safe on oversized username | ✓ VERIFIED (defect closed) | `validate_string($f['username'], 1, 50)` at line 24; `$stmt->execute()` at line 48 wrapped in identical try/catch; success redirect to `users.php?updated=1` occurs inside the try block, only reached on successful write. `php -l` clean. |
| `public/admin/user_reset_password.php` | Password reset, no old-password check | ✓ VERIFIED | Unchanged, previously verified, not touched by 11-04. |
| `public/admin/user_toggle_active.php` | is_active toggle + guards | ✓ VERIFIED | Unchanged, previously verified, not touched by 11-04. |
| `public/admin/user_delete.php` | Permanent delete + guards | ✓ VERIFIED | Unchanged, previously verified, not touched by 11-04. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `users.php` row actions | `user_edit.php` / `user_reset_password.php` / `user_toggle_active.php` / `user_delete.php` | links + inline CSRF POST forms | ✓ WIRED | Unchanged, previously verified. |
| `user_add.php`/`user_reset_password.php` | `helpers.php::generate_password()` | direct call | ✓ WIRED | Unchanged. |
| `admin_header.php` nav | `currentRole()`/`$_SESSION['admin_role']` | `in_array($role, ['admin'], true)` | ✓ WIRED, no longer stale | The session value it reads is now refreshed by `requireRole()` on every protected page load prior to `admin_header.php` rendering (every admin page calls `requireRole()` before including `admin_header.php`). |
| `requireRole('admin'/'mod'/'author', ...)` (all protected admin pages) | `admin_users` table | prepared `SELECT role, is_active ... WHERE id = :id` inside `requireRole()` | ✓ WIRED, re-validated | Confirmed via `grep -rn "requireRole\(" public/admin` — every admin page except `logout.php` and `ei-oikeutta.php` (both non-privileged, calling only `requireLogin()`) calls `requireRole(...)`, which now performs the DB round-trip on every invocation. This closes the gap: it is no longer the case that access control is decided from a cached session value alone. |
| `user_add.php` / `user_edit.php` writes | `try/catch(PDOException)` | inline exception handling around `execute()` | ✓ WIRED | Confirmed present in both files; SQLSTATE 23000 → duplicate message, others → `error_log` + generic `.flash-err`. |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| PHP syntax valid — `helpers.php` | `php -l` (project's `simgamestable-template-php-web` Docker image) | "No syntax errors detected in public/src/includes/helpers.php" | ✓ PASS |
| PHP syntax valid — `user_add.php` | same | "No syntax errors detected in public/admin/user_add.php" | ✓ PASS |
| PHP syntax valid — `user_edit.php` | same | "No syntax errors detected in public/admin/user_edit.php" | ✓ PASS |
| Session re-validated against DB per request (the exact spot-check that FAILED in the prior verification) | `grep -rn "SELECT.*admin_users.*WHERE id" public/admin public/src` (no longer excluding login.php — matches now legitimately include the fix itself) | Match found: `public/src/includes/helpers.php:88: $stmt = $db->prepare('SELECT role, is_active FROM admin_users WHERE id = :id');` (outside `login.php`) | ✓ PASS (previously FAIL — gap closed) |
| Username validation bound matches schema | `rg "validate_string\(\$f\['username'\], 1, 50\)" public/admin/user_add.php public/admin/user_edit.php` and confirm no `1, 255` remains | Both files match `1, 50`; no `1, 255` occurrence for username in either file | ✓ PASS |
| Write paths wrapped in try/catch | Read `user_add.php` line 41-55 and `user_edit.php` line 47-61 directly | Both `execute()` calls are inside `try { ... } catch (PDOException $e) { ... }` with SQLSTATE 23000 branch | ✓ PASS |

Note: this verifier re-ran `php -l` independently via the project's own `simgamestable-template-php-web` Docker image (not trusting the SUMMARY.md claim of "automated verification passed") and re-read every changed line of `helpers.php`, `user_add.php`, and `user_edit.php` directly from the current working tree, cross-checked against `git log` (commits `7724ea1`, `6242d14`, `5c1f9eb` all present) rather than trusting the SUMMARY narrative alone.

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| USER-01 | 11-01, 11-02, 11-04 | Admin creates a user (username, password, role) | ✓ SATISFIED | `user_add.php`; edge case (oversized username crash) closed by 11-04 |
| USER-02 | 11-02, 11-04 | Admin edits role/username; change takes effect by next request | ✓ SATISFIED | `user_edit.php` DB write + `requireRole()` per-request re-validation (11-04) — the "next request" timing guarantee now holds |
| USER-03 | 11-01, 11-03 | Admin deactivates a user without losing content | ✓ SATISFIED | `user_toggle_active.php`; login-time enforcement + now also live-session enforcement via `requireRole()` |
| USER-04 | 11-01, 11-03 | Admin permanently deletes a user | ✓ SATISFIED | `user_delete.php` |
| USER-05 | 11-01, 11-03 | Admin resets another user's password without the old one | ✓ SATISFIED | `user_reset_password.php` |
| USER-06 | 11-01, 11-03 | System blocks last-admin deletion/deactivation | ✓ SATISFIED | Guards in `user_toggle_active.php`/`user_delete.php` |
| USER-07 | 11-01, 11-02, 11-03 | System blocks self deletion/deactivation/demotion | ✓ SATISFIED | Self-guards in all three action files + `user_edit.php` D-05 |

No orphaned requirements — all USER-01..07 IDs from REQUIREMENTS.md are claimed across plans 11-01/11-02/11-03's frontmatter, and 11-04 (gap closure, requirements: [USER-01, USER-02]) targets exactly the two requirements whose gaps were open. REQUIREMENTS.md marks all of USER-01..07 as `[x]` complete, consistent with this phase's status.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `public/admin/user_toggle_active.php` / `user_delete.php` | ~29-36 / ~26-33 | Last-admin `COUNT` check then `UPDATE`/`DELETE` with no transaction/row lock (WR-02) | ⚠️ Warning | TOCTOU race: two concurrent admins could each deactivate/delete a different admin account and jointly zero out active admins. Intentionally out of scope for 11-04 per its `<objective>` — not a blocker for phase 11 sign-off (low-probability, requires two admins acting on two other admin accounts within milliseconds of each other in a 2-4 user internal tool). |
| `public/admin/user_toggle_active.php` / `user_delete.php` | duplicated COUNT query | Last-admin-count query copy-pasted in two files (IN-01) | ℹ️ Info | Maintenance risk if the "last admin" rule ever changes. Not a blocker. |
| `public/admin/user_add.php` / `user_edit.php` | — | No username case-normalization (IN-02) | ℹ️ Info | Relies on `utf8mb4_unicode_ci` collation; works but undocumented. Not a blocker. |

No TBD/FIXME/XXX/TODO/HACK/PLACEHOLDER markers found in any of the 8 phase files (helpers.php, admin_header.php, users.php, user_add.php, user_edit.php, user_reset_password.php, user_toggle_active.php, user_delete.php).

WR-02, IN-01, IN-02 were explicitly scoped out of plan 11-04 (see its `<objective>` and `Next Phase Readiness` section) and do not block the phase's core success criteria (SC1-SC5) — they are residual hardening items, not regressions or unmet must-haves. They are recorded here for visibility, not as gaps.

### Human Verification Required

None required to determine phase status. Both previously-open gaps (CR-01 session staleness, CR-02 username-overflow crash) are conclusively closed by static code evidence: `requireRole()`'s DB round-trip and forced-logout/role-sync logic is directly present and correctly ordered in `helpers.php`; the `validate_string()` bound and `try/catch(PDOException)` wrapping are directly present and correctly ordered in both `user_add.php` and `user_edit.php`. `php -l` confirms no syntax regressions were introduced.

A live two-session manual UAT pass (deactivate/demote a currently-logged-in admin from a second session and confirm forced logout/permission change on next request; POST a 51-100 character username and confirm a clean flash-err) remains a good confirmatory sanity check before considering the milestone fully shipped, but is not required to reach this verdict — the code path is unambiguous and was read directly, not inferred from SUMMARY claims.

### Gaps Summary

No gaps remain. Both gaps from the initial verification are closed:

1. **Session staleness (CR-01, previously falsified ROADMAP Success Criterion #2)** — closed. `requireRole()` in `helpers.php` (lines 84-102) now performs a prepared `SELECT role, is_active FROM admin_users WHERE id = :id` on every protected request, forces logout via `session_destroy()` + redirect on a missing/deactivated row, and syncs the fresh role into `$_SESSION['admin_role']` before the allowed-roles check. This was directly re-read from the current codebase (not assumed from SUMMARY.md), confirmed present in commit `7724ea1`, and confirmed syntactically valid via `php -l`.
2. **Unhandled crash on long usernames (CR-02)** — closed. `user_add.php` and `user_edit.php` both validate usernames with `validate_string($f['username'], 1, 50)`, matching the `admin_users.username VARCHAR(50)` schema column, and both wrap their `INSERT`/`UPDATE` `execute()` calls in `try/catch (PDOException $e)`, mapping SQLSTATE 23000 to the existing duplicate-username message and all other errors to a logged, generic flash-err. Directly re-read from the current codebase, confirmed present in commit `6242d14`, confirmed syntactically valid via `php -l`.

Three residual warning/info-level findings from `11-REVIEW.md` (WR-02 last-admin TOCTOU race, IN-01 duplicated query, IN-02 no case-normalization) remain unaddressed but were explicitly scoped out of the gap-closure plan and do not block the phase goal — they concern a low-probability concurrent-admin race and code-maintainability, not the phase's core safety guarantee ("talli ei voi koskaan jäädä ilman toimivaa admin-tiliä" is still enforced by the last-admin guards that exist; the race only matters under simultaneous concurrent admin actions on two different accounts).

**Phase goal achieved:** Admin can manage all user accounts safely — creation, role/username editing (now enforced by the next request), deactivation, deletion, and password reset are all implemented, wired, and guarded against last-admin lockout and self-lockout, and the two previously-identified safety defects (stale-session privilege retention, unhandled crash on oversized input) are both closed.

---

_Verified: 2026-07-16T17:30:00Z_
_Verifier: Claude (gsd-verifier)_
