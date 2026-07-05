# Virtuaalitalli

## What This Is

Virtuaalitalli on PHP- ja MySQL-pohjainen tallinhallintajärjestelmä, joka korvaa olemassa olevan HTML/CSS/PHP-sivuston kokonaan uudelleenrakennuksella. Sivusto esittelee hevostalli ja sen hevoset yleisölle, sekä tarjoaa admin-paneelin tallinhallintaan. Järjestelmä on isännöity Altervistassa (PHP 8.2, MySQL, PDO).

## Core Value

Hevosomistaja voi hallita koko tallinsa hevostietoja (profiilit, sukutaulut, kisahistoria, kuvat ja kasvatus) yhdestä turvallisesta admin-paneelista, ja kaikki tieto näkyy automaattisesti julkisella sivustolla.

## Current State

**Shipped:** v1.1 Teemajärjestelmä (2026-07-05)

Sivusto on täysin toimiva PHP/MySQL-pohjainen virtuaalitalli Altervista-tuotannossa (`/demotalli-02/`). Julkinen puoli (etusivu, hevoslistaus, hevosprofiili, kasvatus, yhteystiedot, blogi) ja admin-paneeli (autentikaatio, hevosten/kuvien/kilpailujen/kasvatuksen CRUD, tietoturva) ovat kaikki tuotannossa. v1.1 lisäsi tiedostopohjaisen teemajärjestelmän: julkiset sivukontrollerit ovat data-only ja lataavat HTML:n aktiivisesta teemasta `resolveThemePath()`:n kautta; admin voi vaihtaa teeman `settings.php`:sta yhdellä klikkauksella; teemanvaihto on todistettu toimivaksi tuotannossa ilman kontrollerimuutoksia.

**Tunnettu, tarkoituksellisesti rajattu puute:** `oma-talli`-teemalla ei ole vielä `pages/.htaccess`-suojausta kuten `default`-teemalla (D-06, Phase 9) — teema on keskeneräinen eikä kuulunut v1.1-milestonen verifiointiin.

<details>
<summary>Archived: v1.1 Teemajärjestelmä goal (Phases 6-9)</summary>

**Goal:** Tallinpitäjä voi vaihtaa sivuston julkisen puolen ulkoasun admin-paneelista valitsemalla asennetun teeman; teemat ovat tiedostopohjaisia ja sijaitsevat `public/themes/`-kansiossa.

**Target features (all shipped):**
- Teemakansiorakenne `public/themes/` (header, footer, nav, sivupohjat, CSS, blogi-sivut) — Phase 7
- Nykyinen oletus-ilme siirretty `public/themes/default/`-rakenteeseen — Phase 7
- Admin-paneelin teeman valintanäkymä (listaa asennetut teemat, tallentaa valinnan) — Phase 9
- PHP lataa sivupohjat aktiivisesta teemasta — Phase 8
- Teeman page-templatejen suora HTTP-pääsy estetty + koko teemajärjestelmä varmistettu Altervista-tuotannossa — Phase 9

</details>

## Current Milestone: v1.2 Käyttäjäroolit

**Goal:** Adminin lisäksi tallilla voi olla mod- ja author-käyttäjiä rajatuin oikeuksin; kaikki käyttäjät voivat vaihtaa oman salasanansa; vain admin voi luoda uusia tunnuksia.

**Target features:**
- `admin_users.role`-sarake (admin / mod / author) ja rooliperusteinen pääsynhallinta admin-paneelissa
- **admin**: kaikki oikeudet — hevoset/varsat/kisat/näyttelyt/postaukset (luo/muokkaa/poista suoraan), täysi käyttäjähallinta (luo/muokkaa roolia+nimeä/poista/nollaa salasana), teemavalinta/asetukset
- **mod**: voi luoda/muokata hevosia (+kuvat), varsoja, kisoja, näyttelyitä (showrecords), postauksia. Poisto vaatii admin-hyväksynnän (pending-deletion-tila → admin hyväksyy/hylkää). Ei pääsyä käyttäjähallintaan eikä teema-asetuksiin.
- **author**: voi luoda/muokata/poistaa VAIN omia postauksiaan (`posts.author_id`, välitön poisto). Voi linkittää olemassa olevia hevosia postaukseen (read-only valinta postausten post_horses-linkitykseen). Ei pääsyä muualle.
- Kaikki roolit voivat vaihtaa oman salasanansa admin-paneelista
- Pending-deletion-mekanismi hevosille, varsoille, kisoille, näyttelyille ja postauksille (uusi status/taulu, admin-hyväksyntänäkymä)

## Next Milestone Goals

Ei vielä määritelty seuraavaksi milestoneksi v1.2:n jälkeen.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

- ✓ Julkinen etusivu, hevoslistaus, hevosprofiili (sukutaulu 3 sukupolvea + kisakalenteri + kuvagalleria), kasvatus-sivu, yhteystiedot-sivu — v1.0
- ✓ Session-pohjainen admin-paneeli (kirjautuminen, hevosten CRUD, kuvagallerian hallinta, kisakalenterin hallinta, kasvatustietojen hallinta) — v1.0
- ✓ OWASP Top 10 -tietoturva (PDO prepared statements, CSRF-suojaus, XSS-esto, input-validointi, turvalliset session-asetukset) — v1.0
- ✓ Blogi (postausten hallinta adminissa, julkinen postauslista + yksittäinen postaussivu arkistosidebarilla) — v1.0 (Phase 5)
- ✓ Tiedostopohjainen teemajärjestelmä: `resolveThemePath()` path-traversal-suojauksella, `public/themes/default/`-rakenne, data-only-sivukontrollerit, admin-teemavalinta, Altervista-tuotantoverifiointi — v1.1

