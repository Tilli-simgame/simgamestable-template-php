---
phase: 11-k-ytt-j-hallinta
plan: 02
subsystem: auth
tags: [php, pdo, csrf, admin-panel, role-based-access, bcrypt]

# Dependency graph
requires:
  - phase: 11-k-ytt-j-hallinta plan 01
    provides: generate_password() CSPRNG helper, admin-only "Käyttäjät" nav link, users.php list page with wired action-form targets
provides:
  - user_add.php — admin-only user creation with server-generated password shown once inline
  - user_edit.php — admin-only username/role edit with D-05 self-demotion guard
affects: [11-03]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Inline one-time secret display (no redirect) for password generation — mirrors change_password.php's inline-$success pattern, avoids leaking secrets via $_GET/Referer/browser history (T-11-06)"
    - "D-05 self-protection guard: (int)$_SESSION['admin_id'] === $id && new role !== 'admin' blocks self-demotion, placed alongside other validations before the empty($errors) insert/update block"

key-files:
  created: [public/admin/user_add.php, public/admin/user_edit.php]
  modified: []

key-decisions:
  - "user_add.php never redirects on success — renders the generated password inline in the same request/response, since a plaintext password must never travel via $_GET (T-11-06 mitigation)"
  - "user_edit.php's UPDATE statement touches only username and role columns — password changes are exclusively handled by the separate reset-password action (plan 11-03), never by this edit form"

patterns-established:
  - "Self-protection guard shape (D-05): compare int-cast session admin_id to target id, combined with a field-value check, placed in the validation chain before the write — reusable template for future self-protection checks (e.g. user_toggle_active.php/user_delete.php in plan 11-03)"

requirements-completed: [USER-01, USER-02, USER-07]

coverage:
  - id: D1
    description: "user_add.php lets admin create a new account with just username+role; server generates a strong password via generate_password(), hashes it with bcrypt (cost 12), inserts with is_active=1, and shows the plaintext password once inline (never via redirect/$_GET)"
    requirement: "USER-01"
    verification:
      - kind: other
        ref: "rg checks: requireRole('admin'), generate_password(, PASSWORD_BCRYPT, INSERT INTO admin_users, SELECT COUNT(*) FROM admin_users WHERE username, absence of type=\"password\" field — all pass"
        status: pass
    human_judgment: true
    rationale: "Structural/static checks pass, but confirming the new account can actually log in immediately with the displayed password requires a live UAT pass against a running instance (no PHP CLI or running app server available in this environment to execute end-to-end)."
  - id: D2
    description: "user_edit.php lets admin update an existing user's username and role; change takes effect on next request since role is read from session/DB per-request via requireRole()"
    requirement: "USER-02"
    verification:
      - kind: other
        ref: "rg checks: requireRole('admin'), UPDATE admin_users SET username, users.php?updated=1, absence of UPDATE admin_users SET password — all pass"
        status: pass
    human_judgment: true
    rationale: "Structural checks pass, but confirming the role change actually affects permissions on the very next request requires a live UAT pass with a logged-in session (no running app server available in this environment)."
  - id: D3
    description: "D-05/USER-07: admin cannot change their own role away from 'admin' — self-demotion guard checks (int)$_SESSION['admin_id'] === $id && new role !== 'admin', adds error, skips the UPDATE"
    requirement: "USER-07"
    verification:
      - kind: other
        ref: "rg -q admin_id public/admin/user_edit.php confirms guard code present; guard placed before if (empty($errors)) block"
        status: pass
    human_judgment: true
    rationale: "Guard logic is structurally present and correctly positioned, but confirming it actually blocks the write path end-to-end (attempting to self-demote and observing the error + unchanged role) requires a live UAT pass with an authenticated admin session."

# Metrics
duration: 12min
completed: 2026-07-16
status: complete
---

# Phase 11 Plan 02: Käyttäjän luonti ja muokkaus Summary

