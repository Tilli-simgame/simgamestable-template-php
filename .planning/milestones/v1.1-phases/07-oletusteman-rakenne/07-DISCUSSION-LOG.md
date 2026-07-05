# Phase 7: Oletusteman rakenne - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-02
**Phase:** 7-Oletusteman-rakenne
**Areas discussed:** Template-jaon syvyys, Siirto vs. kopiointi, CSS-kytkin (WR-02), Sivujen nimeäminen

---

## Template-jaon syvyys

| Option | Description | Selected |
|--------|-------------|----------|
| Täysi HTML-erotus nyt | themes/default/pages/*.php kirjoitetaan puhtaina templateina (vain HTML + muuttujat), Phase 8 vain kytkee kontrollerit | ✓ |
| Kopioi sellaisenaan | themes/default/pages/*.php saa saman data+HTML-sekamuodon kuin nykyiset tiedostot, jako tehdään kokonaan Phase 8:ssa | |

**User's choice:** Täysi HTML-erotus nyt
**Notes:** Selkeämpi vastuunjako Phase 7/8 välillä, vaikka enemmän työtä nyt.

---

## Siirto vs. kopiointi

| Option | Description | Selected |
|--------|-------------|----------|
| Kopioi rinnakkain | header/footer/nav/CSS kopioidaan themes/default/-kansioon, alkuperäiset pysyvät käytössä | ✓ |
| Siirrä heti ja päivitä polut | Tiedostot siirretään ja kontrollerit päivitetään heti resolveThemePath()-kutsuihin | |

**User's choice:** Kopioi rinnakkain
**Notes:** Pitää sivuston toimivana muuttumattomana koko Phase 7:n ajan; Phase 8 poistaa vanhat kopiot kytkennän jälkeen.

---

## CSS-kytkin (WR-02)

| Option | Description | Selected |
|--------|-------------|----------|
| Vasta Phase 8:ssa | header.php pysyy muuttumattomana, lataa yhä public/assets/css/style.css:n | ✓ |
| Kytke jo nyt | header.php päivitetään käyttämään THEME_URL:ia jo Phase 7:ssä | |

**User's choice:** Vasta Phase 8:ssa
**Notes:** Yhdenmukainen "kopioi rinnakkain" -päätöksen kanssa.

---

## Sivujen nimeäminen

| Option | Description | Selected |
|--------|-------------|----------|
| Säilytä nykyiset nimet | themes/default/pages/ saa ajankohtaista.php ja postaus.php, ei uudelleennimeämistä | ✓ |
| Nimeä uudelleen ROADMAPin mukaan | ajankohtaista.php → blogi.php | |

**User's choice:** Säilytä nykyiset nimet
**Notes:** ROADMAPin "blogi"-maininta viittaa toiminnallisuuteen, ei tiedostonimeen.

---

## Claude's Discretion

- Tarkka data/HTML-erottelutapa kussakin 7 sivussa (mitkä muuttujat annetaan templatelle) jätetään plannerin/executorin päätettäväksi.
- `theme-page.php` (manifest-pohjainen generic-kontrolleri) rajattu pois Phase 7:n scopesta — ei osa 7 vakiosivun listaa.

## Deferred Ideas

None — discussion stayed within phase scope.
