# Phase 6: Teema-infrastruktuuri - Pattern Map

**Mapped:** 2026-06-22
**Files analyzed:** 4 new/modified files
**Analogs found:** 4 / 4

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|-------------------|------|-----------|----------------|---------------|
| `public/src/includes/theme.php` | utility/shim | request-response | `public/src/includes/helpers.php` + `public/src/includes/config.php` | role-match (utility + constant-define combined) |
| `database/migrate_theme.sql` | migration | batch | `database/migrate_settings.sql` | exact |
| `public/themes/default/theme.json` | config | — | `public/src/includes/config.php` (structure reference only) | partial (JSON vs PHP config) |
| `public/pages/index.php` | controller | request-response | `public/pages/index.php` (itself, existing) | exact (one-line addition) |

---

## Pattern Assignments

### `public/src/includes/theme.php` (utility/shim, request-response)

**Analogs:** `public/src/includes/config.php` (constant-define pattern) + `public/src/includes/helpers.php` (function structure + path validation pattern)

**Constant-define pattern** (`public/src/includes/config.php` lines 8–19):
```php
define('SITE_URL', rtrim(getenv('SITE_URL') ?: 'https://tilli.altervista.org/demotalli', '/'));
define('UPLOADS_DIR', __DIR__ . '/../../uploads/');
define('UPLOADS_URL', SITE_URL . '/uploads/');
```
Copy the `define()` + `__DIR__`-relative path pattern. THEME_PATH and THEME_URL follow the same convention as UPLOADS_DIR and UPLOADS_URL — one server path, one browser URL, built from SITE_URL.

**Guard pattern** (`public/src/includes/config.php` lines 8–11 via `db.php` lines 9–11):
```php
if (!defined('SESSION_NAME')) {
    require_once __DIR__ . '/config.php';
}
```
Copy the `if (!defined(...))` guard. theme.php wraps its entire initialization block in `if (!defined('THEME_PATH'))` to make it safe for repeated includes.

**DB singleton pattern** (`public/src/includes/helpers.php` lines 95–96, `public/src/includes/db.php` lines 27–55):
```php
$db = getDB();
$stmt = $db->prepare('SELECT ... WHERE id = :id');
$stmt->execute([':id' => $horseId]);
$result = $stmt->fetch();
```
Copy the `getDB()` singleton call + named placeholder prepare/execute pattern. For the active_theme read, use `fetchColumn()` with Elvis fallback: `$stmt->fetchColumn() ?: 'default'` (not `?? 'default'` — PDO returns `false` for missing rows, not `null`).

**Path-traversal validation pattern** (`public/src/includes/helpers.php` lines 264–298):
```php
function validate_file_name($filename, int $max_length = 255): array {
    $filename = is_string($filename) ? basename($filename) : '';

    // Sallitaan vain turvallisia merkkejä
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
        return ['valid' => false, 'error' => '...', 'value' => ''];
    }
    // Torjutaan path traversal -yritykset
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
        return ['valid' => false, 'error' => '...', 'value' => ''];
    }
    return ['valid' => true, 'value' => $filename, 'error' => null];
}
```
Copy the `preg_match` allowlist approach for the theme name. resolveThemePath() upgrades this with `realpath()` + `str_starts_with()` (THEME_PATH with trailing DIRECTORY_SEPARATOR) to handle subdirectory subPaths that the flat-filename validator cannot cover.

**Function docblock pattern** (`public/src/includes/helpers.php` lines 6–10, 89–94):
```php
/**
 * Sanitoi käyttäjäsyöte HTML-tulostusta varten (XSS-suojaus)
 */
function e(string $value): string {
```
Copy the single-line description docblock with typed parameters. resolveThemePath() uses `@param` and `@return` tags matching the style of `getHorsePedigree()` (lines 89–94).

**File header comment pattern** (`public/src/includes/helpers.php` lines 1–4):
```php
<?php
/**
 * Apufunktiot — sisällytetään db.php:n kautta automaattisesti
 */
```
Copy this structure for theme.php header — describe what the file is, how it is loaded, and add the explicit warning that it must NOT be added to db.php.

---

### `database/migrate_theme.sql` (migration, batch)

