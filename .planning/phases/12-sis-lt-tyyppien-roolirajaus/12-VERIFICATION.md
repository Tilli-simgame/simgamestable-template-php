---
phase: 12-sis-lt-tyyppien-roolirajaus
verified: 2026-07-17T07:03:02Z
status: passed
score: 12/12 must-haves verified
behavior_unverified: 0
overrides_applied: 0
human_verification:

  - test: "Harvested from 12-02-PLAN.md Task 3 <human-check> (auto task, deferred UAT): (a) Log in as an author user — posts.php list shows only that author's own posts. (b) As that author, open posts.php?action=edit&id=X for another author's post via direct URL — redirected to ei-oikeutta.php. (c) As that author, submit a crafted POST to posts.php with edit_id set to another author's post ID — post is NOT updated, redirected to ei-oikeutta.php before the UPDATE runs. (d) Log in as admin and separately as mod — both see and can edit ALL posts (no ownership restriction). (e) As mod and as author, attempt to open users.php and settings.php via direct URL — both redirect to ei-oikeutta.php."
    expected: "All five behaviors hold exactly as described; no author can view/edit/enumerate another author's post, admin/mod remain unrestricted, and mod/author cannot reach users.php/settings.php."
    why_human: "Session-based, role-switching runtime behavior (logging in as different roles and observing server-side redirects) cannot be reliably driven from this executor's non-interactive shell context. Static code trace (below) independently confirms the logic is correct and deterministic, but a live pass exercises the actual session/cookie/redirect path end-to-end, which the plan explicitly deferred to gsd-verify-work."
---

# Phase 12: Sisältötyyppien roolirajaus Verification Report

**Phase Goal:** Mod voi ylläpitää tallin sisältöä (hevoset, varsat, kilpailut, näyttelyt, postaukset) omalla roolillaan; author voi ylläpitää vain omia postauksiaan ja linkittää niihin olemassa olevia hevosia; kumpikaan ei pääse käyttäjähallintaan eikä teema-asetuksiin.
**Verified:** 2026-07-17T07:03:02Z
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Mod voi luoda ja muokata hevosen (+kuvat), varsan, kilpailun, näyttelytuloksen (SC1, MOD-01..04) | ✓ VERIFIED | `grep requireRole` confirms `requireRole('admin', 'mod')` on `horses.php`, `horse_add.php`, `horse_edit.php`, `foals.php`, `foal_add.php`, `foal_edit.php`, `competitions.php`, `showrecords.php`, `photos.php`, `photo_update.php`, `horse_import_vrl.php`. Read `horse_add.php`/`foal_add.php` — substantive INSERT/UPDATE logic, not stubs (pre-existing from Phase 10, re-confirmed unchanged). |
| 2 | Mod voi luoda ja muokata postauksen (SC2, MOD-05) | ✓ VERIFIED | `posts.php:3` — `requireRole('admin', 'mod', 'author')`; mod hits the unfiltered `else` branch (list) and INSERT/UPDATE with no ownership restriction (code Level 3). |
| 3 | Mod ja author eivät pääse käyttäjähallinta-/teema-asetussivuille suoralla osoitteella (SC5, MOD-07/AUTHOR-05) | ✓ VERIFIED | `grep requireRole` — `users.php`, `settings.php`, `user_add.php`, `user_edit.php`, `user_delete.php`, `user_reset_password.php`, `user_toggle_active.php` all gate `requireRole('admin')`. Unchanged from Phase 10 (D-04 regression pass confirmed, see grep evidence below). |
| 4 | Author voi luoda uuden postauksen (AUTHOR-01) | ✓ VERIFIED | `posts.php:3` allows `author`; INSERT branch (`posts.php:53-54`) runs for any role, sets `author_id`. |
| 5 | Author voi muokata vain omia postauksiaan; lista suodattuu (AUTHOR-02, D-03) | ✓ VERIFIED | `posts.php:119-122` — `if (currentRole() === 'author') { ... WHERE author_id = :aid ... }` else unfiltered query for admin/mod. |
| 6 | Author joka avaa toisen postauksen `action=edit&id=X` ohjautuu `ei-oikeutta.php`:hen (SC3) | ✓ VERIFIED (code trace) | `posts.php:83-86` — `requireOwnResourceOrAdmin((int)$editPost['author_id'])` called immediately after fetch, before form pre-fill. `helpers.php:86-90` — function redirects to `SITE_URL . '/admin/ei-oikeutta.php'` when `currentRole() === 'author'` and IDs mismatch. Also flagged for live UAT — see Human Verification. |
| 7 | Crafted POST toisen postauksen `edit_id`:llä ei päivitä postausta, ohjautuu ennen UPDATE-lausetta (IDOR defense-in-depth) | ✓ VERIFIED (code trace) | `posts.php:38-46` — `SELECT author_id FROM posts WHERE id = :id` then `requireOwnResourceOrAdmin(...)` called *before* the `UPDATE` statement on line 48. Independent code review (`12-REVIEW.md`) reached the same conclusion: "I did not find a way for an author to read, modify, or enumerate another author's post content." Also flagged for live UAT — see Human Verification. |
| 8 | `posts.author_id` sarake olemassa, FK `fk_posts_author` → `admin_users.id`, backfill 0 NULL-riviä (D-01) | ✓ VERIFIED | Live DB query against `virtuaalitalli-db`: `SHOW COLUMNS FROM posts LIKE 'author_id'` → row exists; `SELECT COUNT(*) FROM posts WHERE author_id IS NULL` → `0`; `KEY_COLUMN_USAGE` count for `fk_posts_author` → `1`. |
| 9 | `database/schema.sql` posts-lohko peilaa `author_id` fresh-install-polkua varten | ✓ VERIFIED | `grep -n author_id database/schema.sql` → lines 266 (column) and 271 (`CONSTRAINT fk_posts_author ... REFERENCES admin_users (id) ON DELETE SET NULL`). |
| 10 | Jokainen uusi postaus tallentuu `author_id = $_SESSION['admin_id']` riippumatta roolista (D-02) | ✓ VERIFIED | `posts.php:53-54` — `INSERT INTO posts (title, slug, content, author_id) VALUES (...)`, bound to `$_SESSION['admin_id']`, no role branch. |
| 11 | Admin ja mod näkevät ja voivat muokata KAIKKIA postauksia ilman omistajuusrajausta | ✓ VERIFIED | `helpers.php:87` — guard only fires `currentRole() === 'author'`; `posts.php:123-125` — else branch is the unfiltered `SELECT ... FROM posts ORDER BY created_at DESC`. |
| 12 | Author voi linkittää olemassa olevia hevosia postaukseensa muttei muokata hevostietoja (AUTHOR-04) | ✓ VERIFIED | `posts.php:107-131, 254-407` — horse-link widget unchanged from pre-Phase-12 state, selects from `horses` table read-only (no horse INSERT/UPDATE reachable from `posts.php`), confirmed role-neutral. |

