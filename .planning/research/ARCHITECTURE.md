# Architecture Research

**Domain:** Role-based access control (RBAC) + delete-approval workflow, integrated into an existing plain-PHP-includes admin panel (no framework, no router, no ORM)
**Researched:** 2026-07-05
**Confidence:** HIGH (direct codebase inspection of every touched file) / MEDIUM (general RBAC pattern conventions, cross-checked against SitePoint/Medium/Tony Marston RBAC write-ups — see Sources)

## Standard Architecture

### System Overview

This is **not** a generic domain — it's "how do you retrofit roles + an approval queue onto an existing require→check→query→render script pattern without introducing a router, middleware layer, or ORM." The correct architecture is additive: one new session field, one new helper function family, a handful of new standalone admin scripts (following the exact same shape as `login.php`/`horse_delete.php`), and two schema additions (a `role` column, a `pending_deletions` table). Nothing about the existing request lifecycle changes.

```
┌──────────────────────────────────────────────────────────────────────┐
│  Browser → public/admin/{page}.php  (standalone script, no router)   │
├──────────────────────────────────────────────────────────────────────┤
│  1. require_once db.php   → config.php + getDB() + helpers.php       │
│                              + session_start() w/ hardened cookies   │
│  2. requireLogin()        → unchanged, existing gate                 │
│  3. requireRole(...roles) → NEW gate, called right after             │
│                              requireLogin() in every admin/*.php     │
│  4. Inline PDO queries    → unchanged shape; ownership/role filters  │
│                              added to WHERE clauses where needed     │
│  5. require admin_header.php / admin_footer.php → NEW: nav items     │
│                              conditionally rendered by role          │
├──────────────────────────────────────────────────────────────────────┤
│  Delete-approval sub-flow (mod-only trigger)                         │
│  mod clicks "Poista" → existing *_delete.php / inline action=delete  │
│    branch runs the SAME soft-delete UPDATE (is_deleted=1) that       │
│    already exists for horses, PLUS an INSERT into pending_deletions  │
│  admin/deletions.php (NEW, admin-only) → lists pending_deletions     │
│    → approve: mark pending_deletions.status, row stays is_deleted=1  │
│    → reject:  mark pending_deletions.status, UPDATE ... is_deleted=0 │
└──────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | New or Modified |
|-----------|----------------|------------------|
| `public/src/includes/helpers.php` | Add `requireRole()`, `currentRole()`, `isAdmin()`, `requireOwnResourceOrAdmin()`, `insertPendingDeletion()`, `pendingDeletionCount()` | **Modified** |
| `database/schema.sql` (+ new `database/migrate_roles.sql`) | Add `admin_users.role`, `posts.author_id`, `is_deleted`/`deleted_at` on `foals`/`competitions`/`showrecords`/`posts`, new `pending_deletions` table | **Modified + new migration file** |
| `public/admin/login.php` | Store `$_SESSION['admin_role']` alongside existing session fields | **Modified** (2-line change) |
| `public/admin/includes/admin_header.php` | Conditionally render nav items (Käyttäjät, Poistopyynnöt badge, content-type links) by role | **Modified** |
| `public/admin/users.php` | List/create/edit(role+username)/deactivate users, admin-only | **New** |
| `public/admin/user_reset_password.php` | Admin resets another user's password | **New** (or folded into `users.php` as an action) |
| `public/admin/change_password.php` | Any logged-in role changes their own password | **New** |
| `public/admin/deletions.php` | Approval queue — list pending deletions across all entity types | **New** |
| `public/admin/deletion_approve.php` / `deletion_reject.php` | POST handlers for the approval queue | **New** |
| `public/admin/horses.php`, `horse_add.php`, `horse_edit.php`, `horse_delete.php`, `horse_import_vrl.php`, `kuvat_all.php`, `photos.php`, `photo_update.php`, `photo_delete.php` | Add `requireRole('admin','mod')` gate; `horse_delete.php` branches admin(direct)/mod(pending) | **Modified** |
| `public/admin/foals.php`, `kasvatus_all.php` | Add `requireRole('admin','mod')`; convert inline `action==='delete'` hard `DELETE` to soft-delete + pending branch | **Modified** |
| `public/admin/competitions.php`, `kilpailut_all.php` | Same as foals | **Modified** |
| `public/admin/showrecords.php`, `showrecords_all.php` | Same as foals | **Modified** |
| `public/admin/posts.php`, `post_delete.php` | Add `author_id` on insert; `requireLogin()` only (all 3 roles allowed); list query filtered by role; edit/delete branch through `requireOwnResourceOrAdmin()`; delete branches admin/mod(pending)/author(instant, own only) | **Modified** |
| `public/pages/hevonen.php` (horse profile) and any other public page querying `foals`/`competitions`/`showrecords`/`posts` | Add `WHERE is_deleted = 0` filters — these tables have never had this column before, so public queries currently show everything unconditionally | **Modified** (verify each; not read in this pass — flag for phase-specific research) |

## Recommended Project Structure

No new folders. This stays inside the existing flat `public/admin/` layout — that is itself the "structure rationale": the whole point of this domain is *not* introducing a `src/Http/Controllers`-style reorganization.

```
public/
├── admin/
│   ├── users.php                    # NEW — admin-only user management (list/create/edit/deactivate)
│   ├── user_reset_password.php      # NEW — admin-only, POST handler
│   ├── change_password.php          # NEW — any role, self-service
│   ├── deletions.php                # NEW — admin-only approval queue (list)
│   ├── deletion_approve.php         # NEW — admin-only POST handler
│   ├── deletion_reject.php          # NEW — admin-only POST handler
│   ├── login.php                    # MODIFIED — store admin_role in session
│   ├── horses.php / horse_*.php     # MODIFIED — requireRole('admin','mod')
│   ├── foals.php / kasvatus_all.php # MODIFIED — requireRole + soft-delete branch
│   ├── competitions.php / kilpailut_all.php   # MODIFIED — same pattern
│   ├── showrecords.php / showrecords_all.php  # MODIFIED — same pattern
│   ├── posts.php / post_delete.php  # MODIFIED — author_id + ownership gate
│   ├── kuvat_all.php / photo_*.php  # MODIFIED — requireRole('admin','mod')
│   ├── settings.php                 # UNCHANGED but stays admin-only (already effectively is; add explicit requireRole('admin'))
│   └── includes/
│       └── admin_header.php         # MODIFIED — role-aware nav
├── src/includes/
│   └── helpers.php                  # MODIFIED — new RBAC + pending-deletion helpers
database/
├── schema.sql                       # MODIFIED — role column, author_id, is_deleted×4, pending_deletions
└── migrate_roles.sql                # NEW — idempotent ALTER/CREATE migration, same style as existing migrate_*.sql
```

### Structure Rationale

- **No `middleware/` or `router/` folder:** the codebase's own convention (confirmed in `db.php`, `horse_delete.php`, `post_delete.php`) is "each script is the full request handler." Introducing a router would be the single biggest architectural mismatch you could make here. `requireRole()` is a **function called at the top of the script**, exactly like `requireLogin()` already is — not a dispatched middleware chain.
- **One migration file per milestone, not per table:** every existing schema change in this repo (`migrate_posts.sql`, `migrate_foals.sql`, `migrate_theme.sql`, etc.) is one file per feature, using `INSERT IGNORE` (never `ON DUPLICATE KEY UPDATE`, per the project's own Key Decisions). `migrate_roles.sql` should follow that exact convention for its seed/backfill rows, using guarded `ALTER TABLE` statements for the new columns.
- **User management lives in `admin/`, not a subfolder:** every other admin feature (`contacts.php`, `settings.php`) is a flat top-level script in `admin/`. `users.php` should be a peer, not `admin/users/index.php`.

## Architectural Patterns

### Pattern 1: Session-stored role + `requireRole()` gate (mirrors `requireLogin()`)

**What:** Store the role string in `$_SESSION['admin_role']` at login (same lifecycle as `admin_id`/`admin_username`), and add a `requireRole()` helper next to `requireLogin()` in `helpers.php`. Every `admin/*.php` script keeps its existing two-line preamble and just adds a third line.

**When:** Every admin script that isn't universally accessible to all three roles (i.e., everything except `change_password.php`, `logout.php`, and — with internal branching — `posts.php`).

**Example:**
```php
// public/src/includes/helpers.php — additions

function currentRole(): string {
    return $_SESSION['admin_role'] ?? 'author'; // fail-closed: unknown/missing → least privilege
}

function isAdmin(): bool {
    return currentRole() === 'admin';
}

/**
 * Vaatii tietyn roolin (tai jonkin listatuista) — kutsutaan requireLogin() jälkeen
 */
