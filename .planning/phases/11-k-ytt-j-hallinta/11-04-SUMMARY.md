---
phase: 11-kayttajahallinta
plan: 04
subsystem: auth
tags: [php, pdo, session, security, gap-closure]

# Dependency graph
requires:
  - phase: 11-kayttajahallinta (plans 01-03)
    provides: admin_users table, requireRole()/requireLogin() gate, user_add/user_edit/user_toggle_active/user_delete pages
provides:
  - requireRole() re-validates role/is_active from admin_users on every protected request (no longer trusts a stale session)
  - user_add.php / user_edit.php username validation aligned to VARCHAR(50); INSERT/UPDATE wrapped in try/catch(PDOException) with clean flash-err instead of a crash
affects: [phase-12-sisaltotyyppien-roolirajaus, phase-13-poisto-hyvaksyntatyonkulku]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Per-request DB re-validation of session-derived authorization state (requireRole()) instead of trust-on-login-only caching"
    - "try/catch(PDOException) around admin write paths, mapping SQLSTATE 23000 to the existing duplicate-username flash message and all other DB errors to a generic flash-err + error_log (matches db.php's existing convention)"

key-files:
  created: []
  modified:
    - public/src/includes/helpers.php
    - public/admin/user_add.php
    - public/admin/user_edit.php

key-decisions:
  - "requireRole() runs one PK-indexed SELECT per protected page load (accepted cost per 11-REVIEW.md CR-01) rather than caching a TTL in session, guaranteeing the ROADMAP SC2 'takes effect by the next request' contract exactly"
  - "requireLogin(), currentRole(), isAdmin() left untouched — only requireRole()'s internals changed; its signature requireRole(string ...$allowedRoles): void is unchanged"
  - "Username upper bound lowered from 255 to 50 in both user_add.php and user_edit.php to match admin_users.username VARCHAR(50); existing COUNT(*) uniqueness pre-check kept as first line of defense, try/catch as second line for the TOCTOU race (WR-01)"

patterns-established:
  - "Gap-closure plans (gap_closure: true) target VERIFICATION.md/REVIEW.md findings directly, citing CR-/WR- IDs in commit messages and this summary for traceability"

requirements-completed: [USER-01, USER-02]

coverage:
  - id: D1
    description: "requireRole() re-validates role and is_active from admin_users on every protected request; a role demotion, deactivation, or deletion of another admin takes effect on that admin's next protected request (session_destroy + redirect to login.php if deactivated/deleted, fresh role synced to $_SESSION['admin_role'] otherwise)"
    requirement: "USER-02"
    verification:
      - kind: manual_procedural
        ref: "Manual DB-flip test: deactivate/delete a logged-in admin's admin_users row in another session, then load any admin page as that admin — expect forced logout to login.php. Demote role instead — expect immediate nav/permission change on next request."
        status: unknown
    human_judgment: true
    rationale: "Requires a live two-session browser test (one admin acting on another's account while that account has an open session) — no automated test harness exists in this PHP-only codebase to simulate concurrent sessions."
  - id: D2
    description: "user_add.php and user_edit.php: username validation upper bound lowered to 50 (matching VARCHAR(50)); INSERT/UPDATE wrapped in try/catch(PDOException) so a too-long username or a race-condition duplicate produces a clean .flash-err instead of an unhandled PDOException"
    requirement: "USER-01"
    verification:
      - kind: manual_procedural
        ref: "POST a 51-255 char username to user_add.php/user_edit.php — expect .flash-err 'Teksti on liian pitka...' not a crash. Simulate a duplicate-username race (bypass the COUNT pre-check) — expect .flash-err 'Kayttajanimi on jo kaytossa.'"
        status: unknown
    human_judgment: true
    rationale: "Exercising the try/catch's SQLSTATE 23000 branch requires forcing a genuine race past the existing COUNT(*) pre-check (e.g. two concurrent requests), which needs manual/concurrent HTTP testing rather than a unit test in this codebase."

duration: 10min
completed: 2026-07-16
status: complete
---

# Phase 11 Plan 04: Session Re-validation & Username Overflow Guard Summary

**requireRole() re-validates role/is_active from admin_users per-request (closing session-staleness gap), and user_add/user_edit wrap their writes in try/catch(PDOException) with a VARCHAR(50)-aligned validation bound (closing the long-username crash gap).**