### Active

<!-- Current scope. Building toward these. -->

- [ ] Rooliperusteinen pääsynhallinta (admin/mod/author) admin-paneelissa
- [ ] Käyttäjähallinta: admin voi luoda/muokata/poistaa käyttäjiä ja nollata salasanoja
- [ ] Kaikki käyttäjät voivat vaihtaa oman salasanansa
- [ ] Mod-roolin rajattu CRUD hevosille/varsoille/kisoille/näyttelyille/postauksille poisto-hyväksyntäkiertoineen
- [ ] Author-roolin rajattu CRUD vain omiin postauksiin + hevoslinkitys (read-only valinta)

### Out of Scope

- Rekisteröityminen/kirjautuminen julkiselle sivustolle — sivusto on vain esittelysivu
- Maksujärjestelmä — ei kaupallinen toiminto
- Varausjärjestelmä — ei pyydetty
- Teeman esikatselukuva ja toinen valmis teema — siirretty v2-laajennuksiin (V2-05, V2-06)

## Context

- Projekti korvaa aiemman HTML/CSS/PHP-sivuston, joka käytti PHP:tä vain sivupohjien pilkkomiseen (header/footer/nav)
- Hosting: Altervista (ilmainen), PHP 8.2.31, MySQL-tietokanta käytettävissä
- Omistajalla on teknistä taustaa PHP:stä ja HTML/CSS:stä
- Tietoturva on erityinen painopiste: OWASP Top 10 2025, SQL-injektiot, XSS, CSRF
- v1.1 jälkeen: julkinen puoli on täysin teema-ajettu (data-only-kontrollerit + `resolveThemePath()`); admin-puoli pysyy tarkoituksella teemajärjestelmän ulkopuolella (ei koskaan lataa `theme.php`-shimmiä)
- Toinen teema (`oma-talli`) on olemassa hakemistorakenteessa mutta on keskeneräinen (ei `pages/.htaccess`-suojausta)

## Constraints

- **Hosting**: Altervista — ei shell-access, FTP/cPanel-hallinta, PHP 8.2.31, MySQL
- **Tietoturva**: Kaiken input täytyy olla validoitu ja sanitoitu; PDO prepared statements pakollisia
- **Kuvat**: Max 5 kuvaa per hevonen, file upload palvelimelle
- **Admin**: Session-pohjainen autentikaatio; useampi käyttäjä rooleilla (admin/mod/author) v1.2:sta alkaen — vain admin luo tunnuksia
- **Tech stack**: PHP (PDO), MySQL, HTML5, CSS3 — ei ulkoisia framework-riippuvuuksia
- **Teemat**: Tiedostopohjaisia (`public/themes/{teema}/`), ei tietokantapohjaista teemaeditoria

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| PHP PDO tietokantayhteydelle | Parempi tietoturva ja joustavuus kuin MySQLi; yhtenäinen API | ✓ Good |
| Session-pohjainen admin-auth | Yksinkertaisin turvallinen ratkaisu yhdelle omistajalle | ✓ Good |
| PHP includes sivupohjien pilkkomiseen | Jatkaa olemassa olevaa arkkitehtuurikuviota | ✓ Good |
| Kuvien tallennus palvelimelle | File upload → palvelinhakemisto; URL tallennetaan tietokantaan | ✓ Good |
| resolveThemePath(): preg_match + realpath + prefix-check | Path-traversal-suojaus teemapoluille | ✓ Good |
| Admin-paneeli ei koskaan lataa theme.php-shimmiä | Selkeä eristys julkisen teemajärjestelmän ja adminin välillä | ✓ Good |
| public/assets/css/style.css pysyy muuttumattomana | admin_header.php riippuu siitä; teemat eivät koske admin-CSS:ää | ✓ Good |
| INSERT IGNORE (ei ON DUPLICATE KEY UPDATE) migraatioissa | Yhdenmukaisuus muiden migrate_*.sql-tiedostojen kanssa | ✓ Good |
| theme.json vain name+version | description/author/preview siirretty V2-05-laajennukseen | ✓ Good |
| Sivukontrollerit data-only + resolveThemePath()-delegointi | Teeman vaihto ei vaadi kontrollerimuutoksia (todistettu Phase 08-04) | ✓ Good |
| Deny from all themes/{teema}/pages/*.php:lle | Root .htaccess ei koskaan routita polkuun; require_once ohittaa Apache-eston joka tapauksessa turvallisesti | ✓ Good |
| oma-talli-teeman .htaccess-suojaus jätetty ulkopuolelle | Teema keskeneräinen, ei kuulunut v1.1-scopeen | — Pending (tunnettu puute) |

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
*Last updated: 2026-07-05 — v1.2 Käyttäjäroolit milestone started*
