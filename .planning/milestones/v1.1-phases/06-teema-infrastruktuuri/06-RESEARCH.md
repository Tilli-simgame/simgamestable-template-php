# Phase 6: Teema-infrastruktuuri - Research

**Researched:** 2026-06-22
**Domain:** PHP path security, theme abstraction layer, MySQL settings pattern
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Uusi `database/migrate_theme.sql` — yhdenmukainen 8 olemassa olevan `migrate_*.sql`-tiedoston pattern kanssa.
- **D-02:** `active_theme`-rivin oletusarvo on `'default'` (`INSERT IGNORE INTO settings ('active_theme', 'default')`).
- **D-03:** Funktiosignatuuri: `resolveThemePath(string $subPath): string|false` — yksi merkkijonoargumentti, joka on suhteellinen polku teemakansion sisällä.
- **D-04:** Turvallisuus: preg_match + realpath + prefix-check. Tarkistukset järjestyksessä: (1) preg_match allowlist teemanimelle, (2) realpath palauttaa todellisen polun, (3) prefix-check varmistaa polun pysyvän `public/themes/`-hakemiston sisällä.
- **D-05:** Fallback-logiikka: aktiivinen teema → default-teema → `false`.
- **D-06:** Palauttaa absoluuttisen palvelinpolun (PHP `include`/`require` -käyttöön).
- **D-07:** `public/pages/index.php` saa `require_once '../src/includes/theme.php'` db.php-requireen jälkeen — vain tämä rivi lisätään. Phase 8 migroi loput 6 sivua.
- **D-08:** `theme.json` minimaalinen rakenne: `{"name": "Teeman nimi", "version": "1.0.0"}`.
- **D-09:** `src/includes/theme.php` EI saa olla require-kutsussa `db.php`:ssä — admin-sivut eivät koskaan lataa shimmiä.
- **D-10:** `public/assets/css/style.css` pysyy muuttumattomana.

### Claude's Discretion

None — kaikki päätökset on tehty CONTEXT.md:ssä.

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| THEME-01 | `resolveThemePath()`-helper path-traversal-suojauksella (realpath + prefix-check) ja default-fallbackilla | D-03, D-04, D-05, D-06: funktiosignatuuri, turvallisuuslogiikka ja palautusarvo on lukittu; `validate_file_name()` -koodiesimerkki olemassa helpers.php:ssä mallina |
| THEME-02 | Aktiivinen teema tallennetaan `settings`-tauluun `active_theme`-rivinä | D-01, D-02: migrate_theme.sql -pattern ja INSERT IGNORE -vakioarvo on lukittu; migrate_settings.sql on valmis malli |
| THEME-03 | Julkiset sivut saavat `THEME_PATH`- ja `THEME_URL`-vakiot `src/includes/theme.php`-shimistä; admin EI lataa shimmiä | D-07, D-09: index.php saa shimin integration proof; admin-eristys on arkkitehturinen rajoite |
| THEME-04 | Jokainen teema sisältää `theme.json` (nimi, versio) | D-08: minimaalinen rakenne lukittu; `public/themes/default/theme.json` luodaan Phase 6:ssa testauksen mahdollistamiseksi |
</phase_requirements>

---

## Summary

Phase 6 rakentaa teemajärjestelmän infrastruktuurikerroksen ilman yhtään näkyvää käyttöliittymämuutosta. Kaikki toteutuspäätökset on tehty CONTEXT.md-session aikana — tämä tutkimus dokumentoi teknisen perustan, jonka päälle planner luo tehtävät.

Vaihe koostuu neljästä konkreettisesta artefaktista: (1) `database/migrate_theme.sql` lisää `active_theme`-rivin `settings`-tauluun INSERT IGNORE -patternilla, (2) `src/includes/theme.php` on erillinen shim joka lukee DB:stä aktiivisen teeman, rakentaa `THEME_PATH`- ja `THEME_URL`-vakiot, ja tarjoaa `resolveThemePath()`-funktion, (3) `public/pages/index.php` saa yhden `require_once`-rivin integraatiotodistukseksi, ja (4) `public/themes/default/theme.json` syntyy testausta varten.

Kriittisin tekninen huomio on `realpath()`-käyttäytyminen: funktio palauttaa `false` jos polku ei olemassa — tämä on fallback-logiikan aktivaattori, ei virhe. Prefix-check tulee tehdä merkkijonon alkuun (`str_starts_with`) eikä sisältöön, koska `strpos()` palauttaisi väärän positiivisen tuloksen kun teemakansio sisältää hakemistopolun jossain kohti merkkijonoa.

**Primary recommendation:** Toteuta `resolveThemePath()` suoraan `src/includes/theme.php`-shimiin (ei helpers.php:hen) admin-eristyksen takaamiseksi. Shim on ainoa paikka joka tietää teemoista — helpers.php pysyy teema-agnostisena.

---

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Teemanimivalidointi | API / Backend (PHP) | — | Turvallisuustarkistus kuuluu palvelinpäähän; selain ei koskaan näe teemanimiä suoraan |
| THEME_PATH / THEME_URL -vakioiden luonti | Frontend Server (PHP shim) | — | Shim on välityskerros joka muuttaa DB-arvon käyttökelpoisiksi PHP-vakioiksi |
| DB-luku (active_theme) | API / Backend (PHP) | — | Tietokantaoperaatio kuuluu palvelimelle; shim käyttää olemassa olevaa `getDB()`-singletonia |
| Teemakansiorakenne (`public/themes/`) | CDN / Static | Frontend Server | Staattiset tiedostot (CSS, kuvat) serveerataan suoraan; PHP-tiedostot (sivupohjat) ajetaan palvelimella |
| theme.json -löydettävyys | API / Backend (PHP) | — | `json_decode(file_get_contents(...))` palvelimella; selain ei koskaan saa raakaa theme.json:ia |

---

## Standard Stack

### Core

Tämä vaihe ei asenna ulkoisia paketteja. Kaikki rakentuu PHP:n sisäisille funktioille.

| Toiminto | PHP-funktio | Huomio |
|----------|-------------|--------|
| Path traversal -suojaus | `realpath()` + `str_starts_with()` | `realpath()` resoloi symlinkit ja normalisoi `../` sekä `%2F` |
| Teemanimivalidointi | `preg_match('/^[a-zA-Z0-9_-]+$/', $name)` | Sallii vain turvallisen merkistön |
| theme.json -luku | `file_get_contents()` + `json_decode(..., true)` | Palauttaa `null` jos JSON on viallinen |
| DB-luku | `getDB()` (olemassa oleva singleton) | `SELECT setting_value FROM settings WHERE setting_key = 'active_theme'` |
| Vakioiden määrittely | `define('THEME_PATH', ...)` + `define('THEME_URL', ...)` | Vain jos vakiota ei ole vielä määritelty (`defined()` -tarkistus) |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `realpath()` + prefix-check | Pelkkä `preg_match` allowlist | `realpath()` resoloi symlinkit ja OS-spesifit polkuvariantit; pelkkä regex ei suojaa kaikilta vektoreilta |
| Erillinen `theme.php`-shim | `resolveThemePath()` helpers.php:hen | helpers.php on jaettu admin-sivuilla; shim-erillisyys on ainoa tapa taata admin-eristys (D-09) |

---

## Package Legitimacy Audit

Ei ulkoisia paketteja tässä vaiheessa.

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

---

## Architecture Patterns

### System Architecture Diagram

