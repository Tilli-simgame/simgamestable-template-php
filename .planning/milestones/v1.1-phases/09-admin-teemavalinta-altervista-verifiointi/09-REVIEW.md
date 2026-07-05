---
phase: 09-admin-teemavalinta-altervista-verifiointi
reviewed: 2026-07-05T00:00:00Z
depth: standard
files_reviewed: 1
files_reviewed_list:
  - public/themes/default/pages/.htaccess
findings:
  critical: 0
  warning: 2
  info: 1
  total: 3
status: issues_found
---

# Phase 09: Code Review Report

**Reviewed:** 2026-07-05T00:00:00Z
**Depth:** standard
**Files Reviewed:** 1
**Status:** issues_found

## Summary

Reviewed `public/themes/default/pages/.htaccess`, the new directory-protection file added to
block direct HTTP access to theme page templates. The file itself is syntactically valid and
consistent with the project's established `<FilesMatch> + Deny from all` idiom (matches
`public/uploads/.htaccess`, `public/admin/.htaccess`, and root `public/.htaccess`). No secrets,
dangerous functions, or debug artifacts are present — the file is 5 lines of pure Apache config.

However, two issues undercut the actual security guarantee this file is meant to provide: (1) the
directive syntax used is Apache 2.2-era and has no fallback for Apache 2.4 environments that lack
`mod_access_compat` — relevant because this phase is explicitly about verifying behavior on
Altervista hosting; and (2) the protection is not applied uniformly across the themes the admin
theme-selector now exposes — the second theme (`oma-talli`) ships PHP templates directly under
`public/themes/oma-talli/` with no equivalent `.htaccess`, leaving them fully HTTP-reachable.

## Warnings

### WR-01: Legacy `Deny from all` syntax has no Apache 2.4 fallback, undermining the Altervista-verification goal of this phase

**File:** `public/themes/default/pages/.htaccess:3-5`
**Issue:** The directive `Deny from all` is provided by `mod_access` (Apache 2.2) / `mod_access_compat`
(Apache 2.4 compatibility shim). On a plain Apache 2.4 install without `mod_access_compat` loaded,
`Deny` is not a recognized directive at all, which causes Apache to reject the `.htaccess` file
with an "Invalid command 'Deny'" error — every request under `public/themes/default/pages/` (not
just `.php` requests, since `.htaccess` parsing happens per-directory) then returns a 500 Internal
Server Error instead of the intended clean 403. This is the exact same legacy idiom already used in
`public/uploads/.htaccess`, `public/admin/.htaccess` (via `Order Deny,Allow`), and root
`public/.htaccess`, so it is not a new pattern introduced by this file — but this phase is
specifically named "Altervista-verifiointi" (Altervista verification), and no evidence in the
plan/summary artifacts confirms `mod_access_compat` is actually enabled on the target Altervista
account. If it is not, the "protection" degrades from a clean deny to an unhandled server error,
and if Altervista's PHP-handler config additionally maps `.htaccess` syntax errors to a blank page
or generic error, the failure mode is silent (looks like it works because access is blocked either
way — but for the wrong reason, and any future move to a stricter host could turn the 500 into a
full outage of legitimate `require_once`-based rendering if the per-directory `.htaccess` is ever
parsed in a context where it also blocks legitimate access, e.g. if a future refactor accidentally
starts serving templates by URL).
**Fix:** Add the Apache 2.4-native form alongside the legacy one so the block degrades gracefully on
either version:
```apache
<FilesMatch "\.php$">
    <IfModule mod_access_compat.c>
        Deny from all
    </IfModule>
    <IfModule !mod_access_compat.c>
        Require all denied
    </IfModule>
</FilesMatch>
```
This same fix should be applied to the other `.htaccess` files project-wide, but that is outside
this file's diff — flagging here since this phase's explicit purpose is hosting-environment
verification.

### WR-02: Template-protection coverage does not extend to the `oma-talli` theme, which the admin theme selector can now activate

**File:** `public/themes/default/pages/.htaccess` (scope gap, related files: `public/themes/oma-talli/*.php`)
**Issue:** This phase adds admin theme-selection (THEME-10/THEME-11) and the plan/pattern docs
(`09-PATTERNS.md` line 20, tagged "*(future)*") explicitly acknowledge that the `pages/.htaccess`
protection was only implemented for the `default` theme, with other themes left as future work.
In the interim, the currently-shippable second theme, `oma-talli` (present in the working tree as
`public/themes/oma-talli/`, replacing the previously-committed `oma-talli.zip`), does not use a
`pages/` subdirectory at all — its templates (`hevonen.php`, `esittely.php`, `asukkaat.php`,
`henkilot.php`, `tarhaus.php`, `info.php`, etc.) sit directly under the theme root with no
`.htaccess` of any kind. Since the site's root `.htaccess` only blocks `^src/` and never rewrites
`themes/`, these files are directly reachable via a plain HTTP GET (e.g.
`/themes/oma-talli/hevonen.php?slug=x`), completely bypassing the app's router and
`resolveThemePath()`/`theme-page.php` controller flow that the `default` theme relies on for
context (e.g. `THEME_URL`). `oma-talli/hevonen.php` does defensively check
`defined('THEME_URL')` before using it and uses parameterized queries, so this is not an
immediately exploitable injection — but it is an inconsistency: the exact HTTP-access-control gap
this phase set out to close for one theme is left wide open for the other theme that the same
phase's admin selector now makes user-selectable in production.
**Fix:** Either (a) extend the same `<FilesMatch "\.php$"> Deny from all </FilesMatch>` idiom to a
new `public/themes/oma-talli/.htaccess` (targeting the theme root since there is no `pages/`
subfolder), or (b) if `oma-talli` is intentionally out of scope for this phase, record that
explicitly as a known gap/follow-up item in ROADMAP.md/STATE.md rather than leaving it implicit in
a "future" pattern-map annotation, so it isn't silently forgotten before the theme is exposed via
the admin selector.

## Info

### IN-01: Security-rationale comment is theme-specific but will be copy-pasted verbatim for future themes

**File:** `public/themes/default/pages/.htaccess:1-2`
**Issue:** The comment "Templatet odottavat kontrollerin (esim. theme-page.php) asettamia
muuttujia" ("Templates expect variables set by the controller, e.g. theme-page.php") accurately
describes the `default` theme's template contract, but is not true of every theme's templates (see
WR-02 — `oma-talli`'s templates are self-contained and query the DB directly rather than relying on
controller-set variables). Not a defect in this file as submitted, but worth noting so the comment
isn't blindly copied into a future `oma-talli/.htaccess` without adjustment.
**Fix:** When adding equivalent `.htaccess` files for other themes, tailor the rationale comment to
that theme's actual template contract instead of copying this one verbatim.

---

_Reviewed: 2026-07-05T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
