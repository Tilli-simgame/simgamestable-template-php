<?php
/**
 * Apufunktiot — sisällytetään db.php:n kautta automaattisesti
 */

/**
 * Sanitoi käyttäjäsyöte HTML-tulostusta varten (XSS-suojaus)
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitoi käyttäjäsyöte — poistaa ylimääräiset välilyönnit
 */
function sanitize(string $value): string {
    return trim(strip_tags($value));
}

/**
 * Luo URL-turvallisen slugin hevosen nimestä
 * Esim: "Testiponi Tähti" → "testiponi-tahti"
 */
function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, ['ä' => 'a', 'ö' => 'o', 'å' => 'a', 'ü' => 'u', 'á' => 'a', 'é' => 'e', 'ó' => 'o']);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s_]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Palauttaa hevosen profiilisivun URL:n slugin perusteella
 */
function horseUrl(array $horse): string {
    $slug = $horse['slug'] ?? slugify($horse['name']);
    return SITE_URL . '/pages/hevonen/' . rawurlencode($slug);
}

/**
 * Ohjaa käyttäjä toiselle sivulle ja pysäyttää skriptin
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/**
 * Tarkistaa onko admin kirjautunut sisään
 */
function isLoggedIn(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Vaatii admin-kirjautumista — ohjaa login-sivulle jos ei kirjautunut
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/admin/login.php');
    }
}

/**
 * Palauttaa kirjautuneen käyttäjän roolin, tai null jos ei kirjautunut.
 */
function currentRole(): ?string {
    return $_SESSION['admin_role'] ?? null;
}

/**
 * Oikotie: onko kirjautunut käyttäjä admin.
 */
function isAdmin(): bool {
    return currentRole() === 'admin';
}

/**
 * Vaatii kirjautumisen JA että käyttäjän rooli on jokin sallituista.
 * Käytetään requireLogin()-kutsun tilalla jokaisen suojatun sivun alussa.
 *
 * @param string ...$allowedRoles esim. requireRole('admin', 'mod')
 */
function requireRole(string ...$allowedRoles): void {
    requireLogin();

    $db = getDB();
    $stmt = $db->prepare('SELECT role, is_active FROM admin_users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['admin_id'] ?? 0]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['is_active'] !== 1) {
        session_destroy();
        redirect(SITE_URL . '/admin/login.php');
    }

    $_SESSION['admin_role'] = $row['role'];

    if (!in_array($row['role'], $allowedRoles, true)) {
        redirect(SITE_URL . '/admin/ei-oikeutta.php');
    }
}

/**
 * Muotoilee päivämäärän suomalaiseen muotoon
 */
function formatDate(?string $date): string {
    if (!$date) return '—';
    return date('d.m.Y', strtotime($date));
}

/**
 * Laskee hevosen iän syntymäpäivästä (IRL, kokonaiset vuodet)
 */
function calculateAge(?string $birthDate): ?int {
    if (!$birthDate) return null;
    $birth = new DateTime($birthDate);
    $now = new DateTime();
    return (int) $now->diff($birth)->y;
}

/**
 * VHKR-ikä: 1 irl kuukausi = 1 vuosi
 */
function calculateAgeVHKR(?string $birthDate): ?int {
    if (!$birthDate) return null;
    $birth = new DateTime($birthDate);
    $now = new DateTime();
    if ($birth > $now) return 0;
    $diff = $birth->diff($now);
    return $diff->y * 12 + $diff->m;
}

/**
 * VARL-ikä: 48 irl päivää = 1 vuosi
 */
function calculateAgeVARL(?string $birthDate): ?int {
    if (!$birthDate) return null;
    $birth = new DateTime($birthDate);
    $now = new DateTime();
    if ($birth > $now) return 0;
    return (int) floor((int) $birth->diff($now)->days / 48);
}

/**
 * CAS-ikä: 12 irl viikkoa (84 pv) = 1 vuosi, ajanlasku alkoi 04.12.2006 (CAS 1)
 */
function calculateAgeCAS(?string $birthDate): ?int {
    if (!$birthDate) return null;
    $epoch = new DateTime('2006-12-04');
    $birth = new DateTime($birthDate);
    $now   = new DateTime();
    if ($birth > $now) return 0;
    $birthDays = $birth >= $epoch ? (int) $epoch->diff($birth)->days : 0;
    $nowDays   = $now   >= $epoch ? (int) $epoch->diff($now)->days   : 0;
    $birthCasYear = (int) floor($birthDays / 84) + 1;
    $nowCasYear   = (int) floor($nowDays   / 84) + 1;
    return max(0, $nowCasYear - $birthCasYear);
}

/**
 * KATT-ikä: 6 irl kuukautta = 1 vuosi
 */
function calculateAgeKATT(?string $birthDate): ?int {
    if (!$birthDate) return null;
    $birth = new DateTime($birthDate);
    $now = new DateTime();
    if ($birth > $now) return 0;
    $diff = $birth->diff($now);
    return (int) floor(($diff->y * 12 + $diff->m) / 6);
}

/**
 * Laskee iän valitun järjestelmän mukaan (apufunktio näytölle)
 */
