---
phase: 12
slug: sis-lt-tyyppien-roolirajaus
status: verified
# threats_open = count of OPEN threats at or above workflow.security_block_on severity (the blocking gate)
threats_open: 0
asvs_level: 1
created: 2026-07-17
---

# Phase 12 — Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Data Crossing |
|----------|-------------|---------------|
| operaattori/CLI → dev-tietokanta | DDL-migraatio ajetaan tietokantaan; virheellinen kohde tai tuhoava lause voisi menettää dataa | SQL DDL/DML (schema + backfill) |
| selain (author, epäluotettu) → admin/posts.php | Kirjautunut author-rooli; rooli- ja omistajuusrajaus PAKKO tehdä palvelinpuolella, ei piilottamalla linkkejä | Session-authenticated HTTP GET/POST |
| crafted POST → posts.php UPDATE-käsittelijä | Hyökkääjä voi lähettää mielivaltaisen edit_id:n POST-rungossa GET-näkymän ohi | POST body (edit_id, title, content) |
| POST-runko (horse_ids, edit_id) → tietokanta | Käyttäjän toimittamat arvot; identiteetti johdettava aina sessiosta, ei POST:sta | POST body → bound SQL params |

---

## Threat Register

| Threat ID | Category | Component | Severity | Disposition | Mitigation | Status |
|-----------|----------|-----------|----------|-------------|------------|--------|
| T-12-01-01 | Tampering | `database/migrate_posts_author.sql` (ALTER/UPDATE) | low | mitigate | Additive-only (ADD COLUMN + backfill UPDATE, no DROP/DELETE); FK `ON DELETE SET NULL` protects posts from user deletion. Verified: live DB query confirms 0 NULL `author_id` rows post-backfill. | closed |
| T-12-01-02 | Denial of Service | FK `fk_posts_author` | low | accept | FK references `admin_users(id)` which pre-exists `posts`; `SET FOREIGN_KEY_CHECKS=0` on fresh install avoids ordering issues. Accepted at plan time — no mitigation required. | closed |
| T-12-02-01 | Elevation of Privilege | `posts.php` GET `action=edit` branch | high | mitigate | `requireOwnResourceOrAdmin($editPost['author_id'])` called immediately after fetch, before form pre-fill — author opening another author's edit URL redirects to `ei-oikeutta.php`. Verified: code trace (VERIFICATION.md truth #6), independent code review (12-REVIEW.md), and live human UAT item (b). | closed |
| T-12-02-02 | Tampering / Elevation | `posts.php` POST UPDATE branch | high | mitigate | Ownership fetch + `requireOwnResourceOrAdmin()` called BEFORE the UPDATE statement — crafted POST with another author's `edit_id` is blocked before any write. Verified: code trace (VERIFICATION.md truth #7), 12-REVIEW.md, live human UAT item (c). | closed |
| T-12-02-03 | Information Disclosure | `posts.php` list query | medium | mitigate | `WHERE author_id = :aid` applied when `currentRole() === 'author'` — author cannot see existence of other authors' posts. Verified: VERIFICATION.md truth #5, live human UAT item (a). | closed |
| T-12-02-04 | Spoofing | `author_id` source in INSERT/ownership checks | high | mitigate | Identity always derived from `$_SESSION['admin_id']`, never from POST input, at every comparison site (INSERT, GET guard, POST guard). Verified: VERIFICATION.md Key Link Verification row 4, 12-REVIEW.md ("identity is always taken from `$_SESSION['admin_id']`, never from request input"). | closed |
| T-12-02-05 | Elevation of Privilege | `users.php`/`settings.php` direct URL, mod/author | high | mitigate | Phase 10's `requireRole('admin')` gating confirmed unchanged via D-04 regression grep pass (VERIFICATION.md) and live human UAT item (e). | closed |
| T-12-02-06 | Bypass | CSRF validation ordering vs. new ownership branch | medium | mitigate | Ownership check (`posts.php:45`) is nested inside the `else` block gated by `validate_csrf_token()` (`posts.php:11`) — CSRF validation strictly precedes the ownership check, not bypassed by it. Verified directly by code read during this audit. | closed |
| T-12-SC | Tampering | npm/pip/cargo installs | low | accept | No package installs in this phase (SQL + PHP edits only) — no package-legitimacy risk surface. Accepted at plan time (both 12-01 and 12-02). | closed |

*Status: open · closed · open — below high threshold (non-blocking)*
*Severity: critical > high > medium > low — only open threats at or above workflow.security_block_on (high) count toward threats_open*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-12-01 | T-12-01-02 | FK ordering risk mitigated by `SET FOREIGN_KEY_CHECKS=0` on fresh install; `admin_users` table pre-exists `posts` in normal migration order, so no real DoS surface. | Plan (12-01) | 2026-07-17 |
| AR-12-02 | T-12-SC | No package installs occur in Phase 12 (SQL migration + PHP edits only) — package-legitimacy checkpoint not applicable. | Plan (12-01, 12-02) | 2026-07-17 |

*Accepted risks do not resurface in future audit runs.*

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-07-17 | 9 | 9 | 0 | Claude (gsd-secure-phase, orchestrator classification — L1/ASVS-1 short-circuit, no auditor spawn needed) |

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed
- [x] `status: verified` set in frontmatter

**Approval:** verified 2026-07-17
