---
phase: 10-roolit-ja-autentikaation-perusta
plan: 01
subsystem: auth
tags: [php, pdo, session-auth, rbac]

# Dependency graph
requires:
  - phase: 03-admin-paneeli
    provides: session-pohjainen admin-autentikaatio (login.php, requireLogin(), isLoggedIn())
provides:
  - admin_users.role ENUM('admin','mod','author') ja admin_users.is_active TINYINT(1) -sarakkeet (migraatio + schema.sql)
  - helpers.php: currentRole(), isAdmin(), requireRole(...$allowedRoles) — keskitetty rooligate
  - login.php kirjoittaa $_SESSION['admin_role'] ja estää is_active=0-kirjautumisen
  - public/admin/ei-oikeutta.php — ROLE-03 redirect-kohdesivu
affects: [10-02-sivugatet, 10-03-nav-ja-salasananvaihto, 11-kayttajahallinta]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "requireRole(string ...$allowedRoles) deny-by-default allow-list gate, mirrors requireLogin()/isLoggedIn() style"
    - "currentRole() on koodikannan ainoa $_SESSION['admin_role']-lukupaikka (Phase 11 vaihtaa vain tämän funktion rungon)"

key-files:
  created:
    - database/migrate_roles.sql
    - public/admin/ei-oikeutta.php
  modified:
    - database/schema.sql
    - public/src/includes/helpers.php
    - public/admin/login.php

key-decisions:
  - "ei-oikeutta.php sulkeutuu require admin_footer.php -kutsulla (ei kovakoodattua </div></div></body></html>-lohkoa), koska se on koodikannan tosiasiallinen konventio kaikissa 21 nykyisessä admin-sivussa (PATTERNS.md:n esimerkkilohko oli tältä osin vanhentunut)."
  - "role-sarakkeen DEFAULT on 'author' (turvallisin fallback per D-06) sekä migraatiossa että schema.sql:ssä — olemassa oleva admin-tunnus nostetaan eksplisiittisellä UPDATE-lauseella role='admin'-arvoon."

requirements-completed: [ROLE-01, ROLE-02, ROLE-03]

coverage:
  - id: D1
    description: "admin_users-taulussa role ENUM + is_active-sarakkeet migraation ja schema.sql-päivityksen kautta"
    requirement: "ROLE-01"
    verification:
      - kind: manual_procedural
        ref: "Aja database/migrate_roles.sql phpMyAdminissa; DESCRIBE admin_users; SELECT role,is_active FROM admin_users WHERE username='admin'"
        status: unknown
    human_judgment: true
    rationale: "Ei DB-assertointityökaluja tässä koodikannassa (VALIDATION.md) — migraatio vaatii käsin ajon phpMyAdminissa ennen kuin sarakkeet ovat todennettavissa."
  - id: D2
    description: "helpers.php: currentRole()/isAdmin()/requireRole() lisätty, requireLogin()/isLoggedIn() ennallaan"
    requirement: "ROLE-02"
    verification:
      - kind: manual_procedural
        ref: "php -l public/src/includes/helpers.php (ei ajettu — php-binääriä tai Docker-konttia ei ollut käytettävissä suoritusympäristössä)"
        status: unknown
    human_judgment: true
    rationale: "Koodi tarkistettu visuaalisesti syntaksin osalta, mutta php -l -ajoa ei voitu suorittaa suoritusympäristössä (ei PHP:tä, Docker ei käynnissä) — vaatii manuaalisen tarkistuksen ennen Plan 02:n gate-swappeja."
  - id: D3
    description: "login.php kirjoittaa $_SESSION['admin_role'] onnistuneen, is_active=1-tarkistetun kirjautumisen jälkeen; sama geneerinen virhe molemmille epäonnistumistavoille"
    requirement: "ROLE-02"
    verification:
      - kind: manual_procedural
        ref: "Kirjaudu admin-tunnuksella migraation ajon jälkeen; testaa is_active=0-tila väliaikaisella UPDATE-lauseella"
        status: unknown
    human_judgment: true
    rationale: "Vaatii ajetun migraation ja live-kirjautumisen selaimessa — ei automatisoitavissa tässä suoritusympäristössä."
  - id: D4
    description: "ei-oikeutta.php renderöityy admin_header.php-layoutilla, kiinteä D-04-viesti, Takaisin-linkki admin/index.php:hen"
    requirement: "ROLE-03"
    verification:
      - kind: manual_procedural
        ref: "Avaa /admin/ei-oikeutta.php kirjautuneena selaimessa"
        status: unknown
    human_judgment: true
    rationale: "Visuaalinen renderöintitarkistus vaatii selainkäytön — ei automatisoitavissa tässä ympäristössä."

