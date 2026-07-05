# Phase 8: Sivukontrollerien migraatio - Pattern Map

**Mapped:** 2026-07-04
**Files analyzed:** 7 (public/pages/index.php, hevoset.php, hevonen.php, kasvatus.php, yhteystiedot.php, ajankohtaista.php, postaus.php)
**Analogs found:** 7 / 7 (all analogs are sibling files within the same 7; no external analog needed)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog (Malli B hook) | Paired Template (Malli A target) | Match Quality |
|---|---|---|---|---|---|
| `public/pages/index.php` | controller | CRUD (read-only) | already has Malli B (self) | `public/themes/default/pages/index.php` | exact — has both mechanisms partially, needs Malli A delegation added |
| `public/pages/hevonen.php` | controller | CRUD (read-only, multi-join) | already has Malli B (self) | `public/themes/default/pages/hevonen.php` | exact — Malli B present, needs inline-HTML removal + Malli A delegation |
| `public/pages/hevoset.php` | controller | CRUD (read-only, list) | `hevonen.php` lines 4-11 (Malli B pattern) | `public/themes/default/pages/hevoset.php` | role-match — needs both mechanisms added |
| `public/pages/kasvatus.php` | controller | CRUD (read-only, list) | `hevonen.php` lines 4-11 | `public/themes/default/pages/kasvatus.php` | role-match — needs both mechanisms added |
| `public/pages/yhteystiedot.php` | controller | CRUD (read-only, key-value settings) | `hevonen.php` lines 4-11 | `public/themes/default/pages/yhteystiedot.php` | role-match — needs both mechanisms added |
| `public/pages/ajankohtaista.php` | controller | CRUD (read-only, filtered list) | `hevonen.php` lines 4-11 | `public/themes/default/pages/ajankohtaista.php` | role-match — needs both mechanisms added |
| `public/pages/postaus.php` | controller | CRUD (read-only, single record + 404) | `hevonen.php` lines 4-11 AND lines 58-65/69-76 (404 handling) | `public/themes/default/pages/postaus.php` | role-match — needs both mechanisms added |

**Supporting analogs (read-only reference, not modified):**
- `public/src/includes/theme.php` — `resolveThemePath()` implementation, `THEME_PATH`/`THEMES_ROOT` constants
- `public/pages/theme-page.php` — canonical `http_response_code(404); exit;` convention for missing resources

## Pattern Assignments

### Malli B hook — canonical source, copy identically into all 5 controllers lacking it

**Source A:** `public/pages/index.php` lines 5-12 (root path, index-specific variant using `realpath()` directly):
```php
// Jos aktiivisella teemalla on oma etusivu, käytetään sitä
$_vt_themeFile = realpath(THEME_PATH . 'index.php');
if ($_vt_themeFile !== false
    && str_starts_with($_vt_themeFile, THEME_PATH)
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}
```

**Source B (PREFERRED CANONICAL — use this form, it already uses `resolveThemePath()` and is simpler):** `public/pages/hevonen.php` lines 5-11:
```php
// Jos aktiivisella teemalla on oma hevosen profiilisivu, käytetään sitä
$_vt_themeFile = resolveThemePath('hevonen.php');
if ($_vt_themeFile !== false
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}
```

**Rationale for using Source B as the template (per CONTEXT.md D-02):** `resolveThemePath()` already performs the `str_starts_with($real, THEME_PATH)` check internally (see `theme.php` lines 105-107), so the caller only needs the `!== false` check plus the default-theme exclusion check. This is the exact structure to replicate — for each of the 5 remaining controllers, substitute the filename and Finnish comment:

```php
// Jos aktiivisella teemalla on oma {sivun kuvaus}, käytetään sitä
$_vt_themeFile = resolveThemePath('{controllerin_tiedostonimi}.php');
if ($_vt_themeFile !== false
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}
```

Concrete substitutions needed:
| Controller | Root-override filename param | Suggested comment |
|---|---|---|
| `hevoset.php` | `resolveThemePath('hevoset.php')` | "Jos aktiivisella teemalla on oma hevoslistaussivu, käytetään sitä" |
| `kasvatus.php` | `resolveThemePath('kasvatus.php')` | "Jos aktiivisella teemalla on oma kasvatussivu, käytetään sitä" |
| `yhteystiedot.php` | `resolveThemePath('yhteystiedot.php')` | "Jos aktiivisella teemalla on oma yhteystietosivu, käytetään sitä" |
| `ajankohtaista.php` | `resolveThemePath('ajankohtaista.php')` | "Jos aktiivisella teemalla on oma ajankohtaista-sivu, käytetään sitä" |
| `postaus.php` | `resolveThemePath('postaus.php')` | "Jos aktiivisella teemalla on oma postaussivu, käytetään sitä" |

