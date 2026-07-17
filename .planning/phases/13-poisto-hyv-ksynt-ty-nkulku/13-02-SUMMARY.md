---
phase: 13-poisto-hyv-ksynt-ty-nkulku
plan: 02
subsystem: admin-delete-handlers
tags: [php, pdo, soft-delete, role-gating, idor-defense]

# Dependency graph
requires:
  - phase: 13-poisto-hyv-ksynt-ty-nkulku (plan 01)
    provides: pending_deletions table, is_deleted/deleted_at columns, insertPendingDeletion(), entityTypeToTable()
provides:
  - Role-branched soft-delete for horse/foal/competition/showrecord/post
  - Mod-poisto -> soft-delete + pending_deletions-rivi (MOD-06)
  - Author-oman-postauksen valitön poisto ilman pending-riviä (AUTHOR-03)
  - is_deleted = 0 -suodatus foals/competitions/showrecords/posts-listakyselyihin
affects: [13-03 (admin-hyväksyntänäkymä deletions.php käyttää pending_deletions-rivejä joita nämä käsittelijät nyt luovat)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "3-way role branch (admin/mod/author) with ownership check via requireOwnResourceOrAdmin() before soft-delete"
    - "mod-branch calls insertPendingDeletion() after successful soft-delete UPDATE; admin/author-own-post skip it"
    - "WHERE id = :id AND is_deleted = 0 double-update guard applied uniformly across all six delete paths"

key-files:
  modified:
    - public/admin/horse_delete.php
    - public/admin/post_delete.php
    - public/admin/foals.php
    - public/admin/competitions.php
    - public/admin/showrecords.php
    - public/admin/kasvatus_all.php

key-decisions:
  - "post_delete.php performs its own independent author_id fetch + requireOwnResourceOrAdmin() call before the UPDATE, mirroring posts.php's IDOR defense-in-depth pattern rather than trusting the caller (GET view) to have already checked ownership"
  - "competitions.php's edit-mode fetch query (editStmt) also received the is_deleted = 0 filter alongside the list query, since PATTERNS.md flagged both line 86 and 93 as targets — a soft-deleted competition should not be loadable into the edit modal even via direct edit=<id> URL"
  - "kasvatus_all.php's foal list query had no prior WHERE clause at all (unlike foals.php's per-horse view) — added a new WHERE f.is_deleted = 0 clause rather than appending AND to an existing filter"

requirements-completed: [MOD-06, AUTHOR-03]

coverage:
  - id: D1
    description: "horse_delete.php widened to admin+mod; mod branch creates a pending_deletions row, admin does not"
    requirement: "MOD-06"
    verification:
      - kind: static
        ref: "php -l public/admin/horse_delete.php; grep requireRole('admin', 'mod'); grep insertPendingDeletion('horse', ...)"
        status: pass
    human_judgment: false
  - id: D2
    description: "post_delete.php converted from hard-delete admin-only to soft-delete 3-way role branch (admin/mod/author) with ownership-checked author path"
    requirement: "AUTHOR-03"
    verification:
      - kind: static
        ref: "php -l public/admin/post_delete.php; grep -c 'DELETE FROM posts' = 0; grep requireOwnResourceOrAdmin; grep insertPendingDeletion('post', ...)"
        status: pass
    human_judgment: false
  - id: D3
    description: "foals.php/competitions.php/showrecords.php/kasvatus_all.php inline delete branches converted to soft-delete with admin/mod role widening and mod pending-row creation"
    requirement: "MOD-06"
    verification:
      - kind: static
        ref: "php -l all four files; grep -RcE 'DELETE FROM (foals|competitions|showrecords)' = 0; grep insertPendingDeletion present in each"
        status: pass
    human_judgment: false
  - id: D4
    description: "Soft-deleted rows filtered out of each page's own content list query (is_deleted = 0)"
    verification:
      - kind: static
        ref: "grep 'is_deleted = 0' present in foals.php (list), competitions.php (list + edit), showrecords.php (list), kasvatus_all.php (list)"
        status: pass
    human_judgment: false

duration: 10min
completed: 2026-07-17
status: complete
---

# Phase 13 Plan 02: Poisto-hyväksyntätyönkulku delete-handler role branching Summary

**Six content-delete handlers converted from hard-delete/admin-only to role-branched soft-delete: mod requests are queued via `insertPendingDeletion()`, admin deletes directly, and author can only delete their own posts immediately**

## Performance

- **Duration:** ~10 min
- **Tasks:** 2 completed
- **Files modified:** 6 (`horse_delete.php`, `post_delete.php`, `foals.php`, `competitions.php`, `showrecords.php`, `kasvatus_all.php`)

## Accomplishments
- `horse_delete.php`: gate widened `requireRole('admin')` → `requireRole('admin', 'mod')`; mod branch calls `insertPendingDeletion('horse', $id, ...)` after the existing soft-delete `UPDATE`; admin path unchanged (no pending row)
- `post_delete.php`: converted from hard `DELETE FROM posts` to `UPDATE posts SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0`; gate widened to `requireRole('admin', 'mod', 'author')`; added an independent `SELECT author_id FROM posts WHERE id = :id` + `requireOwnResourceOrAdmin()` ownership check before the soft-delete (IDOR defense-in-depth, mirroring `posts.php`'s existing pattern); mod branch creates a `pending_deletions` row, admin and author-own-post do not
- `foals.php`, `competitions.php`, `showrecords.php`, `kasvatus_all.php` (its own separate inline foal-delete branch): all four inline `action=delete` branches widened to `requireRole('admin', 'mod')`, converted hard `DELETE` to soft-delete `UPDATE ... SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0` (preserving each file's existing ownership/belonging pre-check), with mod branch calling `insertPendingDeletion()` using the correct `entity_type` (`foal`, `competition`, `showrecord`)
- Added `is_deleted = 0` filtering to each file's own content list query so soft-deleted rows disappear immediately from admin lists: `foals.php` (per-horse list), `competitions.php` (list query AND edit-mode fetch query), `showrecords.php` (per-horse list), `kasvatus_all.php` (global foal list, which previously had no `WHERE` clause at all)

## Task Commits

Each task was committed atomically:

1. **Task 1: Standalone-käsittelijät horse_delete.php + post_delete.php roolihaaroihin** - `e4eb7ed` (feat)
2. **Task 2: Inline delete-haarat foals/competitions/showrecords/kasvatus_all soft-deleteen** - `5c27443` (feat)

**Plan metadata:** pending (this commit)

## Files Created/Modified
- `public/admin/horse_delete.php` - Added mod role + pending-row branch to existing soft-delete
- `public/admin/post_delete.php` - Hard-delete → soft-delete, 3-way role branch, IDOR ownership check
- `public/admin/foals.php` - Inline delete branch soft-delete + role branch + list filter
- `public/admin/competitions.php` - Inline delete branch soft-delete + role branch + list/edit filter
- `public/admin/showrecords.php` - Inline delete branch soft-delete + role branch + list filter
- `public/admin/kasvatus_all.php` - Second foal-delete branch soft-delete + role branch + list filter (new WHERE clause)

## Decisions Made
- `post_delete.php` performs its own independent author_id fetch + `requireOwnResourceOrAdmin()` call before the `UPDATE`, matching `posts.php`'s IDOR defense-in-depth convention (Phase 12) rather than assuming the GET view already validated ownership
- `competitions.php`'s edit-mode fetch query (`editStmt`) also received the `is_deleted = 0` filter, not just the list query — a soft-deleted competition should not be loadable via a direct `edit=<id>` URL
- `kasvatus_all.php`'s global foal list query had no prior `WHERE` clause; added a fresh `WHERE f.is_deleted = 0` rather than appending to an existing condition

## Deviations from Plan

None - plan executed exactly as written. All six handlers matched the `13-PATTERNS.md` target code blocks; the `competitions.php` edit-query filter (line 93) and `kasvatus_all.php`'s missing prior `WHERE` clause were both anticipated by the plan's `<action>` text and PATTERNS.md references.

## Issues Encountered
- Local `php` binary not available on PATH in the executing shell; PHP lint (`php -l`) was run inside the `virtuaalitalli-web` Docker container instead, using `MSYS_NO_PATHCONV=1` to prevent Git Bash from mangling the absolute container path (`/var/www/html/admin/...`) — same workaround documented in 13-01-SUMMARY.md. Not a plan deviation, purely a local shell-environment detail.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All five content types (horse/foal/competition/showrecord/post) now have consistent role-branched soft-delete: mod → soft-delete + `pending_deletions` row; admin → soft-delete only; author → soft-delete own post only (no pending row)
- `post_delete.php`'s IDOR defense is verified structurally (ownership check present before soft-delete); behavioral verification (crafted POST with another author's post id) should be exercised as part of Plan 03's or the phase-level UAT pass
- Plan 03 (admin approval view `deletions.php` + `deletion_approve.php`/`deletion_reject.php`) can now build directly on the `pending_deletions` rows these six handlers create
- No blockers identified

---
*Phase: 13-poisto-hyv-ksynt-ty-nkulku*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: public/admin/horse_delete.php (requireRole('admin', 'mod'), insertPendingDeletion('horse', ...))
- FOUND: public/admin/post_delete.php (requireRole('admin', 'mod', 'author'), requireOwnResourceOrAdmin, soft-delete)
- FOUND: public/admin/foals.php (soft-delete + is_deleted = 0 filter)
- FOUND: public/admin/competitions.php (soft-delete + is_deleted = 0 filter x2)
- FOUND: public/admin/showrecords.php (soft-delete + is_deleted = 0 filter)
- FOUND: public/admin/kasvatus_all.php (soft-delete + is_deleted = 0 filter)
- FOUND: e4eb7ed (Task 1 commit)
- FOUND: 5c27443 (Task 2 commit)