**Score:** 12/12 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `database/migrate_posts_author.sql` | ADD COLUMN author_id + FK fk_posts_author + backfill UPDATE | ✓ VERIFIED | File exists, contains all three elements, phpMyAdmin import header present, no debt markers. |
| `database/schema.sql` (posts block) | Mirrors author_id + FK for fresh installs | ✓ VERIFIED | Lines 266, 271 confirmed. |
| `public/src/includes/helpers.php` — `requireOwnResourceOrAdmin()` | Ownership guard, mirrors `requireRole()` convention | ✓ VERIFIED | Function present (lines 86-90), `php -l` passes, redirects to `ei-oikeutta.php`. |
| `public/admin/posts.php` | Ownership logic: INSERT author_id, list filter, GET/POST ownership checks | ✓ VERIFIED | All four changes present (INSERT, POST-UPDATE guard, GET-edit guard, list filter); `php -l` passes. |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `posts.php` (author-role redirect path) | `public/admin/ei-oikeutta.php` | `redirect(SITE_URL . '/admin/ei-oikeutta.php')` inside `requireOwnResourceOrAdmin()` | ✓ WIRED | Same redirect target used by `requireRole()`, file exists at `public/admin/ei-oikeutta.php`. |
| `posts.php` GET edit / POST update | `requireOwnResourceOrAdmin()` (`helpers.php`) | Direct function call, ≥2 call sites | ✓ WIRED | `grep -c requireOwnResourceOrAdmin public/admin/posts.php` → 2 (GET branch line 86, POST branch line 45). |
| `posts.author_id` (12-01) | `posts.php` INSERT/SELECT/UPDATE | Column consumed in bound queries | ✓ WIRED | INSERT (`:aid`), list SELECT (`WHERE author_id`), ownership SELECT (`SELECT author_id FROM posts WHERE id = :id`). |
| `currentRole()` / `$_SESSION['admin_id']` (`helpers.php`) | Ownership comparisons in `posts.php` + `requireOwnResourceOrAdmin()` | Session-sourced identity, never POST-supplied | ✓ WIRED | Confirmed identity is derived from `$_SESSION['admin_id']` at every comparison site (INSERT, GET guard, POST guard). |

