<?php
require_once __DIR__ . '/../src/includes/db.php';
require_once __DIR__ . '/../src/includes/theme.php';

// Jos aktiivisella teemalla on oma hevosen profiilisivu, käytetään sitä
$_vt_themeFile = resolveThemePath('hevonen.php');
if ($_vt_themeFile !== false
    && !str_starts_with(THEME_PATH, THEMES_ROOT . 'default' . DIRECTORY_SEPARATOR)) {
    require $_vt_themeFile;
    exit;
}

$db = getDB();

// Hae hevonen slugin tai id:n perusteella
$contactCols = 'oc.nickname AS owner_nickname, oc.stable_name AS owner_stable_name, oc.stable_url AS owner_stable_url, oc.vrl_id AS owner_vrl_id, oc.email AS owner_email, oc.country AS owner_country,
                bc.nickname AS breeder_nickname, bc.stable_name AS breeder_stable_name, bc.stable_url AS breeder_stable_url, bc.vrl_id AS breeder_vrl_id, bc.email AS breeder_email, bc.country AS breeder_country,
                ic.nickname AS importer_nickname, ic.stable_name AS importer_stable_name, ic.stable_url AS importer_stable_url, ic.vrl_id AS importer_vrl_id, ic.email AS importer_email, ic.country AS importer_country';
$contactJoins = 'LEFT JOIN contacts oc ON oc.id = h.owner_contact_id
         LEFT JOIN contacts bc ON bc.id = h.breeder_contact_id
         LEFT JOIN contacts ic ON ic.id = h.importer_contact_id';

if (!empty($_GET['slug'])) {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['slug'])));
    $stmt = $db->prepare(
        "SELECT h.*,
                (SELECT GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ', ')
                 FROM horse_disciplines hd
                 JOIN disciplines d ON d.id = hd.discipline_id
                 WHERE hd.horse_id = h.id) AS discipline_names,
                b.name AS breed_name, c.name AS color_name,
                $contactCols
         FROM horses h
         LEFT JOIN breeds b ON b.id = h.breed_id
         LEFT JOIN colors c ON c.id = h.color_id
         $contactJoins
         WHERE h.slug = :slug AND h.is_deleted = 0"
    );
    $stmt->execute([':slug' => $slug]);
} elseif (!empty($_GET['id'])) {
    // Taaksepäin yhteensopivuus vanhoille linkeille
    $id = (int)$_GET['id'];
    $stmt = $db->prepare(
        "SELECT h.*,
                (SELECT GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ', ')
                 FROM horse_disciplines hd
                 JOIN disciplines d ON d.id = hd.discipline_id
                 WHERE hd.horse_id = h.id) AS discipline_names,
                b.name AS breed_name, c.name AS color_name,
                $contactCols
         FROM horses h
         LEFT JOIN breeds b ON b.id = h.breed_id
         LEFT JOIN colors c ON c.id = h.color_id
         $contactJoins
         WHERE h.id = :id AND h.is_deleted = 0"
    );
    $stmt->execute([':id' => $id]);
} else {
    http_response_code(404);
    exit;
}

$horse = $stmt->fetch();

if (!$horse) {
    http_response_code(404);
    exit;
}

$id = (int)$horse['id'];
$page_title = $horse['name'];

// Hae kilpailut
$stmtComp = $db->prepare(
    'SELECT competition_date, discipline, country, organizer, organizer_url, class, placement, points, notes
     FROM competitions WHERE horse_id = :id ORDER BY competition_date DESC'
);
$stmtComp->execute([':id' => $id]);
$competitions = $stmtComp->fetchAll();

// Hae näyttelytulokset
$stmtShow = $db->prepare(
    'SELECT s.show_date, s.discipline, s.country, s.organizer, s.organizer_url,
            s.class, s.placement, s.points, s.review, s.notes,
            jc.nickname AS judge_nickname, jc.stable_name AS judge_stable_name, jc.email AS judge_email,
            p.filename AS photo_filename, p.title AS photo_title, p.original_name AS photo_original_name
     FROM showrecords s LEFT JOIN contacts jc ON jc.id = s.judge_contact_id
     LEFT JOIN horse_photos p ON p.id = s.photo_id
     WHERE s.horse_id = :id ORDER BY s.show_date DESC'
);
$stmtShow->execute([':id' => $id]);
$showrecords = $stmtShow->fetchAll();

// Hae kuvat
$stmtPhotos = $db->prepare(
    'SELECT filename, original_name, title, caption FROM horse_photos
     WHERE horse_id = :id ORDER BY sort_order ASC LIMIT 5'
);
$stmtPhotos->execute([':id' => $id]);
$photos = $stmtPhotos->fetchAll();

// Varsat (tämä hevonen isänä tai emänä)
$stmtFoals = $db->prepare(
    'SELECT f.foal_name, f.birth_date, f.gender, f.status, f.merits,
            f.sire_id, f.dam_id, f.foal_horse_id,
            b.abbreviation AS breed_abbr,
            s.name AS sire_name, s.slug AS sire_slug,
            d.name AS dam_name,  d.slug AS dam_slug,
            oc.nickname AS owner_nickname, oc.vrl_id AS owner_vrl, oc.email AS owner_email,
            fh.slug AS foal_horse_slug
     FROM foals f
     LEFT JOIN breeds   b  ON b.id  = f.breed_id
     LEFT JOIN horses   s  ON s.id  = f.sire_id          AND s.is_deleted = 0
     LEFT JOIN horses   d  ON d.id  = f.dam_id           AND d.is_deleted = 0
     LEFT JOIN contacts oc ON oc.id = f.owner_contact_id
     LEFT JOIN horses   fh ON fh.id = f.foal_horse_id    AND fh.is_deleted = 0
     WHERE f.sire_id = :id1 OR f.dam_id = :id2
     ORDER BY f.birth_date DESC, f.foal_name ASC'
);
$stmtFoals->execute([':id1' => $id, ':id2' => $id]);
$foals = $stmtFoals->fetchAll();

// Postaukset
$stmtPosts = $db->prepare(
    'SELECT p.title, p.slug, p.created_at
     FROM posts p
     JOIN post_horses ph ON ph.post_id = p.id
     WHERE ph.horse_id = :id
     ORDER BY p.created_at DESC'
);
$stmtPosts->execute([':id' => $id]);
$horsePosts = $stmtPosts->fetchAll();

// Sukutaulu
$pedigree = getHorsePedigree($id);

$_themePage = resolveThemePath('pages/hevonen.php');
if ($_themePage === false) {
    http_response_code(404);
    exit;
}
require $_themePage;
