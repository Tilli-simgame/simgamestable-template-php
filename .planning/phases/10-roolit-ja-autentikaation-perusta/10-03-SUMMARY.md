---
phase: 10-roolit-ja-autentikaation-perusta
plan: 03
subsystem: auth
tags: [php, rbac, ux, session-security]

# Dependency graph
requires:
  - phase: 10-01
    provides: "requireRole()/currentRole()/isAdmin() helpers.php, admin_role session-kirjoitus"
  - phase: 10-02
    provides: "27 admin/*.php-tiedostoa suojattu requireRole(...)-kutsuilla — nav-listojen lähde"
provides:
  - "admin_header.php: roolikohtainen nav-näkyvyys jokaiselle nav-kohdalle, synkronoitu Plan 02:n sivugatteihin"
  - "Sidebar-footerin 'Vaihda salasana' -linkki kaikille rooleille"
  - "public/admin/change_password.php — oman salasanan vaihtolomake session fixation -suojauksella"
affects: [11-kayttajahallinta, 12-sisaltotyyppien-roolirajaus]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Nav-näkyvyys pelkkänä UX-kerroksena: in_array(currentRole(), [...], true) jokaisen admin-nav-item-linkin ympärillä, EI valvontamekanismi — todellinen gate pysyy Plan 02:n sivukohtaisessa requireRole()-kutsussa"
    - "change_password.php: password_verify + password_hash(PASSWORD_BCRYPT, cost 12) + session_regenerate_id(true) VÄLITTÖMÄSTI onnistuneen UPDATE:n jälkeen (session fixation -suojaus)"

key-files:
  created:
    - public/admin/change_password.php
  modified:
    - public/admin/includes/admin_header.php

key-decisions:
  - "change_password.php sulkeutuu require admin_footer.php -kutsulla (ei PATTERNS.md:n esimerkin kovakoodattua </div></div></body></html>-lohkoa), koska tämä on kaikkien nykyisten admin-sivujen (esim. settings.php) tosiasiallinen konventio — sama päätös kuin 10-01:n ei-oikeutta.php:ssä. Ei toiminnallinen poikkeama, sama lopullinen HTML."
  - "Nav-roolilistat ristiintarkistettu suoraan committed-tiedostoista (grep requireRole kaikista 15 nav-kohteen tiedostosta), ei pelkästä PATTERNS.md-dokumentista — kaikki täsmäsivät (index/posts: admin+mod+author; settings: admin; loput: admin+mod)."

requirements-completed: [ROLE-04, AUTH-06]

coverage:
  - id: D1
    description: "Jokainen admin_header.php:n nav-kohta kääritty in_array(currentRole(), [...], true) -tarkistukseen, roolilista vastaa täsmälleen kyseisen sivun requireRole()-listaa"
    requirement: "ROLE-04"
    verification:
      - kind: manual_procedural
        ref: "grep -n requireRole kaikista 15 nav-kohteen tiedostosta (index, horses, contacts, sukulaiset, horse_import_vrl, kasvatus_all, foals, kilpailut_all, competitions, showrecords_all, showrecords, kuvat_all, photos, posts, settings) — kaikki täsmäävät nav-wrappien roolilistoihin; php -l puhdas"
        status: pass
    human_judgment: false
    rationale: "Suora grep-vertailu committed-tiedostoista on deterministinen todiste — ei vaadi selainajoa listojen synkronoinnin todentamiseen."
  - id: D2
    description: "Sidebar-footerin 'Vaihda salasana' -linkki näkyy kaikille rooleille käyttäjänimen ja logout-napin välissä (D-08)"
    requirement: "ROLE-04"
    verification:
      - kind: manual_procedural
        ref: "Koodikatselmus: linkki ei roolikääreen sisällä (kaikille rooleille), sijaitsee .admin-sidebar-footer-divin sisällä käyttäjänimi-divin ja logout-lomakkeen välissä"
        status: pass
    human_judgment: false
    rationale: "Staattinen sijaintitarkistus riittää — linkillä ei ole roolikäärettä, joten kaikki roolit näkevät sen deterministisesti."
  - id: D3
    description: "change_password.php: requireRole('admin','mod','author') gate + CSRF-validointi, 3 negatiivitapausta (väärä nykyinen, <8 merkin uusi, täsmäämätön vahvistus) torjuvat ilman UPDATE:a"
    requirement: "AUTH-06"
    verification:
      - kind: manual_procedural
        ref: "Koodikatselmus: kaikki 3 negatiivihaaraa lisäävät $errors[]-merkinnän eivätkä koskaan saavuta empty($errors)-lohkoa jossa UPDATE tapahtuu; php -l puhdas"
        status: pass
    human_judgment: false
    rationale: "Koodin kontrollivuo on staattisesti todennettavissa — kaikki virhehaarat estävät UPDATE-lauseen suorituksen ehdollisen $errors-tarkistuksen kautta."
  - id: D4
    description: "Onnistunut vaihto: bcrypt cost 12, UPDATE, session_regenerate_id(true) heti UPDATE:n jälkeen, käyttäjä pysyy kirjautuneena"
    requirement: "AUTH-06"
    verification:
      - kind: manual_procedural
        ref: "grep session_regenerate_id change_password.php vahvistaa kutsun läsnäolon heti UPDATE-lauseen jälkeen ennen $success=true-asetusta; php -l puhdas"
        status: pass
    human_judgment: false
    rationale: "Rivijärjestys tarkistettu suoraan lähdekoodista — session_regenerate_id(true) on UPDATE:n jälkeinen ensimmäinen lause, ei vaadi live-sessiota staattisen järjestyksen todentamiseen."
  - id: D5
    description: "Rooli-flip-selainverifiointi (ROLE-04 nav näkyvyys eri rooleilla, AUTH-06 salasananvaihdon täysi POST-flow selaimessa)"
    requirement: "ROLE-04, AUTH-06"
    verification:
      - kind: manual_procedural
        ref: "Plan-tason verification-osion 10 vaihetta: role-flip UPDATE admin_users SET role=..., kirjaudu uudelleen kolmella eri roolilla, testaa change_password.php:n kaikki 3 negatiivi- ja 1 positiivipolku selaimessa, palauta admin-rooli lopuksi"
        status: unknown
    human_judgment: true
    rationale: "Vaatii ajetun database/migrate_roles.sql-migraation (ei vielä ajettu, ks. 10-01-SUMMARY.md User Setup Required) ja elävän selainsession — ei automatisoitavissa tässä suoritusympäristössä."

