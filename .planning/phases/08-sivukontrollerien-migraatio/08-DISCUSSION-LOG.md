# Phase 8: Sivukontrollerien migraatio - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-04
**Phase:** 8-Sivukontrollerien-migraatio
**Areas discussed:** Override-koukku, oma-talli, Verifiointi, Konventio, Testiteema

---

## Override-koukku (kaksoismekanismi)

| Option | Description | Selected |
|--------|-------------|----------|
| Säilytä molemmat, laajenna kaikkiin 7 sivuun | Jokainen kontrolleri tarkistaa ensin onko teemalla oma juuritason override-tiedosto — jos on, käytetään sitä kokonaan; muuten data-only + resolveThemePath('pages/X.php'). | ✓ |
| Poista override-koukku, yhtenäistä pages/-malliin | index.php ja hevonen.php menettäisivät nykyisen erikoiskäsittelyn; oma-talli ei toimisi ennen uudelleenrakennusta. | |
| Jätä päätös Claudelle tutkimusvaiheessa | Ei lukita nyt. | |

**User's choice:** Säilytä molemmat, laajenna kaikkiin 7 sivuun.
**Notes:** Löydettiin sitoutumaton `public/themes/oma-talli/`-kansio, joka käyttää juuri tätä juuritason override-mallia.

---

## oma-talli (sitoutumaton kansio)

| Option | Description | Selected |
|--------|-------------|----------|
| Kyllä, rakennan toista teemaa parhaillaan | oma-talli on aktiivista työtä tälle milestonelle. | ✓ |
| Ei, se on vain vanhan sivuston raakadumppi | Ei vaikuta Phase 8:n suunnitteluun. | |
| En ole varma / kerron myöhemmin | Ei tarvitse päättää nyt. | |

**User's choice:** Kyllä, rakennan toista teemaa parhaillaan.
**Notes:** Vahvistaa miksi override-koukun laajennus on relevantti; oma-tallin rakenteellinen viimeistely jää kuitenkin Phase 8:n ulkopuolelle (ks. Deferred).

---

## Verifiointi (Success Criteria #3)

| Option | Description | Selected |
|--------|-------------|----------|
| Luo kevyt testiteema (kopio default:sta, pieni visuaalinen muutos) | Yksinkertaisin tapa todistaa kytkentä toimii, ei riipu oma-tallin keskeneräisestä rakenteesta. | ✓ |
| Käytä oma-tallia jos se saadaan yhteensopivaksi | Todempi testi, mutta riippuu oma-tallin rakenteesta. | |
| Riittää manuaalinen DB-arvon vaihto + visuaalinen tarkistus samalla teemalla | Kevyin, mutta todistaa heikommin teema-agnostisuuden. | |

**User's choice:** Luo kevyt testiteema (kopio default:sta, pieni visuaalinen muutos).

---

## Konventio (override-koukun tarkka kaava)

| Option | Description | Selected |
|--------|-------------|----------|
| Kyllä, täysin sama kaava kaikissa 7:ssä | themes/{teema}/hevoset.php jne. — identtinen ehtorakenne kuin index.php/hevonen.php:ssä nyt. | ✓ |
| Muu — kerron tarkemmin | Poikkeus tai eri nimeämiskäytäntö. | |

**User's choice:** Kyllä, täysin sama kaava kaikissa 7:ssä.

---

## Testiteema (pysyvyys)

| Option | Description | Selected |
|--------|-------------|----------|
| Väliaikainen, poistetaan Phase 8:n jälkeen | Vain kontrollerimigraation todistamiseen. | ✓ |
| Jätetään pysyväksi (toinen valmis teema, V2-06 ennakointi) | Toimisi myös Phase 9:n testidatana. | |

**User's choice:** Väliaikainen, poistetaan Phase 8:n jälkeen.

---

## Claude's Discretion

- Tarkka data/muuttuja-jako kunkin 7 kontrollerin sisällä (templatet Phase 7:stä sanelevat muuttujasopimuksen).
- 404/virhekäsittely resolveThemePath()-epäonnistumiselle (epätodennäköinen, koska default-teema kattaa aina kaikki 7 sivua).
- Testiteeman tarkka nimi/slug ja visuaalinen muutos.

## Deferred Ideas

- oma-talli-teeman rakenteellinen viimeistely (nav-yhteensopivuus, puuttuvat vakiosivut kuten kasvatus/yhteystiedot/blogi-vastineet) — ei kuulu Phase 8:aan eikä Phase 9:ään, jää tulevaksi työksi.
