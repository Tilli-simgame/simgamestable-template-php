# Phase 9: Admin-teemavalinta & Altervista-verifiointi - Context

**Gathered:** 2026-07-04
**Status:** Ready for planning

<domain>
## Phase Boundary

Admin voi valita aktiivisen teeman ja tallentaa valinnan (THEME-10, THEME-11), ja koko teemajärjestelmä varmistetaan toimivaksi Altervistan tuotantoympäristössä (THEME-12: CSS MIME-tyypit, URL-polut, `public/themes/`-kansion suojaus).

**Merkittävä löydös:** `public/admin/settings.php` sisältää jo täysin toimivan teemavalitsimen (committoitu 2026-06-23, ennen GSD-seurantaa) — listaa asennetut teemat `glob(themes/*/theme.json)`:lla, näyttää `theme.json`:n `name`-kentän, tallentaa valinnan allowlist-validoituna (`array_key_exists($active_theme_post, $available_themes)`) ja vaatii CSRF-tokenin (`validate_csrf_token()`). Tämä täyttää THEME-10 ja THEME-11 -vaatimukset jo olemassa olevana koodina. Phase 9:n ydinsisältö on siis:
1. `public/themes/`-alikansioiden suojaus suoralta template-ajolta (uusi työ)
2. Altervista-tuotantoverifiointi (THEME-12) — ei vaadi uutta koodia, vaan manuaalisen tarkistuksen deploymentin jälkeen

</domain>

<decisions>
## Implementation Decisions

### Teemalistan käsittely (oma-talli)
- **D-01:** `oma-talli`-teema (keskeneräinen, ei kaikkia perussivuja — ks. Phase 8 CONTEXT D-03) näkyy admin-teemalistassa normaalisti, ilman erityismerkintää tai piilotusta. Ei muutosta nykyiseen `glob()`-pohjaiseen listaukseen. Admin voi valita sen jo nyt; puuttuvat sivut fallbackaavat `default`-teemaan `resolveThemePath()`:n kautta.

### public/themes/-kansiosuojaus
- **D-02:** Ei uutta `public/themes/.htaccess`-tiedostoa hakemistolistauksen (`Options -Indexes`) estoon — root `.htaccess`:n periytyvä asetus riittää. Phase 9 vain todentaa tämän Altervistassa deploymentin jälkeen, ei luo uutta suojausmekanismia listaukselle.
- **D-03:** Jokaisen teeman `pages/`-alikansion suoraa HTTP-ajoa (templatet odottavat kontrollerin asettamia muuttujia, esim. `themes/default/pages/hevonen.php`) ei jätetä pelkän `display_errors Off` -asetuksen varaan — lisätään eksplisiittinen esto.
- **D-04:** Estomekanismi on **.htaccess-pohjainen**, ei PHP-vakiotarkistus (`defined()`) templaten sisällä. Jokaisen teeman `pages/`-kansioon lisätään `.htaccess` joka estää suoran HTTP-pyynnön templatetiedostoihin. Koskee kaikkia teemoja, myös tulevia (esim. `oma-talli` jos/kun se saa oman `pages/`-kansion).
  - Rationale: ei vaadi PHP-koodin muutosta olemassa oleviin tai tuleviin templateihin — pelkkä hakemistotason suojaus riittää eikä unohdu uutta teemaa lisättäessä (jos toteutustapa on yhtenäinen kaikille teemoille).

### Nykyisen settings.php-toteutuksen kohtalo
- **D-05:** `settings.php`:n nykyinen teemalistaus-/tallennuslogiikka (glob()+theme.json suoraan, ei `getThemeManifest()`-helperia — koska admin ei koskaan lataa `theme.php`-shimmiä, D-09 Phase 6:sta) hyväksytään sellaisenaan Phase 9:n lähtökohdaksi. **Ei muokata.** THEME-10 ja THEME-11 katsotaan jo täytetyksi tällä koodilla.