**user_add.php generates a bcrypt-hashed password server-side and displays it once inline; user_edit.php updates username/role with a self-demotion guard blocking admins from removing their own admin role**

## Performance

- **Duration:** 12 min
- **Started:** 2026-07-16T13:25:00Z
- **Completed:** 2026-07-16T13:37:00Z
- **Tasks:** 2
- **Files modified:** 2 (both new)

## Accomplishments
- Built `public/admin/user_add.php` — admin-only (`requireRole('admin')`) creation form taking only username + role; server generates an 8+ char CSPRNG password via `generate_password()`, hashes it with `password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])`, inserts with `is_active = 1` (new account can log in immediately), and displays the plaintext password exactly once inline on success — no redirect, no `$_GET` transport, no logging (D-02, T-11-06)
- Built `public/admin/user_edit.php` — admin-only edit form for username + role only; UPDATE statement never touches the `password` column; includes a username-uniqueness check that excludes the row being edited (`AND id != :id`)
- Implemented D-05/USER-07 self-demotion guard in `user_edit.php`: `(int)$_SESSION['admin_id'] === $id && $f['role'] !== 'admin'` adds a validation error and blocks the UPDATE, preventing an admin from locking themselves out of admin-only pages

## Task Commits

Each task was committed atomically:

1. **Task 1: Luo user_add.php — käyttäjän luonti + generoidun salasanan kertanäyttö** - `f7d052f` (feat)
2. **Task 2: Luo user_edit.php — käyttäjänimen + roolin muokkaus ja oman roolin suoja (D-05)** - `2a7e59a` (feat)

_Note: No TDD tasks in this plan — single commit per task._

## Files Created/Modified
- `public/admin/user_add.php` - New admin-only user creation form; server-generated bcrypt password shown once inline
- `public/admin/user_edit.php` - New admin-only username/role edit form with D-05 self-demotion guard

## Decisions Made
- `user_add.php` intentionally never uses `redirect()` on success — the generated plaintext password is rendered inline in the same HTTP response, mirroring `change_password.php`'s inline-`$success` pattern, since passing a secret through `$_GET` would leak it via browser history/Referer/server logs (T-11-06 mitigation)
- `user_edit.php`'s UPDATE statement is scoped to `username` and `role` only — password changes belong exclusively to the separate reset-password action planned for 11-03, keeping this form's write surface minimal
- The D-05 self-demotion check uses `(int)$_SESSION['admin_id'] === $id` (integer-cast comparison against the URL/route id, not the submitted form data) so the guard can't be bypassed by manipulating POST fields

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- No local PHP CLI or running PHP container available in this environment (only a DB container was up), so `php -l` syntax checks could not be run automatically. Both files were manually reviewed line-by-line for `<?php`/brace/quote balance and PDO parameter binding correctness; no issues found. This mirrors the same limitation noted in 11-01's summary — recommend a `php -l` pass in CI or on next Altervista deploy to confirm.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `user_add.php` and `user_edit.php` are both live and wired to the exact URLs `users.php` (built in 11-01) already links to (`user_add.php`, `user_edit.php?id=`), so the "+ Lisää käyttäjä" button and per-row "✏️ Muokkaa" links in the list view are now functional
- Plan 11-03 still needs to build `user_reset_password.php`, `user_toggle_active.php`, and `user_delete.php` — the three remaining action targets `users.php`'s per-row forms POST to, including the USER-06 last-admin protection and USER-07 self-protection guards for delete/deactivate
- The D-05 self-demotion guard pattern established here (int-cast session id comparison + field-value check, placed before the write) is directly reusable for 11-03's self-protection checks in `user_toggle_active.php`/`user_delete.php`

---
*Phase: 11-k-ytt-j-hallinta*
*Completed: 2026-07-16*

## Self-Check: PASSED

All created files verified present on disk (`public/admin/user_add.php`, `public/admin/user_edit.php`); both task commits (`f7d052f`, `2a7e59a`) verified present in `git log`.
