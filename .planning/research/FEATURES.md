# Feature Research

**Domain:** Role-based access control (admin/mod/author) + soft-delete approval workflow for a small single-stable PHP/MySQL admin panel
**Researched:** 2026-07-05
**Confidence:** MEDIUM (patterns are well-established, cross-checked against general web knowledge; no single authoritative source verified — see Sources)

## Context Recap (from PROJECT.md, grounding this research)

- `admin_users` table currently: `id, username, password, created_at` — no `role` column yet.
- Session-based auth already exists (`login.php` sets `$_SESSION['admin_id']`, `$_SESSION['admin_username']`); adding `$_SESSION['admin_role']` at login is the natural extension point.
- Existing soft-delete precedent: `horses.is_deleted` (TINYINT) + `horses.deleted_at` (TIMESTAMP) — the app already has one soft-delete convention to be consistent with.
- `posts` table has **no** `author_id` column yet — this must be added for the author-ownership feature.
- `post_horses` (many-to-many, post_id + horse_id) already exists and is exactly the mechanism the author role's "link existing horses to their posts, read-only picker" feature will reuse — no new join table needed there.
- Entities needing pending-deletion: horses, foals (varsat), competitions (kisat), showrecords (näyttelyt), posts.
- Scale: 2-4 total admin-panel users, single stable, not multi-tenant, not a SaaS product.

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist. Missing these = product feels incomplete or unsafe.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Single `role` column on user table (enum: admin/mod/author) | Simplest correct model for 3 fixed roles at this scale; every small CMS with roles does this, not a permissions-join-table | LOW | Add `role ENUM('admin','mod','author') NOT NULL DEFAULT 'author'` to `admin_users`. No new `roles`/`permissions` tables needed. |
| Role stored in session at login, checked server-side on every protected page | Table-stakes security pattern; client-side/UI-only hiding is not enough | LOW | Extend `login.php` to set `$_SESSION['admin_role']`; add a `requireRole('admin'|['admin','mod'])` guard function reused at top of every admin page, deny-by-default. |
| Centralized permission-check helper, not scattered `if ($role === 'admin')` checks | Prevents drift/inconsistency across ~15 admin pages; easy to audit | LOW | One function in `helpers.php` (e.g. `can($action, $resource)` or simpler `requireRole()`), called at top of each page — matches existing `isLoggedIn()` pattern already in the codebase. |
| "Access denied" page/redirect for users hitting a page outside their role | Users will click into URLs they can't use (e.g. mod visiting `users.php`); a blank page or fatal error looks broken | LOW | Reuse existing redirect/flash-message conventions already in the admin panel. |
| Self-service "change my password" page requiring current password + new password (+confirm) | Standard identity-reverification pattern for any authenticated user changing their own credential | LOW | `password_verify()` old password, `password_hash()` new one, regenerate session ID after change (matches existing `session_regenerate_id(true)` call already used at login). |
| Admin user management: list, create, edit (username + role), deactivate/delete, reset another user's password | This is the actual milestone deliverable — admin needs a UI to manage the accounts the role system creates | MEDIUM | Reset-by-admin does NOT require the old password (distinct from self-service change) — that's expected admin-tool behavior. Needs its own CSRF-protected forms following existing admin CRUD conventions (horses.php, posts.php, etc.). |
| Pending-deletion inbox/queue for admin ("N items awaiting approval") | This is table stakes for *this specific milestone*, not generic CMS — the requirement explicitly calls for mod's deletes to require admin approval | MEDIUM | A single admin-facing list view across all 5 entity types beats 5 separate per-entity approval screens; see dependency notes below on schema shape. |
| Ownership enforcement for author role at the query level, not just UI hiding | An author must literally be unable to edit/delete another author's post via a guessed/edited URL | LOW–MEDIUM | Every posts.php CRUD query must add `AND author_id = :current_user_id` when the acting role is `author` (not just admin ones); mod/admin bypass this filter. |
| CSRF protection on every new form (role change, password change, delete-approve/reject, user create/edit) | App already treats CSRF as a hard requirement (OWASP Top 10 focus in PROJECT.md); every existing admin form uses `generate_csrf_token()`/`validate_csrf_token()` | LOW | Pure consistency work — reuse the existing helper, no new pattern needed. |

