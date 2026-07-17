<?php
require_once __DIR__ . '/../../src/includes/db.php';
$db = getDB();

$horse = null;
$baseQuery = 'SELECT h.*,
        (SELECT GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR \', \')
         FROM horse_disciplines hd
         JOIN disciplines d ON d.id = hd.discipline_id
         WHERE hd.horse_id = h.id) AS discipline_names,
        b.name AS breed_name, b.abbreviation AS breed_abbr,
        c.name AS color_name, c.abbreviation AS color_abbr,
        pd.name AS porrastetut_discipline_name,
        oc.nickname AS owner_nickname, oc.stable_name AS owner_stable_name, oc.stable_url AS owner_stable_url, oc.vrl_id AS owner_vrl_id, oc.email AS owner_email, oc.country AS owner_country,
        bc.nickname AS breeder_nickname, bc.stable_name AS breeder_stable_name, bc.stable_url AS breeder_stable_url, bc.vrl_id AS breeder_vrl_id, bc.email AS breeder_email, bc.country AS breeder_country,
        ic.nickname AS importer_nickname, ic.stable_name AS importer_stable_name, ic.stable_url AS importer_stable_url, ic.vrl_id AS importer_vrl_id, ic.email AS importer_email, ic.country AS importer_country
 FROM horses h
 LEFT JOIN breeds b ON b.id = h.breed_id
 LEFT JOIN colors c ON c.id = h.color_id
 LEFT JOIN disciplines pd ON pd.id = h.porrastetut_discipline_id
 LEFT JOIN contacts oc ON oc.id = h.owner_contact_id
 LEFT JOIN contacts bc ON bc.id = h.breeder_contact_id
 LEFT JOIN contacts ic ON ic.id = h.importer_contact_id';

if (!empty($_GET['slug'])) {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['slug'])));
    $stmt = $db->prepare($baseQuery . ' WHERE h.slug = :slug AND h.is_deleted = 0');
    $stmt->execute([':slug' => $slug]);
    $horse = $stmt->fetch();
} elseif (!empty($_GET['id'])) {
    $stmt = $db->prepare($baseQuery . ' WHERE h.id = :id AND h.is_deleted = 0');
    $stmt->execute([':id' => (int)$_GET['id']]);
    $horse = $stmt->fetch();
}

if (!$horse) {
    http_response_code(404);
    ?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><?php if (defined('THEME_URL')): ?><base href="<?= htmlspecialchars(THEME_URL, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link href="style.css" rel="stylesheet" type="text/css" /></head>
<body><div id="header"><h1 class="inset-text">Hevosta ei löydy</h1></div>
<div id="wrapper"><div id="content"><p>Hevosta ei löydy tai se on poistettu.</p>
<p><a href="asukkaat.php">&#8592; Tallin hevoset</a></p>
</div></div></body></html><?php
    exit;
}

$id = (int)$horse['id'];

$stmtComp = $db->prepare(
    'SELECT competition_date, discipline, country, organizer, organizer_url, class, placement, points, notes
     FROM competitions WHERE horse_id = :id AND is_deleted = 0 ORDER BY competition_date DESC'
);
$stmtComp->execute([':id' => $id]);
$competitions = $stmtComp->fetchAll();

$stmtShow = $db->prepare(
    'SELECT s.show_date, s.discipline, s.country, s.organizer, s.organizer_url,
            s.class, s.placement, s.points, s.review, s.notes,
            jc.nickname AS judge_nickname, jc.stable_name AS judge_stable_name, jc.email AS judge_email,
            p.filename AS photo_filename, p.title AS photo_title, p.original_name AS photo_original_name
     FROM showrecords s LEFT JOIN contacts jc ON jc.id = s.judge_contact_id
     LEFT JOIN horse_photos p ON p.id = s.photo_id
     WHERE s.horse_id = :id AND s.is_deleted = 0 ORDER BY s.show_date DESC'
);
$stmtShow->execute([':id' => $id]);
$showrecords = $stmtShow->fetchAll();

