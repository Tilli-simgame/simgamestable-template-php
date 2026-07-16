---
phase: 10-roolit-ja-autentikaation-perusta
verified: 2026-07-16T00:00:00Z
status: passed
score: 4/4 must-haves code-verified (3 of these routed to human_verification pending pending manual DB migration)
behavior_unverified: 3
overrides_applied: 0
human_verification:

  - test: "Aja database/migrate_roles.sql phpMyAdminissa (localhost:8080 -> tietokanta -> Import), sitten DESCRIBE admin_users; ja SELECT role, is_active FROM admin_users WHERE username='admin';"
    expected: "admin_users saa role ENUM('admin','mod','author') ja is_active TINYINT(1) -sarakkeet; admin-tunnus role='admin', is_active=1. Kirjaudu admin-tunnuksella -> $_SESSION['admin_role']='admin' kirjoittuu ja kirjautuminen onnistuu."
    why_human: "Tämä koodikanta ei sisällä DB-migraatiotyökalua eikä testikehystä (VALIDATION.md) — migraatio ajetaan käsin phpMyAdminin Import-toiminnolla, ja login.php:n SELECT role, is_active hakee jo näitä sarakkeita, joten kirjautuminen palauttaa tällä hetkellä SQL-virheen kunnes migraatio on ajettu. Vahvistettu suoraan livetietokannasta tämän verifioinnin aikana: DESCRIBE admin_users palautti VAIN id/username/password/created_at — role/is_active puuttuvat vielä."

  - test: "Rooli-flip-testi (Wave 0 -menettely, 10-02-PLAN.md/10-03-PLAN.md verification-osiot): UPDATE admin_users SET role='mod' WHERE username='admin'; kirjaudu ulos/sisään; yritä avata settings.php ja POST action=delete foals.php/kasvatus_all.php/competitions.php/showrecords.php-tiedostoihin suoralla osoitteella. Toista role='author'-arvolla admin-only- ja admin+mod-sivuille. Palauta role='admin' lopuksi."
    expected: "mod-roolilla: settings.php ja delete-POSTit ohjautuvat admin/ei-oikeutta.php:hen, riviä ei poisteta. author-roolilla: vain index.php/posts.php avautuvat, loput ohjautuvat ei-oikeutta.php:hen."
    why_human: "Vaatii elävän selainsession ja phpMyAdminin roolinvaihdon — ei automatisoitavissa staattisella koodianalyysillä. requireRole()-kutsujen sijainti/roolilistat on staattisesti todennettu koodista (ks. Key Link Verification), mutta itse redirect-käyttäytymistä ei voida ajaa ilman migraation jälkeistä live-kirjautumista."

  - test: "change_password.php:n täysi POST-flow selaimessa: väärä nykyinen salasana, <8 merkin uusi, täsmäämätön vahvistus (kolme negatiivitapausta), sekä onnistunut vaihto -> kirjaudu ulos ja sisään uudella salasanalla."
    expected: "Kolme negatiivitapausta torjutaan inline-virheellä ilman UPDATE:a; onnistunut vaihto näyttää 'Salasana vaihdettu onnistuneesti.', käyttäjä pysyy kirjautuneena, ja uudella salasanalla kirjautuminen onnistuu heti."
    why_human: "Vaatii live-tietokannan (migraation jälkeen, koska requireRole()-gate change_password.php:ssä edellyttää toimivaa admin_role-sessiota) ja selainkäyttöä. Koodin kontrollivuo on staattisesti todennettu (kaikki virhehaarat estävät UPDATE:n ehdollisen $errors-tarkistuksen kautta; session_regenerate_id(true) sijaitsee heti UPDATE:n jälkeen) mutta ajonaikaista todistetta ei ole."
---

# Phase 10: Roolit ja autentikaation perusta Verification Report

