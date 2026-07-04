---
phase: 08-sivukontrollerien-migraatio
plan: 03
subsystem: theming
tags: [php, pdo, theme-engine, resolveThemePath, data-only-controller]

# Dependency graph
requires:
  - phase: 07-oletusteman-rakenne
    provides: public/themes/default/pages/hevonen.php clean template owning $genderFi, pedigreeHorseLink(), pedigreeCell(), $heroPhoto/$heroStyle, and the full profile markup
  - phase: 08-sivukontrollerien-migraatio (plan 01, plan 02)
    provides: proven Model B hook + Malli A delegation pattern (canonical resolveThemePath() form) established on index.php, hevoset.php, kasvatus.php, yhteystiedot.php, ajankohtaista.php, postaus.php
provides:
  - hevonen.php as the 7th and final data-only controller, delegating all HTML rendering to resolveThemePath('pages/hevonen.php')
  - both hevonen.php 404 branches (missing param, not-found) unified to the silent http_response_code(404); exit; convention
  - all 7 base public controllers now confirmed data-only, completing the phase's core migration
affects: [09-admin-teemavalinta (theme selector can now safely assume all 7 controllers are theme-agnostic data-only shells)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Malli A: controller fetches data then guarded `resolveThemePath('pages/X.php')` delegation with http_response_code(404); exit; fallback"
    - "Malli B: canonical resolveThemePath('X.php') root-override hook, retained unchanged (already canonical prior to this plan)"
    - "Unified silent 404: http_response_code(404); exit; with no header/footer render or echoed body, matching theme-page.php convention"

key-files:
  created: []
  modified:
    - public/pages/hevonen.php

key-decisions:
  - "Kept dynamic $page_title = $horse['name']; in the controller — the hevonen template does not self-set $page_title, it relies on the controller setting it before the template's header include renders <title>"
  - "Removed $genderFi array, pedigreeHorseLink()/pedigreeCell() functions, and $heroPhoto/$heroStyle locals from the controller — all are already defined identically in themes/default/pages/hevonen.php; leaving them in place would cause a fatal function-redeclaration error"

patterns-established:
  - "All 7 base public controllers (index, hevoset, hevonen, kasvatus, yhteystiedot, ajankohtaista, postaus) now share the identical data-only shape: db.php + theme.php requires -> Model B hook -> data queries -> guarded Malli A delegation"

requirements-completed: [THEME-08]

coverage:
  - id: D1
    description: "hevonen.php converted to data-only controller: retained canonical Model B hook unchanged, both 404 branches unified to silent http_response_code(404); exit;, dynamic $page_title kept, template-owned helpers ($genderFi, pedigreeHorseLink(), pedigreeCell(), $heroPhoto/$heroStyle) removed, and guarded Malli A delegation (resolveThemePath('pages/hevonen.php')) appended"
    requirement: "THEME-08"
    verification:
      - kind: integration
        ref: "docker exec virtuaalitalli-web php -l /var/www/html/pages/hevonen.php (No syntax errors); curl http://localhost:8080/pages/hevonen.php (no slug) returns 404; with active_theme=default, curl ?slug=testiponi-tahti returns 200 and contains hero-banner/Sukutaulu/Kisakalenteri markup from the default theme template; grep checks confirm 0 header/footer/pedigreeHorseLink/pedigreeCell/genderFi references, 1 Model B hook, 1 Malli A delegation, 1 dynamic page_title"
        status: pass
    human_judgment: false

# Metrics
duration: 12min
completed: 2026-07-04
status: complete
---

# Phase 8 Plan 03: Sivukontrollerien migraatio (hevonen.php) Summary

**hevonen.php — the largest public controller (614 lines) — converted to a data-only controller: ~470 lines of inline HTML and two template-owned helper functions removed, both 404 branches unified to the silent convention, canonical Model B hook retained unchanged, guarded Malli A delegation added.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-07-04T08:01:51Z (approx.)
- **Completed:** 2026-07-04T08:13:00Z (approx.)
- **Tasks:** 1/1
- **Files modified:** 1

## Accomplishments
- `hevonen.php` fully migrated to a data-only controller: all DB queries producing `$horse`, `$id`, `$competitions`, `$showrecords`, `$photos`, `$foals`, `$horsePosts`, `$pedigree` retained unchanged
- Both inline-HTML 404 branches (missing `slug`/`id` param, horse not found) unified to the silent `http_response_code(404); exit;` idiom, matching `theme-page.php`'s convention and reducing information disclosure
- Template-owned presentation code removed from the controller: `$genderFi` array, `pedigreeHorseLink()` function, `pedigreeCell()` function, `$heroPhoto`/`$heroStyle` locals — all already defined identically in `themes/default/pages/hevonen.php`
- Canonical Model B root-override hook (`resolveThemePath('hevonen.php')`) retained byte-for-byte unchanged, as it was already the reference form all other controllers in this phase copied
- Guarded Malli A delegation appended: `resolveThemePath('pages/hevonen.php')` with `http_response_code(404); exit;` fallback if resolution fails
- This completes all 7 base public controllers (`index.php`, `hevoset.php`, `hevonen.php`, `kasvatus.php`, `yhteystiedot.php`, `ajankohtaista.php`, `postaus.php`) as data-only shells — the largest and most complex migration in the phase, deliberately isolated in its own plan for context budget

## Task Commits

Each task was committed atomically:

1. **Task 1: Migrate hevonen.php to data-only + unify 404 + strip template-owned helpers** - `199ddd6` (feat)

_No TDD tasks in this plan (pure PHP controller refactor, no test suite in project)._

## Files Created/Modified
- `public/pages/hevonen.php` - Retained Model B hook + all DB queries (competitions, showrecords, photos, foals, horsePosts, pedigree); unified both 404 branches to silent `http_response_code(404); exit;`; kept dynamic `$page_title = $horse['name'];`; removed `$genderFi`/`pedigreeHorseLink()`/`pedigreeCell()`/`$heroPhoto`/`$heroStyle`; added guarded `resolveThemePath('pages/hevonen.php')` delegation. Net change: 5 insertions, 477 deletions.

## Decisions Made
- Kept `hevonen.php`'s dynamic `$page_title = $horse['name'];` line unchanged — like `postaus.php` (08-02), the hevonen template does not self-set `$page_title`; the controller must set it before the template's header include renders `<title>`.
- Confirmed removing `pedigreeHorseLink()`/`pedigreeCell()`/`$genderFi`/`$heroPhoto`/`$heroStyle` from the controller was mandatory, not optional cleanup — the template defines `pedigreeHorseLink()` and `pedigreeCell()` itself, and PHP does not allow function redeclaration; leaving them in the controller would cause a fatal error the moment the template is `require`d.

## Deviations from Plan

None - plan executed exactly as written. The single task matched its `<action>` block precisely; all acceptance criteria and the `<verify>` command passed as specified.

## Issues Encountered
- The dev environment's `settings.active_theme` is `oma-talli` (the user's real WIP theme for this milestone), which ships its own root-level `hevonen.php` — so a direct curl of `?slug=...` with `oma-talli` active correctly renders via the Model B hook (oma-talli's own page), not Malli A. To specifically verify the new Malli A delegation path (per the plan's acceptance criteria), `active_theme` was temporarily switched to `default` in the `settings` table, the curl check was run (200 status, hero-banner/Sukutaulu/Kisakalenteri markup confirmed present), then `active_theme` was immediately restored to `oma-talli`. No permanent database or code state was altered by this verification step — same approach used in plan 08-01.

