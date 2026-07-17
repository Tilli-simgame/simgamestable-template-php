---
status: testing
phase: 12-sis-lt-tyyppien-roolirajaus
source: [12-VERIFICATION.md]
started: 2026-07-17T07:05:48Z
updated: 2026-07-17T07:05:48Z
---

## Current Test

number: 1
name: Manual UAT checklist (a)-(e) — post ownership and IDOR redirect behavior
expected: |
  (a) Logged in as `author`, `posts.php` list shows only that author's own posts.
  (b) As that author, opening `posts.php?action=edit&id=X` for another author's post via direct URL redirects to `ei-oikeutta.php`.
  (c) As that author, submitting a crafted POST to `posts.php` with `edit_id` set to another author's post ID does NOT update the post and redirects to `ei-oikeutta.php` before the UPDATE runs.
  (d) Logged in as `admin` and separately as `mod`, both see and can edit ALL posts (no ownership restriction).
  (e) As `mod` and as `author`, attempting to open `users.php` and `settings.php` via direct URL both redirect to `ei-oikeutta.php`.
awaiting: user response

## Tests

### 1. Manual UAT checklist (a)-(e) — post ownership and IDOR redirect behavior
expected: All five behaviors hold exactly as described; no author can view/edit/enumerate another author's post, admin/mod remain unrestricted, and mod/author cannot reach users.php/settings.php.
result: [pending]

## Summary

total: 1
passed: 0
issues: 0
pending: 1
skipped: 0
blocked: 0

## Gaps
