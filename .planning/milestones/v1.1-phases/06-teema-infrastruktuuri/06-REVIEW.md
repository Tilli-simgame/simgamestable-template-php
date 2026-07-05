---
phase: 06-teema-infrastruktuuri
reviewed: 2026-06-22T00:00:00Z
depth: standard
files_reviewed: 4
files_reviewed_list:
  - database/migrate_theme.sql
  - public/themes/default/theme.json
  - public/src/includes/theme.php
  - public/pages/index.php
findings:
  critical: 2
  warning: 4
  info: 3
  total: 9
status: issues_found
---

# Phase 06: Code Review Report

**Reviewed:** 2026-06-22
**Depth:** standard
**Files Reviewed:** 4
**Status:** issues_found

## Summary

Four files were reviewed: one SQL migration, one JSON theme manifest, the PHP theme-resolver shim, and the public index page that loads the shim. The migration and JSON manifest are trivial and largely correct. The substantive issues are in `theme.php` and `index.php`.

The most serious problems are: (1) `theme.php` does not wrap the database calls that load the active theme in try/catch, so any database failure at theme-load time produces an unhandled exception before error handlers are in place; (2) `index.php` assembles the news-card `href` with only partial HTML-escaping, leaving a latent XSS path if a slug ever contains HTML-special characters. There are also several correctness and quality issues in how the theme constants are used (and not used) across the page.

---

## Critical Issues

### CR-01: Unhandled PDOException in theme.php database query

**File:** `public/src/includes/theme.php:13-17`
**Issue:** `getDB()` uses `PDO::ERRMODE_EXCEPTION`. Both `$db->prepare()` and `$stmt->execute()` can throw `PDOException`. These calls are not wrapped in try/catch. Because `theme.php` is loaded very early in the request (before any application-level error handler runs), an uncaught exception here produces a raw PHP fatal error or stack trace visible to the user, and the page hard-stops rather than falling back to the default theme. The Elvis operator on line 20 only covers the case where the query *succeeds* but returns no rows — it provides no protection against a connection/query failure.

**Fix:**
```php
// theme.php lines 13-20 — wrap in try/catch
try {
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1'
    );
    $stmt->execute([':k' => 'active_theme']);
    $rawTheme = $stmt->fetchColumn() ?: 'default';
} catch (Throwable $e) {
    error_log('theme.php: aktiivisen teeman haku epäonnistui: ' . $e->getMessage());
    $rawTheme = 'default';
}
```

---

### CR-02: Partial HTML-escaping of assembled href produces latent XSS

**File:** `public/pages/index.php:61-63`
**Issue:** `$newsHref` is assembled by applying `e()` only to `SITE_URL`, then appending a raw `rawurlencode($latestPost['slug'])`. The fully assembled string is then output unescaped into an HTML attribute at line 70 (`href="<?= $newsHref ?>"`). `rawurlencode()` does NOT encode `"` (ASCII 34) or `>` — a slug stored in the database that contains `"` would break out of the attribute and allow script injection. While slugs are likely validated upstream, that constraint is invisible here and fragile.

```php
// Line 70 — $newsHref is NOT passed through e() before output
<a class="overlay-card overlay-card--news" href="<?= $newsHref ?>">
```

**Fix:** Either escape the full assembled URL at output time, or restrict assembly to already-safe components:
```php
// Option A: escape at output
<a class="overlay-card overlay-card--news" href="<?= e($newsHref) ?>">

// Option B: build $newsHref without calling e() on SITE_URL, and escape at the single output point
$newsHref = $latestPost
    ? SITE_URL . '/pages/ajankohtaista/' . rawurlencode($latestPost['slug'])
    : SITE_URL . '/pages/ajankohtaista.php';
// ... then output as: href="<?= e($newsHref) ?>"
```
Note: applying `e()` twice to `SITE_URL` (as Option A does) double-encodes any `&` in the URL — Option B is cleaner.

---

## Warnings

### WR-01: str_starts_with prefix check is incorrect when realpath() fallback is active

**File:** `public/src/includes/theme.php:72-76`
**Issue:** When `realpath(__DIR__ . '/../../themes')` returns `false` (directory not yet created), `THEMES_ROOT` and `THEME_PATH` are built from an unresolved path containing `../../` segments. On line 72, `realpath(THEME_PATH . $subPath)` resolves the absolute canonical path, but the prefix guard on line 75 compares it against `THEME_PATH` which still contains unresolved segments. Since a resolved path (e.g., `/var/www/public/themes/default/`) will never start with an unresolved string (e.g., `/var/www/public/src/includes/../../themes/default/`), the prefix check always fails. `resolveThemePath()` silently returns `false` for every call in this fallback state instead of raising an error or logging a warning. Developers will see blank/missing theme files with no diagnostic.

