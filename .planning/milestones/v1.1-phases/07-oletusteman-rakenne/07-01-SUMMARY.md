---
phase: 07-oletusteman-rakenne
plan: 01
subsystem: theme-structure
tags: [theme, static-assets, copy]
dependency-graph:
  requires: []
  provides:
    - "public/themes/default/includes/header.php"
    - "public/themes/default/includes/footer.php"
    - "public/themes/default/includes/nav.php"
    - "public/themes/default/assets/css/style.css"
  affects:
    - "07-02 (sivupohjien migraatio, käyttää samaa teemakansiorakennetta)"
    - "07-03 (sivupohjien migraatio, jatkaa saman kansion päälle)"
    - "Phase 8 (resolveThemePath()-kytkentä hyödyntää näitä tiedostoja)"
tech-stack:
  added: []
  patterns:
    - "Byte-identical file copy verified via Get-FileHash (PowerShell), not manual content rewrite"
key-files:
  created:
    - public/themes/default/includes/header.php
    - public/themes/default/includes/footer.php
    - public/themes/default/includes/nav.php
    - public/themes/default/assets/css/style.css
  modified: []
decisions: []
metrics:
  duration: "~10 min"
  completed: 2026-07-03
---

# Phase 7 Plan 1: Oletusteeman perusrakenne (header/footer/nav/style.css) Summary

Kopioitiin nykyisen julkisen ilmeen jaetut osat (`header.php`, `footer.php`, `nav.php`) ja tyylitiedosto (`style.css`) tavu-identtisinä kopioina `public/themes/default/`-teemakansioon PowerShellin `Copy-Item`-komennolla ilman käsin uudelleenkirjoitusta.

## What Was Built

- `public/themes/default/includes/header.php` — tavu-identtinen kopio `public/src/includes/header.php`:sta. Sisältää WR-02 TODO-kommenttilohkon (rivit 42-46) ja `SITE_URL`-pohjaisen CSS-linkin (rivi 47) muuttumattomana (D-05: ei vaihdettu `THEME_URL`:iin). `require_once __DIR__ . '/config.php'` (rivi 12) osoittaa tarkoituksella olemattomaan sisareen — tämä on tietoinen deferred-tila, jonka Phase 8 ratkaisee `resolveThemePath()`-kytkennällä. `require_once __DIR__ . '/nav.php'` (rivi 54) resolvoituu oikein, koska nav.php kopioitiin samaan hakemistoon.
- `public/themes/default/includes/footer.php` — tavu-identtinen kopio `public/src/includes/footer.php`:sta (7 riviä, ei polkuriippuvuuksia).
- `public/themes/default/includes/nav.php` — tavu-identtinen kopio `public/src/includes/nav.php`:sta (18 riviä, käyttää `SITE_URL`-vakiota ja `$_SERVER['REQUEST_URI']`:a, ei sisäisiä require-polkuja).
- `public/themes/default/assets/css/style.css` — tavu-identtinen kopio `public/assets/css/style.css`:stä (1368 riviä). Ei vielä linkitetty mistään live-sivusta (D-06) — pelkkä olemassaolo ja sisältövastaavuus riittävät tässä vaiheessa.
- `public/themes/default/theme.json` — varmennettu olemassa olevaksi (luotu Phase 6:ssa THEME-04:n yhteydessä), sisältää `"name": "Default"` ja `"version": "1.0.0"`. Ei muutettu.

Alkuperäiset lähdetiedostot (`public/src/includes/{header,footer,nav}.php`, `public/assets/css/style.css`) pysyivät täysin koskemattomina koko suorituksen ajan (vahvistettu `git status --short`:lla ennen ja jälkeen kopioinnin — ei muutosmerkintöjä).

## Verification Performed

- `Get-FileHash`-vertailu jokaiselle neljälle kopiolle vs. lähde: kaikki neljä palauttivat `True` (identtiset hashit).
- `grep` header.php:stä: `TODO WR-02`, `<?= SITE_URL ?>/assets/css/style.css` ja `require_once __DIR__ . '/nav.php';` kaikki läsnä muuttumattomina.
- `public/themes/default/assets/css/style.css` rivimäärä: 1368 (vastaa lähteen rivimäärää, min_lines-vaatimus täyttyi).
- `theme.json` JSON-parse onnistui, `name` ja `version` molemmat ei-tyhjiä.
- `git status --short public/src/includes/ public/assets/css/style.css` palautti tyhjän tuloksen sekä ennen että jälkeen kopioinnin — alkuperäiset tiedostot todistetusti muuttumattomia.

## Deviations from Plan

None - plan executed exactly as written.

## Auth Gates

None encountered.

## Known Stubs

None. This plan is a pure verbatim-copy operation; the copied files are not yet wired into live routing (intentional per D-04, deferred to Phase 8), which is the documented and expected state — not a stub requiring resolution tracking.

## Threat Flags

None. Both threats identified in the plan's threat model (T-07-01 tampering mitigation via hash-verified verbatim copy, T-07-02 accepted deferred config.php path) were already addressed by the plan design; no new surface was introduced beyond what's documented there.

## Self-Check: PASSED

- FOUND: public/themes/default/includes/header.php
- FOUND: public/themes/default/includes/footer.php
- FOUND: public/themes/default/includes/nav.php
- FOUND: public/themes/default/assets/css/style.css
- FOUND: public/themes/default/theme.json
- FOUND commit fc03a82 (feat(07-01): copy header/footer/nav to default theme includes)
- FOUND commit c298be8 (feat(07-01): copy style.css to default theme assets)
