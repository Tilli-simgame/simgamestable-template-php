# Requirements: Virtuaalitalli — v1.2 Käyttäjäroolit

**Defined:** 2026-07-05
**Core Value:** Hevosomistaja voi hallita koko tallinsa hevostietoja yhdestä turvallisesta admin-paneelista, ja kaikki tieto näkyy automaattisesti julkisella sivustolla.

## v1.2 Requirements — Käyttäjäroolit

### Roolit & pääsynhallinta

- [x] **ROLE-01**: Järjestelmässä on kolme roolia (admin, mod, author) tallennettuna `admin_users`-tauluun
- [x] **ROLE-02**: Käyttäjän rooli tallennetaan sessioon kirjautumisen yhteydessä ja tarkistetaan palvelinpuolella jokaisella suojatulla admin-sivulla
- [x] **ROLE-03**: Käyttäjä joka yrittää avata roolinsa ulkopuolisen admin-sivun ohjataan "Ei käyttöoikeutta" -näkymään
- [x] **ROLE-04**: Admin-navigaatio näyttää vain käyttäjän roolille sallitut valikkokohdat

### Käyttäjähallinta

- [x] **USER-01**: Admin voi luoda uuden käyttäjätunnuksen (käyttäjänimi, salasana, rooli)
- [x] **USER-02**: Admin voi muokata olemassa olevan käyttäjän roolia ja käyttäjänimeä
- [x] **USER-03**: Admin voi deaktivoida käyttäjätunnuksen ilman että käyttäjän aiempi sisältö (esim. postausten tekijätieto) katoaa
- [x] **USER-04**: Admin voi poistaa käyttäjätunnuksen pysyvästi
- [x] **USER-05**: Admin voi nollata toisen käyttäjän salasanan ilman että tarvitsee tietää vanhaa salasanaa
- [x] **USER-06**: Järjestelmä estää viimeisen admin-tunnuksen poistamisen tai deaktivoinnin
- [x] **USER-07**: Admin ei voi poistaa tai deaktivoida omaa tunnustaan

### Salasanan vaihto

- [x] **AUTH-06**: Kirjautunut käyttäjä (mikä tahansa rooli) voi vaihtaa oman salasanansa antamalla nykyisen salasanan sekä uuden salasanan kahdesti

### Mod-rajattu sisällönhallinta

- [x] **MOD-01**: Mod-rooli voi luoda ja muokata hevosia sekä niiden kuvia
- [x] **MOD-02**: Mod-rooli voi luoda ja muokata varsamerkintöjä
- [x] **MOD-03**: Mod-rooli voi luoda ja muokata kilpailuja
- [x] **MOD-04**: Mod-rooli voi luoda ja muokata näyttelytuloksia (showrecords)
- [x] **MOD-05**: Mod-rooli voi luoda ja muokata postauksia
- [ ] **MOD-06**: Mod-roolin poistopyyntö hevosesta/varsasta/kilpailusta/näyttelytuloksesta/postauksesta ei poista sisältöä heti, vaan asettaa sen odottamaan admin-hyväksyntää
- [x] **MOD-07**: Mod-roolilla ei ole pääsyä käyttäjähallintaan eikä teema-asetuksiin

### Poisto-hyväksyntätyönkulku

- [ ] **DEL-01**: Admin näkee yhden näkymän kaikista odottavista poistopyynnöistä (hevoset, varsat, kilpailut, näyttelyt, postaukset)
- [ ] **DEL-02**: Admin voi hyväksyä poistopyynnön, jolloin sisältö poistuu (pehmeästi) näkyvistä
- [ ] **DEL-03**: Admin voi hylätä poistopyynnön, jolloin sisältö palautuu normaalisti näkyväksi
- [ ] **DEL-04**: Admin-etusivulla näkyy laskuri odottavien poistopyyntöjen määrästä
- [x] **DEL-05**: Sama sisältö ei voi olla useamman kertaan poistojonossa samanaikaisesti

### Author-rajattu sisällönhallinta

- [x] **AUTHOR-01**: Author-rooli voi luoda uuden postauksen
- [x] **AUTHOR-02**: Author-rooli voi muokata vain omia postauksiaan
- [ ] **AUTHOR-03**: Author-rooli voi poistaa vain omia postauksiaan välittömästi (ilman hyväksyntää)
- [x] **AUTHOR-04**: Author-rooli voi linkittää olemassa olevia hevosia postaukseensa valitsemalla ne listalta (ei muokkausoikeutta hevostietoihin)
- [x] **AUTHOR-05**: Author-roolilla ei ole pääsyä muihin admin-toimintoihin (hevoset, varsat, kilpailut, näyttelyt, käyttäjähallinta, teema-asetukset)

## v2 Requirements

Siirretty tulevaisuuteen tutkimuksen perusteella (ks. `.planning/research/FEATURES.md`):

