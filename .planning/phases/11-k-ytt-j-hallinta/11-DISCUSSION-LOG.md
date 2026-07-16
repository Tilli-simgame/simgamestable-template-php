# Phase 11: Käyttäjähallinta - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-16
**Phase:** 11-Käyttäjähallinta
**Areas discussed:** Listanäkymä, Uuden käyttäjän luonti (salasana), Salasanan nollaus, Oman roolin muokkaus, Nollaustapa (jatko), Reaktivointi (jatko), Poiston vahvistus (jatko)

---

## Käyttäjätunnusten listanäkymä

| Option | Description | Selected |
|--------|-------------|----------|
| Sama compact-list kuin osoitekirjassa | Klikattava/laajeneva rivi kuten contacts.php | |
| Yksinkertainen taulukko, aina näkyvät toiminnot | Perinteinen `<table>`, kaikki napit aina näkyvissä | ✓ |

**User's choice:** Yksinkertainen taulukko, aina näkyvät toiminnot
**Notes:** → D-01 CONTEXT.md:ssä.

---

## Uuden käyttäjän luonti — salasanan asetus

| Option | Description | Selected |
|--------|-------------|----------|
| Admin kirjoittaa salasanan suoraan lomakkeeseen | Käyttäjänimi + salasana (2x) + rooli samassa lomakkeessa | |
| Järjestelmä generoi satunnaisen salasanan, näytetään kerran | Vain käyttäjänimi + rooli, salasana generoidaan ja näytetään kerran | ✓ |

**User's choice:** Järjestelmä generoi satunnaisen salasanan, näytetään kerran
**Notes:** Ei sähköpostijärjestelmää — reset-linkki ei ole vaihtoehto. → D-02 CONTEXT.md:ssä.

---

## Salasanan nollaus olemassa olevalle käyttäjälle (USER-05)

| Option | Description | Selected |
|--------|-------------|----------|
| Erillinen "Nollaa salasana" -toiminto listassa | Oma pieni lomake/modaali, ei kysy vanhaa salasanaa | ✓ |
| Osa "Muokkaa käyttäjää" -lomaketta | Valinnainen salasanakenttäpari muokkauslomakkeessa | |

**User's choice:** Erillinen "Nollaa salasana" -toiminto listassa
**Notes:** → D-03 CONTEXT.md:ssä.

---

## Nollaustapa (jatkokysymys)

| Option | Description | Selected |
|--------|-------------|----------|
| Sama generoitu satunnaissalasana kuin luonnissa | Yhdenmukainen UX, näytetään kerran | ✓ |
| Admin kirjoittaa uuden salasanan käsin (2x vahvistus) | Sama malli kuin change_password.php mutta ilman nykyisen salasanan kysymistä | |

**User's choice:** Sama generoitu satunnaissalasana kuin luonnissa
**Notes:** → D-04 CONTEXT.md:ssä.

---

## Oman roolin muokkaus

| Option | Description | Selected |
|--------|-------------|----------|
| Estetään myös oman roolin alentaminen | Sama suoja kuin poistossa/deaktivoinnissa | ✓ |
| Sallitaan — USER-06:n viimeinen-admin-suoja riittää | Rooli voi vaihtua jos adminejä on useampi | |

**User's choice:** Estetään myös oman roolin alentaminen
**Notes:** → D-05 CONTEXT.md:ssä.

---

## Deaktivoitu käyttäjä taulukossa — reaktivointi

| Option | Description | Selected |
|--------|-------------|----------|
| Sama "Deaktivoi"-nappi muuttuu "Aktivoi"-napiksi | Yksi tila-toggle-nappi, muut toiminnot pysyvät käytettävissä | ✓ |
| Deaktivoitu rivi näyttää vain "Aktivoi"-napin | Muokkaus/nollaus piilotetaan kunnes reaktivoitu | |

**User's choice:** Sama "Deaktivoi"-nappi muuttuu "Aktivoi"-napiksi
**Notes:** → D-06 CONTEXT.md:ssä.

---

## Pysyvä poisto (USER-04) — vahvistustapa

| Option | Description | Selected |
|--------|-------------|----------|
| Sama confirm() kuin muualla admin-paneelissa | Yhdenmukainen contact_delete.php/photo_delete.php:n kanssa | ✓ |
| Tiukempi vahvistus — kirjoita käyttäjänimi uudelleen | Vahvempi suoja peruuttamattomalle toiminnolle | |

**User's choice:** Sama confirm() kuin muualla admin-paneelissa
**Notes:** → D-07 CONTEXT.md:ssä.

---

## Claude's Discretion

- Taulukon tarkka sarake-/tyylitysjärjestys ja CSS-luokat.
- Salasanan generointitapa (pituus, merkistö, PHP-funktio).
- Virheviestien tarkka sanamuoto (viimeinen-admin-suoja, itsesuoja).
- Nav-linkin ("Käyttäjähallinta") tarkka sijoituspaikka admin_header.php:ssä.
- Käyttäjänimen validointi (uniikkius, sallitut merkit).

## Deferred Ideas

Ei uusia scope-ehdotuksia noussut. Roadmapissa jo olemassa olevat myöhemmät vaiheet: sisältötyyppien roolirajaus (Phase 12), poisto-hyväksyntätyönkulku (Phase 13). `todo.match-phase` ei löytänyt osumia tälle vaiheelle (todo_count: 0).