### Altervista-verifiointi (THEME-12)
- **D-06:** Verifioidaan tuotannossa **vain `default`-teema**. `oma-talli` on keskeneräinen eikä kuulu tämän phasen verifiointiin.
- **D-07:** CSS:n MIME-tyyppi (`text/css`) todennetaan selaimen DevTools-Network-välilehdeltä deploymentin jälkeen (ei `curl -I`).
- **D-08:** Verifioinnista **ei** tuoteta erillistä dokumentoitua tarkistuslistaa/tulosta (ei VERIFICATION.md-tyyppistä artefaktia). Kertaluontoinen manuaalinen tarkistus riittää — jos kaikki toimii, phase merkitään valmiiksi ilman lisäkirjauksia.

### Claude's Discretion
- Tarkka tapa toteuttaa `.htaccess`-esto `pages/`-alikansioihin (esim. `FilesMatch`-säännön tarkka regex, käytetäänkö `Deny from all` vai `403`-uudelleenohjaus) — periaate (D-03/D-04) on lukittu, syntaksi ei.
- Miten estomekanismi asennetaan olemassa olevaan `default`-teemaan vs. dokumentoidaan tulevia teemoja varten (esim. onko `pages/.htaccess` osa teeman "pakollista rakennetta" jatkossa) — ei vaadi erillistä käyttäjäpäätöstä tässä vaiheessa.
- Tarkka Altervista-verifioinnin suoritustapa (mitä sivuja klikataan läpi, missä järjestyksessä) — periaatteet (D-06/D-07/D-08) ovat lukittu, yksityiskohtainen suoritus ei.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Vaatimukset ja roadmap
- `.planning/REQUIREMENTS.md` §v1.1 THEME-10–THEME-12 — Phase 9:n vaatimukset
- `.planning/ROADMAP.md` Phase 9 -osio (rivit 210-224) — tavoite ja success criteria

### Jo olemassa oleva admin-teemavalinta (THEME-10/11 — EI MUOKATA)
- `public/admin/settings.php` — täysin toimiva teemavalitsin: `glob($themes_dir . '/*/theme.json')`-listaus (rivit 17-27), allowlist-validoitu POST-käsittely (`array_key_exists($active_theme_post, $available_themes)`, rivi 74), CSRF-suojaus (`validate_csrf_token()`, rivi 30). Hyväksytty sellaisenaan (D-05).
- `public/src/includes/helpers.php` — sisältää `validate_csrf_token()` ja `generate_csrf_token()` -funktiot joita settings.php käyttää.

### Teemainfrastruktuuri (käytetään, ei muokata sisällöltään)
- `public/src/includes/theme.php` — `resolveThemePath()`, `THEME_PATH`, `THEME_URL`, `THEMES_ROOT`, `getThemeManifest()`. Admin ei koskaan lataa tätä (D-09, Phase 6).
- `.planning/phases/08-sivukontrollerien-migraatio/08-CONTEXT.md` — Phase 8:n päätökset (D-01–D-03: kaksoismekanismi, oma-talli-rakenne)
- `.planning/phases/07-oletusteman-rakenne/07-CONTEXT.md` — Phase 7:n päätökset (template-jaon syvyys)
- `.planning/phases/06-teema-infrastruktuuri/06-CONTEXT.md` — Phase 6:n päätökset (D-09: admin-eristys, D-10: style.css koskemattomuus)

### Kansiosuojauksen precedent
- `public/uploads/.htaccess` — esimerkki hakemistotason suojauksesta (PHP-ajon esto `FilesMatch`-säännöllä, `Options -Indexes`, piilotiedostojen esto). Malliesimerkki `pages/`-alikansion `.htaccess`-eston syntaksille (D-04), vaikka tarkoitus on eri (uploads/ estää KAIKEN PHP-ajon, pages/ estää vain suoran HTTP-pyynnön templateihin — teeman `index.php`/root-override-tiedostojen ja muun PHP:n pitää edelleen toimia).
- `public/admin/.htaccess` — minimaalinen esimerkki (`Options -Indexes` yksinään).
- `public/.htaccess` — root-tason suojaus: `Options -Indexes` (periytyy `themes/`-kansioon, D-02), `RewriteRule ^src/ - [F,L]`, arkaluonteisten tiedostojen esto `FilesMatch`-säännöllä.