```
[HTTP request: public page]
        |
        v
[public/pages/index.php]
        |
        ├─ require_once db.php  ──→ [config.php] [helpers.php] [session]
        |
        └─ require_once theme.php
                |
                ├─ getDB() ──→ [settings table: active_theme = 'default']
                |
                ├─ validate theme name (preg_match allowlist)
                |
                ├─ build candidate path: /var/www/public/themes/default/
                |
                ├─ define THEME_PATH  (absolute server path)
                |
                └─ define THEME_URL   (SITE_URL . '/themes/default/')

[public page calls resolveThemePath('pages/index.php')]
        |
        ├─ realpath(THEME_PATH . 'pages/index.php')
        |       |
        |       ├─ file exists → prefix-check → return absolute path
        |       |
        |       └─ file missing → try default theme
        |               |
        |               ├─ file exists in default → return absolute path
        |               └─ not found → return false
        |
        └─ caller: require resolveThemePath('pages/index.php') or handle false

[HTTP request: admin page]
        |
        v
[public/admin/*.php]
        |
        └─ require_once db.php  ──→ [config.php] [helpers.php] [session]
                                      (theme.php ei ladata koskaan)
```

### Recommended Project Structure

```
src/
└── includes/
    ├── db.php           # Muuttumaton (ei theme.php:tä sisään)
    ├── config.php       # Muuttumaton (SITE_URL käytössä)
    ├── helpers.php      # Muuttumaton (ei teemafunktiota)
    └── theme.php        # UUSI: shim joka definee THEME_PATH, THEME_URL,
                         #        ja tarjoaa resolveThemePath()

database/
└── migrate_theme.sql    # UUSI: INSERT IGNORE active_theme = 'default'

public/
├── pages/
│   └── index.php        # MUUTOS: lisätään yksi require_once theme.php
└── themes/
    └── default/
        └── theme.json   # UUSI: {"name":"Default","version":"1.0.0"}
```

### Pattern 1: theme.php-shim rakenne

**What:** Erillinen tiedosto joka lukee DB:stä aktiivisen teeman, määrittelee THEME_PATH- ja THEME_URL-vakiot, ja tarjoaa resolveThemePath()-funktion.

**When to use:** Kaikilla julkisilla sivuilla heti db.php:n require_oncen jälkeen. Ei koskaan admin-puolella.

**Example:**
```php
// Source: CONTEXT.md D-03 through D-06, consistent with existing helpers.php pattern
<?php
/**
 * Teemashim — tarjoaa THEME_PATH, THEME_URL ja resolveThemePath()
 * Ladataan VAIN julkisilla sivuilla (db.php:n require jälkeen).
 * ÄLÄ lisää tätä db.php:hen — admin-sivut eivät saa ladata shimmiä.
 */

if (!defined('THEME_PATH')) {
    // Lue aktiivinen teema tietokannasta
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1'
    );
    $stmt->execute([':k' => 'active_theme']);
    $rawTheme = $stmt->fetchColumn() ?: 'default';

    // Validoi teemanimi — salli vain turvallinen merkistö
    $themeName = preg_match('/^[a-zA-Z0-9_-]+$/', $rawTheme) ? $rawTheme : 'default';

    // Absoluuttinen polku public/themes/-kansioon
    $themesRoot = realpath(__DIR__ . '/../../themes');

    define('THEME_PATH', $themesRoot . DIRECTORY_SEPARATOR . $themeName . DIRECTORY_SEPARATOR);
    define('THEME_URL',  SITE_URL . '/themes/' . $themeName . '/');
    define('THEMES_ROOT', $themesRoot . DIRECTORY_SEPARATOR);
}

/**
 * Palauttaa absoluuttisen palvelinpolun teematiedostolle.
 *
 * 1. Tarkistaa aktiivisesta teemasta
 * 2. Fallback: default-teema
 * 3. Jos ei löydy kummastakaan → false
 *
 * @param string $subPath Suhteellinen polku teemakansion sisällä
 *                        (esim. 'pages/index.php', 'includes/header.php')
 * @return string|false Absoluuttinen palvelinpolku tai false
 */
function resolveThemePath(string $subPath): string|false {
    // Normalisoi separaattorit (Windows-kompatibiliteetti)
    $subPath = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subPath), DIRECTORY_SEPARATOR);

    // --- Aktiivinen teema ---
    $candidate = THEME_PATH . $subPath;
    $real = realpath($candidate);
    if ($real !== false && str_starts_with($real, THEME_PATH)) {
        return $real;
    }

    // --- Default-teema fallback ---
    $defaultPath = THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR;
    $defaultCandidate = $defaultPath . $subPath;
    $realDefault = realpath($defaultCandidate);
    if ($realDefault !== false && str_starts_with($realDefault, $defaultPath)) {
        return $realDefault;
    }

    return false;
}
```

