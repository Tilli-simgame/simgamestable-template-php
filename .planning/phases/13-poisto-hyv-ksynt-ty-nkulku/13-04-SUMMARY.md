---
phase: 13-poisto-hyv-ksynt-ty-nkulku
plan: 04
subsystem: query-audit
tags: [mysql, pdo, soft-delete, audit-pass, public-site, themes]

# Dependency graph
requires:
  - phase: 13-01
    provides: is_deleted/deleted_at soft-delete columns on foals/competitions/showrecords/posts
  - phase: 13-02
    provides: is_deleted = 0 filtering already applied to delete handlers' own list/edit queries
provides:
  - Full is_deleted = 0 audit-pass coverage across all remaining admin list/edit queries and every public-facing query (both themes) touching foals/competitions/showrecords/posts
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "AND is_deleted = 0 appended to existing WHERE clauses (or new WHERE added when none existed) with correct table alias per query"
    - "OR-conditions wrapped in parentheses before appending AND is_deleted = 0 to preserve correct operator precedence (pages/hevonen.php and themes/oma-talli/hevonen.php foals query)"

key-files:
  created: []
  modified:
    - public/admin/foal_edit.php
    - public/admin/kilpailut_all.php
    - public/admin/showrecords_all.php
    - public/admin/posts.php
    - public/pages/kasvatus.php
    - public/pages/hevonen.php
    - public/pages/index.php
    - public/pages/postaus.php
    - public/pages/ajankohtaista.php
    - public/themes/oma-talli/hevonen.php

key-decisions:
  - "Wrapped the OR-condition foals query (sire_id/dam_id lookup) in parentheses before appending AND is_deleted = 0, in both pages/hevonen.php and themes/oma-talli/hevonen.php, since appending AND without parens would have changed operator precedence and let soft-deleted foals leak in via the dam_id branch"
  - "Extended the audit beyond the plan's line-numbered inventory to also cover postaus.php's and ajankohtaista.php's archive-sidebar COUNT queries, since those are posts-table queries too and an uncounted archive entry would still leak the existence/count of a soft-deleted post"

requirements-completed: [MOD-06]

coverage:
  - id: D1
    description: "All four remaining admin queries (foal_edit, kilpailut_all, showrecords_all, posts) filter is_deleted = 0"
    requirement: "MOD-06"
    verification:
      - kind: static
        ref: "php -l on all 4 files (docker exec virtuaalitalli-web) + grep -c 'is_deleted = 0' counts: foal_edit=5, kilpailut_all=2, showrecords_all=2, posts.php=4"
        status: pass
    human_judgment: false
  - id: D2
    description: "All public-facing queries in both themes (default pages/* + oma-talli) filter is_deleted = 0"
    requirement: "MOD-06"
    verification:
      - kind: static
        ref: "php -l on all 6 files + grep -c 'is_deleted = 0' counts: hevonen.php=9, oma-talli/hevonen.php=9, postaus.php=5, ajankohtaista.php=4, index.php=3, kasvatus.php=3"
        status: pass
    human_judgment: false
---

# Phase 13 Plan 04: Poisto-hyväksyntätyönkulku query audit pass Summary

**Audit-passi joka lisää `is_deleted = 0` -suodatuksen kaikkiin jäljellä oleviin foals/competitions/showrecords/posts-kyselyihin — neljä admin-tiedostoa ja kuusi julkisen sivuston tiedostoa (molemmat teemat)**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-07-17T08:34:03Z (STATE.md session start)
- **Completed:** 2026-07-17T08:41:48Z
- **Tasks:** 2 completed
- **Files modified:** 10 (0 created, 10 modified)

## Accomplishments
- `public/admin/foal_edit.php`: edit-load query now excludes soft-deleted foals (`f.is_deleted = 0`), preventing a soft-deleted foal from being opened for editing via direct `id=` URL
- `public/admin/kilpailut_all.php` / `public/admin/showrecords_all.php`: full admin lists now exclude soft-deleted competitions/showrecords
- `public/admin/posts.php`: author-branch list, admin/mod-branch list, and edit-load query all exclude soft-deleted posts (slug-uniqueness check and ownership check deliberately left untouched — they operate on id/slug lookups, not visibility lists)
- `public/pages/kasvatus.php`: public breeding-page foal listing excludes soft-deleted foals
- `public/pages/hevonen.php` and `public/themes/oma-talli/hevonen.php` (both themes): horse-profile competitions, showrecords, foals (sire/dam pedigree lookup), and posts queries all exclude soft-deleted rows
- `public/pages/index.php`: yearly foal counter and latest-post card both exclude soft-deleted rows
- `public/pages/postaus.php`: single-post slug/id lookup, prev/next navigation, and archive-sidebar count all exclude soft-deleted posts — closes the direct-URL leak (T-13-15)
- `public/pages/ajankohtaista.php`: all three listing branches (year+month, year-only, unfiltered) plus the archive-sidebar count exclude soft-deleted posts

