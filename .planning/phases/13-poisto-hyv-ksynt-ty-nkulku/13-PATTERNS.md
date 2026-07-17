# Phase 13: Poisto-hyväksyntätyönkulku - Pattern Map

**Mapped:** 2026-07-17
**Files analyzed:** 12
**Analogs found:** 12 / 12

## Important correction to CONTEXT.md assumption

CONTEXT.md's `code_context`/`canonical_refs` sections assume `foal_delete.php`, `competition_delete.php`, `showrecord_delete.php` exist as standalone handlers analogous to `horse_delete.php`. **They do not exist.** Deletion for foals/competitions/showrecords is currently handled as an inline `action=delete` branch inside the respective list page itself (`foals.php` line 122-129, `competitions.php` line 73-80, `showrecords.php` line 100-107), each doing a hard `DELETE FROM ... WHERE id = :id` gated by `requireRole('admin')`. `post_delete.php` DOES exist as a standalone handler but currently does a hard `DELETE` (not soft-delete like `horse_delete.php`). Planner should treat this phase as: (1) convert `horse_delete.php`'s already-soft-delete pattern to add mod/pending branching, (2) convert `post_delete.php` from hard-delete to soft-delete + add mod/admin/author branching, (3) convert the three inline `action=delete` branches in `foals.php`/`competitions.php`/`showrecords.php` from hard-delete to soft-delete + add mod/admin branching (no author concept applies to these three — only admin/mod can reach them per Phase 12 gating).

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|-------------------|------|-----------|----------------|---------------|
| `database/migrate_delete_approval.sql` (new) | migration | batch | `database/migrate_posts_author.sql` | exact |
| `public/src/includes/helpers.php` (+`insertPendingDeletion()`) | utility | CRUD | `requireOwnResourceOrAdmin()` in same file (lines 78-90) | exact |
| `public/admin/horse_delete.php` (modify) | controller | request-response | itself (already soft-delete admin-only) | exact — needs role-branch added |
| `public/admin/post_delete.php` (modify) | controller | request-response | `public/admin/horse_delete.php` (soft-delete pattern) | role-match (currently hard-delete) |
| `public/admin/foals.php` (modify inline delete branch, lines 122-129) | controller (inline) | request-response | `public/admin/horse_delete.php` (target soft-delete pattern) + itself (current inline structure) | role-match |
| `public/admin/competitions.php` (modify inline delete branch, lines 73-80) | controller (inline) | request-response | same as foals.php | role-match |
| `public/admin/showrecords.php` (modify inline delete branch, lines 100-107) | controller (inline) | request-response | same as foals.php | role-match |
| `public/admin/deletions.php` (new) | controller/view | CRUD (read-heavy, list) | `public/admin/users.php` (table-list-view, D-06 mirrors Phase 11 D-01) | exact |
| `public/admin/deletion_approve.php` + `public/admin/deletion_reject.php` (new, or one combined handler) | controller | request-response | `public/admin/user_toggle_active.php` / `public/admin/user_delete.php` (small single-purpose POST-only handler w/ CSRF + redirect) | exact |
| `public/admin/index.php` (modify, add stat card) | controller/view | CRUD (aggregate read) | itself (existing `.admin-stat-row` block, lines 25-46) | exact |
| `public/admin/includes/admin_header.php` (modify nav, lines 334-343) | component | request-response (render) | itself (existing `isAdmin()`-gated `settings.php` nav link, lines 339-342) | exact |
| Public/admin list queries against `foals`/`competitions`/`showrecords`/`posts` (audit pass) | query/transform | CRUD | `horses`-table queries already filtered with `WHERE is_deleted = 0` (e.g. `foals.php` lines 11, 18-20, 141-144) | exact |

## Pattern Assignments

### `database/migrate_delete_approval.sql` (migration)

**Analog:** `database/migrate_posts_author.sql` (full file, 16 lines) — migration-file convention: comment header explaining purpose + phpMyAdmin import instruction + additive `ALTER TABLE`/backfill statements, no destructive operations.

