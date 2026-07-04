# Requirements: Virtuaalitalli

**Defined:** 2026-06-17
**Core Value:** Hevosomistaja voi hallita koko tallinsa hevostietoja yhdestä turvallisesta admin-paneelista, ja kaikki tieto näkyy automaattisesti julkisella sivustolla.

## v1 Requirements

### Projektirakenne & Tietokanta

- [ ] **DB-01**: Projekti sisältää PHP-hakemistorakenteen (includes/, pages/, admin/, assets/, uploads/)
- [ ] **DB-02**: MySQL-tietokanta sisältää taulut: `horses`, `horse_photos`, `pedigree`, `competitions`, `foals`, `admin_users`
- [ ] **DB-03**: Tietokantayhteys käyttää PDO:ta prepared statements -tuelle
- [ ] **DB-04**: Konfiguraatiotiedosto (`config.php`) on sijoitettu web-juuren ulkopuolelle tai suojattu `.htaccess`:lla
- [ ] **DB-05**: PHP include -rakenne (header.php, footer.php, nav.php) käytössä kaikilla sivuilla

### Hevosen tietomalli

- [ ] **HORSE-01**: Hevosella on perustiedot: nimi, kutsumanimi, rotu, syntymäpäivä, ikääntyminen, sukupuoli, väri, säkäkorkeus, VH-tunnus
- [ ] **HORSE-02**: Hevosella on omistajan tiedot: nimi, sähköposti
- [ ] **HORSE-03**: Hevosella on kasvattajan tiedot: nimi, sähköposti
- [ ] **HORSE-04**: Hevosella on tuojan tiedot: nimi, sähköposti
- [ ] **HORSE-05**: Hevosella on painotuslaji ja painotustaso
- [ ] **HORSE-06**: Hevosella on luonnekuvaus (vapaa tekstikenttä)
- [ ] **HORSE-07**: Hevosella on sukutaulu kolmelle sukupolvelle (vanhemmat, isovanhemmat, isoisovanhemmat)
- [ ] **HORSE-08**: Hevosella on sukuselvitys (vapaa tekstikenttä)
- [ ] **HORSE-09**: Hevoseen voi liittää 0–5 kuvaa (galleria)
- [ ] **HORSE-10**: Hevosella on kisakalenteri (kilpailun nimi, päivämäärä, tulos, huomiot)

### Julkiset sivut

- [ ] **PUB-01**: Etusivu näyttää tallin esittelyn ja tiivistetyn listauksen hevosista
- [ ] **PUB-02**: Hevoset-sivu listaa kaikki tallin hevoset (nimi, rotu, sukupuoli, kuva)
- [ ] **PUB-03**: Hevosen profiilisivu näyttää kaikki hevosen tiedot (perustiedot, sukutaulu, kisakalenteri, kuvagalleria)
- [ ] **PUB-04**: Kasvatus-sivu listaa menneet ja tulevat varsomiset (varsan nimi, isä, emä, syntymävuosi, sukupuoli)
- [ ] **PUB-05**: Yhteystiedot-sivu näyttää tallin yhteystiedot (staattinen tai DB-pohjainen)
- [ ] **PUB-06**: Navigaatio on yhtenäinen kaikilla sivuilla (PHP include)

### Admin-paneeli — autentikaatio

- [ ] **AUTH-01**: Admin pääsee kirjautumissivulle (`/admin/login.php`)
- [ ] **AUTH-02**: Kirjautuminen vaatii oikean salasanan (bcrypt-tiivistetty, tallennettu tietokantaan)
- [ ] **AUTH-03**: Onnistunut kirjautuminen luo PHP-session
- [ ] **AUTH-04**: Kaikki admin-sivut tarkistavat aktiivisen session — suojaamaton pyyntö ohjataan login-sivulle
- [ ] **AUTH-05**: Uloskirjautuminen tuhoaa session

### Admin-paneeli — hevostenhallinta

