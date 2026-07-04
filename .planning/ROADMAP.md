# Roadmap: Virtuaalitalli

**Milestone:** v1.0 — Täysin toimiva virtuaalitalli tietokantoineen
**Granularity:** Coarse (3–5 phases)
**Status:** Planning

## Overview

Projekti rakentaa PHP/MySQL-pohjaisen virtuaalitallin kokonaan uudelleen: ensin perusta ja tietokanta, sitten julkiset sivut, admin-paneeli ja viimeisenä tietoturva sekä Altervista-deployment.

## Phases

- [x] **Phase 1: Perusta & Tietokantarakenne** - Hakemistorakenne, tietokantaskeema ja PHP-peruspohja
- [x] **Phase 2: Julkiset sivut** - Kaikki 5 julkista sivua live-datalla tietokannasta
- [x] **Phase 3: Admin-paneeli** - Hevosten hallinta, kirjautuminen, kuvat, kilpailut
- [x] **Phase 4: Tietoturva & Viimeistely** - OWASP, CSRF, XSS ja Altervista-deployment
- [x] **Phase 5: Blogi** - Postausten hallinta adminissa, julkinen postauslista ja yksittäinen postaussivu arkistosidebarilla (completed 2026-06-18)
- [ ] **Phase 6: Teema-infrastruktuuri** - theme.php-shim, DB-migraatio, resolveThemePath()-helper ja julkinen/admin-eristys
- [x] **Phase 7: Oletusteman rakenne** - public/themes/default/-rakenne: includes, sivupohjat, CSS ja theme.json (completed 2026-07-03)
- [ ] **Phase 8: Sivukontrollerien migraatio** - Kaikki 7 julkista sivukontrolleria muuttuvat data-only-kontrollereiksi
- [ ] **Phase 9: Admin-teemavalinta & Altervista-verifiointi** - Admin voi valita teeman; järjestelmä toimii tuotannossa

## Phase Details

### Phase 1: Perusta & Tietokantarakenne

**Goal**: Projektirakenne, tietokantaskeemat ja PHP-peruspohja pystyssä. Kaikki myöhemmät vaiheet rakentuvat tähän.
**Depends on**: Nothing (first phase)
**Requirements**: [DB-01, DB-02, DB-03, DB-04, DB-05, HORSE-01, HORSE-02, HORSE-03, HORSE-04, HORSE-05, HORSE-06, HORSE-07, HORSE-08, HORSE-09, HORSE-10]
**Success Criteria** (what must be TRUE):

  1. Sivuston hakemistorakenne on luotu ja PHP include -rakenne toimii (header/footer/nav näkyvät)
  2. Tietokantaskeema on ajettu — kaikki 6 taulua olemassa MySQL:ssä
  3. PDO-yhteys tietokantaan toimii ilman virheitä
  4. Testidata (2–3 hevosta) voidaan lisätä ja lukea tietokannasta

**Plans**: 3 plans

Plans:

- [ ] 01-01: Hakemistorakenne ja PHP-sivupohja (header.php, footer.php, nav.php, config.php)
- [ ] 01-02: MySQL-tietokantaskeema (horses, horse_photos, pedigree, competitions, foals, admin_users)
- [ ] 01-03: PDO-tietokantayhteys ja apufunktiot (db.php, helpers.php)

### Phase 2: Julkiset sivut

**Goal**: Kaikki viisi julkista sivua toimivat ja näyttävät live-dataa tietokannasta.
**Depends on**: Phase 1
**Requirements**: [PUB-01, PUB-02, PUB-03, PUB-04, PUB-05, PUB-06]
**Success Criteria** (what must be TRUE):

  1. Etusivu, hevoset-listaus, profiilisivu, kasvatus ja yhteystiedot latautuvat oikein
  2. Profiilisivu näyttää sukutaulun (3 sukupolvea), kisakalenterin ja kuvagallerian
  3. Navigaatio toimii PHP include:n kautta kaikilla sivuilla

