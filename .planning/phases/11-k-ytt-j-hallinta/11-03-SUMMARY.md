---
phase: 11-k-ytt-j-hallinta
plan: 03
subsystem: auth
tags: [php, pdo, csrf, admin-panel, role-based-access, bcrypt]

# Dependency graph
requires:
  - phase: 11-k-ytt-j-hallinta plan 01
    provides: generate_password() CSPRNG helper, users.php list page with wired action-form targets (reset/toggle/delete POST URLs)
  - phase: 11-k-ytt-j-hallinta plan 02
    provides: D-05 self-protection guard pattern (int-cast session admin_id comparison), established in user_edit.php, reused here for delete/deactivate
provides:
  - user_reset_password.php — admin-only password reset with inline one-time plaintext display
  - user_toggle_active.php — admin-only is_active toggle with last-admin/self guards
  - user_delete.php — admin-only permanent delete with last-admin/self guards
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Last-admin lockout guard: SELECT COUNT(*) FROM admin_users WHERE role='admin' AND is_active=1, blocks action if count <= 1 and target is that admin — reusable structural template for any future 'last row of a kind' protection"
    - "Self-protection guard reused from D-05 (plan 11-02): (int)$_SESSION['admin_id'] === $id blocks self-deactivation/self-deletion"

key-files:
  created: [public/admin/user_reset_password.php, public/admin/user_toggle_active.php, public/admin/user_delete.php]
  modified: []

key-decisions:
  - "user_reset_password.php never redirects on success — renders the generated password inline in the same request/response (mirrors user_add.php/change_password.php), since a plaintext secret must never travel via $_GET (T-11-06 mitigation)"
  - "Last-admin and self guards only run in user_toggle_active.php when $isDeactivating is true — reactivation (0->1) is always safe and skips both guards entirely"
  - "user_delete.php's last-admin guard only triggers when the target row is currently role='admin' AND is_active=1 — an already-deactivated admin row doesn't count toward the active-admin quorum, so deleting it never needs the COUNT check"

patterns-established:
  - "COUNT-then-redirect-without-mutation guard shape (from contact_delete.php's in-use check) applied to two new predicates: last-active-admin and self-target — both check-then-skip before the UPDATE/DELETE"

requirements-completed: [USER-03, USER-04, USER-05, USER-06, USER-07]

coverage:
  - id: D1
    description: "user_reset_password.php resets a target user's password without requiring the old password; generates via generate_password(), hashes with bcrypt cost 12, shows the plaintext once inline (never via $_GET)"
    requirement: "USER-05"
    verification:
      - kind: other
        ref: "rg checks: requireRole('admin'), POST-only guard, validate_csrf_token, generate_password(, UPDATE admin_users SET password, absence of current_password/password_verify, absence of users.php?...password in redirect — all pass"
        status: pass
    human_judgment: true
    rationale: "Structural/static checks pass, but confirming the displayed password actually logs the target user in (end-to-end bcrypt round-trip against a running DB) requires a live UAT pass — no PHP CLI or running app server available in this environment."
  - id: D2
    description: "user_toggle_active.php flips is_active (1<->0); deactivation is blocked with ?err=self when targeting own account and with ?err=lastadmin when targeting the last active admin; reactivation is always allowed"
    requirement: "USER-03, USER-06, USER-07"
    verification:
      - kind: other
        ref: "rg checks: requireRole('admin'), POST-only guard, validate_csrf_token, admin_id self-check, err=self, err=lastadmin, COUNT role='admin' AND is_active=1, UPDATE admin_users SET is_active — all pass"
        status: pass
    human_judgment: true
    rationale: "Structural checks pass, but confirming a deactivated account is actually rejected at login (row/content preserved) and that both guards fire correctly against live data requires a UAT pass against a running instance."
  - id: D3
    description: "user_delete.php permanently removes a user row via DELETE; blocked with ?err=self for own account and ?err=lastadmin for the last active admin"
    requirement: "USER-04, USER-06, USER-07"
    verification:
      - kind: other
        ref: "rg checks: requireRole('admin'), POST-only guard, validate_csrf_token, admin_id self-check, err=self, err=lastadmin, COUNT role='admin' AND is_active=1, DELETE FROM admin_users WHERE id, users.php?deleted=1 — all pass"
        status: pass
    human_judgment: true
    rationale: "Structural checks pass, but confirming the row is truly gone and both lockout guards correctly reject their target scenarios requires a live UAT pass against a running instance with real data (multiple admins, single admin, self-target)."

# Metrics
duration: 10min
completed: 2026-07-16
status: complete
---

# Phase 11 Plan 03: Salasanan nollaus, tila-toggle ja poisto Summary

