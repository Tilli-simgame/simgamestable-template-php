# Milestones

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