```sql
-- ============================================================
-- Poisto-hyväksyntätyönkulku — pending_deletions + soft-delete-sarakkeet
-- Lisää is_deleted/deleted_at-sarakkeet foals/competitions/showrecords/posts-tauluihin
-- (mallina horses-taulu, schema.sql rivit 111-113, 121) ja uuden pending_deletions-jonotaulun.
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

ALTER TABLE `foals`
  ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  ADD KEY `idx_foals_deleted` (`is_deleted`);

-- (repeat ALTER for competitions, showrecords, posts — same two columns + index)

CREATE TABLE IF NOT EXISTS `pending_deletions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` ENUM('horse','foal','competition','showrecord','post') NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL COMMENT 'admin_users.id (mod)',
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT UNSIGNED DEFAULT NULL COMMENT 'admin_users.id (admin)',
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pending_deletions_status` (`status`),
  KEY `idx_pending_deletions_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_pending_deletions_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pending_deletions_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Soft-delete column reference** (`database/schema.sql` lines 111-113, 121 — `horses` table, the ONLY existing precedent):
```sql
  -- Pehmeä poisto
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  ...
  KEY `idx_horses_deleted` (`is_deleted`),
```

---

### `public/src/includes/helpers.php` — new `insertPendingDeletion()` function

**Analog:** `requireOwnResourceOrAdmin()` in same file (lines 78-90) — same file, same doc-comment style (Finnish doc block explaining purpose + params), same defensive-identity-from-session convention.

```php
/**
 * Luo odottavan pending_deletions-rivin annetulle sisältötyypille/idlle.
 * Estää duplikaatit (DEL-05): tarkistaa ensin ettei samalle entiteetille
 * ole jo pending-tilassa olevaa pyyntöä (check-then-insert, PHP-tason guard).
 *
 * @param string $entityType Whitelistattu arvo: horse|foal|competition|showrecord|post
 * @param int $entityId
 * @param int $requestedBy admin_users.id (aina $_SESSION['admin_id'], ei käyttäjän syötteestä)
 */
function insertPendingDeletion(string $entityType, int $entityId, int $requestedBy): void {
    $db = getDB();
    $chk = $db->prepare(
        'SELECT id FROM pending_deletions WHERE entity_type = :t AND entity_id = :id AND status = :status'
    );
    $chk->execute([':t' => $entityType, ':id' => $entityId, ':status' => 'pending']);
    if ($chk->fetch()) {
        return; // jo pending, ei duplikaattia
    }
    $db->prepare(
        'INSERT INTO pending_deletions (entity_type, entity_id, requested_by) VALUES (:t, :id, :by)'
    )->execute([':t' => $entityType, ':id' => $entityId, ':by' => $requestedBy]);
}
```

**Whitelist entity_type-to-table lookup pattern** (Claude's Discretion item, CONTEXT.md line 35 — use `match()`, no dynamic SQL from user input):
```php
function entityTypeToTable(string $entityType): string {
    return match ($entityType) {
        'horse'       => 'horses',
        'foal'        => 'foals',
        'competition' => 'competitions',
        'showrecord'  => 'showrecords',
        'post'        => 'posts',
        default       => throw new InvalidArgumentException('Invalid entity_type'),
    };
}
```

---

### `public/admin/horse_delete.php` (modify — add role branch)

**Analog:** itself, full file (24 lines, already read in entirety above).

**Current pattern (admin-only, soft-delete, no pending row):**
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');
// ... POST check, CSRF check, id validation ...
$db = getDB();
$stmt = $db->prepare('UPDATE horses SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0');
$stmt->execute([':id' => $id]);
redirect(SITE_URL . '/admin/horses.php?deleted=1');
```

**Target pattern (role branch to add):**
```php
requireRole('admin', 'mod'); // widen from 'admin' only

// ... POST + CSRF + id checks unchanged ...

$db = getDB();
$stmt = $db->prepare('UPDATE horses SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0');
$stmt->execute([':id' => $id]);

if (currentRole() === 'mod') {
    insertPendingDeletion('horse', $id, (int)$_SESSION['admin_id']);
}
// admin: soft-delete only, no pending row (D-04)

redirect(SITE_URL . '/admin/horses.php?deleted=1');
```

**WHERE-condition dedup guard** (already present, extend to all 4 new tables): `WHERE id = :id AND is_deleted = 0` prevents double-update.

---

### `public/admin/post_delete.php` (modify — hard-delete → soft-delete + 3-way role branch)

**Analog for target soft-delete pattern:** `public/admin/horse_delete.php` (as above).
**Analog for current file structure:** itself, full file (21 lines, already read).

