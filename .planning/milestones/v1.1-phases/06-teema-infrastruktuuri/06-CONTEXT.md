# Phase 6: Teema-infrastruktuuri - Context

**Gathered:** 2026-06-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Teemajärjestelmän infrastruktuurikerros: `src/includes/theme.php` -shim joka tarjoaa `THEME_PATH`- ja `THEME_URL`-vakiot julkisille sivuille, `resolveThemePath()`-helperi path-traversal-suojauksella ja default-fallbackilla, `active_theme`-rivi `settings`-taulussa sekä `theme.json`-tiedostorakenne teemoille. Ei käyttäjälle näkyvää muutosta — pelkkä infrastruktuuri. Julkiset sivut, admin-teemavalinta ja oletusteman template-tiedostot kuuluvat myöhempiin vaiheisiin.

</domain>

<decisions>
## Implementation Decisions

### DB-migraatio (active_theme-rivi)
- **D-01:** Luodaan uusi `database/migrate_theme.sql` -tiedosto — johdonmukainen 8 olemassa olevan `migrate_*.sql`-tiedoston pattern kanssa.
- **D-02:** `active_theme`-rivin oletusarvo on `'default'` (`INSERT IGNORE INTO settings ('active_theme', 'default')`).

### resolveThemePath() -funktion signatuuri ja käyttäytyminen
- **D-03:** Funktiosignatuuri: `resolveThemePath(string $subPath): string|false` — yksi merkkijonoargumentti, joka on suhteellinen polku teemakansion sisällä (esim. `'pages/index.php'`, `'includes/header.php'`, `'assets/css/style.css'`).
- **D-04:** Turvallisuus: preg_match + realpath + prefix-check (päätetty STATE.md:ssä). Tarkistukset järjestyksessä: (1) preg_match allowlist teemanimelle, (2) realpath palauttaa todellisen polun, (3) prefix-check varmistaa polun pysyvän `public/themes/`-hakemiston sisällä.
- **D-05:** Fallback-logiikka: jos tiedostoa ei löydy aktiivisesta teemasta → palauttaa absoluuttisen palvelinpolun `default`-teeman vastaavaan tiedostoon. Jos default-teemasta ei löydy → palauttaa `false`.
- **D-06:** Palauttaa absoluuttisen palvelinpolun (käytetään PHP:n `include`/`require` -kutsuissa suoraan, ei selaimen URL:a).

### Phase 6 -integraatiotodistus
- **D-07:** `public/pages/index.php` saa `require_once '../src/includes/theme.php'` db.php-requireen jälkeen — vain tämä rivi lisätään, muu logiikka pysyy ennallaan. Tämä todistaa shimin toimivan oikeassa kontekstissa. Phase 8 migroi loput 6 julkista sivua täysin data-only-kontrollereiksi.

### theme.json -rakenne
- **D-08:** Minimaalinen rakenne: `{"name": "Teeman nimi", "version": "1.0.0"}`. Vain THEME-04:n vaatimat kentät — ei description/author/preview vielä. Laajennus V2-05:ssa (preview.png).

### Teema-admin-eristys (aiemmin päätetty)
- **D-09:** `src/includes/theme.php` EI saa olla require-kutsussa `db.php`:ssä — `db.php` on jaettu sekä julkisille sivuille että admin-sivuille. Admin-sivut EIVÄT koskaan lataa theme.php:tä.
- **D-10** [informational]: `public/assets/css/style.css` pysyy muuttumattomana — `admin_header.php` riippuu siitä. Teeman CSS on `public/themes/{teema}/assets/css/style.css`. (Negatiivinen rajoite — ei luo artefaktia, ei vaadi omaa tehtävää.)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Vaatimukset
- `.planning/REQUIREMENTS.md` §v1.1 THEME-01–THEME-04 — Phase 6:n kaikki vaatimukset (resolveThemePath, settings-taulu, shim, theme.json)
- `.planning/ROADMAP.md` Phase 6 -osio — tavoite ja success criteria