- [ ] **ADMIN-01**: Admin voi lisätä uuden hevosen lomakkeella (kaikki kentät)
- [ ] **ADMIN-02**: Admin voi muokata olemassa olevan hevosen tietoja
- [ ] **ADMIN-03**: Admin voi poistaa hevosen (pehmeä poisto tai vahvistus ennen poistoa)
- [ ] **ADMIN-04**: Admin näkee listauksen kaikista hevosista hallintanäkymässä
- [ ] **ADMIN-05**: Admin voi hallita sukutaulua (lisää/muokkaa vanhemmat, isovanhemmat, isoisovanhemmat)

### Admin-paneeli — kuvat

- [ ] **PHOTO-01**: Admin voi ladata kuvan hevoselle (tiedostolataus palvelimelle)
- [ ] **PHOTO-02**: Järjestelmä validoi kuvatiedoston tyypin (jpg, jpeg, png, gif) ja koon
- [ ] **PHOTO-03**: Ladatut kuvat tallennetaan `uploads/`-hakemistoon, URL tietokantaan
- [ ] **PHOTO-04**: Admin voi poistaa hevosen kuvan
- [ ] **PHOTO-05**: Järjestelmä estää yli 5 kuvan lataamisen per hevonen

### Admin-paneeli — kisakalenteri

- [ ] **COMP-01**: Admin voi lisätä kilpailun hevoselle (nimi, päivämäärä, tulos, huomiot)
- [ ] **COMP-02**: Admin voi muokata kilpailutietoa
- [ ] **COMP-03**: Admin voi poistaa kilpailun

### Admin-paneeli — kasvatus

- [ ] **BREED-01**: Admin voi lisätä varsamerkinnän (varsan nimi, isä, emä, syntymävuosi, sukupuoli, status: mennyt/tuleva)
- [ ] **BREED-02**: Admin voi muokata varsamerkintää
- [ ] **BREED-03**: Admin voi poistaa varsamerkinnän

### Tietoturva

- [ ] **SEC-01**: Kaikki tietokantakyselyt käyttävät PDO prepared statements -menetelmää (SQL-injektiosuojaus)
- [ ] **SEC-02**: Kaikki käyttäjäsyöte validoidaan ja sanitoidaan palvelinpäässä ennen tallennusta
- [ ] **SEC-03**: HTML-tulosteet suojataan `htmlspecialchars()`:lla (XSS-suojaus)
- [ ] **SEC-04**: Lomakkeet sisältävät CSRF-tokenin ja palvelin tarkistaa sen ennen käsittelyä
- [ ] **SEC-05**: Kuvatiedostojen lataus validoi tyypin (MIME + tarkistus) ja koon — ei PHP-koodia hyväksytä
- [ ] **SEC-06**: Tietokantayhteyden tunnistetiedot eivät ole web-juuressa tai ne on suojattu
- [ ] **SEC-07**: Admin-sessio käyttää turvallisia PHP-sessioasetuksia (httponly, secure cookies)
- [ ] **SEC-08**: Virheilmoitukset eivät paljasta tietokanta- tai hakemistorakennetta julkisesti

## v1.1 Requirements — Teemajärjestelmä

### Teema-infrastruktuuri

- [x] **THEME-01**: Tallinpitäjä voi käyttää `resolveThemePath()`-helperia joka lataa tiedoston aktiivisesta teemasta path-traversal-suojatusti (`realpath` + prefix-check) ja fallback-oletuksena `default`-teemaan jos tiedosto puuttuu
- [x] **THEME-02**: Järjestelmä tallentaa aktiivisen teeman nimen `settings`-tauluun `active_theme`-rivinä
- [x] **THEME-03**: Julkiset sivut saavat `THEME_PATH`- ja `THEME_URL`-vakiot `src/includes/theme.php`-shimistä; admin-paneeli ei koskaan lataa shimmiä
- [x] **THEME-04**: Jokainen teema sisältää `theme.json`-tiedoston (nimi, versio) admin-löydettävyyttä varten

### Oletustema

