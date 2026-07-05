# Project Research Summary

**Project:** Virtuaalitalli — milestone v1.2 "Käyttäjäroolit"
**Domain:** Retrofitting role-based access control (admin/mod/author) + a delete-approval workflow onto an existing plain PHP 8.2 + PDO + MySQL admin panel (no framework, no router, no Composer), deployed to Altervista shared hosting
**Researched:** 2026-07-05
**Confidence:** MEDIUM-HIGH

## Executive Summary

This milestone adds three fixed roles (admin/mod/author) and a mod-initiated delete-approval workflow to a small, single-stable admin panel that today has one flat `admin_users` table and no role concept at all. Research converges on a single conclusion: this is a retrofit problem, not a greenfield RBAC design problem. The correct approach is the smallest extension of patterns already in the codebase — session-cached role (mirroring `admin_id`/`admin_username`), a `requireRole()` guard function called at the top of each script (mirroring `requireLogin()`), a `role ENUM('admin','mod','author')` column, and one new `pending_deletions` audit/queue table rather than bespoke status columns duplicated across five content tables. No new dependencies, no ORM, no router.

Recommended sequence: (1) add `admin_users.role`/`is_active`, extend `login.php` session write, add `requireRole()`/`currentRole()`/`requireOwnResourceOrAdmin()` to `helpers.php`; (2) add `posts.author_id` (does not exist today) and gate every admin page by role, with `posts.php` needing an ownership filter since all three roles can reach it; (3) extend the existing `horses.is_deleted`/`deleted_at` convention to `foals`/`competitions`/`showrecords`/`posts` (currently hard-deleted) and add `pending_deletions` so a mod delete soft-deletes immediately (hidden everywhere via existing filters) while remaining reversible until admin approves or rejects.

Dominant risk: inconsistent, manually-applied enforcement across roughly 30 independently-authored `admin/*.php` files (easy to forget a page not named in the milestone spec, e.g. `contacts.php`, `settings.php`). Close behind: IDOR on `posts.php`/`post_delete.php` once `author_id` exists, and edge cases unique to introducing more than one admin account — last-admin lockout, admin self-deletion, and session role staleness after mid-session demotion/deactivation (up to 30 min of stale privilege). All cheap to prevent with explicit guards and acceptance tests if flagged during phase planning.

## Key Findings

### Recommended Stack

No new runtime dependencies — everything is native PHP 8.2 / MySQL already used elsewhere in this repo. The highest-leverage decision is the delete-approval data model: one shared `pending_deletions` polymorphic queue table (`entity_type` ENUM, `entity_id`, `requested_by`, `requested_at`, `status` ENUM(pending/approved/rejected), `reviewed_by`, `reviewed_at`) beats five near-duplicate per-table status columns.

**Core technologies:**
- PHP native `$_SESSION` — carries `admin_role`, zero new dependency, mirrors existing `admin_id`/`admin_username`
- `password_hash()`/`password_verify()`/`password_needs_rehash()` — self-service + admin-reset passwords, already secures `admin_users.password`
- MySQL `ENUM` — `admin_users.role`, `pending_deletions.entity_type`/`status` — matches existing conventions (`horses.gender`, `foals.status`)
- PDO prepared statements + `PDO::beginTransaction()` — all new queries; transactions for the two-write "approve" action
- Hand-written `requireRole()`/`hasRole()`/`currentRole()` in `helpers.php` — 3 static roles do not justify an RBAC engine, and Composer packages cannot be installed on Altervista production

### Expected Features

**Must have (table stakes):**
- `admin_users.role` + centralized `requireRole()` gating (not scattered inline checks)
- "Access denied" redirect for out-of-scope roles
- Self-service password change (current + new + confirm), with session regeneration
- Admin-only user management: list/create/edit/deactivate-or-delete/reset-password
- Pending-deletion inbox spanning all 5 entity types in one view
- Ownership enforcement for author at the query level, not just UI hiding
- CSRF protection on every new form (reuse existing helpers)

**Should have (post-launch polish):**
- Pending-approvals count badge; rejection-reason field; `is_active` deactivation flag distinct from hard delete

**Defer indefinitely (anti-features at 2-4 user scale):**
- Granular permission matrix, multi-admin approval chains, general audit-log subsystem, self-registration, auto-expiring requests (needs cron — unavailable on Altervista free tier), password-strength/breach checking

### Architecture Approach

Additive architecture: one new session field, one new helper-function family in `helpers.php`, new standalone `admin/*.php` scripts shaped like `login.php`/`horse_delete.php`, two schema additions. No router/middleware layer — that would be the single biggest architectural mismatch given this codebase's documented "PHP includes" convention.