$stmtFoals = $db->prepare(
    'SELECT f.foal_name, f.birth_date, f.gender, f.status, f.merits,
            f.sire_id, f.dam_id, f.foal_horse_id,
            b.abbreviation AS breed_abbr,
            s.name AS sire_name, d.name AS dam_name,
            oc.nickname AS owner_nickname, oc.vrl_id AS owner_vrl, oc.email AS owner_email,
            fh.slug AS foal_horse_slug
     FROM foals f
     LEFT JOIN breeds   b  ON b.id  = f.breed_id
     LEFT JOIN horses   s  ON s.id  = f.sire_id          AND s.is_deleted = 0
     LEFT JOIN horses   d  ON d.id  = f.dam_id           AND d.is_deleted = 0
     LEFT JOIN contacts oc ON oc.id = f.owner_contact_id
     LEFT JOIN horses   fh ON fh.id = f.foal_horse_id    AND fh.is_deleted = 0
     WHERE (f.sire_id = :id1 OR f.dam_id = :id2) AND f.is_deleted = 0
     ORDER BY f.birth_date DESC, f.foal_name ASC'
);
$stmtFoals->execute([':id1' => $id, ':id2' => $id]);
$foals = $stmtFoals->fetchAll();

$stmtPhotos = $db->prepare(
    'SELECT filename, original_name, title, caption
     FROM horse_photos WHERE horse_id = :id ORDER BY sort_order ASC LIMIT 12'
);
$stmtPhotos->execute([':id' => $id]);
$photos = $stmtPhotos->fetchAll();

$stmtPosts = $db->prepare(
    'SELECT p.title, p.slug, p.content, p.created_at
     FROM posts p
     JOIN post_horses ph ON ph.post_id = p.id
     WHERE ph.horse_id = :id AND p.is_deleted = 0
     ORDER BY p.created_at DESC'
);
$stmtPosts->execute([':id' => $id]);
$horsePosts = $stmtPosts->fetchAll();

$pedigree = getHorsePedigree($id);
$sire = $pedigree['sire'] ?? null;
$dam  = $pedigree['dam']  ?? null;
$ss   = $sire['sire']     ?? null;
$sd   = $sire['dam']      ?? null;
$ds   = $dam['sire']      ?? null;
$dd   = $dam['dam']       ?? null;
$sss  = $ss['sire']       ?? null;
$ssd  = $ss['dam']        ?? null;
$sds  = $sd['sire']       ?? null;
$sdd  = $sd['dam']        ?? null;
$dss  = $ds['sire']       ?? null;
$dsd  = $ds['dam']        ?? null;
$dds  = $dd['sire']       ?? null;
$ddd  = $dd['dam']        ?? null;

$genderFi = ['ori' => 'ori', 'tamma' => 'tamma', 'ruuna' => 'ruuna'];

function pedHorse(?array $h): string {
    if (!$h) return '—';
    $parts = [];
    if (!empty($h['breed_abbr'])) $parts[] = e($h['breed_abbr']);
    if (!empty($h['color_abbr'])) $parts[] = e($h['color_abbr']);
    if (!empty($h['height_cm']))  $parts[] = e((string)$h['height_cm']) . 'cm';
    $metaHtml = $parts ? '<br /><small>' . implode(', ', $parts) . '</small>' : '';
    if (!empty($h['evm']) || !empty($h['ancestor'])) {
        if (!empty($h['profile_url']) && filter_var($h['profile_url'], FILTER_VALIDATE_URL)) {
            $link = '<a href="' . e($h['profile_url']) . '" target="_blank" rel="noopener">' . e($h['name']) . '</a>';
        } else {
            $link = e($h['name']);
        }
    } else {
        $link = '<a href="hevonen.php?id=' . (int)$h['id'] . '">' . e($h['name']) . '</a>';
    }
    return $link . $metaHtml;
}

function compClass(?string $discipline): string {
    if (!$discipline) return 'muu';
    return preg_replace('/[^a-z0-9]/', '', mb_strtolower($discipline, 'UTF-8'));
}

$uniqueDisc = [];
foreach ($competitions as $comp) {
    if (!empty($comp['discipline']) && !in_array($comp['discipline'], $uniqueDisc)) {
        $uniqueDisc[] = $comp['discipline'];
    }
}

$uniqueDiscShow = [];
foreach ($showrecords as $s) {
    if (!empty($s['discipline']) && !in_array($s['discipline'], $uniqueDiscShow)) {
        $uniqueDiscShow[] = $s['discipline'];
    }
}

