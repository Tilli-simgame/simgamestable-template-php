---
phase: 07-oletusteman-rakenne
reviewed: 2026-07-03T00:00:00Z
depth: standard
files_reviewed: 11
files_reviewed_list:
  - public/themes/default/includes/header.php
  - public/themes/default/includes/footer.php
  - public/themes/default/includes/nav.php
  - public/themes/default/assets/css/style.css
  - public/themes/default/pages/index.php
  - public/themes/default/pages/hevoset.php
  - public/themes/default/pages/kasvatus.php
  - public/themes/default/pages/yhteystiedot.php
  - public/themes/default/pages/hevonen.php
  - public/themes/default/pages/ajankohtaista.php
  - public/themes/default/pages/postaus.php
findings:
  critical: 1
  warning: 4
  info: 1
  total: 6
status: issues_found
---

# Phase 7: Code Review Report

**Reviewed:** 2026-07-03
**Depth:** standard
**Files Reviewed:** 11
**Status:** issues_found

## Summary

Reviewed the Phase 7 "oletusteman-rakenne" default-theme extraction: the shared includes (`header.php`, `footer.php`, `nav.php`), the theme stylesheet, and the seven page templates. I byte-diffed every template against its originating controller in `public/pages/*.php` (after normalizing CRLF/LF so line-ending noise wouldn't hide real diffs) and confirmed the extraction claim made in the phase summary: **no SQL/`getDB()` calls were introduced**, all `e()`/`htmlspecialchars()` escaping present in the source controllers is preserved verbatim, and the only intentional textual deviations are the `require __DIR__ . '/../includes/...'` paths (correctly updated for the new `themes/default/` location) and the removal of the data-fetching code that stays in the controllers. `header.php`, `footer.php`, `nav.php`, and `style.css` are exact byte-for-byte copies of their `public/src/includes/` / `public/assets/css/` counterparts.

That said, one genuine XSS defect exists in the copied `header.php` (not introduced by this phase, but present in the reviewed file and worth fixing here since this copy is now the permanent theme artifact going forward), plus several structural/consistency issues in `nav.php` and the theme-override wiring that are relevant to the "path/include errors relative to the new theme directory structure" concern called out for this review.

## Critical Issues

### CR-01: Unescaped fallback value in `$page_title` used directly inside `<title>` — stored XSS

**File:** `public/themes/default/includes/header.php:33-34,41`
**Issue:** The comment says "XSS-suojaus: sanitoi `$page_title`", but only the `isset($page_title)` branch is escaped. The `else` branch assigns the **raw, unescaped** `$site_display_name` (which itself comes straight from the `settings.stable_name` DB column, `$GLOBALS['stable_name']`, see lines 17-31) directly to `$page_title`:

```php
// XSS-suojaus: sanitoi $page_title
$page_title = isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') : $site_display_name;
```

This value is then echoed with no further escaping:

```php
<title><?= $page_title ?> — <?= htmlspecialchars($site_display_name, ENT_QUOTES, 'UTF-8') ?></title>
```

If any page that includes this header fails to set `$page_title` before the include (the documented calling contract at the top of the file), and the `stable_name` setting contains `</title><script>...</script>`, the browser's raw-text tokenizer for `<title>` will treat the embedded `</title>` as the real closing tag and execute the following markup as HTML/script — a stored XSS reachable by any visitor loading a page in that state, seeded by an admin-configurable setting. Every other place `$site_display_name` is echoed in this same file (lines 41's second interpolation, 52) correctly wraps it in `htmlspecialchars()`; only this one assignment misses it.

**Fix:**
```php
$page_title = isset($page_title)
    ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8')
    : htmlspecialchars($site_display_name, ENT_QUOTES, 'UTF-8');
```

## Warnings

### WR-01: `nav.php` active-link detection is inconsistent and effectively dead for direct/redirected `.php` URLs

**File:** `public/themes/default/includes/nav.php:11-15`
**Issue:** Four of the five nav checks compare `$uri` for strict equality against the *pretty* route only (no `.php` suffix), e.g.:
```php
<li><a href="<?= SITE_URL ?>/"<?= ($uri === '' || $uri === '/pages/index') ? ' class="active"' : '' ?>>Etusivu</a></li>
<li><a href="<?= SITE_URL ?>/hevoset"<?= ($uri === '/hevoset' || $uri === '/pages/hevoset') ? ' class="active"' : '' ?>>Hevoset</a></li>
```
`public/index.php` (site root) issues a `302` redirect to `SITE_URL . '/pages/index.php'` — so the real `REQUEST_URI` a browser ever sees for the homepage is `/pages/index.php`, which matches **neither** `''` nor `/pages/index`. The "Etusivu" nav item can therefore never render as active in real usage. The same applies to any direct visit to `/pages/hevoset.php`, `/pages/kasvatus.php`, `/pages/yhteystiedot.php` — none of the four affected checks account for the `.php` extension, so the second alternative in each condition is dead code. Only the "Ajankohtaista" check uses `strpos($uri, '/pages/ajankohtaista') === 0` (prefix match), which does correctly match `/pages/ajankohtaista.php`, making the inconsistency clearly unintentional rather than a deliberate design choice.

