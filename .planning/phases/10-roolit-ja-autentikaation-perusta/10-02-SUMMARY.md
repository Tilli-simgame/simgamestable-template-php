---
phase: 10-roolit-ja-autentikaation-perusta
plan: 02
subsystem: auth
tags: [php, rbac, access-control]

# Dependency graph
requires:
  - phase: 10-01
    provides: "requireRole()/currentRole()/isAdmin() helpers.php, admin_role session-kirjoitus, ei-oikeutta.php"
provides:
  - "27 admin/*.php-tiedostoa suojattu requireRole(...)-kutsuilla audit-taulukon mukaisesti"
  - "4 sekatiedoston delete-haara suojattu inline requireRole('admin')-alagatella"
affects: [10-03-nav-ja-salasananvaihto, 11-kayttajahallinta, 12-sisaltotyyppien-roolirajaus]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "requireRole(...$allowedRoles) tiedoston ylimpänä requireLogin()-kutsun paikalla — koskaan ei siirretä admin_header.php:hen (Pitfall 4)"
    - "Mixed create/edit/delete -tiedostoissa (Pattern 2): tiedostotason löyhempi requireRole('admin','mod') + inline tiukempi requireRole('admin') delete-haaran ensimmäisenä lauseena"

key-files:
  created: []
  modified:
    - public/admin/index.php
    - public/admin/posts.php
    - public/admin/horses.php
    - public/admin/horse_add.php
    - public/admin/horse_edit.php
    - public/admin/horse_delete.php
    - public/admin/horse_import_vrl.php
    - public/admin/api/vrl_import_save.php
    - public/admin/contacts.php
    - public/admin/contact_add.php
    - public/admin/contact_edit.php
    - public/admin/contact_delete.php
    - public/admin/sukulaiset.php
    - public/admin/foal_add.php
    - public/admin/foal_edit.php
    - public/admin/kilpailut_all.php
    - public/admin/showrecords_all.php
    - public/admin/kuvat_all.php
    - public/admin/photos.php
    - public/admin/photo_update.php
    - public/admin/photo_delete.php
    - public/admin/post_delete.php
    - public/admin/settings.php
    - public/admin/foals.php
    - public/admin/kasvatus_all.php
    - public/admin/competitions.php
    - public/admin/showrecords.php

key-decisions:
  - "Ei poikkeamia — plan suoritettu kirjaimellisesti audit-taulukon roolilistojen mukaan, mukaan lukien 10-01:ssä jo ratkaistut [ASSUMED]-oletukset (contact_delete.php/photo_delete.php -> admin+mod)."

requirements-completed: [ROLE-02, ROLE-03]

coverage:
  - id: D1
    description: "23 yksinkertaista admin-sivua kutsuvat requireRole(...) audit-taulukon roolilistalla, samassa kutsupaikassa kuin aiempi requireLogin()"
    requirement: "ROLE-02"
    verification:
      - kind: manual_procedural
        ref: "grep -rn requireRole public/admin/ vahvistaa 23 tiedostoa oikeilla roolilistoilla; php -l ajettu Docker-kontissa kaikille 23 tiedostolle, ei syntaksivirheitä"
        status: pass
    human_judgment: false
    rationale: "Grep + php -l ovat riittäviä ja deterministisiä tälle mekaaniselle gate-swap-muutokselle; roolilistat verrattu suoraan PATTERNS.md:n audit-taulukkoon."
  - id: D2
    description: "index.php, horse_import_vrl.php, kilpailut_all.php: requireRole() säilyy ennen DB-kyselyitä ja admin_header.php-includea (Pitfall 4)"
    requirement: "ROLE-03"
    verification:
      - kind: manual_procedural
        ref: "grep -n requireRole|admin_header kullekin kolmelle tiedostolle — requireRole() rivi 3 kaikissa, admin_header-include rivi 12/18/18 — rivinumero varmasti pienempi"
        status: pass
    human_judgment: false
    rationale: "Rivinumerovertailu on deterministinen ja riittävä todiste oikeasta suoritusjärjestyksestä ilman selainajoa."
  - id: D3
    description: "4 sekatiedostoa (foals/kasvatus_all/competitions/showrecords): tiedostotason requireRole('admin','mod') + inline requireRole('admin') delete-haaran ensimmäisenä lauseena ennen DB-luku/-kirjoitusta"
    requirement: "ROLE-02"
    verification:
      - kind: manual_procedural
        ref: "grep -n requireRole|action === 'delete' kullekin neljälle tiedostolle — requireRole('admin') on delete-haaran ensimmäinen rivi ennen SELECT/DELETE-lauseita; php -l puhdas"
        status: pass
    human_judgment: false
    rationale: "Rivijärjestys ja sisältö tarkistettu grepillä suoraan lähdekoodista; ei vaadi ajonaikaista todentamista tälle rakenteelliselle muutokselle."
  - id: D4
    description: "Rooli-flip-testaus (mod/author-roolilla suoraan admin-tunnuksella, phpMyAdmin UPDATE) — todentaa ROLE-03-redirect käytännössä"
    requirement: "ROLE-03"
    verification:
      - kind: manual_procedural
        ref: "Plan-tason verification-osion Wave-0-menettely: UPDATE admin_users SET role='mod'/'author' WHERE username='admin'; testaa out-of-role-URL:t ja delete-POSTit; palauta role='admin' lopuksi"
        status: unknown
    human_judgment: true
    rationale: "Vaatii phpMyAdminin ja selaimen käytön elävällä sessiolla — ei automatisoitavissa tässä suoritusympäristössä. Migraatio (database/migrate_roles.sql) on lisäksi vielä ajamatta (käyttäjän vastuulla, ks. 10-01-SUMMARY.md User Setup Required), joten role-sarake ei ole vielä edes olemassa DB:ssä — tämä testi voidaan suorittaa vasta migraation jälkeen."