### Pattern 2: migrate_theme.sql

**What:** SQL-migraatiotiedosto joka lisää `active_theme`-rivin `settings`-tauluun olemassa olevan patternin mukaisesti.

**Example:**
```sql
-- Source: CONTEXT.md D-01, D-02; consistent with database/migrate_settings.sql pattern
-- ============================================================
-- Teema-infrastruktuuri — active_theme-asetus
-- Aja phpMyAdminissa: Import → valitse tämä tiedosto
-- ============================================================

-- settings-taulu on jo olemassa (migrate_settings.sql loi sen).
-- INSERT IGNORE ei ylikirjoita olemassa olevaa arvoa.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('active_theme', 'default');
```

### Pattern 3: index.php integration proof

**What:** Yksi rivi lisätään `public/pages/index.php`:hen todistamaan shimin toimivan oikeassa kontekstissa.

**Example:**
```php
// Source: CONTEXT.md D-07
// Lisätään db.php require_oncen JÄLKEEN, ennen $page_title-määrittelyä:
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/../src/includes/theme.php'; // ← UUSI Phase 6:ssa
```

### Anti-Patterns to Avoid

- **theme.php db.php:n sisälle:** db.php on ladattu sekä admin- että julkisilla sivuilla. Jos theme.php on siellä, admin lataa teemashimin — rikkoo D-09:n.
- **preg_match ilman realpath:** Hyökkääjä voi URL-enkoodata (`%2F` = `/`) tai käyttää OS-spesifejä variantteja jotka regex ei nappaa. `realpath()` resoloi kaikki tällaiset ennen prefix-checkiä.
- **Prefix-check `strpos()`:lla:** `strpos('/var/www/public/themes/eviltheme/../default/', '/var/www/public/themes/')` palauttaa `0` (tosi) vaikka polku ei pysy teemakansion sisällä. Käytä `str_starts_with()` joka vertaa kirjaimellisesti alkuun.
- **`realpath()` ilman olemassaolotarkistusta:** `realpath()` palauttaa `false` jos tiedosto ei olemassa — tämä on tarkoitettu käyttäytyminen fallback-aktivoinnille. Älä tulkitse `false`-palautusta virheeksi ennen kuin default-fallbackkin on yritetty.
- **THEME_PATH ilman trailing separaattoria:** Jos THEME_PATH = `/var/www/themes/default`, sitten `/var/www/themes/defaultevil/../../etc` alkaa THEME_PATHilla. Trailing DIRECTORY_SEPARATOR varmistaa että prefix-check toimii oikein.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Path traversal -suojaus | Oma regex joka yrittää nappaa kaikki `../` variantit | `realpath()` + `str_starts_with(THEME_PATH)` | `realpath()` resoloi symlinkit, unicode-variantit, Windows-polut — käsin rakennettu regex jää aina vajaa |
| DB-singleton | Uusi PDO-yhteys theme.php:ssä | `getDB()` (helpers.php kautta) | getDB() on jo lazy singleton; kaksi yhteyttä on tarpeeton resurssien tuhlailu |
| JSON-validointi | Oma parser | `json_decode(file_get_contents($path), true)` + `=== null` tarkistus | PHP:n sisäinen JSON-decoder on riittävä pienelle theme.json-rakenteelle |

