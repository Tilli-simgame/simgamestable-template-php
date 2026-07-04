# Phase 8: Sivukontrollerien migraatio - Context

**Gathered:** 2026-07-04
**Status:** Ready for planning

<domain>
## Phase Boundary

Kaikki 7 julkista sivukontrolleria (`public/pages/index.php`, `hevoset.php`, `hevonen.php`, `kasvatus.php`, `yhteystiedot.php`, `ajankohtaista.php`, `postaus.php`) muuttuvat data-only-kontrollereiksi: ne hakevat datan tietokannasta ja delegoivat KAIKEN HTML-renderöinnin aktiivisen teeman sivupohjille `resolveThemePath('pages/X.php')`:n kautta. Nykyinen inline-HTML (header.php-require + suora HTML + footer.php-require) poistetaan kontrollereista — se on jo olemassa puhtaana templatena `public/themes/default/pages/`-kansiossa (Phase 7:n tuotos).

Lisäksi: `index.php`:ssa ja `hevonen.php`:ssä jo olemassa oleva "juuritason täysoverride"-koukku (teema voi tarjota koko sivun oman version teeman juuresta, esim. `themes/{teema}/index.php`) laajennetaan samalla kaavalla kaikkiin 7 kontrolleriin. Admin-puoli ei kuulu tähän vaiheeseen. Teeman valintanäkymä (admin) on Phase 9:ää.

</domain>

<decisions>
## Implementation Decisions

### Kaksoismekanismi: root-override + data-only pages/-malli
- **D-01:** Molemmat teemaintegraatiomekanismit säilytetään rinnakkain — ei konsolidoida yhteen malliin.
  - **Malli A (uusi, tämän phasen ydin):** Kontrolleri hakee datan, kutsuu `require resolveThemePath('pages/X.php')` → aktiivisen teeman puhdas HTML-template (`themes/default/pages/*.php`, Phase 7:n tuotos) renderöi datan.
  - **Malli B (jo olemassa index.php/hevonen.php:ssä, laajennetaan kaikkiin 7):** Jos non-default-teemalla on juuritason tiedosto samalla nimellä kuin kontrolleri (esim. `themes/oma-talli/hevoset.php`), koko kontrollerin normaali suoritus ohitetaan ja teeman oma tiedosto ajetaan sellaisenaan (täysi itsenäinen sivu, ei data-only-sopimusta).
- **D-02:** Malli B:n ehtorakenne kopioidaan identtisenä kaikkiin 7 kontrolleriin, samalla logiikalla kuin `index.php`:ssä/`hevonen.php`:ssä tänään: `resolveThemePath('{controllerin_tiedostonimi}.php')` (juuritasolla, EI `pages/`-alikansiossa) + tarkistus `!str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)` (koskee vain non-default-teemoja — default-teema käyttää AINA Mallia A).
- **D-03:** Rationale: `public/themes/oma-talli/` (sitoutumaton, käyttäjän aktiivinen WIP-teema tälle milestonelle) on rakenteeltaan "täysi custom-teema" -tyyppinen (juuritason `index.php`, `hevonen.php`, oma `theme.json`-manifest nav/pages-taulukoineen `theme-page.php`:ta varten). Sen omat sivut (`asukkaat.php`, `esittely.php`, `toiminta.php` jne.) eivät vastaa suoraan 5 perussivun nimiä, joten Mallin B laajennus ei suoraan "korjaa" oma-tallia — se on symmetria-/tulevaisuusvarautuminen mahdollisille teemoille jotka haluavat korvata jonkin perussivun (esim. `kasvatus.php`) kokonaan omalla juuritason tiedostollaan.

