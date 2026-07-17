---
gsd_state_version: 1.0
milestone: v1.2
milestone_name: Käyttäjäroolit
current_phase: 13
current_phase_name: Poisto-hyväksyntätyönkulku
status: verifying
stopped_at: Phase 12 context gathered
last_updated: "2026-07-17T07:27:26.204Z"
last_activity: 2026-07-17
last_activity_desc: Phase 12 complete, transitioned to Phase 13
progress:
  total_phases: 4
  completed_phases: 3
  total_plans: 9
  completed_plans: 9
  percent: 75
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-07-17)

**Core value:** Hevosomistaja voi hallita koko tallinsa hevostietoja yhdestä turvallisesta admin-paneelista, ja kaikki tieto näkyy automaattisesti julkisella sivustolla.
**Current focus:** Phase 13 — poisto-hyväksyntätyönkulku

## Current Position

Phase: 13 — Poisto-hyväksyntätyönkulku
Plan: Not started
Status: Ready to plan
Last activity: 2026-07-17 — Phase 12 complete, transitioned to Phase 13

Progress: [░░░░░░░░░░] 0%

## Workflow Status

| Phase | Status | Started | Completed |
|-------|--------|---------|-----------|
| Phase 1 — Perusta & DB | Complete | 2026-06-17 | 2026-06-17 |
| Phase 2 — Julkiset sivut | Complete | 2026-06-17 | 2026-06-17 |
| Phase 3 — Admin-paneeli | Complete | 2026-06-17 | 2026-06-17 |
| Phase 4 — Tietoturva & Deploy | Complete | 2026-06-17 | 2026-06-18 |
| Phase 5 — Blogi | Complete | 2026-06-18 | 2026-06-18 |
| Phase 6 — Teema-infrastruktuuri | Complete | 2026-06-22 | 2026-06-22 |
| Phase 7 — Oletusteman rakenne | Complete | 2026-06-22 | 2026-07-03 |
| Phase 8 — Sivukontrollerien migraatio | Complete | 2026-07-03 | 2026-07-04 |
| Phase 9 — Admin-teemavalinta & Altervista | Complete | 2026-07-04 | 2026-07-05 |
| Phase 10 — Roolit ja autentikaation perusta | Complete | 2026-07-16 | 2026-07-16 |
| Phase 11 — Käyttäjähallinta | Complete | 2026-07-16 | 2026-07-16 |
| Phase 12 — Sisältötyyppien roolirajaus | Complete | 2026-07-17 | 2026-07-17 |
| Phase 13 — Poisto-hyväksyntätyönkulku | Not started | - | - |

## Configuration

- **Mode:** YOLO
- **Granularity:** Coarse
- **Research:** Completed (research/SUMMARY.md)
- **Plan Check:** Enabled
- **Verifier:** Enabled
- **Git tracking:** Enabled

## Accumulated Context

### Decisions

- Roadmap (v1.2): 4 phases derived from research/SUMMARY.md's dependency-ordered sequence — role/auth foundation before user management before content-type gating before delete-approval workflow, since each depends on the actor's role/permissions already being resolvable.
- Roadmap (v1.2): `AUTHOR-03` (author's immediate own-post delete) and `MOD-06` (mod's pending-deletion request) both grouped into Phase 13 rather than Phase 12, because both depend on the soft-delete/`pending_deletions` schema that Phase 13 introduces — post delete-branching logic (mod/admin/author) is one coherent unit of work.

Full decision log in `.planning/PROJECT.md` Key Decisions table.

