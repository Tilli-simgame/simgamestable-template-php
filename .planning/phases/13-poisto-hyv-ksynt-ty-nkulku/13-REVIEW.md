---
phase: 13-poisto-hyv-ksynt-ty-nkulku
reviewed: 2026-07-17T00:00:00Z
depth: standard
files_reviewed: 23
files_reviewed_list:
  - database/migrate_delete_approval.sql
  - database/schema.sql
  - public/src/includes/helpers.php
  - public/admin/horse_delete.php
  - public/admin/post_delete.php
  - public/admin/foals.php
  - public/admin/competitions.php
  - public/admin/showrecords.php
  - public/admin/kasvatus_all.php
  - public/admin/deletions.php
  - public/admin/deletion_approve.php
  - public/admin/deletion_reject.php
  - public/admin/index.php
  - public/admin/includes/admin_header.php
  - public/admin/foal_edit.php
  - public/admin/kilpailut_all.php
  - public/admin/showrecords_all.php
  - public/admin/posts.php
  - public/pages/kasvatus.php
  - public/pages/hevonen.php
  - public/pages/index.php
  - public/pages/postaus.php
  - public/pages/ajankohtaista.php
  - public/themes/oma-talli/hevonen.php
findings:
  critical: 1
  critical_open: 0
  warning: 5
  warning_open: 4
  info: 2
  total: 8
  total_open: 6
status: issues_found
fixed_post_review:
  - CR-01 (commit bda578e, 2026-07-17)
  - WR-03 (commit bda578e, 2026-07-17)
---

# Phase 13: Code Review Report

**Reviewed:** 2026-07-17T00:00:00Z
**Depth:** standard
**Files Reviewed:** 23
**Status:** issues_found

## Summary

The delete-approval workflow is well structured overall: `entityTypeToTable()` is a proper whitelist `match()` (no dynamic table-name interpolation from user input), CSRF tokens and role checks are applied consistently, the reject-path uses `entity_type`/`entity_id` sourced from the `pending_deletions` ENUM column (never from raw request input) before building the `UPDATE \`$table\`` statement, and the phase-04 `is_deleted = 0` audit pass looks thorough — every public-facing and admin query touching `foals`/`competitions`/`showrecords`/`posts` that was in scope now filters soft-deleted rows, including the previously-buggy `(f.sire_id = :id1 OR f.dam_id = :id2) AND f.is_deleted = 0` pedigree/foal-lookup query (verified correctly parenthesized in both `pages/hevonen.php` and `themes/oma-talli/hevonen.php`).

However, there is one real correctness/data-integrity bug in the six soft-delete sites added in plan 13-02: `insertPendingDeletion()` is called unconditionally after the soft-delete `UPDATE`, without checking whether the `UPDATE` actually changed a row. Because the reject flow (`deletion_reject.php`) unconditionally restores whatever entity a pending row points to, this can lead to previously-approved (intentionally, permanently) deleted content being silently resurrected the next time an admin rejects an unrelated/duplicate pending request for the same entity. Details and reproduction in CR-01 below. A handful of lower-severity robustness and code-quality issues are also noted.

## Critical Issues

### CR-01: Soft-deleted content can be silently restored via a stale/duplicate delete resubmission — ✓ FIXED (2026-07-17, commit bda578e)

**File:** `public/admin/horse_delete.php:20-26`, `public/admin/post_delete.php:31-37`, `public/admin/foals.php:122-133`, `public/admin/competitions.php:73-84`, `public/admin/showrecords.php:100-112`, `public/admin/kasvatus_all.php:17-25`

**Resolution:** All six sites now capture the `UPDATE` statement handle and gate `insertPendingDeletion()` on `$stmt->rowCount() > 0`, so a duplicate/stale delete resubmission against an already-soft-deleted row no longer queues a phantom pending request.

**Issue:**
In every soft-delete site, `insertPendingDeletion()` is invoked purely based on `currentRole() === 'mod'`, without checking whether the preceding `UPDATE ... SET is_deleted = 1 ... WHERE id = :id AND is_deleted = 0` actually affected a row:

```php
$stmt = $db->prepare('UPDATE horses SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0');
$stmt->execute([':id' => $id]);

if (currentRole() === 'mod') {
    insertPendingDeletion('horse', $id, (int)$_SESSION['admin_id']);
}
```

`insertPendingDeletion()`'s duplicate guard only checks for an existing row with `status = 'pending'` for the same entity — it does **not** know or care that the entity is already `is_deleted = 1` from an earlier, already-`approved` request.