## User Setup Required

None - no external service configuration required.

## Requirements Note

`THEME-08` ("Kaikki 5 julkista sivukontrolleria... data-only") is now fully satisfied across all contributing plans: `index.php`, `hevoset.php`, `kasvatus.php` (08-01), `yhteystiedot.php` (08-02), and `hevonen.php` (this plan, 08-03) — the 6th controller tagged THEME-08. All 7 base public controllers (including `ajankohtaista.php`/`postaus.php` under THEME-09, completed in 08-02) are now confirmed data-only.

## Next Phase Readiness
- All 7 base public controllers are now data-only, theme-agnostic shells delegating 100% of rendering to `resolveThemePath('pages/X.php')` (Malli A) with a symmetric Model B root-override hook in every controller.
- Plan 08-04 (if scoped for phase closeout/testiteema per D-06/D-07) or Phase 9 (admin theme selection) can proceed without further controller changes.
- No blockers. The dev environment's active theme remains `oma-talli` (unchanged from prior sessions); `oma-talli` has its own root-level `hevonen.php`, so Malli B correctly takes precedence there, while Malli A's fallback to the default theme's `pages/hevonen.php` template was independently verified to work correctly.

## Self-Check: PASSED

All modified file and the SUMMARY.md confirmed present on disk. Commit hash `199ddd6` confirmed present in `git log --oneline --all`.

---
*Phase: 08-sivukontrollerien-migraatio*
*Completed: 2026-07-04*
