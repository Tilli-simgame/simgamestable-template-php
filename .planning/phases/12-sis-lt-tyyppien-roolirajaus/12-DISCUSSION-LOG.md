# Phase 12: Sisältötyyppien roolirajaus - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-16
**Phase:** 12-Sisältötyyppien roolirajaus
**Areas discussed:** Vanhojen postausten author_id, Uusien postausten author_id-käytäntö, Authorin postauslistan laajuus, Mod/author-rajauksen kattavuus

---

## Vanhojen postausten author_id

| Option | Description | Selected |
|--------|-------------|----------|
| Backfill alkuperäiselle admin-tunnukselle | UPDATE posts SET author_id = (admin_users.id WHERE username='admin'). Kaikilla vanhoilla postauksilla aina jokin tekijä, ei NULL-erikoiskäsittelyä missään kyselyssä. | ✓ |
| Jätä NULL | Vanhat postaukset merkitään 'ei author-omistuksessa'; jokainen ownership-tarkistus tarvitsee erillisen NULL-käsittelyn. | |

**User's choice:** Backfill alkuperäiselle admin-tunnukselle
**Notes:** —

---

## Uusien postausten author_id-käytäntö

| Option | Description | Selected |
|--------|-------------|----------|
| Kaikki roolit — author_id = luoja aina | Yksi INSERT-lause asettaa author_id:n = $_SESSION['admin_id'] riippumatta roolista. | ✓ |
| Vain author-roolin postauksille | author_id asetetaan vain author-luonnissa; admin/mod:n postaukset jäävät NULL:ksi. Vaatii roolikohtaisen haaran. | |

**User's choice:** Kaikki roolit — author_id = luoja aina
**Notes:** —

---

## Authorin postauslistan laajuus

| Option | Description | Selected |
|--------|-------------|----------|
| Vain omat postaukset listassa | WHERE author_id = :current_user_id kun rooli on author. Ei tarvita rivikohtaista "Muokkaa"-napin esto-logiikkaa. | ✓ |
| Kaikki postaukset näkyvissä, muokkaus estetty | Author näkee koko listan; muokkaus muihin estetään joko UI:ssa tai backend-uudelleenohjauksella. | |

**User's choice:** Vain omat postaukset listassa
**Notes:** Seuraus: "Ei käyttöoikeutta" -esto tarvitaan vain suoralle action=edit&id=X-URL-yritykselle, ei listan renderöinnille.

---

## Mod/author-rajauksen kattavuus

| Option | Description | Selected |
|--------|-------------|----------|
| Ei — Phase 10:n rajaus riittää sellaisenaan | Ei uutta roolikoodia hevosille/varsoille/kilpailuille/näyttelytuloksille/kuville; vain postausten omistajuustyö + jälkikäteisverifiointi. | ✓ |
| Kyllä — haluan tarkentaa erityistapauksen | Esim. VRL-tuonti, kuvien poisto tms. | |

**User's choice:** Ei — Phase 10:n rajaus riittää sellaisenaan
**Notes:** —

---

## Claude's Discretion

- `requireOwnResourceOrAdmin()`-tyylisen apufunktion tarkka signatuuri (vai inline-tarkistus).
- POST-käsittelijän puolen omistajuustarkistus (defense-in-depth, ei vain GET-suoran URL:n esto).
- Migraatiotiedoston tarkka nimi/rakenne (`migrate_roles.sql`-konvention mukaisesti).
- "Ei käyttöoikeutta" -uudelleenohjauksen virheviestin sanamuoto (sama kuin Phase 10:ssä).

## Deferred Ideas

Ei uusia scope-ehdotuksia noussut. Poisto-hyväksyntätyönkulku (MOD-06, AUTHOR-03, DEL-01..05) pysyy Phase 13:ssa kuten roadmapissa jo määritelty.