### Olemassa olevat tiedostot joita muokataan
- `public/src/includes/config.php` — SITE_URL-vakio (THEME_URL rakennetaan tästä: `SITE_URL . '/themes/' . $themeName . '/'`)
- `public/src/includes/helpers.php` — olemassa oleva helperi-tiedosto (resolveThemePath() lisätään tänne TAI omaan theme.php-shimiin — planner päättää)
- `public/pages/index.php` — ensimmäinen julkinen sivu joka saa `require_once theme.php`:n Phase 6:ssa

### DB-migraatiopattern
- `database/migrate_settings.sql` — esimerkki olemassa olevasta migratiopatternista (CREATE TABLE IF NOT EXISTS + INSERT IGNORE)
- `database/schema.sql` — täydellinen skeema; settings-taulu linjalla 78+

### Admin-sivut (EI muokkausta Phase 6:ssa)
- `public/admin/settings.php` — käyttää INSERT ... ON DUPLICATE KEY UPDATE settings-taulussa; ei ladata theme.php:tä

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `public/src/includes/helpers.php`: Kaikki olemassa olevat apufunktiot ovat täällä. `resolveThemePath()` voisi mennä tähän tiedostoon tai erilliseen `theme.php`-shimiin — shimin erillisyys on tärkeää koska admin EI saa ladata sitä.
- `public/src/includes/config.php`: `SITE_URL`-vakio on jo määritelty; `THEME_URL` rakennetaan sen päälle.
- `database/migrate_settings.sql`: Valmis malli uudelle `migrate_theme.sql`-tiedostolle — sama CREATE TABLE IF NOT EXISTS + INSERT IGNORE -pattern.

### Established Patterns
- **DB-settings pattern:** Admin luku `$db->query('SELECT setting_key, setting_value FROM settings')->fetchAll()` → assosiatiivinen taulukko. Kirjoitus: `INSERT ... ON DUPLICATE KEY UPDATE`. Molemmat ovat jo käytössä `settings.php`:ssä.
- **Include-ketju julkisilla sivuilla:** `require_once __DIR__ . '/../src/includes/db.php'` → lataa config.php + helpers.php + käynnistää session. `theme.php` lisätään tähän ketjuun (`require_once` db.php:n jälkeen), ei db.php:n sisälle.
- **Path-traversal-suojaus:** `validate_file_name()` helpers.php:ssä käyttää `preg_match('/^[a-zA-Z0-9._-]+$/', $filename)` + `strpos($filename, '..')` -tarkistuksia. `resolveThemePath()` seuraa samaa periaatetta mutta käyttää `realpath()` + prefix-check -yhdistelmää teeman nimen validointiin.

### Integration Points
- `public/pages/index.php`: Lisätään `require_once __DIR__ . '/../src/includes/theme.php'` rivin `require_once __DIR__ . '/../src/includes/db.php'` jälkeen.
- `public/themes/` -hakemisto: Ei vielä olemassa — Phase 6 luo sen rakenteen (ainakin `public/themes/default/theme.json` testausta varten). Phase 7 täyttää sisällön.
- `settings`-taulu: `active_theme`-rivi lisätään `migrate_theme.sql`:lla; `theme.php` lukee sen `getDB()`:n kautta.

</code_context>

<specifics>
## Specific Ideas

- `resolveThemePath()` -kutsu Phase 8:ssa näyttää tältä: `require resolveThemePath('pages/index.php');` tai `require resolveThemePath('includes/header.php');`
- `THEME_PATH` = absoluuttinen palvelinpolku aktiiviseen teemakansioon (esim. `/var/www/public/themes/default/`)
- `THEME_URL` = selainselattava URL aktiiviseen teemaan (esim. `https://tilli.altervista.org/demotalli/themes/default/`)
- `theme.json` minimisisältö: `{"name": "Default", "version": "1.0.0"}`
- Phase 6:n lopussa `public/themes/default/theme.json` on luotu (testausta varten), vaikka `themes/default/` sisältö rakennetaan Phase 7:ssa

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 6-Teema-infrastruktuuri*
*Context gathered: 2026-06-22*
