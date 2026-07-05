# Phase 9: Admin-teemavalinta & Altervista-verifiointi - Pattern Map

**Mapped:** 2026-07-04
**Files analyzed:** 1 (new file type, replicated per theme)
**Analogs found:** 1 / 1

## Scope note

Per CONTEXT.md, Phase 9's only code deliverable is a new `.htaccess` file placed in each theme's
`pages/` subdirectory (D-03/D-04). `public/admin/settings.php` (THEME-10/THEME-11) is explicitly
**not modified** (D-05) and is documented here only as a read-only reference to confirm the existing
implementation satisfies requirements. THEME-12 (Altervista verification) produces no code artifact
(D-08) and is out of scope for pattern mapping.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|-------------------|------|-----------|-----------------|---------------|
| `public/themes/default/pages/.htaccess` (new) | config (server directive) | request-response (deny direct HTTP access) | `public/uploads/.htaccess` | role-match (same directory-protection mechanism, different `FilesMatch` target) |
| *(future)* `public/themes/{theme}/pages/.htaccess` | config | request-response | `public/uploads/.htaccess` | role-match — same template applies to any future theme's `pages/` folder |

No PHP source files are created or modified in this phase.

## Pattern Assignments

### `public/themes/default/pages/.htaccess` (config, request-response)

**Analog:** `public/uploads/.htaccess` (full file, 20 lines — read above in entirety)

**Full analog content:**
```apache
# Security: Disable PHP execution in uploads directory
# Prevents uploaded files from being executed as PHP

<FilesMatch "\.ph(p[3-7]?|ar|ps)$">
    Deny from all
</FilesMatch>

# Alternative: completely disable script engine
<IfModule mod_php.c>
    php_flag engine off
</IfModule>

# Disable directory listing
Options -Indexes

# Prevent access to hidden files
<FilesMatch "^\.">
    Deny from all
</FilesMatch>
```

**Key difference to apply (per CONTEXT D-04 rationale):**
`public/uploads/.htaccess` denies ALL PHP execution in that directory (uploads should never contain
PHP at all). The new `pages/.htaccess` must instead deny only **direct HTTP requests to the page
template files**, while still allowing the files to be `require`/`include`d by the theme's
controllers (e.g. `theme-page.php`, `hevonen.php` when called via rewrite rules in the root
`.htaccess`). A blanket `Deny from all` on `*.php` inside `pages/` would be **correct** here too,
since these files are never meant to be requested directly by URL — the root `.htaccess` rewrite
rules (see below) route all public requests to controller scripts, and PHP `require_once` calls
bypass `.htaccess` `Deny from all` entirely (Apache access control only applies to HTTP requests,
not filesystem includes). So the uploads pattern's core `<FilesMatch>` + `Deny from all` block is
directly reusable without needing an allowlist/include exception.

**Secondary precedent — minimal style** (`public/admin/.htaccess`, full file, 1 line):
```apache
Options -Indexes
```
Shows the minimal acceptable `.htaccess` in this codebase — confirms single-directive files are an
established, valid pattern if directory listing suppression alone were the only goal (not sufficient
alone here per D-03, but shows the file can be short).

**Tertiary precedent — root-level protection** (`public/.htaccess`, lines 52-55):
```apache
# Tietoturva: Estä suora pääsy arkaluonteisiin tiedostoihin
<FilesMatch "^(config|\.env|\.git|\.htaccess|README|package\.json|composer\.json)">
    Deny from all
</FilesMatch>
```
Confirms the project's established idiom: `<FilesMatch "regex"> Deny from all </FilesMatch>` blocks
with a Finnish comment above explaining intent (`# Tietoturva: ...` / `# Security: ...` bilingual
style seen across `.htaccess` files — uploads uses English comments, root uses Finnish). Follow
whichever comment language matches the target file's sibling files; root `.htaccess` (closest to
the theme tree) uses Finnish comments, so Finnish comments are the safer default for `pages/.htaccess`.

**Suggested composed pattern for `pages/.htaccess`** (synthesis of the above three analogs — exact
regex/response code left to implementer per Claude's Discretion in CONTEXT.md):
```apache
# Tietoturva: estä suora HTTP-pääsy teeman page-templateihin
# Templatet odottavat kontrollerin (esim. theme-page.php) asettamia muuttujia
<FilesMatch "\.php$">
    Deny from all
</FilesMatch>
```

## Shared Patterns

### Directory-level `.htaccess` protection (project-wide convention)
**Sources:** `public/uploads/.htaccess`, `public/admin/.htaccess`, `public/.htaccess`
**Apply to:** `public/themes/{theme}/pages/.htaccess` (all current and future themes, per D-04)

Established convention in this codebase: every directory needing protection beyond the root
`.htaccess` gets its own `.htaccess` file with a `<FilesMatch "regex"> Deny from all </FilesMatch>`
block, optionally preceded by a one-line comment stating the security rationale. `Options -Indexes`
is set at root and inherited (per D-02, not repeated in `pages/.htaccess`).

### Root `.htaccess` rewrite context (informational — not modified in Phase 9)
**Source:** `public/.htaccess` lines 4-50
Confirms all public page requests are routed through rewrite rules to controller scripts
(`theme-page.php`, `hevonen.php`, `postaus.php`, etc.) rather than directly to files inside
`themes/{theme}/pages/`. This validates that denying direct access to `pages/*.php` via the new
`.htaccess` will not break the routing rules already in place, since none of them target
`themes/{theme}/pages/` paths directly — they target `public/pages/*.php` (a separate top-level
`pages/` directory, distinct from `themes/{theme}/pages/`). Implementer should double-check this
distinction: `public/pages/` (controllers) vs `public/themes/{theme}/pages/` (templates) are two
different directories with the same base name.

## No Analog Found

None — the `.htaccess` directory-protection pattern is well established in this codebase with
three usable precedents.

## Read-Only Reference (not to be modified — D-05)

### `public/admin/settings.php` (lines 1-27 read)
Confirms existing, accepted-as-is theme selector implementation:
```php
// Lue saatavilla olevat ulkoasuteeman kansiot (tarvitaan POST-validoinnissa)
$available_themes = [];
$themes_dir = __DIR__ . '/../themes';
foreach (glob($themes_dir . '/*/theme.json') ?: [] as $json_path) {
    $theme_key = basename(dirname($json_path));
    $meta = json_decode(file_get_contents($json_path), true);
    $available_themes[$theme_key] = $meta['name'] ?? $theme_key;
}
if (empty($available_themes)) {
    $available_themes['default'] = 'Default';
}
```
This satisfies THEME-10/THEME-11 already (glob-based listing, `theme.json` `name` field, CSRF
protection referenced at line 30 via `validate_csrf_token()`, allowlist validation referenced in
CONTEXT.md at line 74). No pattern extraction needed for planning — planner should treat this file
as out of scope for any new plan file/action.

## Metadata

**Analog search scope:** `public/uploads/`, `public/admin/`, `public/` (root), `public/themes/default/pages/`
**Files scanned:** 5 (`uploads/.htaccess`, `admin/.htaccess`, root `.htaccess`, `admin/settings.php`, theme pages directory listing)
**Existing theme page files found (target install locations):** `public/themes/default/pages/{index,hevoset,kasvatus,yhteystiedot,hevonen,ajankohtaista,postaus}.php`
**Pattern extraction date:** 2026-07-04
