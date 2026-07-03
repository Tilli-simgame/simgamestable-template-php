# Phase 7: Oletusteman rakenne - Pattern Map

**Mapped:** 2026-07-02
**Files analyzed:** 11 (3 includes + 1 CSS + 7 page templates)
**Analogs found:** 11 / 11 (all are direct copy/split sources — this phase duplicates/refactors existing files, it does not build new functionality)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog (source) | Match Quality |
|-------------------|------|-----------|--------------------------|---------------|
| `public/themes/default/includes/header.php` | component (include) | request-response (render) | `public/src/includes/header.php` | exact (verbatim copy, D-03) |
| `public/themes/default/includes/footer.php` | component (include) | request-response (render) | `public/src/includes/footer.php` | exact (verbatim copy) |
| `public/themes/default/includes/nav.php` | component (include) | request-response (render) | `public/src/includes/nav.php` | exact (verbatim copy) |
| `public/themes/default/assets/css/style.css` | config/asset | file-I/O (static asset) | `public/assets/css/style.css` | exact (verbatim copy) |
| `public/themes/default/pages/index.php` | component (template) | request-response (HTML only, no data) | `public/pages/index.php` (split source) | exact — data logic stays, HTML+vars extracted |
| `public/themes/default/pages/hevoset.php` | component (template) | request-response | `public/pages/hevoset.php` | exact |
| `public/themes/default/pages/hevonen.php` | component (template) | request-response | `public/pages/hevonen.php` | exact |
| `public/themes/default/pages/kasvatus.php` | component (template) | request-response | `public/pages/kasvatus.php` | exact |
| `public/themes/default/pages/yhteystiedot.php` | component (template) | request-response | `public/pages/yhteystiedot.php` | exact |
| `public/themes/default/pages/ajankohtaista.php` | component (template) | request-response | `public/pages/ajankohtaista.php` | exact |
| `public/themes/default/pages/postaus.php` | component (template) | request-response | `public/pages/postaus.php` | exact |

Note: every "new" file in this phase is derived from an existing file already in the repo (copy-then-split, per CONTEXT D-01–D-07). There are no genuinely novel patterns to source externally — the analog IS the source of truth for exact structure. `public/pages/theme-page.php` (already `resolveThemePath()`-based generic controller) is explicitly out of scope per CONTEXT and was not used as an analog.

## Pattern Assignments

### `public/themes/default/includes/header.php` (component, verbatim copy)

**Source:** `public/src/includes/header.php` (56 lines) — read in full above.

**Copy instruction:** Byte-identical copy, including the WR-02 TODO comment block (lines 42-46) and the `SITE_URL`-based CSS link (line 47). Do NOT change to `THEME_URL` (D-05 explicitly forbids this in Phase 7).

Key structural points to preserve:
- Guard at top: `if (!defined('SITE_NAME')) { require_once __DIR__ . '/config.php'; }` (line 11-13) — note this path is relative to the *new* location; since `themes/default/includes/` is a different directory than `src/includes/`, this relative require must still resolve correctly. Preserve behavior, not necessarily the literal relative path, if directory depth differs — verify at copy time whether `config.php` needs a different relative offset.
- `$GLOBALS['_vt_settings_loaded']` caching pattern (lines 17-29) for `stable_name` / `color_theme` — copy verbatim.
- XSS sanitization of `$page_title` via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` (line 34).
- `require_once __DIR__ . '/nav.php';` (line 54) — after copy, this will resolve to the sibling `themes/default/includes/nav.php`, which is correct since nav.php is copied alongside.

### `public/themes/default/includes/footer.php` (component, verbatim copy)

**Source:** `public/src/includes/footer.php` (7 lines, trivial — full file):
```php
<footer>
  <div class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>.</p>
  </div>
