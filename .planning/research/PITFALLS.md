# Pitfalls Research

**Domain:** Retrofitting role-based access control (RBAC) + delete-approval workflow onto an existing single-admin PHP/MySQL admin panel (no framework, no ORM, no router)
**Researched:** 2026-07-05
**Confidence:** MEDIUM-HIGH (codebase-specific findings are HIGH — verified by direct code inspection; general RBAC/IDOR/session pitfalls are MEDIUM — cross-checked web sources)

This research is grounded in the actual codebase: `requireLogin()` in `public/src/includes/helpers.php`, ~30 individual `admin/*.php` files that each call it, the flat `admin_users` table in `database/schema.sql`, the existing `horses.is_deleted`/`deleted_at` soft-delete pair, and the hard-delete pattern used by `posts`, `foals`, `competitions`, `showrecords` today.

## Critical Pitfalls

### Pitfall 1: Copy-pasted `requireLogin()` means role-gating one page at a time — one file gets missed

**What goes wrong:**
There is no central router or middleware. Every admin page (`admin/horses.php`, `admin/posts.php`, `admin/settings.php`, `admin/foal_add.php`, `admin/photo_delete.php`, `admin/api/vrl_import_save.php`, etc. — currently ~30 files) independently opens with `require_once __DIR__ . '/../src/includes/db.php'; requireLogin();`. Adding roles means every one of these call sites must be changed to something like `requireRole(['admin','mod'])`, plus every new file (user management, password change, approval queue) must get the right check too. Because the pattern is "copy the top three lines into a new file," it is trivial to create a new admin page, copy an old file as a template, and forget to tighten (or even include) the role check — especially for `admin/settings.php`-style admin-only pages, `contacts.php`/`contact_add.php`/`contact_edit.php` (not explicitly mentioned in the milestone scope but currently reachable by any logged-in user), and any new `admin/users*.php` files.

**Why it happens:**
`requireLogin()` is boolean (logged in or not) — there is no dimension for "which role." Retrofitting role checks onto ~30 independently-authored files with copy-paste heritage is exactly the scenario where broken access control tops the charts: enforcement is inconsistent because it's manual and per-file, not centralized.

**How to avoid:**
- Write a single `requireRole(string ...$allowed): void` helper in `helpers.php` (or a new `auth.php`) that reads `$_SESSION['admin_role']` and redirects/403s if not in the allowed set. Keep `requireLogin()` as a convenience wrapper (`requireRole('admin','mod','author')`).
- Build one authoritative table mapping **every** existing `admin/*.php` file (including `contacts.php`, `contact_add.php`, `contact_edit.php`, `contact_delete.php`, `sukulaiset.php`, `horse_import_vrl.php`, `api/vrl_import_save.php`, `kuvat_all.php`, `photos.php`, `photo_update.php`, `photo_delete.php`) to its required role(s) *before* writing code, so nothing is "forgotten because it wasn't on the milestone's feature list."
- Add an automated check (grep-based test or a small PHP script run in CI/pre-commit) that fails the build if any file under `admin/*.php` does not contain a `requireRole(` or `requireLogin(` call in its first ~10 lines.
- Since `settings.php` (theme selection) and user management are explicitly admin-only per PROJECT.md, verify those two are the first ones tightened and manually re-tested with a mod/author session.

**Warning signs:**
- A new admin page renders successfully for a `mod` or `author` session when it shouldn't.
- Any `admin/*.php` file whose first executable lines don't include a role check.
- Sidebar nav in `admin_header.php` still shows a link for a section the current role can't reach (implies the link, not the underlying page, was gated — a common half-fix).

**Phase to address:**
The phase that introduces the `role` column and the shared `requireRole()` helper — this must happen *before* or *together with* wiring individual pages, and should include an explicit audit pass over every existing `admin/*.php` file, not just the ones named in the milestone's feature list.

---

### Pitfall 2: IDOR on `posts.php?action=edit&id=X` and `post_delete.php` — author can edit/delete another author's post by changing the ID

**What goes wrong:**
Today `admin/posts.php` and `admin/post_delete.php` take `$_GET['id']` / `$_POST['id']` and operate on any post ID with zero ownership check — there's only `requireLogin()` and a CSRF check. That's fine while there's one admin, but once `author_id` is added and authors are restricted to "own posts only," this exact code path is the textbook IDOR: a logged-in author who edits their own post can simply change `id=` in the URL (view-source is enough, no guessing needed since IDs are sequential) or change the hidden `id` field in the delete form to touch someone else's post. CSRF protection does **not** prevent this — the token proves the request came from the app's own form, not that the target ID belongs to the requester.

**Why it happens:**
CSRF and ownership are different axes of security and are easy to conflate. The current code was written for a single-admin model where "any logged-in admin can touch any post" was the correct behavior, so no ownership filter exists anywhere in the query. When author role is bolted on, the missing filter becomes a vulnerability rather than a non-issue.

**How to avoid:**
- Every read/update/delete on `posts` (and eventually any "own resource" scoped table) must add `AND author_id = :current_user_id` to the `WHERE` clause when the session role is `author` — enforced server-side in the query itself, not just hidden from the UI list.
- Do **not** rely on only showing the author their own posts in the list view (`posts.php` list rendering) — the edit/delete endpoints must independently re-verify ownership because the attacker skips the list and goes straight to the URL.
- For `admin`/`mod`, no ownership filter is needed (they can touch all posts per the milestone spec) — but the check must branch on role, not be removed entirely.
- Apply the same pattern check to `post_horses` writes inside `posts.php`'s POST handler: an author editing their own post can currently submit arbitrary `horse_ids[]` — confirm this is intended (milestone says "read-only valinta" from existing horses, which is fine) but do not let an author's edit_id bypass into modifying a post they don't own via a forged `edit_id` hidden field.
- Write a negative test: log in as author A, attempt to load `posts.php?action=edit&id=<author B's post>` and `post_delete.php` with author B's post ID — must fail/redirect, not silently succeed.

