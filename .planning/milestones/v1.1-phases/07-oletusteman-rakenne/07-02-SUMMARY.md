---
phase: 07-oletusteman-rakenne
plan: 02
subsystem: theme-templates
tags: [theme, template-split, xss-preservation]
dependency-graph:
  requires:
    - "public/themes/default/includes/header.php (Plan 01)"
    - "public/themes/default/includes/footer.php (Plan 01)"
  provides:
    - "public/themes/default/pages/index.php"
    - "public/themes/default/pages/hevoset.php"
    - "public/themes/default/pages/kasvatus.php"
    - "public/themes/default/pages/yhteystiedot.php"
  affects:
    - "07-03 (loput sivupohjat: hevonen.php, ajankohtaista.php, postaus.php)"
    - "Phase 8 (resolveThemePath()-kytkentä delegoi kontrollereista näihin sivupohjiin)"
tech-stack:
  added: []
  patterns:
    - "Data/template-erottelu: kontrolleri (public/pages/*.php) säilyttää SQL:n ja getDB()-kutsut, sivupohja (public/themes/default/pages/*.php) sisältää vain HTML:n + muuttujien tulostuksen (D-01)"
    - "Header/footer-include-polku muutettu teeman omiin includeihin (/../includes/) alkuperäisen /../src/includes/ sijaan — ainoa sallittu poikkeama lähteen tekstistä"
key-files:
  created:
    - public/themes/default/pages/index.php
    - public/themes/default/pages/hevoset.php
    - public/themes/default/pages/kasvatus.php
    - public/themes/default/pages/yhteystiedot.php
  modified: []
decisions: []
metrics:
  duration: "~15 min"
  completed: 2026-07-03
---

# Phase 7 Plan 2: Neljän julkisen sivun sivupohjat (index, hevoset, kasvatus, yhteystiedot) Summary

Erotettiin neljän julkisen sivun (index, hevoset, kasvatus, yhteystiedot) HTML-osuudet puhtaiksi sivupohjiksi `public/themes/default/pages/`-kansioon (THEME-06). Datanhakulogiikka (SQL, `getDB()`) jäi muuttumattomana alkuperäisiin `public/pages/*.php`-kontrollereihin.

## What Was Built

- `public/themes/default/pages/index.php` (89 riviä) — etusivun hero, overlay-kortit (hevoset/varsat/ajankohtaista), esittelyteksti ja count-up-`<script>`. `$newsHref`/`$newsTitle`/`$newsExcerpt`/`$newsDate`-presentaatiojohdannaislohko (CR-02-kommentti mukaan lukien) siirrettiin sivupohjaan sellaisenaan, koska se on esitysmuotoilua eikä datanhakua (D-01 discretion). Kuluttaa kontrollerin muuttujat `$horseCount`, `$foalCount`, `$thisYear`, `$latestPost`.
- `public/themes/default/pages/hevoset.php` (73 riviä) — filter-bar, `horse-list-card`-foreach, ehdollinen kuvanäyttö, `$genderFi`-lookup ja filter-`<script>`. Kuluttaa `$horses`.
- `public/themes/default/pages/kasvatus.php` (127 riviä) — odotetut/syntyneet-varsat-foreach, `foal-card`-markup, `$genderFi`-lookup ja filter-`<script>`. Kuluttaa `$allFoals`, `$expected`, `$born`.
- `public/themes/default/pages/yhteystiedot.php` (74 riviä) — `contact-card` ehdollisilla kentillä (sähköposti, nimimerkki, VRL-tunnus, foorumi). Kuluttaa `$stable_name`, `$nickname`, `$vrl_id`, `$email`, `$forum_url`. Yksinkertaisin neljästä sivupohjasta.

Jokaisessa sivupohjassa:
- `$page_title` asetettu staattisena metatietona ennen header-includea (`'Etusivu'`, `'Hevoset'`, `'Kasvatus'`, `'Yhteystiedot'`).
- Header/footer-include muutettu teeman omiin includeihin: `require __DIR__ . '/../includes/header.php'` ja `require __DIR__ . '/../includes/footer.php'` (lähteen `/../src/includes/`-polkujen sijaan — ainoa sallittu poikkeama lähteen tekstistä, split_convention-säännön mukaisesti).
- Jokainen dynaaminen tuloste säilyttää lähteen `e()`-kääreen identtisenä — ei poistoja, lisäyksiä eikä muutoksia XSS-suojaukseen.

Alkuperäiset kontrollerit (`public/pages/{index,hevoset,kasvatus,yhteystiedot}.php`) pysyivät täysin muuttumattomina koko suorituksen ajan — ne toimivat edelleen live-käytössä sellaisenaan.

## Verification Performed

- Kaikki 4 sivupohjaa olemassa `public/themes/default/pages/`-kansiossa (yhteensä 363 riviä).
- Jokainen tehtäväkohtainen automated-verify (PowerShell regex -tarkistus) palautti PASS: `overlay-cards`/`contact-card`/`horse-list-card`/`foal-card` läsnä, ei datanhakua, oikeat include-polut, `e()`-avainkentät läsnä.
- Yhdistetty grep koko sivupohjajoukolle: `getDB\(|->query\(|->prepare\(|->fetchAll\(` — 0 osumaa neljässä sivupohjassa.
- Yhdistetty grep: `\.\./src/includes/` — 0 osumaa (kaikki neljä viittaavat teeman omiin includeihin).
- `git status --short` alkuperäisille kontrollereille (`public/pages/{index,hevoset,kasvatus,yhteystiedot}.php`) — tyhjä sekä ennen että jälkeen jokaisen tehtävän, vahvistaen ne muuttumattomiksi.
- `php -l` (Docker-kontin `virtuaalitalli-web` kautta, PHP 8.2) kaikille neljälle sivupohjalle: "No syntax errors detected" jokaiselle.

## Deviations from Plan

None - plan executed exactly as written.

## Auth Gates

None encountered.

## Known Stubs

None. Sivupohjat odottavat kontrollerin muuttujia (esim. `$horses`, `$latestPost`) — tämä on tarkoituksellinen välitila (D-02), joka ratkeaa Phase 8:n `resolveThemePath()`-kytkennällä. Sivupohjat eivät ole vielä itsenäisesti ajettavissa, mutta tämä on plan-tason odotettu tila, ei stub joka estäisi tämän planin tavoitteen saavuttamista.

## Threat Flags

None. T-07-03 (XSS) ja T-07-04 (Tampering/datanhaku-vuoto) -uhat molemmat mitigoitu suoraan tehtävätason acceptance-kriteereillä (grep-portit varmensivat e()-säilymisen ja SQL:n poissaolon); ei uutta pintaa tuotu plan-suunnitelman ulkopuolelta.

## Self-Check: PASSED

- FOUND: public/themes/default/pages/index.php
- FOUND: public/themes/default/pages/hevoset.php
- FOUND: public/themes/default/pages/kasvatus.php
- FOUND: public/themes/default/pages/yhteystiedot.php
- FOUND commit 9b439dc (feat(07-02): add index.php theme template)
- FOUND commit 844b72c (feat(07-02): add hevoset.php and kasvatus.php theme templates)
- FOUND commit b45bc74 (feat(07-02): add yhteystiedot.php theme template)