**Kolme POST-only admin-toimintoa (reset_password, toggle_active, delete) salasanan nollaukseen kertanäytöllä sekä viimeisen adminin/oman tunnuksen lukituksenestoguardein (USER-03/04/05/06/07)**

## Performance

- **Duration:** 10 min
- **Tasks:** 3
- **Files modified:** 3 (all new)

## Accomplishments
- Built `public/admin/user_reset_password.php` — admin-only (`requireRole('admin')`), POST-only + CSRF guarded; generates a new password via `generate_password()`, hashes with bcrypt (cost 12), updates `admin_users.password`, and displays the plaintext exactly once inline (no redirect, no `$_GET` transport, no old-password check needed — D-03/D-04)
- Built `public/admin/user_toggle_active.php` — flips `is_active` between 0 and 1; when deactivating, blocks with `?err=self` if the target is the acting admin's own account (USER-07) and blocks with `?err=lastadmin` if the target is the last active admin (USER-06); reactivation always proceeds without guards; redirects to `users.php?deactivated=1` / `?activated=1`
- Built `public/admin/user_delete.php` — permanently deletes the target row via `DELETE FROM admin_users`; same self-guard (`?err=self`) and last-admin guard (`?err=lastadmin`) as the toggle action, evaluated before the delete; redirects to `users.php?deleted=1` on success

## Task Commits

Each task was committed atomically:

1. **Task 1: Luo user_reset_password.php — salasanan nollaus + inline kertanäyttö** - `ce71a49` (feat)
2. **Task 2: Luo user_toggle_active.php — deaktivointi/reaktivointi + last-admin/self-guardit** - `61832af` (feat)
3. **Task 3: Luo user_delete.php — pysyvä poisto + last-admin/self-guardit** - `8a21c77` (feat)

_Note: No TDD tasks in this plan — single commit per task._

## Files Created/Modified
- `public/admin/user_reset_password.php` - New admin-only password reset action; generates + bcrypt-hashes a new password, shows it once inline
- `public/admin/user_toggle_active.php` - New admin-only is_active toggle action with last-admin/self deactivation guards
- `public/admin/user_delete.php` - New admin-only permanent delete action with last-admin/self deletion guards

## Decisions Made
- `user_reset_password.php` intentionally never redirects on success — the generated plaintext password is rendered inline in the same HTTP response, mirroring `user_add.php`/`change_password.php`'s inline-success pattern, since passing a secret through `$_GET` would leak it via browser history/Referer/server logs (T-11-06 mitigation).
- Guards in `user_toggle_active.php` only run when `$isDeactivating` is true — reactivating an account (0→1) can never cause a lockout, so both the self-guard and last-admin-guard are skipped entirely on that path, matching the plan's explicit instruction.
- `user_delete.php`'s last-admin guard checks `role === 'admin' && is_active === 1` on the target row before running the COUNT query — an already-deactivated admin doesn't count toward "the last active admin," so deleting a deactivated admin account never needs the guard (consistent with the toggle action's semantics).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- No local PHP CLI or running PHP container available in this environment (only a DB container was up per `10-03`/`11-01`/`11-02` summaries), so `php -l` syntax checks could not be run automatically. All three files were manually reviewed line-by-line for `<?php`/brace/quote balance and PDO parameter binding correctness; no issues found. This mirrors the same limitation noted in 11-01's and 11-02's summaries — recommend a `php -l` pass in CI or on next Altervista deploy to confirm.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All four wave-2 action targets (`user_add.php`, `user_edit.php`, `user_reset_password.php`, `user_toggle_active.php`, `user_delete.php`) that `users.php` (11-01) links to now exist and are functional — Phase 11's complete user-management CRUD surface is live
- USER-06 (last-admin lockout prevention) and USER-07 (self-lockout prevention) are enforced server-side across both toggle and delete actions, satisfying the phase's critical safety guarantee ("talli ei koskaan jää ilman toimivaa admin-tiliä eikä admin lukitse itseään ulos")
- Recommend a live UAT pass (multiple admins, single-admin edge case, self-target edge case, deactivated-user login attempt) once a running PHP environment is available — no blockers, but end-to-end guard behavior has only been verified structurally via `rg`, not executed against a live DB
- Phase 11 (Käyttäjähallinta) is now feature-complete across all 3 plans (01/02/03); ready for phase-level verification/transition

---
*Phase: 11-k-ytt-j-hallinta*
*Completed: 2026-07-16*

## Self-Check: PASSED

All created files verified present on disk (`public/admin/user_reset_password.php`, `public/admin/user_toggle_active.php`, `public/admin/user_delete.php`); all three task commits (`ce71a49`, `61832af`, `8a21c77`) verified present in `git log`.