**Current (hard-delete, admin-only):**
```php
requireRole('admin');
...
$db->prepare('DELETE FROM posts WHERE id = :id')->execute([':id' => $id]);
```

**Target (3-way branch — mod/admin/author-on-own-post per D-04, AUTHOR-03):**
```php
requireRole('admin', 'mod', 'author');
...
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) redirect(SITE_URL . '/admin/posts.php');

$db = getDB();
$ownerChk = $db->prepare('SELECT author_id FROM posts WHERE id = :id');
$ownerChk->execute([':id' => $id]);
$ownerRow = $ownerChk->fetch();
if (!$ownerRow) redirect(SITE_URL . '/admin/posts.php');

// IDOR defense-in-depth — same pattern as posts.php lines 39-46
requireOwnResourceOrAdmin((int)$ownerRow['author_id']);

$stmt = $db->prepare('UPDATE posts SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0');
$stmt->execute([':id' => $id]);

if (currentRole() === 'mod') {
    insertPendingDeletion('post', $id, (int)$_SESSION['admin_id']);
}
// admin: direct soft-delete, no pending row
// author-own-post: direct soft-delete, no pending row (AUTHOR-03) — requireOwnResourceOrAdmin already enforced ownership

redirect(SITE_URL . '/admin/posts.php?deleted=1');
```

**Ownership-check analog** (`public/admin/posts.php` lines 39-46 — IDOR defense-in-depth pattern to reuse verbatim):
```php
$ownerChk = $db->prepare('SELECT author_id FROM posts WHERE id = :id');
$ownerChk->execute([':id' => $edit_id]);
$ownerRow = $ownerChk->fetch();
if ($ownerRow) {
    requireOwnResourceOrAdmin((int)$ownerRow['author_id']);
}
```

---

### `public/admin/foals.php` / `competitions.php` / `showrecords.php` (modify inline `action=delete` branch)

**Analog:** `public/admin/horse_delete.php` for target soft-delete + branch logic; itself for current inline structure.

**Current (`foals.php` lines 122-129, hard-delete, admin-only):**
```php
} elseif ($action === 'delete' && $foal_id > 0) {
    requireRole('admin');
    $own = $db->prepare('SELECT id FROM foals WHERE id = :foal_id AND horse_id = :horse_id');
    $own->execute([':foal_id' => $foal_id, ':horse_id' => $horse_id]);
    if ($own->fetch()) {
        $db->prepare('DELETE FROM foals WHERE id = :foal_id')->execute([':foal_id' => $foal_id]);
    }
    redirect(SITE_URL . '/admin/foals.php?horse_id=' . $horse_id . '&deleted=1');
}
```

**Target:**
```php
} elseif ($action === 'delete' && $foal_id > 0) {
    requireRole('admin', 'mod');
    $own = $db->prepare('SELECT id FROM foals WHERE id = :foal_id AND horse_id = :horse_id');
    $own->execute([':foal_id' => $foal_id, ':horse_id' => $horse_id]);
    if ($own->fetch()) {
        $db->prepare('UPDATE foals SET is_deleted = 1, deleted_at = NOW() WHERE id = :foal_id AND is_deleted = 0')
           ->execute([':foal_id' => $foal_id]);
        if (currentRole() === 'mod') {
            insertPendingDeletion('foal', $foal_id, (int)$_SESSION['admin_id']);
        }
    }
    redirect(SITE_URL . '/admin/foals.php?horse_id=' . $horse_id . '&deleted=1');
}
```
Apply the identical transform to `competitions.php` (lines 73-80, entity_type `'competition'`) and `showrecords.php` (lines 100-107, entity_type `'showrecord'`).

---

### `public/admin/deletions.php` (new — approval list view)