$agingSystem = $horse['aging_system'] ?: 'IRL';
$age = calculateAgeBySystem($horse['birth_date'], $agingSystem);
$tietokuva = !empty($photos) ? $photos[0] : null;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<?php if (defined('THEME_URL')): ?>
<base href="<?= htmlspecialchars(THEME_URL, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<title><?= e($horse['name']) ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css" integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous" />
<link href="style.css" rel="stylesheet" type="text/css" />
<link href="tabcontent.css" rel="stylesheet" type="text/css" />
<script src="tabcontent.js" type="text/javascript"></script>
<link rel="stylesheet" type="text/css" href="filter_hevonen.css" />

</head>
<body>

	<div id="header"><h1 class="inset-text"><?= e($horse['name']) ?></h1></div>

	<div id="wrapper"><div id="content_hevonen">
<ul class="tabs">
	<li class="selected"><a href="#view1">Perustiedot &amp; luonne</a></li>
	<li><a href="#view2">Suku</a></li>
	<li><a href="#view3">Kilpailut</a></li>
	<li><a href="#view7">Näyttelyt</a></li>
	<li><a href="#view4">Kuvagalleria</a></li>
	<?php if (!empty($foals)): ?><li><a href="#view5">Varsat (<?= count($foals) ?>)</a></li><?php endif; ?>
	<?php if (!empty($horsePosts)): ?><li><a href="#view6">Postaukset (<?= count($horsePosts) ?>)</a></li><?php endif; ?>
</ul>

	<div class="tabcontents"><div id="view1">



<?php if ($tietokuva): ?>
<img src="<?= e(UPLOADS_URL . $tietokuva['filename']) ?>" style="height: 385px; margin-right: 30px;" align="right" />
<?php endif; ?>

<?php if ($horse['call_name']): ?>
<p class="tiedot">Kutsumanimeltään <u><?= e($horse['call_name']) ?></u><br />
<?php else: ?>
<p class="tiedot">
<?php endif; ?>
<?php
$colorGender = [];
if ($horse['color_name']) {
    $colorGender[] = e($horse['color_name']);
    if ($horse['genes']) {
        $colorGender[] = '<span class="pikkuinfo">(' . e($horse['genes']) . ')</span>';
    }
}
if ($horse['breed_name']) $colorGender[] = e($horse['breed_name']);
if ($horse['gender'])     $colorGender[] = e($genderFi[$horse['gender']] ?? $horse['gender']) . ', ';
if ($horse['height_cm'])  $colorGender[] = e((string)$horse['height_cm']) . 'cm';
echo implode(' ', $colorGender);
?><br />
<?php if ($horse['birth_date']): ?>
Syntynyt <?= e(formatDate($horse['birth_date'])) ?>, on nyt <?= e((string)$age) ?>-vuotias<br />
<?php endif; ?>
<?php if ($horse['vh_id']): ?>
<a href="https://virtuaalihevoset.net/virtuaalihevoset/hevonen/<?= e($horse['vh_id']) ?>"><?= e($horse['vh_id']) ?></a><?php endif; ?>
<?php if ($horse['pkk_id']): ?>
, <a href="https://piirroshevosille.fi/hevoset/hevonen/<?= e($horse['pkk_id']) ?>"><?= e($horse['pkk_id']) ?></a><br />
<?php endif; ?>
</p>

<?php
// Muotoilee yhteystiedon muotoon: Nimi / Talli (VRL-tunnus, email) Maa
function formatContact(array $h, string $prefix): string {
    $nick    = $h[$prefix.'_nickname'] ?? '';
    $stable  = $h[$prefix.'_stable_name'] ?? '';
    $url     = $h[$prefix.'_stable_url'] ?? '';
    $vrl     = $h[$prefix.'_vrl_id'] ?? '';
    $email   = $h[$prefix.'_email'] ?? '';
    $country = $h[$prefix.'_country'] ?? '';
    if (!$nick && !$stable) return '';
    $out = '';
    if ($nick) $out .= e($nick);
    if ($stable) {
        if ($out) $out .= ' / ';
        $out .= $url ? '<a href="'.e($url).'" target="_blank" rel="noopener">'.e($stable).'</a>' : e($stable);
    }
    $parens = array_filter([e($vrl), $email ? '<a href="mailto:'.e($email).'">&#9993;</a>' : '']);
    if ($parens) $out .= ' <span class="pikkuinfo">(' . implode(', ', $parens) . ')</span>';
    if ($country) $out .= ' <img src="https://lianna.altervista.org/flag/' . e(strtolower($country)) . '.png" alt="' . e($country) . '" />';
    return $out;
}
$ownerStr   = formatContact($horse, 'owner');
$breederStr = formatContact($horse, 'breeder');
$importerStr = formatContact($horse, 'importer');
if ($ownerStr || $breederStr || $importerStr): ?>
<p class="tiedot">
<?php if ($breederStr): ?>Kasvattanut <?= $breederStr ?><br /><?php endif; ?>
<?php if ($ownerStr): ?>Omistaa <?= $ownerStr ?><br /><?php endif; ?>
<?php if ($importerStr): ?>Tuonut <?= $importerStr ?><br /><?php endif; ?>
</p>
<?php endif; ?>

