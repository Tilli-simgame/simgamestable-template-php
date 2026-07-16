# Roadmap: Virtuaalitalli

## Milestones

- ✅ **v1.0 MVP** — Phases 1-5 (shipped 2026-06-18)
- ✅ **v1.1 Teemajärjestelmä** — Phases 6-9 (shipped 2026-07-05)
- 🚧 **v1.2 Käyttäjäroolit** — Phases 10-13 (in progress)

## Phases

<details>
<summary>✅ v1.0 MVP (Phases 1-5) — SHIPPED 2026-06-18</summary>

- [x] Phase 1: Perusta & Tietokantarakenne (3/3 plans) — completed 2026-06-17
- [x] Phase 2: Julkiset sivut (3/3 plans) — completed 2026-06-17
- [x] Phase 3: Admin-paneeli (4/4 plans) — completed 2026-06-17
- [x] Phase 4: Tietoturva & Viimeistely (2/2 plans) — completed 2026-06-18
- [x] Phase 5: Blogi (0/0 plans) — completed 2026-06-18

</details>

<details>
<summary>✅ v1.1 Teemajärjestelmä (Phases 6-9) — SHIPPED 2026-07-05</summary>

- [x] Phase 6: Teema-infrastruktuuri (2/2 plans) — completed 2026-06-22
- [x] Phase 7: Oletusteman rakenne (4/4 plans) — completed 2026-07-03
- [x] Phase 8: Sivukontrollerien migraatio (4/4 plans) — completed 2026-07-04
- [x] Phase 9: Admin-teemavalinta & Altervista-verifiointi (2/2 plans) — completed 2026-07-05

See `.planning/milestones/v1.1-ROADMAP.md` for full phase details.

</details>

### 🚧 v1.2 Käyttäjäroolit (Phases 10-13) — IN PROGRESS

**Milestone Goal:** Adminin lisäksi tallilla voi olla mod- ja author-käyttäjiä rajatuin oikeuksin; kaikki käyttäjät voivat vaihtaa oman salasanansa; vain admin voi luoda uusia tunnuksia.

- [ ] **Phase 10: Roolit ja autentikaation perusta** - Kolme roolia (admin/mod/author) tunnistetaan sessiossa ja jokaisella suojatulla admin-sivulla; kaikki roolit voivat vaihtaa oman salasanansa
- [ ] **Phase 11: Käyttäjähallinta** - Admin luo, muokkaa, deaktivoi ja poistaa käyttäjätunnuksia sekä nollaa salasanoja ilman lukkiutumisriskiä
- [ ] **Phase 12: Sisältötyyppien roolirajaus** - Mod luo/muokkaa hevosia, varsoja, kilpailuja, näyttelytuloksia ja postauksia; author luo/muokkaa vain omia postauksiaan ja linkittää niihin olemassa olevia hevosia
- [ ] **Phase 13: Poisto-hyväksyntätyönkulku** - Modin poistopyynnöt odottavat admin-hyväksyntää yhdessä näkymässä; author poistaa omat postauksensa heti ilman hyväksyntää

## Phase Details

### Phase 10: Roolit ja autentikaation perusta

**Goal**: Kolme roolia (admin/mod/author) tallentuu `admin_users`-tauluun ja tunnistetaan sessiossa jokaisella suojatulla admin-sivulla; kaikki kirjautuneet käyttäjät voivat vaihtaa oman salasanansa.
**Depends on**: Olemassa oleva admin-autentikaatio (v1.0 Phase 3 — `requireLogin()`/`public/src/includes/helpers.php`)
**Requirements**: ROLE-01, ROLE-02, ROLE-03, ROLE-04, AUTH-06
**Success Criteria** (what must be TRUE):

  1. Käyttäjän rooli (admin/mod/author) luetaan `admin_users`-taulusta ja tallentuu sessioon kirjautumisen yhteydessä.
  2. Kun mod- tai author-käyttäjä avaa suoralla osoitteella admin-sivun, joka ei kuulu hänen roolilleen, hän ohjautuu "Ei käyttöoikeutta" -näkymään sivun sisällön sijaan.
  3. Admin-navigaatio näyttää kullekin roolille vain sen omat valikkokohdat (esim. author ei näe käyttäjähallinta- eikä teema-asetuslinkkejä).
  4. Kirjautunut käyttäjä (mikä tahansa rooli) voi vaihtaa oman salasanansa antamalla nykyisen salasanan sekä uuden kahdesti, ja kirjautuminen onnistuu heti uudella salasanalla.