**Analog:** `public/admin/users.php` (full table-list-view pattern, lines 1-80 already read — D-06 explicitly mirrors Phase 11 D-01's single unified `<table>`, no per-type sections).

**Query shell (whitelist JOIN across 5 tables via UNION, guided by CONTEXT.md's whitelist-mapping requirement):**
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

$db = getDB();
$pending = $db->query(
    "SELECT pd.id, pd.entity_type, pd.entity_id, pd.requested_at, u.username AS requested_by_name,
            COALESCE(h.name, f.foal_name, c.discipline_id, s.discipline_id, p.title) AS entity_label
     FROM pending_deletions pd
     JOIN admin_users u ON u.id = pd.requested_by
     LEFT JOIN horses h ON pd.entity_type='horse' AND h.id = pd.entity_id
     LEFT JOIN foals f ON pd.entity_type='foal' AND f.id = pd.entity_id
     LEFT JOIN competitions c ON pd.entity_type='competition' AND c.id = pd.entity_id
     LEFT JOIN showrecords s ON pd.entity_type='showrecord' AND s.id = pd.entity_id
     LEFT JOIN posts p ON pd.entity_type='post' AND p.id = pd.entity_id
     WHERE pd.status = 'pending'
     ORDER BY pd.requested_at DESC"
)->fetchAll();
```

**Table + row-action-buttons pattern** (`public/admin/users.php` lines 40-77 — table head, foreach row, inline single-purpose POST forms per action, `confirm()` only on destructive actions — approve/reject are reversible so no `confirm()` needed per CONTEXT.md line 82):
```php
<table>
  <thead>
    <tr><th>Tyyppi</th><th>Kohde</th><th>Pyytäjä</th><th>Pyydetty</th><th>Toiminnot</th></tr>
  </thead>
  <tbody>
    <?php foreach ($pending as $row): ?>
    <tr>
      <td><?= e($row['entity_type']) ?></td>
      <td><?= e($row['entity_label']) ?></td>
      <td><?= e($row['requested_by_name']) ?></td>
      <td><?= formatDate($row['requested_at']) ?></td>
      <td>
        <form method="post" action="<?= e(SITE_URL) ?>/admin/deletion_approve.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <button type="submit" class="btn-sm">Hyväksy</button>
        </form>
        <form method="post" action="<?= e(SITE_URL) ?>/admin/deletion_reject.php" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <button type="submit" class="btn-sm btn-danger">Hylkää</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
```

---

### `public/admin/deletion_approve.php` / `deletion_reject.php` (new)

**Analog:** `public/admin/user_toggle_active.php` / `public/admin/user_delete.php` (small single-purpose POST-only handler: `requireRole()`, POST check, CSRF check, id validation, one UPDATE, redirect) — same shape as `horse_delete.php`.

**deletion_approve.php target (D-05 — only status transitions, content already soft-deleted):**
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(SITE_URL . '/admin/deletions.php');
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) redirect(SITE_URL . '/admin/deletions.php');

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $db = getDB();
    $db->prepare(
        "UPDATE pending_deletions SET status = 'approved', reviewed_by = :by, reviewed_at = NOW()
         WHERE id = :id AND status = 'pending'"
    )->execute([':by' => $_SESSION['admin_id'], ':id' => $id]);
}
redirect(SITE_URL . '/admin/deletions.php?approved=1');
```

**deletion_reject.php target (D-05 — reverse soft-delete on content table + transition pending_deletions row, whitelist entity_type→table via `entityTypeToTable()`, PDO transaction for the two-write sequence per research SUMMARY.md line 26):**
```php
<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(SITE_URL . '/admin/deletions.php');
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) redirect(SITE_URL . '/admin/deletions.php');

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $db = getDB();
    $pd = $db->prepare("SELECT entity_type, entity_id FROM pending_deletions WHERE id = :id AND status = 'pending'");
    $pd->execute([':id' => $id]);
    $row = $pd->fetch();
    if ($row) {
        $table = entityTypeToTable($row['entity_type']); // whitelist match(), no dynamic SQL from input
        $db->beginTransaction();
        $db->prepare("UPDATE `$table` SET is_deleted = 0, deleted_at = NULL WHERE id = :eid")
           ->execute([':eid' => $row['entity_id']]);
        $db->prepare(
            "UPDATE pending_deletions SET status = 'rejected', reviewed_by = :by, reviewed_at = NOW() WHERE id = :id"
        )->execute([':by' => $_SESSION['admin_id'], ':id' => $id]);
        $db->commit();
    }
}
redirect(SITE_URL . '/admin/deletions.php?rejected=1');
```

---

### `public/admin/index.php` (modify — add DEL-04 stat card)

**Analog:** itself, existing `.admin-stat-row` block (lines 6-9, 25-46, full file already read).

**Query pattern to add** (mirrors existing `$horseCount`/`$compCount` lines 6-9):
```php
$pendingDeletionCount = $db->query("SELECT COUNT(*) FROM pending_deletions WHERE status = 'pending'")->fetchColumn();
```

**Stat card to add** (mirrors existing card markup, lines 31-35):
```php
    <div class="admin-stat-card">
      <div class="stat-icon">🗑️</div>
      <div class="stat-num"><?= (int)$pendingDeletionCount ?></div>
      <div class="stat-label">Odottavaa poistopyyntöä</div>
    </div>
```

---

### `public/admin/includes/admin_header.php` (modify — add nav link, lines 334-343)

**Analog:** itself, existing `settings.php` nav link (lines 339-342 — `isAdmin()`/role-array-gated `<a>` in "Sivusto" section).

```php
<?php if (in_array($role, ['admin'], true)): ?>
<a class="admin-nav-item <?= $_activePage === 'deletions' ? 'active' : '' ?>"
   href="<?= e(SITE_URL) ?>/admin/deletions.php">🗑️ Poistopyynnöt</a>
<?php endif; ?>
```
Place directly before or after the `settings.php` link block (line 339-342), inside the existing "Sivusto" `<div class="admin-nav-section">` (line 334).

---

## Shared Patterns

### CSRF protection
**Source:** `public/admin/horse_delete.php` line 11, `public/admin/users.php` line 58 (`generate_csrf_token()`/`validate_csrf_token()`)
**Apply to:** all new/modified POST handlers (`horse_delete.php`, `post_delete.php`, inline foal/competition/showrecord deletes, `deletion_approve.php`, `deletion_reject.php`)
```php
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    redirect(SITE_URL . '/admin/<list>.php');
}
```

### Role gating
**Source:** `public/src/includes/helpers.php` lines 98-116 (`requireRole()`)
**Apply to:** every modified delete handler — widen from `requireRole('admin')` to `requireRole('admin', 'mod')` (or add `'author'` for `post_delete.php`); `deletions.php`/`deletion_approve.php`/`deletion_reject.php` stay `requireRole('admin')` only.

### Ownership enforcement (IDOR defense)
**Source:** `public/src/includes/helpers.php` lines 78-90 (`requireOwnResourceOrAdmin()`), used in `public/admin/posts.php` lines 39-46, 86
**Apply to:** `post_delete.php` only — author role must own the post being deleted; admin/mod bypass per function's own logic.

### Soft-delete WHERE-guard against double-update
**Source:** `public/admin/horse_delete.php` line 21 (`... WHERE id = :id AND is_deleted = 0`)
**Apply to:** `horse_delete.php`, `post_delete.php`, and the three inline deletes in `foals.php`/`competitions.php`/`showrecords.php`.

### is_deleted = 0 query filter (audit pass)
**Source:** `public/admin/foals.php` lines 11, 18-20, 141-144 (existing `horses` filtering pattern to replicate for the 4 newly-soft-deletable tables)
**Apply to:** every list/detail query against `foals`/`competitions`/`showrecords`/`posts` on both admin and public-facing pages — CONTEXT.md flags this as a full audit-pass requirement (line 37), not just the list pages named above. Planner should enumerate all such queries during implementation.

### Stat-card row
**Source:** `public/admin/index.php` lines 25-46 (`.admin-stat-row`/`.admin-stat-card` CSS classes, already defined)
**Apply to:** `admin/index.php` DEL-04 counter card.

### Admin-only nav link with active-state highlighting
**Source:** `public/admin/includes/admin_header.php` lines 335-342 (`users.php`/`settings.php` links)
**Apply to:** new "Poistopyynnöt" nav link.

## No Analog Found

None — all 12 files/changes have a strong existing analog in the codebase (this is a small, tightly-patterned admin panel; every needed shape already exists somewhere).

## Metadata

**Analog search scope:** `public/admin/*.php`, `public/admin/includes/`, `public/src/includes/helpers.php`, `database/*.sql`
**Files scanned:** `horse_delete.php`, `post_delete.php`, `foals.php`, `competitions.php`, `showrecords.php`, `posts.php`, `users.php`, `helpers.php`, `admin_header.php`, `admin/index.php`, `schema.sql`, `migrate_posts_author.sql`
**Pattern extraction date:** 2026-07-17
