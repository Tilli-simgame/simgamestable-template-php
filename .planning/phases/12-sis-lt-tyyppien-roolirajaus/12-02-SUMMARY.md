---
phase: 12-sis-lt-tyyppien-roolirajaus
plan: 2
subsystem: auth
tags: [php, pdo, ownership, idor, role-based-access, posts]

# Dependency graph
requires:
  - phase: 12-01-posts-author-foundation
    provides: posts.author_id column (nullable, FK to admin_users.id, ON DELETE SET NULL, backfilled)
provides:
  - requireOwnResourceOrAdmin() ownership guard helper in helpers.php
  - posts.php ownership logic (INSERT author_id, list filter, GET/POST IDOR guards)
affects: [13-poisto-hyvaksyntatyonkulku]

# Tech tracking
tech-stack:
  added: []
  patterns: [ownership-guard helper mirroring requireRole()'s redirect-on-fail convention, defense-in-depth ownership check on both GET (direct-URL) and POST (crafted-request) paths]

key-files:
  created: []
  modified:
    - public/src/includes/helpers.php
    - public/admin/posts.php

key-decisions:
  - "requireOwnResourceOrAdmin(int $resourceAuthorId): void added to helpers.php, mirroring requireRole()'s no-return-value/redirect-to-ei-oikeutta.php convention; admin/mod always pass (not ownership-restricted), only author role is compared against $_SESSION['admin_id']"
  - "Ownership identity is always derived from $_SESSION['admin_id'], never from POST-supplied IDs (Spoofing defense, T-12-02-04)"
  - "POST UPDATE branch fetches the target post's author_id and calls the guard BEFORE the UPDATE runs (separate check from the GET-side one) to close the crafted-POST IDOR gap (Pitfall 2) — GET-side verification does not protect the POST handler since a crafted request never traverses the GET view"

patterns-established:
  - "Ownership-guard placement: call requireOwnResourceOrAdmin() immediately after fetching the resource row and before any further processing (both in the GET action=edit branch and the POST UPDATE branch), always after CSRF validation on the POST side"

requirements-completed: [MOD-01, MOD-02, MOD-03, MOD-04, MOD-05, MOD-07, AUTHOR-01, AUTHOR-02, AUTHOR-04, AUTHOR-05]

coverage:
  - id: D1
    description: "requireOwnResourceOrAdmin() added to helpers.php: redirects author role to ei-oikeutta.php when resourceAuthorId does not match $_SESSION['admin_id']; admin/mod always pass"
    requirement: "AUTHOR-02"
    verification:
      - kind: other
        ref: "grep -q 'function requireOwnResourceOrAdmin' public/src/includes/helpers.php && php -l public/src/includes/helpers.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "posts.php INSERT sets author_id = $_SESSION['admin_id'] for every new post regardless of role (D-02)"
    requirement: "AUTHOR-02"
    verification:
      - kind: other
        ref: "grep -n 'author_id' public/admin/posts.php — INSERT statement includes :aid bound to $_SESSION['admin_id']"
        status: pass
    human_judgment: false
  - id: D3
    description: "posts.php list query filters WHERE author_id = :aid when currentRole()==='author'; admin/mod see the unfiltered list (D-03)"
    requirement: "AUTHOR-02"
    verification:
      - kind: other
        ref: "grep -n \"currentRole() === 'author'\" and 'WHERE author_id' in public/admin/posts.php"
        status: pass
    human_judgment: false
  - id: D4
    description: "posts.php GET action=edit calls requireOwnResourceOrAdmin($editPost['author_id']) after fetching the post, before form pre-fill — direct-URL access to another author's post redirects to ei-oikeutta.php (SC3)"
    requirement: "AUTHOR-02"
    verification:
      - kind: manual_procedural
        ref: "UAT (b): log in as author, open posts.php?action=edit&id=X for another author's post, confirm redirect to ei-oikeutta.php"
        status: unknown
    human_judgment: true
    rationale: "Session-based cross-role behavior (logging in as different roles and observing redirects) cannot be reliably automated from this executor context — requires human-driven UAT per plan's own verification section"
  - id: D5
    description: "posts.php POST UPDATE branch fetches the target post's author_id and calls requireOwnResourceOrAdmin() before running UPDATE — crafted POST with another author's edit_id is blocked (IDOR defense-in-depth, Pitfall 2)"
    requirement: "AUTHOR-02"
    verification:
      - kind: manual_procedural
        ref: "UAT (c): log in as author, send crafted POST to posts.php with another author's edit_id, confirm no UPDATE occurs and redirect to ei-oikeutta.php"
        status: unknown
    human_judgment: true
    rationale: "Requires a crafted authenticated POST request under a specific session role — not something this executor can drive from a shell without a live authenticated session; deferred to human UAT per plan's verification section"
  - id: D6
    description: "D-04 regression grep pass: Phase 10 role gating on content pages (mod-allowed), posts.php (all three roles), and users.php/settings.php/user_* family (admin-only) is unchanged"
    requirement: "MOD-01"
    verification:
      - kind: other
        ref: "grep -rn \"requireRole(\" public/admin/*.php — see grep evidence below"
        status: pass
    human_judgment: false

# Metrics
duration: 8min
completed: 2026-07-17
status: complete
---

# Phase 12 Plan 2: Posts Ownership Logic Summary

**Author-role ownership enforcement on posts.php via a new `requireOwnResourceOrAdmin()` helper — list filtering, INSERT author_id assignment, and defense-in-depth IDOR guards on both the GET edit view and the POST update handler**

## Performance

- **Duration:** 8 min
- **Started:** 2026-07-17T06:36:00Z
- **Completed:** 2026-07-17T06:44:53Z
- **Tasks:** 3 completed (Task 3 is verification-only, no code changes)
- **Files modified:** 2

## Accomplishments
- Added `requireOwnResourceOrAdmin(int $resourceAuthorId): void` to `helpers.php`, following `requireRole()`'s exact convention (no return value, redirects to `ei-oikeutta.php` on failure); only the `author` role is ownership-restricted, admin/mod always pass
- `posts.php` INSERT now sets `author_id = $_SESSION['admin_id']` for every new post regardless of role (D-02)
- `posts.php` list query filters `WHERE author_id = :aid` when `currentRole() === 'author'`; admin/mod continue to see the unfiltered list (D-03)
- `posts.php` GET `action=edit` branch calls the ownership guard right after fetching `$editPost`, before form pre-fill — blocks direct-URL access to another author's post (SC3)
- `posts.php` POST UPDATE branch fetches the target post's `author_id` and calls the ownership guard **before** running the UPDATE — a separate check from the GET-side one, closing the crafted-POST IDOR gap since a crafted request never traverses the GET view (Pitfall 2, IDOR defense-in-depth)
- Verified via grep that Phase 10's page-level role gating (mod-allowed content pages, posts.php all-three-roles, admin-only users/settings/user_* family) is unchanged (D-04) — no regression

## Task Commits

Each task was committed atomically:

1. **Task 1: Lisää requireOwnResourceOrAdmin()-apufunktio helpers.php:hen** - `4a001fb` (feat)
2. **Task 2: Omistajuuslogiikka posts.php:hen** - `3784903` (feat)
3. **Task 3: Verifioi mod/author-sivutason roolirajaus ennallaan + UAT-tarkistuslista** - no commit (verification-only, no files changed — grep evidence below)

**Plan metadata:** (pending — final docs commit)

## Files Created/Modified
- `public/src/includes/helpers.php` - Added `requireOwnResourceOrAdmin(int $resourceAuthorId): void`
- `public/admin/posts.php` - INSERT author_id, list-query author filter, GET/POST ownership guards

## Decisions Made
- `requireOwnResourceOrAdmin()` placed alongside `isAdmin()`/`requireRole()` in `helpers.php`, matching the existing helper style exactly (small `currentRole()`-based composable check, `redirect(SITE_URL . '/admin/ei-oikeutta.php')` on failure, no return value).
- The POST UPDATE branch performs its own `SELECT author_id FROM posts WHERE id = :id` fetch and ownership check, independent of the GET-side check — this is deliberate defense-in-depth per the plan's Pitfall 2 note, since a crafted POST request bypasses the GET view entirely.
- Ownership comparisons and the `author_id` written on INSERT are always sourced from `$_SESSION['admin_id']`, never from POST-supplied values (T-12-02-04, Spoofing mitigation).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. `docker exec virtuaalitalli-web php -l ...` required `MSYS_NO_PATHCONV=1` on this Windows/Git-Bash environment to prevent the `/var/www/html/...` path from being rewritten to a Windows path before reaching the container — this is a local shell quirk, not a code issue, and both `php -l` runs reported "No syntax errors detected" (with a pre-existing, unrelated `mbstring.internal_encoding` deprecation notice from the container's PHP 8.2 config).

**D-04 grep evidence (Task 3):**

```
=== Content pages (mod-allowed) ===
public/admin/horses.php:3:requireRole('admin', 'mod');
public/admin/horse_add.php:3:requireRole('admin', 'mod');
public/admin/horse_edit.php:3:requireRole('admin', 'mod');
public/admin/foals.php:3:requireRole('admin', 'mod');
public/admin/foal_add.php:3:requireRole('admin', 'mod');
public/admin/foal_edit.php:3:requireRole('admin', 'mod');
public/admin/competitions.php:3:requireRole('admin', 'mod');
public/admin/showrecords.php:3:requireRole('admin', 'mod');
public/admin/photos.php:3:requireRole('admin', 'mod');
public/admin/photo_update.php:3:requireRole('admin', 'mod');
public/admin/horse_import_vrl.php:3:requireRole('admin', 'mod');

=== posts.php (all three roles) ===
public/admin/posts.php:3:requireRole('admin', 'mod', 'author');

=== admin-only (users/settings + user_* family) ===
public/admin/users.php:3:requireRole('admin');
public/admin/settings.php:3:requireRole('admin');
public/admin/user_add.php:3:requireRole('admin');
public/admin/user_edit.php:3:requireRole('admin');
public/admin/user_delete.php:3:requireRole('admin');
public/admin/user_reset_password.php:3:requireRole('admin');
public/admin/user_toggle_active.php:3:requireRole('admin');
```

`GATES_OK` from the plan's automated verify command confirms all three gate categories intact. No regression found — Phase 10's role gating is unchanged.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Phase 12's core deliverable (posts ownership logic) is code-complete and grep/php-lint-verified. The following manual UAT checklist (session-dependent, cannot be automated from this executor) must be run in `gsd-verify-work` before Phase 12 is considered fully verified:

**Manual UAT checklist:**
- **(a)** Log in as an `author` user → `posts.php` list view shows only that author's own posts.
- **(b)** As that author, open another author's post via `posts.php?action=edit&id=X` (direct URL with a known/guessed foreign post ID) → redirected to `ei-oikeutta.php`.
- **(c)** As that author, send a crafted POST to `posts.php` with `edit_id` set to another author's post ID → the post is NOT updated, redirected to `ei-oikeutta.php` before the UPDATE statement runs.
- **(d)** Log in as `admin` and separately as `mod` → both see and can edit ALL posts (no ownership restriction).
- **(e)** As `mod` and as `author`, attempt to open `users.php` and `settings.php` via direct URL → both redirect to `ei-oikeutta.php` (Phase 10 gate, reconfirmed unchanged by D-04 grep pass above).

Phase 13 (Poisto-hyväksyntätyönkulku) can build on the now-complete `posts.author_id` ownership foundation — `AUTHOR-03` (author's immediate own-post delete) will need the same `requireOwnResourceOrAdmin()` helper.

---
*Phase: 12-sis-lt-tyyppien-roolirajaus*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: public/src/includes/helpers.php
- FOUND: public/admin/posts.php
- FOUND: 4a001fb (Task 1 commit)
- FOUND: 3784903 (Task 2 commit)
