---
phase: 08-sivukontrollerien-migraatio
plan: 04
subsystem: theming
tags: [php, theme-engine, resolveThemePath, verification]

# Dependency graph
requires:
  - phase: 08-sivukontrollerien-migraatio (plans 01, 02, 03)
    provides: all 7 base public controllers (index, hevoset, hevonen, kasvatus, yhteystiedot, ajankohtaista, postaus) converted to data-only Malli A delegation shells
provides:
  - "Empirical proof of Success Criteria #3: a DB-only settings.active_theme change alters site appearance with zero controller edits"
  - "Net-zero persistent artifact: throwaway testitema theme created and fully removed within this plan"
affects: [09-admin-teemavalinta (theme switching mechanism proven safe to expose in an admin UI selector)]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Throwaway proof-theme pattern: full copy of default + one marker string, DB flip, curl-verify, revert, delete — used once to validate Malli A end-to-end without leaving permanent surface"

key-files:
  created: []
  modified: []

key-decisions:
  - "Followed D-06/D-07 exactly: testitema theme created solely to prove Success Criteria #3 and deleted before phase close; no root-level override files were added to testitema so the proof exercises Malli A (not Malli B)"
  - "Restored settings.active_theme to 'default' (not the dev environment's prior 'oma-talli') per the plan's explicit acceptance criteria and success_criteria statement — leaves the project in a clean default-theme state as required for phase closeout"

patterns-established:
  - "Marker-based theme-switch proof: inject a unique HTML comment into includes/header.php of a full theme copy, flip settings.active_theme, curl multiple Malli A pages to confirm presence/absence, with git status --porcelain public/pages/ used as the zero-controller-change witness"

requirements-completed: [THEME-08, THEME-09]

coverage:
  - id: D1
    description: "Throwaway testitema theme created as a full copy of default with a single TESTITEMA-MARKER injected into includes/header.php; no root-level override files so Malli A delegation is exercised"
    requirement: "THEME-08"
    verification:
      - kind: integration
        ref: "test -f public/themes/testitema/pages/index.php; grep -c TESTITEMA-MARKER header.php = 1 (testitema) / 0 (default); test ! -f public/themes/testitema/index.php — all passed prior to Task 3 cleanup"
        status: pass
    human_judgment: false
  - id: D2
    description: "DB-only theme switch (settings.active_theme=testitema) made the marker appear on two independent Malli A pages (index.php, hevoset.php) at HTTP 200 with git status --porcelain public/pages/ empty before and after, proving Success Criteria #3; restoring active_theme=default removed the marker"
    requirement: "THEME-09"
    verification:
      - kind: integration
        ref: "curl http://localhost:8080/pages/index.php and /pages/hevoset.php with active_theme=testitema both returned HTTP 200 and contained TESTITEMA-MARKER; git status --porcelain public/pages/ empty throughout; after reverting to active_theme=default, curl /pages/index.php returned HTTP 200 with marker absent"
        status: pass
    human_judgment: false
  - id: D3
    description: "testitema theme deleted entirely (D-07); default and oma-talli themes untouched; settings.active_theme confirmed 'default'; site renders HTTP 200 with no leftover marker"
    requirement: "THEME-09"
    verification:
      - kind: integration
        ref: "test ! -d public/themes/testitema; test -d public/themes/default && test -d public/themes/oma-talli; SELECT setting_value FROM settings WHERE setting_key='active_theme' = 'default'; curl /pages/index.php returns 200 with 0 TESTITEMA-MARKER occurrences"
        status: pass
    human_judgment: false

# Metrics
duration: 15min
completed: 2026-07-04
status: complete
---

# Phase 8 Plan 04: Sivukontrollerien migraatio (testiteema-todiste) Summary

**Success Criteria #3 empirically proven with a throwaway `testitema` theme: flipping `settings.active_theme` in the DB changed rendered output on two Malli A pages at HTTP 200 with zero controller edits, then the test theme was fully deleted per D-07 leaving no persistent artifact.**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-07-04 (session start)
- **Completed:** 2026-07-04
- **Tasks:** 3/3
- **Files modified:** 0 net (12 files created in Task 1, all 12 deleted in Task 3)

