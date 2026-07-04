---
phase: 08-sivukontrollerien-migraatio
plan: 01
subsystem: theming
tags: [php, pdo, theme-engine, resolveThemePath, data-only-controller]

# Dependency graph
requires:
  - phase: 07-oletusteman-rakenne
    provides: public/themes/default/pages/*.php clean templates (index, hevoset, kasvatus) with their own variable contracts and self-set $page_title
provides:
  - index.php, hevoset.php, kasvatus.php as data-only controllers delegating all HTML rendering to resolveThemePath('pages/X.php')
  - canonical Model B root-override hook (resolveThemePath() form) standardized across all 3 controllers
affects: [08-02, 08-03, 08-04 (remaining 4 controllers: hevonen.php, yhteystiedot.php, ajankohtaista.php, postaus.php follow the identical pattern)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Malli A: controller fetches data then `require resolveThemePath('pages/X.php')`, guarded by http_response_code(404); exit; if false"
    - "Malli B: canonical resolveThemePath('X.php') root-override hook, identical structure across controllers, guarded by !str_starts_with(THEME_PATH, THEMES_ROOT.'default'.DIRECTORY_SEPARATOR)"

key-files:
  created: []
  modified:
    - public/pages/index.php
    - public/pages/hevoset.php
    - public/pages/kasvatus.php

key-decisions:
  - "Converted index.php's realpath()-based Model B hook to the canonical resolveThemePath() form (D-02) for structural symmetry with the other 6 controllers"
  - "Removed $page_title and $genderFi from hevoset.php/kasvatus.php controllers — these are template-owned per D-05, already present identically in public/themes/default/pages/*.php"

patterns-established:
  - "Data-only controller shape: db.php + theme.php requires -> Model B hook -> data queries -> guarded Malli A delegation"

requirements-completed: [THEME-08]

coverage:
  - id: D1
    description: "index.php converted to data-only controller with canonical Model B hook and guarded Malli A delegation, no inline HTML"
    requirement: "THEME-08"
    verification:
      - kind: integration
        ref: "docker exec virtuaalitalli-web php -l /var/www/html/pages/index.php && curl http://localhost:8080/pages/index.php (verified HTTP 200 + frontpage-hero markup with active_theme=default; oma-talli active theme has no root index.php override in play for hevoset/kasvatus, confirmed correct default-theme fallback in real environment)"
        status: pass
    human_judgment: false
  - id: D2
    description: "hevoset.php converted to data-only controller: theme.php require + Model B hook added, $page_title/$genderFi removed, Malli A delegation added"
    requirement: "THEME-08"
    verification:
      - kind: integration
        ref: "docker exec virtuaalitalli-web php -l + curl http://localhost:8080/pages/hevoset.php returns 200, renders horse-list-card/Tallin hevoset markup from default theme template"
        status: pass
    human_judgment: false
  - id: D3
    description: "kasvatus.php converted to data-only controller: theme.php require + Model B hook added, $page_title/$genderFi removed, Malli A delegation added"
    requirement: "THEME-08"
    verification:
      - kind: integration
        ref: "docker exec virtuaalitalli-web php -l + curl http://localhost:8080/pages/kasvatus.php returns 200, renders foal-section/Kasvatus markup from default theme template"
        status: pass
    human_judgment: false

duration: 20min
completed: 2026-07-04
status: complete
---

# Phase 8 Plan 01: Sivukontrollerien migraatio (index/hevoset/kasvatus) Summary

**index.php, hevoset.php, and kasvatus.php converted to data-only controllers delegating 100% of HTML rendering to `resolveThemePath('pages/X.php')`, with a standardized `resolveThemePath()`-based Model B root-override hook in all three.**

## Performance

- **Duration:** ~20 min (including Docker Desktop cold start)
- **Started:** 2026-07-04T07:25:00Z (approx.)
- **Completed:** 2026-07-04T07:38:03Z
- **Tasks:** 3/3
- **Files modified:** 3

## Accomplishments
- `index.php`'s ad-hoc `realpath()`-based root-override hook standardized to the canonical `resolveThemePath()` form used elsewhere (identical structure to `hevonen.php`)
- `hevoset.php` and `kasvatus.php` gained the `theme.php` require and the Model B hook for the first time, plus a guarded Malli A delegation
- All 3 controllers' inline HTML (header/footer requires + raw markup + template-owned helpers `$genderFi`, `$page_title`) fully deleted — zero dead code, matching Phase 7's already-shipped `public/themes/default/pages/*.php` templates exactly

## Task Commits

Each task was committed atomically:

1. **Task 1: Migrate index.php to data-only + standardize Model B hook** - `a0886e3` (feat)
2. **Task 2: Migrate hevoset.php to data-only + add Model B hook** - `f2b98a1` (feat)
3. **Task 3: Migrate kasvatus.php to data-only + add Model B hook** - `2b1dc69` (feat)

_No TDD tasks in this plan (pure PHP controller refactor, no test suite in project)._

## Files Created/Modified
- `public/pages/index.php` - Canonical Model B hook + data queries (horse count, foal count, latest post) + guarded `resolveThemePath('pages/index.php')` delegation; `$page_title` removed
- `public/pages/hevoset.php` - Added `theme.php` require + Model B hook; kept horse-list query; removed `$page_title`/`$genderFi`; added guarded Malli A delegation
- `public/pages/kasvatus.php` - Added `theme.php` require + Model B hook; kept foals query + `$expected`/`$born` filters; removed `$page_title`/`$genderFi`; added guarded Malli A delegation

## Decisions Made
- Standardized `index.php`'s pre-existing Model B hook from raw `realpath()` form to the `resolveThemePath()` form (per plan's D-02, matching `hevonen.php`'s canonical structure) — no behavior change, only structural consistency across all 7 controllers this phase targets.
- Confirmed via live verification that `public/themes/oma-talli/` (the currently active theme in this dev environment's `settings.active_theme`) has no root-level `hevoset.php`/`kasvatus.php`, so Malli A correctly falls through to the default theme's `pages/*.php` templates for those two controllers even with a non-default theme active — this is the intended fallback behavior of `resolveThemePath()`, not a bug.

## Deviations from Plan

None - plan executed exactly as written. All 3 tasks matched their `<action>` blocks precisely; acceptance criteria and `<verify>` commands passed as specified.

## Issues Encountered
- Docker Desktop was not running at session start (`virtuaalitalli-web` container existed but was stopped). Started Docker Desktop and the `virtuaalitalli-web`/`virtuaalitalli-phpmyadmin` containers to run `php -l` and `curl` verification — this is standard environment setup, not a plan deviation.
- The dev environment's `settings.active_theme` is currently `oma-talli` (the user's real WIP theme for this milestone, per 08-CONTEXT.md), not `default`. Task 1's acceptance criterion `curl ... contains "frontpage-hero"` assumes the default theme is active, since `oma-talli` ships its own root-level `index.php` that fully overrides via Malli B. Verified index.php's Malli A path correctly by temporarily switching `active_theme` to `default` in the `settings` table, running the curl check (passed, 2 matches for `frontpage-hero`), then restoring `active_theme` back to `oma-talli` immediately after. No permanent database or code state was altered by this verification step.

## User Setup Required

None - no external service configuration required.

## Requirements Note

`THEME-08` ("Kaikki 5 julkista sivukontrolleria... data-only") spans this plan plus 08-02 (which finishes `yhteystiedot.php`, the 5th and last base page) — `hevonen.php` (08-03) is a 6th controller also tagged THEME-08 in its own plan. REQUIREMENTS.md's THEME-08 checkbox was deliberately left unchecked after this plan; it is fully satisfied only once 08-02 and 08-03 land. Do not mark THEME-08 complete until the last contributing plan in this phase finishes.

## Next Phase Readiness
- Plans 08-02/08-03/08-04 can proceed with the remaining 4 controllers (`hevonen.php`, `yhteystiedot.php`, `ajankohtaista.php`, `postaus.php`) using the identical Model B hook + Malli A delegation pattern now proven working in `index.php`, `hevoset.php`, `kasvatus.php`.
- No blockers. The dev environment's active theme remains `oma-talli` as it was before this plan started (verification-only theme switch was reverted).

## Self-Check: PASSED

All 3 modified files and the SUMMARY.md confirmed present on disk. All 4 commit hashes (`a0886e3`, `f2b98a1`, `2b1dc69`, `3330f06`) confirmed present in `git log --oneline --all`.
