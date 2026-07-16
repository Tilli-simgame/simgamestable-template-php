---
status: complete
phase: 10-roolit-ja-autentikaation-perusta
source: [10-VERIFICATION.md]
started: 2026-07-16T08:41:06Z
updated: 2026-07-16T09:10:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Aja database/migrate_roles.sql phpMyAdminissa (localhost:8080 -> tietokanta -> Import), sitten DESCRIBE admin_users; ja SELECT role, is_active FROM admin_users WHERE username='admin';
expected: admin_users saa role ENUM('admin','mod','author') ja is_active TINYINT(1) -sarakkeet; admin-tunnus role='admin', is_active=1. Kirjaudu admin-tunnuksella -> $_SESSION['admin_role']='admin' kirjoittuu ja kirjautuminen onnistuu.
result: pass

### 2. Rooli-flip-testi: UPDATE admin_users SET role='mod' WHERE username='admin'; kirjaudu ulos/sisään; yritä avata settings.php ja POST action=delete foals.php/kasvatus_all.php/competitions.php/showrecords.php-tiedostoihin suoralla osoitteella. Toista role='author'-arvolla admin-only- ja admin+mod-sivuille. Palauta role='admin' lopuksi.
expected: mod-roolilla: settings.php ja delete-POSTit ohjautuvat admin/ei-oikeutta.php:hen, riviä ei poisteta. author-roolilla: vain index.php/posts.php avautuvat, loput ohjautuvat ei-oikeutta.php:hen.
result: pass

### 3. change_password.php:n täysi POST-flow selaimessa: väärä nykyinen salasana, <8 merkin uusi, täsmäämätön vahvistus (kolme negatiivitapausta), sekä onnistunut vaihto -> kirjaudu ulos ja sisään uudella salasanalla.
expected: Kolme negatiivitapausta torjutaan inline-virheellä ilman UPDATE:a; onnistunut vaihto näyttää 'Salasana vaihdettu onnistuneesti.', käyttäjä pysyy kirjautuneena, ja uudella salasanalla kirjautuminen onnistuu heti.
result: pass

## Summary

total: 3
passed: 3
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps
