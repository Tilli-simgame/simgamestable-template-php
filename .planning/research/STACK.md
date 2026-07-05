# Stack Research

**Domain:** Role-based access control (admin/mod/author) + soft-delete approval workflow, added to an existing plain PHP 8.2 + PDO + MySQL admin panel on Altervista shared hosting (no shell, no Composer, no framework)
**Researched:** 2026-07-05
**Confidence:** HIGH (core patterns are native-PHP/MySQL mechanics already used elsewhere in this codebase); MEDIUM (external best-practice corroboration for the approval-queue shape, since no single canonical source exists for that part)

## Recommended Stack

**There are no new runtime dependencies to install.** Everything below is either a native PHP 8.2 function, a native MySQL column type, or a plain-PHP pattern that mirrors code already in this repo (`isLoggedIn()`/`requireLogin()` in `public/src/includes/helpers.php`, `generate_csrf_token()`/`validate_csrf_token()`, the `migrate_*.sql` + phpMyAdmin-import workflow, and the `is_deleted`/`deleted_at` soft-delete columns already on `horses`).

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| PHP native `$_SESSION` | PHP 8.2 (built-in) | Carry the logged-in user's `role` for the duration of the request, alongside the existing `admin_logged_in`/`admin_id`/`admin_username` keys | Zero new dependency; identical mechanism the app already trusts for auth. Adding `$_SESSION['admin_role']` at login is a one-line change to `login.php`, no new session library needed. |
| `password_hash()` / `password_verify()` / `password_needs_rehash()` | PHP 8.2 (built-in, `PASSWORD_DEFAULT` = bcrypt) | Self-service password change, admin-initiated password reset for other users | Already the mechanism securing `admin_users.password`. `PASSWORD_DEFAULT` auto-upgrades to a stronger algorithm on future PHP upgrades without code changes — official recommendation is to never hand-roll hashing. Call `password_needs_rehash()` only immediately after a successful `password_verify()` (login, or self-service change where you have the plaintext) [HIGH confidence — php.net manual, corroborated across independent sources]. |
| MySQL `ENUM` column type | MySQL (Altervista-provided) | `admin_users.role`, `pending_deletions.entity_type`, `pending_deletions.status` | Matches this schema's existing conventions exactly (`horses.gender ENUM('ori','tamma','ruuna')`, `foals.status ENUM('born','expected')`). DB-enforced valid values, self-documenting, no app-level validation library needed. |
| PDO prepared statements | Already in use (`getDB()` in `db.php`) | All new queries: role checks, `pending_deletions` CRUD, user management CRUD | No new abstraction — every new query below is written the same way as the existing `horse_delete.php`/`settings.php` queries. |
| Plain-PHP "guard function" pattern | N/A (hand-written, ~15 lines) | `requireRole()`, `hasRole()`, `currentRole()` in `helpers.php` | Three static roles and roughly a dozen permission checks do not justify a permission engine. A framework-style RBAC/ACL system would be solving a problem this app doesn't have. |

### Supporting Libraries

**None.** This is a deliberate recommendation, not a gap — see "What NOT to Use" below for why an external RBAC/ACL package or ORM would be the wrong fit for this specific deployment.

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| phpMyAdmin "Import" (existing workflow) | Apply new `migrate_*.sql` files to the Altervista production database | Continue the established pattern (`migrate_theme.sql`, `migrate_ancestor.sql`, etc.): one file per schema change, `ALTER TABLE ... ADD COLUMN` for existing tables, `CREATE TABLE IF NOT EXISTS` for new tables, `INSERT IGNORE` for seed data — never `ON DUPLICATE KEY UPDATE` in migrations (per existing Key Decision in PROJECT.md). |

## Schema Additions (concrete, ready to hand to a planner)

### 1. `admin_users` — add `role` and `is_active`