**Fix:** Use prefix matching consistently, e.g.:
```php
<li><a href="<?= SITE_URL ?>/"<?= ($uri === '' || $uri === '/pages/index' || $uri === '/pages/index.php') ? ' class="active"' : '' ?>>Etusivu</a></li>
<li><a href="<?= SITE_URL ?>/hevoset"<?= (str_starts_with($uri, '/hevoset') || str_starts_with($uri, '/pages/hevoset')) ? ' class="active"' : '' ?>>Hevoset</a></li>
```
(and equivalently for kasvatus/yhteystiedot).

### WR-02: Stale docblock in `header.php` still references the pre-extraction include path

**File:** `public/themes/default/includes/header.php:5-7`
**Issue:** The usage docblock says:
```
 * Vaatii ennen include:a:
 *   $page_title = 'Sivun otsikko';
 *   require_once __DIR__ . '/../src/includes/header.php';
```
This is a copy-paste artifact from the original `public/src/includes/header.php` — every theme page template that actually includes this file uses `require __DIR__ . '/../includes/header.php'` (theme-relative), never `/../src/includes/header.php`. A future contributor following this docblock literally in a new theme page would write a broken include path.
**Fix:** Update the docblock to reflect the theme-relative path actually used elsewhere in this codebase:
```
 *   require __DIR__ . '/../includes/header.php';
```

### WR-03: Unguarded global function declarations in `hevonen.php` theme template

**File:** `public/themes/default/pages/hevonen.php:7,196`
**Issue:** `function pedigreeHorseLink(array $h): string { ... }` (line 7) and `function pedigreeCell(?array $h, int $row): string { ... }` (line 196) are declared unconditionally at global scope with no `function_exists()` guard. Today this is safe only because `public/pages/hevonen.php` `exit;`s immediately after `require`-ing any resolved theme override (lines 5-11 of the controller) before reaching its own copy of the same function names — but that safety is incidental to the current controller flow, not enforced by this file. Any future Phase 8 dispatch path that `require`s this template more than once per request, or alongside another file that defines the same function names (a custom theme's own `hevonen.php`, or a shared helpers file), will hit a fatal `Cannot redeclare function pedigreeHorseLink()` error.
**Fix:** Guard the declarations, e.g. `if (!function_exists('pedigreeHorseLink')) { function pedigreeHorseLink(...) {...} }`, or move these into a namespaced/shared theme-helpers file that is `require_once`'d.

### WR-04: New `pages/` subdirectory convention is incompatible with the pre-existing ad-hoc theme-override checks

**File:** `public/themes/default/pages/index.php`, `public/themes/default/pages/hevonen.php` (directory placement)
**Issue:** This phase places the default theme's overridable pages under `themes/default/pages/*.php`. However, the *existing* ad-hoc override checks already live in the controllers look for theme files by bare filename directly under the theme root, not under a `pages/` subfolder:
- `public/pages/index.php:6`: `realpath(THEME_PATH . 'index.php')`
- `public/pages/hevonen.php:6`: `resolveThemePath('hevonen.php')` (which, per `public/src/includes/theme.php:92-117`, resolves both the active theme's `THEME_PATH . 'hevonen.php'` and the default-theme fallback `THEMES_ROOT . 'default/hevonen.php'` — neither includes a `pages/` prefix)

Because of this, these two legacy checks can never find `public/themes/default/pages/index.php` or `public/themes/default/pages/hevonen.php` (the files land one directory level deeper than what's being looked up), and would equally never find a custom theme's override if that theme also adopts the new `pages/` convention (as `public/themes/oma-talli/` does *not* — it keeps `hevonen.php`/`index.php` flat at its theme root, matching the *old* convention). This is not an active production bug today (only the `default` theme currently exists in the new shape, and the `!str_starts_with(THEME_PATH, THEMES_ROOT.'default'...)` guard already excludes `default` from triggering these two checks), but it is a latent structural inconsistency that will silently make index/hevonen theme overrides unreachable via this path once Phase 8 wiring assumes the `pages/` convention uniformly. Worth reconciling before Phase 8 (either update the two legacy checks to look under `pages/`, or route all override resolution exclusively through the manifest-driven `theme-page.php` + `theme.json` mechanism and remove the ad-hoc checks).
**Fix:** Align the legacy lookups with the new convention, e.g. `resolveThemePath('pages/hevonen.php')`, or delete the ad-hoc index/hevonen override blocks once Phase 8's dispatcher supersedes them.

## Info

### IN-01: `$genderFi` lookup table duplicated across three theme templates

**File:** `public/themes/default/pages/hevoset.php:6`, `public/themes/default/pages/kasvatus.php:6`, `public/themes/default/pages/hevonen.php:4`
**Issue:** Each template redefines its own copy of the Finnish gender label map (`hevoset.php`/`hevonen.php` include `'käkky' => 'Käkky'`; `kasvatus.php` instead includes `'tuntematon' => ''`), inherited unchanged from the original controllers. This is pre-existing duplication (not introduced by this phase), but now that these three copies live permanently side-by-side in the theme folder, any future update to gender labels risks the three copies drifting out of sync.
**Fix:** Consider extracting a single canonical `$GENDER_FI` map (e.g. into a small theme-helpers include) that all page templates pull from.

---

_Reviewed: 2026-07-03_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