### Deployment (Altervista-verifiointia varten)
- `.github/workflows/deploy.yml` — FTP-deploy `SamKirkland/FTP-Deploy-Action@v4.3.4`, push main -triggerillä, `server-dir: /demotalli-02/`. `THEME_URL` rakentuu `SITE_URL`:n päälle, joka sisältää tämän `/demotalli-02/`-prefiksin tuotannossa.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `public/admin/settings.php` (rivit 17-27, 67-74, 163-227) — valmis teemavalitsin, ei tarvitse lisätyötä (D-05).
- `public/uploads/.htaccess` — copy-paste-pohja `.htaccess`-syntaksille (D-04), tosin `FilesMatch`-kohde on eri (uploads estää kaiken PHP:n, pages/ estää vain templaten suoran HTTP-kutsun).

### Established Patterns
- **Admin/julkinen-eristys (D-09, Phase 6):** admin-sivut eivät koskaan lataa `theme.php`-shimmiä. `settings.php` lukee teemat suoraan tiedostojärjestelmästä (`glob()`), ei `getThemeManifest()`:n kautta — tämä on tarkoituksellista, ei puute.
- **`.htaccess`-hakemistosuojaus:** jokainen suojattava hakemisto (`uploads/`, `admin/`, `src/includes/`) saa oman `.htaccess`:n root-tason suojauksen lisäksi. `pages/`-alikansion esto (D-04) seuraa samaa periaatetta.
- **Path-traversal-suojaus (`resolveThemePath()`):** `preg_match` + `realpath()` + `str_starts_with()`-prefix-check. Ei muutu Phase 9:ssä.

### Integration Points
- `public/themes/{teema}/pages/.htaccess` (uusi, per teema) — estää suoran HTTP-pääsyn templatetiedostoihin.
- FTP-deploy (`deploy.yml`) julkaisee koko `public/`-kansion — uusi `.htaccess` päätyy tuotantoon automaattisesti push main -yhteydessä, ilman erillisiä deploy-muutoksia.

</code_context>

<specifics>
## Specific Ideas

- Teemalistan järjestys/nimeäminen settings.php:ssä (raa'at kansionimet vs. `theme.json`:n `name`-kenttä, `(vakio)`-merkintä defaultille) on jo toteutettu halutulla tavalla — ei muutostarvetta.
- Verifiointi on kevyt, kertaluontoinen läpikäynti: avaa tuotantosivu selaimessa deploymentin jälkeen, tarkista DevTools-Networkista CSS:n Content-Type, varmista ettei `themes/`-kansio listaudu suoralla URL-käynnillä.

</specifics>

<deferred>
## Deferred Ideas

- `oma-talli`-teeman rakenteellinen viimeistely (puuttuvat perussivut, nav-yhteensopivuus) ja sen verifiointi Altervistassa — ei kuulu Phase 9:ään (D-06). Jää tulevaksi työksi kun `oma-talli` otetaan käyttöön.
- Mahdollinen `theme.json`-kentän lisäys keskeneräisten teemojen merkitsemiseksi admin-listassa (esim. "Kesken"-lippu) — ei valittu tässä phasessa (D-01), koska nykyinen käytäntö (näytä kaikki, luota fallbackiin) riitti. Voi nousta uudelleen esiin jos useampi keskeneräinen teema aletaan pitää repossa samanaikaisesti.

</deferred>

---

*Phase: 9-Admin-teemavalinta-Altervista-verifiointi*
*Context gathered: 2026-07-04*
