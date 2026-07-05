---
phase: 10
slug: roolit-ja-autentikaation-perusta
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-07-05
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | None detected — no `phpunit.xml`, no `composer.json`, no `tests/` directory in repo `[VERIFIED: filesystem check]` |
| **Config file** | none — no Wave 0 install; manual/smoke-test is the established pattern for every prior phase in this codebase, and RESEARCH.md recommends against introducing a test framework for this phase (no new dependencies) |
| **Quick run command** | Manual smoke test (log in as the relevant role, verify page access + nav visibility for the file(s) touched) — no automated command exists |
| **Full suite command** | Same as above — no automated suite exists for this codebase |
| **Estimated runtime** | ~15–20 minutes for a full manual walkthrough of all 5 requirements across ~29 audited files |

---

## Sampling Rate

- **After every task commit:** Manual smoke test of the specific file(s) touched — verify `requireRole()` call is present with the correct role list and the page still renders for an allowed role.
- **After every plan wave:** Manual full pass — log in as `admin` (only real account until Phase 11), verify every page in the audit table still loads; out-of-role redirect behavior (ROLE-03) is verified by temporarily setting `role='mod'` / `role='author'` on the existing test account, testing, then reverting to `'admin'` (no real mod/author account exists until Phase 11 — this caveat must appear explicitly in plan verification steps).
- **Before `/gsd-verify-work`:** All 5 requirements (ROLE-01..04, AUTH-06) manually walked through end-to-end; no automated gate exists for this codebase currently.
- **Max feedback latency:** ~5 minutes per task (single file/page manual check).

---

## Per-Task Verification Map

> Task IDs are assigned during planning — this table maps requirements to their verification approach ahead of plan creation. The planner/executor should carry these rows forward into task-level `<verify>` blocks once Plan/Wave/Task IDs are known.

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| TBD | TBD | TBD | ROLE-01 | T-10-Auth | `admin_users.role` ENUM exists; existing admin backfilled to `'admin'`; `is_active` defaults to 1 | manual (phpMyAdmin `DESCRIBE`/`SELECT`) | none | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | ROLE-02 | T-10-Session | Role written to `$_SESSION['admin_role']` at login, read via `currentRole()` on every protected page | manual (log in, inspect session-derived behavior) | none | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | ROLE-03 | T-10-AccessControl | Out-of-role direct access redirects to `admin/ei-oikeutta.php`, not page content; inline delete branches in mixed files (`kasvatus_all.php`, `foals.php`, `competitions.php`, `showrecords.php`) also gate correctly | manual (temporary role-flip test on existing account per file/role combination) | none | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | ROLE-04 | — | Nav hides disallowed sections per role in `admin_header.php` | manual (visual check per role) | none | ❌ Wave 0 | ⬜ pending |
| TBD | TBD | TBD | AUTH-06 | T-10-PasswordChange | Wrong current password rejected; min-8-length enforced; mismatch rejected; success re-authenticates with new password + `session_regenerate_id(true)` | manual (each negative case, then successful change, then re-login) | none | ❌ Wave 0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] No test framework installed at all (PHPUnit or otherwise) — installing one is explicitly out of scope for this phase (no new dependencies); continue with manual/smoke testing as every prior phase in this codebase has done. **No Wave 0 install action required.**
- [ ] No fixture/seed mechanism exists for creating a temporary mod/author test account within Phase 10 itself. The plan must include an explicit manual-testing step describing the temporary role-flip-and-revert procedure (`UPDATE admin_users SET role='mod' WHERE username='admin'`, test, revert to `'admin'`) since Phase 11 (real multi-user creation) has not landed yet.

*This mirrors every prior phase in this project — manual/smoke-test-only is the established and accepted pattern here, not a gap introduced by this phase.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Role backfill correctness | ROLE-01 | No DB assertion tooling exists in this codebase | phpMyAdmin: `DESCRIBE admin_users;` confirms `role` ENUM + `is_active`; `SELECT role, is_active FROM admin_users WHERE username='admin';` confirms `'admin'` / `1` |
| Session role propagation | ROLE-02 | No test framework to assert session state | Log in as the admin account; confirm role-gated pages that should be visible render, and nav reflects `admin` role |
| Out-of-role redirect (all ~29 audited pages) | ROLE-03 | No real mod/author account exists until Phase 11; no automated HTTP test tooling | Temporarily set `role='mod'` (then `'author'`) on the existing account, visit each page outside that role's allowed set, confirm redirect to `admin/ei-oikeutta.php`; revert role to `'admin'` after each pass |
| Nav visibility per role | ROLE-04 | Visual/UI check, no test framework | While role-flipped per above, visually confirm `admin_header.php` hides links for sections outside the current role |
| Password change flow (all cases) | AUTH-06 | No automated form-submission test tooling | Attempt: wrong current password → rejected; new password < 8 chars → rejected; confirmation mismatch → rejected; valid change → success message, `session_regenerate_id(true)` fires, log out and back in with the new password succeeds |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies — N/A for this phase; all verification is manual per the established codebase pattern (see Test Infrastructure above)
- [ ] Sampling continuity: no 3 consecutive tasks without a manual verify step performed
- [ ] Wave 0 covers all MISSING references — no framework install needed; temporary role-flip procedure must be documented in the plan
- [ ] No watch-mode flags
- [ ] Feedback latency < ~5 min per task (manual)
- [ ] `nyquist_compliant: true` set in frontmatter once plan-checker confirms all 5 requirements have a documented manual verification path

**Approval:** pending