### D-04 Regression Grep Evidence (Phase 10 role gating unchanged)

```
=== Content pages (mod-allowed) ===
horses.php, horse_add.php, horse_edit.php, foals.php, foal_add.php, foal_edit.php,
competitions.php, showrecords.php, photos.php, photo_update.php, horse_import_vrl.php
  → requireRole('admin', 'mod')   [confirmed via live grep, matches SUMMARY]

=== posts.php (all three roles) ===
posts.php → requireRole('admin', 'mod', 'author')

=== admin-only (users/settings + user_* family) ===
users.php, settings.php, user_add.php, user_edit.php, user_delete.php,
user_reset_password.php, user_toggle_active.php → requireRole('admin')
```

No regression found — matches Phase 10's established gating exactly (re-verified independently, not just trusted from SUMMARY).

Note: `competitions.php:74`, `foals.php:123`, `showrecords.php:101` additionally gate their inline `delete` sub-actions with `requireRole('admin')` — this is pre-existing Phase 10 behavior (mod has create/edit but not delete on these resource types, consistent with MOD-06 being deferred to Phase 13), not a Phase 12 change or regression.

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|--------------|------------|-------------|--------|----------|
| MOD-01 | 12-02 | Mod voi luoda ja muokata hevosia sekä niiden kuvia | ✓ SATISFIED | `requireRole('admin','mod')` on horses.php/horse_add.php/horse_edit.php/photos.php/photo_update.php |
| MOD-02 | 12-02 | Mod voi luoda ja muokata varsamerkintöjä | ✓ SATISFIED | `requireRole('admin','mod')` on foals.php/foal_add.php/foal_edit.php |
| MOD-03 | 12-02 | Mod voi luoda ja muokata kilpailuja | ✓ SATISFIED | `requireRole('admin','mod')` on competitions.php |
| MOD-04 | 12-02 | Mod voi luoda ja muokata näyttelytuloksia | ✓ SATISFIED | `requireRole('admin','mod')` on showrecords.php |
| MOD-05 | 12-02 | Mod voi luoda ja muokata postauksia | ✓ SATISFIED | `requireRole('admin','mod','author')` on posts.php, unfiltered for mod |
| MOD-07 | 12-02 | Mod-roolilla ei ole pääsyä käyttäjähallintaan/teema-asetuksiin | ✓ SATISFIED | `requireRole('admin')` on users.php/settings.php/user_* family |
| AUTHOR-01 | 12-02 | Author-rooli voi luoda uuden postauksen | ✓ SATISFIED | posts.php allows author, INSERT sets author_id |
| AUTHOR-02 | 12-01, 12-02 | Author-rooli voi muokata vain omia postauksiaan | ✓ SATISFIED | Ownership guard on GET/POST + list filter |
| AUTHOR-04 | 12-01, 12-02 | Author voi linkittää olemassa olevia hevosia postaukseensa | ✓ SATISFIED | Horse-link widget unchanged, role-neutral, read-only selection |
| AUTHOR-05 | 12-02 | Author-roolilla ei ole pääsyä muihin admin-toimintoihin | ✓ SATISFIED | requireRole('admin') on users/settings/horses/foals/competitions/showrecords family excludes author |

No orphaned requirements: `.planning/REQUIREMENTS.md` traceability table maps exactly MOD-01,02,03,04,05,07 and AUTHOR-01,02,04,05 to Phase 12 — all ten appear in the union of both plans' `requirements` frontmatter. `MOD-06`, `AUTHOR-03`, and `DEL-01..05` are explicitly and correctly deferred to Phase 13 per the traceability note (dependent on the `pending_deletions` schema not yet built).

### Anti-Patterns Found

