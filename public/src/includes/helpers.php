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
 * Päätalli (settings.main_stable_id): tarjoillaan sivuston juuresta (/, /hevoset, /{sivu}).
 * Palauttaa stables-rivin tai null, jos päätallia ei ole valittu.
 */
function mainStable(): ?array {
    static $main = false;
    if ($main !== false) return $main;
    try {
        $stmt = getDB()->prepare(
            'SELECT s.* FROM settings st JOIN stables s ON s.id = st.setting_value
             WHERE st.setting_key = :k LIMIT 1'
        );
        $stmt->execute([':k' => 'main_stable_id']);
        return $main = ($stmt->fetch() ?: null);
    } catch (\Throwable $e) {
        error_log('mainStable(): päätallin haku epäonnistui: ' . $e->getMessage());
        return $main = null;
    }
}

/**
 * Onko talli sivuston päätalli
 */
function isMainStable(array $stable): bool {
    $main = mainStable();
    return $main !== null && $main['slug'] === ($stable['slug'] ?? null);
}

/**
 * Tallin julkinen sivu: päätalli → /, muut tallit → /slug
 */
function stableUrl(array $stable): string {
    return SITE_URL . '/' . (isMainStable($stable) ? '' : rawurlencode($stable['slug']));
}

/**
 * Tallin alasivu: päätalli → /sivu, muut tallit → /slug/sivu
 * (sivut määritellään teeman theme.json:n stable_pages-avaimessa)
 */
function stablePageUrl(array $stable, string $page): string {
    return rtrim(stableUrl($stable), '/') . '/' . rawurlencode($page);
}

/**
 * Asettaa pyynnön tallikontekstin julkisille sivuille (tallin sivu, tallin hevosen sivu).
 * Kutsuttava ENNEN theme.php:n lataamista, jotta tallin ulkoasuteema tulee käyttöön.
 * Header käyttää tallin väriteemaa, jos sellainen on valittu.
 */