</footer>
</body>
</html>
```
Copy verbatim, no path dependencies.

### `public/themes/default/includes/nav.php` (component, verbatim copy)

**Source:** `public/src/includes/nav.php` (18 lines, full file) — uses `$_SERVER['REQUEST_URI']` for active-link detection and `SITE_URL` constant for hrefs. No relative-path requires inside this file — safe to copy verbatim without adjustment.

### `public/themes/default/assets/css/style.css` (asset, verbatim copy)

**Source:** `public/assets/css/style.css` (1368 lines). Byte-identical copy per D-06. Not linked from any live template yet (only exists for Success Criteria checkbox — file presence + content match).

### `public/themes/default/pages/index.php` (template, split from `public/pages/index.php`)

**Source analog:** `public/pages/index.php` (126 lines, read in full above).

**Split boundary (D-01/D-02):** Everything from line 1 through the `require __DIR__ . '/../src/includes/header.php';` call (lines 1-40) is data-fetch logic (`$db`, `$horseCount`, `$foalCount`, `$latestPost`, plus the theme-override hook at lines 5-12) — this STAYS in `public/pages/index.php` unchanged. Everything from line 41 (the blank line after header include) through EOF (line 125, the footer include) is pure HTML/template — this is what gets copied into `themes/default/pages/index.php`.

**Variables the template consumes** (must be passed in by controller eventually, in Phase 8): `$horseCount`, `$foalCount`, `$thisYear`, `$latestPost` (nullable array with `title`, `slug`, `content`, `created_at`), and derived `$newsHref`, `$newsTitle`, `$newsExcerpt`, `$newsDate` (lines 69-81 — this derivation logic arguably belongs in the template since it's presentation formatting, not data-fetch; use judgement, D-01 discretion note applies).

**Header/footer include pattern to preserve inside template:**
```php
require __DIR__ . '/../src/includes/header.php';
...
<?php require __DIR__ . '/../src/includes/footer.php'; ?>
```
Since Phase 7 does NOT wire `resolveThemePath()` yet (D-04), the copied template's header/footer requires can either mirror the same relative-include convention pointing at `public/src/includes/` (simplest, since these are not yet functionally invoked anyway) — Claude's discretion per CONTEXT, but keep consistent across all 7 templates.

**Escaping convention to replicate:** All dynamic output uses `e()` helper (from `public/src/includes/helpers.php`, line 9) except pre-escaped concatenated strings which are still passed through `e()` at the single output point (see comment at lines 70-72 of source: "escape at the single output point to avoid double-encoding").

### `public/themes/default/pages/hevoset.php` (template, split from `public/pages/hevoset.php`)

**Source analog:** `public/pages/hevoset.php` (95 lines, read in full above).

**Split boundary:** Lines 1-24 (`require db.php`, `$page_title`, `$db`, prepared statement, `$horses = $stmt->fetchAll()`) stay in the controller. Line 26 `require header.php` through line 28 (`$genderFi` array — this is a presentation lookup table, could go either side; keep in template since it's used only for display formatting) through EOF (line 95, footer include) is template.

**Core pattern:** foreach loop over `$horses` rows rendering `.horse-list-card` divs; conditional image display (`$horse['filename']`); `e()` escaping on every field; helper calls `horseUrl($horse)` and `calculateAge($horse['birth_date'])` from `helpers.php` — these helper functions must remain available (they're loaded via `db.php`/`helpers.php` require chain, unaffected by the copy).

**Variables template consumes:** `$horses` (array of rows with `id, name, slug, gender, birth_date, breed_name, discipline_names, filename`), `$genderFi` (Finnish gender label map — currently defined post-header, template-local).

### `public/themes/default/pages/hevonen.php` (template, split from `public/pages/hevonen.php`)

**Source analog:** `public/pages/hevonen.php` (614 lines, read in full above) — the most complex of the 7 pages.

**Split boundary:** Lines 1-143 are entirely data-fetch (theme-override hook, horse lookup by slug/id, competitions, showrecords, photos, foals, posts, pedigree via `getHorsePedigree($id)`) plus two early-exit 404 branches (lines 58-65, 69-76) that each do their own mini header/footer render — these 404 branches must stay in the controller (they render before the template split point). Lines 145-614 (from `require header.php` onward, including the `pedigreeHorseLink()` and `pedigreeCell()` helper function definitions at lines 150-167 and 339-343) form the template. Note: PHP does not allow duplicate function declarations, so if these helper functions are needed only for rendering, keep them defined within the template file itself (they already are, post-header, in the source) — this is consistent with keeping them in the template copy.

**Variables template consumes:** `$horse` (full row incl. joined breed/color/contact fields), `$competitions`, `$showrecords`, `$photos`, `$foals`, `$horsePosts`, `$pedigree`, `$id`, `$genderFi`, `$heroPhoto`, `$heroStyle`.

**Notable pattern — inline anonymous helper functions:** `pedigreeHorseLink()` (lines 150-167) and `pedigreeCell()` (lines 339-343) are defined directly in the page file, not in `helpers.php`. Preserve this same in-template placement in the copy for consistency; do not move them to shared helpers unless doing so elsewhere too.

### `public/themes/default/pages/kasvatus.php` (template)

**Source analog:** `public/pages/kasvatus.php` (145 lines, not fully read here but structurally consistent with hevoset.php/index.php pattern — same `require db.php` → data fetch → `require header.php` → HTML → `require footer.php` structure). Apply the same split boundary methodology: everything before `header.php` require = data logic stays; everything after = template.

### `public/themes/default/pages/yhteystiedot.php` (template, split from `public/pages/yhteystiedot.php`)

**Source analog:** `public/pages/yhteystiedot.php` (91 lines, read in full above).

**Split boundary:** Lines 1-19 (settings fetch via `$db->query('SELECT setting_key, setting_value FROM settings')`, building `$s` assoc array, deriving `$stable_name`, `$nickname`, `$vrl_id`, `$email`, `$forum_url`) plus `$page_title` (line 18) stay in controller. Line 20 (`require header.php`) through EOF (line 90, footer) is template.

**Variables template consumes:** `$stable_name`, `$nickname`, `$vrl_id`, `$email`, `$forum_url` — all plain strings, each conditionally rendered with `!== ''` checks and `e()` escaping. This is the simplest of the 7 templates — good reference for the minimal-variable-passing pattern.

### `public/themes/default/pages/ajankohtaista.php` (template)

**Source analog:** `public/pages/ajankohtaista.php` (135 lines). Same split methodology — blog listing query stays in controller (data-fetch of `posts` table), listing markup (likely a foreach over posts with title/excerpt/date, similar card pattern to `hevoset.php`) moves to template.

### `public/themes/default/pages/postaus.php` (template)

**Source analog:** `public/pages/postaus.php` (151 lines). Single-post lookup by slug (mirrors the `hevonen.php` slug-lookup + 404-branch pattern at a smaller scale) stays in controller; post detail markup (title, content, related horses via `post_horses` join, per `hevonen.php` line 131-140's `$horsePosts` reverse-relation) moves to template.

---

## Shared Patterns

### XSS escaping — `e()` helper
**Source:** `public/src/includes/helpers.php` line 9, `function e(string $value): string { ... }`
**Apply to:** Every template file, on every dynamic output. This is non-negotiable per CONTEXT line 74 ("XSS-suojaus... säilytettävä identtisenä kopioissa").

### Header/footer include pattern
**Source:** every page in `public/pages/*.php` — consistent structure:
```php
require __DIR__ . '/../src/includes/db.php';   // (and theme.php if slug-lookup page)
// ...data fetch...
$page_title = '...';
require __DIR__ . '/../src/includes/header.php';
?>
<!-- HTML body -->
<?php require __DIR__ . '/../src/includes/footer.php'; ?>
```
**Apply to:** All 7 templates. `$page_title` must be set before the header include — this variable crosses the data/template boundary, so it should be one of the variables the (future, Phase 8) controller passes to the template, or set at the very top of the template if treated as static per-page metadata.

### 404 / not-found pattern
**Source:** `public/pages/hevonen.php` lines 58-65 and 69-76:
```php
http_response_code(404);
$page_title = 'Hevosta ei löydy';
require __DIR__ . '/../src/includes/header.php';
echo '<main><p>Hevosta ei löydy tai se on poistettu.</p></main>';
require __DIR__ . '/../src/includes/footer.php';
exit;
```
**Apply to:** `postaus.php` template's controller counterpart (single-item lookup by slug follows the same shape) — this logic stays in the controller side, not the template, since it exits before normal data assembly completes.

### Theme-override hook (do not modify)
**Source:** `public/pages/index.php` lines 5-12 and `public/pages/hevonen.php` lines 5-11, both using `THEME_PATH`/`THEMES_ROOT` constants from `public/src/includes/theme.php` (`resolveThemePath()` defined at lines 92-117 of that file).
**Apply to:** N/A for Phase 7 template copies — this logic lives only in the current `public/pages/*.php` controllers and is explicitly excluded from modification (CONTEXT line 75). Do not replicate this hook inside the new `themes/default/pages/*.php` templates; it's a controller-level concern reserved for Phase 8's `resolveThemePath()` wiring.

## No Analog Found

None — all 11 target files have a direct 1:1 source file in the current codebase (this phase is a structural copy/split operation, not new-feature development).

## Metadata

**Analog search scope:** `public/src/includes/`, `public/pages/`, `public/assets/css/`, `public/themes/` (for theme.php contract only)
**Files scanned:** `header.php`, `footer.php`, `nav.php`, `theme.php`, `index.php` (root + pages), `hevoset.php`, `hevonen.php`, `yhteystiedot.php`, `helpers.php`, plus line counts for `kasvatus.php`, `ajankohtaista.php`, `postaus.php`, `style.css`
**Pattern extraction date:** 2026-07-02
