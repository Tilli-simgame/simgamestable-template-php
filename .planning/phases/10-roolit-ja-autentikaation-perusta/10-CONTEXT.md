# Phase 10: Roolit ja autentikaation perusta - Context

**Gathered:** 2026-07-05
**Status:** Ready for planning

<domain>
## Phase Boundary

Kolme roolia (admin/mod/author) tallentuu `admin_users`-tauluun ja tunnistetaan sessiossa jokaisella suojatulla admin-sivulla. Jokainen olemassa oleva admin-sivu (~30 kpl) saa lopullisen roolivaatimuksensa (mikä rooli/roolit pääsevät sivulle) sekä roolikohtaisen nav-näkyvyyden. Kaikki kirjautuneet käyttäjät (mikä tahansa rooli) voivat vaihtaa oman salasanansa.

**Tämä vaihe EI rakenna:** sivujen sisäistä roolikohtaista liiketoimintalogiikkaa (esim. author näkee tässä vaiheessa yhä kaikki postaukset — omistajuussuodatus tulee Phase 12:ssa), käyttäjähallinta-UI:ta (Phase 11), poisto-hyväksyntätyönkulkua (Phase 13). Ks. "Rooli-sivukartoituksen laajuus" alla — tämä rajanveto on eksplisiittisesti käyty läpi ja lukittu.

</domain>

<decisions>
## Implementation Decisions

### Rooli-sivukartoituksen laajuus
- **D-01:** Phase 10 tekee TÄYDEN sivukartoituksen heti — kaikki ~30 admin-sivua saavat lopullisen roolivaatimuksensa (`requireRole()`-tason gating + nav-näkyvyys `admin_header.php`:ssä), ei vain admin-only/muut-jaottelun. Perustelu: tutkimus (`research/SUMMARY.md`) suositteli eksplisiittistä file-by-file audit-taulukkoa jo tässä vaiheessa Pitfall 1:n (unohdettu suojaus) välttämiseksi.
- **D-02:** Poisto-endpointit (`horse_delete.php`, varsan/kilpailun/näyttelytuloksen poisto, `post_delete.php`) pysyvät `requireRole('admin')`-tasolla Phase 13:aan asti, vaikka mod saa muuten create/edit-pääsyn sisältösivuille jo nyt. Perustelu: pending-deletion-mekanismi ei ole vielä olemassa — mod ei saa väliaikaisesti pystyä pysyvään poistoon.
- **D-03:** Eksplisiittinen rajanveto Phase 10 vs. Phase 12: **Phase 10 = pääsyoikeus + nav-näkyvyys per sivu** (voiko rooli edes avata sivun ja näkeekö sen navissa); **Phase 12 = sivun sisällä tapahtuva roolikohtainen suodatus/logiikka** (esim. author näkee/voi teoriassa muokata muidenkin postauksia heti Phase 10:n jälkeen — omistajuussuodatus rakennetaan Phase 12:ssa). Tämä on hyväksytty väliaikaistila, koska mod/author-tunnuksia ei vielä edes ole olemassa (luodaan Phase 11:ssä) — kukaan ei voi hyödyntää väliaikaista aukkoa ennen kuin Phase 11 on ajettu.
- Suunnittelijan tehtävä: rakenna eksplisiittinen file-to-role-taulukko kaikille `public/admin/*.php`-tiedostoille (ml. nimeämättömät kuten `contacts.php`, `sukulaiset.php`, `horse_import_vrl.php`, `kuvat_all.php`) osana suunnittelua — tutkimus tunnisti tämän kriittiseksi puutteeksi jos jätetään tekemättä.

### "Ei käyttöoikeutta" -näkymä
- **D-04:** Oma dedikoitu sivu (esim. `admin/ei-oikeutta.php`), käyttää `admin_header.php`-layoutia (sama sivupalkki/konteksti kuin muillakin sivuilla). Viesti: "Sinulla ei ole käyttöoikeutta tähän sivuun" + linkki takaisin.
- **D-05:** "Palaa"-linkki ohjaa aina `admin/index.php`:hen (dashboard) riippumatta roolista — ei HTTP_REFERER-pohjaista logiikkaa (ei luotettava turvallisuuskontekstissa), ei rooli→etusivu-mappausta (kaikki roolit näkevät saman dashboardin, vain nav-kohdat eroavat).

### Admin-käyttäjän rooli-backfill
- **D-06:** `ALTER TABLE admin_users ADD COLUMN role ENUM('admin','mod','author') NOT NULL DEFAULT 'author'` (turvallisin fallback-oletus), sitten eksplisiittinen `UPDATE admin_users SET role='admin' WHERE username='admin'` samassa migraatiotiedostossa. Ei luoteta pelkkään DEFAULTiin olemassa olevan tilin osalta — tutkimus suositteli eksplisiittistä backfillia.
- **D-07:** `is_active TINYINT(1) NOT NULL DEFAULT 1` lisätään SAMASSA migraatiossa kuin `role` (vaikka hallinta-UI tulee vasta Phase 11:ssä). `login.php`:n kirjautumistarkistus laajennetaan tarkistamaan `is_active = 1` heti — välitön turvallisuushyöty, välttää toisen `ALTER TABLE`in Phase 11:ssä.
- Migraatiotiedosto nimetään konvention mukaisesti, esim. `database/migrate_roles.sql`, samaan tyyliin kuin `migrate_theme.sql` (kommenttiheader + phpMyAdmin-ohje + `INSERT IGNORE`/eksplisiittiset lauseet).

### Salasananvaihto (AUTH-06)
- **D-08:** Uusi sivu `admin/change_password.php`. Linkki sijaitsee sivupalkin footerissa (`admin_header.php` `.admin-sidebar-footer`) käyttäjänimen ja "Kirjaudu ulos" -linkin välissä. Näkyy kaikille rooleille (admin/mod/author) — ei roolikohtaista piilotusta tälle linkille.
- **D-09:** Lomake: nykyinen salasana + uusi salasana + uusi salasana vahvistus (3 kenttää). Onnistuneen vaihdon jälkeen: `session_regenerate_id(true)` (estää session fixation -riskin), käyttäjä PYSYY kirjautuneena, onnistumisviesti näytetään inline samalla sivulla (ei redirect+flash-sessiomekanismia — vältetään uuden flash-järjestelmän rakentaminen kun sitä ei koodikannassa muutenkaan ole).
- **D-10:** Minimipituus uudelle salasanalle: 8 merkkiä, käyttäen `validate_string()`-tyylistä validointia. Ei muita vahvuussääntöjä (isot/pienet/erikoismerkit) — tutkimus suositteli välttämään salasanavahvuustarkistuksia tässä 2-4 käyttäjän mittakaavassa.

### Claude's Discretion
- Tarkka `requireRole()`/`currentRole()`/`isAdmin()`-funktioiden signatuuri ja koodityyli `helpers.php`:ssa (mirroring `requireLogin()`/`isLoggedIn()`).
- Nav-kohtien tarkka rooli-array-toteutus `admin_header.php`:ssä (esim. `in_array($currentRole, ['admin','mod'])`).
- Virheviestien tarkka sanamuoto (paitsi "Ei käyttöoikeutta" -sivun ydinviesti, joka on annettu yllä).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Research & vaatimukset
- `.planning/research/SUMMARY.md` — Koko v1.2-milestonen tutkimus; "Phase 1: Roles and Auth Foundation" -osio (rivit 68-73) vastaa suoraan tätä roadmap-Phase 10:tä. Sisältää suositellun arkkitehtuurin, pitfallit ja avoimet kysymykset.
- `.planning/ROADMAP.md` §"Phase 10: Roolit ja autentikaation perusta" — goal, success criteria, requirements-lista (ROLE-01..04, AUTH-06).
- `.planning/REQUIREMENTS.md` — ROLE-01..04 (rivit 10-13), AUTH-06 (rivi 27), jäljitettävyystaulukko (rivit 81-85) vahvistaa mitkä vaatimukset kuuluvat TÄHÄN vaiheeseen vs. Phase 12/13:een.
- `.planning/PROJECT.md` §"Current Milestone: v1.2 Käyttäjäroolit" — roolien lopulliset oikeusrajat (admin/mod/author-kuvaukset).

### Olemassa oleva autentikaatiokoodi (laajennettava, ei korvattava)
- `public/src/includes/helpers.php` — `isLoggedIn()`/`requireLogin()` (rivit 51-62): malli jota `requireRole()`/`currentRole()`/`isAdmin()` mirroroi. `generate_csrf_token()`/`validate_csrf_token()` (rivit 218-237) ja `validate_string()` (rivit 247-271) uudelleenkäytettäviksi salasananvaihtolomakkeessa.
- `public/admin/login.php` — session-kirjoitus (`$_SESSION['admin_logged_in']`, `admin_id`, `admin_username`, rivit 24-29) laajennetaan kirjoittamaan `admin_role` samalla tavalla; `session_regenerate_id(true)`-kutsu jo olemassa mallina.
- `public/admin/includes/admin_header.php` — nav-rakenne (rivit 290-317) roolikohtaisen näkyvyyden lisäämiseksi; sivupalkin footer (rivit 318-324) salasananvaihtolinkin sijoituspaikka.
- `database/schema.sql` rivit 245-252 — `admin_users`-taulun nykyinen rakenne (ei vielä `role`- tai `is_active`-saraketta).
- `database/seed.sql` rivit 550-556 — vahvistaa että tällä hetkellä on tasan 1 admin-tili (`username='admin'`).
- `database/migrate_theme.sql` — migraatiotiedostojen konventio (kommenttiheader, phpMyAdmin-ohje, `INSERT IGNORE`) jota uusi `migrate_roles.sql` seuraa.

### Muut suojatut admin-sivut (täyden sivukartoituksen kohteet)
- `public/admin/*.php` (~30 tiedostoa, kaikki kutsuvat `requireLogin()`) — täydellinen lista saatava `grep -rl requireLogin() public/admin/` ja jokaiselle määritettävä lopullinen roolivaatimus suunnitteluvaiheessa.

**No external specs beyond the above** — vaatimukset on kokonaan lukittu ROADMAP.md/REQUIREMENTS.md:ssä, tässä keskustelussa tehdyt päätökset täydentävät niitä.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `isLoggedIn()`/`requireLogin()` (`helpers.php`) — suora malli `requireRole($allowedRoles)`/`currentRole()`/`isAdmin()`-funktioille.
- `generate_csrf_token()`/`validate_csrf_token()` — käytetään sellaisenaan salasananvaihtolomakkeessa.
- `validate_string()` — käytetään uuden salasanan minimipituusvalidointiin (8 merkkiä).
- `session_regenerate_id(true)` -kutsumalli jo olemassa `login.php`:ssä — sama kutsutaan salasananvaihdon onnistuessa.
- `admin_header.php`:n `$_activePage`-tunnistusmalli (rivi 7, `basename($_SERVER['PHP_SELF'], '.php')`) — käytetään "Ei käyttöoikeutta" -sivun ja salasananvaihtosivun aktiivinen-tila-tunnistukseen navissa (jos relevanttia).

### Established Patterns
- Migraatiotiedostot: yksi `.sql`-tiedosto per ominaisuus `database/`-kansiossa, kommenttiheader + phpMyAdmin-import-ohje, `INSERT IGNORE` (ei `ON DUPLICATE KEY UPDATE`) idempotenssiin.
- Sivutason suojaus: jokainen `admin/*.php` kutsuu `requireLogin()` tiedoston alussa `db.php`-includen jälkeen — uusi `requireRole()` seuraa samaa kutsupaikkaa/-tyyliä.
- Session-kentät ovat litteitä `$_SESSION`-avaimia (`admin_logged_in`, `admin_id`, `admin_username`) — ei olio/array-rakennetta. `admin_role` lisätään samalla tasolla.

### Integration Points
- `admin_header.php` sisällytetään jokaiselta suojatulta sivulta — roolikohtainen nav-logiikka lisätään tähän yhteen tiedostoon, ei per-sivu.
- `login.php` on ainoa paikka joka kirjoittaa auth-session-kentät — `admin_role`/`is_active`-tarkistus lisätään tähän yhteen paikkaan.

</code_context>

<specifics>
## Specific Ideas

Ei erillisiä ulkoasu-/tekstitoiveita tälle vaiheelle — "Ei käyttöoikeutta" -sivun ja salasananvaihtosivun oletetaan noudattavan olemassa olevaa admin-paneelin visuaalista tyyliä (`admin_header.php`:n CSS, `.admin-card`, `.form-group`, `.flash-ok`/`.flash-err`-luokat) ilman uutta suunnittelua.

</specifics>

<deferred>
## Deferred Ideas

Ei uusia scope-ehdotuksia noussut keskustelun aikana — kaikki neljä käsiteltyä aihetta pysyivät Phase 10:n rajojen sisällä. Seuraaviin vaiheisiin jo roadmapissa kuuluvat asiat (ei toistettu tässä):
- Käyttäjähallinta-UI, roolin/nimen muokkaus, deaktivointi/poisto, salasanan nollaus toiselle käyttäjälle → Phase 11.
- Sisältösivujen sisäinen roolikohtainen suodatus (author-omistajuus, mod:n täysi CRUD-toiminnallisuus, hevoslinkitys read-only) → Phase 12.
- Poisto-hyväksyntätyönkulku, `pending_deletions`-taulu → Phase 13.

</deferred>

---

*Phase: 10-Roolit ja autentikaation perusta*
*Context gathered: 2026-07-05*