## Performance

- **Duration:** 10 min
- **Started:** 2026-07-16T14:22:00Z
- **Completed:** 2026-07-16T14:32:30Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- `requireRole()` now performs a prepared `SELECT role, is_active FROM admin_users WHERE id = :id` on every call, force-logging-out (session_destroy + redirect to login.php) any session whose backing account was deactivated or deleted, and syncing the fresh role into `$_SESSION['admin_role']` so demotions apply immediately (closes VERIFICATION.md gap 1 / CR-01, and the derivative WR-03 nav-gating staleness)
- `user_add.php` and `user_edit.php` username validation upper bound lowered from 255 to 50 (matching `admin_users.username VARCHAR(50)`), and their INSERT/UPDATE writes wrapped in `try/catch(PDOException)`: SQLSTATE 23000 maps to the existing "Käyttäjänimi on jo käytössä." message (closes WR-01 TOCTOU), all other DB errors are logged and shown as a generic flash-err (closes VERIFICATION.md gap 2 / CR-02)

## Task Commits

Each task was committed atomically:

1. **Task 1: CR-01 — re-validate session against admin_users in requireRole()** - `7724ea1` (fix)
2. **Task 2: CR-02 — align username validation to VARCHAR(50) + try/catch around writes** - `6242d14` (fix)

_Note: No TDD tasks in this plan; both tasks are direct code fixes with automated `rg`/`php -l` verification per the plan's `<verify>` blocks._

## Files Created/Modified
- `public/src/includes/helpers.php` - `requireRole()` re-validates role/is_active from `admin_users` per request; forces logout on deactivated/deleted accounts; syncs fresh role to session
- `public/admin/user_add.php` - username validation bound lowered to 50; INSERT wrapped in try/catch(PDOException)
- `public/admin/user_edit.php` - username validation bound lowered to 50; UPDATE wrapped in try/catch(PDOException)

## Decisions Made
- Accepted one extra PK-indexed SELECT per protected page load in `requireRole()` as the cost of guaranteeing "takes effect by next request" (per 11-REVIEW.md §CR-01) rather than any session-TTL caching scheme, which would reintroduce staleness windows
- Kept `requireLogin()`, `currentRole()`, `isAdmin()` unchanged — scope-limited the fix to `requireRole()`'s internals only, per plan's explicit instruction (only `ei-oikeutta.php`/`logout.php` use bare `requireLogin()` and perform no privileged actions)
- Kept the existing `COUNT(*)` uniqueness pre-check in both user_add.php/user_edit.php as the fast first-line validation, adding try/catch as a second defense layer for the concurrent-write race rather than replacing the pre-check

## Deviations from Plan

None - plan executed exactly as written. Both tasks matched their `<action>` specifications precisely; all automated `<verify>` regex checks and `php -l` syntax checks passed on first attempt.

## Issues Encountered

The `Edit` tool's exact-string match failed on the first attempt against `helpers.php`'s docblock/function block (likely a Unicode normalization mismatch on the Finnish `ä` characters in the original text). Worked around by re-scoping the edit to the ASCII-only function body first (succeeded), then re-reading the file to confirm state before further changes — no functional impact, no extra fix-attempt cycles beyond this tooling retry.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Both VERIFICATION.md gaps (CR-01 session staleness, CR-02 username-overflow crash) are closed, along with their derivative findings (WR-03 nav-gating staleness, WR-01 duplicate-username TOCTOU crash)
- WR-02 (transactional locking), IN-01, IN-02 remain intentionally out of scope per this plan's `<objective>` — not blockers for Phase 11 sign-off, but worth revisiting if concurrency load increases
- Manual two-session verification (D1) and race-condition verification (D2) are flagged `human_judgment: true` in this summary's coverage block — recommend a quick manual pass (deactivate a live-session admin; submit a 60-char username) before closing out Phase 11 entirely
- Ready for Phase 11 verification re-run / sign-off

---
*Phase: 11-kayttajahallinta*
*Completed: 2026-07-16*

## Self-Check: PASSED

- FOUND: public/src/includes/helpers.php
- FOUND: public/admin/user_add.php
- FOUND: public/admin/user_edit.php
- FOUND: 7724ea1 (fix(11-04): re-validate session role/is_active on every protected request)
- FOUND: 6242d14 (fix(11-04): align username validation to VARCHAR(50) and catch write errors)