function calculateAgeBySystem(?string $birthDate, ?string $system): ?int {
    return match ($system) {
        'VHKR' => calculateAgeVHKR($birthDate),
        'VARL' => calculateAgeVARL($birthDate),
        'CAS'  => calculateAgeCAS($birthDate),
        'KATT' => calculateAgeKATT($birthDate),
        'SHS'  => calculateAgeSHS($birthDate),
        default => calculateAge($birthDate),
    };
}

/**
 * SHS-ikä taulukkokaavan mukaan
 */
function calculateAgeSHS(?string $birthDate): ?int {
    if (!$birthDate) return null;
    $birth = new DateTime($birthDate);
    $now = new DateTime();
    if ($birth > $now) return 0;
    $diff   = $birth->diff($now);
    $months = $diff->y * 12 + $diff->m;
    if ($months < 3)  return 0;
    if ($months < 6)  return 1;
    if ($months < 10) return 2;
    if ($months < 16) return 3;
    return (int) round($months / 8 + 2);
}

/**
 * Hakee hevosen sukutaulun rekursiivisesti (maks. 3 sukupolvea)
 *
 * @param int $horseId Hevosen ID
 * @param int $depth Nykyinen syvyys (0 = aloitushevonen, 1 = vanhemmat jne.)
 * @param int $maxDepth Maksimisyvyys (3 = isoisovanhemmat)
 * @return array|null Hevosen tiedot sukutauluineen tai null
 */
function getHorsePedigree(int $horseId, int $depth = 0, int $maxDepth = 3): ?array {
    if ($depth > $maxDepth || $horseId <= 0) {
        return null;
    }

    $db = getDB();
    $stmt = $db->prepare(
        'SELECT h.id, h.name, h.call_name, h.birth_date, h.gender,
                h.sire_id, h.dam_id, h.evm, h.ancestor, h.profile_url,
                h.height_cm,
                b.name AS breed, COALESCE(b.abbreviation, b.name) AS breed_abbr,
                c.name AS color, COALESCE(c.abbreviation, c.name) AS color_abbr
         FROM horses h
         LEFT JOIN breeds b ON b.id = h.breed_id
         LEFT JOIN colors c ON c.id = h.color_id
         WHERE h.id = :id AND h.is_deleted = 0'
    );
    $stmt->execute([':id' => $horseId]);
    $horse = $stmt->fetch();

    if (!$horse) {
        return null;
    }

    // Rekursiivinen haku: vanhemmat, isovanhemmat, isoisovanhemmat
    if ($depth < $maxDepth) {
        $horse['sire'] = $horse['sire_id']
            ? getHorsePedigree((int)$horse['sire_id'], $depth + 1, $maxDepth)
            : null;
        $horse['dam'] = $horse['dam_id']
            ? getHorsePedigree((int)$horse['dam_id'], $depth + 1, $maxDepth)
            : null;
    } else {
        $horse['sire'] = null;
        $horse['dam'] = null;
    }

    return $horse;
}

/**
 * Luo CSRF-token istuntoon
 * Luodaan uusi token jos sitä ei ole olemassa
 *
 * @return string 64-merkkinen heksadesimaalinen token
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Tarkistaa CSRF-tokenin pyynnöstä
 * Käyttää timing-safe vertailua (hash_equals)
 *
 * @param string|null $token Käyttäjän toimittama token
 * @return bool true jos token on kelvollinen
 */
function validate_csrf_token(?string $token): bool {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token ?? '');
}

/**
 * Vahvistaa merkkijonon pituuden ja sisällön
 *
 * @param mixed $input Syöte
 * @param int $min Minimipituus
 * @param int $max Maksimipituus
 * @return array ['valid' => bool, 'value' => string, 'error' => string|null]
 */
function validate_string($input, int $min = 1, int $max = 255): array {
    $input = is_string($input) ? trim($input) : '';
    $len = strlen($input);
    
    if ($len < $min) {
        return [
            'valid' => false,
            'error' => "Teksti on liian lyhyt (vähintään $min merkkiä).",
            'value' => ''
        ];
    }
    if ($len > $max) {
        return [
            'valid' => false,
            'error' => "Teksti on liian pitkä (enintään $max merkkiä).",
            'value' => ''
        ];
    }
    
    return [
        'valid' => true,
        'value' => $input,
        'error' => null
    ];
}

/**
 * Vahvistaa sähköpostiosoitteen
 *
 * @param mixed $email Sähköpostiosoite
 * @return array ['valid' => bool, 'value' => string, 'error' => string|null]
 */
function validate_email($email): array {
    $email = is_string($email) ? trim(strtolower($email)) : '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'error' => 'Virheellinen sähköpostiosoite.',
            'value' => ''
        ];
    }
    
    return [
        'valid' => true,
        'value' => $email,
        'error' => null
    ];
}

/**
 * Vahvistaa kokonaisluvun ja sen rajat
 *
 * @param mixed $input Syöte
 * @param int|null $min Minimiarvo (optionaalinen)
 * @param int|null $max Maksimiarvo (optionaalinen)
 * @return array ['valid' => bool, 'value' => int, 'error' => string|null]
 */
