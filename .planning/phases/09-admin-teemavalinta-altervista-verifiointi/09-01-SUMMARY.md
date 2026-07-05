---
phase: 09-admin-teemavalinta-altervista-verifiointi
plan: 01
subsystem: infra
tags: [apache, htaccess, security, theme-system]

# Dependency graph
requires:
  - phase: 08-sivukontrollerien-migraatio
    provides: data-only page controllers that require_once theme templates via resolveThemePath()
provides:
  - public/themes/default/pages/.htaccess denying direct HTTP access to theme page templates
  - Formal verification that public/admin/settings.php already satisfies THEME-10/THEME-11
affects: [09-02, future-theme-additions]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Directory-level .htaccess protection: every sensitive directory gets its own <FilesMatch> Deny from all block (established convention, now extended to themes/{theme}/pages/)"

key-files:
  created:
    - public/themes/default/pages/.htaccess
  modified: []

key-decisions:
  - "Deny from all on \\.php$ inside pages/ is safe because root .htaccess rewrite rules never target themes/{theme}/pages/ directly, and PHP require_once (filesystem include) bypasses Apache HTTP access control entirely"
  - "settings.php accepted as-is per D-05 — no modification; THEME-10/THEME-11 satisfied by pre-existing glob()+theme.json listing and CSRF+allowlist POST validation"

patterns-established:
  - "Any future theme's pages/ folder should receive the same .htaccess as a copy-paste template (D-04)"

requirements-completed: [THEME-10, THEME-11, THEME-12]

coverage:
  - id: D1
    description: "public/themes/default/pages/.htaccess created, denying direct HTTP access to *.php theme templates while leaving require_once-based rendering untouched"
    requirement: "THEME-12"
    verification:
      - kind: unit
        ref: "grep assertion: FilesMatch \\.php$ + Deny from all present, no php_flag/IfModule, all 7 templates still present"
        status: pass
    human_judgment: false
  - id: D2
    description: "public/admin/settings.php formally verified (read-only) to satisfy THEME-10 (theme discovery glob + name display) and THEME-11 (CSRF + allowlist validation) without modification"
    requirement: "THEME-10"
    verification:
      - kind: unit
        ref: "grep assertion: glob($themes_dir . '/*/theme.json'), $meta['name'], validate_csrf_token(, array_key_exists($active_theme_post, $available_themes) all present; git diff --name-only shows settings.php unchanged"
        status: pass
    human_judgment: false
  - id: D3
    description: "public/admin/settings.php formally verified (read-only) to satisfy THEME-11 (allowlist-validated theme selection with CSRF protection)"
    requirement: "THEME-11"
    verification:
      - kind: unit
        ref: "grep assertion: validate_csrf_token( and array_key_exists($active_theme_post, $available_themes) present in settings.php; validate_csrf_token/generate_csrf_token defined in helpers.php"
        status: pass
    human_judgment: false

# Metrics
duration: 5min
completed: 2026-07-05
status: complete
---

# Phase 9 Plan 1: Theme Page Template Protection & Settings.php Verification Summary

**New `public/themes/default/pages/.htaccess` blocks direct HTTP access to theme templates via `<FilesMatch "\.php$"> Deny from all`, and pre-existing `public/admin/settings.php` is formally verified (read-only, unmodified) to already satisfy THEME-10 and THEME-11.**

## Performance

- **Duration:** 5 min
- **Started:** 2026-07-05T07:14:01Z
- **Completed:** 2026-07-05T07:18:02Z
- **Tasks:** 2 completed
- **Files modified:** 1 (created)

## Accomplishments
- Created `public/themes/default/pages/.htaccess` closing an information-disclosure gap: direct HTTP requests to theme page templates (which expect controller-set variables) previously produced PHP notices/warnings disclosing file paths and structure
- Confirmed the `.htaccess` deny does not affect the actual rendering path — theme templates are loaded via `require_once` from `resolveThemePath()`, a filesystem include Apache access control never intercepts
- Formally verified via automated grep assertions that `public/admin/settings.php` already satisfies THEME-10 (glob-based theme discovery + `theme.json` `name` field display) and THEME-11 (CSRF token validation + allowlist validation on the `active_theme` POST field) — no code changes needed or made

## Task Commits

Each task was committed atomically:

1. **Task 1: Create pages/.htaccess to deny direct HTTP access to theme templates (D-03/D-04, THEME-12)** - `c8097f8` (feat)
2. **Task 2: Verify existing settings.php satisfies THEME-10 and THEME-11 without modification (D-05)** - no commit (read-only verification task; `public/admin/settings.php` was not modified, confirmed via `git diff --name-only`)

**Plan metadata:** (recorded in final docs commit)

_Note: Task 2 produced no file changes by design (D-05) — verification results are captured in this SUMMARY's coverage block._

## Files Created/Modified
- `public/themes/default/pages/.htaccess` - New Apache per-directory config; single `<FilesMatch "\.php$"> Deny from all </FilesMatch>` block with a Finnish `# Tietoturva:` rationale comment. No index-suppression or php-engine directives (both intentionally omitted per plan constraints).

## Decisions Made
- Followed D-03/D-04 exactly: `.htaccess`-based mechanism (not a PHP `defined()` guard), explicit `Deny from all` rather than relying on `display_errors Off` alone.
- Followed D-05 exactly: `public/admin/settings.php` left completely unmodified; THEME-10/THEME-11 satisfied by existing code, verified read-only.
- Followed D-06: scope limited to the `default` theme only — `oma-talli` has no `pages/` subdirectory yet, so no `.htaccess` was created there.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- THEME-12's folder-protection code portion is complete for the `default` theme; the same `.htaccess` file is documented as the template for any future theme's `pages/` folder (per D-04).
- THEME-10 and THEME-11 are now formally verified complete via pre-existing code — no further code work needed for these requirements.
- Remaining THEME-12 scope (Altervista production verification: CSS MIME types, URL paths, folder-listing protection) is a manual, no-code verification step per D-06/D-07/D-08, expected to be covered by 09-02 or a post-deployment check — not part of this plan's code deliverable.

---
*Phase: 09-admin-teemavalinta-altervista-verifiointi*
*Completed: 2026-07-05*

## Self-Check: PASSED

- FOUND: public/themes/default/pages/.htaccess
- FOUND: .planning/phases/09-admin-teemavalinta-altervista-verifiointi/09-01-SUMMARY.md
- FOUND commit: c8097f8
- FOUND commit: ce1e934