# Metrics
duration: 12min
completed: 2026-07-16
status: complete
---

# Phase 10 Plan 03: Nav-näkyvyys ja oman salasanan vaihto Summary

**Admin_header.php:n navigaatio piilottaa nyt roolikohtaisesti sisällönhallintalinkit (author näkee vain Dashboard+Postaukset, mod ei näe Asetuksia) synkronoituna suoraan Plan 02:n requireRole()-listoihin, ja uusi change_password.php antaa kaikille rooleille turvallisen oman salasanan vaihdon session fixation -suojauksella.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-07-16
- **Completed:** 2026-07-16
- **Tasks:** 2 completed
- **Files modified:** 2 (1 modified, 1 created)

## Accomplishments

- `admin_header.php`:n jokainen nav-kohta (11 sisältölinkkiä) kääritty `in_array(currentRole(), [...], true)` -tarkistukseen: Dashboard+Postaukset = admin+mod+author; Hevoset/Osoitekirja/Sukulaiset/Tuo VRL/Kasvatus/Kilpailut/Näyttelyt/Kuvat = admin+mod; Asetukset = admin only; Julkinen sivu jätetty käärimättä (ei admin-resurssi)
- Roolilistat ristiintarkistettu suoraan committed-tiedostoista grepillä (ei pelkästä PATTERNS.md:stä) — kaikki 15 nav-kohteen tiedostoa täsmäävät
- Sidebar-footeriin lisätty "Vaihda salasana" -linkki käyttäjänimen ja "Kirjaudu ulos" -napin väliin, uudelleenkäyttäen `.sb-logout-btn`-CSS-luokkaa, näkyy kaikille rooleille (D-08)
- Uusi `public/admin/change_password.php`: `requireRole('admin','mod','author')` gate, CSRF-validointi, nykyisen salasanan `password_verify()`-tarkistus, `validate_string($new, 8, 255)` (D-10), täsmäävyystarkistus, onnistuessa `password_hash(PASSWORD_BCRYPT, cost 12)` + `UPDATE` + `session_regenerate_id(true)` (D-09, T-10-09-mitigointi) + inline success-viesti, käyttäjä pysyy kirjautuneena
- `requireRole()`-gatea ei siirretty `admin_header.php`:hen — se pysyy pelkkänä nav-renderöinnin UX-kerroksena (Pitfall 4, T-10-11-mitigointi)

## Task Commits

Each task was committed atomically:

1. **Task 1: Roolikohtainen nav-näkyvyys + salasananvaihtolinkki admin_header.php:hen (ROLE-04, D-08)** - `57851f7` (feat)
2. **Task 2: Luo change_password.php salasananvaihtolomake (AUTH-06, D-08/09/10)** - `54b5e6d` (feat)

_Note: no TDD tasks in this plan — tdd_mode is false for this project._

## Files Created/Modified

- `public/admin/includes/admin_header.php` — 11 nav-kohtaa käärittiin rooliehtoihin; sidebar-footeriin lisättiin salasananvaihtolinkki
- `public/admin/change_password.php` (uusi) — 3-kenttäinen salasananvaihtolomake, uudelleenkäyttäen olemassa olevia CSRF-/validate_string-/CSS-mekanismeja