**Analog:** `database/migrate_settings.sql` (exact match)

**Full analog** (`database/migrate_settings.sql` lines 1–21):
```sql
-- ============================================================
-- Tallin asetukset — avain-arvo-taulu
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(100) NOT NULL,
  ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Oletusarvot (INSERT IGNORE ei ylikirjoita olemassa olevia)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('owner_nickname',   ''),
  ('color_theme',      'savi');
```

Copy exactly:
- Banner comment block with the same `-- ====` style and "Aja phpMyAdminissa" instruction
- `INSERT IGNORE INTO` syntax (not `INSERT ... ON DUPLICATE KEY UPDATE` — migrate files use IGNORE, admin write-back uses ON DUPLICATE KEY)
- Backtick-quoted identifiers
- No `CREATE TABLE` block needed — migrate_theme.sql only inserts a row into the table that migrate_settings.sql already created

---

### `public/themes/default/theme.json` (config, static)

**No direct PHP analog** — this is a JSON config file. No existing `.json` config files in the codebase.

**Structure is fully specified by D-08:**
```json
{"name": "Default", "version": "1.0.0"}
```

Minimal two-field object. No comments (JSON does not support them). Written once and read by theme.php via `json_decode(file_get_contents($path), true)`.

---

### `public/pages/index.php` (controller, request-response — ONE LINE ADDITION)

**Analog:** `public/pages/index.php` itself (existing file, lines 1–2)

**Existing include chain** (`public/pages/index.php` lines 1–2):
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
```

**After modification** — add exactly one line after db.php require:
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/../src/includes/theme.php'; // Phase 6: teemashim
```

Copy the `__DIR__ . '/../src/includes/` path prefix pattern exactly. No other changes to the file.

---

## Shared Patterns

### DB singleton access
**Source:** `public/src/includes/db.php` lines 27–55, used in `public/src/includes/helpers.php` line 95
**Apply to:** `public/src/includes/theme.php` (active_theme read)
```php
$db = getDB();
$stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1');
$stmt->execute([':k' => 'active_theme']);
$rawTheme = $stmt->fetchColumn() ?: 'default';
```
Note: use `?: 'default'` (Elvis), not `?? 'default'` (null-coalescing). PDO `fetchColumn()` returns `false` when no row found; `false` is falsy for Elvis but not null for null-coalescing.

### Constant definition guard
**Source:** `public/src/includes/db.php` lines 9–11 (pattern), `public/src/includes/config.php` lines 8–25 (application)
**Apply to:** `public/src/includes/theme.php` initialization block
```php
if (!defined('THEME_PATH')) {
    // ... all initialization here ...
}
```

### `__DIR__`-relative path building
**Source:** `public/src/includes/config.php` lines 14–15
**Apply to:** `public/src/includes/theme.php` when building THEMES_ROOT and THEME_PATH
```php
define('UPLOADS_DIR', __DIR__ . '/../../uploads/');
```
THEME_PATH equivalent: `realpath(__DIR__ . '/../../themes') . DIRECTORY_SEPARATOR . $themeName . DIRECTORY_SEPARATOR`
Critical: always append `DIRECTORY_SEPARATOR` after `realpath()` output — `realpath()` strips trailing slashes, but prefix-check requires the separator to be present so `/themes/defaultevil/` does not match a `/themes/default/` prefix.

### preg_match allowlist for names
**Source:** `public/src/includes/helpers.php` lines 268–271
**Apply to:** Theme name validation in `public/src/includes/theme.php`
```php
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) { ... }
```
Theme name uses a stricter pattern: `/^[a-zA-Z0-9_-]+$/` (no dots — dots in theme folder names would complicate path reasoning).

---

## No Analog Found

| File | Role | Data Flow | Reason |
|------|------|-----------|--------|
| `public/themes/default/theme.json` | config | static | No JSON config files exist in the codebase; structure is fully locked by D-08 |

---

## Metadata

**Analog search scope:** `public/src/includes/`, `database/`, `public/pages/`
**Files scanned:** 5 (helpers.php, config.php, db.php, migrate_settings.sql, index.php)
**Pattern extraction date:** 2026-06-22