**Key insight:** PHP:n `realpath()` on ainoa luotettava keino tehdä path traversal -suojausta — se tekee OS-tasoisen polun normalisoinnin joka kattaa kaikki variantit (symlinkit, `..`, `.`, URL-enkoodatut merkit jotka web-palvelin on jo dekooinut) yhdellä kutsulla.

---

## Runtime State Inventory

Tämä ei ole rename/refactor/migraatiovaihe olemassa olevaan dataan. Uusi `active_theme`-rivi lisätään INSERT IGNORE:lla — se ei korvaa olemassa olevia rivejä.

| Category | Items Found | Action Required |
|----------|-------------|-----------------|
| Stored data | `settings`-taulussa ei vielä `active_theme`-riviä | `migrate_theme.sql` luo sen INSERT IGNORE:lla |
| Live service config | Ei teemaan liittyvää ajoaikakonfiguraatiota | None |
| OS-registered state | Ei OS-tason rekisteröintejä | None |
| Secrets/env vars | Ei teemaan liittyviä sekrettejä | None |
| Build artifacts | `public/themes/`-hakemistoa ei olemassa | Phase 6 luo `public/themes/default/theme.json` |

---

## Common Pitfalls

### Pitfall 1: realpath() palauttaa false olemassa olevalle hakemistolle

**What goes wrong:** `realpath('/var/www/public/themes/default/')` palauttaa `/var/www/public/themes/default` (ilman trailing slashia) vaikka hakemisto on olemassa. THEME_PATH jossa on trailing slash ei vastaa `realpath()`-paluuarvoa — prefix-check epäonnistuu.

**Why it happens:** `realpath()` normalisoi polkuja ja poistaa trailing slashin hakemistoilta. Tiedostopolut vastaavat aina ilman trailing slashia.

**How to avoid:** Rakenna THEME_PATH aina `realpath(...) . DIRECTORY_SEPARATOR`-yhdistelmällä. Silloin prefix-check vertaa `DIRECTORY_SEPARATOR`-päätteistä prefixiä tiedostopolkuun joka ei sisällä trailing separaattoria — tarkistus toimii oikein.

**Warning signs:** `resolveThemePath()` palauttaa aina `false` vaikka tiedosto on olemassa oikeassa paikassa.

### Pitfall 2: THEMES_ROOT ei ole olemassa Phase 6:ssa

**What goes wrong:** `realpath(__DIR__ . '/../../themes')` palauttaa `false` jos `public/themes/`-hakemistoa ei ole luotu. THEME_PATH ja THEMES_ROOT rakentuvat tästä, joten kaikki `resolveThemePath()`-kutsut palauttavat `false`.

**Why it happens:** `public/themes/`-hakemisto ei ole olemassa ennen Phase 6:a — tiedostojärjestelmässä ei ole mitään jonka `realpath()` voisi resoloida.

**How to avoid:** Phase 6:n Wave 0 luo `public/themes/default/theme.json`. Tiedoston luominen automaattisesti luo myös hakemistopolun. Shim ei saa kuolla `realpath()`-epäonnistumiseen — se voi palata fallbackiin tai käyttää `__DIR__`-pohjaista rakentamista jos `realpath()` palauttaa `false`.

**Warning signs:** Kaikki `resolveThemePath()`-kutsut palauttavat `false` vaikka logiikka näyttää oikealta.

### Pitfall 3: settings-taulussa ei ole active_theme-riviä suoritushetkellä

**What goes wrong:** `fetchColumn()` palauttaa `false` (ei `null`), jos riviä ei löydy — tämä on PHP:n PDO-käyttäytyminen. `$stmt->fetchColumn() ?: 'default'` toimii oikein (`false` on falsy), mutta kehittäjä saattaa käyttää `?? 'default'` joka ei toimi koska `false ?? 'default'` = `false`.

