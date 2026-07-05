---
phase: 06-teema-infrastruktuuri
plan: "02"
subsystem: theme-shim
tags: [php, theme-system, path-security, realpath, constants, settings-table]

# Dependency graph
requires:
  - phase: 06-01
    provides: public/themes/default/ hakemistopolku (realpath resoloituu) ja active_theme-rivi DB:ssä
  - phase: 01-perusta-tietokantarakenne
    provides: getDB()-singleton, settings-taulu, SITE_URL-vakio
provides:
  - public/src/includes/theme.php — THEME_PATH/THEME_URL/THEMES_ROOT-vakiot ja resolveThemePath()-funktio
  - public/pages/index.php — shimin integraatiotodistus (yksi require_once-rivi)
affects:
  - Phase 8: sivukontrollerien migraatio käyttää resolveThemePath()-funktiota kaikkien sivujen renderöintiin
  - Phase 9: Altervista-yhteensopivuustestaus (str_starts_with PHP 8.0+ -vaatimus)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "theme.php-shim: if(!defined)-guard ympäröi vakiomäärittelyt — yhdenmukainen db.php-guardin kanssa"
    - "Elvis-fallback fetchColumn() ?: 'default' — PDO fetchColumn() palauttaa false puuttuvalle riville"
    - "resolveThemePath: realpath() + str_starts_with prefix-check (trailing DIRECTORY_SEPARATOR pakollinen)"
    - "D-09 admin-eristys: theme.php require_oncetaan vain public/pages/:stä, ei db.php:stä"

key-files:
  created:
    - public/src/includes/theme.php
  modified:
    - public/pages/index.php

key-decisions:
  - "str_starts_with() ja string|false union type — PHP 8.0+ (Docker/Linux kehitysympäristö tukee; Altervista varmistetaan Phase 9)"
  - "realpath()-fallback jos themes/-hakemisto puuttuu — shim ei kuole, resolveThemePath() palauttaa false"
  - "Trailing DIRECTORY_SEPARATOR THEME_PATH:ssa — estää /themes/defaultevil/-tyyppiset prefix-bypass-hyökkäykset"

patterns-established:
  - "resolveThemePath()-kutsukaava Phase 8:ssa: require resolveThemePath('pages/index.php') tai require resolveThemePath('includes/header.php')"
  - "Shim ladataan vain public/pages/-tiedostoissa — admin-sivut eivät koskaan saa theme.php:tä"

requirements-completed: [THEME-01, THEME-03]

# Metrics
duration: 20min
completed: 2026-06-22
---

# Phase 6 Plan 02: Teemashim — theme.php vakiot ja resolveThemePath Summary

**theme.php-shim määrittelee THEME_PATH/THEME_URL/THEMES_ROOT-vakiot settings-taulun active_theme-rivin perusteella ja tarjoaa path-traversal-suojatun resolveThemePath()-funktion realpath() + str_starts_with prefix-checkillä**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-06-22T15:45:00Z
- **Completed:** 2026-06-22T16:05:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Luotu `public/src/includes/theme.php` joka lukee aktiivisen teeman settings-taulusta prepared statementilla, validoi teemanimiallowlistilla ja määrittelee THEME_PATH-, THEME_URL- ja THEMES_ROOT-vakiot if(!defined)-guardin sisällä
- Toteutettu `resolveThemePath(string $subPath): string|false` funktiolla realpath() + str_starts_with prefix-check (D-04) ja aktiivinen teema → default-fallback → false logiikalla (D-05)
- Lisätty `public/pages/index.php`:hen TASAN yksi rivi integraatiotodistukseksi: `require_once __DIR__ . '/../src/includes/theme.php';` db.php-rivin jälkeen (D-07)
- PHP-syntaksitarkistus molemmille tiedostoille: `No syntax errors detected` (Docker PHP CLI)

## Task Commits

Jokainen tehtävä commitoitu atomisesti:

1. **Task 1: Luo theme.php-shim vakioineen ja resolveThemePath()-funktiolla** — `77c9bdf` (feat)
2. **Task 2: Lisää theme.php-integraatiotodistus index.php:hen** — `4692527` (feat)