```sql
-- migrate_roles.sql
ALTER TABLE `admin_users`
  ADD COLUMN `role` ENUM('admin','mod','author') NOT NULL DEFAULT 'author'
    COMMENT 'admin = full rights, mod = restricted CRUD + delete needs approval, author = own posts only'
    AFTER `username`,
  ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Deactivated accounts can no longer log in (soft alternative to deleting a user)'
    AFTER `role`;

-- Promote the existing single admin account explicitly — do this by hand per-environment,
-- do NOT guess an id in the migration file:
-- UPDATE `admin_users` SET `role` = 'admin' WHERE `username` = '<existing admin username>';
```

Why `is_active` alongside a real delete option: the milestone requires "deactivate OR delete" for admin's user management. A hard `DELETE FROM admin_users` orphans `posts.author_id`, `pending_deletions.requested_by`/`reviewed_by`, and any historical attribution. Recommend: **deactivate is the primary control** (`is_active = 0`, login blocked, account preserved for FK integrity); allow hard delete only when the account has zero rows referencing it (check `posts`, `pending_deletions` first), otherwise force deactivation instead. This avoids ever needing `ON DELETE CASCADE` from content tables back to `admin_users`.

### 2. `posts` — add `author_id` (required for author-role scoping)

```sql
-- migrate_post_author.sql
ALTER TABLE `posts`
  ADD COLUMN `author_id` INT UNSIGNED DEFAULT NULL
    COMMENT 'Kirjoittaja (admin_users.id) — NULL sallitaan legacy-postauksille'
    AFTER `id`,
  ADD KEY `idx_posts_author` (`author_id`),
  ADD CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;
```

`ON DELETE SET NULL` (not `RESTRICT`) so a deactivated-then-eventually-removed author account never blocks deleting the user row; existing posts just lose attribution instead of being orphaned or blocking deletion.

### 3. Extend soft-delete to `foals`, `competitions`, `showrecords`, `posts`

These four currently lack `is_deleted`/`deleted_at` (only `horses` has it). Copy the exact column shape already used on `horses` for consistency:

```sql
-- migrate_soft_delete_extend.sql
ALTER TABLE `foals`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notes`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_deleted`,
  ADD KEY `idx_foals_deleted` (`is_deleted`);

ALTER TABLE `competitions`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `points`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_deleted`,
  ADD KEY `idx_comp_deleted` (`is_deleted`);

ALTER TABLE `showrecords`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notes`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_deleted`,
  ADD KEY `idx_showrecords_deleted` (`is_deleted`);

ALTER TABLE `posts`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `author_id`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_deleted`,
  ADD KEY `idx_posts_deleted` (`is_deleted`);
```

Every public-facing and admin-listing query against these four tables must be updated to add `AND is_deleted = 0` (mirroring how `horses` queries already do this — see `getHorsePedigree()` in `helpers.php`). Flag this explicitly to the planner: it's a cross-cutting change across every existing SELECT on these four tables, not just the delete action.

### 4. `pending_deletions` — one shared approval-queue table (not five per-table status columns)

```sql
-- migrate_pending_deletions.sql
CREATE TABLE IF NOT EXISTS `pending_deletions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` ENUM('horse','foal','competition','showrecord','post') NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL COMMENT 'admin_users.id — mod who requested the delete',
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT UNSIGNED DEFAULT NULL COMMENT 'admin_users.id — admin who approved/rejected',
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `reject_reason` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pd_entity` (`entity_type`, `entity_id`),
  KEY `idx_pd_status` (`status`),
  CONSTRAINT `fk_pd_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `admin_users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pd_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Why one shared table instead of a `pending_delete` status column on each of `horses`/`foals`/`competitions`/`showrecords`/`posts`:** a per-table column means duplicating `requested_by`, `requested_at`, `reviewed_by`, `reviewed_at`, `reject_reason` five times and writing five nearly-identical "approve/reject" admin screens. A single queue table lets the admin's approval inbox be **one query** (`SELECT * FROM pending_deletions WHERE status = 'pending'`) joined out to whichever table `entity_type` points to, and lets the approve/reject action be **one shared PHP function** parameterized by `entity_type`. This is the standard shape for this problem (confirmed against general approval-workflow design writeups) [MEDIUM confidence — no single canonical source, but the tradeoff is straightforward given this app's existing shared-helper style].

