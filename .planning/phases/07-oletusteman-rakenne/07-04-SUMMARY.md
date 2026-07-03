---
phase: 07-oletusteman-rakenne
plan: 04
subsystem: theme-templates
tags: [theme, template-split, xss-preservation, blog]
dependency-graph:
  requires:
    - "public/themes/default/includes/header.php (Plan 01)"
    - "public/themes/default/includes/footer.php (Plan 01)"
  provides:
    - "public/themes/default/pages/ajankohtaista.php"
    - "public/themes/default/pages/postaus.php"
  affects:
    - "Phase 8 (resolveThemePath()-kytkentä delegoi kontrollereista näihin sivupohjiin)"
tech-stack:
  added: []
  patterns:
    - "Data/template-erottelu: kontrolleri (public/pages/*.php) säilyttää SQL:n, getDB()-kutsut ja postaus.php:n 404-early-exit-haarat, sivupohja (public/themes/default/pages/*.php) sisältää vain HTML:n + muuttujien tulostuksen (D-01)"
    - "Header/footer-include-polku muutettu teeman omiin includeihin (/../includes/) alkuperäisen /../src/includes/ sijaan — ainoa sallittu poikkeama lähteen tekstistä"
    - "$MONTHS_FI presentaatio-lookup kopioitu sivupohjaan (ei jätetä kontrolleriin), samalla periaatteella kuin $genderFi Plan 07-02:ssa"
key-files:
  created:
    - public/themes/default/pages/ajankohtaista.php
    - public/themes/default/pages/postaus.php
  modified: []
decisions: []
requirements-completed: [THEME-06]
metrics:
  duration: "~10 min"
  completed: 2026-07-03
status: complete
---

# Phase 7 Plan 4: Blogilistauksen ja yksittäisen postauksen sivupohjat (ajankohtaista, postaus) Summary

Erotettiin blogilistauksen (`ajankohtaista.php`) ja yksittäisen postauksen (`postaus.php`) HTML-osuudet puhtaiksi sivupohjiksi `public/themes/default/pages/`-kansioon (THEME-06). Datanhakulogiikka (SQL, `getDB()`) sekä `postaus.php`:n 404-early-exit-haarat jäivät muuttumattomana alkuperäisiin `public/pages/*.php`-kontrollereihin.

## What Was Built

- `public/themes/default/pages/ajankohtaista.php` (91 riviä) — postauslista `post-list-card`-foreach mukaan lukien `$excerpt`-johdannainen, filter-info-lohko, arkisto-sidebar `archive-sidebar` mukaan lukien `$firstYr = array_key_first($archive)`. `$MONTHS_FI`-presentaatiolookup ja staattinen `$page_title = 'Ajankohtaista'` asetettu sivupohjan alkuun ennen header-includea. Kuluttaa kontrollerin muuttujat `$posts`, `$archive`, `$yearFilter`, `$monthFilter`.
- `public/themes/default/pages/postaus.php` (85 riviä) — artikkeli `post-body` (`nl2br(e($post['content']))`), prev/next-navigaatio `post-prevnext`, arkisto-sidebar `archive-sidebar` mukaan lukien `$postYear`/`$postMonth`-johdannaiset. `$MONTHS_FI`-presentaatiolookup kopioitu sivupohjan alkuun. `$page_title` EI asetettu sivupohjassa — lähteessä se on dynaaminen (`$post['title']`) ja jää kontrolleriin. Kuluttaa `$post`, `$prev`, `$next`, `$archive`.

Molemmissa sivupohjissa:
- Header/footer-include muutettu teeman omiin includeihin: `require __DIR__ . '/../includes/header.php'` ja `require __DIR__ . '/../includes/footer.php'` (lähteen `/../src/includes/`-polkujen sijaan — ainoa sallittu poikkeama lähteen tekstistä, split_convention-säännön mukaisesti).
- Jokainen dynaaminen tuloste säilyttää lähteen `e()`-kääreen identtisenä — ei poistoja, lisäyksiä eikä muutoksia XSS-suojaukseen (mukaan lukien `nl2br(e($post['content']))`).
- `postaus.php`:n 404-early-exit-logiikka (lähteen rivit 15-22 ja 26-33, `http_response_code(404)` + mini-header/footer-render + `exit`) jätettiin kokonaan pois sivupohjasta — se pysyy kontrollerissa, koska se renderöityy ennen split-rajaa.

