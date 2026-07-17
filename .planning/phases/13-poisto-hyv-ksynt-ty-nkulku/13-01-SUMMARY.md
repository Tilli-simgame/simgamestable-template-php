---
phase: 13-poisto-hyv-ksynt-ty-nkulku
plan: 01
subsystem: database
tags: [mysql, pdo, soft-delete, migration, helpers]

# Dependency graph
requires:
  - phase: 12-sisaltotyyppien-roolirajaus
    provides: posts.author_id ownership + requireOwnResourceOrAdmin()/requireRole() role-gating conventions
provides:
  - pending_deletions polymorphic queue table (entity_type/entity_id/status/requested_by/reviewed_by)
  - is_deleted/deleted_at soft-delete columns on foals/competitions/showrecords/posts
  - insertPendingDeletion() duplicate-safe queue insert helper
  - entityTypeToTable() whitelist entity_type-to-table-name mapper
affects: [13-02, deletion approval views, delete-branching logic in horse_delete.php/post_delete.php/foals.php/competitions.php/showrecords.php]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Polymorphic queue table with ENUM entity_type + whitelist match() lookup instead of dynamic SQL table names"
    - "PHP-level check-then-insert duplicate guard for a queue table (accepted low-severity race condition at single-tenant volume)"

key-files:
  created:
    - database/migrate_delete_approval.sql
  modified:
    - database/schema.sql
    - public/src/includes/helpers.php

key-decisions:
  - "Migration mirrors horses table's existing is_deleted/deleted_at/idx_<table>_deleted pattern verbatim across foals/competitions/showrecords/posts"
  - "pending_deletions placed in schema.sql directly after admin_users so its two admin_users FKs resolve at CREATE-time"
  - "entityTypeToTable() is the only sanctioned path from entity_type to a table name — match() whitelist, default throws InvalidArgumentException, no string interpolation from user input"

patterns-established:
  - "insertPendingDeletion(string,int,int): void — check-then-insert against (entity_type, entity_id, status='pending') before INSERT, called with requestedBy always sourced from $_SESSION['admin_id']"

requirements-completed: [DEL-05]

coverage:
  - id: D1
    description: "pending_deletions table + is_deleted/deleted_at columns on foals/competitions/showrecords/posts exist in dev DB and schema.sql"
    requirement: "DEL-05"
    verification:
      - kind: integration
        ref: "docker exec virtuaalitalli-db mysql ... SELECT COUNT(*) FROM information_schema.columns ... = 4; SHOW TABLES LIKE 'pending_deletions'"
        status: pass
      - kind: integration
        ref: "docker exec virtuaalitalli-db mysql ... SHOW COLUMNS FROM pending_deletions LIKE 'status' -> enum('pending','approved','rejected')"
        status: pass
    human_judgment: false
  - id: D2
    description: "insertPendingDeletion() prevents duplicate pending rows for the same entity_type+entity_id (DEL-05)"
    requirement: "DEL-05"
    verification:
      - kind: integration
        ref: "docker exec virtuaalitalli-web php -r 'insertPendingDeletion(...) twice; SELECT COUNT(*) ... = 1'"
        status: pass
    human_judgment: false
  - id: D3
    description: "entityTypeToTable() whitelist match() maps valid entity types and throws InvalidArgumentException on unknown input (SQL-injection defense)"
    verification:
      - kind: integration
        ref: "docker exec virtuaalitalli-web php -r \"entityTypeToTable('showrecord')==='showrecords'; entityTypeToTable('post')==='posts'; entityTypeToTable('../horses; DROP') throws InvalidArgumentException\""
        status: pass
    human_judgment: false

duration: 12min
completed: 2026-07-17
status: complete
---

# Phase 13 Plan 01: Poisto-hyväksyntätyönkulku database foundation Summary

**New `pending_deletions` polymorphic queue table plus soft-delete columns on foals/competitions/showrecords/posts, backed by a duplicate-safe `insertPendingDeletion()` and a whitelist-only `entityTypeToTable()` helper**

## Performance