function validate_integer($input, ?int $min = null, ?int $max = null): array {
    if (!is_numeric($input) || intval($input) != $input) {
        return [
            'valid' => false,
            'error' => 'Arvo ei ole kokonaisluku.',
            'value' => 0
        ];
    }
    
    $int = intval($input);
    
    if ($min !== null && $int < $min) {
        return [
            'valid' => false,
            'error' => "Arvo ei voi olla pienempi kuin $min.",
            'value' => 0
        ];
    }
    if ($max !== null && $int > $max) {
        return [
            'valid' => false,
            'error' => "Arvo ei voi olla suurempi kuin $max.",
            'value' => 0
        ];
    }
    
    return [
        'valid' => true,
        'value' => $int,
        'error' => null
    ];
}

/**
 * Vahvistaa tiedostonimen
 * Sallii vain aakkosnumerot, väliviivoja, alleviivoja ja pisteitä
 *
 * @param mixed $filename Tiedostonimi
 * @param int $max_length Maksimipituus
 * @return array ['valid' => bool, 'value' => string, 'error' => string|null]
 */
function validate_file_name($filename, int $max_length = 255): array {
    $filename = is_string($filename) ? basename($filename) : '';
    
    // Sallitaan vain turvallisia merkkejä
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
        return [
            'valid' => false,
            'error' => 'Tiedostonimi sisältää kiellettyjä merkkejä.',
            'value' => ''
        ];
    }
    
    if (strlen($filename) > $max_length) {
        return [
            'valid' => false,
            'error' => "Tiedostonimi on liian pitkä (enintään $max_length merkkiä).",
            'value' => ''
        ];
    }
    
    // Torjutaan path traversal -yritykset
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
        return [
            'valid' => false,
            'error' => 'Virheellinen tiedostonimi.',
            'value' => ''
        ];
    }
    
    return [
        'valid' => true,
        'value' => $filename,
        'error' => null
    ];
}

/**
 * Luo turvallisen satunnaisen tiedostonimen
 * Käyttää random_bytes() entropian lähteenä
 *
 * @param string $extension Tiedostopääte (esim. "jpg")
 * @return string Turvallinen tiedostonimi (esim. "3a4b5c6d7e8f.jpg")
 */
function generate_safe_filename(string $extension): string {
    $name = bin2hex(random_bytes(16));
    return $name . '.' . strtolower($extension);
}

/**
 * Luo kryptografisesti vahvan satunnaissalasanan
 * Käyttää random_int()-pohjaista valintaa (CSPRNG, ei rand()/mt_rand()/uniqid())
 *
 * @param int $length Salasanan pituus (oletus 16, täyttää D-10:n 8 merkin minimin)
 * @return string Satunnaissalasana (A-Z, a-z, 0-9)
 */
function generate_password(int $length = 16): string {
    $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($charset) - 1;
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $charset[random_int(0, $max)];
    }
    return $password;
}

/**
 * Validoi kuvan latausyrityksen
 * Tarkistaa tiedostotyypin, koon ja tiedostopääteen
 *
 * @param array $file_array $_FILES['fieldname'] array
 * @param int $max_size_bytes Maksimikoko tavuissa (oletus: 5 MB)
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validate_image_upload(array $file_array, int $max_size_bytes = 5242880): array {
    // Sallitut MIME-tyypit
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    // Sallitut tiedostopäätteet
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    // Kielletyt PHP-päätteet
    $blocked_exts = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'pht'];
    
    // Tarkista upload-virheet
    if ($file_array['error'] !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'error' => 'Latausvirhe: ' . (
                $file_array['error'] === UPLOAD_ERR_INI_SIZE ? 'Tiedosto on liian suuri.' :
                ($file_array['error'] === UPLOAD_ERR_FORM_SIZE ? 'Tiedosto on liian suuri.' :
                ($file_array['error'] === UPLOAD_ERR_PARTIAL ? 'Lataus keskeytyi.' :
                'Tuntematon virhe.'))
            )
        ];
    }
    
    // Tarkista tiedoston koko
    if ($file_array['size'] > $max_size_bytes) {
        return [
            'valid' => false,
            'error' => 'Tiedosto on liian suuri (max 5 Mt).'
        ];
    }
    
    // Tarkista MIME-tyyppi finfo_file():llä (luotettavampi kuin $_FILES['type'])
    if (function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_array['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed_mimes)) {
            return [
                'valid' => false,
                'error' => 'Tiedostotyyppi ei ole kelvollinen kuva.'
            ];
        }
    }
    
    // Tarkista tiedostopääte
    $ext = strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) {
        return [
            'valid' => false,
            'error' => 'Tiedostopääte ei ole kelvollinen (sallitut: ' . implode(', ', $allowed_exts) . ').'
        ];
    }
    
    // Torju PHP-tiedostot
    if (in_array($ext, $blocked_exts)) {
        return [
            'valid' => false,
            'error' => 'PHP-tiedostoja ei sallita.'
        ];
    }
    
    return [
        'valid' => true,
        'error' => null
    ];
}
