---
phase: 13
slug: poisto-hyv-ksynt-ty-nkulku
status: verified
# threats_open = count of OPEN threats at or above workflow.security_block_on severity (the blocking gate)
threats_open: 0
asvs_level: 1
created: 2026-07-18
---

# Phase 13 — Security

> Per-phase security contract: threat register, accepted risks, and audit trail.

---

## Trust Boundaries

| Boundary | Description | Data Crossing |
|----------|-------------|---------------|
| kutsuja → `insertPendingDeletion()` | `entity_type`/`entity_id` välitetään funktiolle; `requested_by` on aina sessiosta | Function call params, session-derived identity |
| `entity_type` → taulunimi (kaikki mappauskohdat) | Polymorfinen mappaus voi altistaa dynaamiselle SQL:lle jos ei whitelistattu | Enum string → SQL identifier |
| selain → delete-käsittelijä (6 kohdetta) | Epäluotettu POST (id, csrf_token) ylittää tähän | Session-authenticated POST |
| author → `post_delete.php` | Author saattaa yrittää poistaa toisen omistaman postauksen (IDOR) | POST body → ownership check |
| mod → delete-käsittelijä | Mod:n poiston on luotava pending-rivi, ei suoraa lopullista poistoa | Role-branched soft-delete |
| selain → approve/reject-käsittelijä | Epäluotettu POST (id, csrf_token) | Session-authenticated POST |
| ei-admin → `deletions.php` | Mod/author eivät saa nähdä eivätkä ratkaista pyyntöjä | Role-gated page access |
| julkinen kävijä → sivupyyntö | Soft-deletetty sisältö ei saa vuotaa suoralla URL:lla | HTTP GET → SQL query filter |

---

## Threat Register