**Plans**: 3 plans

Plans:

- [ ] 02-01: Etusivu (index.php) ja hevoslistaus (hevoset.php)
- [ ] 02-02: Hevosen profiilisivu (hevonen.php) — perustiedot, sukutaulu, kisakalenteri, kuvagalleria
- [ ] 02-03: Kasvatus-sivu (kasvatus.php) ja yhteystiedot-sivu (yhteystiedot.php)

### Phase 3: Admin-paneeli

**Goal**: Täysin toimiva admin-paneeli hevosten, kuvien, kilpailujen ja kasvatustietojen hallintaan, suojattuna salasanalla.
**Depends on**: Phase 2
**Requirements**: [AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05, ADMIN-01, ADMIN-02, ADMIN-03, ADMIN-04, ADMIN-05, PHOTO-01, PHOTO-02, PHOTO-03, PHOTO-04, PHOTO-05, COMP-01, COMP-02, COMP-03, BREED-01, BREED-02, BREED-03]
**Success Criteria** (what must be TRUE):

  1. Admin voi kirjautua sisään ja ulos; session suojaa kaikki admin-sivut
  2. Admin voi lisätä, muokata ja poistaa hevosia kaikkine kenttiineen
  3. Admin voi ladata ja poistaa kuvia (max 5 per hevonen); kuva näkyy julkisella sivulla
  4. Admin voi hallita kilpailuja ja varsamerkintöjä

**Plans**: 4 plans

Plans:

- [ ] 03-01: Admin-kirjautuminen (login.php, session-hallinta, logout.php)
- [ ] 03-02: Hevosten CRUD-lomakkeet (lisää/muokkaa/poista, sukutaulun hallinta)
- [ ] 03-03: Kuvien lataus ja hallinta (file upload validointeineen)
- [ ] 03-04: Kisakalenterin ja kasvatustietojen hallinta

### Phase 4: Tietoturva & Viimeistely

**Goal**: Kaikki OWASP-tietoturvakohteet toteutettu ja koko sivusto on valmis julkaistavaksi Altervistaan automatisoidulla CI/CD-deploymentilla.
**Depends on**: Phase 3
**Requirements**: [SEC-01, SEC-02, SEC-03, SEC-04, SEC-05, SEC-06, SEC-07, SEC-08]
**Success Criteria** (what must be TRUE):

  1. Kaikki tietokantakyselyt käyttävät PDO prepared statements, kaikki tulosteet suojattu htmlspecialchars():lla
  2. Kaikki lomakkeet sisältävät CSRF-tokenin ja palvelin validoi sen
  3. Kuvalataus hylkää PHP-tiedostot ja väärät MIME-tyypit
  4. Sivusto toimii oikein Altervistan tuotantoympäristössä
  5. Push main-haaraan deployaa automaattisesti vain `public/`-hakemiston Altervistaan GitHub Actionsin (FTP) kautta

**Plans**: 2 plans

Plans:

- [x] PLAN.md: Tietoturva-audit ja korjaukset (SEC-01–SEC-08: SQL-injektio, XSS, CSRF, upload, session, virheet) — completed 2026-06-17
- [x] 04-02-PLAN.md: GitHub Actions CI/CD -deployment Altervistaan (deploy.yml + DEPLOYMENT_CHECKLIST päivitys) — completed 2026-06-18

---
*Roadmap created: 2026-06-17*
*Last updated: 2026-06-22 — v1.1 Teemajärjestelmä phases 6–9 added*

### Phase 5: Blogi