### Differentiators (Nice additions, not required for this milestone)

Features that add polish but aren't required to satisfy the stated requirements.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Unified "Pending Approvals" dashboard widget/badge on admin home showing count across all 5 entity types | Admin doesn't have to check 5 separate list pages to find pending mod deletions | LOW–MEDIUM | Cheap to add once the `pending_deletions` table (see dependency notes) exists — a single `SELECT COUNT(*) WHERE status='pending'` grouped by entity_type. |
| Rejection reason / admin note when declining a mod's delete request | Gives the mod feedback on why a deletion was declined instead of silent revert | LOW | Optional `TEXT` column on the approval-queue row; small UX win, easy to defer to v2 if time-constrained. |
| Audit trail (who requested delete, who approved/rejected, when) | Useful for a 2-4 person team to know "why did this disappear" | LOW | Natural side-effect of the pending_deletions queue table already having `requested_by`/`resolved_by`/timestamps — don't build a separate audit-log system, just keep those columns. |
| "Deactivate" as distinct from "delete" for user accounts | Lets admin temporarily disable a mod/author without losing their historical authorship attribution (e.g. `posts.author_id` FK integrity) | LOW | Add `is_active` (or reuse `is_deleted`-style column) on `admin_users`; login checks `is_active = 1`. Cheap, and avoids FK-orphaning posts if a user is later deleted rather than merely deactivated. |
| Force-logout of a deactivated/role-changed user's active session | If admin demotes a mod to author mid-session, the old session still has the old role cached | MEDIUM | For 2-4 users this is a minor real-world risk; simplest mitigation is short session lifetime + re-check role from DB periodically, not a full session-invalidation table. Judgment call — likely defer unless trivially cheap. |

### Anti-Features (Commonly Requested, Often Problematic at This Scale)

Features that seem like natural additions to "add roles" but are overkill for a 2-4 user single-stable app.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|------------------|-------------|
| Granular permissions matrix (per-action, per-resource checkboxes; e.g. "can edit foals but not competitions") | Feels more "flexible" and future-proof | Massive complexity (permissions table, role_permissions join table, permission-check engine) for zero real benefit when there are only 3 fixed roles and ~4 users total; nobody will ever need a 4th custom role | Fixed enum role column (admin/mod/author) with hard-coded per-role rules in code, exactly as specified in requirements |
| Multi-level approval chains (e.g. two admins must approve a deletion) | "More secure" instinct | Solves a problem that doesn't exist with 1 admin account; adds workflow-state complexity (partial approvals, quorum logic) with no stated requirement | Single admin approve/reject action, binary state |
| Full audit-log / activity-feed subsystem (generic event log for all admin actions) | "Good practice" / seen in bigger CMSs | Whole new table + instrumentation across every admin action; requirements only ask for role gating + a deletion-approval queue, not general auditing | Minimal fields on the pending_deletions row (requested_by, resolved_by, timestamps) — enough context without a general-purpose logging system |
| Self-registration / invite links for new mod/author accounts | Common in SaaS onboarding flows | Explicitly out of scope — PROJECT.md states "vain admin luo tunnuksia" (only admin creates accounts); public registration for an admin panel is also a security anti-pattern for a small trusted-team app | Admin manually creates accounts via the user-management CRUD screen |
| Role-based UI theming or per-role dashboards (different admin homepage layouts per role) | Feels more "professional"/personalized | Adds UI/routing complexity disproportionate to value; a mod and an author fundamentally just see fewer menu items on the same shell | Single admin layout that conditionally hides nav items the current role can't access, driven by the same `requireRole()` checks used for page gating |
| Time-boxed/expiring pending-deletion requests (auto-approve or auto-reject after N days) | Seems like good workflow hygiene | Adds a cron/scheduled-task dependency the app doesn't have (Altervista free hosting has no shell access / reliable cron); also not requested | Requests simply sit in the pending queue indefinitely until admin acts; with 2-4 users this is a non-issue |
| Password complexity/strength meter, breach-list checking (e.g. haveibeenpwned integration) | Seen as security best practice in bigger products | Requires external API calls or a large dictionary bundled into a free-tier Altervista PHP app; disproportionate for an internal 2-4 person tool | `password_hash()` with bcrypt/argon2 default cost + a simple minimum-length check (existing OWASP focus is already satisfied by proper hashing + PDO + CSRF, not password complexity theater) |

## Feature Dependencies

```
admin_users.role column (admin/mod/author)
    └──requires──> nothing new (column addition on existing table)

Role-based page gating (requireRole() helper)
    └──requires──> admin_users.role column
                       └──requires──> session sets $_SESSION['admin_role'] at login

Admin user management CRUD (create/edit/deactivate/delete/reset password)
    └──requires──> Role-based page gating (only admin role may reach these pages)

Mod-role restricted CRUD (horses/foals/competitions/showrecords/posts, create+edit only)
    └──requires──> Role-based page gating

Pending-deletion queue / admin approval inbox
    └──requires──> Role-based page gating (to know "this actor is mod, route delete → pending instead of hard/soft delete")
    └──requires──> Existing soft-delete convention on horses (is_deleted/deleted_at) as the pattern to extend or wrap
    └──enhances──> Mod-role restricted CRUD (delete action specifically)

Author-role restricted CRUD (own posts only, immediate delete)
    └──requires──> Role-based page gating
    └──requires──> posts.author_id column (NEW — does not exist yet)
    └──requires──> Ownership-filtered queries (WHERE author_id = current_user)

Author horse-linking via post_horses (read-only picker)
    └──requires──> existing post_horses table (ALREADY EXISTS — no schema change)
    └──requires──> Author-role restricted CRUD (the picker is embedded in the post edit form)

Self-service password change (all roles)
    └──requires──> nothing new beyond existing admin_users.password + session auth (already exists)

Admin reset-another-user's-password
    └──requires──> Admin user management CRUD
```

### Dependency Notes

