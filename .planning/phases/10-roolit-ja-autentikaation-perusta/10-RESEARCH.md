# Phase 10: Roolit ja autentikaation perusta - Research

**Researched:** 2026-07-05
**Domain:** Role-based access gating (admin/mod/author) retrofitted onto an existing plain PHP 8.2 + PDO + MySQL admin panel — implementation-level detail layer on top of `.planning/research/SUMMARY.md`
**Confidence:** HIGH

## Summary

This research goes one level deeper than the milestone-level `research/SUMMARY.md`/`STACK.md`/`PITFALLS.md`, which already locked the architecture (session-cached `admin_role`, `requireRole()` mirroring `requireLogin()`, ENUM `role` column, no new dependencies). What's new here: the exact PHP idioms to mirror in `helpers.php`, the concrete `login.php`/`admin_header.php` diffs, the finalized ~30-file role-audit table (verified directly against every file in `public/admin/`), and one previously-undocumented implementation trap — **four content files mix create/edit *and* delete in the same script** (`kasvatus_all.php`, `foals.php`, `competitions.php`, `showrecords.php`), so file-level `requireRole()` cannot be used to keep delete admin-only per CONTEXT.md's D-02; the delete branch needs its own inline sub-check.

**Primary recommendation:** Add `requireRole(string ...$allowed): void` + `currentRole(): ?string` + `isAdmin(): bool` to `helpers.php` as thin wrappers around `isLoggedIn()`/`$_SESSION['admin_role']` (no DB re-check needed this phase — see Session Staleness section). Apply file-level `requireRole(...)` at the exact `requireLogin();` call site in all ~27 existing files per the audit table below, add an inline `requireRole('admin')` guard immediately before the `DELETE FROM` statement in the four mixed create/edit/delete files, and gate `horse_delete.php`/`post_delete.php`/`photo_delete.php` (already separate files) at file level. Ship `admin/change_password.php` and `admin/ei-oikeutta.php` as two small new scripts following the exact patterns already used by `login.php` and `admin_header.php`.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Role storage (`admin_users.role`, `is_active`) | Database / Storage | — | Single source of truth for role/active-state; ENUM matches existing `horses.gender`/`foals.status` convention |
| Role assertion at login (`login.php` session write) | API / Backend (PHP script, no framework) | Database (read `role`/`is_active`) | Session is populated once, at the moment of DB-verified authentication — same pattern as existing `admin_id`/`admin_username` |
| Per-page access gating (`requireRole()`) | API / Backend | — | Server-side check in every `admin/*.php` script; this codebase has no router/middleware tier, so gating lives at the top of each script (documented existing convention) |
| Nav visibility (`admin_header.php`) | Frontend Server (server-rendered PHP include) | — | Cosmetic only — must never be treated as the enforcement layer; the same role list used for `requireRole()` must drive `in_array($currentRole, [...])` in nav rendering, kept in sync manually since there is no shared config yet |
| Password change (`change_password.php`) | API / Backend | Database (verify/write hash) | Self-contained script; reuses `password_hash`/`password_verify`/CSRF/`validate_string` already used elsewhere |
| "Ei käyttöoikeutta" redirect target | API / Backend (server-rendered page) | — | Reached via `redirect()` from any `requireRole()` failure; not a client-side route |

## User Constraints (from CONTEXT.md)

<user_constraints>

### Locked Decisions