**Major components:**
1. `helpers.php` additions — `requireRole()`, `currentRole()`, `isAdmin()`, `requireOwnResourceOrAdmin()`, `insertPendingDeletion()`
2. New admin scripts — `users.php`, `user_reset_password.php`, `change_password.php`, `deletions.php`, `deletion_approve.php`/`deletion_reject.php`
3. Modified delete call sites — `horse_delete.php`, inline branches in `foals.php`/`competitions.php`/`showrecords.php`, three-way branch in `post_delete.php`
4. `database/migrate_roles.sql` (plus possibly a second file) — role/is_active, `posts.author_id`, soft-delete columns on 4 tables, `pending_deletions`

### Critical Pitfalls

1. Copy-pasted `requireLogin()` across roughly 30 files — trivial to miss gating one page. Build an authoritative file-to-role mapping before coding; add a grep-based CI check.
2. IDOR on `posts.php`/`post_delete.php` once `author_id` exists — CSRF does not prevent it. Add `AND author_id = :current_user_id` server-side on every read/update/delete for the `author` role, re-checked on the endpoint itself.
3. Session-cached role/active-state goes stale after mid-session demotion/deactivation/deletion — up to 30 min of stale privilege. Re-fetch role and is_active from DB per request (cheap indexed PK lookup) instead of trusting session alone.
4. Last-admin lockout / admin self-deletion — generic CRUD can let an admin delete the only other admin or themselves. Add a `canModifyUser()` guard blocking admin-count-to-zero and self-targeting destructive actions.
5. No soft-delete columns on 4 tables, no `posts.author_id` — approval workflow and ownership checks have nothing to build on until these migrations land first; sequencing matters.

## Implications for Roadmap

Dependencies flow one direction: role must exist before gating; gating before delete-approval; schema (author_id, soft-delete, pending_deletions) before workflow logic depending on it.

### Phase 1: Roles and Auth Foundation
**Rationale:** Everything else is gated behind "who is this and what role" — the mandatory starting point.
**Delivers:** role/is_active migration (explicit backfill, not DEFAULT-reliant); `requireRole()`/`currentRole()`/`isAdmin()`; `login.php` session write; `admin/change_password.php`; an explicit file-by-file audit table mapping every existing `admin/*.php` (including unnamed ones like `contacts.php`, `sukulaiset.php`, `kuvat_all.php`) to its required role(s).
**Addresses:** Role column + session gating, self-service password change.
**Avoids:** Pitfall 1 (missed gates) via audit table; Pitfall 6 (session fixation) via `session_regenerate_id(true)` in change_password from day one.

### Phase 2: Admin User Management
**Rationale:** First real consumer of `requireRole('admin')`; carries the highest-risk edge cases (last-admin lockout, self-deletion) cheap to prevent now.
**Delivers:** `admin/users.php`, `admin/user_reset_password.php` (one-time-displayed password + `must_change_password` enforcement), `canModifyUser()` guard, DB re-check of role/active-state per request.
**Avoids:** Pitfalls 3 (session staleness), 4 (last-admin lockout), 5 (self-lockout), 7 (insecure/unforced password reset).

### Phase 3: Content-Type Role Gating (horses/foals/competitions/showrecords/photos + posts ownership)
**Rationale:** Depends on Phase 1's `requireRole()`; mechanical for most files, qualitatively different for `posts.php` (all 3 roles land there, needs ownership not just role filtering).
**Delivers:** `requireRole('admin','mod')` on horse/foal/competition/showrecord/photo scripts; `posts.author_id` migration + `requireOwnResourceOrAdmin()` + role-aware queries in `posts.php`/`post_delete.php`; role-aware nav in `admin_header.php`.
**Avoids:** Pitfall 2 (IDOR) via server-side ownership re-check on every endpoint; Pitfall 9 (missing author_id) via schema-first change with explicit legacy-post backfill decision.

### Phase 4: Delete-Approval Workflow
**Rationale:** Depends on Phase 3's gating; schema work (soft-delete on 4 tables + `pending_deletions`) and workflow UI are tightly coupled enough to combine, migration conceptually first.
**Delivers:** `is_deleted`/`deleted_at` on `foals`/`competitions`/`showrecords`/`posts`; `pending_deletions` table; `insertPendingDeletion()`; modified delete call sites (mod: soft-delete + pending row; admin: soft-delete only; author on own posts: immediate soft-delete only); `admin/deletions.php` plus approve/reject handlers; audit pass adding `WHERE is_deleted = 0` to every existing query against the 4 newly-soft-deletable tables.
**Avoids:** Pitfall 8 (nowhere to park pending state) via shared queue table; Pitfall 10 (ambiguous visibility — resolved since soft-delete is immediate and reversible, `is_deleted` never gets a third value); Pitfall 11 (duplicate pending requests — check-then-insert guard); Pitfall 12 (rejected requests must transition state, never be hard-deleted).