**Goal**: Tallinpitäjä voi kirjoittaa blogipostauksia adminissa; vierailijat lukevat ne julkisella puolella postauslistalta tai yksittäiseltä sivulta sticky sidebar -arkistolla.
**Depends on**: Phase 2, Phase 3
**Requirements**: [BLOG-01, BLOG-02, BLOG-03, BLOG-04, BLOG-05, BLOG-06]
**Success Criteria** (what must be TRUE):

  1. `posts`-taulukko luotu ja migraatio ajettavissa
  2. Julkinen postauslista (`/pages/blogi.php`) näyttää kaikki postaukset uusimmasta vanhimpaan
  3. Yksittäinen postaussivu (`/pages/postaus.php`) näyttää artikkelin sticky sidebar -arkistolla (vuosi→kuukausi accordion)
  4. Etusivun overlay-kortti linkittää uusimpaan postaukseen
  5. Admin voi lisätä, muokata ja poistaa postauksia (`/admin/posts.php`)

**Plans**: 0 plans

---

## v1.1 — Teemajärjestelmä

### Phase 6: Teema-infrastruktuuri

**Goal**: Teemajärjestelmän perusta on pystyssä — julkiset sivut saavat THEME_PATH/THEME_URL-vakiot shimistä, aktiivinen teema tallennetaan tietokantaan, ja path-traversal-hyökkäykset on estetty.
**Depends on**: Phase 5
**Requirements**: [THEME-01, THEME-02, THEME-03, THEME-04]
**Success Criteria** (what must be TRUE):

  1. `resolveThemePath()` palauttaa oikean polun aktiiviiselle teemalle ja fallbackaa `default`-teemaan kun tiedosto puuttuu
  2. `resolveThemePath()` hylkää teemanimet joissa on polkutraversaalimerkkejä (`../`, `%2F` jne.) — testi: syöte `../../etc/passwd` ei tuota osumaa
  3. Julkinen sivu saa `THEME_PATH`- ja `THEME_URL`-vakiot `src/includes/theme.php`-shimistä; admin-sivu ei lataa shimmiä lainkaan
  4. `settings`-taulussa on `active_theme`-rivi ja sen arvo on haettavissa tietokannasta
  5. Jokainen teemakansio jolla on `theme.json` löytyy ja luetaan oikein (nimi, versio)

**Plans**: 2 plans
Plans:
**Wave 1**

- [x] 06-01-PLAN.md — migrate_theme.sql (active_theme-rivi) ja public/themes/default/theme.json (THEME-02, THEME-04)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 06-02-PLAN.md — theme.php-shim (THEME_PATH/THEME_URL + resolveThemePath path-traversal-suojauksella) ja index.php-integraatiotodistus (THEME-01, THEME-03)

**UI hint**: no

### Phase 7: Oletusteman rakenne

**Goal**: Sivusto näyttää täsmälleen samalta kuin ennen, mutta kaikki julkinen HTML on nyt `public/themes/default/`-rakenteessa ja admin-puoli käyttää edelleen muuttumattomia tiedostojaan.
**Depends on**: Phase 6
**Requirements**: [THEME-05, THEME-06, THEME-07]
**Success Criteria** (what must be TRUE):

  1. `public/themes/default/includes/` sisältää header.php, footer.php ja nav.php — sivusto näyttää visuaalisesti identtiseltä kuin ennen
  2. `public/themes/default/pages/` sisältää kaikki 7 sivupohjaa (index, hevoset, hevonen, kasvatus, yhteystiedot, blogi, postaus)
  3. `public/themes/default/assets/css/style.css` palvelee teeman tyylit; `public/assets/css/style.css` pysyy muuttumattomana (admin käyttää sitä)
  4. `public/themes/default/theme.json` on olemassa ja sisältää nimen ja version

**Plans**: 4/4 plans complete

Plans:
**Wave 1**

- [x] 07-01-PLAN.md — teeman includet (header/footer/nav) + style.css + theme.json (THEME-05, THEME-07)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 07-02-PLAN.md — 4 sivupohjaa: index, hevoset, kasvatus, yhteystiedot (THEME-06)
- [x] 07-03-PLAN.md — sivupohja: hevonen (yksittäisen hevosen profiili) (THEME-06)
- [x] 07-04-PLAN.md — 2 sivupohjaa: ajankohtaista (blogilistaus), postaus (THEME-06)

