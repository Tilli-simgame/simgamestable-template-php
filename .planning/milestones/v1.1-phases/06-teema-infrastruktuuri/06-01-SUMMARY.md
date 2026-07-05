---
phase: 06-teema-infrastruktuuri
plan: "01"
subsystem: database
tags: [mysql, sql-migration, theme-system, settings-table, json]

# Dependency graph
requires:
  - phase: 01-perusta-tietokantarakenne
    provides: settings-taulu joka vastaanottaa active_theme-rivin
provides:
  - database/migrate_theme.sql — INSERT IGNORE active_theme='default' settings-tauluun
  - public/themes/default/theme.json — teema-metadata ja hakemistorakenteen luominen
  - public/themes/default/ — hakemistopolku jotta realpath() resoloituu Wave 2:n shimissä
affects:
  - 06-02 (theme.php-shim tarvitsee public/themes/-hakemiston olemassaoloa ja active_theme-riviä)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "migrate_theme.sql: INSERT IGNORE -migraatiopattern uusille settings-riveille ilman CREATE TABLE"
    - "theme.json: minimaalinen JSON-metadata teemoille (name + version)"

key-files:
  created:
    - database/migrate_theme.sql
    - public/themes/default/theme.json
  modified: []

key-decisions:
  - "INSERT IGNORE (ei ON DUPLICATE KEY UPDATE) migraatioissa — yhdenmukaisuus kaikkien migrate_*.sql-tiedostojen kanssa"
  - "theme.json vain name + version -kentillä — description/author/preview ovat V2-05 laajennuksia"
  - "public/themes/default/ -hakemisto syntyy theme.json-tiedoston luomisen sivutuotteena — ratkaisee Pitfall 2:n"

patterns-established:
  - "migrate_*.sql ei sisällä CREATE TABLE jos taulu on jo olemassa aiemmassa migraatiossa"
  - "Teema-hakemistorakenne syntyy tiedoston luomisen kautta, ei erillisellä mkdir-komennolla"

requirements-completed: [THEME-02, THEME-04]

# Metrics
duration: 10min
completed: 2026-06-22
---

# Phase 6 Plan 01: Teema-infrastruktuuri runtime-prerequisiitit Summary

**migrate_theme.sql lisää active_theme='default'-rivin settings-tauluun INSERT IGNORE -patternilla, ja public/themes/default/theme.json luo teema-hakemistorakenteen sekä tarjoaa minimaalisen teema-metadatan Wave 2:n shimille**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-06-22T15:29:00Z
- **Completed:** 2026-06-22T15:39:40Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Luotu `database/migrate_theme.sql` joka lisää `active_theme = 'default'` -rivin `settings`-tauluun INSERT IGNORE -patternilla, yhdenmukaisena kaikkien muiden `migrate_*.sql`-tiedostojen kanssa
- Luotu `public/themes/default/theme.json` validina minimaalisena JSON-tiedostona (name=Default, version=1.0.0), joka samalla luo `public/themes/default/`-hakemistorakenteen
- Molemmat artefaktit ovat itsenäisiä — ei koodiriippuvuutta `theme.php`-shimiin joka syntyy Plan 02:ssa

## Task Commits

Jokainen tehtävä commitoitu atomisesti:

1. **Task 1: Luo migrate_theme.sql active_theme-rivillä** - `114877a` (feat)
2. **Task 2: Luo public/themes/default/theme.json** - `1cabe24` (feat)

## Files Created/Modified

- `database/migrate_theme.sql` — Lisää `active_theme = 'default'` settings-tauluun INSERT IGNORE -lauseella; ei sisällä CREATE TABLE (taulu on jo olemassa migrate_settings.sql:stä)
- `public/themes/default/theme.json` — Minimaalinen teema-metadata: `{"name": "Default", "version": "1.0.0"}`; luo myös `public/themes/default/`-hakemistopolun

## Decisions Made

- Noudatettu D-01 ja D-02: `INSERT IGNORE` (ei `ON DUPLICATE KEY UPDATE`) — migraatiot käyttävät IGNORE-syntaksia, vain admin-kirjoitus käyttää ON DUPLICATE KEY
- Noudatettu D-08: Vain `name` ja `version` -kentät theme.json:ssa — ei description/author/preview

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- PHP-komento ei ollut PATH:ssa Bash-ympäristössä (PHP pyörii Docker-kontissa localhost:8080). Käytettiin Python-tulkkia JSON-validointiin — tulos identtinen (json_decode-ekvivalentti). Tämä ei vaikuta tuotantokoodiin.

## User Setup Required

**DB-migraatio vaatii manuaalisen ajon:** Ennen kuin teemashim (Plan 02) voi toimia, `database/migrate_theme.sql` on ajettava phpMyAdminissa:

1. Avaa `http://localhost:8080` (phpMyAdmin Docker-ympäristössä)
2. Valitse tietokanta → Import → valitse `database/migrate_theme.sql`
3. Aja migraatio
4. Tarkista: `SELECT * FROM settings WHERE setting_key='active_theme'` — tuloksena pitäisi olla rivi arvolla `default`

## Next Phase Readiness

- `public/themes/default/`-hakemisto on olemassa → `realpath(__DIR__ . '/../../themes')` resoloituu Plan 02:n shimissä (Pitfall 2 ratkaistu)
- `active_theme`-rivi tietokannassa DB-migraation ajamisen jälkeen → `fetchColumn() ?: 'default'` palauttaa `'default'` (Pitfall 3 ratkaistu)
- Plan 02 voi aloittaa `public/src/includes/theme.php` -shimin rakentamisen

## Self-Check: PASSED

- FOUND: database/migrate_theme.sql
- FOUND: public/themes/default/theme.json
- FOUND: 06-01-SUMMARY.md
- FOUND commit: 114877a (Task 1)
- FOUND commit: 1cabe24 (Task 2)

---
*Phase: 06-teema-infrastruktuuri*
*Completed: 2026-06-22*