## Files Created/Modified

- `public/src/includes/theme.php` — Uusi: teemashim joka tarjoaa THEME_PATH/THEME_URL/THEMES_ROOT + resolveThemePath() (87 riviä)
- `public/pages/index.php` — Muutettu: yksi `require_once theme.php`-rivi lisätty riville 3 (kaikki muu logiikka muuttumaton)

## Decisions Made

- Noudatettu D-03/D-04/D-05/D-06: resolveThemePath()-signatuuri, turvallisuuslogiikka, fallback ja palautusarvo suunnitelman mukaan
- PHP 8.0+ syntaksi (str_starts_with, string|false union type) — kehitysympäristö (Docker) tukee; Altervista-yhteensopivuus varmistetaan Phase 9:ssä
- realpath()-fallback lisätty jos `public/themes/`-hakemisto puuttuu — shim ei kuole bootstrap-vaiheessa (Pitfall 2)
- Elvis-operator (`?:`) fetchColumn()-kutsulle (ei `??`) — PDO palauttaa `false` puuttuvalle riville (Pitfall 3)

## Deviations from Plan

None — plan executed exactly as written.

## Verification Notes

**Automaattinen (grep + PHP lint):**
- `php -l public/src/includes/theme.php` → No syntax errors (Docker CLI)
- `php -l public/pages/index.php` → No syntax errors (Docker CLI)
- `grep "function resolveThemePath"` → osuma
- `grep "str_starts_with"` → osuma (ei strpos prefix-checkiin)
- `grep -c "require_once.*theme.php" public/pages/index.php` → 1
- `grep "fetchColumn() ?:"` → osuma (Elvis, ei ??)

**Manuaalinen (selain, TODO käyttäjälle):**
- Avaa `http://localhost:8080/pages/index.php` — sivu latautuu ilman virheitä
- var_dump(THEME_PATH, THEME_URL) → molemmat määritelty merkkijonoina
- var_dump(resolveThemePath('../../etc/passwd')) → false (path-traversal torjuttu)
- var_dump(resolveThemePath('theme.json')) → absoluuttinen polku joka alkaa THEME_PATH:lla
- Admin-eristys: lataa admin-sivu, var_dump(defined('THEME_PATH')) → false

**Huom:** DB-migraatio `database/migrate_theme.sql` on ajettava phpMyAdminissa ennen manuaalista testausta (katso 06-01-SUMMARY.md).

## Known Stubs

Ei stubs — shim käyttää oikeaa DB-dataa. Fallback `'default'` on oikea käyttäytyminen kun `active_theme`-riviä ei vielä ole DB:ssä tai Elvis-evaluointi palauttaa false.

## Threat Flags

| Flag | File | Description |
|------|------|-------------|
| threat_flag: path-traversal mitigated | public/src/includes/theme.php | resolveThemePath() käyttää realpath() + str_starts_with prefix-check — kaikki syötteet normalisoidaan OS-tasolla ennen prefixvertailua |
| threat_flag: theme-name injection mitigated | public/src/includes/theme.php | DB:stä luettu active_theme validoidaan preg_match('/^[a-zA-Z0-9_-]+$/') allowlistilla ennen tiedosto-operaatioita |
| threat_flag: sql-injection mitigated | public/src/includes/theme.php | active_theme luetaan named placeholder -prepared statementilla (SEC-01-pattern) |
| threat_flag: admin isolation enforced | public/src/includes/theme.php | theme.php require_oncetaan vain index.php:stä; docblock-varoitus db.php-lisäyksen estämiseksi (D-09) |

## Self-Check: PASSED

- FOUND: public/src/includes/theme.php
- FOUND: public/pages/index.php (modified)
- FOUND commit: 77c9bdf (Task 1 — theme.php)
- FOUND commit: 4692527 (Task 2 — index.php)
- FOUND: 06-02-SUMMARY.md

---
*Phase: 06-teema-infrastruktuuri*
*Completed: 2026-06-22*
