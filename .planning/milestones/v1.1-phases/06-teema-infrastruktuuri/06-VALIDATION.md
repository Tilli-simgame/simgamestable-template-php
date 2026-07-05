---
phase: 06
slug: teema-infrastruktuuri
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-06-22
---

# Phase 06 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHP built-in + manual browser testing (no separate test framework in project) |
| **Config file** | none |
| **Quick run command** | Avaa `http://localhost:8080/pages/index.php` selaimessa |
| **Full suite command** | Käy läpi kaikki 5 success criteria manuaalisesti (ks. alla) |
| **Estimated runtime** | ~5 min (manuaalinen) |

---

## Sampling Rate

- **After every task commit:** Avaa `http://localhost:8080/pages/index.php` — varmista ei PHP-virheitä
- **After every plan wave:** Käy läpi kaikki ao. success criteria manuaalisesti
- **Before `/gsd-verify-work`:** Kaikki 6 tarkistuspistettä vihreänä
- **Max feedback latency:** ~5 min (manual)

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 06-01-T01 | 06-01 | 1 | THEME-02 | — | INSERT IGNORE ei ylikirjoita olemassa olevaa arvoa | manual | phpMyAdmin: `SELECT * FROM settings WHERE setting_key='active_theme'` — odotettu: `default` | ❌ Wave 1 | ⬜ pending |
| 06-01-T02 | 06-01 | 1 | THEME-04 | — | theme.json sisältää oikean rakenteen | manual | `php -r "print_r(json_decode(file_get_contents('public/themes/default/theme.json'), true));"` | ❌ Wave 1 | ⬜ pending |
| 06-02-T01 | 06-02 | 2 | THEME-01, THEME-03 | Path traversal (V5) | `resolveThemePath('../../etc/passwd')` palauttaa `false`; `THEME_PATH` ja `THEME_URL` ovat määritelty | manual | Lisää `var_dump(resolveThemePath('pages/index.php'), resolveThemePath('../../etc/passwd'), THEME_PATH, THEME_URL)` sivun alkuun | ❌ Wave 2 | ⬜ pending |
| 06-02-T02 | 06-02 | 2 | THEME-03 | Admin isolation (V4) | Admin-sivu EI lataa theme.php:tä — `defined('THEME_PATH')` on `false` admin-sivulla | manual | Lisää `var_dump(defined('THEME_PATH'))` admin/index.php:hen — täytyy olla `false` | ❌ Wave 2 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Ei erillistä Wave 0:aa — Wave 1 luo tarvittavat prerequisites:

- [ ] `database/migrate_theme.sql` — Wave 1 / Task 06-01-T01 (active_theme-rivi settings-taulussa)
- [ ] `public/themes/default/theme.json` — Wave 1 / Task 06-01-T02 (themes-hakemistorakenne)

*Wave 2:n testit edellyttävät Wave 1:n suorittamista (Wave 1 luo hakemiston jolle realpath() toimii — ks. RESEARCH.md Pitfall 2).*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| `resolveThemePath('pages/index.php')` palauttaa absoluuttisen polun default-teemaan | THEME-01 | Ei test frameworkia projektissa | Lisää `var_dump(resolveThemePath('pages/index.php'))` index.php:hen; odotettu: `/var/www/html/public/themes/default/pages/index.php` (tai vastaava) |
| `resolveThemePath('../../etc/passwd')` palauttaa `false` | THEME-01 (path traversal) | Security assertion, no test runner | Lisää `var_dump(resolveThemePath('../../etc/passwd'))` index.php:hen; odotettu: `bool(false)` |
| `THEME_PATH` ja `THEME_URL` ovat määritelty julkisella sivulla | THEME-03 | Vakiomäärittely, no automated check | `var_dump(THEME_PATH, THEME_URL)` index.php:ssä — molemmat ei-tyhjänä merkkijonona |
| Admin-sivu EI lataa theme.php:tä — `defined('THEME_PATH')` on `false` | THEME-03 | Admin isolation vaatii admin-sivun avaamista | Lisää tilapäisesti `var_dump(defined('THEME_PATH'))` johonkin admin-sivuun; odotettu: `bool(false)` |
| `settings`-taulussa on `active_theme`-rivi arvolla `'default'` | THEME-02 | DB-sisältö vaatii phpMyAdminin | phpMyAdmin: `SELECT * FROM settings WHERE setting_key='active_theme'` |
| `public/themes/default/theme.json` löytyy ja palautaa `{"name":"Default","version":"1.0.0"}` | THEME-04 | Tiedostorakenne, no CLI runner | `php -r "print_r(json_decode(file_get_contents('public/themes/default/theme.json'), true));"` |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 1 covers all MISSING references (themes dir + DB row)
- [ ] No watch-mode flags
- [ ] Feedback latency < 5 min (manual)
- [ ] `nyquist_compliant: true` set in frontmatter after sign-off

**Approval:** pending
