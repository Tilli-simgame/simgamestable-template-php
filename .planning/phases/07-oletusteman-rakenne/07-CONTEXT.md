# Phase 7: Oletusteman rakenne - Context

**Gathered:** 2026-07-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Nykyinen julkinen ilme kopioidaan `public/themes/default/`-rakenteeseen (THEME-05, THEME-06, THEME-07): `includes/` (header, footer, nav), `pages/` (7 sivupohjaa täysin HTML-templateiksi eroteltuna) ja `assets/css/style.css`. Sivusto näyttää ja toimii täsmälleen ennallaan, koska nykyiset `public/pages/*.php`-kontrollerit ja `public/src/includes/*`-tiedostot pysyvät muuttumattomina ja käytössä koko Phase 7:n ajan — uusi themes/default/-kopio on rinnakkainen, ei vielä kytketty live-reititykseen. Kontrollerien kytkeminen `resolveThemePath()`:iin ja vanhojen tiedostojen poisto kuuluvat Phase 8:aan. Admin-puoli ei kuulu tähän vaiheeseen.

</domain>

<decisions>
## Implementation Decisions

### Template-jaon syvyys
- **D-01:** `themes/default/pages/*.php` kirjoitetaan Phase 7:ssä puhtaina templateina — vain HTML + muuttujien tulostus (`<?= $var ?>`), ei SQL-kyselyitä tai datanhakua. Data-hakulogiikka (SQL, laskennat) jää nykyisiin `public/pages/*.php`-tiedostoihin ennallaan Phase 8:aan asti.
- **D-02:** Käytännössä tämä tarkoittaa jokaisen 7 sivun (index, hevoset, hevonen, kasvatus, yhteystiedot, ajankohtaista, postaus) datanhaku- ja HTML-osien erottelua nyt, vaikka kytkentä (controller → resolveThemePath() → template) tehdään vasta Phase 8:ssa. Templatet eivät ole vielä suoraan ajettavissa itsenäisesti (odottavat muuttujia kontrollerilta) — tämä on odotettu välitila.

### Siirto vs. kopiointi
- **D-03:** `header.php`, `footer.php`, `nav.php` ja `style.css` **kopioidaan** (ei siirretä) `themes/default/`-kansioon. Alkuperäiset tiedostot `public/src/includes/` ja `public/assets/css/` pysyvät paikallaan ja käytössä muuttumattomina — admin-puoli riippuu `public/assets/css/style.css`:stä (D-10, Phase 6).
- **D-04:** Phase 8 poistaa vanhat kopiot/duplikaatit sen jälkeen kun kontrollerit on kytketty käyttämään `resolveThemePath()`:ia. Phase 7:n aikana on siis tarkoituksella kaksi kopiota header/footer/nav/CSS:stä.

