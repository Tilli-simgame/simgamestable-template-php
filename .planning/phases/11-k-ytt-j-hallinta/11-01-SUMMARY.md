---
phase: 11-k-ytt-j-hallinta
plan: 01
subsystem: auth
tags: [php, pdo, csrf, admin-panel, role-based-access]

# Dependency graph
requires:
  - phase: 10-roolit-ja-autentikaation-perusta
    provides: admin_users.role/is_active columns, currentRole()/isAdmin()/requireRole() helpers, session-based role auth
provides:
  - generate_password() CSPRNG helper for admin password generation
  - admin-only "Käyttäjät" nav link in admin_header.php
  - users.php admin-only user management list view (table, per-row actions)
affects: [11-02, 11-03]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "random_int()-based CSPRNG password generator (no rand()/mt_rand()/uniqid())"
    - "Traditional <table> list view (D-01 deviation from .compact-list grid pattern used elsewhere in admin)"
    - "Per-row inline POST forms with hidden csrf_token + id, only delete button uses confirm() (D-07)"

key-files:
  created: [public/admin/users.php]
  modified: [public/src/includes/helpers.php, public/admin/includes/admin_header.php]

key-decisions:
  - "users.php uses requireRole('admin') only (not 'admin','mod') — entire users.* file family is admin-exclusive per phase domain"
  - "Reset-password/toggle-active flashes use non-secret $_GET flags only (updated/deleted/deactivated/activated/err); plaintext generated password is never read from $_GET (T-11-06 mitigation), deferred to wave 2 action pages"

patterns-established:
  - "generate_password(int $length = 16): string in helpers.php — reusable across user_add.php and user_reset_password.php (wave 2)"

requirements-completed: [USER-01, USER-02, USER-03, USER-04, USER-05]

coverage:
  - id: D1
    description: "generate_password() helper produces an 8+ char CSPRNG password using random_int(), ready for wave 2 add/reset actions"
    requirement: "USER-01"
    verification:
      - kind: other
        ref: "rg -q 'function generate_password\\(' public/src/includes/helpers.php && rg -q 'random_(bytes|int)' public/src/includes/helpers.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "Admin-only 'Käyttäjät' nav link added to admin_header.php, wrapped in in_array($role,['admin'],true)"
    requirement: "USER-02"
    verification:
      - kind: other
        ref: "rg -q 'admin/users.php' public/admin/includes/admin_header.php"
        status: pass
    human_judgment: true
    rationale: "Automated grep confirms link markup and admin-only conditional exist, but visual confirmation that mod/author roles do not see the link requires a logged-in UAT pass with non-admin sessions."
  - id: D3
    description: "users.php renders a semantic <table> listing all admin_users with per-row muokkaa/nollaa/toggle/poista actions, all CSRF-protected, admin-only guard"
    requirement: "USER-03, USER-04, USER-05"
    verification:
      - kind: other
        ref: "rg checks: requireRole('admin'), <table, user_add.php, user_edit.php, user_reset_password.php, user_toggle_active.php, user_delete.php, generate_csrf_token count>=3, confirm( count==1"
        status: pass
    human_judgment: true
    rationale: "Structural checks pass, but rendering with real data (multiple roles/active states) and confirming the table displays correctly requires a human UAT pass against a running instance — wave 2 action endpoints (user_edit/user_reset_password/user_toggle_active/user_delete) don't exist yet so the page cannot be fully exercised end-to-end until 11-02/11-03 land."

# Metrics
duration: 12min
completed: 2026-07-16
status: complete
---

# Phase 11 Plan 01: Käyttäjähallinnan perusta ja hub Summary

**generate_password() CSPRNG-apufunktio, admin-only käyttäjähallinta-nav-linkki, ja users.php-listasivu perinteisenä table-näkymänä kaikin per-rivin toiminnoin**

## Performance

- **Duration:** 12 min
- **Started:** 2026-07-16T13:19:00Z
- **Completed:** 2026-07-16T13:31:32Z
- **Tasks:** 3
- **Files modified:** 3 (1 new, 2 modified)

## Accomplishments
- Added `generate_password(int $length = 16): string` to `helpers.php` — `random_int()`-based CSPRNG password generator, default 16 chars, satisfies `validate_string($pw, 8, 255)` and D-10's 8-char minimum
- Added admin-only "👥 Käyttäjät" navigation link to `admin_header.php`, wrapped in the same `in_array($role, ['admin'], true)` conditional used by the existing settings link — mod/author never see the route
- Built `public/admin/users.php` — admin-exclusive (`requireRole('admin')`) list view rendering all `admin_users` rows in a traditional `<table>` (D-01 deviation from the `.compact-list` grid used elsewhere), with all four per-row actions (edit link, reset-password/toggle-active/delete inline forms) visible without click-to-expand (D-06), all CSRF-protected, only delete requiring `confirm()` (D-07)

## Task Commits

Each task was committed atomically:

1. **Task 1: Lisää generate_password()-apufunktio helpers.php:hen** - `00e75c2` (feat)
2. **Task 2: Lisää admin-only "Käyttäjähallinta"-nav-linkki admin_header.php:hen** - `c6596a6` (feat)
3. **Task 3: Rakenna users.php-listasivu table-näkymänä kaikin per-rivin toiminnoin** - `25895e8` (feat)

_Note: No TDD tasks in this plan — single commit per task._

## Files Created/Modified
- `public/src/includes/helpers.php` - Added `generate_password()` CSPRNG helper
- `public/admin/includes/admin_header.php` - Added admin-only "Käyttäjät" nav link (active on users/user_add/user_edit pages)
- `public/admin/users.php` - New admin-only user list view with per-row muokkaa/nollaa salasana/deaktivoi-aktivoi/poista actions

## Decisions Made
- `users.php` uses `requireRole('admin')` only (not `'admin','mod'` like `contacts.php`) — the entire `users.*` file family is admin-exclusive per the phase's role model, since mod/author must never manage accounts.
- Error flashes (`?err=lastadmin`, `?err=self`) are pre-wired in `users.php` even though the guards that produce them live in wave 2's `user_toggle_active.php`/`user_delete.php` — this keeps the list page's flash-handling complete and ready for wave 2 without requiring a follow-up edit to this file.
- No new CSS was needed for the `<table>` — a generic `table { width: 100%; border-collapse: collapse; margin: 1rem 0; }` rule already exists in `public/assets/css/style.css`, confirming D-01's table deviation doesn't require new styling.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- `php -l` was unavailable in this environment (no local PHP CLI, no running PHP container — only `virtuaalitalli-db` container was up). Syntax for all three files was verified manually by careful review instead; no `<?php`/brace/quote mismatches found. Recommend a `php -l` pass in CI or on next Altervista deploy to confirm.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `generate_password()` is available for wave 2's `user_add.php` (11-02) and `user_reset_password.php` (11-03)
- `users.php`'s inline forms already POST to `user_edit.php`, `user_reset_password.php`, `user_toggle_active.php`, `user_delete.php` — these four files do not exist yet and must be built in wave 2 (plans 11-02/11-03) before the list page's actions are functional
- The admin-only nav link is live and correctly gated; no blockers for wave 2

---
*Phase: 11-k-ytt-j-hallinta*
*Completed: 2026-07-16*

## Self-Check: PASSED

All created/modified files verified present on disk; all four task/summary commits (`00e75c2`, `c6596a6`, `25895e8`, `df51a31`) verified present in `git log`.
