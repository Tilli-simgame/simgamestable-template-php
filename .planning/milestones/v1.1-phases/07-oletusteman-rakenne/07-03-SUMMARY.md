---
phase: 07-oletusteman-rakenne
plan: 03
subsystem: ui
tags: [php, theme, template-split, xss]

# Dependency graph
requires:
  - phase: 07-01
    provides: "Oletusteman perusrakenne (public/themes/default/includes/header.php, footer.php, nav.php)"
provides:
  - "public/themes/default/pages/hevonen.php — puhdas HTML-sivupohja yksittäisen hevosen profiilille"
affects: [08-sivukontrollerien-migraatio]

# Tech tracking
tech-stack:
  added: []
  patterns: ["template-split (data/HTML erottelu, D-01/D-02)"]

key-files:
  created: [public/themes/default/pages/hevonen.php]
  modified: []

key-decisions:
  - "pedigreeHorseLink() ja pedigreeCell() säilytetty sivupohjan sisällä (ei siirretty helpers.php:hen) — PHP ei salli kaksoismäärittelyä ja lähteessäkin ne ovat header-includen jälkeen"

patterns-established: []

requirements-completed: [THEME-06]

coverage:
  - id: D1
    description: "hevonen.php-sivupohja luotu puhtaana HTML+muuttuja-templatena (614-rivisen lähteen rivit 145-614), sisältää hero-bannerin, perustiedot, kilpailut, näyttelyt, kuvagallerian+lightboxin, varsat, postaukset ja sukutaulun"
    requirement: THEME-06
    verification:
      - kind: automated_ui
        ref: "powershell Grep-verifiointi: rivimäärä >=400, sisältää hero-banner/pedigreeHorseLink/pedigreeCell, ei getDB()/->prepare()/->fetchAll()/getHorsePedigree()/resolveThemePath()/http_response_code, viittaa /../includes/header.php ja /../includes/footer.php, ei /../src/includes/, sisältää e($horse['name'])"
        status: pass
    human_judgment: false

# Metrics
duration: 12min
completed: 2026-07-03
status: complete
---

# Phase 07 Plan 03: Hevonen.php-sivupohja Summary

**Yksittäisen hevosen profiilisivun (614-rivinen, monimutkaisin 7 sivupohjasta) HTML-osuus eroteltu puhtaaksi templateksi `public/themes/default/pages/hevonen.php`-tiedostoon; datanhaku, teema-hook ja 404-logiikka jäävät alkuperäiseen kontrolleriin muuttumattomana.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-07-03T08:05:00Z (arvio istunnon alusta)
- **Completed:** 2026-07-03
- **Tasks:** 1
- **Files modified:** 1 (uusi tiedosto)

## Accomplishments
- `public/themes/default/pages/hevonen.php` luotu (471 riviä) — sisältää hero-bannerin, perustiedot-sivupalkin, omistus/kasvatus-kortin, sukutaulun (`pedigreeHorseLink()`/`pedigreeCell()`-apufunktiot mukana), varsalistan, kisakalenterin, näyttelytulokset, postaukset, kuvagallerian + lightbox-modalin ja inline-scriptin
- Header/footer-includet muutettu osoittamaan teeman omiin includeihin (`/../includes/header.php`, `/../includes/footer.php`) lähteen `/../src/includes/`-polkujen sijaan — ainoa sallittu poikkeama lähteen tekstistä
- Kaikki `e()`-escape-kutsut ja `filter_var(...FILTER_VALIDATE_URL)`-validointi säilytetty identtisinä lähteen kanssa (XSS-suojaus, T-07-05)
- Alkuperäinen kontrolleri `public/pages/hevonen.php` pysyy täysin muuttumattomana (git diff tyhjä)

## Task Commits

Each task was committed atomically:

1. **Task 1: hevonen.php-sivupohja (yksittäisen hevosen profiili)** - `fbd3431` (feat)

**Plan metadata:** (seuraa tätä committia)

## Files Created/Modified
- `public/themes/default/pages/hevonen.php` - Yksittäisen hevosen profiilisivupohja (471 riviä): hero-banneri, perustiedot, sukutaulu, kilpailut, näyttelyt, varsat, postaukset, kuvagalleria+lightbox

## Decisions Made
- `pedigreeHorseLink()` ja `pedigreeCell()` säilytetty sivupohjan sisällä (ei siirretty jaettuun `helpers.php`-tiedostoon), koska PHP ei salli funktioiden kaksoismäärittelyä ja lähteessäkin ne on määritelty header-includen jälkeen — sama sijoittelu kuin split_convention-lohko ohjeisti

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None. PHP CLI ei ollut käytettävissä `php -l`-syntaksitarkistukseen tässä ympäristössä, mutta sisältö on rivikohtainen verbatim-kopio jo validoidusta lähdekoodista (vain kaksi include-polkua muutettu), joten syntaksiriski on minimaalinen. Kaikki plan.md:n powershell-pohjaiset acceptance-kriteerit ajettiin ja läpäistiin.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- 5/7 oletusteman sivupohjaa valmiina (index, hevoset, kasvatus, yhteystiedot, hevonen)
- Phase 8 voi delegoi `public/pages/hevonen.php`:n renderöinnin tälle sivupohjalle `resolveThemePath()`:n kautta kun kontrolleri muutetaan data-only-muotoon
- Jäljellä: ajankohtaista.php ja postaus.php -sivupohjat (myöhemmät planit)

---
*Phase: 07-oletusteman-rakenne*
*Completed: 2026-07-03*

## Self-Check: PASSED
- FOUND: public/themes/default/pages/hevonen.php
- FOUND: fbd3431 (git log)
