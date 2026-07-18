---
status: complete
phase: 13-poisto-hyv-ksynt-ty-nkulku
source: [13-VERIFICATION.md]
started: 2026-07-18T06:32:19Z
updated: 2026-07-18T06:42:40Z
---

## Current Test

[testing complete]

## Tests

### 1. Deletions.php visual rendering with mixed entity types
expected: Kirjaudu admin-tunnuksella, siirry /admin/deletions.php ja tarkista sivun ulkoasu kun pending_deletions-taulussa on rivejä kaikista viidestä entity_type-arvosta samanaikaisesti (hevonen/varsa/kilpailu/näyttelytulos/postaus). Kaikki rivit näkyvät yhdessä taulukossa oikeilla suomenkielisillä tyyppinimillä ja luettavilla entity_label-arvoilla, ei PHP-virheitä tai tyhjiä/rikkinäisiä soluja.
result: pass

### 2. Role-based nav link visibility
expected: Kirjaudu mod- ja author-tunnuksilla ja tarkista, ettei admin-navigaatiossa näy 'Poistopyynnöt'-linkkiä kummallakaan roolilla; kirjaudu adminilla ja tarkista että linkki näkyy ja on aktiivinen deletions.php-sivulla. Vain admin näkee linkin; mod/author eivät. Admin-sessiolla linkki korostuu aktiivisena deletions.php:llä.
result: pass

### 3. End-to-end approve/reject click-through
expected: Klikkaa oikeasti 'Hyväksy'- ja 'Hylkää'-nappeja deletions.php-sivulla adminina kirjautuneena (CSRF-token + POST aidon lomakkeen kautta, ei suoraa SQL-simulaatiota) ja varmista redirect + flash-viesti näkyy oikein. Hyväksyntä ohjaa deletions.php?approved=1:een ja näyttää 'Poistopyyntö hyväksytty.' Hylkäys ohjaa deletions.php?rejected=1:een ja näyttää 'Poistopyyntö hylätty, sisältö palautettu näkyväksi.'
result: pass

## Summary

total: 3
passed: 3
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps
