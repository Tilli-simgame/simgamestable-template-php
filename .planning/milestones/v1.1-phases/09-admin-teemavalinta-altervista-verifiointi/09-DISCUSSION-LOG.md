# Phase 9: Admin-teemavalinta & Altervista-verifiointi - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-04
**Phase:** 9-Admin-teemavalinta & Altervista-verifiointi
**Areas discussed:** Teemalistan käsittely (oma-talli), public/themes/-kansiosuojaus, Nykyisen settings.php-toteutuksen kohtalo, Altervista-verifioinnin laajuus

---

## Teemalistan käsittely (oma-talli)

| Option | Description | Selected |
|--------|-------------|----------|
| Näkyy normaalisti (nykytila) | Ei muutosta — glob() löytää sen automaattisesti, admin voi valita sen vaikka sivuja puuttuisi (fallback defaultiin) | ✓ |
| Merkitään "Kesken"-lipulla listassa | theme.json saisi status-kentän, settings.php näyttäisi huomautuksen | |
| Piilotetaan listasta kokonaan | Suodatus estäisi keskeneräisten teemojen näkymisen ennen valmiiksi merkitsemistä | |

**User's choice:** Näkyy normaalisti (nykytila)
**Notes:** Ei muutostarvetta — nykyinen glob()-pohjainen listaus riittää.

---

## public/themes/-kansiosuojaus

| Option | Description | Selected |
|--------|-------------|----------|
| Riittää periytyvä suojaus | Root .htaccess:n Options -Indexes periytyy, ei uutta tiedostoa | ✓ |
| Lisätään oma public/themes/.htaccess | Eksplisiittinen Options -Indexes belt-and-suspenders -periaatteella | |

**User's choice:** Riittää periytyvä suojaus
**Notes:** Ei uutta .htaccess-tiedostoa hakemistolistauksen estoon.

| Option | Description | Selected |
|--------|-------------|----------|
| Riittää nykytila | display_errors Off riittää suojaksi pages/-templatejen suoralta kutsulta | |
| Lisätään esto pages/-alikansion suoralle PHP-ajolle | Eksplisiittinen esto templaten suoralle kutsulle | ✓ |

**User's choice:** Lisätään esto pages/-alikansion suoralle PHP-ajolle
**Notes:** Templatet odottavat kontrollerin asettamia muuttujia — halutaan eksplisiittinen esto pelkän display_errors-asetuksen sijaan.

| Option | Description | Selected |
|--------|-------------|----------|
| PHP-vakiotarkistus templaten alkuun | define()-pohjainen tarkistus jokaiseen templateen | |
| .htaccess-esto pages/-alikansioille | Hakemistotason esto, ei vaadi PHP-koodin muutosta | ✓ |

**User's choice:** .htaccess-esto pages/-alikansioille
**Notes:** Koskee kaikkia teemoja, myös tulevia (esim. oma-talli).

---

## Nykyisen settings.php-toteutuksen kohtalo

| Option | Description | Selected |
|--------|-------------|----------|
| Kelpaa sellaisenaan | THEME-10/THEME-11 katsotaan jo täytetyksi olemassa olevalla koodilla | ✓ |
| Pieniä parannuksia tarvitaan | Perusrakenne säilyy, mutta jotain hiotaan | |

**User's choice:** Kelpaa sellaisenaan
**Notes:** settings.php:n glob()+theme.json-pohjainen teemalistaus/tallennus (ei getThemeManifest(), koska admin ei lataa theme.php:tä D-09:n mukaisesti) on tarkoituksellista eristystä, ei bugi. Phase 9 ei muokkaa tätä koodia.

---

## Altervista-verifioinnin laajuus

| Option | Description | Selected |
|--------|-------------|----------|
| Vain default-teema | oma-talli on keskeneräinen, ei kuulu verifiointiin | ✓ |
| Default + oma-talli | Molemmat testataan tuotannossa | |

**User's choice:** Vain default-teema

| Option | Description | Selected |
|--------|-------------|----------|
| Selaimen DevTools (Network-välilehti) | CSS:n Content-Type tarkistetaan selaimesta deploymentin jälkeen | ✓ |
| curl -I komennolla | Komentorivipohjainen tarkistus | |

**User's choice:** Selaimen DevTools (Network-välilehti)

| Option | Description | Selected |
|--------|-------------|----------|
| Kirjataan tulokset ylös | Dokumentoitu tarkistuslista/tulos phasen artefaktiksi | |
| Riittää kertaluontoinen tarkistus | Ei erillistä dokumenttia, jos kaikki toimii phase merkitään valmiiksi | ✓ |

**User's choice:** Riittää kertaluontoinen tarkistus

---

## Claude's Discretion

- .htaccess-eston tarkka syntaksi (FilesMatch-regex, Deny from all vs. 403-uudelleenohjaus) pages/-alikansioille.
- Miten estomekanismi dokumentoidaan/asennetaan tuleviin teemoihin.
- Altervista-verifioinnin tarkka suoritusjärjestys (mitä sivuja klikataan läpi).

## Deferred Ideas

- oma-talli-teeman rakenteellinen viimeistely ja sen tuotantoverifiointi — jää tulevaksi työksi kun teema otetaan käyttöön.
- theme.json-kentän lisäys keskeneräisten teemojen merkitsemiseksi admin-listassa — ei valittu tässä phasessa, voi nousta uudelleen esiin jatkossa.