function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array(currentRole(), $roles, true)) {
        http_response_code(403);
        die('Sinulla ei ole oikeuksia tähän toimintoon.');
    }
}
```

```php
// public/admin/login.php — modified block (2 lines added)
if ($row && password_verify($password, $row['password'])) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id']       = $row['id'];
    $_SESSION['admin_username'] = $row['username'];
    $_SESSION['admin_role']     = $row['role'];       // NEW
    redirect(SITE_URL . '/admin/index.php');
}
```

```php
// public/admin/horses.php — top of file, unchanged line 2, one new line
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');   // was: requireLogin();
```

**Trade-offs:** Session-cached role can go stale if an admin changes a logged-in user's role mid-session (rare, single-owner-plus-a-few-helpers context — acceptable). Mitigate by regenerating the session on `login.php` only (already done) and, if paranoid, re-reading `admin_users.role` from DB on `requireRole()` for admin-only pages — but that adds a query to every page load for a threat model (a small stable's admin panel) that doesn't need it. **Recommendation: session-only, no per-request DB re-check.** This matches the project's existing security posture (session-based, single-owner-plus-helpers, not a multi-tenant SaaS).

### Pattern 2: Ownership-gated CRUD via a single reusable check (author "own posts only")

**What:** Rather than duplicating `$post['author_id'] === $_SESSION['admin_id']` checks with different error handling in every branch, add one helper that admin/mod always pass and author must own.

**When:** `posts.php` (edit form load, save handler) and `post_delete.php`.

**Example:**
```php
// helpers.php
function requireOwnResourceOrAdmin(?int $ownerId): void {
    $role = currentRole();
    if ($role === 'admin' || $role === 'mod') return;           // mod/admin: unrestricted on posts
    if ($role === 'author' && $ownerId === (int)($_SESSION['admin_id'] ?? -1)) return;
    http_response_code(403);
    die('Voit muokata vain omia postauksiasi.');
}
```

```php
// public/admin/posts.php — after requireLogin() (NOT requireRole, all 3 roles land here)
require_once __DIR__ . '/../src/includes/db.php';
requireLogin();

