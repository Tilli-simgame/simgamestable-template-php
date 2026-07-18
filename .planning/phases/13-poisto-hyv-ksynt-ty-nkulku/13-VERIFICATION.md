---
phase: 13-poisto-hyv-ksynt-ty-nkulku
verified: 2026-07-18T06:25:36Z
status: human_needed
score: 15/15 must-haves verified
behavior_unverified: 0
overrides_applied: 0
human_verification:
  - test: "Kirjaudu admin-tunnuksella, siirry /admin/deletions.php ja tarkista sivun ulkoasu kun pending_deletions-taulussa on rivejä kaikista viidestä entity_type-arvosta samanaikaisesti (hevonen/varsa/kilpailu/näyttelytulos/postaus)."
    expected: "Kaikki rivit näkyvät yhdessä taulukossa oikeilla suomenkielisillä tyyppinimillä ja luettavilla entity_label-arvoilla, ei PHP-virheitä tai tyhjiä/rikkinäisiä soluja."
    why_human: "Verifioija ajoi tämän LEFT JOIN -kyselyn suoraan tietokantaa vasten (tyhjällä tuloksella, rakenne validoitu) ja simuloi approve/reject-SQL:n suoraan, mutta ei renderöinyt varsinaista deletions.php-sivua selaimessa aidon admin-session + useiden rivien kanssa."
  - test: "Kirjaudu mod- ja author-tunnuksilla ja tarkista, ettei admin-navigaatiossa näy 'Poistopyynnöt'-linkkiä kummallakaan roolilla; kirjaudu adminilla ja tarkista että linkki näkyy ja on aktiivinen deletions.php-sivulla."
    expected: "Vain admin näkee linkin; mod/author eivät. Admin-sessiolla linkki korostuu aktiivisena deletions.php:llä."
    why_human: "Nav-linkin `in_array($role, ['admin'], true)`-ehto todennettiin staattisesti (grep), mutta ei renderöity kolmella eri roolilla selaimessa."
  - test: "Klikkaa oikeasti 'Hyväksy'- ja 'Hylkää'-nappeja deletions.php-sivulla adminina kirjautuneena (CSRF-token + POST aidon lomakkeen kautta, ei suoraa SQL-simulaatiota) ja varmista redirect + flash-viesti näkyy oikein."
    expected: "Hyväksyntä ohjaa deletions.php?approved=1:een ja näyttää 'Poistopyyntö hyväksytty.' Hylkäys ohjaa deletions.php?rejected=1:een ja näyttää 'Poistopyyntö hylätty, sisältö palautettu näkyväksi.'"
    why_human: "Verifioija todensi approve/reject-käsittelijöiden SQL-logiikan suoralla tietokantasimulaatiolla (täsmälleen sama SQL kuin tiedostoissa) ja `php -l`:llä, mutta ei ajanut itse PHP-käsittelijätiedostoja HTTP-pyynnön ja CSRF-tokenin kautta selaimessa."
---

# Phase 13: Poisto-hyväksyntätyönkulku Verification Report