# Metrics
duration: 15min
completed: 2026-07-16
status: complete
---

# Phase 10 Plan 02: Sivukohtaiset gatet — kaikki 27 admin-sivua roolisuojattu Summary

**Kaikki ~27 suojattua admin/*.php-tiedostoa saivat lopullisen requireRole(...)-gaten audit-taulukon mukaisesti; 4 sekatiedostoon lisättiin inline requireRole('admin') delete-haaran alagatti — bare requireLogin() jäi vain logout.php:hen ja ei-oikeutta.php:hen.**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-07-16T08:16:28Z
- **Completed:** 2026-07-16T08:22:31Z
- **Tasks:** 2 completed
- **Files modified:** 27

## Accomplishments
- 23 yksinkertaista admin-sivua: `requireLogin();` -> `requireRole(...)` täsmälleen audit-taulukon roolilistalla, samassa kutsupaikassa (rivi 3, tai rivi 9 `api/vrl_import_save.php`:ssä joka sisältää docblock-kommentin ennen sitä)
- Roolijako: `index.php`/`posts.php` = admin+mod+author (D-03/D-05); `horse_delete.php`/`post_delete.php`/`settings.php` = admin only (D-02); loput 18 = admin+mod (mukaan lukien 10-01:ssä ratkaistut [ASSUMED]-kohdat `contact_delete.php`/`photo_delete.php`)
- Pitfall-4-tarkistus tehty erikseen kolmelle myöhäisen `admin_header.php`-includen tiedostolle (`index.php`, `horse_import_vrl.php`, `kilpailut_all.php`): `requireRole()` pysyy tiedoston ylimpänä rivillä 3, kaikki DB-kyselyt ja `admin_header.php`-include tulevat myöhemmin (rivit 12/18/18)
- 4 sekatiedostoa (`foals.php`, `kasvatus_all.php`, `competitions.php`, `showrecords.php`): tiedostotason gate `requireRole('admin', 'mod')` + inline `requireRole('admin')` lisätty delete-haaran (`$_POST['action'] === 'delete'`) ensimmäiseksi lauseeksi, ennen mitään DB-luku/-kirjoitusta kyseisessä haarassa
- add-/edit-haarat, CSRF-tarkistukset, GET-renderöinti ja muu sivulogiikka pysyivät koskemattomina kaikissa 27 tiedostossa — vain gate-rivit muuttuivat/lisättiin

## Task Commits

Each task was committed atomically:

1. **Task 1: Gate-swap 23 yksinkertaiselle admin-sivulle audit-taulukon mukaan (ROLE-02/03)** - `19ba0fd` (feat)
2. **Task 2: Sekatiedostojen inline delete-alagate 4 tiedostoon (D-02, Pitfall 1)** - `36b9581` (feat)

_Note: no TDD tasks in this plan — tdd_mode is false for this project._

## Files Created/Modified

**23 yksinkertaista gate-swap-tiedostoa:**
- `public/admin/index.php`, `posts.php` — admin+mod+author
- `public/admin/horses.php`, `horse_add.php`, `horse_edit.php`, `horse_import_vrl.php`, `api/vrl_import_save.php`, `contacts.php`, `contact_add.php`, `contact_edit.php`, `contact_delete.php`, `sukulaiset.php`, `foal_add.php`, `foal_edit.php`, `kilpailut_all.php`, `showrecords_all.php`, `kuvat_all.php`, `photos.php`, `photo_update.php`, `photo_delete.php` — admin+mod
- `public/admin/horse_delete.php`, `post_delete.php`, `settings.php` — admin only

**4 sekatiedostoa (tiedostotason admin+mod + inline delete-haaran admin-only):**
- `public/admin/foals.php` — delete-alagate rivillä 123 (haara alkaa rivillä 122)
- `public/admin/kasvatus_all.php` — delete-alagate rivillä 18 (haara alkaa rivillä 17)
- `public/admin/competitions.php` — delete-alagate rivillä 74 (haara alkaa rivillä 73)
- `public/admin/showrecords.php` — delete-alagate rivillä 101 (haara alkaa rivillä 100)

## Decisions Made

Ei uusia päätöksiä tässä planissa — kaikki roolilistat ja [ASSUMED]-oletukset (contact_delete.php/photo_delete.php admin+mod-tasolle) oli jo ratkaistu 10-02-PLAN.md:n `<assumptions>`-osiossa perustuen 10-RESEARCH.md:n suositukseen.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

Ei ongelmia. Docker oli käynnissä (`virtuaalitalli-web`-kontti), joten `php -l` voitiin ajaa suoraan kaikille 27 muokatulle tiedostolle — kaikki läpäisivät puhtaasti (ainoa ulostulo oli esiintynyt `mbstring.internal_encoding`-deprekaatiovaroitus, joka on ympäristön PHP 8.2 -asetusten olemassa oleva piirre, ei tämän muutoksen aiheuttama).

## Verification Performed

- **Grep-audit:** `grep -rn "requireLogin()" public/admin/` palauttaa enää vain `logout.php:3` ja `ei-oikeutta.php:3` — ei bare requireLogin() jäänyt yhdellekään suojatulle sisältösivulle.
- **php -l:** Kaikki 27 muokattua tiedostoa ajettu `MSYS_NO_PATHCONV=1 docker exec virtuaalitalli-web php -l` -komennolla — ei syntaksivirheitä yhdessäkään.
- **Pitfall 4 -rivinumerotarkistus:** `index.php` (requireRole rivi 3, admin_header rivi 12), `horse_import_vrl.php` (rivi 3 vs. 18), `kilpailut_all.php` (rivi 3 vs. 18) — kaikissa requireRole() edeltää admin_header-includea.
- **Pitfall 1 -rivinumerotarkistus (delete-alagate):** kaikissa neljässä sekatiedostossa `requireRole('admin')` on delete-haaran ensimmäinen rivi, ennen mitään SELECT/DELETE-lausetta.

## Deferred / Not Yet Runnable

- **Rooli-flip-selainverifiointi (Wave-0-menettely, plan-tason `<verification>`):** vaatii `database/migrate_roles.sql`-migraation ajon phpMyAdminissa (ei vielä ajettu, ks. 10-01-SUMMARY.md "User Setup Required") ja elävän selainsession — ei suoritettavissa tässä suoritusympäristössä. Tulee tehdä ennen Plan 03:n wave-mergeä tai viimeistään ennen Phase 10:n kokonaisverifiointia.

## Next Phase Readiness

- Kaikki 27 admin/*.php-tiedostoa (23 + 4 sekatiedostoa) ovat nyt roolisuojattuja palvelinpuolella — Plan 03 (nav-näkyvyys + salasananvaihto) voi synkronoida navigaation nämä samat roolilistat.
- Blokkeri ennen live-verifiointia: `database/migrate_roles.sql` on ajettava phpMyAdminissa ja rooli-flip-testaus (Wave 0 -menettely) suoritettava läpi ennen kuin ROLE-03-redirect voidaan todentaa selaimessa todellisella session-tilalla.

---
*Phase: 10-roolit-ja-autentikaation-perusta*
*Completed: 2026-07-16*

## Self-Check: PASSED

- FOUND: public/admin/index.php (requireRole('admin', 'mod', 'author'))
- FOUND: public/admin/foals.php (requireRole('admin', 'mod') + inline requireRole('admin'))
- FOUND: 19ba0fd (Task 1 commit)
- FOUND: 36b9581 (Task 2 commit)
- FOUND: only logout.php and ei-oikeutta.php retain bare requireLogin()