**Warning signs:**
- Any query against `posts` that does not include `author_id` in its `WHERE` clause when the caller's role is `author`.
- The edit form's `edit_id` hidden field trusted without re-checking who owns that ID after CSRF passes.
- List view filters by author but detail/edit/delete endpoints don't.

**Phase to address:**
The author-permissions phase (author own-post CRUD) — this is the single highest-priority item in that phase and should be the first thing tested, since the existing code has zero ownership scaffolding to build on (no `author_id` column exists yet either — see Pitfall 9).

---

### Pitfall 3: Session caches the role at login time — demotion/deactivation doesn't take effect until re-login

**What goes wrong:**
`login.php` currently sets `$_SESSION['admin_logged_in']`, `$_SESSION['admin_id']`, `$_SESSION['admin_username']` once, at login, and nothing re-reads the DB on subsequent requests. If `role` is added the same way (cached in session at login), then when an admin demotes a mod to author, deactivates a user, or deletes a user outright, that user's *already-open session* keeps operating with the old role/privileges until they happen to log out or the session naturally expires (30 min `gc_maxlifetime` per `db.php`) — that's up to 30 minutes of privilege that should have been revoked immediately, including a **deleted** user's session still working.

**Why it happens:**
Reading the role from `$_SESSION` on every request is fast and simple, and it's the obvious way to extend the existing `isLoggedIn()` pattern. But it means the session, not the database, becomes the source of truth for authorization — which is fine for "logged in or not" (rarely changes mid-session) but wrong for "role" and "active/deleted" (which an admin can change *specifically to cut someone off immediately*, e.g., firing a mod).

**How to avoid:**
- Either (a) re-fetch `role` and an `is_active`/`deleted_at` flag from `admin_users` on every admin request (cheap — one indexed `SELECT` by primary key, already paying for a DB roundtrip on most admin pages anyway), or (b) keep session-cached role but add a lightweight invalidation mechanism (e.g., a `sessions_invalidated_at` timestamp column on `admin_users`, checked against a session-start timestamp) so demotions/deactivations take effect on the next request, not next login.
- Given the scale of this app (single small talli, handful of users), option (a) — re-verify against the DB each request — is simpler to implement correctly and cheap enough to not worry about performance.
- Explicitly decide and test: what happens to a currently-logged-in session when admin deletes that user? It must not be able to complete another authenticated action.

**Warning signs:**
- `role` only ever read from `$_SESSION`, never re-queried against `admin_users`.
- No test exercises "demote user mid-session, confirm their next request is restricted."
- No test exercises "delete user mid-session, confirm their next request is rejected."

**Phase to address:**
The user-management phase (admin creates/edits/deactivates/deletes users) — the deactivation/deletion feature is not actually complete without solving session staleness; call this out explicitly in that phase's acceptance criteria.

---

### Pitfall 4: Last admin account can be demoted or deleted, locking the whole system out of admin-only functions

**What goes wrong:**
User management, theme/settings access, and deletion-approval are all admin-only per the milestone spec. If the user-management delete/role-change form doesn't special-case "this is the only remaining admin," an admin could delete their own account, delete the only other admin, or demote the last admin to mod/author — leaving the system with zero users who can approve pending deletions, manage users, or change settings. Recovery would require direct DB access (Altervista has phpMyAdmin, so it's recoverable, but it's a self-inflicted outage).

**Why it happens:**
Single-admin systems never had this class of bug because there was exactly one admin and no delete-user feature at all. The generic "admin can manage users" CRUD, if implemented uniformly (same edit/delete form for every user row regardless of role or count), doesn't special-case the last-admin scenario — it's an edge case that only exists once you introduce >1 admin capability.

**How to avoid:**
- Before allowing a role change away from `admin` or a delete of an `admin` user, run `SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND (is_active flag if added)` and block the action with a clear error if the count would drop to 0.
- Apply this check on both the "change role" and "delete user" code paths — don't just guard one.
- Decide whether an admin can deactivate/delete **their own** account at all (see Pitfall 5) — if self-deletion is disallowed outright, this mostly collapses into "can't delete the only other admin," which is simpler to reason about.

**Warning signs:**
- Delete-user and change-role forms have no query counting remaining admins.
- No test exists for "attempt to delete the second-to-last admin when only two admins exist" (should succeed) vs. "the last admin" (should fail).

**Phase to address:**
User-management phase — this is a required acceptance check for that phase, not an edge case to defer.

---

### Pitfall 5: Admin can delete or demote their own account mid-session (accidental self-lockout)

**What goes wrong:**
A generic "edit/delete any user" admin UI, applied uniformly to all rows including the currently-logged-in admin's own row, lets an admin accidentally delete themselves or demote themselves to `mod`/`author` while working through a list. Combined with Pitfall 3 (session staleness), the effect might not even be visible until the next request, making it confusing to debug ("why can't I access settings anymore?").

**Why it happens:**
It's the natural result of building one generic "manage users" table/form without special-casing "is this row the current session's user?" It is very easy to miss because during development/testing there's typically only one admin account logged in, so self-targeting bugs don't surface until a second real admin exists.

**How to avoid:**
- In the user-management UI, disable/hide the delete and role-change actions for the row matching `$_SESSION['admin_id']` (self), or require a distinct "you are about to modify your own account" confirmation step.
- Self-service password change (which every role can do) is the correct, separate path for a user to change their *own* credentials — it should not be reachable through the admin user-management delete/role-change controls at all.
- Server-side, not just UI-side: guard the actual handler (`user_edit.php`/`user_delete.php` equivalent) against `target_id === $_SESSION['admin_id']` for the destructive role-change/delete actions, since UI-only disabling can be bypassed by a direct POST.

**Warning signs:**
- The user-management delete/edit form renders action buttons identically for every row, including the current session's own user.
- No server-side check comparing target user ID to session user ID on the delete/role-change handler.

**Phase to address:**
User-management phase, alongside Pitfall 4 — both are "who can I not delete/demote" rules that belong in the same guard function (e.g., `canModifyUser($targetId, $targetRole)`).

---

### Pitfall 6: Password change/reset doesn't regenerate the session ID — leftover session-fixation window

**What goes wrong:**
`login.php` correctly calls `session_regenerate_id(true)` after a successful login — but that's the *only* place a session ID gets regenerated. If self-service password change and admin-initiated password reset are added without also calling `session_regenerate_id(true)` after a successful change, any session ID that existed before the password change (e.g., fixated by an attacker via a shared/public computer, or simply a second concurrent tab) remains valid and privileged after the "security-sensitive" change — defeating much of the point of letting users rotate a compromised password.

**Why it happens:**
It's easy to implement password-change as "just an UPDATE query" and stop there, because the login flow's regeneration already happened once and the developer doesn't revisit session handling for a feature that "isn't login."

**How to avoid:**
- After every successful password change (self-service) and every admin-initiated password reset, call `session_regenerate_id(true)` for the *currently active* session performing the change.
- For an *admin resetting someone else's password* (a different session than the one being changed), also consider invalidating the target user's other active sessions — with PHP's default file-based sessions this isn't automatic; the practical option at this app's scale is the Pitfall 3 mitigation (re-check role/active-state from DB every request) combined with forcing a "must change password" flag so their next login is intercepted regardless of any lingering session.
- CSRF-protect the password-change form exactly like every other form here (`validate_csrf_token`) — there's no reason to deviate from the existing pattern, but it's easy to forget on a "simple" form.
- Require the user's **current password** as part of self-service password change (not just new password twice) — otherwise a hijacked-but-still-logged-in session (e.g., an unlocked browser) lets an attacker lock the real user out by changing the password without ever knowing it.