### Vanhan inline-HTML:n poisto
- **D-04:** Kun kontrolleri delegoi `resolveThemePath('pages/X.php')`:iin, sen sisältämä vanha inline-HTML (header-require + HTML-body + footer-require) poistetaan kokonaan kontrollerista — ei jätetä kuolleeksi koodiksi. Tämä toteuttaa Phase 7:n D-04-päätöksen ("Phase 8 poistaa vanhat kopiot/duplikaatit").
- **D-05:** Kontrollerin data-only-osiossa turhiksi jäävät apumuuttujat/-funktiot jotka on jo kopioitu puhtaisiin templateihin (`$genderFi`, `$MONTHS_FI`, `pedigreeHorseLink()`, `pedigreeCell()`) poistetaan kontrollerista — templatet määrittelevät ne itse (vahvistettu koodista, Phase 7:n D-mukainen duplikaatio).

### Testiteema Success Criteria #3:n todistamiseksi
- **D-06:** Phase 8:n aikana luodaan kevyt, väliaikainen testiteema (kopio `default`-teemasta + pieni visuaalinen muutos, esim. värimuutos tai bannerin teksti) sen todistamiseksi että teeman vaihto DB:ssä (`settings.active_theme`) muuttaa ulkoasun ilman kontrollerimuutoksia (Success Criteria #3).
- **D-07:** Testiteema on kertakäyttöinen apuväline — poistetaan Phase 8:n päätteeksi (ennen phase-loppuunsaattamista). Ei jätetä pysyväksi asennetuksi teemaksi eikä sekoiteta `oma-talli`-teeman (käyttäjän oikea WIP-teema, ks. Specific Ideas) kanssa.

### Claude's Discretion
- Tarkka tapa jakaa data-only-logiikka pieniin apumuuttujiin kussakin 7 kontrollerissa (esim. mitkä muuttujat annetaan templatelle) — templatet on jo kirjoitettu Phase 7:ssä ja sanelevat tarkan muuttujasopimuksen (esim. `$horseCount`, `$foalCount`, `$stable_name`, `$vrl_id`). Kontrollerin pitää tuottaa täsmälleen nämä.
- 404/virhekäsittely kun `resolveThemePath()` palauttaisi false (käytännössä epätodennäköistä koska default-teema sisältää aina kaikki 7 sivupohjaa) — voidaan yhdenmukaistaa `theme-page.php`:n `http_response_code(404)`-mallin mukaiseksi, ei vaadi erillistä käyttäjäpäätöstä.
- Testiteeman tarkka nimi/slug ja visuaalinen muutos.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Vaatimukset ja roadmap
- `.planning/REQUIREMENTS.md` §v1.1 THEME-08–THEME-09 — Phase 8:n vaatimukset (data-only-kontrollerit, resolveThemePath())
- `.planning/ROADMAP.md` Phase 8 -osio (rivit 180-192) — tavoite ja success criteria

### Phase 6/7:n tuottama infrastruktuuri (käytetään, ei muokata sisällöltään)
- `public/src/includes/theme.php` — `resolveThemePath()`, `THEME_PATH`, `THEME_URL`, `THEMES_ROOT`, `getThemeManifest()`
- `public/themes/default/pages/*.php` — Phase 7:n tuottamat puhtaat templatet (index, hevoset, hevonen, kasvatus, yhteystiedot, ajankohtaista, postaus) — näiden muuttujasopimus (esim. `$horseCount`, `$stable_name`, `$genderFi` templaten sisällä) sanelee mitä data-only-kontrollerin pitää tarjota
- `public/pages/theme-page.php` — jo olemassa oleva generic-kontrolleri manifest-pohjaisille (theme.json nav/pages) mukautetuille sivuille; malliesimerkki `http_response_code(404)`-käsittelylle
- `.planning/phases/07-oletusteman-rakenne/07-CONTEXT.md` — Phase 7:n päätökset (D-03/D-04/D-05/D-06/D-07: kopiointiperiaate, WR-02-kytkin Phase 8:aan, sivujen nimeäminen)
- `.planning/phases/06-teema-infrastruktuuri/06-CONTEXT.md` — Phase 6:n päätökset (admin-eristys, style.css koskemattomuus)

### Nykyiset kontrollerit joita muutetaan (kohde)
- `public/pages/index.php` — sisältää jo Mallin B -koukun (rivit 6-12), pitää yhtenäistää muiden kanssa + lisätä Malli A -delegointi
- `public/pages/hevonen.php` — sisältää jo Mallin B -koukun, pitää säilyttää + lisätä Malli A -delegointi loppuosaan
- `public/pages/hevoset.php`, `kasvatus.php`, `yhteystiedot.php`, `ajankohtaista.php`, `postaus.php` — ei vielä Mallin B -koukkua eikä Malli A -delegointia, lisätään molemmat

### Ei muokata Phase 8:ssa
- `public/admin/*` — ei kosketa
- `public/themes/oma-talli/` — käyttäjän aktiivinen WIP-teema, ei muokata tai korjata rakenteellisesti tässä phasessa (ks. Specifics)
- `public/pages/theme-page.php` — jo valmis generic-kontrolleri, ei osa 7 vakiosivun listaa

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resolveThemePath(string $subPath): string|false` (`theme.php`) — path-traversal-suojattu, default-fallback sisäänrakennettuna. Käytetään sekä Malli A:ssa (`resolveThemePath('pages/X.php')`) että Malli B:ssä (`resolveThemePath('X.php')`, juuritasolla).
- `public/themes/default/pages/*.php` — kaikki 7 puhdasta templatea ovat jo olemassa ja odottavat vain oikeita muuttujia kontrollerilta.

### Established Patterns
- **Mallin B nykyinen toteutus** (`index.php` rivit 6-12, `hevonen.php` rivit 4-10): `resolveThemePath('{tiedosto}.php')` + `str_starts_with(THEME_PATH, THEMES_ROOT.'default'.DIRECTORY_SEPARATOR)`-tarkistus estää default-teemaa käyttämästä juuritason overridea. Tämä ehtorakenne kopioidaan sellaisenaan 5 muuhun kontrolleriin.
- **XSS-suojaus:** kaikki templatet käyttävät `e()`/`htmlspecialchars()`-helperia (`helpers.php`) — säilyy muuttumattomana, kontrollerin data-only-osuus ei renderöi HTML:ää lainkaan.
- **Muuttujasopimus templaten ja kontrollerin välillä:** `require` suorittaa templaten kontrollerin scope-kontekstissa, joten kontrollerin asettamat muuttujat (esim. `$page_title`, `$horses`, `$post`) ovat suoraan templaten käytettävissä ilman erillistä parametrien välitystä.

### Integration Points
- `public/pages/*.php` (7 kpl) — data-only-kontrollerit, uusi loppurivi per sivu: `require resolveThemePath('pages/{sivu}.php');` (Malli A) Mallin B -koukun jälkeen.
- `settings`-taulu (`active_theme`-rivi) — teeman vaihto tapahtuu tätä kautta (`theme.php` lukee sen), ei kontrollerimuutoksia tarvita minkään teeman kohdalla.

</code_context>

<specifics>
## Specific Ideas

- `public/themes/oma-talli/` (sitoutumaton kansio + zip git statuksessa) on käyttäjän aktiivinen työn alla oleva toinen teema tälle milestonelle — ei placeholder tai vanhan sivuston roskaa. Sen rakenne (juuritason `index.php`/`hevonen.php`, oma `theme.json` nav/pages-manifesti, muut sivut kuten `asukkaat.php`/`esittely.php`/`toiminta.php` theme-page.php:n kautta) ei vaadi muutoksia Phase 8:ssa, mutta on tärkeä konteksti sille MIKSI Mallin B -koukku laajennetaan kaikkiin 7 kontrolleriin (symmetria/tulevaisuusvarautuminen, ei suoraa oma-talli-korjausta).
- Testiteema (D-06/D-07) on eri asia kuin oma-talli — pieni tilapäinen kopio `default`-teemasta, poistetaan phasen lopussa.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. `oma-talli`-teeman rakenteellinen viimeistely (nav-yhteensopivuus, mahdolliset puuttuvat vakiosivut) ei kuulu Phase 8:aan eikä Phase 9:ään (admin-teemavalinta) — jää tulevaksi työksi jos/kun oma-talli otetaan käyttöön.

</deferred>

---

*Phase: 8-Sivukontrollerien-migraatio*
*Context gathered: 2026-07-04*
