# Milestones

## v1.2 Käyttäjäroolit (Shipped: 2026-07-18)

**Phases completed:** 4 phases, 13 plans, 31 tasks

**Key accomplishments:**

- Rooli-infrastruktuurin perusta: admin_users.role/is_active-sarakkeet, keskitetyt requireRole()/currentRole()/isAdmin()-funktiot, roolinkirjoitus loginissa ja "Ei käyttöoikeutta" -redirect-kohde.
- Admin_header.php:n navigaatio piilottaa nyt roolikohtaisesti sisällönhallintalinkit (author näkee vain Dashboard+Postaukset, mod ei näe Asetuksia) synkronoituna suoraan Plan 02:n requireRole()-listoihin, ja uusi change_password.php antaa kaikille rooleille turvallisen oman salasanan vaihdon session fixation -suojauksella.
- generate_password() CSPRNG-apufunktio, admin-only käyttäjähallinta-nav-linkki, ja users.php-listasivu perinteisenä table-näkymänä kaikin per-rivin toiminnoin
- user_add.php generates a bcrypt-hashed password server-side and displays it once inline; user_edit.php updates username/role with a self-demotion guard blocking admins from removing their own admin role
- Kolme POST-only admin-toimintoa (reset_password, toggle_active, delete) salasanan nollaukseen kertanäytöllä sekä viimeisen adminin/oman tunnuksen lukituksenestoguardein (USER-03/04/05/06/07)
- requireRole() re-validates role/is_active from admin_users per-request (closing session-staleness gap), and user_add/user_edit wrap their writes in try/catch(PDOException) with a VARCHAR(50)-aligned validation bound (closing the long-username crash gap).
- Added posts.author_id column with FK to admin_users.id (ON DELETE SET NULL), backfilled existing posts to admin, and mirrored the change in schema.sql for fresh installs
- Author-role ownership enforcement on posts.php via a new `requireOwnResourceOrAdmin()` helper — list filtering, INSERT author_id assignment, and defense-in-depth IDOR guards on both the GET edit view and the POST update handler
- New `pending_deletions` polymorphic queue table plus soft-delete columns on foals/competitions/showrecords/posts, backed by a duplicate-safe `insertPendingDeletion()` and a whitelist-only `entityTypeToTable()` helper
- Six content-delete handlers converted from hard-delete/admin-only to role-branched soft-delete: mod requests are queued via `insertPendingDeletion()`, admin deletes directly, and author can only delete their own posts immediately
- Unified `deletions.php` approval list joining `pending_deletions` to all five content tables, dedicated `deletion_approve.php`/`deletion_reject.php` handlers (approve = status-only transition, reject = atomic content-restore + status transition via `entityTypeToTable()`), plus a DEL-04 dashboard counter and admin-only nav link
- Audit-passi joka lisää `is_deleted = 0` -suodatuksen kaikkiin jäljellä oleviin foals/competitions/showrecords/posts-kyselyihin — neljä admin-tiedostoa ja kuusi julkisen sivuston tiedostoa (molemmat teemat)

---

## v1.1 Teemajärjestelmä (Shipped: 2026-07-05)

**Phases completed:** 4 phases, 12 plans, 18 tasks

**Key accomplishments:**

- migrate_theme.sql lisää active_theme='default'-rivin settings-tauluun INSERT IGNORE -patternilla, ja public/themes/default/theme.json luo teema-hakemistorakenteen sekä tarjoaa minimaalisen teema-metadatan Wave 2:n shimille
- theme.php-shim määrittelee THEME_PATH/THEME_URL/THEMES_ROOT-vakiot settings-taulun active_theme-rivin perusteella ja tarjoaa path-traversal-suojatun resolveThemePath()-funktion realpath() + str_starts_with prefix-checkillä
- Yksittäisen hevosen profiilisivun (614-rivinen, monimutkaisin 7 sivupohjasta) HTML-osuus eroteltu puhtaaksi templateksi `public/themes/default/pages/hevonen.php`-tiedostoon; datanhaku, teema-hook ja 404-logiikka jäävät alkuperäiseen kontrolleriin muuttumattomana.
- index.php, hevoset.php, and kasvatus.php converted to data-only controllers delegating 100% of HTML rendering to `resolveThemePath('pages/X.php')`, with a standardized `resolveThemePath()`-based Model B root-override hook in all three.
- yhteystiedot.php, ajankohtaista.php, and postaus.php converted to data-only controllers delegating 100% of HTML rendering to `resolveThemePath('pages/X.php')`, with postaus.php's dual 404 branches unified to the silent `http_response_code(404); exit;` convention.
- hevonen.php — the largest public controller (614 lines) — converted to a data-only controller: ~470 lines of inline HTML and two template-owned helper functions removed, both 404 branches unified to the silent convention, canonical Model B hook retained unchanged, guarded Malli A delegation added.
- Success Criteria #3 empirically proven with a throwaway `testitema` theme: flipping `settings.active_theme` in the DB changed rendered output on two Malli A pages at HTTP 200 with zero controller edits, then the test theme was fully deleted per D-07 leaving no persistent artifact.
- New `public/themes/default/pages/.htaccess` blocks direct HTTP access to theme templates via `<FilesMatch "\.php$"> Deny from all`, and pre-existing `public/admin/settings.php` is formally verified (read-only, unmodified) to already satisfy THEME-10 and THEME-11.
- Theme system confirmed working end-to-end on Altervista production (/demotalli-02/): style.css served as text/css, all default-theme pages render without breakage, themes/ directory listing is blocked, and direct access to themes/default/pages/index.php returns 403 — closing out THEME-12.

**Closeout type:** override_closeout (1 known verification gap acknowledged — see STATE.md Deferred Items)

### Known Gaps

- Phase 6 (`06-VERIFICATION.md`) status is `human_needed`: 2 of 5 success criteria (path-traversal rejection at runtime, `active_theme` row in the DB) were never re-confirmed back into the verification file after the initial 2026-06-22 pass. In practice, Phase 9's Altervista production verification exercised the same `resolveThemePath()` logic and DB-backed active-theme resolution live, so the underlying behavior is validated — only the paperwork in `06-VERIFICATION.md` is stale.
- `oma-talli` theme has no `pages/.htaccess` protection (unlike `default`) — the theme is unfinished and was intentionally out of scope for v1.1 verification (D-06).

---