function setStableContext(?array $stable): void {
    if (!$stable) return;
    $layouts = availableLayoutThemes();
    $colors  = colorThemeDefs();
    $GLOBALS['vt_stable'] = [
        'id'           => (int)$stable['id'],
        'name'         => $stable['name'],
        'slug'         => $stable['slug'],
        'layout_theme' => isset($layouts[$stable['layout_theme'] ?? '']) ? $stable['layout_theme'] : null,
        'color_theme'  => isset($colors[$stable['color_theme'] ?? ''])   ? $stable['color_theme']  : null,
    ];
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
 * Vaatii että kirjautunut author-käyttäjä omistaa käsiteltävän resurssin.
 * Admin ja mod eivät ole omistajuusrajattuja ja läpäisevät aina.
 * Identiteetti johdetaan AINA sessiosta ($_SESSION['admin_id']), ei koskaan
 * käyttäjän toimittamasta syötteestä (Spoofing-suoja).
 *
 * @param int $resourceAuthorId Resurssin (esim. postauksen) author_id
 */
function requireOwnResourceOrAdmin(int $resourceAuthorId): void {
    if (currentRole() === 'author' && $resourceAuthorId !== (int)($_SESSION['admin_id'] ?? 0)) {
        redirect(SITE_URL . '/admin/ei-oikeutta.php');
    }
}

/**
 * Luo odottavan pending_deletions-rivin annetulle sisältötyypille/idlle.
 * Estää duplikaatit (DEL-05): tarkistaa ensin ettei samalle entiteetille
 * ole jo pending-tilassa olevaa pyyntöä (check-then-insert, PHP-tason guard).
 *
 * @param string $entityType Whitelistattu arvo: horse|foal|competition|showrecord|qualityreview|post
 * @param int $entityId
 * @param int $requestedBy admin_users.id (aina $_SESSION['admin_id'], ei käyttäjän syötteestä)
 */
function insertPendingDeletion(string $entityType, int $entityId, int $requestedBy): void {
    $db = getDB();
    $chk = $db->prepare(
        'SELECT id FROM pending_deletions WHERE entity_type = :t AND entity_id = :id AND status = :status'
    );
    $chk->execute([':t' => $entityType, ':id' => $entityId, ':status' => 'pending']);
    if ($chk->fetch()) {
        return; // jo pending, ei duplikaattia
    }
    $db->prepare(
        'INSERT INTO pending_deletions (entity_type, entity_id, requested_by) VALUES (:t, :id, :by)'
    )->execute([':t' => $entityType, ':id' => $entityId, ':by' => $requestedBy]);
}

/**
 * Mappaa entity_type-arvon tietokannan taulunimeen whitelist-match()-lausekkeella.
 * Ainoa sallittu tapa johtaa taulunimi entity_type-arvosta — EI dynaamista
 * taulunimen rakennusta käyttäjän syötteestä (SQL-injektioesto).
 *
 * @param string $entityType Whitelistattu arvo: horse|foal|competition|showrecord|qualityreview|post
 * @return string Taulunimi
 * @throws InvalidArgumentException jos $entityType ei ole whitelistin arvo
 */
function entityTypeToTable(string $entityType): string {
    return match ($entityType) {
        'horse'       => 'horses',
        'foal'        => 'foals',
        'competition' => 'competitions',
        'showrecord'  => 'showrecords',
        'qualityreview' => 'quality_reviews',
        'post'        => 'posts',
        default       => throw new InvalidArgumentException('Invalid entity_type'),
    };
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
 * Hakee hevosen laatuarvostelut aikajärjestyksessä (päivämäärättömät, esim. varsapalkinnot, viimeisenä)
 */
function getQualityReviews(int $horseId): array {
    $stmt = getDB()->prepare(
        'SELECT q.review_date, q.country, q.organizer, q.organizer_url, q.placement, q.points, q.review, q.notes,
                jc.nickname AS judge_nickname, jc.stable_name AS judge_stable_name, jc.email AS judge_email
         FROM quality_reviews q LEFT JOIN contacts jc ON jc.id = q.judge_contact_id
         WHERE q.horse_id = :id AND q.is_deleted = 0
         ORDER BY q.review_date IS NULL, q.review_date, q.id'
    );
    $stmt->execute([':id' => $horseId]);
    return $stmt->fetchAll();
}

/**
 * Hakee hevosen koulutustasot lajeittain (koulu, este, kenttä, valjakko, askellaji ensin, sitten muut aakkosjärjestyksessä)
 */
function getHorseLevels(int $horseId): array {
    $stmt = getDB()->prepare(
        'SELECT l.discipline_id, d.name, d.abbreviation, l.level
         FROM horse_levels l JOIN disciplines d ON d.id = l.discipline_id
         WHERE l.horse_id = :id
         ORDER BY FIELD(l.discipline_id, 7, 6, 3, 1, 2) DESC, d.name'
    );
    $stmt->execute([':id' => $horseId]);
    return $stmt->fetchAll();
}

/**
 * Laskee meriitti- ja varsapalkintorivit laatuarvosteluista legacy-muodossa:
 *   merits      "VIR MVA Ch KTK-II, VSN HCh PSB-II, ERJ-I, YLA1"
 *   foal_awards "EV-II, KV-I, KEV-III"
 * Jaoksesta otetaan paras tulos (I > II > III, YLA1 > YLA2). Jälkeläisarvostelut ja NWA eivät ole meriittejä.
 *
 * @param array $reviews getQualityReviews()-rivit
 * @return array{merits: string, foal_awards: string}
 */
function computeMerits(array $reviews): array {
    $foalOrder  = ['EV', 'KV', 'KEV', 'VV', 'VAR', 'SV', 'AV', 'VRKK', 'SHEV'];
    $jaosOrder  = ['KTK', 'PSB', 'ERJ', 'KRJ', 'KERJ', 'VVJ', 'SLA', 'SHLA', 'HANN', 'VWY', 'YLA'];
    $titleOrder = ['VIR', 'Champion', 'VSN'];
    $rank = function (string $p): int {
        if (preg_match('/-(I{1,3}|IV)\*?$/', $p, $m)) return ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4][$m[1]];
        return preg_match('/(\d+)$/', $p, $m) ? (int)$m[1] : 99;
    };
    // "VSN Halter Champion" → "VSN HCh", "VSN World Ch sis. VSN Ch & VSN NCh" → "VSN WCh"
    $shortTitle = function (string $t): string {
        $t = preg_replace('/\s+sis\..*$/', '', $t);
        if ($t === 'Champion') return 'Ch';
        if (preg_match('/^VSN (Halter|Ridden|Overall|World|National) Ch(ampion)?$/', $t, $m)) {
            return 'VSN ' . ['Halter' => 'H', 'Ridden' => 'R', 'Overall' => 'O', 'World' => 'W', 'National' => 'N'][$m[1]] . 'Ch';
        }
        return $t === 'VSN Champion' ? 'VSN Ch' : $t;
    };
    $sortBy = fn(array $order) => fn($a, $b) => [array_search($a, $order) === false ? 99 : array_search($a, $order), $a]
                                               <=> [array_search($b, $order) === false ? 99 : array_search($b, $order), $b];

    $titles = $vsnBeforePsb = $best = $foal = [];
    foreach ($reviews as $r) {
        $org = (string)$r['organizer'];
        $pl  = trim((string)$r['placement']);
        if ($pl === '' || $org === 'NWA' || str_contains($pl, 'Jälk')) continue;
        if (in_array($org, $titleOrder, true)) {
            $t = $shortTitle($pl);
            // Näyttelyluokan arvonimi (Halter/Ridden/Overall) kirjoitetaan PSB-tuloksen eteen
            if (preg_match('/^VSN [HRO]Ch$/', $t)) $vsnBeforePsb[$t] = true;
            else $titles[$org][$t] = true;
        } elseif (in_array($org, $foalOrder, true)) {
            if (!isset($foal[$org]) || $rank($pl) < $rank($foal[$org])) $foal[$org] = $pl;
        } elseif (!isset($best[$org]) || $rank($pl) < $rank($best[$org])) {
            $best[$org] = $pl;
        }
    }

    uksort($titles, $sortBy($titleOrder));
    uksort($best, $sortBy($jaosOrder));
    uksort($foal, $sortBy($foalOrder));
    if ($vsnBeforePsb && isset($best['PSB'])) {
        $best['PSB'] = implode(' ', array_keys($vsnBeforePsb)) . ' ' . $best['PSB'];
    } elseif ($vsnBeforePsb) {
        $titles['VSN'] = ($titles['VSN'] ?? []) + $vsnBeforePsb;
    }

    $titleText = implode(' ', array_merge(...array_map('array_keys', array_values($titles))));
    return [
        'merits'      => trim($titleText . ' ' . implode(', ', $best)),
        'foal_awards' => implode(', ', $foal),
    ];
}

/**
 * Hevosen meriitit näytettäväksi: käsin syötetty arvo, muuten laskettu laatuarvosteluista
 *
 * @return array{merits: string, foal_awards: string}
 */
function horseMerits(array $horse, array $reviews): array {
    $computed = computeMerits($reviews);
    return [
        'merits'      => ($horse['merits'] ?? '') ?: $computed['merits'],
        'foal_awards' => ($horse['foal_awards'] ?? '') ?: $computed['foal_awards'],
    ];
}

/**
 * Laatuarvostelun päivämäärä muodossa "05/2026" (tuoduista tiedetään vain kuukausi)
 */
function formatReviewMonth(?string $date): string {
    return $date ? date('m/Y', strtotime($date)) : '';
}

/**
 * Laatuarvostelun pisteet muodossa "104,5p." (tyhjä jos pisteitä ei ole)
 */
function formatReviewPoints(?string $points): string {
    if ($points === null || $points === '') return '';
    return str_replace('.', ',', rtrim(rtrim($points, '0'), '.')) . 'p.';
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

/**
 * Väriteemat (data-theme) — avain => nimi + esikatseluvärit.
 * Käytetään asetuksissa (sivuston teema) ja talleilla (tallikohtainen teema).
 */
function colorThemeDefs(): array {
    return [
        'savi'      => ['nimi' => 'Savi',       'p' => '#3d2b1f', 'a' => '#a0633a', 'g' => '#c9a84c', 'bg' => '#f9f7f4'],
        'metsa'     => ['nimi' => 'Metsä',      'p' => '#1a3d2b', 'a' => '#5a8a3a', 'g' => '#8db44a', 'bg' => '#f4f7f4'],
        'yo'        => ['nimi' => 'Yö',         'p' => '#1a1f3d', 'a' => '#4a6fa0', 'g' => '#7090c8', 'bg' => '#f4f5f9'],
        'ruusu'     => ['nimi' => 'Ruusu',      'p' => '#4a1f30', 'a' => '#c06080', 'g' => '#d8a0b8', 'bg' => '#fdf5f7'],
        'kivikko'   => ['nimi' => 'Kivikko',    'p' => '#2c3030', 'a' => '#5a7070', 'g' => '#b0a860', 'bg' => '#f5f5f5'],
        'arktinen'  => ['nimi' => 'Arktinen',   'p' => '#1a3a4d', 'a' => '#3d9abf', 'g' => '#70c0e0', 'bg' => '#f4f8fc'],
        'aurinko'   => ['nimi' => 'Aurinko',    'p' => '#4d2c1a', 'a' => '#c06030', 'g' => '#e0a040', 'bg' => '#fdf8f4'],
        'laventeli' => ['nimi' => 'Laventeli',  'p' => '#3a2060', 'a' => '#8060c0', 'g' => '#c0a8e0', 'bg' => '#f8f5fd'],
        'talvi'     => ['nimi' => 'Talvi',      'p' => '#2c3840', 'a' => '#4a7888', 'g' => '#9ab8c8', 'bg' => '#f5f7f8'],
        'kulta'     => ['nimi' => 'Kultainen',  'p' => '#3d3010', 'a' => '#b88c20', 'g' => '#e8c840', 'bg' => '#fdf9f0'],
    ];
}

/**
 * Saatavilla olevat ulkoasuteemat: public/themes/<kansio>/theme.json → [kansio => nimi].
 */
function availableLayoutThemes(): array {
    $themes = [];
    foreach (glob(__DIR__ . '/../../themes/*/theme.json') ?: [] as $jsonPath) {
        $key  = basename(dirname($jsonPath));
        $meta = json_decode(file_get_contents($jsonPath), true);
        $themes[$key] = $meta['name'] ?? $key;
    }
    return $themes ?: ['default' => 'Default'];
}

/**
 * Kevyt markdown → HTML (luonne, sukuselvitys).
 * Tuetut: **lihavointi**, *kursiivi* / _kursiivi_, [linkki](https://...),
 * tyhjä rivi = uusi kappale, rivinvaihto = <br>. Muu HTML escapetaan.
 */
function renderMarkdown(string $text): string {
    $out = '';
    foreach (preg_split('/\R{2,}/', trim($text)) as $para) {
        $html = e($para);

        // Linkit paikkamerkeiksi, ettei URL:n * tai _ muutu muotoiluksi
        $links = [];
        $html = preg_replace_callback('/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/', function ($m) use (&$links) {
            $links[] = '<a href="' . $m[2] . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
            return "\x00" . (count($links) - 1) . "\x00";
        }, $html);

        $html = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<![*\w])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![*\w])/su', '<em>$1</em>', $html);
        $html = preg_replace('/(?<![_\w])_(?=[^\s_])(.+?)(?<=[^\s_])_(?![_\w])/su', '<em>$1</em>', $html);

        $html = preg_replace_callback('/\x00(\d+)\x00/', fn($m) => $links[(int)$m[1]], $html);
        $out .= '<p>' . nl2br($html) . '</p>';
    }
    return $out;
}