**Phase Goal:** Kolme roolia (admin/mod/author) tallentuu `admin_users`-tauluun ja tunnistetaan sessiossa jokaisella suojatulla admin-sivulla; kaikki kirjautuneet käyttäjät voivat vaihtaa oman salasanansa.
**Verified:** 2026-07-16
**Status:** human_needed
**Re-verification:** No — initial verification

## Context Note

Per explicit task instruction: `database/migrate_roles.sql` has **not yet been applied** to the live database. This was confirmed directly during this verification (`DESCRIBE admin_users` on the live `virtuaalitalli` DB via `docker exec virtuaalitalli-db` returned only `id, username, password, created_at` — no `role`/`is_active` columns). This is a known, documented, pending manual user action (phpMyAdmin at localhost:8080), not a code gap. All findings below that depend on this migration are routed to **human verification**, not treated as failures, per task instructions.

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Käyttäjän rooli (admin/mod/author) luetaan `admin_users`-taulusta ja tallentuu sessioon kirjautumisen yhteydessä | ⚠️ PRESENT_BEHAVIOR_UNVERIFIED | Code fully present + wired: `login.php` SELECTs `role, is_active`, requires `(int)$row['is_active'] === 1`, writes `$_SESSION['admin_role'] = $row['role']` (login.php:20-29). `database/migrate_roles.sql` and `schema.sql` both define `role ENUM('admin','mod','author') NOT NULL DEFAULT 'author'` + explicit `UPDATE ... SET role='admin' WHERE username='admin'`. **Cannot be exercised at runtime**: live DB confirmed missing these columns (migration pending) — see Human Verification #1. |
| 2 | Mod/author-käyttäjä joka avaa suoralla osoitteella roolinsa ulkopuolisen admin-sivun ohjautuu ei-oikeutta.php:hen sisällön sijaan | ⚠️ PRESENT_BEHAVIOR_UNVERIFIED | Code fully present + wired: all 27 previously-`requireLogin()`-only admin pages now call `requireRole(...)` with the exact role list from the audit table (verified via grep, see Key Link Verification); `requireRole()` in helpers.php calls `requireLogin()` then `redirect(SITE_URL.'/admin/ei-oikeutta.php')` on disallowed role; 4 mixed files (foals/kasvatus_all/competitions/showrecords) have an inline `requireRole('admin')` as literally the first statement of the delete branch (verified line order via grep — line 123/18/74/101, immediately preceding the branch's DB logic). Pitfall-4 files (index.php, horse_import_vrl.php, kilpailut_all.php) confirmed: `requireRole()` line 3, `admin_header.php` include at line 12/18/18 (gate strictly precedes). **Cannot be exercised at runtime** without a live mod/author session (no such account exists yet — created in Phase 11 — and role-flip testing requires the migration above) — see Human Verification #2. |
| 3 | Admin-navigaatio näyttää kullekin roolille vain sen omat valikkokohdat | ✓ VERIFIED | Statically verified, not merely behavior-dependent: `admin_header.php` wraps every `admin-nav-item` in `in_array($role, [...], true)` with lists cross-checked directly against the same 27-file audit table used for page gates (index/posts: `admin,mod,author`; settings: `admin` only; all others: `admin,mod`). "Vaihda salasana" link sits unwrapped between username and logout form, visible to all roles. Deterministic code inspection is sufficient here (conditional rendering logic, not a state transition/cleanup invariant). |
| 4 | Kirjautunut käyttäjä (mikä tahansa rooli) voi vaihtaa oman salasanansa antamalla nykyisen salasanan sekä uuden kahdesti, ja kirjautuminen onnistuu heti uudella salasanalla | ⚠️ PRESENT_BEHAVIOR_UNVERIFIED | Code fully present + wired: `change_password.php` gates with `requireRole('admin','mod','author')`, validates CSRF, `password_verify()` on current password, `validate_string($new, 8, 255)`, confirm-match check — all three negative branches append to `$errors[]` and are statically provable to prevent reaching the `empty($errors)` UPDATE block. Success path: `password_hash(PASSWORD_BCRYPT, cost 12)` → `UPDATE` → `session_regenerate_id(true)` (in that order, confirmed via grep) → inline success message, no redirect (user stays logged in by design). **Cannot be exercised at runtime** — the `requireRole()` gate on this page itself requires a working `admin_role` session, which requires the pending migration — see Human Verification #3. |

**Score:** 4/4 truths code-verified (present + correctly wired); 1/4 (Truth 3) fully behavior-independent and VERIFIED; 3/4 routed to human verification pending the documented, pending manual DB migration (not a code gap).

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `database/migrate_roles.sql` | ALTER TABLE + explicit admin backfill | ✓ VERIFIED | Matches migrate_theme.sql convention; adds `role` ENUM + `is_active`, backfills `role='admin'` for existing account |
| `database/schema.sql` (admin_users block) | role/is_active columns for fresh installs | ✓ VERIFIED | Lines 245-256 — same columns/defaults as migration |
| `public/src/includes/helpers.php` (`requireRole`, `currentRole`, `isAdmin`) | Three new functions, existing `requireLogin`/`isLoggedIn` untouched | ✓ VERIFIED | Lines 58-89 — exact deny-by-default `in_array` allow-list; `currentRole()` is the sole `$_SESSION['admin_role']` read site (confirmed via grep — only login.php writes it, only helpers.php reads it) |
| `public/admin/login.php` | Extended SELECT + is_active check + session write | ✓ VERIFIED | Lines 20-33 |
| `public/admin/ei-oikeutta.php` | New redirect-target page | ✓ VERIFIED | Uses `admin_header.php`/`admin_footer.php` shell, fixed message, fixed "Takaisin" target |
| 27 `admin/*.php` files gate-swap | `requireLogin()` → `requireRole(...)` per audit table | ✓ VERIFIED | Grep confirms all 27 files carry the exact role list from the audit table; only `logout.php` and `ei-oikeutta.php` retain bare `requireLogin()` |
| 4 mixed-file inline delete sub-gates | `requireRole('admin')` first statement in delete branch | ✓ VERIFIED | foals.php:123, kasvatus_all.php:18, competitions.php:74, showrecords.php:101 |
| `public/admin/includes/admin_header.php` (nav gating + password link) | Role-conditional nav + universal password-change link | ✓ VERIFIED | Lines 290-348 |
| `public/admin/change_password.php` | New password-change page | ✓ VERIFIED | Full file reviewed, matches D-08/D-09/D-10 |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `login.php` | `$_SESSION['admin_role']` | Written only from a `password_verify` + `is_active=1`-checked DB row | ✓ WIRED | Confirmed — no other code path writes this session key |
| `requireRole()` | `ei-oikeutta.php` | `redirect(SITE_URL.'/admin/ei-oikeutta.php')` on disallowed role | ✓ WIRED | helpers.php:84-89 |
| `currentRole()` | `$_SESSION['admin_role']` | Sole read site | ✓ WIRED | grep confirms exactly one read site, one write site |
| 27 admin pages | `requireRole(...)` | Gate remains at top of file, before DB queries / `admin_header.php` include | ✓ WIRED | Pitfall-4 files individually line-number-verified (gate line < admin_header include line) |
| 4 mixed files | inline `requireRole('admin')` | First statement of delete branch, before DB read/write | ✓ WIRED | Line-order-verified for all 4 files |
| `admin_header.php` nav | 27-file audit table | Same role lists, cross-checked | ✓ WIRED | Every nav-item role list matches exactly the corresponding page's `requireRole()` list |
| `change_password.php` | existing CSRF/validate_string/password_hash helpers | Reuses `validate_csrf_token`, `validate_string`, `password_hash` — no new mechanism | ✓ WIRED | Confirmed present in helpers.php and correctly invoked |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| PHP syntax — helpers.php, login.php, ei-oikeutta.php, admin_header.php, change_password.php | `docker exec virtuaalitalli-web php -l ...` | "No syntax errors detected" (all 5) | ✓ PASS |
| PHP syntax — all 27 gate-swapped admin files + api/vrl_import_save.php + logout.php | `docker exec virtuaalitalli-web php -l ...` (28 files) | "No syntax errors detected" (all 28) | ✓ PASS |
| Live DB schema check | `docker exec virtuaalitalli-db mysql ... -e "DESCRIBE admin_users;"` | Returns only `id, username, password, created_at` — no `role`/`is_active` | ✗ CONFIRMS PENDING MIGRATION (expected per task context, not a code gap) |
| Login flow, role-flip redirect test, password-change flow | N/A | Not run | ? SKIP (requires migration + live browser session) — routed to Human Verification |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| ROLE-01 | 10-01 | Kolme roolia tallennetaan `admin_users`-tauluun | ✓ SATISFIED (code) | migrate_roles.sql + schema.sql; DB apply pending (human) |
| ROLE-02 | 10-01, 10-02 | Rooli tallennetaan sessioon ja tarkistetaan palvelinpuolella jokaisella sivulla | ✓ SATISFIED (code) | login.php session write + 27-file requireRole() audit |
| ROLE-03 | 10-01, 10-02 | Roolin ulkopuolinen sivu ohjaa ei-oikeutta.php:hen | ✓ SATISFIED (code) | requireRole() → redirect() wiring; live redirect test pending (human) |
| ROLE-04 | 10-03 | Nav näyttää vain sallitut valikkokohdat roolille | ✓ SATISFIED | admin_header.php nav wrap verified statically |
| AUTH-06 | 10-03 | Kirjautunut käyttäjä voi vaihtaa oman salasanansa | ✓ SATISFIED (code) | change_password.php full flow reviewed; live test pending (human) |

No orphaned requirements — REQUIREMENTS.md traceability table maps exactly ROLE-01..04 + AUTH-06 to Phase 10, and all five appear across the three plans' `requirements` frontmatter (ROLE-01/02/03 in 10-01, ROLE-02/03 in 10-02, ROLE-04/AUTH-06 in 10-03).

### Anti-Patterns Found

None. Grep for `TODO|FIXME|XXX|HACK|PLACEHOLDER|not yet implemented|coming soon` across all files modified/created in this phase (change_password.php, ei-oikeutta.php, login.php, helpers.php, admin_header.php, migrate_roles.sql, schema.sql) returned no matches.

### Human Verification Required

See frontmatter `human_verification` list. Summary:

1. **Run the pending migration** (`database/migrate_roles.sql` via phpMyAdmin at localhost:8080) and confirm `admin_users` gains `role`/`is_active` with the existing admin account backfilled correctly.
2. **Role-flip redirect test** — temporarily flip the existing admin account's role to `mod`/`author` via phpMyAdmin, confirm out-of-role pages (and the 4 delete sub-gates) redirect to `ei-oikeutta.php` instead of executing, then restore `role='admin'`.
3. **Full change_password.php browser flow** — all 3 negative cases plus a successful change, followed by logging in with the new password.

### Gaps Summary

No code-level gaps found. Every artifact, wiring link, and control-flow invariant claimed by the three phase plans is present in the codebase and passes static/structural verification (grep, line-order checks, `php -l` inside the running Docker container). The phase's only open item is the **explicitly known, pre-existing, and already-documented** pending manual database migration — this blocks live/browser verification of session-role behavior, role-based redirects, and the password-change flow, but does not indicate missing or incorrect code. All three affected truths are routed to human verification rather than marked as failures, consistent with the task's explicit guidance not to penalize the phase for this documented blocker.

---

*Verified: 2026-07-16*
*Verifier: Claude (gsd-verifier)*