<?php
$levelParts = [];
if (!empty($horse['level_ko'])) $levelParts[] = 'ko: ' . e($horse['level_ko']);
if (!empty($horse['level_re'])) $levelParts[] = 're: ' . e($horse['level_re']);
if (!empty($horse['level_ke'])) $levelParts[] = 'ke: ' . e($horse['level_ke']);
if ($horse['discipline_names'] || $levelParts):
?>
<p class="tiedot">
<?php if ($horse['discipline_names']): ?><?= e($horse['discipline_names']) ?><br /><?php endif; ?>
<?php if ($levelParts): ?><?= implode(', ', $levelParts) ?><br /><?php endif; ?>
</p>
<?php endif; ?>

<?php if ($horse['vh_id'] && !empty($horse['porrastetut'])): ?>
<p class="tiedot">
Kilpaillut porrastetuissa <?= $horse['porrastetut_discipline_name'] ? e($horse['porrastetut_discipline_name']) . 'kilpailuissa' : 'porrastetuissa' ?> <br />
<?php
$vh = $horse['vh_id'];
$url = 'http://virtuaalihevoset.net/rajapinta/porrastetut/' . $vh;
$obj = json_decode(@file_get_contents($url), true);

if (is_array($obj) && isset($obj['error']) && $obj['error'] == 0) {
    $hevonen = $obj['porrastetut']['hevonen'];
    $jaosMap = ['kouluratsastus' => 1, 'esteratsastus' => 2, 'kenttäratsastus' => 3];
    $jaos = $jaosMap[strtolower($horse['porrastetut_discipline_name'] ?? '')] ?? 3;
    if ($hevonen['error'] == 1) {
        echo e($hevonen['error_message']);
    } else {
        $tasoinfo = $hevonen['tasot'][$jaos];
        echo e((string)$tasoinfo['pisteet']) . ' ominaisuuspistettä,';
        echo ' on nyt tasolla ' . e((string)$tasoinfo['max_taso_per_pisteet']) . '/' . e((string)$tasoinfo['taso_rajoitus']);
    }
} elseif (is_array($obj) && isset($obj['error']) && $obj['error'] == 1) {
    echo e($obj['error_description']);
} else {
    echo 'Tapahtui odottamaton virhe!';
}
?>
</p>
<?php endif; ?>

<?php if ($horse['description']): ?>
<p><?= nl2br(e($horse['description'])) ?></p>
<?php endif; ?>



	</div><div id="view2">



	<table class="sukutaulu">

	<tr><td width="28%" rowspan="4">