**Fix:** Add error logging in the fallback branch and document clearly that `resolveThemePath()` will not function until the directory exists:
```php
if ($resolvedThemesRoot === false) {
    error_log('theme.php: themes/-hakemistoa ei löydy: ' . __DIR__ . '/../../themes');
    $resolvedThemesRoot = __DIR__ . '/../../themes'; // resolveThemePath() returns false until dir exists
}
```
Additionally, consider normalising the path with `realpath()` at call time inside `resolveThemePath()` rather than relying on the constant being canonical.

---

### WR-02: THEME_PATH / THEME_URL constants are defined but never consumed on the page

**File:** `public/pages/index.php:3` / `public/src/includes/theme.php:40-42`
**Issue:** `index.php` requires `theme.php`, which unconditionally fires a prepared-statement database query and defines three constants (`THEMES_ROOT`, `THEME_PATH`, `THEME_URL`). None of these constants, and `resolveThemePath()`, are referenced anywhere in `index.php` or in the `header.php` it includes. The page still loads its stylesheet from the hardcoded `/assets/css/style.css` path (header.php line 43) with no reference to `THEME_URL`. The theme infrastructure performs a real database round-trip on every page load but has no effect on any rendered output. This is not purely a quality smell — it means the feature is non-functional despite appearing wired up.

**Fix:** Either connect the defined constants to the stylesheet/asset loading in `header.php`, or defer the `require_once theme.php` call to pages that actually use theme resolution. Remove the dead database call until the integration is complete.

---

### WR-03: $newsDate output is unescaped

**File:** `public/pages/index.php:76`
**Issue:** `<?= $newsDate ?>` outputs the return value of `formatDate()` with no escaping. Currently `formatDate()` returns either `'—'` or a `date('d.m.Y', ...)` string, so the output is safe. However, the absence of escaping means any future change to `formatDate()` that returns database-derived content will introduce an XSS vector without an obvious call-site signal.

**Fix:**
```php
<span class="card-date"><?= e($newsDate) ?></span>
```

---

### WR-04: $horseCount and $foalCount output directly into JavaScript without escaping

**File:** `public/pages/index.php:108-109`
**Issue:** Integer values are cast with `(int)` at fetch time (lines 11, 17), so direct interpolation into the `<script>` block is safe today. However, outputting PHP variables into JavaScript inline without `json_encode()` is a fragile pattern — if the cast is ever removed or the values come from a different source, this becomes a script-injection point. `json_encode()` is the correct tool for PHP → JS value embedding.

**Fix:**
```php
animateCount(document.getElementById('stat-hevoset'), <?= json_encode($horseCount) ?>, 800);
animateCount(document.getElementById('stat-varsat'),  <?= json_encode($foalCount) ?>,  600);
```

---

## Info

### IN-01: theme.json is functionally inert — nothing reads it

**File:** `public/themes/default/theme.json:1-4`
**Issue:** `theme.json` contains only `name` and `version`. The theme shim (`theme.php`) never reads or validates this file; it only uses the directory name. There is no defined schema for theme configuration (CSS variables, asset paths, feature flags). The file serves no runtime purpose. If the intent is to have themes declare their capabilities, the schema and reader should be implemented together.

**Fix:** Either define and document what `theme.json` must contain and implement a reader, or remove the file and add it back when the schema is defined. Shipping an inert file creates false confidence that theme metadata is being validated.

---

### IN-02: Lorem ipsum placeholder content in production-facing file

**File:** `public/pages/index.php:87-91`
**Issue:** Three paragraphs of Lorem ipsum dummy text are hardcoded directly in the production page with no TODO comment or template marker indicating they should be replaced. This will reach end users on any deployment.

**Fix:** Replace with a meaningful placeholder or load the content from the `settings` table (a `stable_description` key for example). At minimum, add a clearly visible `<!-- TODO: replace lorem ipsum -->` marker.

---

### IN-03: Magic numbers in JavaScript animation calls

**File:** `public/pages/index.php:107-110`
**Issue:** The `setTimeout` delay (`150`) and animation durations (`800`, `600`) are magic numbers with no explanation of their origin or relationship to each other.

**Fix:** Extract to named constants or add inline comments:
```js
setTimeout(() => {
  animateCount(document.getElementById('stat-hevoset'), <?= json_encode($horseCount) ?>, 800); // 800ms animation
  animateCount(document.getElementById('stat-varsat'),  <?= json_encode($foalCount) ?>,  600); // 600ms animation
}, 150); // 150ms delay — wait for CSS transitions to settle
```

---

_Reviewed: 2026-06-22_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