Deliberately **no foreign key from `entity_id` to the target table** — MySQL cannot express "this FK points to `horses.id` OR `foals.id` OR ... depending on `entity_type`". Validate `entity_id` exists in the appropriate table in PHP before inserting the `pending_deletions` row (a single `getPendingEntityTable(string $type): string` lookup + existence check). Document this as an accepted tradeoff, not an oversight — it's the standard cost of a polymorphic reference in a database that (unlike Postgres) has no partial/check-constraint escape hatch worth relying on here.

**No DB-level uniqueness constraint** on "only one pending row per entity" — MySQL partial/functional unique indexes need 8.0.13+, and Altervista's exact MySQL/MariaDB version/config shouldn't be relied on for this. Enforce it in PHP instead: before inserting, `SELECT COUNT(*) FROM pending_deletions WHERE entity_type = :t AND entity_id = :id AND status = 'pending'` and refuse a duplicate request. This app has a single admin approving low-volume requests, so a tiny race-condition window here is an acceptable risk, not one worth a schema workaround.

## Session & Helper Function Pattern

Extend `public/src/includes/helpers.php` (same file, same style as `isLoggedIn()`/`requireLogin()`):

```php
/** Palauttaa kirjautuneen käyttäjän roolin, tai null jos ei kirjautunut */
function currentRole(): ?string {
    return $_SESSION['admin_role'] ?? null;
}

/** Tarkistaa onko kirjautuneella käyttäjällä jokin annetuista rooleista */
function hasRole(string ...$roles): bool {
    return isLoggedIn() && in_array(currentRole(), $roles, true);
}

/** Vaatii kirjautumisen JA jonkin annetuista rooleista — ohjaa dashboardiin jos ei */
function requireRole(string ...$roles): void {
    requireLogin();
    if (!hasRole(...$roles)) {
        http_response_code(403);
        redirect(SITE_URL . '/admin/index.php?denied=1');
    }
}
```

`login.php` change (minimal diff to the existing query + session block):

```php
$stmt = $db->prepare('SELECT id, username, password, role, is_active FROM admin_users WHERE username = :username LIMIT 1');
// ...
if ($row && $row['is_active'] && password_verify($password, $row['password'])) {
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id']       = $row['id'];
    $_SESSION['admin_username'] = $row['username'];
    $_SESSION['admin_role']     = $row['role'];   // <-- new
    redirect(SITE_URL . '/admin/index.php');
} else {
    $error = 'Väärä käyttäjätunnus tai salasana, tai tunnus on poistettu käytöstä.';
}
```

