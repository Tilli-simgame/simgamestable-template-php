---
phase: 13-poisto-hyv-ksynt-ty-nkulku
plan: 03
subsystem: admin-ui
tags: [php, pdo, admin-panel, approval-workflow, soft-delete]

# Dependency graph
requires:
  - phase: 13-poisto-hyv-ksynt-ty-nkulku (plan 01)
    provides: pending_deletions table, is_deleted/deleted_at columns, insertPendingDeletion()/entityTypeToTable() helpers
affects: [deletion approval UX, admin dashboard, admin nav]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Unified single-table approval list view (mirrors Phase 11 D-01 users.php pattern) instead of per-entity-type sections"
    - "Per-row inline POST forms (approve/reject) with CSRF token + hidden id, no confirm() since both actions are reversible"

key-files:
  created:
    - public/admin/deletions.php
    - public/admin/deletion_approve.php
    - public/admin/deletion_reject.php
  modified:
    - public/admin/index.php
    - public/admin/includes/admin_header.php

key-decisions:
  - "entity_label built via COALESCE across horses.name/foals.foal_name/CONCAT(discipline,date) for competitions+showrecords/posts.title, with a CONCAT(entity_type,'#',id) fallback for any unmatched row"
  - "deletion_approve.php only transitions pending_deletions.status — never touches the content table, since mod's request already soft-deleted it (D-05)"
  - "deletion_reject.php wraps the content-restore UPDATE and the pending_deletions status UPDATE in a single PDO transaction, with entityTypeToTable() as the only sanctioned entity_type-to-table-name path"
  - "index.php's competition/showrecord COUNT queries gained WHERE is_deleted = 0 alongside the new pending-deletion counter, since Plan 01 added soft-delete columns to those tables but the dashboard counts were never updated to filter them"

patterns-established:
  - "Admin-only nav link + stat card pair for a new cross-cutting feature: same in_array($role, ['admin'], true) gate as existing settings.php link, same .admin-stat-card markup as existing dashboard cards"

requirements-completed: [DEL-01, DEL-02, DEL-03, DEL-04]

coverage:
  - id: D1
    description: "deletions.php lists all pending requests from all five content types in one unified table, admin-only"
    requirement: "DEL-01"
    verification:
      - kind: other
        ref: "php -l public/admin/deletions.php; grep requireRole('admin') exactly once; grep confirms LEFT JOIN to horses/foals/competitions/showrecords/posts and WHERE pd.status = 'pending'"
        status: pass
    human_judgment: true
    rationale: "Requires a live DB with pending_deletions rows across multiple entity_types to visually confirm the JOIN renders correctly with no NULL-related errors — static grep/php -l cannot prove runtime rendering behavior."
  - id: D2
    description: "Admin can approve a request -> pending_deletions.status='approved', content stays hidden (is_deleted stays 1)"
    requirement: "DEL-02"
    verification:
      - kind: other
        ref: "php -l public/admin/deletion_approve.php; grep confirms requireRole('admin'), validate_csrf_token, UPDATE ... SET status = 'approved' ... WHERE status = 'pending', and no UPDATE against any content table"
        status: pass
    human_judgment: true
    rationale: "Correct end-to-end status transition and DB side effects require exercising the handler against a live pending_deletions row — not provable from static analysis alone."
  - id: D3
    description: "Admin can reject a request -> content restored (is_deleted=0), pending_deletions.status='rejected', row retained for audit"
    requirement: "DEL-03"
    verification:
      - kind: other
        ref: "php -l public/admin/deletion_reject.php; grep confirms entityTypeToTable() whitelist usage, beginTransaction/commit wrapping both UPDATEs, and no DELETE against pending_deletions"
        status: pass
    human_judgment: true
    rationale: "Atomicity and correct restoration of content visibility require a live transaction run against the dev DB, not just static grep verification."
  - id: D4
    description: "Admin dashboard shows a counter of pending deletion requests as a new stat card"
    requirement: "DEL-04"
    verification:
      - kind: other
        ref: "php -l public/admin/index.php; grep confirms pendingDeletionCount query (WHERE status = 'pending') and new stat-card markup with label 'Odottavaa poistopyyntöä'; grep confirms is_deleted = 0 filter added to compCount/showCount queries"
        status: pass
    human_judgment: false
  - id: D5
    description: "deletions.php/approve/reject restricted to admin only; admin_header.php gets an admin-only 'Poistopyynnöt' nav link"
    verification:
      - kind: other
        ref: "grep confirms requireRole('admin') (not 'admin','mod') on all three new files; grep confirms admin_header.php's new nav link uses in_array($role, ['admin'], true)"
        status: pass
    human_judgment: false

duration: 12min
completed: 2026-07-17
status: complete
---

# Phase 13 Plan 03: Poisto-hyväksyntätyönkulku admin approval UI Summary

**Unified `deletions.php` approval list joining `pending_deletions` to all five content tables, dedicated `deletion_approve.php`/`deletion_reject.php` handlers (approve = status-only transition, reject = atomic content-restore + status transition via `entityTypeToTable()`), plus a DEL-04 dashboard counter and admin-only nav link**

## Performance

