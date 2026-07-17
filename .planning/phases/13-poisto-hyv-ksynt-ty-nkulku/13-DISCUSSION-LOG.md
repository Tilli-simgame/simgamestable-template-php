# Phase 13: Poisto-hyväksyntätyönkulku - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-17
**Phase:** 13-Poisto-hyväksyntätyönkulku
**Areas discussed:** Skeema (pending_deletions + soft-delete), Poisto-käyttäytyminen roolin mukaan, Admin-hyväksyntänäkymä

**Mode:** `--auto` — no interactive AskUserQuestion calls were made. Every decision below is Claude's recommended default, selected automatically per `workflows/discuss-phase/modes/auto.md`, grounded in `research/SUMMARY.md`'s Phase 4 (Delete-Approval Workflow) findings and the established patterns from Phase 10/11/12 CONTEXT.md files.

---

## Skeema: pending_deletions-jono ja soft-delete-sarakkeet

| Option | Description | Selected |
|--------|-------------|----------|
| Yksi jaettu `pending_deletions`-polymorfinen jonotaulu | `entity_type`/`entity_id`/`requested_by`/`status`/`reviewed_by` — yksi taulu kattaa kaikki 5 sisältötyyppiä | ✓ |
| Viisi erillistä status-saraketta per sisältötaulu | `horses.deletion_status`, `foals.deletion_status`, jne. | |

**Selected:** Jaettu jonotaulu (D-01).
**Notes:** `research/SUMMARY.md` rivi 20 suositteli tätä eksplisiittisesti "beats five near-duplicate per-table status columns" -perusteluna. [auto] Skeema — Q: "Jaettu jono vai per-taulu status?" → Selected: "Jaettu pending_deletions-jono" (tutkimuksen suositus).

| Option | Description | Selected |
|--------|-------------|----------|
| PHP-tason check-then-insert-duplikaattiesto | `SELECT` ennen `INSERT`iä sovelluskoodissa | ✓ |
| Tietokannan UNIQUE-rajoite | Osittainen/ehdollinen UNIQUE-indeksi `pending_deletions`-taulussa | |

**Selected:** PHP-tason tarkistus (D-03).
**Notes:** `research/SUMMARY.md` rivi 122: "Altervista's exact MySQL/MariaDB version is unconfirmed... recommended approach (PHP-level uniqueness check) sidesteps this entirely." [auto] Duplikaattiesto — Q: "PHP-check vai DB-constraint?" → Selected: "PHP-tason check-then-insert" (kiertää versio-epävarmuuden).

---

## Poisto-käyttäytyminen roolin mukaan

| Option | Description | Selected |
|--------|-------------|----------|
| Mod: soft-delete + pending-rivi; Admin: suora soft-delete; Author-oma-postaus: suora soft-delete | Kolmihaarainen logiikka roolin mukaan | ✓ |
| Kaikki roolit (mod mukaan lukien) hyväksyntäkiertoon | Myös admin joutuisi hyväksymään omat poistonsa | |

**Selected:** Kolmihaarainen roolikohtainen logiikka (D-04).
**Notes:** `research/SUMMARY.md` rivi 86 määrittelee tämän eksplisiittisesti call-site-tasolla. Admin on roolihierarkian huipulla eikä tarvitse hyväksyntää itseltään; author-oma-postaus on jo AUTHOR-03:n eksplisiittinen vaatimus (ROADMAP SC4). [auto] Poistologiikka — Q: "Kenen poistoa hyväksyntä koskee?" → Selected: "Vain mod:n poisto muille kuin omille postauksille" (tutkimuksen malli).

---

## Admin-hyväksyntänäkymä

| Option | Description | Selected |
|--------|-------------|----------|
| Yksi yhtenäinen taulukko kaikille 5 tyypille (`admin/deletions.php`) | Sarakkeet: Tyyppi/Kohde/Pyytäjä/Pyydetty/Toiminnot | ✓ |
| Erilliset tabit/osiot per sisältötyyppi | 5 erillistä listaa yhdellä sivulla | |

**Selected:** Yhtenäinen taulukko (D-06).
**Notes:** Mirroroi Phase 11:n D-01-päätöstä (`users.php`-taulukkomalli, ei compact-list) — sama perustelu (yksinkertaisin toteutus, pieni volyymi yhden tallin skaalassa). [auto] Näkymä — Q: "Yksi taulukko vai per-tyyppi-osiot?" → Selected: "Yksi yhtenäinen taulukko" (Phase 11:n konventio).

| Option | Description | Selected |
|--------|-------------|----------|
| Laskuri admin-etusivun stat-korttina | Uusi `.admin-stat-card` olemassa olevassa `.admin-stat-row`:ssa | ✓ |
| Laskuri + nav-badge | Molemmat stat-kortti JA numero nav-linkin vieressä | |

**Selected:** Vain stat-kortti (D-07).
**Notes:** Seuraa ROADMAP:n kirjaimellista sanamuotoa ("admin-etusivulla näkyy... laskurina") — nav-badge olisi lisäscope. [auto] Laskuri — Q: "Pelkkä stat-kortti vai myös nav-badge?" → Selected: "Vain stat-kortti" (roadmapin kirjaimellinen scope).

---

## Claude's Discretion

- Hyväksy/hylkää-napit: erilliset inline-lomakkeet vs. yksi käsittelijä `action`-parametrilla.
- `entity_type`-whitelist-toteutustapa (`match()`-lauseke tms.).
- Viiden `_delete.php`-käsittelijän refaktorointitapa (yhteinen `insertPendingDeletion()`-apufunktio vs. duplikoitu logiikka).
- `is_deleted = 0`-suodatuksen tarkka lisäyskohta jokaiseen kyselyyn (audit-passi).
- Nappien sanamuoto ja CSS-tyylitys (uudelleenkäyttö oletuksena).

## Deferred Ideas

- Kuvien (`horse_photos`) poisto-hyväksyntä — ei ROADMAP:n viidessä sisältötyypissä, `photo_delete.php` pysyy admin-only.
- Mod-puolen ilmoitus-/historianäkymä hylätyille pyynnöille — ei roadmapin success-kriteereissä.
- Nav-badge laskurille — pidetty roadmapin kirjaimellisessa scopessa (vain stat-kortti).