**UI hint**: yes

### Phase 8: Sivukontrollerien migraatio

**Goal**: Kaikki 7 julkista sivukontrolleria ovat data-only — ne hakevat datan tietokannasta ja delegoivat kaiken HTML-renderöinnin aktiivisen teeman sivupohjille `resolveThemePath()`:n kautta.
**Depends on**: Phase 7
**Requirements**: [THEME-08, THEME-09]
**Success Criteria** (what must be TRUE):

  1. Kaikki 5 perussivua (index.php, hevoset.php, hevonen.php, kasvatus.php, yhteystiedot.php) latautuvat oikein ilman inline-HTML:ää kontrollerissa
  2. Molemmat blogi-sivut (blogi.php, postaus.php) latautuvat oikein data-only-mallin mukaisesti
  3. Teeman vaihtaminen (manuaalisesti DB:ssä) vaihtaa sivuston ulkoasun — kontrolleri ei tarvitse muutoksia

**Plans**: 1/4 plans executed
**Wave 1**

- [x] 08-01-PLAN.md — data-only migration: index.php, hevoset.php, kasvatus.php (THEME-08)
- [ ] 08-02-PLAN.md — data-only migration: yhteystiedot.php, ajankohtaista.php, postaus.php + unified 404 (THEME-08, THEME-09)
- [ ] 08-03-PLAN.md — data-only migration: hevonen.php (heavy: strip helpers, unify 404) (THEME-08)

**Wave 2** *(blocked on Wave 1 completion)*

- [ ] 08-04-PLAN.md — temporary test theme: prove DB theme-switch changes appearance with zero controller edits, then delete (D-06/D-07)

**Cross-cutting constraints:**

- None of the 3 controllers contain inline HTML, a header.php require, or a footer.php require
- Each of the 3 controllers ends with a resolveThemePath('pages/X.php') delegation guarded by a 404 fallback
- Each of the 3 controllers carries the identical Model B root-override hook (D-02)

**UI hint**: no

### Phase 9: Admin-teemavalinta & Altervista-verifiointi

**Goal**: Tallinpitäjä voi vaihtaa aktiivisen teeman admin-paneelista yhdellä klikkauksella, ja koko teemajärjestelmä toimii varmistettuna Altervistan tuotantoympäristössä.
**Depends on**: Phase 8
**Requirements**: [THEME-10, THEME-11, THEME-12]
**Success Criteria** (what must be TRUE):

  1. Admin-paneelin settings.php listaa kaikki asennetut teemat `theme.json`-nimillä (ei raakoja hakemistonimiä)
  2. Admin voi valita teeman listasta ja tallentaa valinnan — sivusto vaihtaa ulkoasun välittömästi
  3. Teeman tallennus hylkää arvot jotka eivät ole asennettujen teemojen allowlistilla; CSRF-token vaaditaan
  4. `public/themes/`-kansio on suojattu suoralta selailuulta Altervistassa
  5. Teeman CSS latautuu oikealla MIME-tyypillä (`text/css`) Altervistassa — testattu FTP-deploymentilla

**Plans**: TBD
**UI hint**: yes

## Progress Table

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Perusta & Tietokantarakenne | 3/3 | Complete | 2026-06-17 |
| 2. Julkiset sivut | 3/3 | Complete | 2026-06-17 |
| 3. Admin-paneeli | 4/4 | Complete | 2026-06-17 |
| 4. Tietoturva & Viimeistely | 2/2 | Complete | 2026-06-18 |
| 5. Blogi | 0/0 | Complete | 2026-06-18 |
| 6. Teema-infrastruktuuri | 2/2 | Complete | 2026-06-22 |
| 7. Oletusteman rakenne | 4/4 | Complete    | 2026-07-03 |
| 8. Sivukontrollerien migraatio | 1/4 | In Progress|  |
| 9. Admin-teemavalinta & Altervista-verifiointi | 0/? | Not started | - |