// list query becomes role-aware:
$role = currentRole();
if ($role === 'author') {
    $posts = $db->prepare('SELECT id, title, slug, created_at FROM posts WHERE author_id = :uid AND is_deleted = 0 ORDER BY created_at DESC');
    $posts->execute([':uid' => $_SESSION['admin_id']]);
    $posts = $posts->fetchAll();
} else {
    $posts = $db->query('SELECT id, title, slug, created_at FROM posts WHERE is_deleted = 0 ORDER BY created_at DESC')->fetchAll();
}

// on edit load and on POST save, before touching an existing post:
if ($edit_id > 0) {
    $editPost = /* fetch as today */;
    if ($editPost) requireOwnResourceOrAdmin((int)$editPost['author_id']);
}

// on INSERT (new post), stamp ownership:
$db->prepare('INSERT INTO posts (title, slug, content, author_id) VALUES (:t, :s, :c, :aid)')
   ->execute([':t'=>$title, ':s'=>$slug, ':c'=>$content, ':aid'=>$_SESSION['admin_id']]);
```

Author's read-only horse linking (`horse_ids[]` in the same form) needs **no change** — the existing `$allHorses` query in `posts.php` (line ~108) already just SELECTs active horses for the autocomplete widget; it's inherently read-only from the author's perspective since author never reaches `horses.php`.

**Trade-offs:** This keeps ownership logic colocated with the page it protects (matches existing style — every admin script is self-contained) rather than centralizing "can user X touch post Y" in a policy class. For a 3-role, 1-table ownership rule, a policy layer would be over-engineering.

### Pattern 3: Soft-delete-then-approve, reusing the existing `is_deleted`/`deleted_at` convention

**What:** `horses` already has `is_deleted`/`deleted_at` and every query that should hide deleted rows already filters `WHERE is_deleted = 0` (confirmed in `helpers.php::getHorsePedigree`, `foals.php`'s JOINs, `posts.php`'s horse-picker query). The delete-approval workflow should **extend this exact convention** to `foals`, `competitions`, `showrecords`, and `posts` (which currently hard-`DELETE`), and add one new lightweight `pending_deletions` table purely for the approval audit trail — not a `deletion_status` enum duplicated across 5 tables.

**Why a separate `pending_deletions` table instead of a status column per table:** the existing `is_deleted` boolean already means exactly what "hidden from normal views" needs to mean — reuse it, don't reinvent it. What's missing is *who requested the deletion and whether an admin has ruled on it*, which is inherently a cross-table, audit-log-shaped concern — a generic table is the right normalized shape, and it lets `admin/deletions.php` list all five entity types with one query instead of five separate polling queries against five status columns.

```sql
-- database/migrate_roles.sql (new file, follows existing migrate_*.sql convention)