**Plans**: 1/3 plans executed
**Wave 1**

- [x] 10-01-PLAN.md — Rooli-infrastruktuuri: migrate_roles.sql + helpers.php (requireRole/currentRole/isAdmin) + login.php (rooli + is_active) + ei-oikeutta.php (Wave 1)

**Wave 2** *(blocked on Wave 1 completion)*

- [ ] 10-02-PLAN.md — Sivukohtainen roolisuojaus: 27 admin-sivun gate-swapit + 4 sekatiedoston inline delete-alagate (Wave 2)
- [ ] 10-03-PLAN.md — Navigaation roolinäkyvyys (admin_header.php) + salasananvaihto (change_password.php) (Wave 2)

### Phase 11: Käyttäjähallinta

**Goal**: Admin voi hallita kaikkia käyttäjätunnuksia turvallisesti — talli ei voi koskaan jäädä ilman toimivaa admin-tiliä.
**Depends on**: Phase 10 (tarvitsee `requireRole('admin')`-suojauksen ja rooli-sessiomekanismin)
**Requirements**: USER-01, USER-02, USER-03, USER-04, USER-05, USER-06, USER-07
**Success Criteria** (what must be TRUE):

  1. Admin voi luoda uuden käyttäjätunnuksen (käyttäjänimi, salasana, rooli), ja uusi käyttäjä pystyy kirjautumaan sisään heti annetulla roolilla.
  2. Admin voi muokata olemassa olevan käyttäjän roolia ja käyttäjänimeä, ja muutos vaikuttaa käyttäjän oikeuksiin viimeistään seuraavalla pyynnöllä.
  3. Admin voi deaktivoida käyttäjän — deaktivoitu tunnus ei pysty kirjautumaan, mutta käyttäjän aiemmin luoma sisältö (esim. postausten tekijätieto) säilyy näkyvissä ennallaan.
  4. Admin voi poistaa käyttäjätunnuksen pysyvästi tai nollata toisen käyttäjän salasanan ilman että tarvitsee tietää vanhaa salasanaa.
  5. Järjestelmä estää viimeisen admin-tunnuksen poiston/deaktivoinnin sekä adminin oman tunnuksen poiston/deaktivoinnin — yritys näyttää virheilmoituksen eikä toimintoa suoriteta.

**Plans**: TBD

### Phase 12: Sisältötyyppien roolirajaus

**Goal**: Mod voi ylläpitää tallin sisältöä (hevoset, varsat, kilpailut, näyttelyt, postaukset) omalla roolillaan; author voi ylläpitää vain omia postauksiaan ja linkittää niihin olemassa olevia hevosia; kumpikaan ei pääse käyttäjähallintaan eikä teema-asetuksiin.
**Depends on**: Phase 10, Phase 11 (roolimekanismin ja oikeiden mod/author-tunnusten tulee olla olemassa ennen sisältörajauksen testaamista)
**Requirements**: MOD-01, MOD-02, MOD-03, MOD-04, MOD-05, MOD-07, AUTHOR-01, AUTHOR-02, AUTHOR-04, AUTHOR-05
**Success Criteria** (what must be TRUE):

  1. Mod-käyttäjä voi luoda ja muokata hevosen (mukaan lukien kuvat), varsamerkinnän, kilpailun ja näyttelytuloksen admin-paneelissa.
  2. Mod-käyttäjä voi luoda ja muokata postauksen.
  3. Author-käyttäjä voi luoda uuden postauksen ja muokata vain omia postauksiaan — yritys muokata toisen käyttäjän postausta suoralla osoitteella ohjautuu "Ei käyttöoikeutta" -näkymään.
  4. Author-käyttäjä voi valita olemassa olevia hevosia listalta ja linkittää ne omaan postaukseensa, muttei pysty muokkaamaan itse hevostietoja.
  5. Mod- ja author-käyttäjät eivät näe eivätkä pääse käyttäjähallinta- tai teema-asetussivuille edes suoralla osoitteella.

