# Phase 6: Teema-infrastruktuuri - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-22
**Phase:** 06-Teema-infrastruktuuri
**Areas discussed:** active_theme migraatio, resolveThemePath() signatuuri, Phase 6 integraatiotodistus, theme.json rakenne

---

## active_theme migraatio

**Kysymys 1: Miten active_theme-rivi lisätään settings-tauluun?**

| Option | Description | Selected |
|--------|-------------|----------|
| Uusi migrate_theme.sql | Uusi erillinen tiedosto database/migrate_theme.sql — johdonmukainen projektin pattern kanssa | ✓ |
| Lisäys migrate_settings.sql:oon | Lisätään INSERT IGNORE olemassa olevaan tiedostoon | |
| PHP auto-seed theme.php:ssä | theme.php tarkistaa ja insertoi jos active_theme puuttuu | |

**User's choice:** Uusi migrate_theme.sql

**Kysymys 2: Mikä on active_theme:n oletusarvo?**

| Option | Description | Selected |
|--------|-------------|----------|
| 'default' | INSERT IGNORE INTO settings ('active_theme', 'default') | ✓ |
| NULL / tyhjä | theme.php käyttää 'default'-fallbackia kun arvo on NULL | |

**User's choice:** 'default'

---

## resolveThemePath() signatuuri

**Kysymys 1: Miten funktiota kutsutaan?**

| Option | Description | Selected |
|--------|-------------|----------|
| resolveThemePath('pages/index.php') | Yksi merkkijonoargumentti — suhteellinen polku teemakansion sisällä | ✓ |
| resolveThemePath('pages', 'index.php') | Kaksi argumenttia: tyyppi + tiedostonimi | |

**User's choice:** resolveThemePath('pages/index.php') — flat single path

**Kysymys 2: Mitä palautetaan kun aktiivisessa teemassa ei ole tiedostoa?**

| Option | Description | Selected |
|--------|-------------|----------|
| Absoluuttinen palvelinpolku default-teemaan | Palauttaa polun default-teeman vastaavaan tiedostoon | ✓ |
| false / null | Palauttaa false — kutsuva koodi käsittelee itse | |

**User's choice:** Absoluuttinen palvelinpolku default-teemaan (fallback 'default'-teemaan)

---

## Phase 6 integraatiotodistus

**Kysymys 1: Miten Phase 6 todistaa shimin toimivan?**

| Option | Description | Selected |
|--------|-------------|----------|
| Lisätään require jo index.php:hen | public/pages/index.php saa require_once theme.php:n nyt | ✓ |
| Tilapäinen test_theme.php | Luodaan throwaway-testisivu, poistetaan myöhemmin | |

**User's choice:** Lisätään require jo index.php:hen

**Kysymys 2: Mitä index.php:hen tarkalleen lisätään?**

| Option | Description | Selected |
|--------|-------------|----------|
| Vain require_once theme.php | Pelkkä require-rivi — muu logiikka pysyy ennallaan | ✓ |
| Require + korvaa include-kutsut | Require + header/footer/nav THEME_PATH:lla — täysi migraatio | |

**User's choice:** Vain require_once theme.php — Phase 8 migroi loput

---

## theme.json rakenne

**Kysymys 1: Minimaalinen vai laajennettu rakenne?**

| Option | Description | Selected |
|--------|-------------|----------|
| Minimaalinen: name + version | {"name": "Default", "version": "1.0.0"} — vain THEME-04 vaatii | ✓ |
| Laajennettu: + description + author | Enemmän tietoa admin-listaukseen, mutta V2-05 vaatisi vielä muutoksia | |

**User's choice:** Minimaalinen — {"name": "Default", "version": "1.0.0"}

---

## Claude's Discretion

Ei alueita joissa käyttäjä sanoi "sinä päätät" — kaikki valinnat käyttäjän tekemiä.

## Deferred Ideas

Ei deferroituja ideoita — keskustelu pysyi Phase 6:n scopessa.