## Task Commits

Each task was committed atomically:

1. **Task 1: Admin-puolen jäljellä olevat kyselyt (foal_edit, kilpailut_all, showrecords_all, posts)** - `3c2e4f9` (feat)
2. **Task 2: Julkisen sivuston kyselyt molemmissa teemoissa (pages/* + oma-talli)** - `a5966de` (feat)

**Plan metadata:** pending (this commit)

## Files Created/Modified
- `public/admin/foal_edit.php` - added `AND f.is_deleted = 0` to the foal edit-load `SELECT`
- `public/admin/kilpailut_all.php` - added `WHERE c.is_deleted = 0` to the full competitions list
- `public/admin/showrecords_all.php` - added `WHERE s.is_deleted = 0` to the full showrecords list
- `public/admin/posts.php` - added `AND is_deleted = 0` to edit-load query and both list branches (author-own, admin/mod-all)
- `public/pages/kasvatus.php` - added `WHERE f.is_deleted = 0` to the public foal listing
- `public/pages/hevonen.php` - added `is_deleted = 0` to competitions, showrecords, foals, and posts queries
- `public/themes/oma-talli/hevonen.php` - identical four additions mirrored for the `oma-talli` theme
- `public/pages/index.php` - added `is_deleted = 0` to yearly foal count and latest-post card query
- `public/pages/postaus.php` - added `is_deleted = 0` to slug/id lookup, prev/next navigation, and archive count
- `public/pages/ajankohtaista.php` - added `is_deleted = 0` to all three listing branches and the archive count

## Decisions Made
- Wrapped the `f.sire_id = :id1 OR f.dam_id = :id2` foals query in parentheses before appending `AND f.is_deleted = 0` (both `pages/hevonen.php` and `themes/oma-talli/hevonen.php`) — without the parens, operator precedence would have made the filter apply only to the `dam_id` branch, letting a soft-deleted foal linked via `sire_id` still appear on a horse's profile page
- Extended coverage to the archive-sidebar `COUNT(*)` queries in `postaus.php` and `ajankohtaista.php`, beyond the plan's line-numbered inventory — these are posts-table aggregate queries and, left unfiltered, would still surface the existence/count of soft-deleted posts in the sidebar even though the posts themselves were hidden from the main list

## Deviations from Plan

None - plan executed exactly as written. The two archive-count query additions above are within the plan's stated intent ("jokaiseen posts-kyselyyn") even though not individually line-numbered in the read_first inventory — filed under Rule 2 (missing critical functionality: an unfiltered aggregate query on the same table is a correctness gap for the stated objective) rather than a deviation from scope.

## Issues Encountered
- Local `php` binary not available in the execution shell; verification was run via `docker exec virtuaalitalli-web php -l ...` per the project's existing dev-container setup. Container mounts `public/` directly to `/var/www/html/`, and `docker exec` calls needed `MSYS_NO_PATHCONV=1` prefix to avoid Git Bash mangling the absolute container path (`/var/www/html/...` → `C:/Program Files/Git/var/www/html/...`) — same workaround already documented in 13-01-SUMMARY.md. Not a plan deviation, purely a local shell-environment note.

## User Setup Required

None - no external service configuration required. All changes are query-level PHP edits verified via `php -l` inside the existing dev container.

## Next Phase Readiness
- SC1 / MOD-06 is now fully covered: soft-deleted foals/competitions/showrecords/posts are invisible everywhere — admin lists, admin edit-load queries, and every public-facing query across both themes (default `pages/*` and `oma-talli`)
- Combined with 13-02's delete-handler-owned queries, this closes the full audit-pass scope flagged in research/SUMMARY.md ("Gaps to Address" row 119)
- Phase 13 plans (01-04) are all complete; no blockers identified for phase closeout

---
*Phase: 13-poisto-hyv-ksynt-ty-nkulku*
*Completed: 2026-07-17*