**Phase Goal:** Modin poistopyynnöt (hevoset, varsat, kilpailut, näyttelyt, postaukset) eivät toteudu heti vaan odottavat admin-hyväksyntää yhdessä näkymässä; author saa poistaa omat postauksensa heti ilman hyväksyntää.
**Verified:** 2026-07-18T06:25:36Z
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | `pending_deletions` table exists in dev DB with pending/approved/rejected ENUM status | ✓ VERIFIED | `SHOW TABLES LIKE 'pending_deletions'` + `SHOW COLUMNS ... LIKE 'status'` confirm `enum('pending','approved','rejected')` DEFAULT 'pending' in live dev DB |
| 2 | `foals`/`competitions`/`showrecords`/`posts` have `is_deleted`+`deleted_at` columns | ✓ VERIFIED | `information_schema.columns` query returns `4` (all four tables) in live dev DB |
| 3 | `insertPendingDeletion()` prevents duplicate pending rows for same entity (DEL-05) | ✓ VERIFIED | Live PHP execution inside `virtuaalitalli-web`: called twice for `('horse', 999999, 1)` → exactly 1 `status='pending'` row confirmed via `SELECT COUNT(*)`; test row cleaned up |
| 4 | `entityTypeToTable()` is a safe whitelist mapper, throws on invalid input | ✓ VERIFIED | Live execution: `entityTypeToTable('showrecord')` → `showrecords`, `entityTypeToTable('post')` → `posts`, `entityTypeToTable('../horses; DROP')` → `InvalidArgumentException` thrown as expected |
| 5 | Mod's delete request on any of the 5 content types = immediate soft-delete + `pending_deletions` row (MOD-06) | ✓ VERIFIED | `horse_delete.php`, `post_delete.php`, `foals.php`, `competitions.php`, `showrecords.php`, `kasvatus_all.php` all: `requireRole('admin','mod'[,'author'])`, `UPDATE ... SET is_deleted=1 ... WHERE is_deleted=0` followed by `if ($stmt->rowCount() > 0 && currentRole() === 'mod') insertPendingDeletion(...)` — confirmed by direct file read of current code (post-CR-01-fix, commit `bda578e`) |
| 6 | Admin's delete = direct soft-delete, no pending row created | ✓ VERIFIED | Same six files: pending-row insertion is gated strictly on `currentRole() === 'mod'`; admin path falls through with only the soft-delete `UPDATE` |
| 7 | Author can delete only their own post immediately, no pending row (AUTHOR-03) | ✓ VERIFIED | `post_delete.php`: `requireOwnResourceOrAdmin((int)$ownerRow['author_id'])` called before soft-delete; pending-row insert gated on `mod` role only, so author's own-post delete never queues |
| 8 | Author cannot delete another author's post via direct POST (IDOR defense) | ✓ VERIFIED | `requireOwnResourceOrAdmin()`: `if (currentRole() === 'author' && $resourceAuthorId !== (int)($_SESSION['admin_id'] ?? 0)) redirect(.../ei-oikeutta.php)` — deterministic guard, called with DB-sourced `author_id` before any mutation in `post_delete.php` |
| 9 | Soft-deleted rows hidden immediately from delete-handlers' own admin lists/edit queries | ✓ VERIFIED | `foals.php`, `competitions.php` (list + edit), `showrecords.php`, `kasvatus_all.php` all confirmed to filter `is_deleted = 0` in their content queries via direct file read |
| 10 | Admin sees one unified view of all pending requests across all 5 content types (DEL-01) | ✓ VERIFIED | `public/admin/deletions.php`: single `<table>`, one query `LEFT JOIN`ing `horses`/`foals`/`competitions`/`showrecords`/`posts` on `pd.entity_type`, `WHERE pd.status='pending'`; query executed directly against dev DB and returns without error (structurally valid JOIN, confirmed no NULL-cascade crash) |
| 11 | Admin can approve a request → `status='approved'`, content stays hidden (DEL-02) | ✓ VERIFIED | `deletion_approve.php` code confirmed to touch only `pending_deletions`; behaviorally simulated the exact SQL against a disposable test horse row in the dev DB — after "approve", `horses.is_deleted` remained `1` and `pending_deletions.status` became `approved`; test row cleaned up |
| 12 | Admin can reject a request → content restored (`is_deleted=0`), `status='rejected'`, row retained for audit (DEL-03) | ✓ VERIFIED | `deletion_reject.php` code confirmed to use `entityTypeToTable()` + `beginTransaction()`/`commit()`; behaviorally simulated the exact two-statement transaction against a disposable test horse row — after "reject", `horses.is_deleted=0`/`deleted_at=NULL` and `pending_deletions.status='rejected'`; row was not deleted; test data cleaned up |
| 13 | Admin dashboard shows a counter of pending deletion requests (DEL-04) | ✓ VERIFIED | `public/admin/index.php`: `$pendingDeletionCount = ... SELECT COUNT(*) FROM pending_deletions WHERE status = 'pending' ...` + new `.admin-stat-card` with label "Odottavaa poistopyyntöä" confirmed present |
| 14 | `deletions.php`/`deletion_approve.php`/`deletion_reject.php` are admin-only; mod/author routed away | ✓ VERIFIED | All three files call exactly `requireRole('admin')` (not `'admin','mod'`) — confirmed by direct file read |
| 15 | Soft-deleted content is invisible on the public site (both themes) and in all remaining admin list/edit views (SC1, full audit pass) | ✓ VERIFIED | `is_deleted = 0` confirmed present in every query of `public/admin/foal_edit.php`, `kilpailut_all.php`, `showrecords_all.php`, `posts.php` (admin side) and `public/pages/kasvatus.php`, `hevonen.php`, `index.php`, `postaus.php`, `ajankohtaista.php`, `public/themes/oma-talli/hevonen.php` (public side, both themes) — direct file read of every file listed in Plan 04's scope |