- **Duration:** 12 min
- **Started:** 2026-07-17T11:00:08+03:00
- **Completed:** 2026-07-17T11:11:27+03:00
- **Tasks:** 2 completed
- **Files modified:** 3 (1 created: `migrate_delete_approval.sql`; 2 modified: `schema.sql`, `helpers.php`)

## Accomplishments
- New additive migration `database/migrate_delete_approval.sql` adds `is_deleted`/`deleted_at`/`idx_<table>_deleted` to `foals`, `competitions`, `showrecords`, `posts` (mirroring the existing `horses` table pattern) and creates `pending_deletions` with FKs to `admin_users`
- Migration run against dev DB (`virtuaalitalli-db`) and verified via `information_schema` + `SHOW COLUMNS`
- `database/schema.sql` mirrors the same structures so a fresh dev DB bootstrap matches the migrated state
- `insertPendingDeletion(string $entityType, int $entityId, int $requestedBy): void` added to `helpers.php` with a check-then-insert duplicate guard (DEL-05)
- `entityTypeToTable(string $entityType): string` added to `helpers.php` as the sole whitelist-based entity_type→table mapper, throwing `InvalidArgumentException` on any non-whitelisted input

## Task Commits

Each task was committed atomically:

1. **Task 1: migrate_delete_approval.sql + schema.sql-peilaus + ajo dev-DB:hen** - `ef6b929` (feat)
2. **Task 2: insertPendingDeletion() + entityTypeToTable() helpers.php:hen** - `0220a4c` (feat)

**Plan metadata:** pending (this commit)

## Files Created/Modified
- `database/migrate_delete_approval.sql` - New additive migration: soft-delete columns for 4 tables + `pending_deletions` table
- `database/schema.sql` - Mirrors the migration's column/table additions for fresh dev DB bootstraps
- `public/src/includes/helpers.php` - Adds `insertPendingDeletion()` and `entityTypeToTable()` alongside existing role/ownership helpers

## Decisions Made
- Migration column definitions copied verbatim from the `horses` table's existing soft-delete precedent (`is_deleted TINYINT(1) NOT NULL DEFAULT 0`, `deleted_at TIMESTAMP NULL DEFAULT NULL`, `KEY idx_<table>_deleted`) for consistency across all 5 soft-deletable tables
- `pending_deletions` placed in `schema.sql` immediately after `admin_users` so its two FK references resolve without forward-declaration issues
- No PHP-level rate limiting or locking added around the check-then-insert race window — plan's own threat model (T-13-04) explicitly accepts this as low-severity at single-tenant volume

## Deviations from Plan

None - plan executed exactly as written. Both tasks matched the PATTERNS.md reference SQL/PHP bodies closely; only the per-table ALTER statements needed to be written out individually (competitions/showrecords/posts) since the plan's pattern excerpt showed foals as the template with "repeat for..." shorthand.

## Issues Encountered
- `docker exec` calls with absolute container paths (e.g. `/var/www/html/...`) were being mangled by Git Bash's path translation on Windows (`Could not open input file: C:/Program Files/Git/var/www/html/...`). Resolved by prefixing those specific commands with `MSYS_NO_PATHCONV=1`. Not a plan deviation — purely a local shell-environment workaround, no files affected.

## User Setup Required

None - no external service configuration required. Migration was run automatically against the existing dev DB container.

## Next Phase Readiness
- `pending_deletions` table, soft-delete columns, `insertPendingDeletion()`, and `entityTypeToTable()` are all in place and verified in the dev DB
- Wave 2 plans (delete-branching in `horse_delete.php`/`post_delete.php`/inline `foals.php`/`competitions.php`/`showrecords.php` deletes, `deletions.php` admin approval view, `deletion_approve.php`/`deletion_reject.php`, `is_deleted = 0` audit pass across list/detail queries) can now build directly on this schema and these helpers
- No blockers identified

---
*Phase: 13-poisto-hyv-ksynt-ty-nkulku*
*Completed: 2026-07-17*

## Self-Check: PASSED

- FOUND: database/migrate_delete_approval.sql
- FOUND: .planning/phases/13-poisto-hyv-ksynt-ty-nkulku/13-01-SUMMARY.md
- FOUND: ef6b929 (Task 1 commit)
- FOUND: 0220a4c (Task 2 commit)