Alkuperäiset kontrollerit (`public/pages/ajankohtaista.php`, `public/pages/postaus.php`) pysyivät täysin muuttumattomina koko suorituksen ajan — ne toimivat edelleen live-käytössä sellaisenaan.

## Task Commits

1. **Task 1: ajankohtaista.php-sivupohja** - `f813845` (feat)
2. **Task 2: postaus.php-sivupohja** - `b424710` (feat)

## Verification Performed

- Molemmat sivupohjat olemassa `public/themes/default/pages/`-kansiossa (yhteensä 176 riviä).
- Grep `getDB\(|->query\(|->prepare\(|->execute\(|->fetchAll\(` — 0 osumaa `ajankohtaista.php`:ssa.
- Grep `getDB\(|->query\(|->prepare\(|->execute\(|->fetch\(|http_response_code` — 0 osumaa `postaus.php`:ssa (varmistaa myös 404-logiikan poissaolon).
- Molemmat viittaavat teeman omiin includeihin (`/../includes/`); ei osumia `/../src/includes/`-polulle.
- e()-avainkentät läsnä: `e($post['title'])` ja `e($excerpt)` (`ajankohtaista.php`); `nl2br(e($post['content']))` ja `e($post['title'])` (`postaus.php`).
- `git status --short` alkuperäisille kontrollereille (`public/pages/ajankohtaista.php`, `public/pages/postaus.php`) — tyhjä sekä ennen että jälkeen kummankin tehtävän, vahvistaen ne muuttumattomiksi.
- `php -l` ei ollut ajettavissa paikallisesti (php-komento ei löytynyt shellistä); PowerShell-regex-tarkistukset ja manuaalinen rakenteen tarkastus toimivat verifiointina tässä ympäristössä.

## Decisions Made

None - followed plan as specified.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Auth Gates

None encountered.

## Known Stubs

None. Sivupohjat odottavat kontrollerin muuttujia (esim. `$posts`, `$post`, `$archive`) — tämä on tarkoituksellinen välitila (D-02), joka ratkeaa Phase 8:n `resolveThemePath()`-kytkennällä. Sivupohjat eivät ole vielä itsenäisesti ajettavissa, mutta tämä on plan-tason odotettu tila, ei stub joka estäisi tämän planin tavoitteen saavuttamista.

## Threat Flags

None. T-07-07 (Injection/XSS) ja T-07-08 (Tampering/datanhaku- ja 404-logiikan vuoto) -uhat molemmat mitigoitu suoraan tehtävätason acceptance-kriteereillä (grep-portit varmensivat e()-säilymisen, SQL:n poissaolon ja 404-haarojen poissaolon); ei uutta pintaa tuotu plan-suunnitelman ulkopuolelta.

## Next Phase Readiness

- `public/themes/default/pages/` sisältää nyt kaikki 7/7 phase-tavoitteen sivupohjaa (index, hevoset, kasvatus, yhteystiedot, hevonen, ajankohtaista, postaus) — Phase 7:n koko sivupohjajoukko on valmis.
- Phase 8 voi alkaa muuttaa kontrollereita data-only-muotoon, joka delegoi renderöinnin näille sivupohjille `resolveThemePath()`:n kautta.
- Ei blockereita.

## Self-Check: PASSED

- FOUND: public/themes/default/pages/ajankohtaista.php
- FOUND: public/themes/default/pages/postaus.php
- FOUND commit f813845 (feat(07-04): add ajankohtaista.php theme template)
- FOUND commit b424710 (feat(07-04): add postaus.php theme template)

---
*Phase: 07-oletusteman-rakenne*
*Completed: 2026-07-03*
