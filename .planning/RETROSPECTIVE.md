# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v1.1 — Teemajärjestelmä

**Shipped:** 2026-07-05
**Phases:** 4 | **Plans:** 12 | **Tasks:** 18

### What Was Built

- Theme infrastructure: `resolveThemePath()` helper with path-traversal protection (`preg_match` + `realpath` + prefix-check), `active_theme` stored in the `settings` table, `THEME_PATH`/`THEME_URL` constants exposed to public pages only via a `theme.php` shim that the admin panel never loads.
- Full `public/themes/default/` structure: includes, all 7 public page templates, and `style.css`, byte-identical in appearance to the pre-theme site.
- All 7 public controllers converted to data-only: no inline HTML, delegating rendering to `resolveThemePath('pages/X.php')`, with a standardized Model B root-override hook and a unified silent 404 convention.
- Admin theme picker in `settings.php` (found to already exist pre-GSD-tracking, formally verified against THEME-10/THEME-11) plus `.htaccess` protection against direct HTTP access to theme templates.
- Live production verification on Altervista (`/demotalli-02/`): correct CSS MIME type, working theme switch with zero controller edits, blocked directory listing, blocked direct template access.

### What Worked

- Empirically proving the core value proposition (Phase 08-04): standing up a disposable `testitema` theme, flipping `active_theme` in the DB, confirming HTTP 200 with changed output on two pages, then fully deleting the test theme — turned an abstract "the architecture supports theme switching" claim into a concrete, repeatable demonstration with no lingering artifact.
- Splitting the largest/riskiest controller (`hevonen.php`, 614 lines) into its own dedicated plan (08-03) rather than bundling it with the other data-only conversions — it needed careful handling of two helper functions that PHP forbids redeclaring elsewhere.
- Treating admin/public isolation as a first-class constraint throughout (D-09 in Phase 6, carried through to Phase 9): the admin panel never loading `theme.php` was checked by grep at every phase, catching regressions early instead of at the end.

### What Was Inefficient

- Runtime/human verification items from Phase 6 (path-traversal rejection, DB row confirmation) were never looped back into `06-VERIFICATION.md` even though equivalent behavior was later exercised live in Phase 9's production check — leaving the milestone audit with a stale "human_needed" status that had to be manually reconciled at close time instead of at Phase 6's own closeout.
- `06-02-SUMMARY.md`/`07-01-SUMMARY.md`/`07-02-SUMMARY.md`/`07-04-SUMMARY.md` had inconsistent `one_liner` frontmatter fields, requiring a fallback read of raw summary content during milestone stats gathering.

### Patterns Established

- Data-only controller + `resolveThemePath('pages/X.php')` + silent `http_response_code(404); exit;` is now the canonical shape for every public PHP entry point in this project.
- Security-sensitive path resolution follows `preg_match` (reject early) → `realpath()` (normalize) → `str_starts_with` prefix-check (contain) as a reusable 3-step pattern.
- When a feature claim is architectural ("switching X requires zero code changes"), prefer a throwaway empirical proof (build it, flip it, observe it, delete it) over trusting code inspection alone.

### Key Lessons

1. Close the loop on human-verification items at phase completion, not at milestone completion — a `human_needed` status left unresolved for two weeks becomes a judgment call at milestone close instead of a quick check right after the phase.
2. When a later phase's production verification incidentally re-exercises an earlier phase's unresolved runtime checks, that's legitimate evidence to acknowledge the gap — but it should be noted explicitly in the earlier phase's own verification file, not discovered for the first time during milestone audit.

### Cost Observations

- Sessions: spanned 2026-06-22 to 2026-07-05 (13 days, 85 commits across phases 6-9).
- Notable: Phase 9 Plan 01 completed in 5 minutes with 1 file changed (`.htaccess` addition) — smallest/fastest plan in the milestone, reflecting how much of THEME-10/THEME-11 was already satisfied by pre-existing code.

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Sessions | Phases | Key Change |
|-----------|----------|--------|------------|
| v1.1 | 1 (spanning 13 days) | 4 (6-9) | First milestone closed with formal `/gsd-complete-milestone` archival in this project |

### Cumulative Quality

| Milestone | Tests | Coverage | Zero-Dep Additions |
|-----------|-------|----------|---------------------|
| v1.1 | 0 (no test framework — PHP/MySQL project relies on manual + production verification) | N/A | 0 (no external dependencies added) |

### Top Lessons (Verified Across Milestones)

1. Close the loop on human-verification items at phase completion, not at milestone completion.