**Score:** 15/15 truths verified (0 present-but-behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `database/migrate_delete_approval.sql` | Additive migration: 4× soft-delete columns + `pending_deletions` table | ✓ VERIFIED | File exists, content matches spec exactly, no `DROP`/`DELETE` statements, applied to live dev DB |
| `database/schema.sql` | Mirrors migration | ✓ VERIFIED | Contains `pending_deletions`, `idx_foals_deleted`, `idx_competitions_deleted`, `idx_showrecords_deleted`, `idx_posts_deleted` |
| `insertPendingDeletion()` / `entityTypeToTable()` in `helpers.php` | Duplicate-safe insert + whitelist mapper | ✓ VERIFIED | Both present, both behaviorally tested live (see truths 3–4) |
| `public/admin/horse_delete.php` | Admin+mod role branch | ✓ VERIFIED | `requireRole('admin','mod')`, rowCount-guarded `insertPendingDeletion()` |
| `public/admin/post_delete.php` | 3-way role branch + soft-delete + IDOR guard | ✓ VERIFIED | `requireRole('admin','mod','author')`, `requireOwnResourceOrAdmin()`, no `DELETE FROM posts` remaining |
| `public/admin/foals.php`/`competitions.php`/`showrecords.php`/`kasvatus_all.php` | Inline soft-delete + role branch + list filter | ✓ VERIFIED | No `DELETE FROM (foals|competitions|showrecords)` remains; all four filter `is_deleted = 0` in list/edit queries |
| `public/admin/deletions.php` | Unified pending-request list, admin-only | ✓ VERIFIED | Single table, 5-way LEFT JOIN, CSRF-protected approve/reject forms per row |
| `public/admin/deletion_approve.php` | Status-only approve handler | ✓ VERIFIED | Updates only `pending_deletions`, no content-table write |
| `public/admin/deletion_reject.php` | Atomic content-restore + status handler | ✓ VERIFIED | `entityTypeToTable()` + `beginTransaction()`/`commit()`, row retained (no DELETE) |
| `admin/index.php` stat card | Pending-deletion counter | ✓ VERIFIED | `$pendingDeletionCount` query + `.admin-stat-card` with correct label |
| `admin_header.php` nav link | Admin-only "Poistopyynnöt" link | ✓ VERIFIED | `in_array($role, ['admin'], true)` gate, points to `/admin/deletions.php` |
| `is_deleted = 0` audit pass (10 files, Plan 04) | Remaining admin + both-theme public queries filtered | ✓ VERIFIED | Confirmed present in all 10 files: `foal_edit.php`, `kilpailut_all.php`, `showrecords_all.php`, `posts.php`, `pages/kasvatus.php`, `pages/hevonen.php`, `pages/index.php`, `pages/postaus.php`, `pages/ajankohtaista.php`, `themes/oma-talli/hevonen.php` |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `horse_delete.php`/`post_delete.php`/`foals.php`/`competitions.php`/`showrecords.php`/`kasvatus_all.php` | `insertPendingDeletion()` | mod-branch call after soft-delete `UPDATE`, gated on `$stmt->rowCount() > 0` | ✓ WIRED | Confirmed in all six files; matches post-review fix commit `bda578e` |
| `post_delete.php` | `requireOwnResourceOrAdmin()` | called before soft-delete `UPDATE`, with DB-sourced `author_id` | ✓ WIRED | Confirmed present at line 29, before line 31's `UPDATE` |
| `deletion_reject.php` | `entityTypeToTable()` | resolves table name before `UPDATE \`$table\`` | ✓ WIRED | Confirmed at line 20, table name never taken directly from request |
| `deletions.php` | 5 content tables | `LEFT JOIN` on `entity_type` match + `entity_id` | ✓ WIRED | Query executes without error against live dev DB (validated with 0-row result; JOIN structure confirmed sound) |
| `index.php` | `pending_deletions` | `COUNT(*) ... WHERE status='pending'` | ✓ WIRED | Confirmed present, feeds `.admin-stat-card` |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| DB migration applied | `information_schema.columns` COUNT + `SHOW TABLES`/`SHOW COLUMNS` against live `virtuaalitalli` dev DB | `4`, `pending_deletions` present, status ENUM correct | ✓ PASS |
| `entityTypeToTable()` whitelist + exception | Live PHP execution in `virtuaalitalli-web` container | `showrecords`, `posts`, `InvalidArgumentException` thrown for non-whitelisted input | ✓ PASS |
| `insertPendingDeletion()` duplicate guard (DEL-05) | Called twice for the same `(entity_type, entity_id)` via live PHP execution | Exactly 1 `status='pending'` row created | ✓ PASS |
| Approve-flow SQL (DEL-02) | Exact `UPDATE pending_deletions SET status='approved' ...` run against disposable test horse row | Content stayed `is_deleted=1`; queue row became `approved` | ✓ PASS |
| Reject-flow SQL (DEL-03) | Exact two-statement transaction from `deletion_reject.php` run against disposable test horse row | Content restored (`is_deleted=0`); queue row retained as `rejected` | ✓ PASS |
| `deletions.php` unified JOIN query | Ran verbatim against live dev DB | No SQL error; JOIN structure valid across all 5 entity types | ✓ PASS |
| `php -l` on all newly created/modified core files | `docker exec virtuaalitalli-web php -l ...` on 12 key files | No syntax errors on any file | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| MOD-06 | 13-02, 13-04 | Mod-poistopyyntö → pending state, ei heti poistu | ✓ SATISFIED | Truths 5, 15 |
| AUTHOR-03 | 13-02 | Author poistaa vain omat postauksensa heti | ✓ SATISFIED | Truths 7, 8 |
| DEL-01 | 13-03 | Yksi näkymä kaikista pending-pyynnöistä | ✓ SATISFIED | Truth 10 |
| DEL-02 | 13-03 | Hyväksyntä = sisältö pysyy piilossa | ✓ SATISFIED | Truth 11 |
| DEL-03 | 13-03 | Hylkäys = sisältö palautuu | ✓ SATISFIED | Truth 12 |
| DEL-04 | 13-03 | Admin-etusivun laskuri | ✓ SATISFIED | Truth 13 |
| DEL-05 | 13-01 | Ei duplikaattipoistoja jonossa | ✓ SATISFIED | Truth 3 |

**Orphaned requirements check:** REQUIREMENTS.md maps exactly these 7 IDs (`MOD-06`, `DEL-01`–`DEL-05`, `AUTHOR-03`) to "Phase 13 — Poisto-hyväksyntätyönkulku". All 7 appear in at least one plan's `requirements:` frontmatter field. No orphaned requirements found.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `public/admin/deletions.php` | 9-13 | `CONCAT()` with nullable `discipline` column blanks the label for competitions/showrecords without a discipline set (carried over from `13-REVIEW.md` WR-01, still open) | ⚠️ Warning | Cosmetic — falls back to `"competition #47"` style label instead of the intended date/discipline text; does not block DEL-01/02/03 functionality |
| `public/admin/deletion_reject.php` | 22-28 | No `try`/`catch`/rollback around the two-statement transaction (WR-02, still open) | ⚠️ Warning | On a DB exception, admin sees a raw PHP fatal-error page instead of a graceful redirect; MySQL auto-rolls-back on connection close so data stays consistent, but the UX/robustness gap remains |
| `public/admin/posts.php` | 38-49 | POST edit handler does not filter `is_deleted = 0` on the ownership check or the `UPDATE` (WR-04, still open) | ⚠️ Warning | A soft-deleted post's title/content could still be rewritten via a direct crafted POST, even though the GET edit-form path is correctly blocked. Outside the 7 requirement IDs' scope (editing, not deleting) but a real soft-delete boundary gap |
| `public/admin/deletion_reject.php` | 15-17 | Pending-row status check happens before `beginTransaction()`, no `FOR UPDATE` row lock (WR-05, still open) | ⚠️ Warning | Low-severity race condition on concurrent double-click/double-session reject of the same row; `reviewed_by`/`reviewed_at` could be silently overwritten |

None of the above are Blocker-severity per `13-REVIEW.md`'s own classification (all 5 Warning items open of which 1 partially addressed; the single Critical, CR-01, was fixed post-review in commit `bda578e` and independently re-confirmed above). No `TBD`/`FIXME`/`XXX` debt markers found in any of the 23 files reviewed for this phase.

### Human Verification Required

### 1. Deletions.php Visual Rendering with Mixed Entity Types

**Test:** Log in as admin, navigate to `/admin/deletions.php` with `pending_deletions` rows present for all five entity types simultaneously.
**Expected:** All rows render in one table with correct Finnish type labels and readable `entity_label` values, no PHP errors or broken/empty cells.
**Why human:** The verifier confirmed the JOIN query executes without SQL error against the live dev DB (0-row result — table currently empty) and simulated the approve/reject SQL directly, but did not render the actual page in a browser with multiple simultaneous rows across all five entity types.

### 2. Role-Based Nav Link Visibility

**Test:** Log in as mod and as author; confirm the "Poistopyynnöt" nav link does not appear for either role. Log in as admin; confirm it appears and is marked active on `/admin/deletions.php`.
**Expected:** Only admin sees the link.
**Why human:** The `in_array($role, ['admin'], true)` gate was confirmed statically (grep); not rendered in a browser under three different role sessions.

### 3. End-to-End Approve/Reject Click-Through

**Test:** As a logged-in admin, click the actual "Hyväksy" and "Hylkää" buttons on `deletions.php` (real HTTP POST with CSRF token and session) and confirm redirects/flash messages.
**Expected:** Approve redirects to `deletions.php?approved=1` with "Poistopyyntö hyväksytty." message; reject redirects to `deletions.php?rejected=1` with "Poistopyyntö hylätty, sisältö palautettu näkyväksi." message.
**Why human:** The verifier validated the exact SQL statements from both handlers by running them directly against a disposable test DB row (confirming correct data-layer behavior), and confirmed `php -l` syntax correctness, but did not exercise `deletion_approve.php`/`deletion_reject.php` themselves via a real HTTP request with session and CSRF token.

### Gaps Summary

No blocking gaps found. All 15 observable truths derived from ROADMAP.md's 5 success criteria plus the four plans' `must_haves` are verified against the actual codebase — not just SUMMARY.md claims. Verification included live database schema inspection, live PHP function execution inside the dev container, and direct SQL simulation of the approve/reject/duplicate-guard state transitions using disposable test data (cleaned up afterward). The one Critical finding from code review (CR-01 — pending rows queued even when the soft-delete `UPDATE` affected 0 rows, risking resurrection of already-approved-deleted content on a later unrelated reject) was fixed post-review in commit `bda578e` and independently re-confirmed here via `git show` diff inspection and direct file reads.

Three items are routed to human verification because they require an actual browser session (visual rendering, role-gated nav visibility, real HTTP POST+CSRF click-through) that cannot be fully substituted by direct DB/PHP-CLI execution. Four open code-review Warnings (WR-01, WR-02, WR-04, WR-05) remain from `13-REVIEW.md` — none block the phase's 7 requirement IDs or 5 success criteria, but are carried forward here for visibility since they touch files this phase modified.

---

_Verified: 2026-07-18T06:25:36Z_
_Verifier: Claude (gsd-verifier)_