Every existing `requireLogin();` call at the top of an admin page stays as-is for pages **all three roles** may reach (e.g. own-password-change page). For pages restricted by role, replace it with `requireRole('admin');` (e.g. `admin/users.php`, `admin/settings.php` — theme/settings stays admin-only per the milestone spec) or `requireRole('admin', 'mod');` (e.g. `horses.php`, `foal_edit.php`, `competitions.php`, `showrecords.php`, `posts.php` list/edit screens). Ownership checks for `author` (own posts only) are a separate, per-row `WHERE author_id = :current_admin_id` condition in the query — not a role-helper concern.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|--------------------------|
| Hand-written session role + `hasRole()`/`requireRole()` helpers | External RBAC/ACL library (e.g. PHP-Casbin, a Composer permissions package) | Only if the permission model grows well beyond 3 fixed roles into dynamic, per-resource, admin-configurable permissions — and even then, only once Composer/shell access exists somewhere in the deploy pipeline. Not applicable here: Altervista production has no Composer. |
| Single shared `pending_deletions` polymorphic queue table | Per-table `pending_delete` status column on each of the 5 tables | If the approval workflow only ever needed to cover ONE table. With 5 tables in scope, a shared table avoids 5x duplicated reviewer/timestamp columns and 5 near-identical approve screens. |
| MySQL `ENUM('admin','mod','author')` for `role` | Separate `roles` lookup table + `role_id` FK | If roles needed to become admin-configurable at runtime (add a 4th role without a code/schema change). Out of scope — the milestone fixes exactly 3 roles by spec. |
| `is_active` flag + FK-safety-checked hard delete | Immediate hard `DELETE FROM admin_users` always allowed | Never, in this app — it would silently orphan `posts.author_id`/`pending_deletions.requested_by` history. |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Any Composer package (RBAC library, ORM, permissions framework) | Altervista production has no shell access and no Composer; a `vendor/` directory has to be hand-uploaded via FTP and kept in sync manually — fragile, and it directly violates the existing "no external framework dependencies" constraint in PROJECT.md | The ~15-line `hasRole()`/`requireRole()` pattern above, written directly in `helpers.php` |
| A real FK from `pending_deletions.entity_id` to a specific content table | MySQL cannot express a conditional/polymorphic foreign key ("point to `horses` when `entity_type='horse'`, `foals` when `entity_type='foal'`, etc."). Forcing it would mean 5 separate nullable FK columns instead of one `entity_id`, which is worse | `entity_type` ENUM + plain `entity_id` INT with app-level existence validation before insert (documented tradeoff, not an oversight) |
| Hard `DELETE FROM horses/foals/competitions/showrecords/posts` for mod-initiated deletes | Destroys the ability to reject — once the row is gone there's nothing left for admin to approve or deny, which breaks the entire approval-workflow requirement | Soft-delete (`is_deleted`/`deleted_at`, already the house pattern on `horses`) gated behind `pending_deletions` approval |
| Re-querying `admin_users` for the role on every single page load "to be extra safe" | Adds a DB round-trip per request for a threat model this app doesn't have (single small trusted user set, not a high-security multi-tenant system); also inconsistent with how `admin_id`/`admin_username` are already cached in session without re-verification | Store `role` in `$_SESSION` at login time, exactly like `admin_id`/`admin_username` already are |
| A generic `is_deleted` re-purposed as a 3-state flag (0 = active, 1 = deleted, 2 = pending) crammed into one column per content table | Conflates "soft-deleted" (a stable fact about the row) with "workflow state" (a transient fact about a request), makes every existing `WHERE is_deleted = 0` query subtly wrong until updated to also exclude "pending", and still requires the same reviewer/timestamp metadata columns the shared table already provides | Keep `is_deleted`/`deleted_at` meaning exactly what it means today (row is soft-deleted, full stop); track the *workflow* separately in `pending_deletions` |

## Stack Patterns by Variant

**If a `mod` deletes a horse/foal/competition/showrecord/post:**
- Do NOT flip `is_deleted` directly.
- Check for an existing `pending` row for that `(entity_type, entity_id)`; if none, `INSERT INTO pending_deletions (entity_type, entity_id, requested_by) VALUES (...)`.
- Show a "poisto odottaa hyväksyntää" badge in the admin list (`LEFT JOIN pending_deletions pd ON pd.entity_type = '...' AND pd.entity_id = t.id AND pd.status = 'pending'`).

**If an `admin` deletes any entity:**
- Flip `is_deleted = 1, deleted_at = NOW()` immediately — reuse the existing `horse_delete.php` pattern unchanged (`UPDATE ... SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0`). Admin has full rights per spec; the approval queue does not apply to admin-initiated deletes at all.
- Admin approving a mod's pending request performs the *same* `UPDATE ... SET is_deleted = 1, deleted_at = NOW()` on the target row, then `UPDATE pending_deletions SET status = 'approved', reviewed_by = :admin_id, reviewed_at = NOW() WHERE id = :pending_id` — both writes in one request, ideally one transaction (`$db->beginTransaction()` — PDO already supports this, no new code needed).
- Admin rejecting: `UPDATE pending_deletions SET status = 'rejected', reviewed_by = :admin_id, reviewed_at = NOW(), reject_reason = :reason WHERE id = :pending_id` — target row is left completely untouched.

**If an `author` deletes their own post:**
- No approval queue involved (author deletes are immediate per spec).
- Access control is an ownership check, not a role-helper: `UPDATE posts SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND author_id = :current_admin_id AND is_deleted = 0`. If the row count affected is 0, treat it as "not yours" (403), same as a login/role failure.

**If Altervista's MySQL/MariaDB version is uncertain (partial/functional index support unknown):**
- Skip trying to enforce "one pending row per entity" at the DB level. Enforce it in the PHP insert path only — see the `pending_deletions` section above.

## Version Compatibility

| Package/Feature | Compatible With | Notes |
|-----------------|-----------------|-------|
| PHP `match` / `ENUM` / `readonly` etc. | PHP 8.2.31 (confirmed Altervista version per PROJECT.md) | No compatibility concern — all patterns above use only long-stable PHP 8.x features already used elsewhere in this codebase (e.g. `match()` in `calculateAgeBySystem()`). |
| MySQL `ENUM` columns | Any MySQL/MariaDB version Altervista runs | Already used extensively in this schema (`gender`, `status`, `aging_system`); no version risk. |
| `PDO::beginTransaction()`/`commit()`/`rollBack()` | PDO (already the DB layer) | Not currently used elsewhere in the codebase but is core PDO, not an extension — safe to introduce for the "approve = 2 writes" case above. |
| MySQL functional/partial unique indexes | Requires MySQL 8.0.13+ / not in older MariaDB | Explicitly NOT relied upon (see `pending_deletions` uniqueness note) — avoids a hidden version dependency on a host with no shell access to check `SELECT VERSION()` easily ahead of time. |

## Sources

- `public/src/includes/helpers.php`, `public/admin/login.php`, `public/admin/horse_delete.php`, `public/admin/post_delete.php`, `public/admin/settings.php`, `public/admin/includes/admin_header.php`, `database/schema.sql`, `database/migrate_ancestor.sql`, `database/migrate_theme.sql`, `.planning/PROJECT.md` — read directly from this repository; primary source of truth for integration points and existing conventions [HIGH confidence, verified by reading the actual files]
- PHP manual: `password_needs_rehash()`, `password_hash()` / `PASSWORD_DEFAULT` behavior — https://www.php.net/manual/en/function.password-needs-rehash.php, corroborated by https://docs.php.earth/security/passwords/ and https://www.phparch.com/2021/05/security-corner-basics-of-password-hashing/ [HIGH confidence — official manual, cross-checked]
- General PHP RBAC session-pattern writeups — https://www.sitepoint.com/role-based-access-control-in-php/, https://www.tonymarston.net/php-mysql/role-based-access-control.html, https://medium.com/@wwwebadvisor/implementing-role-based-access-control-rbac-in-php-85c0ea7bc86b [MEDIUM confidence — general web sources, not framework-specific, cross-checked against multiple independent writeups]
- General approval-workflow database design discussion — https://www.coderbased.com/p/multi-level-approval-system-design, https://medium.com/@herihermawan/the-ultimate-multifunctional-database-table-design-workflow-states-pattern-156618996549, https://budibase.com/blog/data/workflow-management-database-design/ [MEDIUM confidence — informs the "shared queue table" tradeoff, no single canonical source for this specific shape]

---
*Stack research for: role-based access control + delete-approval workflow, plain PHP/MySQL, Altervista shared hosting*
*Researched: 2026-07-05*