ALTER TABLE `admin_users`
  ADD COLUMN `role` ENUM('admin','mod','author') NOT NULL DEFAULT 'author' AFTER `username`;
-- Backfill the existing owner account explicitly — do NOT rely on the DEFAULT for row 1:
UPDATE `admin_users` SET `role` = 'admin' WHERE `id` = 1;

ALTER TABLE `posts`
  ADD COLUMN `author_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  ADD CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

ALTER TABLE `foals`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE `competitions`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE `showrecords`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `pending_deletions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type`   ENUM('horse','foal','competition','showrecord','post') NOT NULL,
  `entity_id`     INT UNSIGNED NOT NULL,
  `requested_by`  INT UNSIGNED NOT NULL COMMENT 'admin_users.id (mod who requested)',
  `requested_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`   INT UNSIGNED DEFAULT NULL COMMENT 'admin_users.id who approved/rejected',
  `reviewed_at`   TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pd_status` (`status`),
  KEY `idx_pd_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_pd_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pd_reviewed_by`  FOREIGN KEY (`reviewed_by`)  REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

```php
// helpers.php — the one shared write path every *_delete.php / inline delete branch calls

function insertPendingDeletion(string $entityType, int $entityId, int $requestedBy): void {
    $db = getDB();
    $db->prepare(
        'INSERT INTO pending_deletions (entity_type, entity_id, requested_by) VALUES (:t, :id, :uid)'
    )->execute([':t' => $entityType, ':id' => $entityId, ':uid' => $requestedBy]);
}
```

**Modified `public/admin/horse_delete.php` (mirrors the other four files):**
```php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');
// ...CSRF + id checks unchanged...

$db = getDB();
$db->prepare('UPDATE horses SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0')
   ->execute([':id' => $id]);

if (currentRole() === 'mod') {
    insertPendingDeletion('horse', $id, (int)$_SESSION['admin_id']);
}
// admin: no pending row — deletion is final immediately (matches today's behavior 1:1)

redirect(SITE_URL . '/admin/horses.php?deleted=1');
```