Reproduction:
1. Mod soft-deletes horse X → `pending_deletions` row A created (`status = 'pending'`).
2. Admin approves row A → `status = 'approved'`. Horse X stays deleted (correct, intended as permanent).
3. Mod resubmits the same delete action for horse X (stale cached list page + browser back button, double form submit on a slow connection, or simply clicking "Poista" again on a horse that is already gone from their view but not yet refreshed). The `UPDATE ... WHERE is_deleted = 0` affects **0 rows** (already deleted) — but `insertPendingDeletion()` still runs, and since no row for horse X currently has `status = 'pending'` (row A is `approved`), it inserts a brand-new row B (`status = 'pending'`).
4. Admin sees a new "pending deletion" for horse X in `deletions.php` and, believing it to be a duplicate/erroneous request, clicks **Hylkää** (reject).
5. `deletion_reject.php` unconditionally runs `UPDATE horses SET is_deleted = 0, deleted_at = NULL WHERE id = :eid` for row B's `entity_id` — **restoring content that the admin themselves already approved for permanent deletion**, with no indication to the admin that this "reject" action would resurrect previously-deleted data.

This is a genuine data-integrity/authorization-workflow bug: an admin's own prior approval decision can be silently undone by an unrelated "reject" click on a phantom follow-up request, because the code never verifies the soft-delete actually happened before queuing the approval request.

**Fix:** only queue a pending-deletion when the `UPDATE` actually flagged a (previously not-deleted) row:

```php
$stmt = $db->prepare('UPDATE horses SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND is_deleted = 0');
$stmt->execute([':id' => $id]);

if ($stmt->rowCount() > 0 && currentRole() === 'mod') {
    insertPendingDeletion('horse', $id, (int)$_SESSION['admin_id']);
}
```

Apply the same `rowCount() > 0` guard to the analogous blocks in `post_delete.php`, `foals.php`, `competitions.php`, `showrecords.php`, and `kasvatus_all.php`. Additionally, the "own resource" existence checks in `foals.php`/`competitions.php`/`showrecords.php` (e.g. `SELECT id FROM foals WHERE id = :foal_id AND horse_id = :horse_id`) should filter `AND is_deleted = 0` so an already-soft-deleted row can't re-enter the delete branch at all.

## Warnings

### WR-01: `CONCAT()` with a nullable column blanks out the pending-deletion label

**File:** `public/admin/deletions.php:9-13`
**Issue:** The `entity_label` computation uses plain `CONCAT()` for competitions/showrecords:
```sql
COALESCE(h.name, f.foal_name,
         CONCAT(c.discipline, ' ', c.competition_date),
         CONCAT(s.discipline, ' ', s.show_date),
         p.title,
         CONCAT(pd.entity_type, ' #', pd.entity_id)) AS entity_label
```
`discipline` is nullable on both `competitions` and `showrecords` (`schema.sql:170`, `196`). In MySQL, `CONCAT()` returns `NULL` if any argument is `NULL`, so a competition/showrecord with no discipline set will produce `NULL` here, and `COALESCE` falls through to the generic `"competition #47"` / `"showrecord #47"` fallback instead of showing the actual date/organizer info the admin needs to make an informed approve/reject decision.

**Fix:** use `CONCAT_WS()` (which skips `NULL` arguments) or `COALESCE()` per field:
```sql
CONCAT_WS(' ', c.discipline, c.competition_date)
```

### WR-02: `deletion_reject.php` transaction has no error handling / rollback

**File:** `public/admin/deletion_reject.php:22-28`
**Issue:**
```php
$db->beginTransaction();
$db->prepare("UPDATE `$table` SET is_deleted = 0, deleted_at = NULL WHERE id = :eid")
   ->execute([':eid' => $row['entity_id']]);
$db->prepare(
    "UPDATE pending_deletions SET status = 'rejected', reviewed_by = :by, reviewed_at = NOW() WHERE id = :id"
)->execute([':by' => $_SESSION['admin_id'], ':id' => $id]);
$db->commit();
```
With `PDO::ATTR_ERRMODE_EXCEPTION` set (`db.php:39`), if either `execute()` throws, the transaction is never explicitly rolled back and `commit()` is never reached — the uncaught `PDOException` terminates the script with a raw PHP fatal error page instead of a graceful redirect/error message. (MySQL will implicitly roll back the open transaction when the connection closes, so data stays consistent, but the admin gets an unhandled error page, and depending on server `display_errors` configuration this can leak internal details.)

**Fix:** wrap in `try/catch`, roll back explicitly, and redirect with an error flag:
```php
try {
    $db->beginTransaction();
    $db->prepare("UPDATE `$table` SET is_deleted = 0, deleted_at = NULL WHERE id = :eid")
       ->execute([':eid' => $row['entity_id']]);
    $db->prepare("UPDATE pending_deletions SET status = 'rejected', reviewed_by = :by, reviewed_at = NOW() WHERE id = :id")
       ->execute([':by' => $_SESSION['admin_id'], ':id' => $id]);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    error_log('deletion_reject failed: ' . $e->getMessage());
    redirect(SITE_URL . '/admin/deletions.php?error=1');
}
```

