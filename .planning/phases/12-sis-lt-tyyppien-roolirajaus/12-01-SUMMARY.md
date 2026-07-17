---
phase: 12-sis-lt-tyyppien-roolirajaus
plan: 1
subsystem: database
tags: [mysql, migration, posts, ownership, foreign-key]

# Dependency graph
requires:
  - phase: 11-kayttajahallinta
    provides: admin_users.role/is_active columns and role-based session validation
provides:
  - posts.author_id column (INT UNSIGNED, nullable, FK to admin_users.id, ON DELETE SET NULL)
  - Backfilled author_id for all existing posts (D-01, admin username)
  - schema.sql posts block mirrors author_id for fresh-install path
affects: [12-02-posts-ownership-logic]

# Tech tracking
tech-stack:
  added: []
  patterns: [migrate_*.sql mirrored into schema.sql (same convention as migrate_roles.sql -> admin_users block)]

key-files:
  created: [database/migrate_posts_author.sql]
  modified: [database/schema.sql]

key-decisions:
  - "posts.author_id is nullable with FK ON DELETE SET NULL so permanent user deletion (USER-04) does not delete their posts, only clears ownership"
  - "Backfill targets the existing 'admin' username per D-01, matching migrate_roles.sql's admin-elevation convention"

patterns-established:
  - "Additive-only migrations (ADD COLUMN + backfill UPDATE, no DROP/DELETE) mirrored into schema.sql for fresh-install parity"

requirements-completed: [AUTHOR-02, AUTHOR-04]

coverage:
  - id: D1
    description: "database/migrate_posts_author.sql created with ADD COLUMN author_id, fk_posts_author FK to admin_users.id, and backfill UPDATE to admin"
    requirement: "AUTHOR-02"
    verification:
      - kind: other
        ref: "grep -q 'ADD COLUMN' database/migrate_posts_author.sql && grep -q 'fk_posts_author' database/migrate_posts_author.sql"
        status: pass
    human_judgment: false
  - id: D2
    description: "database/schema.sql posts block mirrors author_id column and fk_posts_author FK for fresh-install path"
    requirement: "AUTHOR-02"
    verification:
      - kind: other
        ref: "grep -n author_id database/schema.sql"
        status: pass
    human_judgment: false
  - id: D3
    description: "Migration executed against dev DB (virtuaalitalli-db): posts.author_id column exists, 0 NULL author_id rows among existing posts, fk_posts_author FK constraint active"
    requirement: "AUTHOR-04"
    verification:
      - kind: other
        ref: "docker exec virtuaalitalli-db mysql ... SELECT COUNT(*) FROM posts WHERE author_id IS NULL -> 0"
        status: pass
    human_judgment: false

# Metrics
duration: 2min
completed: 2026-07-17
status: complete
---

# Phase 12 Plan 1: posts.author_id Foundation Summary

**Added posts.author_id column with FK to admin_users.id (ON DELETE SET NULL), backfilled existing posts to admin, and mirrored the change in schema.sql for fresh installs**

## Performance

- **Duration:** 2 min
- **Started:** 2026-07-17T06:36:39Z
- **Completed:** 2026-07-17T06:38:17Z
- **Tasks:** 2 completed
- **Files modified:** 2 (1 created, 1 modified)

## Accomplishments
- Created `database/migrate_posts_author.sql` following the `migrate_roles.sql` convention (header block, phpMyAdmin import instructions, explicit ALTER + FK + backfill UPDATE)
- Mirrored `author_id` column and `fk_posts_author` FK into `database/schema.sql`'s posts block for the fresh-install path
- Ran the migration against the dev database (`virtuaalitalli-db`) via `docker exec` — verified column exists, FK is active, and 0 posts have a NULL `author_id` (1 existing post backfilled to `admin`)

## Task Commits

Each task was committed atomically:

1. **Task 1: Luo migraatiotiedosto ja peilaa muutos schema.sql:ään** - `2c8fbcb` (feat)
2. **Task 2: Aja migraatio dev-tietokantaan ja verifioi sarake + backfill** - no commit (DB-only operation, no file changes — verified via docker exec queries below)

**Plan metadata:** (pending — final docs commit)

## Files Created/Modified
- `database/migrate_posts_author.sql` - New migration: ADD COLUMN author_id + FK fk_posts_author (ON DELETE SET NULL) + backfill UPDATE to admin
- `database/schema.sql` - posts block now includes author_id column and fk_posts_author FK constraint, mirroring the migration for fresh installs

## Decisions Made
- `posts.author_id` is nullable with `ON DELETE SET NULL` FK behavior so that permanently deleting a user (USER-04, Phase 11) clears ownership on their posts instead of cascading a delete — posts are never destroyed by a user removal.
- Backfill targets the `admin` username per D-01 (resolved during phase-12 planning), consistent with `migrate_roles.sql`'s existing admin-elevation pattern.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. Docker dev environment (`virtuaalitalli-db`) was already running and healthy, so the migration ran directly via `docker exec -i virtuaalitalli-db mysql -uvt_user -pvt_password virtuaalitalli < database/migrate_posts_author.sql` — no manual/phpMyAdmin fallback was needed.

**Verification results (Task 2):**
- `SHOW COLUMNS FROM posts LIKE 'author_id'` → `author_id  int unsigned  YES  MUL  NULL` (column exists)
- `SELECT COUNT(*) FROM posts WHERE author_id IS NULL` → `0` (backfill complete; dev DB had 1 existing post, now owned by admin)
- `SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME='posts' AND CONSTRAINT_NAME='fk_posts_author'` → `1` (FK active)

## User Setup Required

None - no external service configuration required. Migration was applied directly to the running dev container; no manual phpMyAdmin step needed.

## Next Phase Readiness

`posts.author_id` is live and backfilled in the dev database, and mirrored in `schema.sql` for fresh installs. Plan 12-02 (posts.php ownership logic, `requireOwnResourceOrAdmin()`) can now proceed — the enabling column and FK it depends on are verified present and populated.

---
*Phase: 12-sis-lt-tyyppien-roolirajaus*
*Completed: 2026-07-17*
