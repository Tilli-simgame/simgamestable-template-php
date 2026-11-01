<?php
/**
 * Teemashim — tarjoaa THEME_PATH, THEME_URL, THEMES_ROOT ja resolveThemePath()
 *
 * Ladataan VAIN julkisilla sivuilla db.php:n require_once-kutsun jälkeen.
 * ÄLÄ lisää tätä db.php:hen — admin-sivut eivät saa ladata shimmiä (D-09).
 *
 * Riippuvuudet: getDB() (db.php), SITE_URL (config.php)
 *
 * Tallikohtainen teema: jos controller on kutsunut setStableContext() ennen tämän
 * tiedoston lataamista ja tallille on valittu ulkoasuteema, se ohittaa sivuston
 * oletuksen (settings.active_theme).
 */

if (!defined('THEME_PATH')) {
    // 1. Lue aktiivinen teema tietokannasta (prepared statement, SEC-01-pattern)
    // Wrapped in try/catch: PDO::ERRMODE_EXCEPTION means prepare/execute can throw
    // PDOException. Because theme.php loads before any application error handler,
    // an uncaught exception here would expose a raw stack trace to the user.
    // Fall back to 'default' theme on any database failure (CR-01).
    $stableTheme = $GLOBALS['vt_stable']['layout_theme'] ?? null;
    if ($stableTheme) {
        $rawTheme = $stableTheme;
    } else try {
        $db = getDB();
        $stmt = $db->prepare(
            'SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1'
        );
        $stmt->execute([':k' => 'active_theme']);
        // Käytetään ?: (Elvis) koska fetchColumn() palauttaa false puuttuvalle riville,
        // ei null — ?? ei toimisi tässä (Pitfall 3)
        $rawTheme = $stmt->fetchColumn() ?: 'default';
    } catch (\Throwable $e) {
        error_log('theme.php: aktiivisen teeman haku epäonnistui: ' . $e->getMessage());
        $rawTheme = 'default';
    }

    // 2. Validoi teemanimi — salli vain alfanumeerinen + viivat/alaviivat (ei pisteitä)
    // Null-byte injection ('theme\0name') torjutaan samalla allowlistilla
    $themeName = preg_match('/^[a-zA-Z0-9_-]+$/', $rawTheme) ? $rawTheme : 'default';

    // 3. Resolvoi public/themes/-juuri
    // realpath() normalisoi polun ja resoloi symlinkit (Pitfall 2: jos hakemisto
    // ei vielä ole olemassa, palautuu false → käytetään __DIR__-pohjaista fallbackia)
    $resolvedThemesRoot = realpath(__DIR__ . '/../../themes');
    if ($resolvedThemesRoot === false) {
        // Fallback: hakemisto ei ole olemassa tai realpath() epäonnistuu
        // Rakennetaan polku manuaalisesti — resolveThemePath() palauttaa false
        // kunnes hakemisto syntyy tiedostojärjestelmään.
        // WR-01: Log the missing directory so developers get a diagnostic instead
        // of silent failure. The str_starts_with prefix check in resolveThemePath()
        // will always fail while this unresolved path is in use (an absolute
        // realpath() result cannot start with an unresolved `../../` path).
        error_log('theme.php: themes/-hakemistoa ei löydy: ' . __DIR__ . '/../../themes');
        $resolvedThemesRoot = __DIR__ . '/../../themes'; // resolveThemePath() returns false until dir exists
    }

    // 4. Määrittele vakiot
    // Trailing DIRECTORY_SEPARATOR on PAKOLLINEN prefix-checkin oikeellisuudelle (Pitfall 1, 5):
    // ilman sitä '/themes/defaultevil/' matchaisi '/themes/default'-prefixin
    define('THEMES_ROOT', $resolvedThemesRoot . DIRECTORY_SEPARATOR);
    define('THEME_PATH',  THEMES_ROOT . $themeName . DIRECTORY_SEPARATOR);
    define('THEME_URL',   SITE_URL . '/themes/' . $themeName . '/');
}

/**
 * Palauttaa aktiivisen teeman theme.json-manifestin taulukkona.
 * Palauttaa tyhjän taulukon jos tiedostoa ei löydy tai JSON on virheellinen.
 */
function getThemeManifest(): array {
    static $manifest = null;
    if ($manifest !== null) return $manifest;

    $jsonFile = THEME_PATH . 'theme.json';
    if (!is_file($jsonFile)) {
        return $manifest = [];
    }
    $data = json_decode(file_get_contents($jsonFile), true);
    return $manifest = (is_array($data) ? $data : []);
}

/**
 * Palauttaa absoluuttisen palvelinpolun teematiedostolle.
 *
 * Tarkistukset järjestyksessä (D-04):
 *  1. preg_match allowlist teemanimelle (toteutettu THEME_PATH-vakion rakennusvaiheessa)
 *  2. realpath() normalisoi polun (resoloi ../, symlinkit, URL-enkoodatut merkit)
 *  3. str_starts_with prefix-check varmistaa polun pysyvän teemakansion sisällä
 *
 * Fallback-logiikka (D-05):
 *  - Aktiivinen teema → default-teema → false
 *
 * @param string $subPath Suhteellinen polku teemakansion sisällä
 *                        (esim. 'pages/index.php', 'includes/header.php',
 *                        'assets/css/style.css')
 * @return string|false Absoluuttinen palvelinpolku tai false jos ei löydy
 *                      tai path-traversal-yritys havaitaan
 */
function resolveThemePath(string $subPath): string|false
{
    // Normalisoi hakemistoseparaattorit (Windows/Linux-kompatibiliteetti)
    // ja poista mahdollinen johtava separaattori
    $subPath = ltrim(
        str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subPath),
        DIRECTORY_SEPARATOR
    );

    // --- Aktiivinen teema ---
    $real = realpath(THEME_PATH . $subPath);
    // str_starts_with (PHP 8.0+) — EI strpos (RESEARCH anti-pattern rivi 273):
    // strpos('/themes/defaultevil/...', '/themes/default') palauttaisi väärän positiivisen
    if ($real !== false && str_starts_with($real, THEME_PATH)) {
        return $real;
    }

    // --- Default-teema fallback ---
    $defaultPath = THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR;
    $realDefault = realpath($defaultPath . $subPath);
    if ($realDefault !== false && str_starts_with($realDefault, $defaultPath)) {
        return $realDefault;
    }

    return false;
}