### WR-03: Debug error display left enabled in an admin page — ✓ FIXED (2026-07-17, commit bda578e)

**File:** `public/admin/kasvatus_all.php:2-3`
**Issue:**
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```
This forces PHP to render notices/warnings/errors (including stack traces and file paths) directly into the admin response for any authenticated admin/mod who triggers a PHP warning on this page — an information-disclosure risk, and inconsistent with every other reviewed admin page (none of which set this). This line predates phase 13 but remains live in a file phase 13-02 modified; since it's in the reviewed diff context it should be removed as part of cleanup.

**Fix:** remove both lines; rely on the project's normal (production-safe) error-reporting configuration.

### WR-04: `posts.php` POST edit path doesn't respect the soft-delete boundary

**File:** `public/admin/posts.php:38-49`
**Issue:** The GET `action=edit` path filters `WHERE id = :id AND is_deleted = 0` (line 80) before allowing the edit form to render. But the POST handler that actually performs the update only does:
```php
$ownerChk = $db->prepare('SELECT author_id FROM posts WHERE id = :id');
...
$db->prepare('UPDATE posts SET title=:t, slug=:s, content=:c WHERE id=:id')
   ->execute([':t'=>$title, ':s'=>$slug, ':c'=>$content, ':id'=>$edit_id]);
```
Neither the ownership `SELECT` nor the `UPDATE` filters `is_deleted = 0`. A direct POST to `posts.php` with the `id` of a soft-deleted post (own post, for an `author`; any post, for `admin`/`mod`) will still succeed in rewriting its title/content, even though the UI path to reach that form is blocked. This is a minor but real inconsistency in how the soft-delete boundary is enforced.

**Fix:** add `AND is_deleted = 0` to the ownership `SELECT` (and treat a soft-deleted post the same as "not found" — redirect instead of proceeding).

### WR-05: Non-atomic pending-row check before the reject transaction

**File:** `public/admin/deletion_reject.php:15-17`
**Issue:** The `SELECT ... WHERE status = 'pending'` that decides whether to proceed happens *before* `beginTransaction()`, with no row lock (`FOR UPDATE`). Two concurrent reject requests for the same `pending_deletions.id` (e.g. an admin double-clicking, or two admin sessions) can both read `status = 'pending'` before either commits, causing the entity `UPDATE` (and the reviewed_by/reviewed_at overwrite) to run twice. The second run is largely idempotent for the entity row itself, but `reviewed_by`/`reviewed_at` get silently overwritten by whichever request commits last, without an error or the second request being aware the row was already processed by someone else.

**Fix:** move the pending-status check inside the transaction using `SELECT ... FOR UPDATE`, or make the final `pending_deletions` update itself conditional (`WHERE id = :id AND status = 'pending'`) and only run the entity-restore `UPDATE` if that conditional update's `rowCount() > 0`.

## Info

### IN-01: Redundant `requireRole()` calls in delete branches

**File:** `public/admin/foals.php:123`, `public/admin/competitions.php:74`, `public/admin/showrecords.php:101`, `public/admin/kasvatus_all.php:18`
**Issue:** Each of these files already calls `requireRole('admin', 'mod')` at the top of the script (e.g. `foals.php:3`), which redirects away before any POST handling runs if the caller isn't admin/mod. The additional `requireRole('admin', 'mod')` call inside the `action === 'delete'` branch is therefore always a no-op — dead code that adds noise without changing behavior.
**Fix:** remove the redundant in-branch calls, or if the intent was defense-in-depth for a future refactor where the file-level check might be loosened, add a comment explaining why it's intentionally duplicated.

### IN-02: Inconsistent CSS/behavior duplication between `showrecords.php` and `showrecords_all.php` / `competitions.php` and `kilpailut_all.php`

**File:** `public/admin/showrecords.php`, `public/admin/showrecords_all.php`, `public/admin/competitions.php`, `public/admin/kilpailut_all.php`
**Issue:** The judge/organizer link-rendering logic (`$judgeLabel`, `$judgeHtml`, `$pbClass` placement-badge mapping) is duplicated verbatim across the per-horse and "all" listing pages. Not a bug, but a maintenance risk — a future fix to one (e.g. the WR-01-style `CONCAT`/label logic) is easy to apply in only one of the two copies.
**Fix:** consider extracting the shared label-formatting helpers into `helpers.php` in a future cleanup pass (out of scope for this phase).

---

_Reviewed: 2026-07-17T00:00:00Z_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
