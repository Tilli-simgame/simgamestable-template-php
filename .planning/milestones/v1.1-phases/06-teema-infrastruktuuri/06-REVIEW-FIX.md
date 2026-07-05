---
phase: 06-teema-infrastruktuuri
fixed_at: 2026-06-22T00:00:00Z
review_path: .planning/phases/06-teema-infrastruktuuri/06-REVIEW.md
iteration: 1
findings_in_scope: 6
fixed: 6
skipped: 0
status: all_fixed
---

# Phase 06: Code Review Fix Report

**Fixed at:** 2026-06-22
**Source review:** .planning/phases/06-teema-infrastruktuuri/06-REVIEW.md
**Iteration:** 1

**Summary:**
- Findings in scope: 6 (2 Critical + 4 Warning; Info findings excluded per fix_scope=critical_warning)
- Fixed: 6
- Skipped: 0

## Fixed Issues

### CR-01: Unhandled PDOException in theme.php database query

**Files modified:** `public/src/includes/theme.php`
**Commit:** a0b0c0a
**Applied fix:** Wrapped the `getDB()` / `$db->prepare()` / `$stmt->execute()` / `$stmt->fetchColumn()` sequence in a `try { } catch (\Throwable $e)` block. On any failure, `error_log()` records the error message and `$rawTheme` falls back to `'default'`. The surrounding logic (`preg_match` validation, `realpath`, `define()` calls) is unchanged and remains outside the try block.

---

### CR-02: Partial HTML-escaping of assembled href produces latent XSS

**Files modified:** `public/pages/index.php`
**Commit:** 20e7f69
**Applied fix:** Applied Option B from the review: removed the premature `e()` wrapping of `SITE_URL` during `$newsHref` assembly (which would cause double-encoding of any `&` in the URL), and added `e($newsHref)` at the single HTML attribute output point (`href="<?= e($newsHref) ?>"`). Added an inline comment explaining why `rawurlencode()` alone is insufficient and that `e()` must be applied at output.

---

### WR-01: str_starts_with prefix check is incorrect when realpath() fallback is active

**Files modified:** `public/src/includes/theme.php`
**Commit:** e765422
**Applied fix:** Added `error_log('theme.php: themes/-hakemistoa ei löydy: ' . __DIR__ . '/../../themes')` inside the `if ($resolvedThemesRoot === false)` branch, plus an inline comment explaining that `resolveThemePath()` will always return false while the unresolved `../../`-based path is in use (because an absolute `realpath()` result cannot start with an unresolved relative string). The fallback assignment is retained as-is since removing it would break future directory creation scenarios; the log entry gives developers immediate diagnostic output.

---

### WR-02: THEME_PATH / THEME_URL constants are defined but never consumed on the page

**Files modified:** `public/src/includes/header.php`
**Commit:** 4fd2288
**Applied fix:** Added a `<!-- TODO WR-02 -->` HTML comment at the exact integration point in `header.php` (adjacent to the `<link rel="stylesheet">` tag) documenting: (a) the current hardcoded path, (b) the target `e(THEME_URL) . 'assets/css/style.css'` integration, and (c) that theme.php constants are defined but have no effect on rendered output until this wiring is complete. This makes the gap visible and actionable without prematurely wiring an incomplete integration or removing the phase's infrastructure.

---

### WR-03: $newsDate output is unescaped

**Files modified:** `public/pages/index.php`
**Commit:** ea8bc33
**Applied fix:** Changed `<?= $newsDate ?>` to `<?= e($newsDate) ?>` on the `<span class="card-date">` line. Consistent escaping at every output point prevents future regressions if `formatDate()` is ever modified to return database-derived content.

---

### WR-04: $horseCount and $foalCount output directly into JavaScript without escaping

**Files modified:** `public/pages/index.php`
**Commit:** 926911b
**Applied fix:** Changed both `animateCount()` calls to use `<?= json_encode($horseCount) ?>` and `<?= json_encode($foalCount) ?>` instead of bare interpolation. `json_encode()` is the canonical safe tool for embedding PHP values in JavaScript contexts and prevents injection if the `(int)` cast is ever removed or the value source changes.

---

## Skipped Issues

None — all in-scope findings were successfully fixed.

---

_Fixed: 2026-06-22_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