### Phase Ordering Rationale

- Role column + `requireRole()` must exist before anything else is buildable.
- User management precedes content-type gating because its edge cases (last-admin, self-lockout) are foundational safety nets needed before more users are actually created and used.
- Content-type gating precedes delete-approval because the workflow depends on knowing the actor's already-gated role.
- Schema work for soft-delete + `pending_deletions` is scoped inside phase 4 (no purpose until the consuming workflow exists), but migration must happen before UI wiring within that phase.

### Research Flags

Needs deeper research during planning:
- Phase 4: the polymorphic `pending_deletions` queue-table shape has no single canonical source (MEDIUM confidence) — worth a focused pass on approve/reject transaction sequencing and the whitelist-based entity_type-to-table lookup before finalizing.
- Phase 3 (posts ownership): IDOR-prevention pattern is well-understood in principle but this codebase has zero existing ownership-filtering precedent — worth explicit negative-test scoping during planning.

Standard patterns (skip research-phase):
- Phase 1: session-role + guard-function directly mirrors existing `requireLogin()`/`isLoggedIn()` — HIGH confidence.
- Phase 2: standard CRUD + `password_hash()`/`password_verify()`, already the exact mechanism securing existing login.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | No new dependencies; every recommendation is a native PHP/MySQL feature already used in this exact codebase, verified by direct file reads |
| Features | MEDIUM | Feature landscape/MVP scope grounded directly in PROJECT.md and existing schema (HIGH); general RBAC/soft-delete best-practice corroboration is general web sources (MEDIUM) |
| Architecture | HIGH | Verified by direct inspection of every touched file; general RBAC pattern cross-check is MEDIUM but used only to validate approach, not source project detail |
| Pitfalls | MEDIUM-HIGH | Codebase-specific pitfalls (missing gates, missing author_id/soft-delete columns) HIGH — confirmed by reading actual schema/files; general IDOR/session-fixation pitfalls MEDIUM (OWASP + cross-checked web sources) |

**Overall confidence:** HIGH

### Gaps to Address

- Public-facing query audit for the 4 newly-soft-deletable tables: not every public page querying `foals`/`competitions`/`showrecords`/`posts` was enumerated during this research pass — phase-4 planning must list every such query (pedigree recursion, listings, calendars) and confirm `WHERE is_deleted = 0` is added.
- Legacy `posts.author_id` backfill decision: backfill to existing sole admin's ID vs. leave NULL treated as "not author-owned" — not resolved by research, must be decided at phase-3 planning.
- Whether editing (not just deleting) an item with a pending deletion request continues to be allowed: flagged as an open product decision (recommended default: yes) to confirm explicitly during phase-4 planning.
- Altervista's exact MySQL/MariaDB version is unconfirmed — only matters if a future DB-level uniqueness constraint is wanted on `pending_deletions`; recommended approach (PHP-level uniqueness check) sidesteps this entirely.

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection: `public/src/includes/helpers.php`, `db.php`, `config.php`, `public/admin/login.php`, `logout.php`, `horse_delete.php`, `post_delete.php`, `posts.php`, `foals.php`, `includes/admin_header.php`, `database/schema.sql`, `database/migrate_ancestor.sql`, `database/migrate_theme.sql`, `.planning/PROJECT.md`

### Secondary (MEDIUM confidence)
- PHP manual: `password_needs_rehash()`, `password_hash()`/`PASSWORD_DEFAULT`
- Role Based Access Control in PHP — SitePoint, Medium (@wwwebadvisor), Tony Marston
- OWASP IDOR Prevention Cheat Sheet
- Session fixation / regenerate session ID after privilege-changing actions
- Multi-level approval system design / workflow-states pattern discussions (coderbased.com, Medium, budibase.com)

### Tertiary (LOW confidence)
- General soft-delete vs hard-delete and approval-workflow-design blog posts (AppMaster, Cflow, HN discussion) — used only to sanity-check the shared-queue-table tradeoff

---
*Research completed: 2026-07-05*
*Ready for roadmap: yes*