- **Everything requires the role column + session role first.** This is the single foundational piece; every other v1.2 feature (user management, mod restrictions, author restrictions, pending-deletion routing) is gated behind knowing "who is this and what role are they."
- **Pending-deletion queue enhances mod-restricted CRUD, doesn't replace it.** Mod's create/edit stays a normal direct CRUD write (identical to admin's, just without user-management/theme access); only the *delete* action branches into "insert a pending-deletion record" instead of "set `is_deleted=1` directly" (or hard delete, for entities without a soft-delete column today — competitions/showrecords/foals currently have no `is_deleted` column, only `horses` does).
- **Schema decision point for the roadmap:** the existing `horses.is_deleted`/`deleted_at` pattern only exists on `horses`. Foals, competitions, showrecords, and posts have no soft-delete columns at all today. Two viable approaches, to be decided at planning/architecture time, not now:
  - (a) Add `is_deleted`/`deleted_at` to all 5 tables + a lightweight `pending_deletions` queue table (`entity_type`, `entity_id`, `requested_by`, `requested_at`, `status`, `resolved_by`, `resolved_at`) that admin approves/rejects — approve flips `is_deleted=1` on the target row, reject just removes the queue row.
  - (b) Skip touching the target tables and let the `pending_deletions` queue table alone gate visibility (join against it to hide "pending" rows from public + admin lists) — avoids a schema migration on 4 more tables but makes queries slightly more complex.
  - Given the app already established the `is_deleted` convention on `horses` in v1.0, **option (a) is more consistent with existing patterns** and is the recommended default for the roadmap, but this is exactly the kind of decision a phase-specific research/design pass should confirm once the phase is scoped.
- **Author ownership requires a new `posts.author_id` column** — this does not exist in the current schema and must be added as a migration (`migrate_*.sql`, following the existing `migrate_post_horses.sql` precedent) with a `FOREIGN KEY (author_id) REFERENCES admin_users(id)`.
- **`post_horses` needs no schema change** — the author's "link existing horses, read-only picker" reuses the exact existing many-to-many table; only the *UI* differs (author sees a read-only select-from-list, not a horse-editing form), enforced by role check, not schema.

## MVP Definition

### Launch With (v1.2 — matches PROJECT.md Active requirements exactly)

- [ ] `admin_users.role` column + session-based role gating — foundational, everything else depends on it
- [ ] Admin-only user management (create/edit role+username/deactivate-or-delete/reset password) — explicit milestone requirement
- [ ] Self-service password change for all roles — explicit milestone requirement, low complexity
- [ ] Mod-restricted CRUD (horses+photos/foals/competitions/showrecords/posts) with pending-deletion approval flow — explicit milestone requirement
- [ ] Author-restricted CRUD (own posts only, immediate delete, read-only horse-linking) — explicit milestone requirement, requires new `author_id` column

### Add After Validation (v1.x, if gaps surface in real use)

- [ ] Pending-approvals count badge/dashboard widget for admin (cheap add-on once queue table exists)
- [ ] Rejection reason field on pending-deletion decline
- [ ] `is_active` deactivation flag distinct from hard user delete (protects `author_id`/authorship FK integrity)

### Future Consideration (v2+, explicitly out of scope per PROJECT.md or disproportionate at this scale)

- [ ] Granular per-action permission matrix — defer indefinitely; 3 fixed roles are sufficient at 2-4 users
- [ ] General-purpose audit log — defer; minimal fields on pending_deletions cover the real need
- [ ] Multi-admin approval chains, expiring requests, password-strength/breach-checking — defer; no stated need and adds infra (cron) the free Altervista host can't reliably support

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Role column + session gating | HIGH | LOW | P1 |
| Admin user management CRUD | HIGH | MEDIUM | P1 |
| Self-service password change | HIGH | LOW | P1 |
| Mod restricted CRUD + pending-deletion queue | HIGH | MEDIUM | P1 |
| Author restricted CRUD (own posts + horse-link picker) | HIGH | MEDIUM | P1 |
| Pending-approvals dashboard badge | MEDIUM | LOW | P2 |
| Rejection reason on decline | LOW | LOW | P2 |
| `is_active` deactivation flag | MEDIUM | LOW | P2 |
| Granular permission matrix | LOW | HIGH | P3 (avoid) |
| Full audit log subsystem | LOW | HIGH | P3 (avoid) |

## Sources

- [Role Based Access Control in PHP — SitePoint](https://www.sitepoint.com/role-based-access-control-in-php/)
- [Implementing Role-Based Access Control (RBAC) in PHP — Medium](https://medium.com/@wwwebadvisor/implementing-role-based-access-control-rbac-in-php-85c0ea7bc86b)
- [How to implement role-based access control (RBAC)? — CloudDevs](https://clouddevs.com/php/role-based-access-control/)
- [PhpRbac.net docs](https://phprbac.net/docs_tutorial.php) (reference for the "over-engineered" full-permissions end of the spectrum, cited here as the anti-feature comparison point)
- [The Ultimate Multifunctional Database Table Design: Workflow States Pattern — Medium](https://medium.com/@herihermawan/the-ultimate-multifunctional-database-table-design-workflow-states-pattern-156618996549)
- [Soft deletes are tedious! — Medium/Geek Culture](https://medium.com/geekculture/soft-deletes-are-tedious-does-an-ideal-deletion-without-loss-even-exist-9cc5d78e9b10)
- [Soft delete vs hard delete — AppMaster](https://appmaster.io/blog/soft-delete-vs-hard-delete)
- [Avoiding the soft delete anti-pattern — Hacker News discussion](https://news.ycombinator.com/item?id=40326815)
- [Approval Workflow Design Patterns — Cflow](https://www.cflowapps.com/approval-workflow-design-patterns/)
- [Password Reset Best Practices — FastPass](https://www.fastpasscorp.com/why-fastpass/insights/password-reset-best-practices/)
- Internal: existing codebase (`database/schema.sql`, `public/admin/login.php`, `.planning/PROJECT.md`) — used to ground all dependency/complexity claims against the actual current schema and auth pattern, this is the highest-confidence input to this document.

**Confidence caveat:** External web findings above are general-knowledge-level (LOW/MEDIUM per source-hierarchy classification — no single authoritative framework doc applies since this is a "plain PHP, no framework" context) and reflect common, cross-corroborated patterns rather than a single canonical spec. The HIGH-confidence portion of this document is the internal codebase grounding (schema, existing conventions), which was read directly rather than inferred.

---
*Feature research for: role-based access control + soft-delete approval workflow (small single-stable PHP/MySQL admin panel)*
*Researched: 2026-07-05*