**Why it happens:** `??` (null coalescing) toimii vain `null`-arvoille, ei `false`-arvoille. PDO `fetchColumn()` palauttaa `false` kun riviä ei löydy.

**How to avoid:** Käytä `?: 'default'` (Elvis operator) eikä `?? 'default'` DB-fallbackissa.

**Warning signs:** Teema pysyy tyhjänä merkkijonona tai aiheuttaa `false`-ohjauksen vaikka `INSERT IGNORE` on ajettu.

### Pitfall 4: admin-sivut alkavat ladata theme.php:tä

**What goes wrong:** Joku lisää `require_once theme.php`:n `db.php`:hen (loogisin paikka automaattiseen lataukseen), jolloin kaikki admin-sivut lataavat shimin. Tämä tekee DB-kyselyn jokaisella admin-sivulla turhaan ja rikkoo eristysperiaatteen.

**Why it happens:** `db.php` on kätevä paikka "kaikille yhteiselle" koodille — mutta teemashim EI kuulu kaikille.

**How to avoid:** theme.php on `require_once`-kutsuttava vain julkisista sivukontrollereista. Phase 6:ssa vain `public/pages/index.php`. Ei koskaan `db.php`:hen.

**Warning signs:** admin-sivut aiheuttavat DB-kyselyn `settings`-tauluun `active_theme`-avaimella (näkyy slow query logissa tai debug-tulosteissa).

---

## Code Examples

Verified patterns from existing codebase:

### DB-pattern: settings-arvon luku (nykyinen käytäntö)

```php
// Source: public/admin/settings.php (olemassa oleva koodi)
$rows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
$s = [];
foreach ($rows as $row) {
    $s[$row['setting_key']] = $row['setting_value'] ?? '';
}
```

### DB-pattern: settings-arvon kirjoitus (nykyinen käytäntö)

```php
// Source: public/admin/settings.php (olemassa oleva koodi)
$stmt = $db->prepare(
    'INSERT INTO settings (setting_key, setting_value)
     VALUES (:k, :v)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
);
$stmt->execute([':k' => $key, ':v' => $val]);
```

### Path-traversal -suojauspatterni (olemassa oleva referenssi)

```php
// Source: public/src/includes/helpers.php validate_file_name()
// Sallitaan vain turvallisia merkkejä
if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) { ... }
// Torjutaan path traversal -yritykset
if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) { ... }
```

Huomio: `resolveThemePath()` käyttää samaa periaatetta mutta **vahvempaa** toteutusta (`realpath()` + `str_starts_with()`), koska teemahakemistopolku sisältää alihakemistoja (esim. `pages/index.php`) joita yksinkertainen `strpos` ei voi turvallisesti validoida.

### THEME_URL rakentaminen SITE_URL:sta

```php
// Source: CONTEXT.md canonref + public/src/includes/config.php SITE_URL-pattern
define('SITE_URL', rtrim(getenv('SITE_URL') ?: 'https://tilli.altervista.org/demotalli', '/'));
// → THEME_URL:
define('THEME_URL', SITE_URL . '/themes/' . $themeName . '/');
```

### theme.json -luku

