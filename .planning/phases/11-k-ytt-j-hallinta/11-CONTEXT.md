# Phase 11: Käyttäjähallinta - Context

**Gathered:** 2026-07-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Admin saa oman admin-paneelisivun (`admin/users.php` tai vastaava) jolla hän voi hallita kaikkia `admin_users`-tunnuksia: luoda uuden tunnuksen (käyttäjänimi, salasana, rooli), muokata olemassa olevan tunnuksen käyttäjänimeä ja roolia, deaktivoida/reaktivoida tunnuksen, poistaa tunnuksen pysyvästi, ja nollata toisen käyttäjän salasanan. Järjestelmä estää sekä viimeisen admin-tunnuksen poiston/deaktivoinnin että adminin oman tunnuksen poiston/deaktivoinnin/roolin alentamisen.

**Tämä vaihe EI rakenna:** sisältötyyppien roolikohtaista CRUD-rajausta (hevoset/varsat/kilpailut/näyttelyt/postaukset — Phase 12), poisto-hyväksyntätyönkulkua sisällölle (Phase 13), `posts.author_id`-saraketta tai sen taannehtivaa täyttöä (ei ole vielä olemassa — päätetään Phase 12:ssa). USER-03:n "aiempi sisältö säilyy" -vaatimus täyttyy Phase 11:ssä sillä, ettei deaktivointi/muokkaus koskaan poista `admin_users`-riviä — mitään olemassa olevaa FK-riippuvuutta ei vielä ole tässä vaiheessa, koska mod/author-tunnuksia ei ole päässyt syntymään sisältöä ennen Phase 12:ta.

</domain>

<decisions>
## Implementation Decisions

### Listanäkymä
- **D-01:** Perinteinen `<table>`-näkymä, ei osoitekirjan (`contacts.php`) klikattava/laajeneva compact-list. Kaikki toiminnot (muokkaa / nollaa salasana / deaktivoi-aktivoi / poista) näkyvät suoraan jokaisella rivillä ilman klikkausta auki.
- Sarakkeet: käyttäjänimi, rooli, tila (aktiivinen/deaktivoitu), toiminnot. Tarkka sarakejärjestys/tyylitys on Claude's Discretion.

### Uuden käyttäjän luonti (USER-01)
- **D-02:** Admin antaa vain käyttäjänimen ja roolin — ei salasanakenttää lomakkeessa. Järjestelmä generoi vahvan satunnaissalasanan palvelinpuolella ja näyttää sen kerran onnistumisilmoituksessa (esim. `.flash-ok`-lohkossa) kopioitavaksi. Salasanaa ei tallenneta plaintext-muodossa mihinkään — vain bcrypt-hash tietokantaan, kerran näytetty selväkielinen arvo on vain kertaluonteinen HTTP-response.
- Perustelu: järjestelmässä ei ole sähköpostia, joten reset-linkki ei ole mahdollinen; admin välittää salasanan käyttäjälle itse (esim. puhelimitse) heti luonnin jälkeen.
- Salasanan generointitapa (pituus/merkistö) on Claude's Discretion — riittävän vahva satunnaisgenerointi (esim. `random_bytes`-pohjainen), täyttää saman 8 merkin minimipituuden kuin muuallakin (D-10, Phase 10).

### Käyttäjän muokkaus (USER-02)
- Käyttäjänimen ja roolin muokkaus samalla lomakkeella — ei salasanakenttää tässä lomakkeessa (salasana hoidetaan erillisellä nollaus-toiminnolla, ks. alla).
- **D-05 (Oman roolin suoja):** Admin ei voi muuttaa omaa rooliaan pois administa — sama suoja kuin oman tunnuksen poistossa/deaktivoinnissa (USER-07:n hengessä laajennettuna). Estää vahingossa tapahtuvan lukkiutumisen tilanteessa jossa kirjautunut admin muokkaa omaa riviään eikä tarkista onko viimeinen admin.

### Salasanan nollaus toiselle käyttäjälle (USER-05)
- **D-03:** Erillinen "Nollaa salasana" -toiminto/nappi käyttäjälistassa (ei osa muokkauslomaketta). Toiminto ei kysy vanhaa salasanaa (admin-oikeudella).
- **D-04:** Nollaus käyttää samaa mekanismia kuin uuden käyttäjän luonti: järjestelmä generoi uuden satunnaissalasanan ja näyttää sen kerran onnistumisilmoituksessa — ei lomaketta jossa admin kirjoittaisi uuden salasanan käsin. Yhdenmukainen UX luonnin kanssa.

### Deaktivointi / reaktivointi (USER-03)
- **D-06:** Yksi tila-toggle-nappi per rivi ("Deaktivoi" ↔ "Aktivoi" riippuen nykyisestä `is_active`-arvosta). Muokkaa-, nollaa salasana- ja poista-toiminnot pysyvät käytettävissä myös deaktivoidulle käyttäjälle (ei piiloteta niitä "jäädytetyn" tunnuksen kohdalla).
- Deaktivointi on `UPDATE admin_users SET is_active = 0` — ei poista riviä, `login.php`:n `is_active = 1` -tarkistus (D-07, Phase 10) estää kirjautumisen jo olemassa olevalla mekanismilla.

### Pysyvä poisto (USER-04)
- **D-07:** Sama selaimen `confirm()`-vahvistusdialogi kuin muualla admin-paneelissa (esim. `contact_delete.php`, `photo_delete.php`) — ei tiukempaa "kirjoita nimi uudelleen" -vahvistusta. Yhdenmukaisuus olemassa olevan koodikannan kanssa painaa enemmän kuin poiston peruuttamattomuus tässä 2-4 käyttäjän mittakaavassa.
- Poisto on todellinen `DELETE FROM admin_users WHERE id = :id` (ei soft-delete) — `admin_users`-taulussa ei ole vielä FK-riippuvuuksia muista tauluista tässä vaiheessa (`posts.author_id` ei ole olemassa ennen Phase 12:ta).

### Viimeinen admin -suoja (USER-06) ja itsesuojaus (USER-07)
- Sekä poisto että deaktivointi tarkistavat palvelinpuolella: (a) onko kohde viimeinen `role='admin' AND is_active=1` -tunnus, (b) onko kohde kirjautuneen käyttäjän oma `admin_id`. Kumpikin tapaus estetään virheilmoituksella, toimintoa ei suoriteta.
- D-05:n mukaisesti sama itsesuoja ulotetaan myös roolin muokkaukseen (ei vain poistoon/deaktivointiin).
- Tarkka virheviestin sanamuoto ja se, näytetäänkö virhe inline-flashina vai eri tavalla, on Claude's Discretion — noudattaa samaa `.flash-err`-konventiota kuin muu admin-paneeli.

### Claude's Discretion
- Taulukon tarkka sarake-/tyylitysjärjestys ja CSS-luokat (uudelleenkäytä `.admin-card`, `.form-group`, `.flash-ok`/`.flash-err`, `.btn`/`.btn-sm`/`.btn-danger` — ei uutta CSS:ää).
- Salasanan generointitapa (pituus, merkistö, PHP-funktio).
- Virheviestien tarkka sanamuoto (paitsi missä yllä on annettu suunta).
- Nav-linkin ("Käyttäjähallinta") tarkka sijoituspaikka `admin_header.php`:ssä — vain admin näkee sen (sama `currentRole()`-malli kuin muissa admin-only-nav-kohdissa, esim. `settings.php`).
- Uuden käyttäjän/muokkauksen käyttäjänimen validointi (uniikkius, sallitut merkit) — mirroroi olemassa olevaa `validate_string()`-käyttöä.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Vaatimukset ja roadmap
- `.planning/ROADMAP.md` §"Phase 11: Käyttäjähallinta" — goal, success criteria, requirements-lista (USER-01..07).
- `.planning/REQUIREMENTS.md` — USER-01..07 (rivit 18-24), jäljitettävyystaulukko vahvistaa scope-rajan Phase 12:een (MOD/AUTHOR-vaatimukset eivät kuulu tähän vaiheeseen).
- `.planning/PROJECT.md` §"Current Milestone: v1.2 Käyttäjäroolit" — roolien lopulliset oikeuskuvaukset.

### Phase 10 -perusta (laajennettava, ei korvattava)
- `.planning/phases/10-roolit-ja-autentikaation-perusta/10-CONTEXT.md` — edeltävän vaiheen päätökset (D-06/D-07: role+is_active-sarakkeet, D-08..D-10: salasananvaihtomalli).
- `public/src/includes/helpers.php` — `requireRole()`/`currentRole()`/`isAdmin()` (rivit 51-89, ks. tämän keskustelun grep-tulos), `generate_csrf_token()`/`validate_csrf_token()`, `validate_string()` — uudelleenkäytettäviksi kaikissa uusissa lomakkeissa.
- `public/admin/change_password.php` — CSRF+validointi+bcrypt-malli jota uudet salasanaa käsittelevät toiminnot (luonti, nollaus) mirroroivat (`password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])`).
- `database/schema.sql` rivit 245-256 — `admin_users`-taulun nykyinen rakenne (`role` ENUM, `is_active` TINYINT, ei vielä FK-riippuvuuksia muista tauluista).
- `database/migrate_roles.sql` — edellisen vaiheen migraatiokonventio (kommenttiheader, phpMyAdmin-ohje) jota mahdollinen uusi migraatio (jos tarpeen) seuraisi — Phase 11 ei todennäköisesti tarvitse uutta migraatiota, koska `role`/`is_active` on jo olemassa.

### Olemassa olevat CRUD-mallit (list+add+edit+delete-pattern)
- `public/admin/contacts.php` / `contact_add.php` / `contact_edit.php` / `contact_delete.php` — lähin olemassa oleva list+CRUD-analogi. **Huom:** listanäkymän tyyli (compact-list) EI toistu Phase 11:ssä (D-01 valitsi taulukon) — mutta lomake-, CSRF-, flash- ja poisto-`confirm()`-mallit uudelleenkäytetään sellaisenaan.
- `public/admin/includes/admin_header.php` — nav-rakenne (rivit ~290-339), roolikohtaisen admin-only-linkin lisäyskohta (esim. `settings.php`:n malli, rivi 336).

**No external specs beyond the above** — vaatimukset on kokonaan lukittu ROADMAP.md/REQUIREMENTS.md:ssä, tässä keskustelussa tehdyt päätökset täydentävät niitä.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `requireRole('admin')` (`helpers.php`) — suoraan käytettävissä uuden `users.php`-sivun ja kaikkien sen alalomakkeiden (add/edit/reset/delete/toggle) suojaukseen.
- `generate_csrf_token()`/`validate_csrf_token()` — kaikki POST-toiminnot (luonti, muokkaus, nollaus, deaktivointi, poisto).
- `validate_string()` — käyttäjänimen ja generoidun salasanan pituusvalidointiin.
- `password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])` -malli `change_password.php`:stä — sama kutsu uuden käyttäjän luonnissa ja nollauksessa.
- `.flash-ok`/`.flash-err`/`.admin-card`/`.form-group`/`.btn`/`.btn-sm`/`.btn-danger` -CSS-luokat — ei uutta CSS:ää tarvita.

### Established Patterns
- List-sivu + `_add.php` + `_edit.php` + `_delete.php` (erilliset tiedostot per toiminto) on koko koodikannan konventio (`contacts.php`-perhe, `horses.php`-perhe) — uusi `users.php`-perhe seuraa samaa rakennetta.
- Poisto-lomakkeet ovat pieniä inline-POST-lomakkeita listasivulla (`<form method="post" action=".../x_delete.php">` + hidden `csrf_token`+`id` + `confirm()`-onclick) — ei erillistä poistosivua.
- Flash-viestit luetaan `$_GET`-parametreista uudelleenohjauksen jälkeen (`contacts.php`: `isset($_GET['added'])` jne.) tavallisissa CRUD-sivuilla — mutta `change_password.php` käyttää sen sijaan inline-`$success`-muuttujaa samalla sivulla (D-09, Phase 10). Uuden käyttäjän salasannan kertanäyttö vaatii inline-mallin (kuten `change_password.php`), koska generoitu salasana ei voi kulkea `$_GET`-parametrissa (näkyisi URL:ssa/historiassa).

### Integration Points
- `admin_header.php` — uusi admin-only nav-linkki "Käyttäjähallinta" lisätään tähän, ehdollisesti `isAdmin()`.
- `admin_users`-taulu on ainoa kosketuspinta — ei muita tauluja tässä vaiheessa (`posts.author_id` tulee vasta Phase 12:ssa).

</code_context>

<specifics>
## Specific Ideas

Ei erillisiä ulkoasu-/tekstitoiveita — käyttäjähallintasivun oletetaan noudattavan olemassa olevaa admin-paneelin visuaalista tyyliä ilman uutta suunnittelua. Ainoa eksplisiittinen UX-poikkeama muusta koodikannasta on D-01 (taulukko compact-listin sijaan) ja D-02/D-04 (generoitu salasana kerta-näyttönä flash-viestissä).

</specifics>

<deferred>
## Deferred Ideas

Ei uusia scope-ehdotuksia noussut keskustelun aikana. Seuraaviin vaiheisiin jo roadmapissa kuuluvat asiat (ei toistettu tässä):
- Sisältötyyppien roolikohtainen CRUD-rajaus (mod: hevoset/varsat/kilpailut/näyttelyt/postaukset; author: vain omat postaukset + hevoslinkitys) → Phase 12.
- `posts.author_id`-sarake ja sen taannehtiva täyttö → Phase 12 (ei resolvoitu tutkimuksessa, päätettävä Phase 12:n suunnittelussa).
- Poisto-hyväksyntätyönkulku sisällölle (`pending_deletions`) → Phase 13.

### Reviewed Todos (not folded)
None — `todo.match-phase` ei löytänyt osumia (todo_count: 0).

</deferred>

---

*Phase: 11-Käyttäjähallinta*
*Context gathered: 2026-07-16*
