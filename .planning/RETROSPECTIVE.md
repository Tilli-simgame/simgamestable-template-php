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

## Milestone: v1.2 — Käyttäjäroolit

**Shipped:** 2026-07-18
**Phases:** 4 (10-13) | **Plans:** 13 | **Tasks:** 31

### What Was Built

- Three-tier role system (admin/mod/author): `admin_users.role`/`is_active` columns, centralized `requireRole()`/`currentRole()`/`isAdmin()` guards, per-request session revalidation, and role-scoped nav visibility — Phase 10.
- Full admin-only user management (`users.php` family): create/edit/deactivate/reset-password/delete with last-admin and self-demotion lockout guards, generated passwords shown once inline (never via `$_GET`) — Phase 11.
- Content-type role scoping: mod manages all stable content unrestricted; author is ownership-restricted to own posts (`posts.author_id`, `requireOwnResourceOrAdmin()`) with IDOR defense-in-depth on both GET and POST paths — Phase 12.
- Delete-approval workflow: a shared `pending_deletions` polymorphic queue table, whitelist-only `entityTypeToTable()`, role-branched soft-delete across 6 delete sites, a unified `deletions.php` approval view with atomic (PDO-transaction) approve/reject, and a dashboard counter — Phase 13.

### What Worked

- Running discuss-phase in `--auto` mode grounded in prior research (`research/SUMMARY.md`'s pre-existing Phase 4 architecture recommendations) produced usable, research-backed decisions without a live user Q&A session — the phase 13 CONTEXT.md's 8 auto-selected decisions were all confirmed correct by later planning and execution.
- The `gsd-pattern-mapper` step caught a real gap before planning: CONTEXT.md assumed `foal_delete.php`/`competition_delete.php`/`showrecord_delete.php` existed as standalone files, but they're actually inline `action=delete` branches — and the planner's own follow-up grep audit found a *second*, previously-undocumented foal hard-delete site in `kasvatus_all.php` that would otherwise have bypassed the entire approval workflow.
- Post-review fixing paid off immediately: the code reviewer's CR-01 finding (a phantom-pending-row bug that could silently resurrect content an admin had already approved for permanent deletion) was fixed in the same session rather than deferred, closing a genuine data-integrity gap before it ever reached the human UAT step.

### What Was Inefficient

- Docker container instability mid-session (web/phpmyadmin containers exited unexpectedly between agent runs) interrupted the `php -l` lint-verification loop for the CR-01 fix; the fix had to be verified by manual diff inspection instead of container-based syntax checking.
- Two SUMMARY.md files across the milestone used inline YAML array syntax (`key-files: created: [file.sql]`) instead of the expected bullet-list format, which silently dropped files from the code-review file-scoping regex — caught and manually corrected both times, but indicates the SUMMARY.md template/parser contract could be tightened.
- STATE.md's "Workflow Status" table repeatedly drifted out of sync with actual phase completion (showing phases as "Not started" well after they were verified complete) — required manual correction at each phase transition rather than being kept live by the tooling.

### Patterns Established

- Shared polymorphic queue table (`pending_deletions`: `entity_type` ENUM + `entity_id` + `status` ENUM) is the canonical shape for any future multi-entity-type workflow needing admin approval, in preference to per-table status columns.
- `entityTypeToTable()`-style `match()` whitelists are the only sanctioned path from a user/DB-supplied type string to a SQL table/identifier name anywhere in this codebase — never string interpolation.
- Soft-delete mutations that create a dependent side-effect (a queue row) must gate that side-effect on the mutating statement's `rowCount() > 0`, not just the precondition check — otherwise stale/duplicate resubmissions against already-mutated rows create orphaned side-effect records.

### Key Lessons

1. A pattern-mapping pass before planning is worth its cost even on a small, well-understood phase — it caught two real discrepancies (nonexistent files assumed by CONTEXT.md, an undocumented second delete site) that would otherwise have produced an incomplete or broken plan.
2. Fixing a Critical code-review finding immediately (rather than filing it for a "future pass") is cheap when caught right after implementation and expensive to reconstruct later — the fix here took minutes because the executor's context was still fresh in the conversation.
3. STATE.md's auxiliary tracking tables (Workflow Status, Performance Metrics) need periodic reconciliation against the canonical frontmatter fields — they're informational, not authoritative, and drift silently if not checked at each phase transition.

### Cost Observations

- Sessions: spanned 2026-07-16 to 2026-07-18 (3 days, phases 10-13 fully chained via `--auto` discuss→plan→execute after Phase 12's UAT gate).
- Model mix: opus for planning (`gsd-planner`), sonnet for research/execution/review/verification/security — consistent with the project's configured model profile.
- Notable: Phase 12's 2-plan/2-task schema plan (12-01) completed in ~2 minutes — smallest plan in the milestone — while the 4-plan Phase 13 delete-approval workflow was the milestone's largest single unit of work (~50 min combined executor time across 4 sequential plans, worktree isolation degraded to sequential for the whole milestone due to a HEAD/origin divergence).

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Sessions | Phases | Key Change |
|-----------|----------|--------|------------|
| v1.1 | 1 (spanning 13 days) | 4 (6-9) | First milestone closed with formal `/gsd-complete-milestone` archival in this project |
| v1.2 | 1 (spanning 3 days) | 4 (10-13) | First milestone run end-to-end with `--auto` discuss→plan→execute chaining; first milestone with a code-review Critical finding fixed inline before shipping |

### Cumulative Quality

| Milestone | Tests | Coverage | Zero-Dep Additions |
|-----------|-------|----------|---------------------|
| v1.1 | 0 (no test framework — PHP/MySQL project relies on manual + production verification) | N/A | 0 (no external dependencies added) |
| v1.2 | 0 (no test framework — same manual + live-DB + container-based verification approach) | N/A | 0 (no external dependencies added) |

### Top Lessons (Verified Across Milestones)

1. Close the loop on human-verification items at phase completion, not at milestone completion.
2. A pattern-mapping/codebase-grounding pass before planning reliably catches stale assumptions and undocumented edge cases that pure requirements-reading misses (confirmed again in v1.2 after first appearing in v1.1's controller-splitting pattern).