**Plans**: TBD

### Phase 13: Poisto-hyväksyntätyönkulku

**Goal**: Modin poistopyynnöt (hevoset, varsat, kilpailut, näyttelyt, postaukset) eivät toteudu heti vaan odottavat admin-hyväksyntää yhdessä näkymässä; author saa poistaa omat postauksensa heti ilman hyväksyntää.
**Depends on**: Phase 12 (poisto-oikeus riippuu jo rajatusta sisältöoikeudesta ja tunnetusta roolista)
**Requirements**: MOD-06, AUTHOR-03, DEL-01, DEL-02, DEL-03, DEL-04, DEL-05
**Success Criteria** (what must be TRUE):

  1. Kun mod pyytää hevosen, varsan, kilpailun, näyttelytuloksen tai postauksen poistoa, sisältö ei katoa heti vaan siirtyy "odottaa hyväksyntää" -tilaan eikä näy enää julkisella sivustolla.
  2. Admin näkee yhdestä näkymästä kaikki odottavat poistopyynnöt kaikista viidestä sisältötyypistä, ja admin-etusivulla näkyy odottavien pyyntöjen määrä laskurina.
  3. Admin voi hyväksyä poistopyynnön (sisältö pysyy pehmeästi poistettuna) tai hylätä sen (sisältö palautuu normaalisti näkyväksi sekä adminissa että julkisella sivustolla).
  4. Author-käyttäjä voi poistaa oman postauksensa välittömästi ilman admin-hyväksyntää.
  5. Sama sisältö ei voi olla useamman kertaan poistojonossa samanaikaisesti — toistuva poistopyyntö samaan sisältöön ei luo uutta odottavaa riviä.

**Plans**: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|-----------------|--------|-----------|
| 1. Perusta & Tietokantarakenne | v1.0 | 3/3 | Complete | 2026-06-17 |
| 2. Julkiset sivut | v1.0 | 3/3 | Complete | 2026-06-17 |
| 3. Admin-paneeli | v1.0 | 4/4 | Complete | 2026-06-17 |
| 4. Tietoturva & Viimeistely | v1.0 | 2/2 | Complete | 2026-06-18 |
| 5. Blogi | v1.0 | 0/0 | Complete | 2026-06-18 |
| 6. Teema-infrastruktuuri | v1.1 | 2/2 | Complete | 2026-06-22 |
| 7. Oletusteman rakenne | v1.1 | 4/4 | Complete | 2026-07-03 |
| 8. Sivukontrollerien migraatio | v1.1 | 4/4 | Complete | 2026-07-04 |
| 9. Admin-teemavalinta & Altervista-verifiointi | v1.1 | 2/2 | Complete | 2026-07-05 |
| 10. Roolit ja autentikaation perusta | v1.2 | 1/3 | In Progress|  |
| 11. Käyttäjähallinta | v1.2 | 0/TBD | Not started | - |
| 12. Sisältötyyppien roolirajaus | v1.2 | 0/TBD | Not started | - |
| 13. Poisto-hyväksyntätyönkulku | v1.2 | 0/TBD | Not started | - |

---
*Roadmap created: 2026-06-17*
*Last updated: 2026-07-05 — v1.2 roadmap created (Phases 10-13, derived from research/SUMMARY.md dependency-ordered sequence)*