| Threat ID | Category | Component | Severity | Disposition | Mitigation | Status |
|-----------|----------|-----------|----------|-------------|------------|--------|
| T-13-01 | Tampering | `entityTypeToTable()` taulunimen johto | high | mitigate | `match()`-whitelist, `default` heittää poikkeuksen. Verified: live PHP exercise inside container confirmed whitelist + exception-on-invalid-input (13-VERIFICATION.md). | closed |
| T-13-02 | Injection | `insertPendingDeletion()` SQL | high | mitigate | Pelkät prepared statementit, ei string-konkatenaatiota parametreille. Verified: direct code read (`helpers.php`). | closed |
| T-13-03 | Spoofing | `requested_by`-identiteetti | medium | mitigate | Kutsusopimus: `requested_by` aina `(int)$_SESSION['admin_id']` kaikissa 6 kutsukohdassa. Verified: grep across all delete sites. | closed |
| T-13-04 | Tampering | Duplikaattirivit pending-jonossa | low | accept | PHP-tason check-then-insert (D-03); jäljelle jäävä kilpailutilanne hyväksytään yhden tallin matalalla volyymilla. | closed |
| T-13-05 | Elevation of Privilege | `post_delete.php` (author IDOR) | high | mitigate | `requireOwnResourceOrAdmin()` omistajuustarkistus ennen soft-deletea. Verified: code present at line 29, exercised in 13-VERIFICATION.md. | closed |
| T-13-06 | Spoofing | CSRF kaikilla 6 delete-POST-käsittelijöillä | high | mitigate | `validate_csrf_token()` jokaisessa käsittelijässä. Verified: grep confirms all 6 files (`horse_delete.php`, `post_delete.php`, `foals.php`, `competitions.php`, `showrecords.php`, `kasvatus_all.php`). | closed |
| T-13-07 | Elevation of Privilege | Mod ohittaa hyväksynnän suoralla poistolla | high | mitigate | Mod-haara ei koskaan tee lopullista poistoa; luo pending-rivin, admin hyväksyy erikseen. Verified: code trace + human UAT test 3 (approve/reject click-through). | closed |
| T-13-08 | Tampering | Tuplasoft-delete / tuplapending | low | mitigate | `WHERE is_deleted = 0` -guard + `insertPendingDeletion()`-dedup. Strengthened post-review: code review CR-01 fix (commit bda578e) added `rowCount() > 0` guard on all 6 sites, closing a phantom-pending-row edge case beyond the original plan scope. | closed |
| T-13-09 | Elevation of Privilege | `deletions.php`/approve/reject pääsy | high | mitigate | `requireRole('admin')` kaikilla kolmella tiedostolla. Verified: grep confirms all 3 files; human UAT test 2 confirmed mod/author don't see nav link. | closed |
| T-13-10 | Injection | `deletion_reject.php` taulunimen johto | high | mitigate | `entityTypeToTable()`-whitelist, backtick-lainaus, ei syötettä suoraan SQL:ään. Verified: 13-VERIFICATION.md `git show` diff inspection. | closed |
| T-13-11 | Spoofing | CSRF approve/reject-POST | high | mitigate | `validate_csrf_token()` molemmissa. Verified: grep confirms `deletion_approve.php` and `deletion_reject.php`. | closed |
| T-13-12 | Tampering | Osittainen kirjoitus hylkäyksessä | medium | mitigate | PDO-transaktio (`beginTransaction`/`commit`) atomisoi sisällön palautuksen + jonon tilamuutoksen. Verified: code confirms transaction wrapping. Note: WR-02 (13-REVIEW.md, open) flags missing explicit try/catch/rollback — MySQL implicitly rolls back on connection close so data stays consistent, but the admin sees a raw error page on failure; tracked as a non-blocking robustness follow-up, not a security gap. | closed |
| T-13-13 | Repudiation | Kuka ratkaisi pyynnön | low | accept | `reviewed_by`/`reviewed_at` täytetään sessioidentiteetistä; riittää yhden tallin tarpeeseen. | closed |
| T-13-14 | Information Disclosure | Soft-deletetty sisältö näkyy julkisella sivulla | high | mitigate | Kattava `is_deleted = 0` -audit-passi molempien teemojen ja kaikkien admin-listojen kyselyissä (10 tiedostoa). Verified: file-by-file confirmation in 13-VERIFICATION.md, including an operator-precedence bug fix in the pedigree query. | closed |
| T-13-15 | Information Disclosure | Soft-deletetty postaus suoralla slug/id-URL:lla | medium | mitigate | `is_deleted = 0` myös single-post-hakukyselyissä. Verified: grep confirms `postaus.php` slug (line 18) and id (line 22) lookups both filter, plus prev/next navigation queries. | closed |

*Status: open · closed · open — below high threshold (non-blocking)*
*Severity: critical > high > medium > low — only open threats at or above workflow.security_block_on (high) count toward threats_open*
*Disposition: mitigate (implementation required) · accept (documented risk) · transfer (third-party)*

---

## Accepted Risks Log

| Risk ID | Threat Ref | Rationale | Accepted By | Date |
|---------|------------|-----------|-------------|------|
| AR-13-01 | T-13-04 | PHP-level check-then-insert has a narrow race window under concurrent duplicate submissions; accepted given single-stable, low-concurrency usage pattern. | Plan (13-01) | 2026-07-18 |
| AR-13-02 | T-13-13 | No dedicated audit-log entity beyond `pending_deletions.reviewed_by`/`reviewed_at`; sufficient for a single-admin/small-team stable. | Plan (13-03) | 2026-07-18 |

*Accepted risks do not resurface in future audit runs.*

---

## Security Audit Trail

| Audit Date | Threats Total | Closed | Open | Run By |
|------------|---------------|--------|------|--------|
| 2026-07-18 | 15 | 15 | 0 | Claude (gsd-secure-phase, orchestrator classification — L1/ASVS-1 short-circuit, no auditor spawn needed) |

---

## Sign-Off

- [x] All threats have a disposition (mitigate / accept / transfer)
- [x] Accepted risks documented in Accepted Risks Log
- [x] `threats_open: 0` confirmed
- [x] `status: verified` set in frontmatter

**Approval:** verified 2026-07-18
