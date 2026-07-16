<?php
/**
 * VRL import save endpoint
 * POST /admin/api/vrl_import_save.php
 * Body: JSON { csrf_token, horse, relatives[] }
 * Returns: JSON { ok, inserted, skipped, mainHorseId, errors[] }
 */
require_once __DIR__ . '/../../src/includes/db.php';
requireRole('admin', 'mod');

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Parse JSON body
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Virheellinen JSON-syöte']);
    exit;
}

// CSRF validation
if (!validate_csrf_token($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Virheellinen CSRF-token']);
    exit;
}

// Validate main horse data
$horse = $data['horse'] ?? null;
if (!is_array($horse)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Hevosen tiedot puuttuvat']);
    exit;
}

$relatives = $data['relatives'] ?? [];
if (!is_array($relatives)) {
    $relatives = [];
}

// ── Lookup tables ────────────────────────────────────────────────────
$db = getDB();

$breedsResult = $db->query('SELECT id, name FROM breeds ORDER BY name')->fetchAll();
$breedsByName = [];
foreach ($breedsResult as $b) {
    $breedsByName[mb_strtolower($b['name'], 'UTF-8')] = (int)$b['id'];
}

$colorsResult = $db->query('SELECT id, name FROM colors ORDER BY name')->fetchAll();
$colorsByName = [];
foreach ($colorsResult as $c) {
    $colorsByName[mb_strtolower($c['name'], 'UTF-8')] = (int)$c['id'];
}

// Load stable owner identifiers from settings
$settingsRows = $db->query(
    "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('owner_nickname','owner_vrl_id')"
)->fetchAll();
$_settings = [];
foreach ($settingsRows as $row) {
    $_settings[$row['setting_key']] = trim($row['setting_value'] ?? '');
}
$ownerNickname = mb_strtolower($_settings['owner_nickname'] ?? '', 'UTF-8');
$ownerVrlId    = mb_strtolower($_settings['owner_vrl_id']   ?? '', 'UTF-8');

// ── Helper functions ─────────────────────────────────────────────────

/**
 * Match breed name to DB id (case-insensitive, partial match fallback)
 */
function matchBreedId(string $name, array $byName): ?int {
    if ($name === '') return null;
    $key = mb_strtolower(trim($name), 'UTF-8');
    return $byName[$key] ?? null;
}

/**
 * Match color name to DB id (case-insensitive)
 */
function matchColorId(string $name, array $byName): ?int {
    if ($name === '') return null;
    $key = mb_strtolower(trim($name), 'UTF-8');
    return $byName[$key] ?? null;
}

/**
 * Convert Finnish date "DD.MM.YYYY" → MySQL "YYYY-MM-DD"
 */
function toMysqlDate(string $d): ?string {
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', trim($d), $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return null;
}

/**
 * Extract height integer from "158 cm"
 */
function parseHeightCm(string $h): ?int {
    if (preg_match('/(\d{2,3})\s*cm/i', $h, $m)) {
        return (int)$m[1];
    }
    return null;
}

/**
 * Map VRL gender string → our ENUM values
 */
function mapGender(string $g): string {
    $g = mb_strtolower(trim($g), 'UTF-8');
    if (str_contains($g, 'tamma') || str_contains($g, 'mare'))    return 'tamma';
    if (str_contains($g, 'ori')   || str_contains($g, 'stallion')) return 'ori';
    if (str_contains($g, 'ruuna') || str_contains($g, 'gelding'))  return 'ruuna';
    return 'tamma'; // default
}

/**
 * Strip VH code suffix from horse title, e.g. "Horsename (VH14-014-0143)" → "Horsename"
 */
function cleanHorseName(string $title): string {
    return trim(preg_replace('/\s*\(VH\d{2}-\d{3}-\d{4}\)\s*$/i', '', $title));
}

/**
 * Validate VH code format
 */
function isValidVhCode(string $vh): bool {
    return (bool)preg_match('/^VH\d{2}-\d{3}-\d{4}$/i', $vh);
}

/**
 * Check if a horse with this VH already exists; return its id or null
 */
function findExistingByVh(PDO $db, string $vh): ?int {
    $stmt = $db->prepare('SELECT id FROM horses WHERE vh_id = :vh AND is_deleted = 0 LIMIT 1');
    $stmt->execute([':vh' => $vh]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

/**
 * Generate a unique slug
 */
function makeUniqueSlug(PDO $db, string $name): string {
    $base = slugify($name);
    $slug = $base;
    $n    = 2;
    $stmt = $db->prepare('SELECT id FROM horses WHERE slug = :slug LIMIT 1');
    while (true) {
        $stmt->execute([':slug' => $slug]);
        if (!$stmt->fetchColumn()) break;
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

/**
 * Returns true if the horse's omistajat field matches the stable owner
 * (case-insensitive substring match on nickname OR VRL ID).
 * If neither is configured, treats all horses as owned by stable.
 */
function isOwnedByStable(array $d, string $ownerNickname, string $ownerVrlId): bool {
    if ($ownerNickname === '' && $ownerVrlId === '') {
        return true; // not configured — import everything fully
    }
    $ownerField = mb_strtolower(trim($d['omistajat'] ?? ''), 'UTF-8');
    if ($ownerField === '') return false;
    if ($ownerNickname !== '' && str_contains($ownerField, $ownerNickname)) return true;
    if ($ownerVrlId    !== '' && str_contains($ownerField, $ownerVrlId))    return true;
    return false;
}

/**
 * Insert a horse. ancestor=true → minimal fields only (name, vh_id, breed_id,
 * height_cm, color_id, profile_url). ancestor=false → full insert.
 * sire_id/dam_id are stored in both cases to preserve pedigree links.
 */
function insertHorse(
    PDO    $db,
    string $name,
    array  $d,
    array  $breedsByName,
    array  $colorsByName,
    bool   $ancestor,
    ?int   $sireId = null,
    ?int   $damId  = null
): int {
    $slug = makeUniqueSlug($db, $name);
    $vh   = !empty($d['vh']) ? strtoupper(trim($d['vh'])) : null;

    $profileUrl = null;
    if (!empty($d['sivut']) && filter_var($d['sivut'], FILTER_VALIDATE_URL)) {
        $profileUrl = $d['sivut'];
    } elseif (!empty($d['horseUrl']) && filter_var($d['horseUrl'], FILTER_VALIDATE_URL)) {
        $profileUrl = $d['horseUrl'];
    }

    if ($ancestor) {
        // Minimal insert — horse belongs to another stable
        $stmt = $db->prepare(
            'INSERT INTO horses
               (name, vh_id, breed_id, height_cm, color_id, profile_url,
                gender, ancestor, sire_id, dam_id, slug)
             VALUES
               (:name, :vh_id, :breed_id, :height_cm, :color_id, :profile_url,
                :gender, 1, :sire_id, :dam_id, :slug)'
        );
        $stmt->execute([
            ':name'        => mb_substr($name, 0, 150),
            ':vh_id'       => $vh,
            ':breed_id'    => !empty($d['rotu'])        ? matchBreedId($d['rotu'], $breedsByName)  : null,
            ':height_cm'   => !empty($d['sakakorkeus']) ? parseHeightCm($d['sakakorkeus'])         : null,
            ':color_id'    => !empty($d['vari'])        ? matchColorId($d['vari'], $colorsByName)  : null,
            ':profile_url' => $profileUrl,
            ':gender'      => !empty($d['sukupuoli'])   ? mapGender($d['sukupuoli'])               : 'tamma',
            ':sire_id'     => $sireId,
            ':dam_id'      => $damId,
            ':slug'        => $slug,
        ]);
    } else {
        // Full insert — horse belongs to this stable
        $ownerName   = !empty($d['omistajat'])      ? mb_substr(trim($d['omistajat']), 0, 150)      : null;
        $breederName = !empty($d['kasvattajanimi']) ? mb_substr(trim($d['kasvattajanimi']), 0, 150) :
                      (!empty($d['kasvattaja'])     ? mb_substr(trim($d['kasvattaja']), 0, 150)     : null);

        $stmt = $db->prepare(
            'INSERT INTO horses
               (name, vh_id, breed_id, birth_date, gender, color_id, height_cm,
                owner_name, breeder_name, profile_url,
                ancestor, sire_id, dam_id, slug)
             VALUES
               (:name, :vh_id, :breed_id, :birth_date, :gender, :color_id, :height_cm,
                :owner_name, :breeder_name, :profile_url,
                0, :sire_id, :dam_id, :slug)'
        );
        $stmt->execute([
            ':name'        => mb_substr($name, 0, 150),
            ':vh_id'       => $vh,
            ':breed_id'    => !empty($d['rotu'])        ? matchBreedId($d['rotu'], $breedsByName)  : null,
            ':birth_date'  => !empty($d['syntynyt'])    ? toMysqlDate($d['syntynyt'])              : null,
            ':gender'      => !empty($d['sukupuoli'])   ? mapGender($d['sukupuoli'])               : 'tamma',
            ':color_id'    => !empty($d['vari'])        ? matchColorId($d['vari'], $colorsByName)  : null,
            ':height_cm'   => !empty($d['sakakorkeus']) ? parseHeightCm($d['sakakorkeus'])         : null,
            ':owner_name'  => $ownerName,
            ':breeder_name'=> $breederName,
            ':profile_url' => $profileUrl,
            ':sire_id'     => $sireId,
            ':dam_id'      => $damId,
            ':slug'        => $slug,
        ]);
    }

    return (int)$db->lastInsertId();
}

// ── Main import logic ────────────────────────────────────────────────

$inserted   = 0;
$skipped    = 0;
$errors     = [];
$markToId   = []; // pedigree mark → DB id

try {
    $db->beginTransaction();

    // Sort relatives deepest first (longest mark = deepest generation)
    // so parents exist in markToId before their children need them
    usort($relatives, function (array $a, array $b) {
        return strlen($b['mark']) - strlen($a['mark']);
    });

    // Insert each relative
    foreach ($relatives as $rel) {
        $mark = $rel['mark'] ?? '';
        $vh   = !empty($rel['vh']) ? strtoupper(trim($rel['vh'])) : '';
        $name = trim($rel['name'] ?? '');

        if ($name === '') {
            continue; // skip nameless entries
        }

        // Skip unknown placeholders ("Tuntematon ori" / "Tuntematon tamma" etc.)
        if (preg_match('/^Tuntematon\b/ui', $name) && $vh === '') {
            continue;
        }

        // Try to find existing horse by VH
        if ($vh !== '' && isValidVhCode($vh)) {
            $existingId = findExistingByVh($db, $vh);
            if ($existingId !== null) {
                $markToId[$mark] = $existingId;
                $skipped++;
                continue;
            }
        }

        // Determine sire/dam from already-inserted deeper relatives
        $path   = rtrim($mark, '.');
        $sireId = $markToId[$path . 'i.'] ?? null;
        $damId  = $markToId[$path . 'e.'] ?? null;

        $ancestor = !isOwnedByStable($rel, $ownerNickname, $ownerVrlId);

        try {
            $id = insertHorse($db, $name, $rel, $breedsByName, $colorsByName, $ancestor, $sireId, $damId);
            $markToId[$mark] = $id;
            $inserted++;
        } catch (PDOException $e) {
            $errors[] = "Sukulainen {$name} ({$mark}): " . $e->getMessage();
        }
    }

    // ── Insert main horse ────────────────────────────────────────────
    $mainVh   = !empty($horse['vh']) ? strtoupper(trim($horse['vh'])) : '';
    $mainName = cleanHorseName($horse['title'] ?? '');
    if ($mainName === '') {
        $mainName = trim($horse['name'] ?? $mainVh);
    }

    if ($mainName === '') {
        $db->rollBack();
        echo json_encode(['ok' => false, 'error' => 'Hevosen nimi puuttuu']);
        exit;
    }

    $mainHorseId = null;

    // Check if main horse already exists
    if ($mainVh !== '' && isValidVhCode($mainVh)) {
        $existingMainId = findExistingByVh($db, $mainVh);
        if ($existingMainId !== null) {
            $mainHorseId = $existingMainId;
            $skipped++;
        }
    }

    if ($mainHorseId === null) {
        $sireId   = $markToId['i.'] ?? null;
        $damId    = $markToId['e.'] ?? null;
        $ancestor = !isOwnedByStable($horse, $ownerNickname, $ownerVrlId);
        $mainHorseId = insertHorse($db, $mainName, $horse, $breedsByName, $colorsByName, $ancestor, $sireId, $damId);
        $inserted++;
    }

    $db->commit();

    echo json_encode([
        'ok'          => true,
        'inserted'    => $inserted,
        'skipped'     => $skipped,
        'mainHorseId' => $mainHorseId,
        'errors'      => $errors,
    ]);

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('VRL import error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Tietokantavirhe tuonnin aikana']);
}
