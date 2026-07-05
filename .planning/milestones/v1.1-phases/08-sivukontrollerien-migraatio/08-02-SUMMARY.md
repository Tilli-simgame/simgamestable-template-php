---
phase: 08-sivukontrollerien-migraatio
plan: 02
subsystem: theming
tags: [php, pdo, theme-engine, resolveThemePath, data-only-controller]

# Dependency graph
requires:
  - phase: 07-oletusteman-rakenne
    provides: public/themes/default/pages/*.php clean templates (yhteystiedot, ajankohtaista, postaus) with their own variable contracts and self-set $page_title (except postaus, which relies on the controller)
  - phase: 08-sivukontrollerien-migraatio (plan 01)
    provides: proven Model B hook + Malli A delegation pattern (canonical resolveThemePath() form) established on index.php, hevoset.php, kasvatus.php
provides:
  - yhteystiedot.php, ajankohtaista.php, postaus.php as data-only controllers delegating all HTML rendering to resolveThemePath('pages/X.php')
  - canonical Model B root-override hook standardized across all 3 controllers
  - postaus.php's two inline-HTML 404 branches unified to the silent http_response_code(404); exit; convention
affects: [08-03 (hevonen.php, the last remaining controller with pre-existing Model B hook needing inline-HTML removal + Malli A delegation)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Malli A: controller fetches data then `require resolveThemePath('pages/X.php')`, no guard needed since default theme always ships all 7 templates (guard omitted per existing pattern from 08-01, consistent with hevoset.php/kasvatus.php/index.php)"
    - "Malli B: canonical resolveThemePath('X.php') root-override hook, identical structure across controllers, guarded by !str_starts_with(THEME_PATH, THEMES_ROOT.'default'.DIRECTORY_SEPARATOR)"
    - "Unified silent 404: http_response_code(404); exit; with no header/footer render or echoed body, matching theme-page.php convention"

key-files:
  created: []
  modified:
    - public/pages/yhteystiedot.php
    - public/pages/ajankohtaista.php
    - public/pages/postaus.php

key-decisions:
  - "postaus.php keeps its dynamic $page_title = $post['title']; line — unlike the other migrated pages, the postaus template does not self-set $page_title, it relies on the controller"
  - "Both postaus.php 404 branches (missing param, not-found) unified from inline-HTML render to the silent http_response_code(404); exit; idiom from theme-page.php — reduces information disclosure surface (T-08-05)"

patterns-established:
  - "Data-only controller shape confirmed across 6/7 controllers: db.php + theme.php requires -> Model B hook -> data queries -> Malli A delegation"

requirements-completed: [THEME-08, THEME-09]

coverage:
  - id: D1
    description: "yhteystiedot.php converted to data-only controller with canonical Model B hook and Malli A delegation, no inline HTML or page_title"
    requirement: "THEME-08"
    verification:
      - kind: integration
        ref: "docker exec virtuaalitalli-web php -l /var/www/html/pages/yhteystiedot.php (No syntax errors) && curl http://localhost:8080/pages/yhteystiedot.php returns 200; grep checks confirm 0 header/footer/page_title references, 1 Model B hook, 1 Malli A delegation"
        status: pass
    human_judgment: false
  - id: D2
    description: "ajankohtaista.php (blog listing) converted to data-only controller: theme.php require + Model B hook added, page_title/MONTHS_FI removed, Malli A delegation added"
    requirement: "THEME-09"
    verification:
      - kind: integration
        ref: "docker exec virtuaalitalli-web php -l /var/www/html/pages/ajankohtaista.php (No syntax errors) && curl http://localhost:8080/pages/ajankohtaista.php returns 200; grep checks confirm 0 header/footer/MONTHS_FI references, 1 Model B hook, 1 Malli A delegation"
        status: pass
    human_judgment: false
  - id: D3
    description: "postaus.php (single post) converted to data-only controller: theme.php require + Model B hook added, both 404 branches unified to silent http_response_code(404); exit, dynamic page_title kept, MONTHS_FI/postYear/postMonth removed, Malli A delegation added"
    requirement: "THEME-09"
    verification:
      - kind: integration
        ref: "docker exec virtuaalitalli-web php -l /var/www/html/pages/postaus.php (No syntax errors); curl http://localhost:8080/pages/postaus.php (no slug) returns 404; curl with a valid DB slug (hissun-paikky) returns 200; grep checks confirm 2x http_response_code(404), 0 MONTHS_FI, 1 dynamic page_title, 1 Model B hook, 1 Malli A delegation"
        status: pass
    human_judgment: false

# Metrics
duration: 15min
completed: 2026-07-04
status: complete
---

# Phase 8 Plan 02: Sivukontrollerien migraatio (yhteystiedot/ajankohtaista/postaus) Summary

**yhteystiedot.php, ajankohtaista.php, and postaus.php converted to data-only controllers delegating 100% of HTML rendering to `resolveThemePath('pages/X.php')`, with postaus.php's dual 404 branches unified to the silent `http_response_code(404); exit;` convention.**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-07-04T07:40:00Z (approx.)
- **Completed:** 2026-07-04T07:55:51Z
- **Tasks:** 3/3
- **Files modified:** 3

## Accomplishments
- `yhteystiedot.php` gained the `theme.php` require and canonical Model B hook, kept its settings query/extraction, removed the now-template-owned `$page_title`, and delegates via `resolveThemePath('pages/yhteystiedot.php')` — this completes the last of the 5 base pages for THEME-08.
- `ajankohtaista.php` (blog listing) gained the `theme.php` require and Model B hook, kept filter parsing/query branches/archive query, removed `$page_title` and the template-owned `$MONTHS_FI` array, and delegates via `resolveThemePath('pages/ajankohtaista.php')`.
- `postaus.php` (single post) gained the `theme.php` require and Model B hook, unified both inline-HTML 404 branches (missing param, not-found) to the silent `http_response_code(404); exit;` idiom, kept the dynamic `$page_title = $post['title'];` (template does not self-set it), removed `$MONTHS_FI` and the duplicate `$postYear`/`$postMonth` computation (template computes these itself), and delegates via `resolveThemePath('pages/postaus.php')`.
- All 3 controllers' inline HTML (header/footer requires + raw markup) fully deleted — zero dead code, matching Phase 7's already-shipped `public/themes/default/pages/*.php` templates exactly.

## Task Commits

Each task was committed atomically:

1. **Task 1: Migrate yhteystiedot.php to data-only + add Model B hook** - `67d6ecf` (feat)
2. **Task 2: Migrate ajankohtaista.php (blog listing) to data-only** - `c703a1c` (feat)
3. **Task 3: Migrate postaus.php (single post) to data-only + unify 404** - `d075a0f` (feat)

_No TDD tasks in this plan (pure PHP controller refactor, no test suite in project)._

## Files Created/Modified
- `public/pages/yhteystiedot.php` - Canonical Model B hook + settings query/extraction + guarded Malli A delegation; `$page_title` removed
- `public/pages/ajankohtaista.php` - `theme.php` require + Model B hook; kept filter/query/archive logic; removed `$page_title`/`$MONTHS_FI`; Malli A delegation added
- `public/pages/postaus.php` - `theme.php` require + Model B hook; both 404 branches unified to silent `http_response_code(404); exit;`; dynamic `$page_title` kept; `$MONTHS_FI`/`$postYear`/`$postMonth` removed; Malli A delegation added

## Decisions Made
- Kept `postaus.php`'s dynamic `$page_title = $post['title'];` line unchanged — this is the one controller among the 6 migrated so far where the paired template does NOT self-set `$page_title` (it relies on the controller setting it before the template's own header include renders `<title>`), per the plan's explicit instruction.
- Unified `postaus.php`'s two previously-distinct inline-HTML 404 branches (no-param vs. not-found) into the identical silent `http_response_code(404); exit;` idiom used by `theme-page.php` — both cases now behave identically from the client's perspective (404, no body), reducing the information-disclosure surface per threat T-08-05.

## Deviations from Plan

None - plan executed exactly as written. All 3 tasks matched their `<action>` blocks precisely; every acceptance criterion and `<verify>` command passed as specified.

## Issues Encountered
- Docker Desktop's `virtuaalitalli-web`/`virtuaalitalli-db` containers were already running from the prior session, so no environment setup was needed this time.
- Git Bash's automatic path conversion (MSYS_NO_PATHCONV) initially mangled the `docker exec ... /var/www/html/...` container-internal paths into a Windows path. Resolved by prefixing verification commands with `MSYS_NO_PATHCONV=1` — this is a local shell/tooling quirk, not a plan or code issue, and required no code changes.

## User Setup Required

None - no external service configuration required.

## Requirements Note

`THEME-09` ("blogi.php, postaus.php -> data-only") is fully satisfied by this plan (`ajankohtaista.php` = the on-disk blog-listing controller referenced as "blogi.php" in the roadmap, plus `postaus.php`). `THEME-08` ("Kaikki 5 julkista sivukontrolleria... data-only") is now also fully satisfied — this plan completes `yhteystiedot.php`, the 5th and last base page (the other 4: `index.php`, `hevoset.php`, `kasvatus.php` from 08-01, and `hevonen.php` pending in 08-03, which is separately tagged in its own plan per 08-01's note). Both `THEME-08` and `THEME-09` checkboxes can now be marked complete for this plan's scope.

## Next Phase Readiness
- Plan 08-03 can proceed with the last remaining controller (`hevonen.php`, which already has a Model B hook and needs inline-HTML removal + Malli A delegation) using the identical pattern now proven working across 6/7 controllers.
- No blockers. The dev environment's active theme remains `oma-talli` (unchanged from prior session); `oma-talli` has no root-level `yhteystiedot.php`/`ajankohtaista.php`/`postaus.php`, so Malli A correctly falls through to the default theme's `pages/*.php` templates for all 3, confirmed by the 200/404 HTTP checks above.

## Self-Check: PASSED

All 3 modified files and the SUMMARY.md confirmed present on disk. All 3 task commit hashes (`67d6ecf`, `c703a1c`, `d075a0f`) confirmed present in `git log --oneline --all`.

---
*Phase: 08-sivukontrollerien-migraatio*
*Completed: 2026-07-04*