```php
// Source: CONTEXT.md D-08; PHP built-in functions
$jsonPath = $themePath . 'theme.json';
$meta = null;
if (is_readable($jsonPath)) {
    $decoded = json_decode(file_get_contents($jsonPath), true);
    if (is_array($decoded)) {
        $meta = $decoded; // ['name' => '...', 'version' => '...']
    }
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardkoodattu `header.php`-include julkisella sivulla | `resolveThemePath()` resoloi include-polun teemahakemistosta | Phase 6 → 8 | Sivuston ulkoasun voi vaihtaa ilman koodimuutosta |
| `color_theme`-asetus CSS-luokkana yhdessä tiedostossa | `active_theme` erillisenä teemainstanssin polkuna | Phase 6 | Eri teemat voivat olla täysin erilaisia PHP/HTML-rakenteita |

**Deprecated/outdated:**
- `color_theme` settings-taulussa: Tämä on CSS-variablepohjainen väriteema, ei PHP-teemajärjestelmä. Se pysyy ennallaan (admin käyttää sitä). `active_theme` on uusi, erillinen asetus PHP-teemajärjestelmälle.

---

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | `str_starts_with()` on käytettävissä (PHP 8.0+) | Architecture Patterns | Jos PHP < 8.0, korvataan `strncmp($real, THEME_PATH, strlen(THEME_PATH)) === 0`-vertailulla |
| A2 | Altervista ajaa PHP 8.0+ | Architecture Patterns | Vaikuttaa `str_starts_with()`-käyttöön (A1) ja `string\|false` return type hint -syntaksiin |

---

## Open Questions (RESOLVED)

1. **PHP-versio Altervistassa** (RESOLVED)
   - What we know: CONTEXT.md viittaa `string|false` return type hintiin (PHP 8.0+ union types) — kehitysympäristö tukee tätä
   - What's unclear: Tukeeko Altervista PHP 8.0+ virallisesti?
   - Resolution: Planner käyttää PHP 7.4 -yhteensopivaa fallbackia: `/** @return string|false */` docblock `string|false` union typen sijaan, ja `strncmp()` `str_starts_with()`:n sijaan. Altervistan PHP-version varmistus tehdään Phase 9:ssä (THEME-12). Assumption A1/A2 dokumentoi tämän riskin.

2. **DIRECTORY_SEPARATOR Windows-kehitysympäristössä vs. Altervista (Linux)** (RESOLVED)
   - What we know: Kehitysympäristö on Docker/Linux (`localhost:8080`) — `DIRECTORY_SEPARATOR` on `/` molemmissa
   - What's unclear: Ei tunnettuja ongelmia
   - Resolution: Docker-kehitysympäristö on Linux (`/`) ja Altervista on Linux (`/`) — separaattori on identtinen. `DIRECTORY_SEPARATOR`-käyttö koodissa on riittävä; ei erillisiä toimia tarvita.

---

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP (CLI/FPM) | theme.php, resolveThemePath() | ✓ | Docker-kontissa (localhost:8080) | — |
| MySQL | migrate_theme.sql, active_theme-luku | ✓ | Docker-kontissa | — |
| phpMyAdmin / Docker MySQL | SQL-migraation ajaminen | ✓ | localhost:8080 Docker-ympäristö | Suora `mysql`-komentorivikäyttö |

**Missing dependencies with no fallback:** None

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHP sisäinen + käsin testaus (ei erillistä test frameworkia projektissa) |
| Config file | none |
| Quick run command | Avaa `http://localhost:8080/pages/index.php` selaimessa |
| Full suite command | Käy läpi kaikki 5 success criteria manuaalisesti (ks. alla) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| THEME-01 | `resolveThemePath('pages/index.php')` palauttaa absoluuttisen polun default-teemaan (fallback) | manual | Lisää `var_dump(resolveThemePath('pages/index.php'))` index.php:hen, lataa sivu | ❌ Wave 0 |
| THEME-01 | `resolveThemePath('../../etc/passwd')` palauttaa `false` | manual | Lisää `var_dump(resolveThemePath('../../etc/passwd'))` — täytyy olla `false` | ❌ Wave 0 |
| THEME-02 | `settings`-taulussa on `active_theme`-rivi arvolla `'default'` | manual | phpMyAdmin: `SELECT * FROM settings WHERE setting_key='active_theme'` | ❌ Wave 0 |
| THEME-03 | `THEME_PATH` ja `THEME_URL` ovat määritelty julkisella sivulla | manual | Lisää `var_dump(THEME_PATH, THEME_URL)` index.php:hen | ❌ Wave 0 |
| THEME-03 | Admin-sivu EI määrittele `THEME_PATH`:a | manual | Lisää `var_dump(defined('THEME_PATH'))` admin/index.php:hen — täytyy olla `false` | ❌ Wave 0 |
| THEME-04 | `public/themes/default/theme.json` löytyy ja `json_decode` palauttaa oikean rakenteen | manual | `file_get_contents` + `json_decode` yksinkertaisella testiskriptillä | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** Käsin tarkistus selaimessa (localhost:8080)
- **Per wave merge:** Kaikki 5 success criteria tarkistettuna manuaalisesti
- **Phase gate:** Kaikki success criteria vihreänä ennen `/gsd-verify-work`

