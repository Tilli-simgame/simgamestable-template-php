# Phase 10: Roolit ja autentikaation perusta - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-05
**Phase:** 10-Roolit ja autentikaation perusta
**Areas discussed:** Rooli-sivukartoituksen laajuus, "Ei käyttöoikeutta" -näkymä, Admin-käyttäjän rooli-backfill, Salasananvaihto-sivun UX

---

## Rooli-sivukartoituksen laajuus

| Option | Description | Selected |
|--------|-------------|----------|
| Täysi kartoitus nyt | Kaikki ~30 admin-sivua saavat lopullisen roolivaatimuksen jo Phase 10:ssä | ✓ |
| Vain admin-only-erottelu nyt | Sisältösivujen roolijako jätetään kokonaan Phase 12:lle | |

**User's choice:** Täysi kartoitus nyt.

| Option | Description | Selected |
|--------|-------------|----------|
| Poisto pysyy admin-only Phase 13:aan asti | Mod saa create/edit heti, poisto-endpointit pysyvät admin-only'na | ✓ |
| Mod saa poisto-oikeuden jo nyt | Mod pääsee kaikkiin CRUD-toimintoihin heti, poisto suora/pysyvä väliaikaisesti | |

**User's choice:** Poisto pysyy admin-only Phase 13:aan asti.

| Option | Description | Selected |
|--------|-------------|----------|
| Kyllä, tällä jaolla | Phase 10 = pääsyoikeus+nav; Phase 12 = sisäinen roolikohtainen logiikka | ✓ |
| Ei — rajaa Phase 10 vain nav+admin-only-sivuihin | Palataan minimaalisempaan scopeen | |

**User's choice:** Kyllä, tällä jaolla — hyväksyttävä väliaikaistila koska mod/author-tunnuksia ei vielä ole olemassa.

---

## "Ei käyttöoikeutta" -näkymä

| Option | Description | Selected |
|--------|-------------|----------|
| Oma dedikoitu sivu | admin/ei-oikeutta.php, admin_header.php-layout, viesti+linkki | ✓ |
| Uudelleenohjaus + flash-viesti | Redirect omalle dashboardille flash-mekanismilla | |

**User's choice:** Oma dedikoitu sivu.

| Option | Description | Selected |
|--------|-------------|----------|
| Aina admin/index.php | Sama dashboard kaikille rooleille | ✓ |
| HTTP_REFERER | Palauttaa edelliselle sivulle | |

**User's choice:** Aina admin/index.php.

---

## Admin-käyttäjän rooli-backfill

| Option | Description | Selected |
|--------|-------------|----------|
| ALTER + eksplisiittinen UPDATE | DEFAULT 'author' + eksplisiittinen UPDATE role='admin' olemassa olevalle tilille | ✓ |
| DEFAULT 'admin' ilman erillistä UPDATEa | Pelkkä DEFAULT riittää koska ainoa tili on admin | |

**User's choice:** ALTER + eksplisiittinen UPDATE.

| Option | Description | Selected |
|--------|-------------|----------|
| Lisää is_active nyt, UI myöhemmin | Sarake mukaan Phase 10:n migraatioon, hallinta-UI Phase 11:ssä | ✓ |
| Jätä kokonaan Phase 11:lle | Tiukempi scope-rajaus | |

**User's choice:** Lisää is_active nyt, UI myöhemmin.

---

## Salasananvaihto-sivun UX

| Option | Description | Selected |
|--------|-------------|----------|
| Sivupalkin footerissa käyttäjänimen vieressä | Näkyy kaikille rooleille footer-osiossa | ✓ |
| Oma rivi päänavigaatiossa | Erillinen linkki settings-linkin lähellä | |

**User's choice:** Sivupalkin footerissa käyttäjänimen vieressä.

| Option | Description | Selected |
|--------|-------------|----------|
| Pysyy kirjautuneena + flash-viesti | session_regenerate_id(true), inline-viesti | ✓ |
| Pakotettu uloskirjautuminen | Kirjaa ulos, ohjaa login-sivulle | |

**User's choice:** Pysyy kirjautuneena; viesti näytetään inline (ei uutta flash-sessiomekanismia).

| Option | Description | Selected |
|--------|-------------|----------|
| Vähintään 8 merkkiä | validate_string()-tyylinen minimipituusvalidointi | ✓ |
| Ei minimivaatimusta | Vain täsmäävyys- ja tyhjyystarkistus | |

**User's choice:** Vähintään 8 merkkiä.

---

## Claude's Discretion

- `requireRole()`/`currentRole()`/`isAdmin()`-funktioiden tarkka signatuuri ja koodityyli.
- Nav-kohtien tarkka rooli-array-toteutus `admin_header.php`:ssä.
- Virheviestien tarkka sanamuoto (paitsi "Ei käyttöoikeutta" -sivun ydinviesti).

## Deferred Ideas

Ei uusia scope-ehdotuksia — kaikki neljä aihetta pysyivät Phase 10:n rajojen sisällä. Jo roadmapissa oleva myöhempien vaiheiden sisältö (käyttäjähallinta, sisältösivujen roolisuodatus, poisto-hyväksyntä) ei kuulu tähän lokiin, ks. ROADMAP.md Phase 11-13.