### CSS-kytkin (WR-02)
- **D-05:** `header.php`:n TODO-merkintää (rivi 42-46, WR-02: vaihda `SITE_URL`-pohjainen CSS-linkki `THEME_URL`-pohjaiseksi) **ei** toteuteta Phase 7:ssä. `header.php` pysyy muuttumattomana ja lataa edelleen `public/assets/css/style.css`:n. Kytkin tehdään Phase 8:ssa kontrollerimigraation yhteydessä — johdonmukaista D-03/D-04:n kanssa.
- **D-06:** `themes/default/assets/css/style.css` on olemassa ja identtinen `public/assets/css/style.css`:n kanssa, mutta ei vielä aktiivisessa käytössä (Success Criteria #3 täyttyy tiedoston olemassaolon ja sisällön osalta, ei vielä kytkennän).

### Sivujen nimeäminen
- **D-07:** ROADMAP-teksti "blogi" viittaa toiminnallisuuteen, ei tiedostonimeen. `themes/default/pages/`-kansioon tulevat tiedostonimet säilyvät nykyisinä: `ajankohtaista.php` (blogilistaus) ja `postaus.php` (yksittäinen postaus). Ei uudelleennimeämistä.

### Claude's Discretion
- Tarkka tapa erottaa data/HTML kussakin 7 sivussa (esim. mitkä muuttujat templatelle annetaan) jätetään plannerin/executorin päätettäväksi kunkin sivun rakenteen mukaan — periaate (D-01/D-02) on lukittu, toteutustapa ei.
- `theme-page.php`-generic-kontrolleri (manifest-pohjainen, käyttää jo `resolveThemePath()`:ia) ei kuulu Phase 7:n 7 vakiosivun listaan — se on erillinen, jo olemassa oleva infrastruktuuri eikä vaadi muutoksia.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Vaatimukset ja roadmap
- `.planning/REQUIREMENTS.md` §v1.1 THEME-05–THEME-07 — Phase 7:n vaatimukset (includes/, pages/, style.css teemakansiossa)
- `.planning/ROADMAP.md` Phase 7 -osio — tavoite ja success criteria (rivit 153-166)

### Phase 6:n tuottama infrastruktuuri (käytetään, ei muokata sisällöltään)
- `public/src/includes/theme.php` — `resolveThemePath()`, `THEME_PATH`, `THEME_URL`, `THEMES_ROOT`, `getThemeManifest()`
- `public/themes/default/theme.json` — jo olemassa (Phase 6 loi placeholderina)
- `.planning/phases/06-teema-infrastruktuuri/06-CONTEXT.md` — Phase 6:n päätökset (D-09/D-10: admin-eristys, style.css koskemattomuus)

### Nykyiset tiedostot joita kopioidaan (lähde)
- `public/src/includes/header.php` — sisältää WR-02 TODO-merkinnän (rivit 42-46), EI muokata Phase 7:ssä
- `public/src/includes/footer.php`
- `public/src/includes/nav.php`
- `public/assets/css/style.css`
- `public/pages/index.php`, `hevoset.php`, `hevonen.php`, `kasvatus.php`, `yhteystiedot.php`, `ajankohtaista.php`, `postaus.php` — lähde template-erottelulle

### Ei muokata Phase 7:ssä
- `public/pages/*.php` -kontrollerien datanhakulogiikka pysyy koskemattomana (Phase 8:n vastuu)
- `public/pages/theme-page.php` — jo teemainfrastruktuuria käyttävä generic-kontrolleri, ei osa 7 vakiosivun listaa
- `public/admin/*` — ei kosketa

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resolveThemePath(string $subPath): string|false` (`theme.php`) — valmis Phase 8:aa varten, path-traversal-suojattu, default-fallback. Phase 7 ei vielä kutsu tätä kontrollereista (paitsi jo olemassa oleva `index.php`:n erillinen teema-override-hook ja `theme-page.php`).
- `getThemeManifest()` (`theme.php`) — lukee `theme.json`:n; hyödyllinen jos templatet tarvitsevat metatietoa teemasta.

### Established Patterns
- **Include-ketju:** `public/pages/*.php` lataa `db.php` → `theme.php` → (data-logiikka) → `header.php` → (HTML-body) → `footer.php`. Template-erottelussa header/footer/nav-kutsut templaten sisällä säilyttävät saman polku-/kutsumallin kuin nyt, vain fyysinen sijainti muuttuu kopioksi.
- **XSS-suojaus:** kaikki templatet käyttävät `e()`/`htmlspecialchars()`-helperia tulostuksessa (`helpers.php`) — säilytettävä identtisenä kopioissa.
- **`index.php`:n teema-override-hook** (rivit 6-12): tarkistaa onko aktiivisella (non-default) teemalla oma `index.php` teemakansion juuressa, ja jos on, lataa sen suoraan. Tämä on Phase 6:n integraatiotodiste (D-07), erillinen mekanismi verrattuna Phase 8:n `resolveThemePath('pages/index.php')`-malliin — ei muuteta Phase 7:ssä.

### Integration Points
- `public/themes/default/pages/*.php` — uudet puhtaat templatet, odottavat kontrollerilta muuttujia (esim. `$horseCount`, `$foalCount`, `$latestPost` index-sivulla).
- `public/themes/default/includes/{header,footer,nav}.php` — kopiot, identtinen sisältö nykyisten kanssa (mukaan lukien WR-02 TODO-kommentti, koskemattomana).
- `public/themes/default/assets/css/style.css` — kopio, ei vielä linkitetty mistään live-sivusta.

</code_context>

<specifics>
## Specific Ideas

- 7 sivua joille template-erottelu tehdään: index, hevoset, hevonen, kasvatus, yhteystiedot, ajankohtaista (blogilistaus), postaus (yksittäinen postaus) — säilyttäen nykyiset tiedostonimet.
- Lopputila Phase 7:n jälkeen: kaksi rinnakkaista, toimivaa kopiota (vanha yhdistetty controller+HTML `public/pages/`, uusi puhdas HTML-template `themes/default/pages/`) — vain vanha on live-käytössä. Tämä on tarkoituksellinen välivaihe, ei virhe.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 7-Oletusteman-rakenne*
*Context gathered: 2026-07-02*