### Wave 0 Gaps

- [ ] `public/themes/default/` -hakemistorakenne ja `theme.json` — tarvitaan ennen kuin shim voidaan testata
- [ ] `database/migrate_theme.sql` ajettu — tarvitaan ennen kuin `active_theme`-rivi on tietokannassa

---

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no | — |
| V3 Session Management | no | — |
| V4 Access Control | yes | Admin-eristys: theme.php ei koskaan db.php:n kautta |
| V5 Input Validation | yes | `preg_match` allowlist teemanimelle + `realpath()` + prefix-check |
| V6 Cryptography | no | — |

### Known Threat Patterns for PHP Theme Path Resolution

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Path traversal (`../`, `%2F`, URL-enkoodatut variantit) | Tampering / Info Disclosure | `realpath()` normalisoi polun; `str_starts_with(THEME_PATH)` varmistaa pysymisen rajoissa |
| Teemanimihuijaus (esim. `../../etc`) | Tampering | `preg_match('/^[a-zA-Z0-9_-]+$/')` allowlist ennen mitään tiedosto-operaatiota |
| Symlink-hyökkäys (symlink teemassa osoittaa ulos) | Info Disclosure | `realpath()` resoloi symlinkit — prefix-check epäonnistuu jos lopullinen polku on teemakansion ulkopuolella |
| Null byte injection (`theme\0name`) | Tampering | `preg_match` allowlist torjuu null-tavun (ei kuulu `[a-zA-Z0-9_-]`-merkistöön) |

---

## Sources

### Primary (HIGH confidence)

- Olemassa oleva koodi: `public/src/includes/helpers.php` — `validate_file_name()` pattern [VERIFIED: codebase grep]
- Olemassa oleva koodi: `public/src/includes/config.php` — SITE_URL-vakio [VERIFIED: codebase read]
- Olemassa oleva koodi: `database/migrate_settings.sql` — migraatiopattern [VERIFIED: codebase read]
- Olemassa oleva koodi: `public/admin/settings.php` — settings-CRUD pattern [VERIFIED: codebase read]
- CONTEXT.md D-01 — D-10: Kaikki toteutuspäätökset [VERIFIED: CONTEXT.md read]

### Secondary (MEDIUM confidence)

- PHP `realpath()` + prefix-check -pattern path traversal -suojaukseen [ASSUMED: training knowledge, vakiintunut PHP-tietoturvakäytäntö]
- `str_starts_with()` PHP 8.0+ [ASSUMED: training knowledge]

### Tertiary (LOW confidence)

None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — ei ulkoisia paketteja, kaikki PHP sisäisiä funktioita
- Architecture: HIGH — kaikki arkkitehtuuripäätökset lukittu CONTEXT.md:ssä
- Pitfalls: HIGH — `realpath()`-käyttäytyminen ja trailing separator -ongelma ovat tunnettuja PHP-sudenkuoppia
- Security: HIGH — path traversal -suojauspatternit ovat vakiintuneita ja olemassa olevaa koodia löytyy vertailuksi

**Research date:** 2026-06-22
**Valid until:** 2026-09-22 (vakaa PHP-infrastruktuuri, 90 päivää)