## Decisions Made

- `change_password.php` sulkeutuu `require __DIR__ . '/includes/admin_footer.php';` -kutsulla PATTERNS.md:n esimerkkilohkon kovakoodatun `</div></div></body></html>`-markkauksen sijaan — tämä on kaikkien nykyisten admin-sivujen (verifioitu `settings.php`:stä) tosiasiallinen konventio. Sama tarkennuspäätös kuin 10-01-SUMMARY.md:ssä `ei-oikeutta.php`:lle. Ei toiminnallinen poikkeama — lopullinen renderöity HTML on identtinen.
- Nav-roolilistat vahvistettiin suoraan committed-lähdekoodista (`grep -n requireRole` kaikkiin 15 nav-kohteen tiedostoon) käyttäjän ohjeen mukaisesti, ei pelkästä PATTERNS.md-dokumentista — kaikki täsmäsivät ilman poikkeamia.

## Deviations from Plan

None — plan suoritettu kirjaimellisesti, pois lukien yllä dokumentoitu ei-toiminnallinen `admin_footer.php`-konventiotarkennus (sama luokka poikkeamaa kuin 10-01:ssä, ei vaadi Rule-numerointia koska kyseessä on pelkkä konventiovalinta joka tuottaa identtisen lopputuloksen).

## Issues Encountered

Ei ongelmia. Docker oli käynnissä (`virtuaalitalli-web`-kontti) — `php -l` ajettiin molemmille muokatuille/luoduille tiedostoille onnistuneesti (ainoa ulostulo oli ympäristön olemassa oleva `mbstring.internal_encoding`-deprekaatiovaroitus, ei tämän muutoksen aiheuttama).

## Verification Performed

- **php -l:** `public/admin/includes/admin_header.php` ja `public/admin/change_password.php` — molemmat puhtaita, ei syntaksivirheitä.
- **Nav-lista vs. sivugate-ristiintarkistus:** `grep -n requireRole` kaikista 15 nav-kohteen tiedostosta (index, horses, contacts, sukulaiset, horse_import_vrl, kasvatus_all, foals, kilpailut_all, competitions, showrecords_all, showrecords, kuvat_all, photos, posts, settings) — kaikki täsmäävät täsmälleen nav-wrappien roolilistoihin.
- **session_regenerate_id-läsnäolo:** grep vahvisti kutsun `change_password.php`:ssä heti `UPDATE`-lauseen jälkeen, ennen `$success = true` -asetusta.
- **Koodikatselmus:** kaikki 3 negatiivihaaraa (väärä nykyinen salasana, <8 merkin uusi, täsmäämätön vahvistus) lisäävät `$errors[]`-merkinnän ja estävät siten `empty($errors)`-ehdon kautta `UPDATE`-lauseen suorituksen.

## Deferred / Not Yet Runnable

- **Rooli-flip-selainverifiointi (Wave-0-menettely, plan-tason `<verification>` vaiheet 1-10):** vaatii `database/migrate_roles.sql`-migraation ajon phpMyAdminissa (ei vielä ajettu, ks. 10-01-SUMMARY.md "User Setup Required") ja elävän selainsession — ei suoritettavissa tässä suoritusympäristössä. Sisältää: (1) rooli-flipin admin/mod/author-tunnuksella nav-näkyvyyden todentamiseksi, (2) change_password.php:n täyden POST-flow'n testauksen kaikilla 4 skenaariolla (väärä nykyinen, liian lyhyt uusi, täsmäämätön vahvistus, onnistunut vaihto), (3) kirjautumisen uudella salasanalla.

## Next Phase Readiness

- Phase 10 (roolit ja autentikaation perusta) on nyt koodillisesti valmis kaikilta kolmelta plan-tasolta: infrastruktuuri (10-01), sivukohtaiset gatet (10-02), nav-näkyvyys + salasananvaihto (10-03).
- Blokkeri ennen live-verifiointia ja Phase 10:n kokonaissulkemista: käyttäjän tulee ajaa `database/migrate_roles.sql` phpMyAdminissa (localhost:8080) ja suorittaa Wave 0 -rooli-flip-menettely (ks. Deferred-osio yllä) todentaakseen ROLE-03/ROLE-04/AUTH-06 toimivan elävällä session-tilalla.
- Phase 11 (käyttäjähallinta) voi rakentaa tämän roolirakenteen päälle; Phase 12 (sisältötyyppien roolirajaus) voi käyttää samaa nav-synkronointimallia uusille sivuille.

---
*Phase: 10-roolit-ja-autentikaation-perusta*
*Completed: 2026-07-16*

## Self-Check: PASSED

- FOUND: public/admin/change_password.php
- FOUND: public/admin/includes/admin_header.php
- FOUND: 57851f7 (Task 1 commit)
- FOUND: 54b5e6d (Task 2 commit)