**Modified inline handlers** (`foals.php` line ~122, `competitions.php` line ~77, `showrecords.php` line ~104) follow the identical shape — replace the hard `DELETE FROM ...` with the soft-delete `UPDATE` + conditional `insertPendingDeletion()`, keeping the surrounding `action === 'delete'` branch structure untouched. This is the "without restructuring them" requirement satisfied literally: same file, same branch, same variable names — only the SQL statement and one `if` change.

**`post_delete.php` needs a three-way branch** (the only file with three distinct delete behaviors):
```php
require_once __DIR__ . '/../src/includes/db.php';
requireLogin();
// ...CSRF + method checks unchanged...

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $db = getDB();
    $post = $db->prepare('SELECT author_id FROM posts WHERE id = :id');
    $post->execute([':id' => $id]);
    $post = $post->fetch();

    if ($post) {
        $role = currentRole();
        if ($role === 'author') {
            requireOwnResourceOrAdmin((int)$post['author_id']); // dies with 403 if not own
            $db->prepare('UPDATE posts SET is_deleted=1, deleted_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
            // author deletes own post instantly — no approval per milestone spec
        } elseif ($role === 'mod') {
            $db->prepare('UPDATE posts SET is_deleted=1, deleted_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
            insertPendingDeletion('post', $id, (int)$_SESSION['admin_id']);
        } else { // admin
            $db->prepare('UPDATE posts SET is_deleted=1, deleted_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
        }
    }
}
redirect(SITE_URL . '/admin/posts.php?deleted=1');
```

**New `admin/deletions.php` (admin-only approval queue):**
```php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

$db = getDB();
$pending = $db->query(
    "SELECT pd.*, u.username AS requested_by_name
     FROM pending_deletions pd
     JOIN admin_users u ON u.id = pd.requested_by
     WHERE pd.status = 'pending'
     ORDER BY pd.requested_at ASC"
)->fetchAll();

// Whitelist-only table/label lookup — NEVER interpolate entity_type into SQL directly
function pendingDeletionLabel(string $type, int $id): string {
    $db = getDB();
    $map = [
        'horse'       => "SELECT name AS label FROM horses WHERE id = :id",
        'foal'        => "SELECT COALESCE(foal_name, 'Nimetön varsa') AS label FROM foals WHERE id = :id",
        'competition' => "SELECT CONCAT(discipline, ' ', competition_date) AS label FROM competitions WHERE id = :id",
        'showrecord'  => "SELECT CONCAT(discipline, ' ', show_date) AS label FROM showrecords WHERE id = :id",
        'post'        => "SELECT title AS label FROM posts WHERE id = :id",
    ];
    if (!isset($map[$type])) return '(tuntematon)';
    $stmt = $db->prepare($map[$type]);
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn() ?: '(poistettu jo)';
}
```

**New `admin/deletion_approve.php` / `admin/deletion_reject.php`:** same shape as every other `*_delete.php` (`requireRole('admin')`, POST-only, CSRF check, whitelist-based `match` on `entity_type` to pick the literal table name — never build the table name from user input even though it's admin-only, since it's the cheapest possible defense-in-depth). Reject additionally restores the row: `UPDATE {whitelisted_table} SET is_deleted = 0, deleted_at = NULL WHERE id = :id`.

**Trade-offs:** Every one of the five delete points changes from a single SQL statement to (at most) two statements plus one `if`. That's the honest minimum cost of this feature — there is no way to add "who requested this and does an admin need to approve it" without touching the delete call site. The alternative (a generic "soft-delete service" object wrapping all five tables behind one interface) would reduce line count marginally but introduces the one abstraction this codebase has consistently avoided everywhere else (see `PROJECT.md` Key Decisions: "PHP includes sivupohjien pilkkomiseen — jatkaa olemassa olevaa arkkitehtuurikuviota").

## Data Flow

### Request Flow (delete-approval)

```
[mod clicks "Poista" on horses.php/foals.php/competitions.php/showrecords.php/posts.php]
    ↓ POST (existing form, existing CSRF token)
[horse_delete.php / inline action=delete branch / post_delete.php]
    ↓ requireRole() already passed at page load; role re-read via currentRole()
    UPDATE {table} SET is_deleted=1, deleted_at=NOW()   ← row now hidden everywhere
    INSERT INTO pending_deletions (entity_type, entity_id, requested_by, status='pending')
    ↓ redirect back to the list page with ?deleted=1 flash (existing UX unchanged for mod)
[admin/deletions.php] — admin visits approval queue
    ↓ SELECT * FROM pending_deletions WHERE status='pending'
    ↓ per row, resolve label via whitelisted per-type lookup
[admin clicks Approve]                      [admin clicks Reject]
    ↓ POST → deletion_approve.php               ↓ POST → deletion_reject.php
    UPDATE pending_deletions                     UPDATE pending_deletions
      SET status='approved',                       SET status='rejected',
          reviewed_by=:aid, reviewed_at=NOW()           reviewed_by=:aid, reviewed_at=NOW()
    (entity stays is_deleted=1 — final)          UPDATE {table} SET is_deleted=0, deleted_at=NULL
                                                  (entity restored — visible again everywhere)
```

### Admin-direct-delete flow (unchanged intent, same code path minus the pending insert)

```
[admin clicks "Poista"] → same *_delete.php/inline branch → UPDATE is_deleted=1
    (currentRole() === 'admin' → skip insertPendingDeletion() → done, no queue entry)
```

### Key Data Flows

1. **Role resolution:** `admin_users.role` (DB, source of truth) → copied into `$_SESSION['admin_role']` once at `login.php` → read via `currentRole()` on every subsequent admin page load for the lifetime of the session. No per-page DB re-fetch of role (see Pattern 1 trade-offs).
2. **Ownership resolution (posts only):** `posts.author_id` (DB, set once at INSERT time from `$_SESSION['admin_id']`) → compared against `$_SESSION['admin_id']` at edit/delete time via `requireOwnResourceOrAdmin()`. Never trust a client-submitted `author_id`.
3. **Visibility resolution:** `is_deleted` column (per-table, already the pattern for `horses`) is the single source of truth for "is this row currently visible anywhere" — public pages, admin list pages, and the pedigree/breeding recursive queries all filter on it identically. `pending_deletions.status` is a separate, orthogonal concern: audit/workflow state, never used for visibility filtering directly.

## Scaling Considerations

Not relevant in the traditional sense — this is a single small-stable admin panel (per `PROJECT.md`: one owner, plus a handful of mod/author helpers, hosted on free-tier Altervista shared hosting). Table sizes stay in the hundreds-to-low-thousands of rows. No scaling concerns beyond:

| Concern | Current scope | If it ever grows |
|---------|---------------|-------------------|
| Pending-deletion queue length | Handful of rows at a time | Add pagination to `admin/deletions.php` list query — trivial `LIMIT`/`OFFSET` |
| Number of `admin_users` | Single-digit to low-double-digit | No change needed; `users.php` list query has no scaling concern at this size |
| Role check overhead | Session read, in-memory | Never re-introduce a per-request DB role lookup unless multi-tenant requirements emerge |

## Anti-Patterns

### Anti-Pattern 1: Introducing a router/middleware layer to "properly" implement RBAC

**What people do:** Reach for a `Middleware::role('admin')` chain, a front controller, or a permissions-matrix config file loaded by a bootstrap router.
**Why it's wrong:** This codebase has zero routing infrastructure by deliberate, repeatedly-reaffirmed choice (see `PROJECT.md` Key Decisions and Constraints: "ei ulkoisia framework-riippuvuuksia", "PHP includes sivupohjien pilkkomiseen — jatkaa olemassa olevaa arkkitehtuurikuviota"). Adding middleware here means introducing a new mental model for every future contributor touching a 30-line admin script.
**Instead:** `requireRole()` as a plain function call at the top of the script, exactly like `requireLogin()`. Zero new concepts.

### Anti-Pattern 2: Trusting a client-supplied role or ownership value

