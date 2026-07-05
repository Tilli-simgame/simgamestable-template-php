---
phase: 08-sivukontrollerien-migraatio
verified: 2026-07-04T15:40:00Z
status: passed
score: 8/8 must-haves verified
behavior_unverified: 0
overrides_applied: 0
re_verification:
  previous_status: gaps_found
  previous_score: 5/8
  gaps_closed:
    - "Each of the 7 controllers ends with a resolveThemePath('pages/X.php') delegation guarded by a 404 fallback"
  gaps_remaining: []
  regressions: []
---

# Phase 8: Sivukontrollerien migraatio Verification Report

**Phase Goal:** Kaikki 7 julkista sivukontrolleria ovat data-only — ne hakevat datan tietokannasta ja delegoivat kaiken HTML-renderöinnin aktiivisen teeman sivupohjille resolveThemePath():n kautta.
**Verified:** 2026-07-04T15:40:00Z
**Status:** passed
**Re-verification:** Yes — after gap closure (commit `8f189e4`, "fix(08): guard resolveThemePath() require with 404 fallback (CR-01)")

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Kaikki 5 perussivua (index, hevoset, hevonen, kasvatus, yhteystiedot) latautuvat oikein ilman inline-HTML:ää kontrollerissa | ✓ VERIFIED | All 5 files grepped clean for `includes/header.php`, `includes/footer.php`, `<html`, `<div`, `<body`. Live curl (re-run this pass): index.php 200, hevoset.php 200, kasvatus.php 200, yhteystiedot.php 200, hevonen.php?slug=testiponi-tahti 200, hevonen.php (no slug) 404. |
| 2 | Molemmat blogi-sivut (ajankohtaista.php, postaus.php) latautuvat oikein data-only-mallin mukaisesti | ✓ VERIFIED | Live curl (re-run): ajankohtaista.php 200, postaus.php?slug=hissun-paikky 200, postaus.php (no slug) 404. Both now also carry the guarded delegation model (see Truth 5 — gap closed). |
| 3 | Teeman vaihtaminen (manuaalisesti DB:ssä) vaihtaa sivuston ulkoasun — kontrolleri ei tarvitse muutoksia | ✓ VERIFIED | Mechanism unchanged by this fix (no controller's Model B hook, lines 6-11, was touched by commit 8f189e4 — only the bottom guard changed). Previously empirically proven in 08-04 with the `testitema` throwaway theme (DB flip → `TESTITEMA-MARKER` rendered at HTTP 200, `git status --porcelain public/pages/` empty throughout; reverted cleanly). `testitema` confirmed still absent from disk this pass; `default` and `oma-talli` intact in `public/themes/`. |
| 4 | None of the 7 controllers contain inline HTML, a header.php require, or a footer.php require | ✓ VERIFIED | `grep -n "includes/header.php\|includes/footer.php\|<html\|<div\|<body"` across all 7 files in `public/pages/` returned zero matches (re-run this pass). |
| 5 | Each of the 7 controllers ends with a resolveThemePath('pages/X.php') delegation guarded by a 404 fallback | ✓ VERIFIED — GAP CLOSED | Direct file read (this pass) confirms all 7 controllers now end with the identical `$_themePage = resolveThemePath('pages/X.php'); if ($_themePage === false) { http_response_code(404); exit; } require $_themePage;` block: `index.php` (37-42), `hevoset.php` (33-38), `kasvatus.php` (29-34), `hevonen.php` (137-142), `yhteystiedot.php` (27-32), `ajankohtaista.php` (56-61), `postaus.php` (69-74). Static grep confirms `grep -c "=== false"` returns exactly 1 per file across all 7. **Live reproduction re-run**: hid `public/themes/default/pages/ajankohtaista.php`, `postaus.php`, and `yhteystiedot.php` in turn inside the `virtuaalitalli-web` container and curled each corresponding route — all three now return **HTTP 404** (previously HTTP 500 fatal error), then were restored and confirmed back to HTTP 200/404-on-empty-query as normal. Control test on `index.php` (previously-correct controller) repeated identically: hide → HTTP 404, restore → HTTP 200 — no regression in the already-correct guard behavior. |
| 6 | Each of the 7 controllers carries the identical Model B root-override hook (D-02) | ✓ VERIFIED | All 7 files carry the identical `$_vt_themeFile = resolveThemePath('X.php'); if ($_vt_themeFile !== false && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) { require $_vt_themeFile; exit; }` block, unchanged by the fix commit. `grep -c "str_starts_with(THEME_PATH"` returns 1 for all 7 files. |
| 7 | Template-owned helpers removed from controllers (`$genderFi`, `$MONTHS_FI`, `pedigreeHorseLink()`, `pedigreeCell()`) | ✓ VERIFIED | Zero matches for any of these symbols across all 7 controller files (re-run this pass). |
| 8 | THEME-08 / THEME-09 requirements satisfied | ✓ VERIFIED | THEME-08/09 both require "data-only-kontrollerit jotka käyttävät `resolveThemePath()`" with a controlled fallback — the guard is now present and live-tested on all covered files. `REQUIREMENTS.md` marking of `[x]` is now accurate. |

**Score:** 8/8 truths verified (0 present-but-behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `public/pages/index.php` | Data-only, guarded Malli A + Model B hook | ✓ VERIFIED | Guard present (37-42), Model B hook present (6-11), no inline HTML. Control-tested this pass (hide/restore → 404/200). |
| `public/pages/hevoset.php` | Data-only, guarded Malli A + Model B hook | ✓ VERIFIED | Guard present (33-38), Model B hook present, no inline HTML. |
| `public/pages/kasvatus.php` | Data-only, guarded Malli A + Model B hook | ✓ VERIFIED | Guard present (29-34), Model B hook present, no inline HTML. |
| `public/pages/hevonen.php` | Data-only, guarded Malli A, helpers stripped | ✓ VERIFIED | Guard present (137-142), `pedigreeHorseLink()`/`pedigreeCell()`/`$genderFi` absent. |
| `public/pages/yhteystiedot.php` | Data-only, guarded Malli A + Model B hook | ✓ VERIFIED (gap closed) | Guard now present (27-32) — `php -l` clean, live hide/restore test confirms HTTP 404 on missing template, HTTP 200 restored. |
| `public/pages/ajankohtaista.php` | Data-only, guarded Malli A + Model B hook | ✓ VERIFIED (gap closed) | Guard now present (56-61) — `php -l` clean, live hide/restore test confirms HTTP 404 on missing template, HTTP 200 restored. |
| `public/pages/postaus.php` | Data-only, guarded Malli A + unified 404 | ✓ VERIFIED (gap closed) | Guard now present (69-74) — `php -l` clean, live hide/restore test confirms HTTP 404 on missing template, HTTP 200 restored. |
| `public/themes/testitema/` (temporary) | Created then deleted, net-zero | ✓ VERIFIED | Confirmed absent from disk this pass; `default` and `oma-talli` intact. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| 7 controllers | `public/themes/default/pages/X.php` | `require resolveThemePath('pages/X.php')` | ✓ VERIFIED | All 7 controllers now have failure-path handling (`=== false` → `http_response_code(404); exit;`). Verified live for 4/7 in the prior pass and confirmed for all 7 (including the 3 previously-broken files plus a repeat control check on `index.php`) in this pass by hiding the corresponding default-theme template file and curling. |
| `settings.active_theme` | `THEME_PATH` (theme.php) | DB read → constant | ✓ VERIFIED | Mechanism untouched by the fix commit (diff only touches the bottom guard in 3 files, `git diff --stat` shows 0 changes elsewhere). Previously proven end-to-end in 08-04 with the `testitema` theme swap. |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| index.php renders at 200 | `curl -s -o /dev/null -w '%{http_code}' http://localhost:8080/pages/index.php` | 200 | ✓ PASS |
| hevoset.php renders at 200 | same pattern | 200 | ✓ PASS |
| kasvatus.php renders at 200 | same pattern | 200 | ✓ PASS |
| yhteystiedot.php renders at 200 | same pattern | 200 | ✓ PASS |
| ajankohtaista.php renders at 200 | same pattern | 200 | ✓ PASS |
| hevonen.php (no slug) returns 404 | same pattern | 404 | ✓ PASS |
| hevonen.php (valid slug) returns 200 | `?slug=testiponi-tahti` | 200 | ✓ PASS |
| postaus.php (no slug) returns 404 | same pattern | 404 | ✓ PASS |
| postaus.php (valid slug) returns 200 | `?slug=hissun-paikky` | 200 | ✓ PASS |
| **Regression re-test: guarded control (index.php) with missing template returns 404** | Hid `themes/default/pages/index.php` in `virtuaalitalli-web` container, curled, restored | HTTP 404 → restored to 200 | ✓ PASS (guard still works, no regression) |
| **Gap-closure test 1/3: ajankohtaista.php with missing template** | Hid `themes/default/pages/ajankohtaista.php`, curled, restored | **HTTP 404** (was HTTP 500 before fix) | ✓ PASS — gap closed |
| **Gap-closure test 2/3: postaus.php with missing template** | Hid `themes/default/pages/postaus.php`, curled `?slug=hissun-paikky`, restored | **HTTP 404** (was HTTP 500 before fix) | ✓ PASS — gap closed |
| **Gap-closure test 3/3: yhteystiedot.php with missing template** | Hid `themes/default/pages/yhteystiedot.php`, curled, restored | **HTTP 404** (was HTTP 500 before fix) | ✓ PASS — gap closed |
| `php -l` syntax check on all 3 modified files | `php -l public/pages/{ajankohtaista,postaus,yhteystiedot}.php` (in container) | "No syntax errors detected" x3 | ✓ PASS |
| Post-test cleanliness: all 7 theme template files restored, no `.hidden` leftovers | `ls` on `themes/default/pages/` in container | 7 files present, correct names, no stray files | ✓ PASS |
| Working tree clean after test cycle | `git status --porcelain public/pages/` | (empty) | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| THEME-08 | 08-01, 08-02, 08-03 | 5 base controllers → data-only via resolveThemePath() | ✓ SATISFIED | All 5 (index, hevoset, kasvatus, hevonen, yhteystiedot) carry the guarded delegation and Model B hook; live-tested this pass. |
| THEME-09 | 08-02 | Blog controllers (ajankohtaista, postaus) → data-only via resolveThemePath() | ✓ SATISFIED | Both now carry the guarded delegation (gap closed); live-tested this pass. |

`REQUIREMENTS.md` marks both THEME-08 and THEME-09 as `[x]` complete — this re-verification confirms that marking is now accurate.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `public/pages/ajankohtaista.php`, `postaus.php`, `yhteystiedot.php` | (formerly the CR-01 lines) | Unguarded `require resolveThemePath(...)` | — | **RESOLVED** by commit `8f189e4`. No longer present — confirmed by direct read and live reproduction test in this pass. |
| `public/pages/hevonen.php`, `postaus.php` | slug handling | `!empty($_GET['slug'])` rejects literal `"0"` slug (08-REVIEW.md WR-01) | ⚠️ Warning | Still present. Narrow, pre-existing edge case not part of the gap under test; does not block phase goal (no controller currently produces a slug of literal `"0"` in seeded data). Not a regression from the fix commit. |
| `public/pages/yhteystiedot.php` | 21 | `$s['stable_name'] !== ''` without `??` guard (08-REVIEW.md WR-03) | ⚠️ Warning | Still present, untouched by the fix commit (diff only added lines at the bottom of the file). Not part of the gap under test. |
| 7 controllers | top-of-file | Model B boilerplate duplicated verbatim across all 7 files (08-REVIEW.md WR-02) | ⚠️ Warning | Still present by design — the fix applied the existing guard pattern consistently rather than refactoring to a shared helper, which was an optional suggestion in 08-REVIEW.md, not a blocking requirement. |

No debt markers (`TBD`/`FIXME`/`XXX`/`TODO`/`HACK`/`PLACEHOLDER`) found in any of the 7 controller files.

The three remaining warnings (WR-01, WR-02, WR-03) were already known at the time of the original review and are pre-existing, non-blocking issues unrelated to the CR-01 gap this re-verification targeted. They do not affect the phase goal ("controllers are data-only and delegate to theme templates via resolveThemePath()") and are not part of any must-have truth or cross-cutting constraint for this phase.

### Gaps Summary

No gaps remain. The single blocking gap from the initial verification — "Each of the 7 controllers ends with a resolveThemePath('pages/X.php') delegation guarded by a 404 fallback" — is now closed.

**Verification method for gap closure (independent, not relying on SUMMARY claims):**

1. **Static**: Read all 7 controller files directly (this pass). Confirmed all 7 now share the identical guard block (`$_themePage = resolveThemePath(...); if ($_themePage === false) { http_response_code(404); exit; } require $_themePage;`). Confirmed via `grep -c "=== false"` returning 1 for each of the 7 files.
2. **Dynamic — the exact same live reproduction the original gaps_found verification used**: hid each of the 3 previously-broken theme template files (`ajankohtaista.php`, `postaus.php`, `yhteystiedot.php`) inside the `virtuaalitalli-web` Docker container one at a time, curled the corresponding controller route, and confirmed **HTTP 404** in all 3 cases (previously HTTP 500 fatal error). Each file was restored immediately after its test and a follow-up curl confirmed normal 200/404 behavior returned.
3. **Regression control**: repeated the identical hide/curl/restore cycle on `index.php` — a controller that was already correct before the fix — and confirmed it still returns HTTP 404 when its template is missing and HTTP 200 when restored. No regression introduced.
4. **Syntax check**: `php -l` inside the container on all 3 modified files reported "No syntax errors detected" (only unrelated `mbstring.internal_encoding` deprecation startup notices, not related to these files).
5. **Cleanliness**: confirmed all 7 theme template files are present and correctly named after the test cycle (no stray `.hidden` files), and `git status --porcelain public/pages/` is empty, confirming the test cycle left no residue and the fix commit is the only change to these files.
6. **No unrelated regressions**: `git diff --stat` for the fix commit shows changes confined to the bottom guard in the 3 affected files only (+7/-1 lines each) — the top-of-file Model B hook, SQL queries, and all other logic are untouched, consistent with Success Criteria #3 (theme switching) and the Model B hook (Truth 6) still holding.

All 8 observable truths for this phase are now VERIFIED. Phase goal achieved.

---

_Verified: 2026-07-04T15:40:00Z_
_Verifier: Claude (gsd-verifier)_