- [Phase 10-01]: ei-oikeutta.php sulkeutuu require admin_footer.php -kutsulla eika kovakoodatulla </div></div></body></html>-lohkolla, koska se on kaikkien nykyisten admin-sivujen tosiasiallinen konventio — PATTERNS.md:n esimerkkilohko oli talta osin vanhentunut - grep-verifiointi osoitti kaikkien 21 admin-sivun kayttavan admin_footer.php-includea
- [Phase 10-01]: role-sarakkeen DEFAULT on author seka migraatiossa etta schema.sql:ssa (D-06 turvallisin fallback), admin-tunnus nostetaan eksplisiittisella UPDATE-lauseella
- [Phase ?]: [Phase 10-02]: Ei poikkeamia - plan suoritettu kirjaimellisesti audit-taulukon roolilistojen mukaan, mukaan lukien 10-01:ssa jo ratkaistut ASSUMED-oletukset (contact_delete.php/photo_delete.php -> admin+mod).
- [Phase 10-03]: change_password.php sulkeutuu require admin_footer.php -kutsulla PATTERNS.md:n kovakoodatun markkauksen sijaan (sama konventio kuin ei-oikeutta.php:ssä, 10-01)
- [Phase 11-01]: users.php uses requireRole('admin') only (not 'admin','mod') — entire users.* file family is admin-exclusive per phase domain — Phase 11 domain restricts account management to admin role only, unlike contacts.php which allows mod
- [Phase 11-01]: Reset-password/toggle-active flashes use non-secret $_GET flags only; plaintext generated password never travels via $_GET (T-11-06 mitigation) — Prevents password leakage via browser history/referrer/logs, deferred to inline display in wave 2 action pages
- [Phase 11]: user_add.php never redirects on success -- generated plaintext password is shown once inline (no $_GET transport) since a secret must never travel via redirect/browser history/Referer (T-11-06)
- [Phase 11]: user_edit.php's UPDATE touches only username+role columns; password changes are exclusively handled by the separate reset-password action in plan 11-03
- [Phase 11]: user_reset_password.php never redirects on success -- generated plaintext password shown once inline (no $_GET transport), mirroring user_add.php's pattern (T-11-06)
- [Phase 11]: user_toggle_active.php and user_delete.php only run last-admin/self guards on the destructive path (deactivating/deleting) -- reactivation is always safe and skips both guards
- [Phase 11-04]: requireRole() re-validates role/is_active from admin_users per protected request (one PK-indexed SELECT per page load accepted) instead of session-TTL caching, guaranteeing role/deactivation changes take effect on the next request (USER-02/SC2, CR-01)
- [Phase 11-04]: Username validation upper bound lowered from 255 to 50 in user_add.php/user_edit.php to match admin_users.username VARCHAR(50); INSERT/UPDATE wrapped in try/catch(PDOException) mapping SQLSTATE 23000 to the existing duplicate-username flash message (CR-02, WR-01)
- [Phase ?]: [Phase 12-01]: posts.author_id nullable with FK ON DELETE SET NULL so permanent user deletion (USER-04) clears ownership instead of cascading delete on posts
- [Phase ?]: [Phase 12-01]: Backfill targets existing 'admin' username per D-01, consistent with migrate_roles.sql's admin-elevation convention
- [Phase ?]: [Phase 12-02]: requireOwnResourceOrAdmin() placed alongside isAdmin()/requireRole() in helpers.php, mirroring the existing helper style exactly (no return value, redirect on failure); admin/mod always pass, only author is ownership-restricted
- [Phase ?]: [Phase 12-02]: POST UPDATE branch performs its own author_id fetch and ownership check independent of the GET-side check (defense-in-depth for crafted-POST IDOR, Pitfall 2), since a crafted POST bypasses the GET view entirely

### Pending Todos

None yet.

### Blockers/Concerns

None open. Altervista CSS MIME-tyyppi -epäilys (Phase 9, v1.1) ratkaistu tuotantoverifioinnilla 2026-07-05.

Research flags deeper attention needed during phase planning (not blockers, just planning inputs):

- Phase 13: `pending_deletions` polymorphic queue-table shape (entity_type/status columns, approve/reject transaction sequencing) has no single canonical source — MEDIUM confidence, needs a focused pass during planning.

## Deferred Items

Items acknowledged and carried forward from previous milestone close:

| Category | Item | Status | Deferred At |
|----------|------|--------|-------------|
| verification_gap | Phase 06 (`06-VERIFICATION.md`) | human_needed — 2/5 success criteria (path-traversal runtime rejection, `active_theme` DB row) never re-confirmed into the verification file. Superseded in practice by Phase 9's production verification. | v1.1 close (2026-07-05) |
| known_gap | `oma-talli` theme has no `pages/.htaccess` protection (unlike `default`) — theme is unfinished, intentionally out of v1.1 scope (D-06) | Open, not scheduled | v1.1 close (2026-07-05) |

## Session Continuity

Last session: 2026-07-17T07:27:26Z
Stopped at: Phase 12 complete, ready to plan Phase 13
Resume file: None

## Performance Metrics

**Velocity:**

- Total plans completed: 27 (v1.0 + v1.1)
- v1.2 plans: none yet planned

**By Phase:**

| Phase | Plan | Duration | Notes |
|-------|------|----------|-------|
| Phase 07 P01 | 10min | 2 tasks | 4 files |
| Phase 07 P03 | 12min | 1 tasks | 1 files |
| Phase 07 P04 | 10min | 2 tasks | 2 files |
| Phase 08 P01 | 20min | 3 tasks | 3 files |
| Phase 08 P02 | 15min | 3 tasks | 3 files |
| Phase 08 P03 | 12min | 1 tasks | 1 files |
| Phase 08 P04 | 15min | 3 tasks | 0 files |
| Phase 09 P01 | 5min | 2 tasks | 1 files |
| Phase 10 P01 | 5min | 3 tasks | 5 files |
| Phase 10 P02 | 15min | 2 tasks | 27 files |
| Phase 10 P03 | 12min | 2 tasks | 2 files |
| Phase 11 P01 | 12min | 3 tasks | 3 files |
| Phase 11 P02 | 12min | 2 tasks | 2 files |
| Phase 11 P03 | 10min | 3 tasks | 3 files |
| Phase 11 P04 | 10min | 2 tasks | 3 files |
| Phase 12 P01 | 2min | 2 tasks | 2 files |
| Phase 12 P2 | 8min | 3 tasks | 2 files |

## Operator Next Steps

- `/gsd-discuss-phase 13` — gather context for Poisto-hyväksyntätyönkulku before planning