**What people do:** Put `role` or `author_id` in a hidden form field "for convenience" and trust it on submit.
**Why it's wrong:** Textbook privilege escalation — a mod could edit the hidden field and self-promote, or an author could claim another author's post.
**Instead:** Role always comes from `$_SESSION['admin_role']` (server-side, set only at login). Ownership (`author_id`) is set once at INSERT from the session and re-verified server-side from the DB row on every edit/delete — never re-derived from client input.

### Anti-Pattern 3: A `deletion_status` ENUM column duplicated across all five tables instead of a shared `pending_deletions` table

**What people do:** Add `deletion_status ENUM('active','pending','deleted')` to `horses`, `foals`, `competitions`, `showrecords`, `posts` individually.
**Why it's wrong:** (a) It breaks the existing, already-battle-tested `is_deleted = 0` filter convention used throughout `helpers.php` and every public page — you'd need to rewrite every existing query's boolean check into a three-way enum check. (b) The approval queue (`admin/deletions.php`) would need five separate polling queries instead of one, and would lose the audit trail (who requested, who reviewed, when) unless you also add `requested_by`/`reviewed_by`/`reviewed_at` columns to all five tables anyway — at which point you've built a worse, denormalized version of the same `pending_deletions` table.
**Instead:** Keep `is_deleted` boolean semantics everywhere (add the column to the four tables that lack it, don't change its meaning), and layer the approval workflow on top via one small audit table.

### Anti-Pattern 4: Hard-deleting rows the moment a mod requests deletion, or leaving them visible until approved

**What people do:** Interpret "mod deletion needs approval" as "queue the request, only actually delete on approval" — i.e. leave the row fully visible until an admin acts.
**Why it's wrong:** The milestone context explicitly requires the item be "hidden from normal views" as soon as the mod marks it for deletion, not only after admin approval. If you leave it visible, you've built a request queue with no actual effect until reviewed, which doesn't match the stated behavior and leaves stale/flagged-for-removal content live on the public site.
**Instead:** Soft-delete (`is_deleted=1`) *immediately* on mod's action (hiding it right away, reusing existing filters everywhere for free), and treat the `pending_deletions` row purely as the audit/reversal mechanism. Reject = restore (`is_deleted=0`). Approve = no-op on the entity (it's already correctly hidden); only the audit row changes state.

### Anti-Pattern 5: Re-fetching role from the database on every single page load "to be safe"

**What people do:** Add `SELECT role FROM admin_users WHERE id = :id` at the top of every admin script instead of trusting `$_SESSION['admin_role']`.
**Why it's wrong:** Adds a DB round-trip to every admin page load for a threat model (single stable, owner + a few trusted helpers, session lifetime capped at 30 minutes per `db.php`'s `session.gc_maxlifetime`) that doesn't justify it. It also isn't actually safer against the realistic threat (a compromised session cookie already grants full access regardless of where the role is read from).
**Instead:** Trust the session-cached role for the whole session lifetime, exactly as `admin_logged_in`/`admin_id`/`admin_username` already are. If the project later needs instant role-change propagation, `session_regenerate_id()` + a forced re-login on role change (from `users.php`) is a far cheaper fix than a per-request DB query.

## Integration Points

### External Services

None — no external services involved in this feature. Everything is self-contained PHP/MySQL/session.

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| `login.php` ↔ session | Direct `$_SESSION` write | Role captured once, at successful password verification, identical lifecycle to `admin_id` |
| `admin/*.php` pages ↔ `helpers.php` | Direct function call (`requireRole()`, `currentRole()`, `requireOwnResourceOrAdmin()`) | No new include files — these land in the existing `helpers.php`, already auto-required via `db.php` |
| Content-type CRUD scripts ↔ `pending_deletions` table | Direct PDO INSERT via `insertPendingDeletion()` helper | Single shared write path for all 5 entity types — the only place that needs to know the `pending_deletions` schema |
| `admin/deletions.php` ↔ per-entity tables | Direct PDO SELECT via whitelisted `match`/lookup array keyed by `entity_type` | Never build table names from `entity_type` via string interpolation directly in a query — use a hardcoded whitelist mapping even though `entity_type` is an ENUM (defense in depth, and it's where entity-specific label logic naturally lives anyway) |
| `admin_header.php` ↔ session | Reads `$_SESSION['admin_role']` / `currentRole()` to decide which nav items to render | Matches the file's existing pattern of reading `$_SESSION['admin_username']` for the sidebar footer |

## Suggested Build Order

Dependencies flow one direction only: **roles must exist before any gating can check them; gating must exist before delete-approval can decide who needs approval.**

1. **Roles + auth foundation** (blocks everything else)
   - `database/migrate_roles.sql`: add `admin_users.role`, backfill existing account to `'admin'`
   - `helpers.php`: `currentRole()`, `isAdmin()`, `requireRole()`
   - `login.php`: store `admin_role` in session
   - `admin/change_password.php` (new, all roles — no dependency on gating below, can ship alongside)
   - `admin/users.php` + `admin/user_reset_password.php` (new, admin-only — first real consumer of `requireRole('admin')`)
   - `admin_header.php`: hide "Käyttäjät" nav item unless `isAdmin()`

2. **Per-content-type permission gating** (depends on 1)
   - Add `requireRole('admin','mod')` to horses/foals/competitions/showrecords/photos scripts
   - `posts.php`/`post_delete.php`: add `author_id` column + migration, ownership checks, role-filtered list query
   - `admin_header.php`: hide horse/foal/competition/showrecord nav items for `author` role

3. **Delete-approval workflow** (depends on 1 and 2 — the pending-insert call sites are the same branches gating touched in step 2)
   - `database/migrate_roles.sql` (same file or a follow-up): `is_deleted`/`deleted_at` on `foals`/`competitions`/`showrecords`/`posts`, new `pending_deletions` table
   - `helpers.php`: `insertPendingDeletion()`
   - Modify the five delete call sites (`horse_delete.php`, inline branches in `foals.php`/`competitions.php`/`showrecords.php`, `post_delete.php`) to soft-delete + conditionally insert pending row
   - `admin/deletions.php`, `deletion_approve.php`, `deletion_reject.php` (new, admin-only)
   - Audit and add `WHERE is_deleted = 0` filters to every public/admin query touching `foals`/`competitions`/`showrecords`/`posts` that doesn't yet have one (these tables never had this column before — this is new surface area, not a copy-paste of an existing filter)
   - `admin_header.php`: pending-deletion count badge, admin-only

## Sources

- Direct codebase inspection (HIGH confidence — primary source for all integration points):
  - `public/src/includes/helpers.php`, `db.php`, `config.php`
  - `public/admin/login.php`, `logout.php`, `horse_delete.php`, `post_delete.php`, `posts.php`, `foals.php`, `includes/admin_header.php`
  - `database/schema.sql`, `database/migrate_posts.sql` (and the broader `migrate_*.sql` naming/idempotency convention)
  - `.planning/PROJECT.md` (milestone scope, constraints, Key Decisions)
- General RBAC pattern cross-check (MEDIUM confidence — used only to validate that session-stored-role + gate-function is the standard lightweight approach, not to source any project-specific detail):
  - [Role Based Access Control in PHP — SitePoint](https://www.sitepoint.com/role-based-access-control-in-php/)
  - [Implementing Role-Based Access Control (RBAC) in PHP — Medium](https://medium.com/@wwwebadvisor/implementing-role-based-access-control-rbac-in-php-85c0ea7bc86b)
  - [A Role-Based Access Control (RBAC) system for PHP — Tony Marston](https://www.tonymarston.net/php-mysql/role-based-access-control.html)

---
*Architecture research for: role-based access control + delete-approval workflow (Virtuaalitalli v1.2 Käyttäjäroolit)*
*Researched: 2026-07-05*