### Käyttäjähallinnan laajennukset

- **USER-V2-01**: Hylkäämisen syy-kenttä (admin voi kirjoittaa lyhyen selityksen kun hylkää mod-poistopyynnön)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Granulaarinen oikeusmatriisi (per-toiminto, per-resurssi) | 3 kiinteää roolia riittää 2-4 käyttäjän tallille; ei tarvetta mukautettaville rooleille |
| Moniportainen hyväksyntäketju (useampi admin hyväksyy) | Ratkaisee ongelman jota ei ole yhden admin-tunnuksen järjestelmässä |
| Yleiskäyttöinen audit-loki / aktiviteettivirta | pending_deletions-taulun requested_by/resolved_by/timestamp-kentät riittävät tarpeeseen |
| Itserekisteröityminen / kutsulinkit | Vain admin luo tunnuksia — vahvistettu vaatimus |
| Roolikohtainen UI-teemoitus / eri dashboard-layoutit rooleittain | Sama admin-layout piilottaa navigaation kohdat roolin mukaan riittää |
| Aikarajatut/vanhenevat poistopyynnöt (auto-approve/reject) | Vaatisi cron-ajastuksen, ei saatavilla Altervistan ilmaistasolla |
| Salasanan vahvuusmittari / breach-tarkistus (esim. haveibeenpwned) | Suhteeton 2-4 hengen sisäiselle työkalulle; bcrypt+CSRF+PDO jo kattaa OWASP-painopisteen |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| ROLE-01 | Phase 10 — Roolit ja autentikaation perusta | Mapped |
| ROLE-02 | Phase 10 — Roolit ja autentikaation perusta | Mapped |
| ROLE-03 | Phase 10 — Roolit ja autentikaation perusta | Mapped |
| ROLE-04 | Phase 10 — Roolit ja autentikaation perusta | Mapped |
| AUTH-06 | Phase 10 — Roolit ja autentikaation perusta | Mapped |
| USER-01 | Phase 11 — Käyttäjähallinta | Mapped |
| USER-02 | Phase 11 — Käyttäjähallinta | Mapped |
| USER-03 | Phase 11 — Käyttäjähallinta | Mapped |
| USER-04 | Phase 11 — Käyttäjähallinta | Mapped |
| USER-05 | Phase 11 — Käyttäjähallinta | Mapped |
| USER-06 | Phase 11 — Käyttäjähallinta | Mapped |
| USER-07 | Phase 11 — Käyttäjähallinta | Mapped |
| MOD-01 | Phase 12 — Sisältötyyppien roolirajaus | Mapped |
| MOD-02 | Phase 12 — Sisältötyyppien roolirajaus | Mapped |
| MOD-03 | Phase 12 — Sisältötyyppien roolirajaus | Mapped |
| MOD-04 | Phase 12 — Sisältötyyppien roolirajaus | Mapped |
| MOD-05 | Phase 12 — Sisältötyyppien roolirajaus | Mapped |
| MOD-06 | Phase 13 — Poisto-hyväksyntätyönkulku | Mapped |
| MOD-07 | Phase 12 — Sisältötyyppien roolirajaus | Mapped |
| DEL-01 | Phase 13 — Poisto-hyväksyntätyönkulku | Mapped |
| DEL-02 | Phase 13 — Poisto-hyväksyntätyönkulku | Mapped |
| DEL-03 | Phase 13 — Poisto-hyväksyntätyönkulku | Mapped |
| DEL-04 | Phase 13 — Poisto-hyväksyntätyönkulku | Mapped |
| DEL-05 | Phase 13 — Poisto-hyväksyntätyönkulku | Mapped |
| AUTHOR-01 | Phase 12 — Sisältötyyppien roolirajaus | Mapped |
| AUTHOR-02 | Phase 12 — Sisältötyyppien roolirajaus | Mapped |
| AUTHOR-03 | Phase 13 — Poisto-hyväksyntätyönkulku | Mapped |
| AUTHOR-04 | Phase 12 — Sisältötyyppien roolirajaus | Mapped |
| AUTHOR-05 | Phase 12 — Sisältötyyppien roolirajaus | Mapped |

**Coverage:**

- v1.2 requirements: 29 total
- Mapped to phases: 29
- Unmapped: 0 ✓

**Note:** `MOD-06` and `AUTHOR-03` are grouped with the delete-approval workflow (Phase 13) rather than the content-gating phase (Phase 12), because both depend on the soft-delete/`pending_deletions` schema introduced there — mod (pending approval), admin (direct soft-delete), and author-on-own-posts (immediate soft-delete) delete branches are implemented as one coherent unit of work in `post_delete.php` and equivalent handlers.

---
*Requirements defined: 2026-07-05*
*Last updated: 2026-07-05 — traceability mapped to Phases 10-13 during roadmap creation*