<?= pedHorse($sire) ?>

	</td><td width="8%" rowspan="4"><div class="isohaka">{</div></td><td width="28%" rowspan="2">

<?= pedHorse($ss) ?>

	</td><td width="8%" rowspan="2"><div class="pikkuhaka">{</div></td><td width="28%">

<?= pedHorse($sss) ?>

	</td></tr><tr><td>

<?= pedHorse($ssd) ?>

	</td></tr><tr><td rowspan="2">

<?= pedHorse($sd) ?>

	</td><td rowspan="2"><div class="pikkuhaka">{</div></td><td>

<?= pedHorse($sds) ?>

	</td></tr><tr><td>

<?= pedHorse($sdd) ?>

	</td></tr>


	<tr><td width="28%" rowspan="4">

<?= pedHorse($dam) ?>

	</td><td width="8%" rowspan="4"><div class="isohaka">{</div></td><td width="28%" rowspan="2">

<?= pedHorse($ds) ?>

	</td><td width="8%" rowspan="2"><div class="pikkuhaka">{</div></td><td width="28%">

<?= pedHorse($dss) ?>

	</td></tr><tr><td>

<?= pedHorse($dsd) ?>

	</td></tr><tr><td rowspan="2">

<?= pedHorse($dd) ?>

	</td><td rowspan="2"><div class="pikkuhaka">{</div></td><td>

<?= pedHorse($dds) ?>

	</td></tr><tr><td>

<?= pedHorse($ddd) ?>

	</td></tr>
</table>

<?php if ($horse['pedigree_notes']): ?>
<div><div style="text-transform: none; border-bottom: 0px; margin-top: 20px; margin-bottom: 0px; margin-left: 0px; display: block;">
<span onClick="if (this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display != '') {  this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = ''; this.innerHTML = '&lt;a href=\'#\' onClick=\'return false;\'&gt;Sulje &lt;/a&gt;'; } else { this.parentNode.parentNode.getElementsByTagName('div')[1].getElementsByTagName('div')[0].style.display = 'none'; this.innerHTML = '&lt;a href=\'#\' onClick=\'return false;\'&gt;Lue sukuselvitys&lt;/a&gt;'; }" />
<a href="#" onClick="return false;">Lue sukuselvitys</a></div><div class="quotecontent"><div style="display: none;">
<?= nl2br(e($horse['pedigree_notes'])) ?>
</div></div></div>
<?php endif; ?>



	</div><div id="view3">



<h2>Kilpailut</h2>

<?php if (empty($competitions)): ?>
<p><i>Ei kilpailutuloksia.</i></p>
<?php else: ?>

<div id="myBtnContainer">
  <button class="btn active" onclick="filterSelection('all')"> Kaikki</button>
<?php foreach ($uniqueDisc as $disc): ?>
  <button class="btn" onclick="filterSelection('<?= e(addslashes(compClass($disc))) ?>')"><?= e($disc) ?></button>
<?php endforeach; ?>
</div>

<div class="container">

<?php foreach ($competitions as $comp):
    $dClass = compClass($comp['discipline']);
    $year   = $comp['competition_date'] ? (string)date('Y', strtotime($comp['competition_date'])) : '';
?>
<div class="filterDiv <?= e($dClass) ?> <?= e($year) ?>"><table class="kisat"><tr class="kilpailutulos">
<td class="pvm"> <?= e(formatDate($comp['competition_date'])) ?> </td>
<td class="laji"> <?= e($comp['discipline'] ?? '—') ?> </td>
<td class="paikka">
<?php if (!empty($comp['organizer_url'])): ?>
<?php if ($comp['country']): ?><img src="https://lianna.altervista.org/flag/<?= e(strtolower($comp['country'])) ?>.png" /> &nbsp; <?php endif; ?>
<a href="<?= e($comp['organizer_url']) ?>" target="_blank" rel="noopener"><?= e($comp['organizer'] ?? '—') ?></a>
<?php else: ?>
<?= e($comp['organizer'] ?? '—') ?>
<?php endif; ?>
</td>
<td class="luokka"> <?= e($comp['class'] ?? '—') ?> </td>
<td class="tulos"> <?= e($comp['placement'] ?? '—') ?> </td>
<?php if ($comp['points'] !== null): ?><td class="virheet"> <?= e((string)$comp['points']) ?> </td><?php else: ?><td class="virheet"> — </td><?php endif; ?>
<?php if (!empty($comp['notes'])): ?><td class="kisainfo"> <?= e($comp['notes']) ?> </td><?php endif; ?>
</tr></table></div>
<?php endforeach; ?>

</div>
<?php endif; ?>



	</div><div id="view7">



<h2>Näyttelyt</h2>

<?php if (empty($showrecords)): ?>
<p><i>Ei näyttelytuloksia.</i></p>
<?php else: ?>

<div id="myBtnContainerShow">
  <button class="btn active" onclick="filterSelectionShow('all')"> Kaikki</button>
<?php foreach ($uniqueDiscShow as $disc): ?>
  <button class="btn" onclick="filterSelectionShow('<?= e(addslashes(compClass($disc))) ?>')"><?= e($disc) ?></button>
<?php endforeach; ?>
</div>

<div class="container">

<?php foreach ($showrecords as $s):
    $dClass = compClass($s['discipline']);
    $year   = $s['show_date'] ? (string)date('Y', strtotime($s['show_date'])) : '';
    $judgeLabel = trim(($s['judge_nickname'] ?? '') . ' ' . ($s['judge_stable_name'] ?? ''));
    $judgeHtml  = $judgeLabel !== '' && !empty($s['judge_email'])
        ? '<a href="mailto:' . e($s['judge_email']) . '">' . e($judgeLabel) . '</a>'
        : e($judgeLabel);
?>
<div class="filterDivShow <?= e($dClass) ?> <?= e($year) ?>"><table class="kisat"><tr class="kilpailutulos">
<?php if (!empty($s['photo_filename'])): ?>
<td class="kisakuva"><img src="<?= e(UPLOADS_URL . $s['photo_filename']) ?>" alt="<?= e($s['photo_title'] ?? $s['photo_original_name'] ?? '') ?>" /></td>
<?php else: ?>
<td class="kisakuva">&nbsp;</td>
<?php endif; ?>
<td class="pvm"> <?= e(formatDate($s['show_date'])) ?> </td>
<td class="laji"> <?= e($s['discipline'] ?? '—') ?> </td>
<td class="paikka">
<?php if (!empty($s['organizer_url'])): ?>
<?php if ($s['country']): ?><img src="https://lianna.altervista.org/flag/<?= e(strtolower($s['country'])) ?>.png" /> &nbsp; <?php endif; ?>
<a href="<?= e($s['organizer_url']) ?>" target="_blank" rel="noopener"><?= e($s['organizer'] ?? '—') ?></a>
<?php else: ?>
<?= e($s['organizer'] ?? '—') ?>
<?php endif; ?>
</td>
<td class="luokka"> <?= e($s['class'] ?? '—') ?> </td>
<td class="tulos"> <?= e($s['placement'] ?? '—') ?> </td>
<?php if ($s['points'] !== null): ?><td class="virheet"> <?= e((string)$s['points']) ?> </td><?php else: ?><td class="virheet"> — </td><?php endif; ?>
<?php if ($judgeLabel !== ''): ?><td class="kisainfo"> Tuomari: <?= $judgeHtml ?> </td><?php endif; ?>
<?php if (!empty($s['review'])): ?><td class="kisainfo"> <?= nl2br(e($s['review'])) ?> </td><?php endif; ?>
<?php if (!empty($s['notes'])): ?><td class="kisainfo"> Huom: <?= nl2br(e($s['notes'])) ?> </td><?php endif; ?>
</tr></table></div>
<?php endforeach; ?>

</div>
<?php endif; ?>



	</div><div id="view4">



	<table width="100%"><tr><td>
<?php if (empty($photos)): ?>
<p><i>Ei kuvia.</i></p>
<?php else: ?>
<?php foreach ($photos as $photo): ?>
<?php
    $alt = $photo['title'] ?: ($photo['original_name'] ?: $horse['name']);
?>
	<table class="pkk-kisat"><tr><td>
<a href="<?= e(UPLOADS_URL . $photo['filename']) ?>" rel="lightbox"><img src="<?= e(UPLOADS_URL . $photo['filename']) ?>" class="pkk-img" /></a><br /><small>
<?= e($alt) ?>
</small>
	</td></tr></table>
<?php endforeach; ?>
<?php endif; ?>
	</td></tr></table>



	</div><?php if (!empty($horsePosts)): ?><div id="view6">

<h2>Postaukset</h2>
<?php foreach ($horsePosts as $hp): ?>
<div class="postaus-entry">
  <h3 class="postaus-otsikko"><?= e($hp['title']) ?></h3>
  <small class="postaus-pvm"><?= e(formatDate($hp['created_at'])) ?></small>
  <div class="postaus-teksti"><?= nl2br(e($hp['content'])) ?></div>
</div>
<?php endforeach; ?>

	</div><?php endif; ?><?php if (!empty($foals)): ?><div id="view5">

<h2>Varsat</h2>
<table class="kisat" style="width:100%">
  <tr>
    <th style="text-align:left;padding:4px 8px">Rotu</th>
    <th style="text-align:left;padding:4px 8px">Varsan nimi</th>
    <th style="text-align:left;padding:4px 8px">Syntymäpäivä</th>
    <th style="text-align:left;padding:4px 8px">Tilanne</th>
    <th style="text-align:left;padding:4px 8px">i/e. Hevosen nimi</th>
    <th style="text-align:left;padding:4px 8px">Omistaja</th>
    <th style="text-align:left;padding:4px 8px">Meriitit</th>
  </tr>
  <?php foreach ($foals as $f):
    $breedGender = '';
    if ($f['breed_abbr']) $breedGender .= e($f['breed_abbr']);
    if (!empty($f['gender']) && $f['gender'] !== 'tuntematon')
        $breedGender .= ($breedGender ? '-' : '') . e($f['gender']);

    if ((int)$f['sire_id'] === $id) {
        $otherLabel = 'e.';
        $otherName  = $f['dam_name']  ?? null;
        $otherId    = (int)$f['dam_id'];
    } else {
        $otherLabel = 'i.';
        $otherName  = $f['sire_name'] ?? null;
        $otherId    = (int)$f['sire_id'];
    }

    $birthStr  = $f['birth_date'] ? 's. ' . date('d.m.Y', strtotime($f['birth_date'])) : '—';
    $statusStr = $f['status'] === 'expected' ? 'Odotettu' : 'Syntynyt';

    $ownerStr = '';
    if ($f['owner_nickname']) {
        if ($f['owner_email']) {
            $ownerStr .= '<a href="mailto:' . e($f['owner_email']) . '">' . e($f['owner_nickname']) . '</a>';
        } else {
            $ownerStr .= e($f['owner_nickname']);
        }
    }
    if ($f['owner_vrl']) $ownerStr .= ($ownerStr ? ' ' : '') . '(' . e($f['owner_vrl']) . ')';
  ?>
  <tr class="kilpailutulos">
    <td class="pvm"><small><?= $breedGender ?: '—' ?></small></td>
    <td class="pvm">
      <?php if ($f['foal_horse_id']): ?>
        <?php $foalUrl = $f['foal_horse_slug'] ? horseUrl(['slug' => $f['foal_horse_slug']]) : 'hevonen.php?id=' . (int)$f['foal_horse_id']; ?>
        <a href="<?= e($foalUrl) ?>"><?= e($f['foal_name'] ?? '—') ?></a>
      <?php else: ?>
        <?= e($f['foal_name'] ?? '—') ?>
      <?php endif; ?>
    </td>
    <td class="pvm"><small><?= $birthStr ?></small></td>
    <td class="pvm"><small><?= e($statusStr) ?></small></td>
    <td class="laji">
      <?php if ($otherName): ?>
        <?= e($otherLabel) ?> <a href="hevonen.php?id=<?= $otherId ?>"><?= e($otherName) ?></a>
      <?php else: ?>—<?php endif; ?>
    </td>
    <td class="luokka"><small><?= $ownerStr ? 'om. ' . $ownerStr : '—' ?></small></td>
    <td class="luokka"><small><?= $f['merits'] ? nl2br(e($f['merits'])) : '—' ?></small></td>
  </tr>
  <?php endforeach; ?>
</table>

	</div><?php endif; ?></div>




	</div>

<?php include_once("footer-content.php"); ?>



<script>
filterSelection("all")
function filterSelection(c) {
  var x, i;
  x = document.getElementsByClassName("filterDiv");
  if (c == "all") c = "";
  for (i = 0; i < x.length; i++) {
    w3RemoveClass(x[i], "show");
    if (x[i].className.indexOf(c) > -1) w3AddClass(x[i], "show");
  }
}

filterSelectionShow("all")
function filterSelectionShow(c) {
  var x, i;
  x = document.getElementsByClassName("filterDivShow");
  if (c == "all") c = "";
  for (i = 0; i < x.length; i++) {
    w3RemoveClass(x[i], "show");
    if (x[i].className.indexOf(c) > -1) w3AddClass(x[i], "show");
  }
}

function w3AddClass(element, name) {
  var i, arr1, arr2;
  arr1 = element.className.split(" ");
  arr2 = name.split(" ");
  for (i = 0; i < arr2.length; i++) {
    if (arr1.indexOf(arr2[i]) == -1) {element.className += " " + arr2[i];}
  }
}

function w3RemoveClass(element, name) {
  var i, arr1, arr2;
  arr1 = element.className.split(" ");
  arr2 = name.split(" ");
  for (i = 0; i < arr2.length; i++) {
    while (arr1.indexOf(arr2[i]) > -1) {
      arr1.splice(arr1.indexOf(arr2[i]), 1);
    }
  }
  element.className = arr1.join(" ");
}

var btnContainer = document.getElementById("myBtnContainer");
if (btnContainer) {
  var btns = btnContainer.getElementsByClassName("btn");
  for (var i = 0; i < btns.length; i++) {
    btns[i].addEventListener("click", function(){
      var current = document.getElementsByClassName("active");
      current[0].className = current[0].className.replace(" active", "");
      this.className += " active";
    });
  }
}
</script>



</body>
</html>