Placement: immediately after the two `require_once` lines (`db.php`, `theme.php`), before any data-fetching logic — matching `hevonen.php`'s position (lines 1-11).

**IMPORTANT dependency note:** These 5 controllers currently do NOT `require_once __DIR__ . '/../src/includes/theme.php';` — only `db.php` is required. Adding the Malli B hook requires ALSO adding the `theme.php` require line, matching `index.php`/`hevonen.php` line 3:
```php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/../src/includes/theme.php';
```

---

### Malli A delegation — new trailing line, added to bottom of all 7 controllers

Replace the current inline-HTML block (`require header.php` ... HTML ... `require footer.php`) with a single delegation line:

```php
require resolveThemePath('pages/{sivu}.php');
```

Concrete per-file target:
| Controller | Delegation line to add |
|---|---|
| `index.php` | `require resolveThemePath('pages/index.php');` |
| `hevonen.php` | `require resolveThemePath('pages/hevonen.php');` |
| `hevoset.php` | `require resolveThemePath('pages/hevoset.php');` |
| `kasvatus.php` | `require resolveThemePath('pages/kasvatus.php');` |
| `yhteystiedot.php` | `require resolveThemePath('pages/yhteystiedot.php');` |
| `ajankohtaista.php` | `require resolveThemePath('pages/ajankohtaista.php');` |
| `postaus.php` | `require resolveThemePath('pages/postaus.php');` |

