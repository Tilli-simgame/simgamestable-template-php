---
gsd_state_version: 1.0
milestone: v1.1
milestone_name: — Teemajärjestelmä
current_phase: 8
current_phase_name: Sivukontrollerien migraatio
status: executing
stopped_at: Phase 08 UI-SPEC approved
last_updated: "2026-07-04T06:49:06.771Z"
last_activity: 2026-07-03
last_activity_desc: Phase 07 complete, transitioned to Phase 8
progress:
  total_phases: 4
  completed_phases: 2
  total_plans: 6
  completed_plans: 6
  percent: 50
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-06-22)

**Core value:** Hevosomistaja voi hallita koko tallinsa hevostietoja yhdestä turvallisesta admin-paneelista, ja kaikki tieto näkyy automaattisesti julkisella sivustolla.
**Current focus:** Phase 07 — oletusteman-rakenne

## Current Position

Phase: 8 — Sivukontrollerien migraatio
Plan: Not started
Status: Ready to execute
Last activity: 2026-07-03 — Phase 07 complete, transitioned to Phase 8

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
- [Phase ?]: v1.1 Plan 07-03: pedigreeHorseLink() ja pedigreeCell() säilytetty hevonen.php-sivupohjan sisällä (ei helpers.php:ssä) — PHP ei salli funktioiden kaksoismäärittelyä
- [Phase ?]: v1.1 Plan 07-04: MONTHS_FI presentaatio-lookup kopioitu sivupohjaan molemmissa (ajankohtaista, postaus) - sama periaate kuin genderFi Plan 07-02:ssa

### Blockers/Concerns

- Altervistan CSS MIME-tyyppi subdirektoreille `public/themes/*/assets/css/` — ei voi varmistaa ennen FTP-deploymenttia (Phase 9)

## Session Continuity

Last session: 2026-07-04T06:49:06.741Z
Stopped at: Phase 08 UI-SPEC approved
Resume file: .planning/phases/08-sivukontrollerien-migraatio/08-UI-SPEC.md

## Performance Metrics

| Phase | Plan | Duration | Notes |
|-------|------|----------|-------|
| Phase 07 P01 | 10min | 2 tasks | 4 files |
| Phase 07 P03 | 12min | 1 tasks | 1 files |
| Phase 07 P04 | 10min | 2 tasks | 2 files |
