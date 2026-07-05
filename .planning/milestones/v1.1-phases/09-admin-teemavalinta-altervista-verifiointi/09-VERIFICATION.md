---
phase: 09-admin-teemavalinta-altervista-verifiointi
verified: 2026-07-05T09:35:22Z
status: passed
score: 9/9 must-haves verified
behavior_unverified: 0
overrides_applied: 0
---

# Phase 9: Admin-teemavalinta & Altervista-verifiointi Verification Report

**Phase Goal:** Admin voi valita aktiivisen teeman (THEME-10, THEME-11), suora HTTP-pääsy teeman page-templateihin on estetty (THEME-12 koodiosuus), ja koko teemajärjestelmä on varmistettu toimivaksi Altervistan tuotantoympäristössä (THEME-12 tuotantoverifiointi).
**Verified:** 2026-07-05T09:35:22Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `settings.php` lists all installed themes using `theme.json`'s `name` field, not raw folder names (THEME-10) | VERIFIED | `public/admin/settings.php:20-23` — `glob($themes_dir . '/*/theme.json')` loop populates `$available_themes[$theme_key] = $meta['name'] ?? $theme_key`; rendered at lines 216-224 |
| 2 | Admin can select a theme from the list and save it; the site switches theme immediately (no caching) (THEME-11) | VERIFIED | `settings.php:73-91` persists `active_theme` via `INSERT ... ON DUPLICATE KEY UPDATE`; `public/src/includes/theme.php:17-22` re-reads `active_theme` from the `settings` table fresh on every request (no session/opcache-level cache) — next request immediately picks up the new value |
| 3 | Theme save is allowlist-validated (rejects unknown theme keys) and CSRF-protected (THEME-11) | VERIFIED | `settings.php:30` `validate_csrf_token($_POST['csrf_token'] ?? '')`; `settings.php:74` `array_key_exists($active_theme_post, $available_themes) ? $active_theme_post : 'default'`; both functions confirmed defined in `public/src/includes/helpers.php` |
| 4 | `public/themes/default/pages/.htaccess` denies direct HTTP access to `*.php` theme templates (THEME-12, code portion) | VERIFIED | File exists with `<FilesMatch "\.php$"> Deny from all </FilesMatch>` + Finnish `# Tietoturva:` comment; local Docker Apache spot-check: `curl http://localhost:8080/themes/default/pages/index.php` → `403` |
| 5 | Deny-from-all does not break rendering — templates still load via `require_once` from `resolveThemePath()` (regression guard) | VERIFIED | Root `public/.htaccess` rewrite rules only target `pages/` and `theme-page.php`, never `themes/{theme}/pages/`; `resolveThemePath()` (`theme.php:92-117`) is a filesystem `realpath()`+`require_once` path, not an HTTP request — Apache `Deny` cannot intercept it; local Docker home page still renders (`curl / → 302` redirect to normal flow, not 403/500) |
| 6 | `public/themes/` folder is protected from directory browsing in production (THEME-12) | VERIFIED | `public/.htaccess:2` `Options -Indexes` (inherited by `themes/`, no override found); human-confirmed in production per 09-02-SUMMARY.md (`themes/` URL does not list contents); local Docker spot-check: `curl http://localhost:8080/themes/` → `403` |
| 7 | Theme CSS loads with correct MIME type (`text/css`) in Altervista production (THEME-12) | VERIFIED | Human-confirmed via browser DevTools Network tab against production per 09-02-SUMMARY.md (D-07 method, not curl); local Docker corroboration: `curl -I http://localhost:8080/themes/default/assets/css/style.css` → `Content-Type: text/css` |
| 8 | In production, a direct URL to a theme template (`themes/default/pages/index.php`) is denied (403) (THEME-12) | VERIFIED | Human-confirmed in production per 09-02-SUMMARY.md — direct request returned Error 403, no PHP execution/notices; consistent with plan 09-01's deployed `.htaccess` |
| 9 | In production, the default theme renders correctly on all public pages after FTP deploy (THEME-12) | VERIFIED | Human-confirmed in production per 09-02-SUMMARY.md — home, hevoset, single hevonen, kasvatus, yhteystiedot, ajankohtaista, single postaus all rendered without breakage under `/demotalli-02/` |

**Score:** 9/9 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `public/themes/default/pages/.htaccess` | Denies direct HTTP access to `*.php` templates; no `php_flag`/`IfModule mod_php.c`/index-suppression directives | VERIFIED | 5 lines: Finnish `# Tietoturva:` comment + single `<FilesMatch "\.php$"> Deny from all </FilesMatch>` block. Confirmed no `php_flag`, no `IfModule`, no `Options -Indexes` repetition. All 7 default theme templates (`index, hevoset, kasvatus, yhteystiedot, hevonen, ajankohtaista, postaus`) still present and unmodified. |
| `public/admin/settings.php` | Read-only; already satisfies THEME-10/THEME-11 | VERIFIED (unchanged) | `git status` confirms no modification to this file by Phase 9. Contains theme glob-listing (THEME-10) and CSRF+allowlist validation (THEME-11) as required. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|----|--------|---------|
| Root `public/.htaccess` rewrite rules | `public/pages/*.php`, `theme-page.php` | `RewriteRule` targets | WIRED | Confirmed no rewrite rule targets `themes/{theme}/pages/` — new `.htaccess` cannot break the app's routing |
| Page controllers (`public/pages/*.php`) | `resolveThemePath()` → theme templates | PHP `require_once` (filesystem include) | WIRED | All 8 files under `public/pages/` (`index, hevoset, hevonen, kasvatus, yhteystiedot, ajankohtaista, postaus, theme-page`) call `resolveThemePath()`; this bypasses Apache HTTP access control entirely, so the new `Deny from all` in `pages/.htaccess` does not affect rendering |
| `settings.php` theme-select form | `settings` DB table (`active_theme` key) | `INSERT ... ON DUPLICATE KEY UPDATE` + `theme.php` fresh `SELECT` per request | WIRED | Verified no request-level or session-level cache of `active_theme` between the write and the next page's shim initialization |
| `.github/workflows/deploy.yml` (FTP deploy) | Altervista `/demotalli-02/` production tree | `SamKirkland/FTP-Deploy-Action`, push-to-main trigger, whole `public/` tree | WIRED | Confirmed by 09-02-SUMMARY.md: the deployed `.htaccess` was observed live in production (403 on direct template access) |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| Direct HTTP access to theme template is denied (local Docker, corroborating production) | `curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/themes/default/pages/index.php` | `403` | PASS |
| `themes/` directory is not browsable (local Docker, corroborating production) | `curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/themes/` | `403` | PASS |
| Theme CSS served with correct MIME type (local Docker, corroborating production DevTools check) | `curl -sI http://localhost:8080/themes/default/assets/css/style.css` | `Content-Type: text/css` | PASS |
| Rendering path is not broken by the new `.htaccess` | `curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/` | `302` (normal redirect flow, not 403/500) | PASS |

Note: these local Docker checks are supplementary corroboration only. The authoritative evidence for THEME-12's production-verification portion is the human-confirmed checkpoint recorded in `09-02-SUMMARY.md` (per task instructions — not re-run here, as no browser/production access is available to this verifier).

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|--------------|--------|----------|
| THEME-10 | 09-01 | Admin sees all installed themes listed by `theme.json` name | SATISFIED | `settings.php` glob + `$meta['name']` listing (pre-existing, verified read-only) |
| THEME-11 | 09-01 | Admin selects and saves active theme (allowlist + CSRF) | SATISFIED | `settings.php` CSRF check + allowlist validation (pre-existing, verified read-only) |
| THEME-12 | 09-01, 09-02 | Theme system verified working in Altervista production (CSS MIME, URL paths, `themes/` folder protection) | SATISFIED | Code portion: new `pages/.htaccess` (09-01). Production portion: human-confirmed 4/4 checks passing (09-02-SUMMARY.md) |

No orphaned requirements — REQUIREMENTS.md maps only THEME-10/THEME-11/THEME-12 to Phase 9, and both plans declare all three in frontmatter.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `public/themes/default/pages/.htaccess` | — | None found (no TBD/FIXME/XXX/TODO/HACK/PLACEHOLDER, no empty implementations) | — | Clean — 5-line file, matches established `.htaccess` idiom used elsewhere in the project |

Carried forward from `09-REVIEW.md` (non-blocking per task instructions — do not undermine any must-have above):

- **WR-01 (warning, empirically mitigated):** `Deny from all` is Apache 2.2/`mod_access_compat` syntax with no Apache-2.4-native (`Require all denied`) fallback. If `mod_access_compat` were disabled on the target host, this would degrade to a 500 instead of a clean 403. This was empirically resolved by the human's production check #4 (real 403 observed, not 500) and independently corroborated here on local Docker Apache 2.4.67 (`curl` → `403`, not `500`). Still worth hardening project-wide in a future pass, but does not block this phase.
- **WR-02 (warning, explicitly deferred by D-06):** The `oma-talli` theme (already selectable in the admin list because it has a `theme.json`) has no `pages/`-folder `.htaccess` equivalent — its templates sit directly under the theme root and are HTTP-reachable. This is explicitly scoped out of Phase 9 per `09-CONTEXT.md` decision D-06 ("verify/protect default theme only; oma-talli is incomplete and out of scope"). Phase 9's roadmap success criteria and PLAN must-haves only reference the `default` theme's protection — this gap does not undermine any of them. No later phase in the current v1.1 roadmap addresses it (Phase 9 is the last phase of this milestone), so it remains an open, explicitly-documented follow-up item rather than a deferred-to-later-phase item.

### Human Verification Required

None outstanding. The four production checks required for THEME-12 (CSS `Content-Type: text/css` via DevTools, all default-theme pages rendering under `/demotalli-02/`, `themes/` directory listing blocked, direct template access returning 403) were already performed by the human against the live Altervista site and confirmed passing, recorded in `09-02-SUMMARY.md`. Per task instructions, this is treated as satisfied evidence and was not re-attempted (no browser/production access available to this verifier).

### Gaps Summary

No gaps found. All 9 derived must-have truths (5 from ROADMAP.md success criteria plus phase-specific implementation/regression details from both plans' frontmatter) are verified either by direct code inspection, local Docker corroboration, or human-confirmed production evidence. THEME-10, THEME-11, and THEME-12 are all satisfied. The two non-blocking review warnings (WR-01, WR-02) are carried forward for visibility but do not affect phase completion, per explicit scope decisions (D-06) and empirical production evidence (real 403, not 500).

Note: `.planning/STATE.md` still shows Phase 9 as "Not started" / "Wave 2 awaiting user push" in its Workflow Status table and Session Continuity section — this is stale documentation lagging behind the actual completed work (09-02-SUMMARY.md confirms Wave 2 is done). This is a documentation-sync issue, not a code/goal gap, and is expected to be corrected by the standard phase-completion workflow step that runs after this verification.

---

_Verified: 2026-07-05T09:35:22Z_
_Verifier: Claude (gsd-verifier)_