- **Duration:** 12 min
- **Started:** 2026-07-17T08:18:00Z
- **Completed:** 2026-07-17T08:30:30Z
- **Tasks:** 3 completed
- **Files modified:** 5 (3 created: `deletions.php`, `deletion_approve.php`, `deletion_reject.php`; 2 modified: `index.php`, `admin_header.php`)

## Accomplishments
- New `public/admin/deletions.php` — single `<table>` view (mirrors Phase 11 D-01 pattern) of all `status='pending'` rows across `horse`/`foal`/`competition`/`showrecord`/`post`, with Finnish type labels, a COALESCE-derived readable `entity_label`, and per-row Hyväksy/Hylkää inline POST forms with CSRF tokens
- New `public/admin/deletion_approve.php` — admin-only, POST+CSRF-gated, updates only `pending_deletions.status='approved'` (content already soft-deleted by the mod's original request, D-05 — no content-table write)
- New `public/admin/deletion_reject.php` — admin-only, POST+CSRF-gated, looks up the pending row, resolves the content table via the whitelist `entityTypeToTable()` helper, then restores `is_deleted=0, deleted_at=NULL` on the content row and sets `pending_deletions.status='rejected'` inside one PDO transaction (`beginTransaction()`/`commit()`) — the pending row is never deleted, preserving audit history
- `public/admin/index.php` gained a `$pendingDeletionCount` query and a new `.admin-stat-card` labeled "Odottavaa poistopyyntöä" (DEL-04); the pre-existing `$compCount`/`$showCount` queries were also fixed to filter `WHERE is_deleted = 0` so Plan 01's new soft-delete columns don't inflate those counters
- `public/admin/includes/admin_header.php` gained a new admin-only "🗑️ Poistopyynnöt" nav link in the "Sivusto" section, next to the existing `settings.php` link, using the identical `in_array($role, ['admin'], true)` gate and active-state pattern

## Task Commits

Each task was committed atomically:

1. **Task 1: deletions.php — yhtenäinen odottavien poistopyyntöjen lista (DEL-01)** - `9f4420f` (feat)
2. **Task 2: deletion_approve.php + deletion_reject.php käsittelijät (DEL-02/DEL-03)** - `99a62a7` (feat)
3. **Task 3: DEL-04-laskuri admin-etusivulle + Poistopyynnöt-nav-linkki** - `a62459d` (feat)

**Plan metadata:** pending (this commit)

## Files Created/Modified
- `public/admin/deletions.php` - Unified pending-deletions list view, admin-only
- `public/admin/deletion_approve.php` - Status-only transition to `approved`
- `public/admin/deletion_reject.php` - Content restore + status transition to `rejected`, atomic via PDO transaction
- `public/admin/index.php` - New `pendingDeletionCount` stat card; `is_deleted = 0` filter added to competition/showrecord counts
- `public/admin/includes/admin_header.php` - New admin-only "Poistopyynnöt" nav link

## Decisions Made
- `entity_label` uses `COALESCE(h.name, f.foal_name, CONCAT(c.discipline,' ',c.competition_date), CONCAT(s.discipline,' ',s.show_date), p.title, CONCAT(entity_type,' #',entity_id))` since competitions/showrecords have no single title-like column — `discipline` + date is the most readable identifying combination, with a final fallback for defensive completeness
- `index.php`'s `$compCount`/`$showCount` queries were widened with `WHERE is_deleted = 0` per the plan's explicit instruction — these queries pre-date Plan 01's soft-delete columns and were never updated, so without this fix the dashboard would still count soft-deleted competitions/showrecords
- No `confirm()` JS added to approve/reject buttons — both actions are reversible (approve/reject can be reversed by the opposite action... actually reject can be re-requested by mod, approve is the terminal state but content stays soft-deleted either way), matching CONTEXT.md's explicit guidance that confirm() is only needed for destructive/irreversible actions

## Deviations from Plan

None - plan executed exactly as written. All three tasks matched the PATTERNS.md reference SQL/PHP bodies closely.

## Issues Encountered
- Local environment has no `php` CLI on PATH; used `MSYS_NO_PATHCONV=1 docker exec virtuaalitalli-web php -l ...` (same workaround documented in 13-01-SUMMARY.md) to run all `php -l` syntax checks against the running dev container. Not a plan deviation — purely a local shell-environment workaround, no files affected.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- All four DEL-01..04 admin-approval-workflow requirements are implemented and statically verified (php -l + grep-based acceptance criteria all pass)
- Runtime/UAT verification (live approve/reject cycle against the dev DB, visual confirmation of the unified table with mixed entity_type rows, mod/author nav-link exclusion) is deferred to the phase's verification step — flagged via `human_judgment: true` on D1-D3 in this summary's coverage block
- No blockers identified; this completes wave 2 of Phase 13 alongside sibling plans 13-02 and 13-04 (no file overlap)

---
*Phase: 13-poisto-hyv-ksynt-ty-nkulku*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: public/admin/deletions.php
- FOUND: public/admin/deletion_approve.php
- FOUND: public/admin/deletion_reject.php
- FOUND: 9f4420f (Task 1 commit)
- FOUND: 99a62a7 (Task 2 commit)
- FOUND: a62459d (Task 3 commit)
