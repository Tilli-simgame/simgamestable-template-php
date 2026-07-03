---
gsd_state_version: 1.0
milestone: v1.1
milestone_name: — Teemajärjestelmä
status: "Phase 06 valmis — seuraava: Phase 07 (oletusteman rakenne)"
stopped_at: Phase 7 context gathered
last_updated: "2026-07-03T06:35:10.640Z"
last_activity: 2026-06-22 -- Phase 06 Plan 02 completed
progress:
  total_phases: 4
  completed_phases: 1
  total_plans: 2
  completed_plans: 2
  percent: 25
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-06-22)

**Core value:** Hevosomistaja voi hallita koko tallinsa hevostietoja yhdestä turvallisesta admin-paneelista, ja kaikki tieto näkyy automaattisesti julkisella sivustolla.
**Current focus:** Phase 06 — teema-infrastruktuuri

## Current Position

Phase: 06 (teema-infrastruktuuri) — COMPLETE
Plan: 2 of 2 (kaikki valmiina)
Status: Phase 06 valmis — seuraava: Phase 07 (oletusteman rakenne)
Last activity: 2026-06-22 -- Phase 06 Plan 02 completed

Progress: [██████░░░░] 60% (v1.1 scope)

## Workflow Status

| Phase | Status | Started | Completed |
|-------|--------|---------|-----------|
| Phase 1 — Perusta & DB | Complete | 2026-06-17 | 2026-06-17 |
| Phase 2 — Julkiset sivut | Complete | 2026-06-17 | 2026-06-17 |
| Phase 3 — Admin-paneeli | Complete | 2026-06-17 | 2026-06-17 |
| Phase 4 — Tietoturva & Deploy | Complete | 2026-06-17 | 2026-06-18 |
| Phase 5 — Blogi | Complete | 2026-06-18 | 2026-06-18 |
| Phase 6 — Teema-infrastruktuuri | Complete | 2026-06-22 | 2026-06-22 |
| Phase 7 — Oletusteman rakenne | Not started | — | — |
| Phase 8 — Sivukontrollerien migraatio | Not started | — | — |
| Phase 9 — Admin-teemavalinta & Altervista | Not started | — | — |

## Configuration

- **Mode:** YOLO
- **Granularity:** Coarse
- **Research:** Completed (research/SUMMARY.md)
- **Plan Check:** Enabled
- **Verifier:** Enabled
- **Git tracking:** Enabled

## Accumulated Context

### Decisions

- Admin-paneeli: kirjautuminen bcrypt+CSRF, pehmeä poisto hevosille, kuvat finfo_file() MIME-tarkistuksella
- CI/CD: SamKirkland/FTP-Deploy-Action@v4 deployaa public/ Altervistaan push main -triggerillä
- v1.1: resolveThemePath() käyttää preg_match + realpath + prefix-check (path-traversal-suojaus)
- v1.1: admin-paneeli ei koskaan lataa theme.php-shimmiä
- v1.1: public/assets/css/style.css pysyy muuttumattomana — admin_header.php riippuu siitä
- v1.1 Plan 01: INSERT IGNORE (ei ON DUPLICATE KEY UPDATE) migraatioissa — yhdenmukaisuus migrate_*.sql-tiedostojen kanssa
- v1.1 Plan 01: theme.json vain name+version — description/author/preview ovat V2-05 laajennuksia
- v1.1 Plan 02: resolveThemePath() käyttää str_starts_with + string|false union type (PHP 8.0+); Altervista-yhteensopivuus varmistetaan Phase 9:ssä
- v1.1 Plan 02: realpath()-fallback shimissä — shim ei kuole vaikka themes/-hakemisto puuttuu käynnistyksessä

### Blockers/Concerns

- Altervistan CSS MIME-tyyppi subdirektoreille `public/themes/*/assets/css/` — ei voi varmistaa ennen FTP-deploymenttia (Phase 9)

## Session Continuity

Last session: 2026-07-02T17:15:40.301Z
Stopped at: Phase 7 context gathered
Resume file: .planning/phases/07-oletusteman-rakenne/07-CONTEXT.md