- [x] **THEME-05**: Oletustema sisältää `public/themes/default/includes/` — header.php, footer.php, nav.php nykyisen ilmeen mukaan
- [x] **THEME-06**: Oletustema sisältää `public/themes/default/pages/` — kaikki 7 julkista sivupohjaa (index, hevoset, hevonen, kasvatus, yhteystiedot, blogi, postaus)
- [x] **THEME-07**: Oletustema sisältää `public/themes/default/assets/css/style.css` nykyisten tyylien mukaan

### Sivukontrollerien migraatio

- [x] **THEME-08**: Kaikki 5 julkista sivukontrolleria (index.php, hevoset.php, hevonen.php, kasvatus.php, yhteystiedot.php) muuttuvat data-only-kontrollereiksi jotka käyttävät `resolveThemePath()`
- [x] **THEME-09**: Blogi-sivukontrollerit (blogi.php, postaus.php) muuttuvat data-only-kontrollereiksi jotka käyttävät `resolveThemePath()`

### Admin-teemavalinta

- [ ] **THEME-10**: Admin voi nähdä kaikki asennetut teemat listana `settings.php`:ssä (`glob()`-löydös, `theme.json`-nimi näytettynä)
- [ ] **THEME-11**: Admin voi valita aktiivisen teeman ja tallentaa valinnan (allowlist-validoitu POST + CSRF-suojaus)
- [ ] **THEME-12**: Tallinpitäjä voi varmistaa että teemajärjestelmä toimii Altervistassa (CSS MIME-tyypit, URL-polut, `public/themes/`-kansion suojaus)

## v2 Requirements

### Tulevaisuuden laajennukset

- **V2-01**: Useampi admin-käyttäjä rooleineen
- **V2-02**: Hakutoiminto hevoslistauksen suodattamiseen
- **V2-03**: Hevosen profiilisivun tulostettava versio
- **V2-04**: Talliesittely-sivu (säännöt, info)
- **V2-05**: Teeman esikatselukuva (`preview.png`) admin-listauksessa
- **V2-06**: Toinen valmis teema tarjolla

## Out of Scope

| Feature | Reason |
|---------|--------|
| Useampi admin | MVP vaatii vain yhden omistajan hallinnan |
| Julkinen rekisteröityminen | Sivusto on esittelysivu, ei yhteisö |
| Maksujärjestelmä | Ei kaupallinen sivusto |
| Varausjärjestelmä | Ei pyydetty |
| Framework (Laravel, Symfony) | Altervista-yksinkertaisuus ja omistajan PHP-taito |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| DB-01–DB-05 | Phase 1 | Complete (v1.0) |
| HORSE-01–HORSE-10 | Phase 1 | Complete (v1.0) |
| PUB-01–PUB-06 | Phase 2 | Complete (v1.0) |
| AUTH-01–AUTH-05 | Phase 3 | Complete (v1.0) |
| ADMIN-01–ADMIN-05 | Phase 3 | Complete (v1.0) |
| PHOTO-01–PHOTO-05 | Phase 3 | Complete (v1.0) |
| COMP-01–COMP-03 | Phase 3 | Complete (v1.0) |
| BREED-01–BREED-03 | Phase 3 | Complete (v1.0) |
| SEC-01–SEC-08 | Phase 4 | Complete (v1.0) |
| THEME-01–THEME-04 | Phase 6 (v1.1) | Pending |
| THEME-05–THEME-07 | Phase 7 (v1.1) | Pending |
| THEME-08–THEME-09 | Phase 8 (v1.1) | Pending |
| THEME-10–THEME-12 | Phase 9 (v1.1) | Pending |

**Coverage:**

- v1.0 requirements: 43 total — all complete ✓
- v1.1 requirements: 12 total
- Mapped to phases: 12
- Unmapped: 0 ✓

---
*Requirements defined: 2026-06-17*
*Last updated: 2026-06-22 — v1.1 Teemajärjestelmä requirements added (THEME-01–THEME-12)*