- **D-01:** Phase 10 does the FULL page audit now — all ~30 admin pages get their final role requirement (`requireRole()`-level gating + nav visibility in `admin_header.php`), not just an admin-only/other split.
- **D-02:** Delete endpoints (`horse_delete.php`, foal/competition/showrecord delete, `post_delete.php`) stay `requireRole('admin')` until Phase 13, even though mod otherwise gets create/edit access to content pages now.
- **D-03:** Explicit boundary Phase 10 vs. Phase 12: **Phase 10 = access + nav visibility per page** (can the role even open the page, does it show in nav); **Phase 12 = in-page role-specific filtering/logic** (e.g. author can, in principle, edit others' posts right after Phase 10 — ownership filtering is built in Phase 12). This is an accepted temporary state because mod/author accounts don't exist yet (created in Phase 11) — nobody can exploit the temporary gap before Phase 11 runs.
- Planner's task: build an explicit file-to-role table for all `public/admin/*.php` files (including unnamed ones like `contacts.php`, `sukulaiset.php`, `horse_import_vrl.php`, `kuvat_all.php`) — identified by research as a critical gap if skipped.

### "Ei käyttöoikeutta" view

- **D-04:** Dedicated page (e.g. `admin/ei-oikeutta.php`), uses `admin_header.php` layout (same sidebar/context as other pages). Message: "Sinulla ei ole käyttöoikeutta tähän sivuun" + link back.
- **D-05:** "Back" link always points to `admin/index.php` (dashboard) regardless of role — no `HTTP_REFERER` logic (not reliable in a security context), no role→homepage mapping (all roles see the same dashboard, only nav items differ).

### Admin user role backfill

- **D-06:** `ALTER TABLE admin_users ADD COLUMN role ENUM('admin','mod','author') NOT NULL DEFAULT 'author'` (safest fallback default), followed by explicit `UPDATE admin_users SET role='admin' WHERE username='admin'` in the same migration file. Do not rely on DEFAULT alone for the existing account.
- **D-07:** `is_active TINYINT(1) NOT NULL DEFAULT 1` added in the SAME migration as `role` (even though the management UI is Phase 11). `login.php`'s login check is extended to check `is_active = 1` immediately — immediate security benefit, avoids a second `ALTER TABLE` in Phase 11.
- Migration file named per convention, e.g. `database/migrate_roles.sql`, matching `migrate_theme.sql`'s style (comment header + phpMyAdmin instructions + `INSERT IGNORE`/explicit statements).

### Password change (AUTH-06)

- **D-08:** New page `admin/change_password.php`. Link lives in the sidebar footer (`admin_header.php` `.admin-sidebar-footer`) between the username and "Kirjaudu ulos" link. Visible to all roles (admin/mod/author) — no role-based hiding for this link.
- **D-09:** Form: current password + new password + new password confirmation (3 fields). On success: `session_regenerate_id(true)` (prevents session fixation), user STAYS logged in, success message shown inline on the same page (no redirect+flash-session mechanism — avoids building a new flash system that doesn't otherwise exist in this codebase).
- **D-10:** Minimum length for the new password: 8 characters, using `validate_string()`-style validation. No other strength rules (upper/lower/special chars) — research recommended avoiding password-strength theater at this 2-4 user scale.

### Claude's Discretion

- Exact `requireRole()`/`currentRole()`/`isAdmin()` function signatures and code style in `helpers.php` (mirroring `requireLogin()`/`isLoggedIn()`).
- Exact role-array implementation per nav item in `admin_header.php` (e.g. `in_array($currentRole, ['admin','mod'])`).
- Exact wording of error messages (except the "Ei käyttöoikeutta" page's core message, given above).

### Deferred Ideas (OUT OF SCOPE)

No new scope proposals surfaced during discussion — all four topics stayed within Phase 10's boundary. Already-scheduled later phases (not repeated here): user management UI, role/name edit, deactivate/delete, password reset for another user → Phase 11. In-page role-specific filtering (author ownership, mod full CRUD substance, horse linking read-only) → Phase 12. Delete-approval workflow, `pending_deletions` table → Phase 13.

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ROLE-01 | Three roles (admin, mod, author) stored on `admin_users` | `database/migrate_roles.sql` pattern below (ENUM column, explicit backfill per D-06); verified current schema has no `role` column (`database/schema.sql:245-252`) |
| ROLE-02 | Role stored in session at login, checked server-side on every protected page | `login.php` session-write extension (verified current write at lines 24-29); `requireRole()`/`currentRole()` helper pattern mirroring `isLoggedIn()`/`requireLogin()` (`helpers.php:51-62`) |
| ROLE-03 | Out-of-role page access redirects to "Ei käyttöoikeutta" view | New `admin/ei-oikeutta.php` per D-04/D-05; `requireRole()` calls `redirect()` (existing helper, `helpers.php:43-46`) to this page instead of failing silently |
| ROLE-04 | Admin nav shows only the role's allowed menu items | `admin_header.php` nav block (verified lines 290-317) — wrap each `<a class="admin-nav-item">` in `in_array($currentRole, [...])` |
| AUTH-06 | Any logged-in user can change their own password | New `admin/change_password.php`; reuses `password_verify()`/`password_hash(..., PASSWORD_BCRYPT, ['cost'=>12])` (matches `database/seed.sql:550` convention), `generate_csrf_token()`/`validate_csrf_token()` (`helpers.php:218-237`), `validate_string()` (`helpers.php:247-271`), `session_regenerate_id(true)` (mirrors `login.php:25`) |

</phase_requirements>

## Standard Stack

No new runtime dependencies. Everything below is native PHP 8.2 / MySQL, already used elsewhere in this exact codebase.

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP native `$_SESSION` | PHP 8.2 (built-in) | Carries `admin_role` alongside existing `admin_logged_in`/`admin_id`/`admin_username` | Zero new dependency; identical mechanism already trusted for auth `[VERIFIED: public/admin/login.php]` |
| `password_hash()`/`password_verify()` | PHP 8.2 (built-in), `PASSWORD_BCRYPT` cost 12 | Verify current password, hash new password in `change_password.php` | Already the exact mechanism securing `admin_users.password` — confirmed by `database/seed.sql:550` comment showing `password_hash('...', PASSWORD_BCRYPT, ['cost' => 12])` `[VERIFIED: database/seed.sql]` |
| MySQL `ENUM` | Altervista-provided MySQL/MariaDB | `admin_users.role` | Matches existing conventions (`horses.gender ENUM(...)`, `foals.status ENUM(...)`) `[VERIFIED: database/schema.sql]` |
| Hand-written `requireRole()`/`currentRole()`/`isAdmin()` | N/A (~15 lines in `helpers.php`) | Central role-gate, mirroring `requireLogin()`/`isLoggedIn()` | Three static roles do not justify a permission engine or Composer package (Altervista shared hosting has no Composer in production anyway) `[CITED: research/STACK.md]` |

### Supporting

None needed for this phase — no new libraries, no new table beyond the one `admin_users` migration.

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Session-cached role, re-verified via `requireRole()` reading `$_SESSION` only | Per-request DB re-check of `role`/`is_active` on every admin page | Not needed in Phase 10 (see Session Staleness section) — would add a query to every page for a risk that cannot yet materialize (no second user exists until Phase 11). Revisit in Phase 11. |
| ENUM `role` column | Separate `roles` + `user_roles` join tables | Massive over-engineering for 3 fixed, never-changing roles and 1-4 users; explicitly rejected in `research/FEATURES.md` anti-features |

**Installation:** none — no `composer install`/`npm install` required for this phase.

**Version verification:** N/A — no packages installed. PHP version already fixed by hosting (Altervista, PHP 8.2 confirmed by existing `match()` expression usage in `helpers.php:137`).

## Package Legitimacy Audit

Not applicable — this phase installs no external packages (no Composer, no npm, no new dependency of any kind). Confirmed against `research/STACK.md`'s explicit "no new runtime dependencies" recommendation and against this being a Composer-less Altervista deployment.

## Architecture Patterns

### System Architecture Diagram

```
Browser (mod/author/admin)
        │  GET/POST /admin/<page>.php
        ▼
[public/admin/<page>.php]
        │
        │ 1. require db.php  ──────────────► loads config.php, session_start(), helpers.php
        │ 2. requireRole('admin','mod', ...) ─┐
        │                                     │ reads $_SESSION['admin_role']
        │                                     │   ├─ not logged in  → redirect(login.php)
        │                                     │   └─ role not allowed → redirect(ei-oikeutta.php)
        │ 3. [file-specific logic runs only if step 2 passed]
        │      └─ if inline delete branch (kasvatus_all/foals/competitions/showrecords):
        │            requireRole('admin')  ── second, narrower gate before DELETE FROM
        ▼
[admin_header.php include]
        │  reads $_SESSION['admin_username'], $currentRole (new)
        │  renders nav: only items where in_array($currentRole, [...allowed...])
        ▼
Rendered admin page (role-appropriate nav, page content unchanged from today)

Separate flow — login:
Browser → login.php (POST username/password)
        → password_verify() against admin_users.password
        → check is_active = 1  (NEW, D-07)
        → session_regenerate_id(true)
        → $_SESSION['admin_role'] = row['role']  (NEW, D-06/ROLE-02)
        → redirect(index.php)

Separate flow — password change:
Browser → change_password.php (any role, reached via sidebar footer link)
        → requireRole('admin','mod','author')  (i.e. requireLogin())
        → validate_csrf_token()
        → password_verify(current, session user's hash)
        → validate_string(new, min:8) + new === confirm
        → password_hash(new, PASSWORD_BCRYPT, ['cost'=>12]) → UPDATE admin_users
        → session_regenerate_id(true)
        → inline success message, same page, still logged in
```

### Recommended Project Structure

No new directories — two new files land in the existing flat `public/admin/` structure, one migration file lands in the existing `database/` directory:

```
public/admin/
├── change_password.php     # NEW — AUTH-06, any role
├── ei-oikeutta.php         # NEW — ROLE-03 target, any role can land here
├── includes/
│   └── admin_header.php    # MODIFIED — nav gated by $currentRole
├── login.php               # MODIFIED — session write includes admin_role, is_active check
├── ...                     # ~27 existing files — MODIFIED: requireLogin() → requireRole(...)
database/
└── migrate_roles.sql       # NEW — role + is_active columns + explicit admin backfill
```

### Pattern 1: `requireRole()` as a drop-in replacement for `requireLogin()`

**What:** A variadic guard function that checks both "is logged in" and "is the session role in the allowed set," redirecting to the appropriate target for each failure mode.
**When to use:** At the exact call site where every existing `admin/*.php` file currently calls `requireLogin();` (immediately after `require_once .../db.php;`).
**Example:**
```php
// Source: mirrors existing isLoggedIn()/requireLogin() in public/src/includes/helpers.php:51-62

/**
 * Palauttaa kirjautuneen käyttäjän roolin, tai null jos ei kirjautunut.
 */
function currentRole(): ?string {
    return $_SESSION['admin_role'] ?? null;
}

/**
 * Oikotie: onko kirjautunut käyttäjä admin.
 */
function isAdmin(): bool {
    return currentRole() === 'admin';
}

/**
 * Vaatii kirjautumisen JA että käyttäjän rooli on jokin sallituista.
 * Käytetään requireLogin()-kutsun tilalla jokaisen suojatun sivun alussa.
 *
 * @param string ...$allowedRoles esim. requireRole('admin', 'mod')
 */
function requireRole(string ...$allowedRoles): void {
    requireLogin(); // olemassa oleva: ohjaa login.php:hen jos ei kirjautunut
    if (!in_array(currentRole(), $allowedRoles, true)) {
        redirect(SITE_URL . '/admin/ei-oikeutta.php');
    }
}
```
Then every existing file changes exactly one line:
```php
// Before:
requireLogin();
// After (example: content page all three roles can reach per D-03):
requireRole('admin', 'mod', 'author');
// After (example: admin-only settings.php):
requireRole('admin');
```
Keep `requireLogin()` itself unchanged (still used standalone by `login.php`'s "already logged in" redirect check, `logout.php`, and internally by `requireRole()`) — do not delete it.

### Pattern 2: Inline sub-gate for mixed create/edit/delete files

**What:** Four files (`kasvatus_all.php`, `foals.php`, `competitions.php`, `showrecords.php`) branch on `$_POST['action']` (`add`/`edit`/`delete`) inside one script. Per D-02, mod may reach `add`/`edit` but not `delete`. File-level `requireRole('admin','mod')` alone is insufficient — the `delete` branch needs its own narrower check.
**When to use:** Only in these 4 files (verified: `horse_delete.php`, `post_delete.php`, `photo_delete.php` are already separate files and just need file-level `requireRole('admin')`, no sub-gate needed).
**Example (mirrors verified structure in `public/admin/foals.php:59-129`):**
```php
// File-level gate (top of file, replaces requireLogin()):
requireRole('admin', 'mod');

// ... existing GET rendering, form display, etc. unchanged ...

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        // existing error handling unchanged
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            // unchanged — admin + mod both reach here
        } elseif ($action === 'edit' && $foal_id > 0) {
            // unchanged — admin + mod both reach here
        } elseif ($action === 'delete' && $foal_id > 0) {
            requireRole('admin'); // NEW — narrower in-branch gate, D-02
            $own = $db->prepare('SELECT id FROM foals WHERE id = :foal_id AND horse_id = :horse_id');
            // ... existing delete logic unchanged ...
        }
    }
}
```
Verified via direct grep: this exact `add`/`edit`/`delete` action-dispatch shape exists in `kasvatus_all.php:17-18`, `foals.php:66-129`, `competitions.php:73-77`, `showrecords.php:100-104`. `kilpailut_all.php` and `showrecords_all.php` are read-only list views with no POST handling at all — no sub-gate needed there, file-level `requireRole('admin','mod')` is sufficient.

### Pattern 3: Nav visibility mirrors the same role list used for gating

**What:** `admin_header.php`'s nav block (verified lines 290-317) wraps each link's `active` class check today; add an `in_array($currentRole, [...])` wrapper (or ternary display) around each `<a class="admin-nav-item">` using the *same* allowed-role list passed to that page's `requireRole()` call.
**When to use:** Every nav item.
**Example:**
```php
<?php $role = currentRole(); ?>
<?php if (in_array($role, ['admin','mod'], true)): ?>
  <a class="admin-nav-item <?= in_array($_activePage, ['horses','horse_add','horse_edit','horse_delete']) ? 'active' : '' ?>"
     href="<?= e(SITE_URL) ?>/admin/horses.php">🐎 Hevoset</a>
<?php endif; ?>
```
**Why this matters (Pitfall from `research/PITFALLS.md` #1 / UX Pitfalls table):** gating only the nav link without gating the underlying page is a "common half-fix" — a direct URL still works. The reverse mistake (gating the page but leaving the nav link visible) produces confusing dead-end clicks. Both the file's `requireRole()` list and the nav's `in_array()` list must be built from the *same* source-of-truth role array per page — recommend the planner define one PHP array/constant per page-group (e.g., in a comment block or a small lookup table) so the two lists can't drift silently.

### Anti-Patterns to Avoid

- **Gating only in `admin_header.php` and not in the page script itself:** `admin_header.php` is included *after* other logic on several pages (e.g. `horse_import_vrl.php`, `contacts.php`, `index.php`) — it is a rendering include, not a gate. `requireRole()` must be called before any file-specific logic runs, not delegated to the header include.
- **Comparing role strings inline scattered across files** (`$_SESSION['admin_role'] === 'Admin'` typos, inconsistent casing): centralize the comparison inside `requireRole()`/`isAdmin()`/`currentRole()` only — confirmed as an explicit "Security Mistake" in `research/PITFALLS.md`'s Technical Debt Patterns table.
- **Building a router/middleware layer for this:** would be the single biggest architectural mismatch for a documented "PHP includes" codebase convention (per `research/SUMMARY.md`) — stick with the copy-one-line-per-file pattern.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Password hashing/verification | Custom hash function, MD5/SHA1, home-rolled salting | `password_hash()`/`password_verify()` (already in use) | Native, battle-tested, auto-upgrades algorithm via `PASSWORD_DEFAULT`/explicit `PASSWORD_BCRYPT` — reinventing this is a textbook OWASP anti-pattern `[VERIFIED: php.net manual via WebSearch]` |
| Role/permission engine | Generic RBAC library, permissions-matrix table, Composer ACL package | Hand-written `requireRole()` + ENUM column | 3 fixed roles, ~30 pages — a permission engine solves a problem this app doesn't have; Altervista production also has no Composer, so any Composer-based package would need manual vendoring `[CITED: research/STACK.md, research/FEATURES.md anti-features table]` |
| Session/token management | JWT, custom session store, stateless tokens | PHP native `$_SESSION` (already in use) | Existing session-based auth already secures the app; introducing a second parallel auth mechanism (JWT) for the same admin panel adds complexity with no benefit at this scale |
| CSRF protection | New per-form CSRF token scheme | `generate_csrf_token()`/`validate_csrf_token()` (already in use, `helpers.php:218-237`) | Every new form (`change_password.php`) must reuse the existing single global-session-token helper — do not introduce a second CSRF mechanism |
| Flash/success messaging | New session-based flash-message subsystem | Inline success message rendered directly in `change_password.php`'s own response (per D-09) | The codebase has no flash-message mechanism today (`flash-ok`/`flash-err` CSS classes exist but are populated by same-request PHP variables, not a session flash pattern) — D-09 explicitly avoids introducing one |

**Key insight:** every "Don't Hand-Roll" recommendation for this phase is "don't hand-roll something bigger than the 3-role, single-admin-panel problem actually is" — the correct amount of engineering here is the smallest extension of patterns that already exist in this exact codebase.

## Common Pitfalls

### Pitfall 1: Mixed create/edit/delete files silently keep mod's delete access if only file-level gating is applied

**What goes wrong:** Applying `requireRole('admin','mod')` at the top of `kasvatus_all.php`/`foals.php`/`competitions.php`/`showrecords.php` (needed so mod can create/edit) without also adding the narrower `requireRole('admin')` check inside the `action === 'delete'` branch silently violates D-02 — mod gets permanent delete on foals/competitions/showrecords the instant Phase 11 creates a mod account, months before the Phase 13 approval workflow exists to make that safe.
**Why it happens:** These four files are visually almost identical to `kilpailut_all.php`/`showrecords_all.php` (pure list views, no delete) and to `horse_delete.php`/`post_delete.php` (pure delete, separate file) — it's easy to assume all content pages follow one of those two shapes and miss that these four are hybrids.
**How to avoid:** Use the Architecture Patterns → Pattern 2 code shape above; add an explicit acceptance test per file: "log in as mod, POST `action=delete` directly to each of the 4 files — must redirect to `ei-oikeutta.php`, not delete the row."
**Warning signs:** A `grep -n "action === 'delete'"` inside any of these 4 files that isn't immediately preceded by a `requireRole('admin')` call in that same conditional branch.

### Pitfall 2: `contacts.php`/`sukulaiset.php`/`horse_import_vrl.php`/`kuvat_all.php`/`api/vrl_import_save.php` are easy to skip because they aren't named in ROADMAP.md's success criteria

**What goes wrong:** ROLE-01..04's success criteria only name "hevoset/varsat/kilpailut/näyttelyt/postaukset/käyttäjähallinta/teema-asetukset" as example pages. The 5 files above are real, currently-`requireLogin()`-gated admin pages that support those workflows (contact/owner management, ancestor linking, VRL import, cross-horse photo gallery) but are never explicitly named — exactly the gap `research/PITFALLS.md` Pitfall 1 and CONTEXT.md's canonical refs flag.
**Why it happens:** Milestone docs describe features by user-facing capability, not by file inventory; these support files don't map 1:1 to a named feature.
**How to avoid:** Use the complete Page-to-Role Audit table below (verified via `grep -rl requireLogin() public/admin/`, 27 files + 1 api file, cross-checked one by one) — every file has an explicit assigned role set, none left as "figure it out later."
**Warning signs:** Any file in `grep -rl requireLogin() public/admin/` not appearing in the audit table below.

### Pitfall 3: Session-cached role goes stale after a mid-session demotion/deactivation — but this is a Phase 11 problem, not a Phase 10 one

**What goes wrong (in general, per `research/PITFALLS.md` Pitfall 3):** if role/`is_active` is only ever read from `$_SESSION`, a demoted or deactivated user's already-open session keeps the old privilege level until they log out or the session naturally expires.
**Why Phase 10 can safely defer the fix:** Phase 10 ships with exactly one account in `admin_users` (the existing `admin` user, explicitly backfilled to `role='admin'` per D-06). No mod/author accounts exist until Phase 11 creates them, and no deactivation/role-change UI exists until Phase 11 either — there is no second user and no code path that could change a role/active-state mid-session in Phase 10. The vulnerability window this pitfall describes literally cannot occur yet. `[ASSUMED — reasoning applies the codebase's own Phase 11 dependency, not an external source; confirm this sequencing assumption still holds if Phase 10/11 scope shifts during planning]`
**How to avoid regressing later:** Design `currentRole()` as the single choke point reading `$_SESSION['admin_role']` (Pattern 1 above) so that when Phase 11 needs the DB re-check, it's a one-function change (swap the body of `currentRole()` to query `admin_users` by `$_SESSION['admin_id']` instead of trusting the session value) — not a find-and-replace across ~30 files. Also extend `login.php`'s `is_active` check now (D-07) even though it only currently matters for a future login attempt, not a live session.
**Warning signs:** Any future Phase-11 planner assuming `currentRole()` already re-checks the DB (it doesn't, in Phase 10) — flag this explicitly in Phase 11 planning/research so the assumption isn't silently carried forward as fact.

### Pitfall 4: `admin_header.php` is `require`d *after* other logic runs on some pages — nav-only role checks would run too late to matter, but page-level gates must run *first*

**What goes wrong:** Verified: `index.php`, `contacts.php`, `horse_import_vrl.php`, `kilpailut_all.php` all call `require __DIR__ . '/includes/admin_header.php';` partway through the file (after DB queries), not at the very top. If a developer mistakenly moves the `requireRole()` call to *inside* `admin_header.php` (reasoning "that's where the role is used for nav anyway"), pages that do expensive/sensitive work before including the header would run that work before the gate fires.
**Why it happens:** `admin_header.php` is the one place currentRole()/nav visibility logic naturally lives, so it's tempting to centralize the whole gate there too.
**How to avoid:** `requireRole()` stays at the very top of each page script (replacing `requireLogin()` at its existing call site, right after `require_once .../db.php;`) — never inside `admin_header.php`. `admin_header.php` only *reads* `currentRole()` for nav rendering; it never performs the authorization decision.
**Warning signs:** Any page where sensitive logic (DB writes, session-affecting code) executes before the `require .../admin_header.php` line, if that page's `requireRole()` call were ever accidentally removed or moved.

### Pitfall 5: Forgetting `session_regenerate_id(true)` on the password-change success path

**What goes wrong:** `login.php` already calls `session_regenerate_id(true)` after successful auth (verified line 25) — but that's currently the *only* call site. Per `research/PITFALLS.md` Pitfall 6 and confirmed via `[CITED: cheatsheetseries.owasp.org/Session_Management_Cheat_Sheet]`, any security-sensitive state change (password rotation) should also regenerate the session ID, or a pre-existing fixated/hijacked session ID stays valid straight through the "security fix."
**How to avoid:** `change_password.php` must call `session_regenerate_id(true)` immediately after the successful `UPDATE admin_users SET password = ...` (per D-09, before rendering the inline success message, while the user stays logged in under the new session ID).
**Warning signs:** `grep -L "session_regenerate_id" public/admin/change_password.php` returning the file (i.e., the call is missing).

## Code Examples

### `admin/change_password.php` (new file, full pattern)

```php
<?php
// Source: composed from public/admin/login.php (session_regenerate_id, password_verify pattern)
// and public/src/includes/helpers.php (CSRF + validate_string helpers) — no new pattern introduced.
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod', 'author'); // any authenticated role, per D-08

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Virheellinen pyyntö.';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';

        $db = getDB();
        $stmt = $db->prepare('SELECT password FROM admin_users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password'])) {
            $errors[] = 'Nykyinen salasana on väärä.';
        }

        $val = validate_string($new, 8, 255); // D-10: min 8 merkkiä
        if (!$val['valid']) {
            $errors[] = $val['error'];
        } elseif ($new !== $confirm) {
            $errors[] = 'Uudet salasanat eivät täsmää.';
        }

        if (empty($errors)) {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]); // matches seed.sql convention
            $db->prepare('UPDATE admin_users SET password = :hash WHERE id = :id')
               ->execute([':hash' => $hash, ':id' => $_SESSION['admin_id']]);
            session_regenerate_id(true); // D-09: prevent session fixation, stay logged in
            $success = true;
        }
    }
}

$pageTitle = 'Vaihda salasana';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-body">
  <div class="admin-card" style="max-width:420px">
    <h2>Vaihda salasana</h2>
    <?php if ($success): ?>
      <p class="flash-ok">Salasana vaihdettu onnistuneesti.</p>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="flash-err"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
      <div class="form-group">
        <label for="current_password">Nykyinen salasana</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
      </div>
      <div class="form-group">
        <label for="new_password">Uusi salasana</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
      </div>
      <div class="form-group">
        <label for="new_password_confirm">Vahvista uusi salasana</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" minlength="8" required>
      </div>
      <button type="submit" class="btn">Vaihda salasana</button>
    </form>
  </div>
</div>
</div></div>
</body>
</html>
```

### `admin/ei-oikeutta.php` (new file)

```php
<?php
// Source: reuses existing admin_header.php layout + flash-err styling; no new UI pattern.
require_once __DIR__ . '/../src/includes/db.php';
requireLogin(); // any authenticated role may land here — this IS the "you're logged in but not allowed" page

$pageTitle = 'Ei käyttöoikeutta';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-body">
  <div class="admin-card">
    <p class="flash-err">Sinulla ei ole käyttöoikeutta tähän sivuun.</p>
    <a class="btn" href="<?= e(SITE_URL) ?>/admin/index.php">← Takaisin</a>
  </div>
</div>
</div></div>
</body>
</html>
```

### `login.php` diff (role + is_active)

```php
// Source: extends verified public/admin/login.php:20-29
$stmt = $db->prepare('SELECT id, username, password, role, is_active FROM admin_users WHERE username = :username LIMIT 1');
$stmt->execute([':username' => $username]);
$row = $stmt->fetch();

if ($row && password_verify($password, $row['password']) && (int)$row['is_active'] === 1) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id']       = $row['id'];
    $_SESSION['admin_username'] = $row['username'];
    $_SESSION['admin_role']     = $row['role']; // NEW
    redirect(SITE_URL . '/admin/index.php');
} else {
    $error = 'Väärä käyttäjätunnus tai salasana.';
}
```
Note: deliberately do not distinguish "wrong password" from "deactivated account" in the error message — leaking account-existence/state information to an unauthenticated caller is an information-disclosure anti-pattern; keep the single generic error message.

### `database/migrate_roles.sql` (new migration, D-06/D-07)

```sql
-- ============================================================
-- Roolit ja aktiivisuustila — admin_users.role, admin_users.is_active
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

ALTER TABLE `admin_users`
  ADD COLUMN `role` ENUM('admin','mod','author') NOT NULL DEFAULT 'author'
    COMMENT 'admin = kaikki oikeudet, mod = rajattu sisällönhallinta, author = vain omat postaukset'
    AFTER `username`,
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Deaktivoitu tunnus ei voi kirjautua sisään'
    AFTER `role`;

-- Nosta olemassa oleva admin-tunnus eksplisiittisesti admin-rooliin.
-- Ei luoteta DEFAULT-arvoon tämän olemassa olevan tilin osalta.
UPDATE `admin_users` SET `role` = 'admin' WHERE `username` = 'admin';
```
`[VERIFIED: MySQL ALTER TABLE ... ADD COLUMN ENUM ... NOT NULL DEFAULT ... syntax via WebSearch, cross-checked]` — matches `database/migrate_theme.sql`'s existing comment-header + phpMyAdmin-import convention exactly.

## Page-to-Role Audit Table

Verified via `grep -rl "requireLogin()" public/admin/` (27 files) plus manual inspection of each file's top ~10 lines and, for the 4 mixed files, their POST-action dispatch. This is the authoritative starting point for the planner's task list — every file has an explicit assignment, none deferred.

| File | Final Role Gate | Nav Section | Notes |
|------|-----------------|-------------|-------|
| `index.php` | `admin`, `mod`, `author` | Dashboard | Same dashboard for all roles per D-05; `admin_header.php` included partway through (after DB queries) — confirm `requireRole()` still sits above those queries |
| `horses.php` | `admin`, `mod` | Hevoset | List + create/edit horses; no inline delete |
| `horse_add.php` | `admin`, `mod` | Hevoset | |
| `horse_edit.php` | `admin`, `mod` | Hevoset | |
| `horse_delete.php` | `admin` only | (n/a — POST target) | Separate file, D-02 explicit; no code change to internal delete logic, only the gate |
| `horse_import_vrl.php` | `admin`, `mod` | Hevoset (horse-creation tool) | `admin_header.php` included partway through — verify gate precedes VRL-loading logic |
| `api/vrl_import_save.php` | `admin`, `mod` | (n/a — JSON API endpoint) | Backing endpoint for `horse_import_vrl.php`; same role set |
| `contacts.php` | `admin`, `mod` | Osoitekirja | Not named in milestone success criteria — flagged by Pitfall 2 above; recommend admin+mod since contacts back horse/foal owner assignment which mod manages |
| `contact_add.php` | `admin`, `mod` | Osoitekirja | |
| `contact_edit.php` | `admin`, `mod` | Osoitekirja | |
| `contact_delete.php` | `admin`, `mod` | (n/a — POST target) | Contacts are NOT one of the 5 milestone content types subject to the Phase-13 approval workflow — D-02's delete-restriction rationale does not apply here. **Flagged for planner/CONTEXT confirmation** — reasonable default is admin+mod, not admin-only, since contact deletion is out of Phase 13's scope entirely. `[ASSUMED]` |
| `sukulaiset.php` | `admin`, `mod` | Sukulaiset | Ancestor-linking tool supporting horse pedigree management |
| `kasvatus_all.php` | `admin`, `mod` (file); `admin` only (inline `action==='delete'` branch) | Kasvatus | **Pitfall 1** — mixed create/edit/delete file, verified `DELETE FROM foals` at line 18 |
| `foals.php` | `admin`, `mod` (file); `admin` only (inline `action==='delete'` branch) | Kasvatus | **Pitfall 1** — verified `DELETE FROM foals` at line 126, action-dispatch at lines 59-129 |
| `foal_add.php` | `admin`, `mod` | Kasvatus | |
| `foal_edit.php` | `admin`, `mod` | Kasvatus | |
| `kilpailut_all.php` | `admin`, `mod` | Kilpailut | Verified: read-only list view, no POST handling at all — file-level gate only, no sub-gate needed |
| `competitions.php` | `admin`, `mod` (file); `admin` only (inline `action==='delete'` branch) | Kilpailut | **Pitfall 1** — verified `DELETE FROM competitions` at line 77 |
| `showrecords_all.php` | `admin`, `mod` | Näyttelyt | Verified: read-only list view, no POST handling — file-level gate only |
| `showrecords.php` | `admin`, `mod` (file); `admin` only (inline `action==='delete'` branch) | Näyttelyt | **Pitfall 1** — verified `DELETE FROM showrecords` at line 104 |
| `kuvat_all.php` | `admin`, `mod` | Kuvat | Verified: no inline delete; POST forms target separate `photo_delete.php`/`photo_update.php` |
| `photos.php` | `admin`, `mod` | Kuvat (per-horse photo page, reached via horse context) | |
| `photo_update.php` | `admin`, `mod` | (n/a — POST target) | Per MOD-01 ("mod... niiden kuvia") mod manages photo metadata |
| `photo_delete.php` | `admin`, `mod` | (n/a — POST target) | **Recommendation, not a locked decision** — photos are not one of the 5 milestone content types subject to Phase-13's approval workflow (horses/foals/competitions/showrecords/posts), so D-02's restriction doesn't apply; MOD-01 explicitly grants photo management to mod. `[ASSUMED — flag for planner confirmation]` |
| `post_delete.php` | `admin` only | (n/a — POST target) | D-02 explicit — stays admin-only through Phase 13, even for mod/author's own future posts |
| `posts.php` | `admin`, `mod`, `author` | Postaukset | Per D-03 — author gets access now (ownership filtering deferred to Phase 12); no code change to the ownership-agnostic query logic in this phase |
| `settings.php` | `admin` only | Sivusto → Asetukset | Per MOD-07 / PROJECT.md — theme settings admin-only |
| `logout.php` | `admin`, `mod`, `author` (i.e. unchanged `requireLogin()`) | (n/a — POST target) | No role distinction needed; already correctly gated |
| `login.php` | n/a (pre-auth) | n/a | Modified for session write only, no role gate applies |
| `change_password.php` **(NEW)** | `admin`, `mod`, `author` | Sidebar footer link (not main nav) | Per D-08 |
| `ei-oikeutta.php` **(NEW)** | `admin`, `mod`, `author` (i.e. `requireLogin()`) | Not in nav — reached only via redirect | Per D-04/D-05 |

**Two items explicitly flagged `[ASSUMED]` for planner/user confirmation** (`contact_delete.php`, `photo_delete.php` role scope) — both fall outside the 5 milestone content types subject to the Phase-13 delete-approval workflow, so D-02's restriction reasoning doesn't mechanically extend to them, but this wasn't explicitly decided in CONTEXT.md. Recommend the planner either lock this via a quick confirmation or default to the admin+mod recommendation above (lowest-risk since both are already outside the approval-workflow's scope).

## Session Staleness / Re-check Analysis

**Question from research brief:** does Phase 10 itself need a per-request DB re-check of role/`is_active`, or can it defer to Phase 11?

**Finding:** Phase 10 can safely defer. `research/PITFALLS.md` Pitfall 3 documents the general risk (demoted/deactivated user's session stays privileged for up to `gc_maxlifetime`), but the risk requires (a) more than one `admin_users` row and (b) a UI/action that changes another user's role or active-state mid-session. Neither exists until Phase 11 (`USER-02` role/username edit, `USER-03` deactivation) creates them. Verified: `database/seed.sql:552-553` confirms exactly one `admin_users` row exists today. Phase 10 only *adds* the `role`/`is_active` columns and reads them once, at login — it introduces no code path capable of exploiting the staleness gap it also can't yet close.

**Forward-looking requirement for the planner:** structure `currentRole()` (Pattern 1) as the sole place that reads `$_SESSION['admin_role']`, so Phase 11 can swap its implementation to a live `admin_users` lookup without touching any of the ~30 call sites that use `requireRole()`/`currentRole()`. This is a design-for-extension note, not a Phase 10 deliverable.

## Runtime State Inventory

Not applicable — this phase is additive (new columns, new files) with no rename/refactor/migration of existing identifiers. The one schema change (`admin_users.role`/`is_active`) is a new-column addition with an explicit backfill (D-06), not a rename — no stored data, live service config, OS-registered state, secrets, or build artifacts reference a name that's changing.

## Environment Availability

Skipped — this phase has no external tool/service dependencies beyond the already-verified PHP 8.2 + MySQL/Altervista stack this codebase already runs on. No new CLI, runtime, or service is introduced.

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | None detected — no `phpunit.xml`, no `composer.json`, no `tests/` directory found in repo |
| Config file | none — see Wave 0 |
| Quick run command | Manual smoke test (login as each role in turn, verify page access + nav visibility) — no automated command exists |
| Full suite command | Same as above; no automated suite exists for this codebase |

`[VERIFIED: filesystem check — no test framework, no composer.json found in repository]`

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ROLE-01 | `admin_users.role` ENUM column exists, existing admin backfilled to `admin` | manual (phpMyAdmin `DESCRIBE admin_users;` + `SELECT role FROM admin_users WHERE username='admin';`) | none | ❌ Wave 0 — no DB assertion tooling exists |
| ROLE-02 | Role written to session at login, read on every protected page | manual (log in, inspect via a temporary `var_dump($_SESSION)` or check nav/access behavior matches role) | none | ❌ Wave 0 |
| ROLE-03 | Out-of-role access redirects to `ei-oikeutta.php` | manual (once Phase 11 creates a mod/author test account — until then, test via a temporary manually-inserted test row per role, verified against a role not in a given file's allowed list) | none | ❌ Wave 0 |
| ROLE-04 | Nav hides disallowed sections per role | manual (visual check per role) | none | ❌ Wave 0 |
| AUTH-06 | Password change flow: wrong current password rejected, min-8-length enforced, mismatch rejected, success re-logs-in with new password, session ID changes | manual (attempt each negative case, then successful change, then log out/in with new password) | none | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** manual smoke test of the specific file(s) touched in that commit (verify `requireRole()` present, correct role list, page still renders for allowed role).
- **Per wave merge:** manual full pass — log in as `admin` (only real account until Phase 11), verify every page in the audit table still loads; since no mod/author account exists yet in Phase 10, out-of-role redirect behavior (ROLE-03) can only be verified by temporarily and manually setting `role='mod'`/`role='author'` on a disposable test row (or the single existing account, temporarily, then reverting) — **document this manual-test caveat explicitly in the plan's verification steps**, since a "real" mod/author account doesn't exist until Phase 11.
- **Phase gate:** All 5 requirements manually walked through before `/gsd-verify-work`; no automated gate exists for this codebase currently.

### Wave 0 Gaps

- [ ] No test framework installed at all (PHPUnit or otherwise) — installing one is out of scope for this phase per "no new dependencies" recommendation; continue with manual/smoke testing as the codebase has done for all prior phases.
- [ ] No fixture/seed mechanism for creating a temporary mod/author test account within Phase 10 itself — the planner should include an explicit manual-testing step describing how to temporarily verify ROLE-03/ROLE-04 against non-admin roles (e.g., temporarily `UPDATE admin_users SET role='mod' WHERE username='admin'`, test, then revert to `'admin'`) since Phase 11 (real multi-user creation) hasn't landed yet.

*(This mirrors every prior phase in this project — manual/smoke-test-only is the established and accepted pattern here, not a gap introduced by this phase.)*

## Security Domain

`security_enforcement` is active (ASVS L1, block on `high`). This phase IS the authentication/access-control phase — full coverage below.

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-------------------|
| V2 Authentication | Yes (indirectly — password change) | `password_hash()`/`password_verify()` (`PASSWORD_BCRYPT`, cost 12) — already the existing mechanism, unchanged algorithm; current-password re-verification required before allowing a change (D-09) |
| V3 Session Management | Yes | `session_regenerate_id(true)` after login (existing) AND after password change (NEW — Pitfall 5); session role (`admin_role`) written only from a DB-verified row at authentication time, never trusted from client input |
| V4 Access Control | Yes — core of this phase | `requireRole()` server-side check on every protected page (ROLE-02/03); deny-by-default (`in_array` allow-list, not a deny-list); nav-hiding treated as UX only, never as the enforcement mechanism (Anti-Patterns section) |
| V5 Input Validation | Yes (password change form) | `validate_string()` for min-length (D-10); CSRF token on every state-changing form (`validate_csrf_token()`) |
| V6 Cryptography | Yes (password storage) | `PASSWORD_BCRYPT` — never hand-roll; no new crypto introduced this phase |

### Known Threat Patterns for this stack

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|----------------------|
| Session fixation (attacker sets victim's session ID before login, or before a privilege-relevant change) | Spoofing / Elevation of Privilege | `session_regenerate_id(true)` at login (existing) and after password change (NEW, Pitfall 5) `[CITED: cheatsheetseries.owasp.org/Session_Management_Cheat_Sheet]` |
| Broken/missing access control — one of ~30 pages left on bare `requireLogin()` instead of `requireRole()` | Elevation of Privilege | Full audit table above; every file explicitly assigned; `[CITED: OWASP ASVS 4.0.3 V4.1.1 — access control must be enforced server-side on every request, missing even one check is sufficient to break confidentiality/integrity]` |
| Half-fix: nav link hidden but underlying page not gated (or vice-versa) | Elevation of Privilege | Pattern 3 above — same allowed-role array drives both the page gate and the nav visibility check |
| Trusting a client-submitted `role` value (not applicable yet — no user-edit form exists until Phase 11, but the `admin_role` session value itself must never be settable from request input) | Elevation of Privilege / Tampering | `$_SESSION['admin_role']` is written exactly once, in `login.php`, sourced only from the DB row matched by `password_verify()` — no other code path in this phase writes to that session key |
| Privilege-check-after-work ordering bug (gate placed after sensitive logic, e.g. inside a late-included `admin_header.php`) | Elevation of Privilege | Pitfall 4 above — `requireRole()` stays at the top of the page script, never delegated to the header include |
| CSRF on new password-change form | Tampering | Reuse `generate_csrf_token()`/`validate_csrf_token()` exactly as every existing form does |
| Information disclosure via differentiated login error messages (e.g., "account deactivated" vs. "wrong password") | Information Disclosure | Keep the single generic "Väärä käyttäjätunnus tai salasana." message for both wrong-password and deactivated-account cases (see `login.php` diff note above) |

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|-------------------|---------------|--------|
| Boolean `isLoggedIn()`/`requireLogin()` (this codebase, since v1.0) | Role-aware `requireRole()` built as a superset wrapper | This phase (v1.2 Phase 10) | Backward compatible — `requireLogin()` keeps working unchanged for `logout.php`/`login.php`'s own "already logged in" check; nothing that currently calls it breaks |

**Deprecated/outdated:** Nothing is deprecated by this phase — `requireLogin()`/`isLoggedIn()` remain in active use, not replaced.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|----------------|
| A1 | Phase 10 does not need a per-request DB re-check of role/`is_active` because no second `admin_users` row or role-change UI exists yet | Session Staleness / Re-check Analysis; Pitfall 3 | Low if Phase 11 immediately follows and adds the DB re-check as its own acceptance criterion (already true per `research/PITFALLS.md`'s own phase mapping); would become a real gap only if Phase 11 is delayed indefinitely while mod/author accounts are somehow created through another path |
| A2 | `contact_delete.php` should be `admin`+`mod` (not admin-only) since contacts fall outside the 5 milestone content types subject to Phase-13's approval workflow | Page-to-Role Audit Table | Low — worst case is mod temporarily lacks a convenience action (contact deletion) that could trivially be added later; does not create a security gap either way |
| A3 | `photo_delete.php` should be `admin`+`mod` (not admin-only) since photos are not one of the 5 milestone content types subject to Phase-13's approval workflow, and MOD-01 explicitly grants mod photo management | Page-to-Role Audit Table | Low — same reasoning as A2; if wrong, tightening to admin-only later is a one-line change with no data-model impact |

**If this table is empty:** N/A — three assumptions logged above, all low-risk and clearly scoped to page-role-assignment judgment calls rather than security-critical architecture.

## Open Questions (RESOLVED)

1. **Should `contact_delete.php` and `photo_delete.php` be admin-only or admin+mod?**
   - What we know: D-02 explicitly restricts delete on the 5 milestone content types (horses/foals/competitions/showrecords/posts) to admin-only until Phase 13. Contacts and photos are not among those 5 types.
   - What's unclear: CONTEXT.md doesn't explicitly address these two files (only the named 5-type deletes).
   - Recommendation: default to admin+mod (per Assumptions A2/A3) since both are outside the Phase-13 approval-workflow scope entirely and MOD-01 already grants mod photo management; confirm with a quick user check during planning if the planner wants this locked rather than assumed.
   - **(RESOLVED)** — Resolved in `10-02-PLAN.md`'s `<assumptions>` block: both `contact_delete.php` and `photo_delete.php` are explicitly locked to `admin+mod` (per A2/A3) as a research-recommendation-based assumption, not a user-locked decision. The plan documents the one-line tightening path to admin-only if the operator prefers it.

2. **How should ROLE-03/ROLE-04 be manually verified in Phase 10 given no real mod/author account exists yet?**
   - What we know: Phase 11 (not yet built) is what actually creates mod/author accounts via a UI.
   - What's unclear: the phase's own acceptance testing needs *some* non-admin session to verify redirect/nav behavior.
   - Recommendation: the plan should include an explicit manual step — temporarily flip the existing `admin` account's `role` column value via phpMyAdmin (`UPDATE admin_users SET role='mod' WHERE username='admin';`), test mod-scoped behavior, then revert to `'admin'` before finishing the phase. Document this clearly in the plan's verification section so it isn't mistaken for a real multi-account test (that comes in Phase 11).
   - **(RESOLVED)** — Resolved via the role-flip-and-revert manual test procedure now documented in the `<verification>` section of all three plans (`10-01`, `10-02`, `10-03`): `UPDATE admin_users SET role='mod'/'author'` on the existing admin account, log out/in, verify access + nav behavior per role, then `UPDATE ... SET role='admin'` to revert before phase completion.

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection: `public/src/includes/helpers.php`, `public/admin/login.php`, `public/admin/logout.php`, `public/admin/includes/admin_header.php`, `public/admin/foals.php`, `public/admin/kasvatus_all.php`, `public/admin/competitions.php`, `public/admin/showrecords.php`, `public/admin/kilpailut_all.php`, `public/admin/showrecords_all.php`, `public/admin/kuvat_all.php`, `public/admin/contacts.php`, `public/admin/contact_add.php`, `public/admin/contact_edit.php`, `public/admin/contact_delete.php`, `public/admin/sukulaiset.php`, `public/admin/horse_import_vrl.php`, `public/admin/api/vrl_import_save.php`, `public/admin/settings.php`, `public/admin/index.php`, `database/schema.sql`, `database/seed.sql`, `database/migrate_theme.sql`
- `.planning/research/SUMMARY.md`, `.planning/research/STACK.md`, `.planning/research/PITFALLS.md`, `.planning/research/FEATURES.md` — milestone-level research this phase-level research extends, not duplicates
- `.planning/phases/10-roolit-ja-autentikaation-perusta/10-CONTEXT.md` — locked D-01 through D-10 decisions

### Secondary (MEDIUM confidence — WebSearch, cross-checked against official sources)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html) — session ID regeneration after authentication AND after privilege-level change
- [OWASP ASVS 4.0 V4 Access Control (GitHub)](https://github.com/OWASP/ASVS/blob/master/4.0/en/0x12-V4-Access-Control.md) — server-side enforcement required on every request, no exceptions
- [PHP manual: password_hash()](https://www.php.net/manual/en/function.password-hash.php) — `PASSWORD_DEFAULT`/`PASSWORD_BCRYPT` usage, `password_needs_rehash()` pattern
- MySQL `ALTER TABLE ... ADD COLUMN ... ENUM(...) NOT NULL DEFAULT ...` syntax — cross-checked against MySQL reference documentation search results

### Tertiary (LOW confidence)
- None used directly in this document without cross-checking against an official/primary source.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new dependencies; every recommendation verified against this exact codebase's existing files
- Architecture: HIGH — verified by direct inspection of every touched/new file, including the previously-undocumented mixed create/edit/delete file pattern (Pitfall 1)
- Pitfalls: HIGH (codebase-specific) / MEDIUM (general security pitfalls, cross-checked web sources)
- Security domain: HIGH — ASVS categories mapped to concrete, already-verified code patterns in this repo

**Research date:** 2026-07-05
**Valid until:** 30 days (stable domain — no fast-moving dependencies; codebase itself is the primary source and won't drift without further phases landing)