**404 handling for `resolveThemePath()` returning false** (Claude's Discretion per CONTEXT.md, unify with `theme-page.php` convention, `theme-page.php` lines 29-32):
```php
$themeFile = resolveThemePath($pageEntry['file']);
if ($themeFile === false) {
    http_response_code(404);
    exit;
}
require $themeFile;
```
Apply the same guard before the final `require` in each of the 7 controllers:
```php
$_themePage = resolveThemePath('pages/{sivu}.php');
if ($_themePage === false) {
    http_response_code(404);
    exit;
}
require $_themePage;
```

---

### Per-controller: inline-HTML to DELETE and variable contract to KEEP/produce

#### `index.php` (data-only, no $_GET params)
**Delete** (index.php lines 14 is kept as data logic marker start; actual HTML/footer to delete: lines 40-125, i.e. everything from `require header.php` through `require footer.php`).
**Keep as data-only logic** (lines 14-38): `$page_title` — actually REMOVE `$page_title` assignment too since the template sets it (`themes/default/pages/index.php` line 2 sets `$page_title = 'Etusivu';` itself — controller does NOT need to set it, template is self-sufficient. Compare current controller line 14 `$page_title = 'Etusivu';` — redundant, delete along with header/footer requires).
**Variable contract required by template** (`themes/default/pages/index.php`): `$horseCount`, `$foalCount`, `$thisYear`, `$latestPost` (array|null with keys `title`, `slug`, `content`, `created_at`).
**Controller keeps:** `$db = getDB();`, horse count query, foal count query, `$latestPost` try/catch query (lines 16-38 as-is, minus `$page_title` line).

#### `hevonen.php` (data-only, `$_GET['slug']` or `$_GET['id']`)
**Delete:** lines 145 (`require header.php`) through 614 (`require footer.php`) — entire HTML body INCLUDING the `$genderFi` array (line 147), `pedigreeHorseLink()` function (lines 150-167), `pedigreeCell()` function (lines 339-343), `$heroPhoto`/`$heroStyle` (lines 170-173) — all of these are already duplicated in `themes/default/pages/hevonen.php` (confirmed: `$genderFi` line 4, `pedigreeHorseLink()` lines 6-20 present in template). Per CONTEXT.md D-05, these must be REMOVED from the controller, not kept as dead code.
**404 special case:** current controller has TWO inline 404 branches (lines 58-65 for missing param, lines 69-76 for not-found) that each do `require header.php` + inline HTML + `require footer.php` + `exit`. These must be replaced with the unified `http_response_code(404); exit;` pattern (per `theme-page.php` convention) — no header/footer render needed since it's a controller-level short-circuit, not a themed page.
**Variable contract required by template** (`themes/default/pages/hevonen.php`): `$horse` (assoc array with many keys: name, call_name, breed_name, gender, birth_date, aging_system, discipline_names, level_ko/re/ke, description, height_cm, color_name, genes, vh_id, pkk_id, pedigree_notes, owner/breeder/importer_* contact fields), `$id`, `$competitions`, `$showrecords`, `$photos`, `$foals`, `$horsePosts`, `$pedigree` (nested sire/dam struct).
**Controller keeps:** lines 13-143 (all DB queries) unchanged, minus the now-template-owned helper functions/arrays.

#### `hevoset.php`
**Delete:** lines 26 (`require header.php`) through 95 (`require footer.php`), including `$genderFi` array (line 28, already in template line 6) and `$page_title` (line 4, template sets its own at line 2).
**Variable contract required by template:** `$horses` (array of assoc arrays: id, name, slug, gender, birth_date, breed_name, discipline_names, filename).
**Controller keeps:** lines 1-2 (`require_once db.php` — theme.php added), lines 6-24 (DB query + fetchAll).

#### `kasvatus.php`
**Delete:** lines 22 (`require header.php`) through 146 (`require footer.php`), including `$genderFi` (line 24, template line 6) and `$page_title` (line 4, template line 2).
**Variable contract required by template:** `$allFoals`, `$expected`, `$born` (all arrays from `array_filter`).
**Controller keeps:** lines 6-20 (query + filters).

#### `yhteystiedot.php`
**Delete:** lines 20 (`require header.php`) through 90 (`require footer.php`), including `$page_title` (line 18, template line 2).
**Variable contract required by template:** `$stable_name`, `$vrl_id`, `$email`, `$nickname`, `$forum_url` (all strings, may be empty string).
**Controller keeps:** lines 4-16 (settings query + variable extraction).

#### `ajankohtaista.php`
**Delete:** lines 55 (`require header.php`) through 135 (`require footer.php`), including `$MONTHS_FI` array (lines 8-12, template lines 3-7) and `$page_title` (line 4, template line 9).
**Variable contract required by template:** `$posts` (array), `$archive` (nested array `[yr][mo] = count`), `$yearFilter`, `$monthFilter` (ints).
**Controller keeps:** lines 5-53 (filter param parsing, query branches, archive query).

#### `postaus.php`
**Delete:** lines 75 (`require header.php`) through 151 (`require footer.php`), including `$MONTHS_FI` (lines 69-73, template lines 3-7).
**404 handling:** two inline branches (lines 15-22, 26-33) doing `require header.php` + echo + `require footer.php` + `exit` — replace with unified `http_response_code(404); exit;` (no themed render on this early-exit path, matching `theme-page.php` convention).
**Variable contract required by template:** `$post` (assoc array: title, content, created_at, slug), `$prev`, `$next` (arrays|false, keys id/title/slug), `$archive` (nested array), `$postYear`, `$postMonth` are computed IN the template itself (template lines 30-31) from `$post['created_at']` — NOT the controller's job (confirm: controller currently computes them at lines 96-97 as dead duplicate code — remove from controller, template already does this).
**Controller keeps:** lines 4-66 (post fetch + prev/next + archive query), minus `$MONTHS_FI` and minus `$postYear`/`$postMonth` (template-owned).

---

## Shared Patterns

### Malli B root-override hook
**Source:** `public/pages/hevonen.php` lines 5-11 (canonical form using `resolveThemePath()`)
**Apply to:** all 7 controllers, filename parameter substituted per file (see table above)
**Prerequisite:** `require_once __DIR__ . '/../src/includes/theme.php';` must exist in each controller (already present in `index.php`/`hevonen.php`; must be ADDED to the other 5)

### Malli A delegation
**Source:** pattern implied by `resolveThemePath()` contract in `theme.php` + `theme-page.php`'s `require $themeFile;` usage (line 34)
**Apply to:** all 7 controllers, as final line: `require resolveThemePath('pages/{sivu}.php');` with 404 guard
**Note:** because the default theme always contains all 7 templates, false is only theoretically possible (path-traversal rejection or filesystem corruption) — still guard per `theme-page.php` convention for defense-in-depth.

### 404 handling unification
**Source:** `public/pages/theme-page.php` lines 7-10, 23-26, 29-32 — canonical `http_response_code(404); exit;` (no themed body render)
**Apply to:** `hevonen.php` (2 branches) and `postaus.php` (2 branches) — replace their current inline-HTML 404 render (require header + echo + require footer) with the bare `http_response_code(404); exit;` idiom, since these are pre-render short-circuits not tied to any theme template.

### Dead helper/array removal (D-05)
**Source:** confirmed present in `themes/default/pages/*.php` for: `$genderFi` (hevoset.php:6, kasvatus.php:6, hevonen.php:4), `$MONTHS_FI` (ajankohtaista.php:3-7, postaus.php:2-7), `pedigreeHorseLink()`/`pedigreeCell()` (hevonen.php template, confirmed lines 6-20+)
**Apply to:** delete these exact duplicates from the 5/7 controllers that currently define them inline, since templates now own them.

## No Analog Found

None — all 7 files share the same migration pattern; `hevonen.php`/`index.php` serve as complete self-analogs for Malli B, and `theme-page.php` serves as the 404-handling analog. No external pattern search was needed.

## Metadata

**Analog search scope:** `public/pages/*.php` (7 files), `public/themes/default/pages/*.php` (7 paired templates), `public/src/includes/theme.php`
**Files scanned:** 15
**Pattern extraction date:** 2026-07-04