## Accomplishments
- Created `public/themes/testitema/` as a full recursive copy of `public/themes/default/`, with `theme.json` name set to `testitema` and a single `<!-- TESTITEMA-MARKER -->` HTML comment injected into `includes/header.php` (present in testitema, absent in default) — no root-level override files, so the proof specifically exercises the Malli A delegation path rather than the Malli B root-override hook
- Flipped `settings.active_theme` to `testitema` via direct SQL (developer-only DB write, no controller involved) and confirmed both `pages/index.php` and `pages/hevoset.php` rendered the marker at HTTP 200, while `git status --porcelain public/pages/` remained empty throughout — direct evidence that the theme switch required zero controller edits
- Restored `settings.active_theme` to `default`, confirmed the marker disappeared and the site kept rendering at HTTP 200
- Deleted `public/themes/testitema/` entirely (D-07), confirmed `default` and `oma-talli` themes are untouched, confirmed `settings.active_theme` is `default`, and confirmed the site still renders at HTTP 200 with no leftover marker — net-zero persistent artifact from this plan

## Task Commits

Each task was committed atomically:

1. **Task 1: Create the temporary test theme (copy of default + visible marker)** - `8167041` (feat)
2. **Task 2: Switch active_theme to testitema, prove appearance change, switch back** - no commit (DB-only verification task; no files were created or modified — `git status --porcelain public/pages/` was the artifact under test and remained empty as required)
3. **Task 3: Delete the temporary test theme and confirm clean state (D-07)** - `81fa6ea` (chore)

_No TDD tasks in this plan (verification/proof plan, no test suite in project)._

## Files Created/Modified
- `public/themes/testitema/` (12 files: theme.json, includes/{header,footer,nav}.php, pages/{index,hevoset,hevonen,kasvatus,yhteystiedot,ajankohtaista,postaus}.php, assets/css/style.css) — created in Task 1 as a full copy of `public/themes/default/` with the `TESTITEMA-MARKER` comment added to `includes/header.php` and `theme.json` name changed to `testitema`; deleted in its entirety in Task 3. Net persistent change: none.

## Decisions Made
- Restored `settings.active_theme` to `default` (per the plan's explicit Task 2/Task 3 acceptance criteria and the plan's `success_criteria` statement), not to `oma-talli` which was the dev environment's prior active theme per the 08-03 SUMMARY. This is the plan-mandated clean-closeout state, not an accidental regression — the phase's success criteria requires the project to end in a clean `default`-theme state with no dangling reference to the deleted `testitema` theme.
- No root-level override files (`testitema/index.php`, `testitema/hevoset.php`, etc.) were added to the test theme, ensuring the proof specifically validates the Malli A `resolveThemePath('pages/X.php')` delegation path introduced across plans 08-01/08-02/08-03, rather than the pre-existing Malli B root-override hook.

## Deviations from Plan

None - plan executed exactly as written. All three tasks matched their `<action>` blocks precisely; every acceptance criterion and `<verify>` command passed as specified.

## Issues Encountered
None.

## User Setup Required

None - no external service configuration required. All verification used the existing local Docker stack (`virtuaalitalli-web`, `virtuaalitalli-db`) already running on `localhost:8080`.

## Next Phase Readiness
- Success Criteria #3 for the v1.1 Teemajärjestelmä milestone is now empirically demonstrated: theme switching via `settings.active_theme` requires zero controller changes across all 7 base public controllers.
- Phase 8 (Sivukontrollerien migraatio) is now fully complete: all 7 controllers are data-only Malli A/B shells (plans 01-03), and the end-to-end switching mechanism is proven (this plan).
- Phase 9 (Admin-teemavalinta & Altervista) can proceed to build the admin theme-selector UI on top of this proven mechanism with confidence that no further controller changes will be needed when a new theme is installed and selected.
- No blockers. `public/themes/` now contains only `default` and `oma-talli` (the user's real WIP theme, untouched throughout this plan); `settings.active_theme` is `default`.

## Self-Check: PASSED

Verified `public/themes/testitema/` does not exist on disk (correctly deleted) and `public/themes/default/`, `public/themes/oma-talli/` are present. Confirmed commit hashes `8167041` and `81fa6ea` both present in `git log --oneline -3`. Confirmed `settings.active_theme` = `default` in the live DB.

---
*Phase: 08-sivukontrollerien-migraatio*
*Completed: 2026-07-04*
