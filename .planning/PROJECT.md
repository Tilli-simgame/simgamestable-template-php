# Virtuaalitalli

## What This Is

Virtuaalitalli on PHP- ja MySQL-pohjainen tallinhallintajärjestelmä, joka korvaa olemassa olevan HTML/CSS/PHP-sivuston kokonaan uudelleenrakennuksella. Sivusto esittelee hevostalli ja sen hevoset yleisölle, sekä tarjoaa admin-paneelin tallinhallintaan. Järjestelmä on isännöity Altervistassa (PHP 8.2, MySQL, PDO).

## Core Value

Hevosomistaja voi hallita koko tallinsa hevostietoja (profiilit, sukutaulut, kisahistoria, kuvat ja kasvatus) yhdestä turvallisesta admin-paneelista, ja kaikki tieto näkyy automaattisesti julkisella sivustolla.

## Current Milestone: v1.1 Teemajärjestelmä

**Goal:** Tallinpitäjä voi vaihtaa sivuston julkisen puolen ulkoasun admin-paneelista valitsemalla asennetun teeman; teemat ovat tiedostopohjaisia ja sijaitsevat `public/themes/`-kansiossa.

**Target features:**
- Teemakansiorakenne `public/themes/` (header, footer, nav, sivupohjat, CSS, blogi-sivut) — ✓ valmis (Phase 7)
- Nykyinen oletus-ilme siirretään `public/themes/default/`-rakenteeseen — ✓ valmis (Phase 7)
- Admin-paneeliin teeman valintanäkymä (listaa asennetut teemat, tallentaa valinnan)
- PHP lataa sivupohjat aktiivisesta teemasta — ✓ valmis (Phase 8): kaikki 7 julkista sivukontrolleria ovat data-only ja delegoivat renderöinnin resolveThemePath()-kutsulla; teeman vaihto DB:ssä todistettu muuttavan ulkoasua ilman kontrollerimuutoksia

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

(None yet — ship to validate)

### Active

<!-- Current scope. Building toward these. -->

- [ ] Julkinen etusivu (tallin esittely)
- [ ] Hevoslistaus-sivu (kaikki tallin hevoset)
- [ ] Hevosen profiilisivu (kattavat tiedot + sukutaulu 3 sukupolvea + kisakalenteri + kuvagalleria)
- [ ] Kasvatus-sivu (menneet ja tulevat varsomiset)
- [ ] Yhteystiedot-sivu
- [ ] Session-pohjainen admin-paneeli (yksi admin-käyttäjä)
- [ ] Hevosten CRUD admin-paneelissa
- [ ] Kuvagallerian hallinta (lataus palvelimelle, max 5 kuvaa per hevonen)
- [ ] Kisakalenterin hallinta admin-paneelissa
- [ ] OWASP Top 10 -tietoturva (PDO prepared statements, CSRF-suojaus, XSS-esto, input-validointi)

### Out of Scope

- Useampi admin-käyttäjä — yksi omistaja riittää MVP:hen
- Rekisteröityminen/kirjautuminen julkiselle sivustolle — sivusto on vain esittelysivu
- Maksujärjestelmä — ei kaupallinen toiminto
- Uutiset/blogi — toteutettu Phase 5:ssa (v1.0)
- Varausjärjestelmä — ei pyydetty

## Context

- Projekti korvaa aiemman HTML/CSS/PHP-sivuston, joka käytti PHP:tä vain sivupohjien pilkkomiseen (header/footer/nav)
- Hosting: Altervista (ilmainen), PHP 8.2.31, MySQL-tietokanta käytettävissä
- Omistajalla on teknistä taustaa PHP:stä ja HTML/CSS:stä
- Tietoturva on erityinen painopiste: OWASP Top 10 2025, SQL-injektiot, XSS, CSRF

## Constraints

- **Hosting**: Altervista — ei shell-access, FTP/cPanel-hallinta, PHP 8.2.31, MySQL
- **Tietoturva**: Kaiken input täytyy olla validoitu ja sanitoitu; PDO prepared statements pakollisia
- **Kuvat**: Max 5 kuvaa per hevonen, file upload palvelimelle
- **Admin**: Yksi admin-käyttäjä, session-pohjainen autentikaatio
- **Tech stack**: PHP (PDO), MySQL, HTML5, CSS3 — ei ulkoisia framework-riippuvuuksia

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| PHP PDO tietokantayhteydelle | Parempi tietoturva ja joustavuus kuin MySQLi; yhtenäinen API | — Pending |
| Session-pohjainen admin-auth | Yksinkertaisin turvallinen ratkaisu yhdelle omistajalle | — Pending |
| PHP includes sivupohjien pilkkomiseen | Jatkaa olemassa olevaa arkkitehtuurikuviota | — Pending |
| Kuvien tallennus palvelimelle | File upload → palvelinhakemisto; URL tallennetaan tietokantaan | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-07-04 — Phase 8 (Sivukontrollerien migraatio) complete*