# Metrics
duration: 5min
completed: 2026-07-16
status: complete
---

# Phase 10 Plan 01: Roolit ja autentikaation perusta — infrastruktuuri Summary

**Rooli-infrastruktuurin perusta: admin_users.role/is_active-sarakkeet, keskitetyt requireRole()/currentRole()/isAdmin()-funktiot, roolinkirjoitus loginissa ja "Ei käyttöoikeutta" -redirect-kohde.**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-07-16T08:09:56Z
- **Completed:** 2026-07-16T08:13:53Z
- **Tasks:** 3 completed
- **Files modified:** 5 (2 created, 3 modified)

## Accomplishments
- `database/migrate_roles.sql` luotu ja `database/schema.sql`:n `admin_users`-lohko päivitetty samoilla `role`/`is_active`-sarakkeilla, jotta tuore asennus vastaa päivitettyä asennusta
- `helpers.php`:hen lisätty `currentRole()`, `isAdmin()`, `requireRole(string ...$allowedRoles)` — olemassa olevaa `isLoggedIn()`/`requireLogin()`-tyyliä mirroroiden, deny-by-default allow-list-logiikalla
- `login.php` laajennettu hakemaan `role`+`is_active`, vaatimaan `is_active=1` onnistumisehdossa, ja kirjoittamaan `$_SESSION['admin_role']` — virheviesti pysyy geneerisenä molemmille epäonnistumistavoille (information-disclosure-esto)
- Uusi `public/admin/ei-oikeutta.php` -sivu ROLE-03-redirect-kohteeksi, käyttää olemassa olevaa `admin_header.php`/`admin_footer.php`-shellia ja `.admin-card`/`.flash-err`/`.btn`-CSS-luokkia

## Task Commits

Each task was committed atomically:

1. **Task 1: Luo migrate_roles.sql ja päivitä schema.sql (ROLE-01)** - `a33fb25` (feat)
2. **Task 2: Lisää requireRole()/currentRole()/isAdmin() helpers.php:hen (ROLE-02/03)** - `de1e83f` (feat)
3. **Task 3: Laajenna login.php ja luo ei-oikeutta.php (ROLE-02/03)** - `4cd941d` (feat)

_Note: no TDD tasks in this plan — tdd_mode is false for this project._

## Files Created/Modified
- `database/migrate_roles.sql` - Uusi migraatio: ALTER TABLE admin_users ADD role/is_active + eksplisiittinen admin-backfill
- `database/schema.sql` - `admin_users` CREATE TABLE -lohko päivitetty samoilla sarakkeilla (fresh-install parity)
- `public/src/includes/helpers.php` - Lisätty `currentRole()`, `isAdmin()`, `requireRole()` heti `requireLogin()`-funktion jälkeen
- `public/admin/login.php` - SELECT laajennettu role+is_active-kentillä, onnistumisehto vaatii is_active=1, kirjoittaa `$_SESSION['admin_role']`
- `public/admin/ei-oikeutta.php` - Uusi ROLE-03-redirect-kohdesivu

## Decisions Made
- `ei-oikeutta.php` sulkeutuu `require admin_footer.php` -kutsulla PATTERNS.md:n ehdottaman kovakoodatun `</div></div></body></html>`-lohkon sijaan, koska tämä on kaikkien 21 nykyisen admin-sivun tosiasiallinen konventio (verifioitu grepillä — PATTERNS.md:n esimerkki oli tältä osin vanhentunut). Ei vaikuta lopputulokseen, sama HTML-rakenne syntyy.
- `role`-sarakkeen `DEFAULT 'author'` sekä migraatiossa että schema.sql:ssä per D-06 (turvallisin fallback-oletus) — olemassa oleva admin-tunnus nostetaan eksplisiittisellä `UPDATE`-lauseella, ei luoteta pelkkään DEFAULT-arvoon.

## Deviations from Plan

None - plan executed exactly as written, pois lukien yksi pieni, ei-toiminnallinen konventiokorjaus (ks. Decisions Made -kohta `ei-oikeutta.php`:n sulkumarkkauksesta, joka ei ole toiminnallinen poikkeama vaan tarkennus koodikannan tosiasiallisesta konventiosta).

## Issues Encountered
- `php -l`-syntaksitarkistusta ei voitu ajaa suoritusympäristössä: paikallista PHP-binääriä ei ollut asennettuna eikä Docker-daemon ollut käynnissä (`docker ps` epäonnistui "failed to connect to the docker API" -virheeseen). Koodi tarkistettu visuaalisesti muutosten suppean koon vuoksi (yksinkertaiset, olemassa olevaa kuviota mirroroivat lisäykset). **Tämä on merkitty ihmisvahvistusta vaativaksi kohdaksi (D2 coverage-lohkossa) — suositellaan ajamaan `php -l public/src/includes/helpers.php public/admin/login.php public/admin/ei-oikeutta.php` Docker-kontissa ennen Plan 02:n aloitusta.**
- Kaikki muu manuaalinen DB/selainverifiointi (migraation ajo, kirjautumistesti, is_active=0-testi, ei-oikeutta.php:n renderöinti) vaatii phpMyAdminin ja selaimen käyttöä — merkitty `human_judgment: true` coverage-lohkoon, ei automatisoitavissa tässä suoritusympäristössä.

## User Setup Required

**Ulkoinen palvelu vaatii manuaalisen konfiguroinnin.** Per plan-frontmatterin `user_setup`:
1. Aja `database/migrate_roles.sql` phpMyAdminissa (Import → valitse tiedosto → Suorita) osoitteessa localhost:8080 (Docker-kehitysympäristö) → tietokanta → Import.
2. Tämä lisää `role`- ja `is_active`-sarakkeet `admin_users`-tauluun ja nostaa olemassa olevan admin-tunnuksen `role='admin'`-arvoon.
3. Verifioi: `DESCRIBE admin_users;` näyttää `role` ENUM + `is_active`; `SELECT role, is_active FROM admin_users WHERE username='admin';` palauttaa `'admin'` / `1`.

Kirjautumis- ja gate-toiminnallisuus (Task 3:n verifiointi, Plan 02:n sivugatet) vaativat tämän migraation ajon ensin.

## Next Phase Readiness
- Plan 02 (sivukohtaiset gatet) ja Plan 03 (nav + salasananvaihto) voivat nyt käyttää `requireRole()`/`currentRole()`/`isAdmin()`-funktioita — perusinfrastruktuuri on paikallaan.
- Blokkeri Plan 02/03:lle: migraatio (`database/migrate_roles.sql`) täytyy ajaa phpMyAdminissa ja `php -l` täytyy vahvistaa manuaalisesti ennen kuin gate-swappien toiminnallisuus voidaan todentaa livenä.

---
*Phase: 10-roolit-ja-autentikaation-perusta*
*Completed: 2026-07-16*

## Self-Check: PASSED

- FOUND: database/migrate_roles.sql
- FOUND: public/admin/ei-oikeutta.php
- FOUND: a33fb25 (Task 1 commit)
- FOUND: de1e83f (Task 2 commit)
- FOUND: 4cd941d (Task 3 commit)