**Warning signs:**
- Password-change handler doesn't call `session_regenerate_id()`.
- Password-change form is missing the CSRF hidden input (easy to miss since it's a new form, not copy-pasted from an existing delete-button pattern).
- Self-service password-change doesn't ask for the current password.

**Phase to address:**
The self-service password change phase, and again in the admin-reset-password phase (different trigger, same fix).

---

### Pitfall 7: Admin-forced password reset leaks the new password or doesn't force a change

**What goes wrong:**
"Admin resets another user's password" implies a temporary/generated password must reach that user somehow. Two failure modes: (a) the new plaintext password is displayed/logged/emailed insecurely — e.g., left visible in server error logs, in the URL as a GET parameter, or (worse) emailed in plaintext when this app has no outbound email infrastructure at all (Altervista free tier — no SMTP configured today), so "email the new password" isn't actually available and a naive implementation might try to log it to a file admins can read, or display it once on-screen without forcing rotation; (b) the reset password is left permanently active — the affected user never has to (or is prompted to) change it, so the admin (who now knows the plaintext) retains indefinite access to that user's account.

**Why it happens:**
There's no existing password-reset flow in this codebase to copy from (bcrypt + login is the only password logic that exists today), and no email-sending capability, so whoever implements this has to invent the delivery mechanism from scratch under hosting constraints (Altervista shared hosting, no shell access) — the path of least resistance is "show the generated password once on the admin's screen" which is acceptable *only if* paired with a forced-change-on-next-login flag.

**How to avoid:**
- Generate a random temporary password server-side (e.g., via `random_bytes`/`bin2hex`, same primitive already used for `generate_safe_filename()` and CSRF tokens), hash it with `password_hash()` before storing, and display the plaintext **once**, only to the admin performing the reset, in the response of that single request (never logged, never emailed given no email infra, never stored anywhere in plaintext).
- Add a `must_change_password` (or `password_reset_at`) flag on `admin_users`; on next login with a flag set, redirect the user to the password-change form before granting access to anything else — enforced server-side (`requireLogin()`/`requireRole()` should check this flag and redirect regardless of the requested page).
- Since delivery is manual (admin tells the user the temp password out-of-band, e.g., verbally or via whatever chat/forum the talli uses — there is no in-app email), document this as the expected workflow rather than trying to bolt on email sending that doesn't exist in this hosting environment.
- Never let the reset form set `must_change_password = 0`; only clearing it via the user's own successful password change should reset the flag.

**Warning signs:**
- Temporary password written to any log file, or included in a redirect URL as a query parameter.
- No `must_change_password`-style flag/enforcement — user can keep using the admin-assigned password indefinitely without ever proving they know it was rotated for a reason.
- Reset-password admin action doesn't force `session_regenerate_id()` / doesn't interact with Pitfall 3's staleness fix for the target account's existing session.

**Phase to address:**
User-management phase (admin resets others' passwords) — pair with the `must_change_password` schema addition and force-redirect logic, tested as its own acceptance case.

---

### Pitfall 8: `foals`, `competitions`, `showrecords`, `posts` have no soft-delete columns yet — the pending-deletion workflow has nowhere to "park" a mod's delete request

**What goes wrong:**
Only `horses` has `is_deleted`/`deleted_at` today (confirmed in `database/schema.sql`); `foals`, `competitions`, `showrecords`, and `posts` are hard-deleted via plain `DELETE FROM ... WHERE id = :id` (see `post_delete.php`). The milestone requires a mod-initiated delete on **any** of the 5 content types to go into a pending/approval state rather than executing immediately — but there is currently no data model that represents "this row is proposed for deletion but still exists and is still visible everywhere it always was." Without adding an equivalent status (or a dedicated `pending_deletions` queue table), a naive implementation will either (a) actually run the `DELETE` immediately regardless of role — defeating the whole approval requirement — or (b) hastily bolt an `is_deleted`-style column onto 4 more tables with inconsistent semantics from table to table (e.g., `foals` already has its own `status` ENUM('born','expected') that a careless migration could collide with).

**Why it happens:**
The milestone description names this exact gap ("most of which currently lack soft-delete entirely") — it's a known, deliberate scope item, not a surprise. The risk is in *execution*: treating "add pending-deletion" as a UI/workflow feature while forgetting it's fundamentally a schema migration across 4 tables that must happen first, and each table has different existing constraints to respect (`foals.status` is unrelated to deletion status; `showrecords`/`competitions` cascade-delete from `horses` via `ON DELETE CASCADE` already).

**How to avoid:**
- Prefer a single generic `pending_deletions` queue table (`id`, `content_type` ENUM/VARCHAR, `content_id`, `requested_by` (admin_users.id), `requested_at`, `status` ENUM('pending','approved','rejected'), `reviewed_by`, `reviewed_at`) over adding bespoke `is_deleted`/`deleted_at`/`pending_deletion` columns to 4 separate tables. This keeps the schema change small (one new table) and the approval-queue UI trivial to build as a single cross-content-type list, rather than 4 near-duplicate ones.
- Still add a *soft-delete pair* (`is_deleted`, `deleted_at`) to `posts`, `foals`, `competitions`, `showrecords` matching the `horses` pattern already proven in production — the actual "delete" that happens on admin approval should be this soft-delete (never an actual `DELETE FROM`), consistent with how `horse_delete.php` already works (`UPDATE horses SET is_deleted = 1, deleted_at = NOW()`), not a hard delete. This also means existing FK `ON DELETE CASCADE` relationships (e.g., `showrecords.photo_id → horse_photos`, `post_horses → posts`) are irrelevant to the new flow since no row is ever hard-deleted through the new path.
- For **mod**-initiated deletes: `mod` role never runs `UPDATE ... SET is_deleted=1` directly — it only inserts a `pending_deletions` row. For **admin**-initiated deletes: admin can delete directly (immediate soft-delete, matching current admin-only behavior) — i.e., keep two code paths (`requestDeletion()` for mod, `deleteNow()` for admin) sharing the same underlying soft-delete function once approved/executed.
- Explicitly decide, and test, whether "delete" of `posts` when author-initiated is *immediate* (per milestone: "author... välitön poisto" — yes, immediate) vs. mod-initiated on `posts` which goes through approval. Same content type, different workflow depending on which role clicked delete — this branching needs a single well-tested `canDeleteImmediately($role, $ownerCheck)` helper, not duplicated per-file logic.

**Warning signs:**
- A "pending deletion" feature ships but the underlying `DELETE FROM` statement in the delete-handler file was never actually removed/branched — the row is gone the instant a mod clicks delete, admin approval box is cosmetic only.
- 4 different ad-hoc "is_pending" columns with inconsistent naming/semantics across `foals`/`competitions`/`showrecords`/`posts`.
- No migration adds `is_deleted`/`deleted_at` to the 4 non-horse content tables before the approval-queue UI is built.

**Phase to address:**
A dedicated schema-migration phase (soft-delete columns on 4 tables + `pending_deletions` queue table) that must land *before* the pending-deletion-workflow/approval-queue phase — sequencing matters here because the UI phase has nothing to build against otherwise.

---

### Pitfall 9: `posts` has no `author_id` column — author-ownership literally cannot be checked until this migration lands

**What goes wrong:**
The milestone requires authors to CRUD only their own posts (`posts.author_id`, per PROJECT.md's own phrasing) — but today's `posts` schema (confirmed in `database/schema.sql`) has no such column. Every existing post was created under the single flat-admin model with no author attribution. If the author-ownership phase is planned as "just add the WHERE clause" without first (a) adding the column, (b) backfilling existing rows with a sensible default (e.g., the original admin, or NULL treated as "admin/mod-owned, not author-owned"), and (c) setting `author_id` on every future insert, the ownership check in Pitfall 2 has no column to filter on.

**Why it happens:**
It's tempting to think of "add author ownership" purely as an application-logic change (add a `WHERE author_id = ...`) when it is first and foremost a schema change with a backfill/migration-ordering dependency.

**How to avoid:**
- Migration: `ALTER TABLE posts ADD COLUMN author_id INT UNSIGNED DEFAULT NULL, ADD CONSTRAINT fk_posts_author FOREIGN KEY (author_id) REFERENCES admin_users(id) ON DELETE SET NULL;` — nullable and `ON DELETE SET NULL` so deleting a user (see Pitfall 4/5 area) never cascades into deleting their posts.
- Backfill existing rows explicitly (e.g., set `author_id` to the current sole admin's ID, or leave NULL and treat NULL as "not author-owned, editable only by admin/mod") — pick one and document it, don't leave it ambiguous.
- Every `INSERT INTO posts` path must set `author_id` going forward — currently `posts.php`'s insert (`INSERT INTO posts (title, slug, content) VALUES (...)`) does not; this must be updated to include `author_id => $_SESSION['admin_id']` when the creator is an author (and probably also when mod/admin creates one, so provenance isn't lost, even though mod/admin aren't restricted by it).

**Warning signs:**
- Author-ownership WHERE-clause work starts before the `author_id` column migration exists.
- New post inserts still omit `author_id`.
- No decision recorded for what NULL `author_id` on legacy posts means for the new access rules.

**Phase to address:**
Same schema-migration phase as Pitfall 8 (or immediately adjacent) — must precede the author-permissions phase.

---

### Pitfall 10: Ambiguous visibility of a "pending deletion" item — mods, other admins, and the public site disagree on what they see

**What goes wrong:**
Once a mod requests deletion of, say, a horse, several audiences need a consistent answer to "is this horse still there?": (a) the public site (should the horse still show on the public horse-listing/profile while deletion is merely *pending*, or should it be hidden immediately on request, i.e., as if soft-deleted, pending only *reversal* on rejection?); (b) other mods browsing the admin panel (should they see it's "pending deletion" and be blocked from also editing it, or can they still edit it while a deletion is pending?); (c) child data — `horse_photos`, `showrecords`, `competitions`, `foals` referencing that horse, and `post_horses`/pedigree (`sire_id`/`dam_id`) references *from other horses* pointing at it. If the horse is hidden from the public site the moment a deletion is *requested* (not yet approved), then rejecting the request must fully restore public visibility — and in the meantime, any horse profile page rendering a pedigree (`getHorsePedigree()` in `helpers.php`) that recurses through `sire_id`/`dam_id` will hit a horse mid-limbo state and needs a defined behavior (show as normal? show "unknown"? the current pedigree query already filters `WHERE h.is_deleted = 0`, so if "pending" reuses that same flag, ancestors of *other* horses would silently disappear from public pedigree charts the moment deletion is merely requested — likely not desired).

**Why it happens:**
"Pending" is a third state layered onto what was previously a boolean (`is_deleted` 0/1). If the pending-deletion queue table approach from Pitfall 8 is used, this resolves cleanly (pending is *only* a row in `pending_deletions`, `is_deleted` stays 0 until actually approved — so nothing changes publicly until approval, exactly matching "admin approves → then it's actually deleted"). The pitfall is real only if someone instead tries to represent "pending" *inside* the `is_deleted` column (e.g., `is_deleted = 2` meaning pending) — which breaks every existing query that only checks `is_deleted = 0`, including the public pedigree recursion, competition listings, and photo galleries.

**How to avoid:**
- Adopt the Pitfall 8 recommendation explicitly for this reason: a **separate** `pending_deletions` table means `is_deleted` never has a third value, so every existing public/query path (including recursive pedigree) continues to work unmodified while something is merely "pending." The row is fully live and visible everywhere until actually approved.
- In the admin UI only, join against `pending_deletions` to show a "pending deletion — awaiting admin approval" badge on the relevant list rows (horses.php, foals.php, etc.), and block a **second** deletion request for the same `(content_type, content_id)` while one is already `status='pending'` (see Pitfall 11).
- Decide explicitly: can other mods still *edit* a horse/post/etc. that has a pending deletion request against it? Recommend: yes, editing remains unrestricted until the deletion is actually approved (simplest rule, avoids a second kind of lock contention) — but flag this decision for the roadmap/spec phase rather than leaving it implicit.
- When admin **approves** a pending deletion, that's the moment the existing soft-delete `UPDATE ... SET is_deleted=1, deleted_at=NOW()` actually runs — at which point all existing "is_deleted=0" filters (including pedigree, listings) correctly and immediately stop showing it, exactly like the current `horses` behavior today.

**Warning signs:**
- `is_deleted` gains a third possible value ("pending") anywhere in the schema or code.
- Public-facing pedigree/listing/gallery pages change behavior the moment a deletion is *requested* rather than *approved*.
- No UI indicator in the admin panel distinguishing "normal" rows from "pending deletion" rows.

**Phase to address:**
Pending-deletion-workflow phase, directly dependent on the schema-migration phase (Pitfall 8) choosing the queue-table approach.

---

### Pitfall 11: Two mods request deletion of the same item — duplicate/conflicting queue entries

**What goes wrong:**
If mod A requests deletion of horse #12, and (before admin reviews it) mod B — unaware of A's request — also clicks "delete" on horse #12, a naive implementation either inserts a second `pending_deletions` row for the same `(content_type, content_id)` (now the admin approval queue shows two entries for one horse, and approving/rejecting one leaves the other dangling and semantically meaningless), or throws an unhandled DB error if a duplicate is prevented by a unique constraint only at the DB level with no friendly UI message.

**Why it happens:**
Concurrent requests for the same resource are an easy case to overlook when building single-user-tested workflows — the developer testing alone will never trigger it.

**How to avoid:**
- Add a unique constraint / partial-unique behavior: only one `pending_deletions` row per `(content_type, content_id)` where `status = 'pending'` — enforce via `UNIQUE KEY (content_type, content_id, status)` is not quite right in MySQL (status varies), so instead check-then-insert inside the request handler (`SELECT ... WHERE content_type=? AND content_id=? AND status='pending'` before `INSERT`), and treat a second request as a no-op with a friendly "already pending, requested by X on Y" message rather than a silent duplicate or a raw SQL error.
- Show, on the delete-request confirmation UI, if an item is already pending (from Pitfall 10's admin-side badge) — ideally the "delete" button itself is disabled/relabeled "deletion already requested" once a pending row exists, preventing the double-click race in the common case, with the server-side check as the actual authority.

**Warning signs:**
- No `SELECT ... WHERE status='pending'` guard before inserting a new `pending_deletions` row.
- Approval queue UI can show 2+ rows referencing the same content item.

**Phase to address:**
Pending-deletion-workflow phase — a concrete acceptance test: "mod A and mod B both request deletion of the same horse; only one pending entry should exist, and the UI should communicate this to mod B."

---

### Pitfall 12: Admin rejects a deletion request — no notification, no state reset, mod repeats the request forever

**What goes wrong:**
When admin rejects a pending deletion, the `pending_deletions` row needs to move to a terminal `status='rejected'` state — but if the UI/query for "does this item have an active pending request" only checks `status='pending'` and a rejected row is left around unmarked or (worse) simply deleted from the queue table with no trace, then: (a) the originating mod has no way to know their request was rejected (no in-app notification system exists in this app at all today) and may resubmit it repeatedly; (b) there's no audit trail of who requested what and why it was rejected, which matters for a talli where a mod and admin might disagree about whether a horse/post should really be removed.

**Why it happens:**
"Reject" is the least-tested path of any approval workflow (approve is the "happy path" everyone tests first) and is often implemented as an afterthought — e.g., just deleting the row from the queue with no state transition, since this app has no notification/email infrastructure to build a "your request was rejected" message on top of.

**How to avoid:**
- Never hard-delete a `pending_deletions` row on rejection — transition it to `status='rejected'`, set `reviewed_by`/`reviewed_at`, and optionally a free-text `review_note` column so admin can leave a reason.
- Since there's no notification system, surface rejected requests directly in the admin panel: when the mod who made the request next visits that content type's list (e.g., `foals.php`), show a small "your deletion request for X was rejected on [date]" banner/badge sourced from `pending_deletions WHERE requested_by = current_user AND status='rejected' AND [not yet acknowledged]` — with a simple "dismiss" action (an `acknowledged_at` column) so it doesn't show forever.
- Allow the same mod to submit a **new** pending-deletion request for the same item after a prior one was rejected (don't permanently block re-requesting — circumstances change) — the uniqueness guard from Pitfall 11 only applies to *simultaneous pending* rows, not historical rejected ones.

**Warning signs:**
- Rejecting a request deletes the row instead of transitioning its status.
- No way for the requesting mod to ever learn their request was rejected.
- Re-requesting deletion of the same item after a rejection is blocked entirely (over-correction of Pitfall 11's guard).

**Phase to address:**
Pending-deletion-workflow phase — "reject" path needs equal test coverage to "approve," including the mod-facing acknowledgment.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|-----------------|------------------|
| Copy-paste `requireRole(...)` call into each `admin/*.php` file instead of building a router/front-controller | No architectural rewrite needed; matches existing "PHP includes" pattern (a documented Key Decision) | Every future new admin page is one more place to forget the check; audits must re-scan every file each time | Acceptable for this project's scale (small file count, single small team) *if* paired with the automated grep-based check from Pitfall 1 |
| Session-cached role, re-verified against DB on every request (Pitfall 3 fix) instead of a proper token/claims cache with explicit invalidation | Simple to implement, no new invalidation infrastructure | One extra indexed `SELECT` per admin request — negligible at this scale but would not scale to a high-traffic app | Always acceptable here; this is a small single-talli admin panel, not a high-traffic system |
| Single generic `pending_deletions` queue table instead of bespoke `is_pending` columns on each of the 4 content tables | One schema addition instead of four; one approval-queue UI instead of four near-duplicates | Slightly less "obvious" when reading a single table's schema in isolation (have to know to join `pending_deletions`) | Always acceptable — this is the recommended approach, not really a shortcut |
| Admin resets password by displaying a generated plaintext password once on screen (no email flow) | No email infrastructure needed (none exists on Altervista free tier today) | Admin necessarily knows the user's new password until the user changes it — must be paired with forced-change flag | Acceptable only paired with `must_change_password` enforcement (Pitfall 7); never acceptable without it |
| Treating `role` as a raw string compared inline (`$_SESSION['admin_role'] === 'admin'`) scattered across files instead of centralizing in `requireRole()`/constants | Fast to write | Typos (`'Admin'` vs `'admin'`) silently fail closed or open depending on the check's direction; hard to grep for all role checks | Never — centralize role constants and the comparison helper from day one, this is cheap to do right |

## Integration Gotchas

This retrofit has no external service integrations (no email provider, no OAuth, no third-party session store) — the "integration" here is entirely with the existing single-file-per-page PHP structure and Altervista shared hosting.

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|-------------------|
| Existing `requireLogin()`/session pattern (`helpers.php`) | Writing a parallel, inconsistent `requireRole()` that duplicates session-reading logic instead of extending the existing helper file | Add `requireRole()` next to `requireLogin()` in `helpers.php`, reusing `isLoggedIn()` as the first check, so there is exactly one place session/auth logic lives |
| Altervista shared hosting (no shell access, no cron, no queue workers) | Designing the approval workflow assuming background jobs (e.g., "auto-expire pending requests after N days" via cron) | Any time-based logic (e.g., auto-expiring old pending requests) must be evaluated lazily on page load (check timestamp when the queue is viewed), not via a scheduled job — there is no cron available |
| PHP default file-based sessions | Assuming a reset-password admin action can force-invalidate another user's *live* session (would require a shared session store queried by user ID) | Accept that a resettable target session can't be killed instantly on this hosting; rely on the DB re-check (Pitfall 3) plus `must_change_password` (Pitfall 7) as the practical mitigation instead |
| `horses.is_deleted` pattern (only existing precedent) | Inventing a different soft-delete convention for `foals`/`competitions`/`showrecords`/`posts` (different column names, different semantics) | Mirror the exact `is_deleted TINYINT(1)` + `deleted_at TIMESTAMP NULL` pair already proven in production on `horses` |

## Performance Traps

This app is a small single-talli admin panel; none of these pitfalls are performance risks at its expected scale (a handful of users, hundreds of horses at most). Re-verifying role/active-state from the DB on every request (Pitfall 3) is the only "cost" introduced, and it is negligible (one indexed lookup by primary key) — not worth optimizing away with caching at this scale.

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|-----------------|
| Re-querying `admin_users` for role/active-state on every admin request | None expected at this scale | N/A — acceptable as-is | Would only matter at hundreds of concurrent admin users, far beyond this app's realistic scale |
| Pending-deletions queue table missing an index on `(content_type, content_id, status)` | Slow "is already pending" checks as queue grows | Add the composite index at creation time (Pitfall 11's guard query depends on it) | Not a real risk given expected data volume, but free to get right from the start |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Trusting a `role` value submitted from a client-side form (e.g., a hidden `<input name="role" value="admin">` echoed back into an edit-user form) | Privilege escalation — a mod could submit `role=admin` directly to the user-edit handler | Server-side handler must only accept role changes performed *by an admin, on someone else's row*; never trust a role value in a request where the target is the requester themselves, and always re-validate the acting user's own role from session/DB before honoring any role-change request |
| Mass-assignment-style `UPDATE admin_users SET ... WHERE id = :id` built from all `$_POST` fields rather than an explicit whitelist | An attacker adds an unexpected `role` or `password_hash` field to a form submission that wasn't meant to allow it | Always build `UPDATE`/`INSERT` statements against explicitly named fields (this codebase already does this correctly everywhere observed — e.g., `posts.php`'s `UPDATE posts SET title=:t, slug=:s, content=:c` — continue that discipline for `admin_users` writes) |
| Role check performed only in the UI (hiding a nav link or button) without a matching server-side check on the handler it points to | Direct POST/GET to the handler bypasses the UI entirely | Every gate must exist in the PHP file that performs the action, not only in `admin_header.php`'s nav rendering (which already conditionally shows/hides links by `$_activePage` — this pattern must not be mistaken for actual authorization) |
| CSRF token is a single global `$_SESSION['csrf_token']` (not per-form/per-action) | Not specific to this retrofit, but worth re-confirming: any new destructive action (delete-user, approve/reject deletion, reset-password) must still call `validate_csrf_token()` — easy to skip on a "new" form type not copy-pasted from an existing delete button | Continue using `generate_csrf_token()`/`validate_csrf_token()` on every new state-changing form exactly as done for existing delete forms; do not introduce a second CSRF mechanism |
| Author's `post_horses` horse-picker (read-only selection of existing horses) trusts submitted `horse_ids[]` without checking they reference non-deleted, existing horses | An author could submit an arbitrary/stale horse ID (including a soft-deleted one) into `post_horses`, linking a post to a horse that shouldn't be publicly referenced anymore | Validate every submitted horse ID against `SELECT id FROM horses WHERE id = ? AND is_deleted = 0` before inserting into `post_horses`, exactly mirroring the existing `$allHorses` query's `WHERE is_deleted = 0` filter used to populate the picker in the first place |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-------------------|
| Mod sees no feedback after requesting a deletion — item just "stays there" | Mod is unsure whether their delete click worked at all, may click repeatedly | Immediate flash message ("Poistopyyntö lähetetty, odottaa hyväksyntää") plus a persistent "pending deletion" badge on that row so the mod can self-verify later |
| Admin's approval queue doesn't show *why* a mod wants something deleted or which content it refers to by name | Admin has to cross-reference IDs manually before approving/rejecting | Store and display a human-readable label (horse name, post title, etc.) and optionally a free-text reason field on the request, not just `content_type`+`content_id` |
| Author is silently redirected/blocked when trying to reach a page outside their scope, with no explanation | Confusing "nothing happened" experience, looks like a bug | A clear "Sinulla ei ole oikeutta tähän sivuun" message before redirecting, consistent with the existing Finnish-language flash-message style already used elsewhere (`flash-err`, `flash-ok` classes in `admin_header.php`'s CSS) |
| Sidebar nav (`admin_header.php`) shows every link regardless of role, even though the underlying page redirects away | User clicks a link, gets bounced, mildly confusing | Conditionally render sidebar nav items based on `$_SESSION['admin_role']` matching the same logic as `requireRole()`, so the UI and the enforcement agree |

## "Looks Done But Isn't" Checklist

- [ ] **Role column added:** Verify every single existing `admin/*.php` file (not just the ones named in the milestone's bullet list — also `contacts.php`, `contact_add.php`, `contact_edit.php`, `contact_delete.php`, `sukulaiset.php`, `horse_import_vrl.php`, `api/vrl_import_save.php`, `kuvat_all.php`, `photos.php`, `photo_update.php`, `photo_delete.php`, `settings.php`) has been updated from `requireLogin()` to an explicit `requireRole(...)` call — grep for `requireLogin()` remaining anywhere and confirm each remaining instance is intentional (e.g., truly all-roles pages, if any).
- [ ] **Author post-ownership:** Verify `posts.php`'s edit path and `post_delete.php` actually filter by `author_id` when the session role is `author` — not just that the *list view* only shows the author's own posts. Test by directly hitting another author's post ID.
- [ ] **Pending-deletion workflow:** Verify the mod-initiated delete button no longer executes a `DELETE FROM`/`UPDATE ... SET is_deleted=1` directly — confirm by checking the row still exists (and is still fully visible publicly) immediately after a mod clicks delete, until an admin approves it.
- [ ] **User management "delete last admin":** Verify a live test — with exactly one admin remaining, attempt to delete or demote that admin — must be blocked with a clear error, not silently succeed.
- [ ] **Password reset forces change:** Verify a user who was given an admin-reset password is redirected to a mandatory password-change screen on next login, before reaching any other admin page — not just that the password itself was changed in the DB.
- [ ] **Session regeneration on password change:** Verify `session_regenerate_id(true)` (or equivalent) is called after self-service password change and after admin-initiated reset — grep for it, don't just trust the feature "works" because login still succeeds afterward.
- [ ] **Soft-delete parity across content types:** Verify `foals`, `competitions`, `showrecords`, and `posts` all gained `is_deleted`/`deleted_at` columns *and* every public-facing query against them (listings, pedigree/foal displays, competition calendars) was updated to filter `is_deleted = 0`, matching the existing `horses` pattern — not just the admin-side delete handler.

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|----------------|------------------|
| Missing role check on a page (Pitfall 1) discovered post-deploy | LOW | Add `requireRole(...)` to the offending file, deploy; review access logs (if any) for signs of misuse during the gap; Altervista provides phpMyAdmin so any unauthorized data changes can usually be identified/reverted from `updated_at` timestamps |
| Last admin deleted (Pitfall 4) despite the guard, via a bug | MEDIUM | Direct phpMyAdmin access to `admin_users`: manually `UPDATE admin_users SET role='admin' WHERE id=...` on a surviving user, or `INSERT` a fresh admin row with a known bcrypt hash generated locally — recoverable without shell access since Altervista provides phpMyAdmin |
| IDOR incident — author edited/deleted another author's post (Pitfall 2) before the fix shipped | LOW-MEDIUM | Posts are soft-deleted-by-design going forward (Pitfall 8/9), so an "IDOR deletion" is recoverable by flipping `is_deleted` back to 0 via phpMyAdmin if it was a soft-delete; if it was a hard `DELETE` (pre-migration), only recoverable from a DB backup — reinforces doing the soft-delete migration (Pitfall 8) *before* enabling mod/author delete access, not after |
| Two mods' duplicate pending-deletion rows (Pitfall 11) reach production before the uniqueness guard | LOW | One-time cleanup query to collapse duplicate pending rows per `(content_type, content_id)` down to the earliest one, then deploy the guard |
| Rejected request silently deleted with no trace (Pitfall 12) | LOW | No data loss (the underlying content item itself was never touched by a rejection) — only the audit trail is lost; add the `status='rejected'` transition going forward, accept the historical gap |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|-------------------|----------------|
| 1. Missing role gate on an `admin/*.php` file | Role/schema foundation phase (add `role` column + `requireRole()` helper) | Automated grep/CI check that every `admin/*.php` file contains a role-check call; manual audit table of all existing files mapped to required role |
| 2. IDOR on author's own posts | Author-permissions phase | Negative test: author A cannot view/edit/delete author B's post via direct ID |
| 3. Stale session role after demotion/deactivation | Role/schema foundation phase (or user-management phase, whichever lands the session-handling change) | Test: demote/deactivate a user mid-session, confirm their very next request is restricted, not just their next login |
| 4. Last admin deleted/demoted | User-management phase | Test: attempt to delete/demote the sole remaining admin — must be blocked |
| 5. Admin self-deletes/self-demotes accidentally | User-management phase | Test: admin cannot target their own row with delete/role-change actions |
| 6. No session regeneration after password change | Self-service password-change phase + admin-reset phase | Grep for `session_regenerate_id` in both handlers; test old session ID is invalid after change |
| 7. Insecure/unforced password reset | User-management phase (admin resets others' passwords) | Test: reset generates a one-time-displayed password, sets `must_change_password`, and the user is redirected to change it before reaching any other page |
| 8. No soft-delete columns on 4 content types / pending-deletion has nowhere to live | Schema-migration phase (must precede pending-deletion-workflow phase) | Migration adds `is_deleted`/`deleted_at` to `foals`/`competitions`/`showrecords`/`posts` plus a `pending_deletions` table; confirm public queries filter on the new column |
| 9. `posts.author_id` missing | Schema-migration phase (same as above, precedes author-permissions phase) | Migration adds `author_id` FK with `ON DELETE SET NULL`; confirm every insert path sets it |
| 10. Ambiguous "pending" visibility across public site / admin / pedigree | Pending-deletion-workflow phase | Confirm a horse with a *pending* (not yet approved) deletion request still renders normally everywhere public-facing, including recursive pedigree lookups |
| 11. Duplicate pending-deletion requests for the same item | Pending-deletion-workflow phase | Test: two different mods request deletion of the same item; only one active pending row exists, second mod sees "already pending" |
| 12. Rejected deletion request has no state/notification | Pending-deletion-workflow phase | Test: admin rejects a request; row transitions to `status='rejected'` (not deleted); requesting mod sees a rejection indicator on next visit; mod can submit a new request afterward |

## Sources

- Direct codebase inspection (HIGH confidence, verified by reading): `public/src/includes/helpers.php` (`requireLogin()`, `isLoggedIn()`, CSRF functions), `public/admin/login.php` (session regeneration on login only), `public/admin/post_delete.php` and `public/admin/posts.php` (no ownership filter on posts, no `author_id` column), `public/admin/horse_delete.php` (existing soft-delete pattern to mirror), `database/schema.sql` (confirms `horses.is_deleted`/`deleted_at` exist; `admin_users` has no `role` column yet; `foals`/`competitions`/`showrecords`/`posts` have no soft-delete columns; `posts` has no `author_id`), `.planning/PROJECT.md` (milestone scope and constraints).
- [RBAC retrofit consistency pitfalls](https://www.osohq.com/learn/rbac-role-based-access-control) — MEDIUM confidence (web, cross-checked)
- [OWASP IDOR Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html) — MEDIUM confidence (web, cross-checked)
- [IDOR guide — ownership checks, unpredictable IDs are not access control](https://redbotsecurity.com/insecure-direct-object-reference/) — MEDIUM confidence (web, cross-checked)
- [Session fixation and password reset — regenerate session ID after privilege-changing actions](https://undercodetesting.com/the-session-fixation-flaw-why-your-password-reset-is-secretly-useless/) — MEDIUM confidence (web, cross-checked)

---
*Pitfalls research for: RBAC + delete-approval retrofit on single-admin PHP/MySQL admin panel*
*Researched: 2026-07-05*