None. Scanned `public/admin/posts.php`, `public/src/includes/helpers.php`, `database/migrate_posts_author.sql`, `database/schema.sql` for `TBD|FIXME|XXX|TODO|HACK|PLACEHOLDER` — zero matches. No empty implementations, no hardcoded-empty stubs in the touched code paths.

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| posts.author_id column exists in dev DB | `docker exec virtuaalitalli-db mysql ... SHOW COLUMNS FROM posts LIKE 'author_id'` | `author_id int unsigned YES MUL NULL` | ✓ PASS |
| Backfill complete (0 NULL author_id) | `docker exec virtuaalitalli-db mysql ... SELECT COUNT(*) FROM posts WHERE author_id IS NULL` | `0` | ✓ PASS |
| FK fk_posts_author active | `docker exec virtuaalitalli-db mysql ... KEY_COLUMN_USAGE COUNT` | `1` | ✓ PASS |
| posts.php PHP syntax valid | `docker exec virtuaalitalli-web php -l /var/www/html/admin/posts.php` | "No syntax errors detected" | ✓ PASS |
| helpers.php PHP syntax valid | `docker exec virtuaalitalli-web php -l /var/www/html/src/includes/helpers.php` | "No syntax errors detected" | ✓ PASS |

Session-dependent behaviors (login-as-role, live redirect verification) cannot be exercised from this non-interactive shell — routed to Human Verification below (also independently confirmed via static code trace).

### Human Verification Required

### 1. Manual UAT checklist (a)-(e) — harvested from 12-02-PLAN.md Task 3 `<human-check>`

**Test:** (a) Log in as `author` → posts.php list shows only own posts. (b) As author, open `posts.php?action=edit&id=X` for another author's post via direct URL. (c) As author, send a crafted POST to `posts.php` with another author's `edit_id`. (d) Log in as `admin` and separately as `mod` → both see/edit ALL posts. (e) As `mod` and `author`, attempt `users.php`/`settings.php` via direct URL.

**Expected:** (a) only own posts listed; (b) and (c) redirect to `ei-oikeutta.php` with no data change; (d) unrestricted admin/mod access; (e) redirect to `ei-oikeutta.php`.

**Why human:** Session-based, role-switching runtime behavior cannot be reliably driven from this executor's non-interactive shell. Static code trace (Observable Truths #6, #7 above) and an independent code review pass (`12-REVIEW.md`) both confirm the logic is correct and deterministic, but this was explicitly deferred by the plan to a live UAT pass in `gsd-verify-work`.

### Notes on Code Review Findings (12-REVIEW.md)

A prior code review (`12-REVIEW.md`, `status: issues_found`, 0 critical / 4 warning / 3 info) flagged several non-blocking robustness gaps, none of which contradict the phase's must-have truths or the goal:

- **WR-01** (posts.php POST-edit path silently no-ops on a non-existent `edit_id` instead of erroring like the GET path) — a logic/UX inconsistency, not an ownership bypass; does not affect any verified truth above (all truths concern *existing* rows).
- **WR-02** (migration backfill silently no-ops if no `admin_users.username = 'admin'` row exists) — current dev DB backfill is verified as `0` NULL rows, so not currently manifesting; a fragility note for other install targets.
- **WR-03** (posts list still renders a "Poista" button for `mod`/`author` even though `post_delete.php` is `admin`-only) — a UX rough edge consistent with `MOD-06`/`AUTHOR-03` being intentionally deferred to Phase 13's approval workflow; not a goal-blocking defect for Phase 12 (delete-workflow scope is explicitly Phase 13's).
- **WR-04** (`requireOwnResourceOrAdmin()`'s `?? 0` default could theoretically fail open if `$_SESSION['admin_id']` were ever unset while `currentRole()` still returned `'author'`) — reviewer confirms this path is not currently reachable given `login.php`'s session-setting invariants.

These are legitimate quality follow-ups but do not block the phase goal — recorded here for visibility, not as gaps.

### Gaps Summary

No gaps found. All must-have truths, artifacts, and key links are present, substantive, and wired. The phase goal — mod can maintain full stable content (horses, foals, competitions, shows, posts) under its own role; author can maintain only its own posts and link existing horses; neither role can reach user management or theme settings — is achieved in the codebase, independently confirmed via live DB queries, `php -l`, grep-based wiring checks, direct code reads, and a prior independent code review. One harvested manual UAT checklist from the plan remains outstanding for a live session-based pass (routes phase status to `human_needed`, not `gaps_found`).

---

_Verified: 2026-07-17T07:03:02Z_
_Verifier: Claude (gsd-verifier)_
