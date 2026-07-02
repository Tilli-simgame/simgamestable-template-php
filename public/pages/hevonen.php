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
    $page_title = 'Hevosta ei löydy';
    require __DIR__ . '/../src/includes/header.php';
    echo '<main><p>Hevosta ei löydy.</p></main>';
    require __DIR__ . '/../src/includes/footer.php';
    exit;
}

$horse = $stmt->fetch();

if (!$horse) {
    http_response_code(404);
    $page_title = 'Hevosta ei löydy';
    require __DIR__ . '/../src/includes/header.php';
    echo '<main><p>Hevosta ei löydy tai se on poistettu.</p></main>';
    require __DIR__ . '/../src/includes/footer.php';
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

require __DIR__ . '/../src/includes/header.php';

$genderFi = ['ori' => 'Ori', 'tamma' => 'Tamma', 'ruuna' => 'Ruuna', 'käkky' => 'Käkky'];

// Apufunktio sukutaulun hevoslinkkiin
function pedigreeHorseLink(array $h): string {
    if ($h['evm'] || !empty($h['ancestor'])) {
        if (!empty($h['profile_url'])) {
            $safeUrl = filter_var($h['profile_url'], FILTER_VALIDATE_URL) !== false ? $h['profile_url'] : '#';
            $nameLink = '<a href="' . e($safeUrl) . '" class="ext-link" target="_blank" rel="noopener">' . e($h['name']) . '</a>';
        } else {
            $nameLink = e($h['name']);
        }
    } else {
        $nameLink = '<a href="' . e(horseUrl($h)) . '">' . e($h['name']) . '</a>';
    }
    $meta = [];
    if (!empty($h['breed_abbr'])) $meta[] = e($h['breed_abbr']);
    if (!empty($h['color_abbr'])) $meta[] = e($h['color_abbr']);
    if (!empty($h['height_cm']))  $meta[] = e((string)$h['height_cm']) . ' cm';
    $metaHtml = $meta ? '<span class="ped-meta">' . implode(' · ', $meta) . '</span>' : '';
    return $nameLink . $metaHtml;
}

// Ensimmäinen kuva hero-banneria varten
$heroPhoto = !empty($photos) ? $photos[0]['filename'] : null;
$heroStyle = $heroPhoto
    ? 'background-image: var(--hero-overlay), url(' . e(UPLOADS_URL . $heroPhoto) . ');background-size:cover;background-position:center;'
    : '';
?>

<!-- Hero-banneri -->
<div class="hero-banner" style="<?= $heroStyle ?>">
  <div class="hero-overlay">
    <h1><?= e($horse['name']) ?></h1>
    <?php if ($horse['call_name']): ?>
      <div class="horse-callname">"<?= e($horse['call_name']) ?>"</div>
    <?php endif; ?>
    <div class="hero-pills">
      <?php if ($horse['breed_name']): ?><span class="hero-pill"><?= e($horse['breed_name']) ?></span><?php endif; ?>
      <span class="hero-pill"><?= e($genderFi[$horse['gender']] ?? $horse['gender']) ?></span>
      <?php if ($horse['birth_date']): ?>
        <?php
          $agingSystem = $horse['aging_system'] ?: 'IRL';
          $heroAge = calculateAgeBySystem($horse['birth_date'], $agingSystem);
        ?>
        <span class="hero-pill"><?= e((string)$heroAge) ?> v. (<?= e($agingSystem) ?>)</span>
      <?php endif; ?>
      <?php if ($horse['discipline_names']): ?>
        <?php
          $levelParts = [];
          if (!empty($horse['level_ko'])) $levelParts[] = 'ko: ' . $horse['level_ko'];
          if (!empty($horse['level_re'])) $levelParts[] = 're: ' . $horse['level_re'];
          if (!empty($horse['level_ke'])) $levelParts[] = 'ke: ' . $horse['level_ke'];
        ?>
        <span class="hero-pill">
          <?= e($horse['discipline_names']) ?>
          <?php if ($levelParts): ?>
            <span style="opacity:.75;font-size:.85em">(<?= e(implode(', ', $levelParts)) ?>)</span>
          <?php endif; ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
</div>

<main style="max-width:1100px;margin:0 auto;padding:0 1.5rem 3rem;">
  <div class="profile-layout">

    <!-- Pääsisältö -->
    <div class="profile-main">

      <?php if ($horse['description']): ?>
      <section>
        <h2>Kuvaus</h2>
        <p style="font-size:var(--text-base);color:var(--color-text-muted);line-height:1.75;"><?= nl2br(e($horse['description'])) ?></p>
      </section>
      <?php endif; ?>

      <!-- Kuvagalleria poistettu täältä, siirretty koko leveyteen alempana -->

    </div><!-- /.profile-main -->

    <!-- Sivupalkki -->
    <aside class="profile-sidebar">

      <div class="sidebar-card">
        <h3>Perustiedot</h3>
        <dl>
          <?php if ($horse['breed_name']): ?>
            <div class="info-row"><dt>Rotu</dt><dd><?= e($horse['breed_name']) ?></dd></div>
          <?php endif; ?>
          <div class="info-row"><dt>Sukupuoli</dt><dd><?= e($genderFi[$horse['gender']] ?? $horse['gender']) ?></dd></div>
          <?php if ($horse['birth_date']): ?>
            <?php
              $sidebarSystem = $horse['aging_system'] ?: 'IRL';
              $sidebarAge    = calculateAgeBySystem($horse['birth_date'], $sidebarSystem);
            ?>
            <div class="info-row"><dt>Syntymäaika</dt><dd><?= e(formatDate($horse['birth_date'])) ?> <span style="color:var(--color-text-muted);font-size:var(--text-sm)">(<?= e((string)$sidebarAge) ?> v., <?= e($sidebarSystem) ?>)</span></dd></div>
          <?php endif; ?>
          <?php if ($horse['color_name']): ?>
            <div class="info-row"><dt>Väri</dt><dd><?= e($horse['color_name']) ?><?php if (!empty($horse['genes'])): ?> <span style="color:var(--color-text-muted)">(<?= e($horse['genes']) ?>)</span><?php endif; ?></dd></div>
          <?php endif; ?>
          <?php if ($horse['height_cm']): ?>
            <div class="info-row"><dt>Säkäkorkeus</dt><dd><?= e((string)$horse['height_cm']) ?> cm</dd></div>
          <?php endif; ?>
          <?php if ($horse['vh_id']): ?>
            <div class="info-row"><dt>VH-tunnus</dt><dd style="font-family:var(--font-mono);font-size:var(--text-xs);"><a href="https://virtuaalihevoset.net/virtuaalihevoset/hevonen/<?= e($horse['vh_id']) ?>" target="_blank" rel="noopener"><?= e($horse['vh_id']) ?></a></dd></div>
          <?php endif; ?>
          <?php if ($horse['pkk_id']): ?>
            <div class="info-row"><dt>PKK-tunnus</dt><dd style="font-family:var(--font-mono);font-size:var(--text-xs);"><a href="https://piirroshevosille.fi/hevoset/hevonen/<?= e($horse['pkk_id']) ?>" target="_blank" rel="noopener"><?= e($horse['pkk_id']) ?></a></dd></div>
          <?php endif; ?>
          <?php if ($horse['discipline_names']): ?>
            <?php
              $levelParts = [];
              if (!empty($horse['level_ko'])) $levelParts[] = 'ko: ' . $horse['level_ko'];
              if (!empty($horse['level_re'])) $levelParts[] = 're: ' . $horse['level_re'];
            ?>
            <div class="info-row">
              <dt>Lajit</dt>
              <dd>
                <?= e($horse['discipline_names']) ?>
                <?php if ($levelParts): ?>
                  <span style="color:var(--color-text-muted)">(<?= e(implode(', ', $levelParts)) ?>)</span>
                <?php endif; ?>
              </dd>
            </div>
          <?php elseif (!empty($horse['level_ko']) || !empty($horse['level_re'])): ?>
            <?php
              $levelParts = [];
              if (!empty($horse['level_ko'])) $levelParts[] = 'ko: ' . $horse['level_ko'];
              if (!empty($horse['level_re'])) $levelParts[] = 're: ' . $horse['level_re'];
            ?>
            <div class="info-row"><dt>Taso</dt><dd><?= e(implode(', ', $levelParts)) ?></dd></div>
          <?php endif; ?>
        </dl>
      </div>

<?php
$contactRows = [];
foreach (['Omistaja' => 'owner', 'Kasvattaja' => 'breeder', 'Tuoja' => 'importer'] as $label => $prefix) {
    $nick    = $horse[$prefix.'_nickname'] ?? '';
    $stable  = $horse[$prefix.'_stable_name'] ?? '';
    $url     = $horse[$prefix.'_stable_url'] ?? '';
    $vrl     = $horse[$prefix.'_vrl_id'] ?? '';
    $email   = $horse[$prefix.'_email'] ?? '';
    $country = $horse[$prefix.'_country'] ?? '';
    if (!$nick && !$stable && !$vrl && !$email && !$country) continue;
    $contactRows[] = ['label' => $label, 'nick' => $nick, 'stable' => $stable, 'url' => $url,
                      'vrl' => $vrl, 'email' => $email, 'country' => $country];
}
if ($contactRows): ?>
      <div class="sidebar-card">
        <h3>Omistus & kasvatus</h3>
        <dl>
          <?php foreach ($contactRows as $cr): ?>
            <div class="info-row"><dt><?= $cr['label'] ?></dt><dd>
              <?php if ($cr['nick']): ?><strong><?= e($cr['nick']) ?></strong><?php endif; ?>
              <?php if ($cr['stable']): ?>
                <?php if ($cr['nick']): ?><br><?php endif; ?>
                <?php if ($cr['url']): ?><a href="<?= e($cr['url']) ?>" target="_blank" rel="noopener"><?= e($cr['stable']) ?></a><?php else: ?><?= e($cr['stable']) ?><?php endif; ?>
              <?php endif; ?>
              <?php if ($cr['vrl']): ?><br><span style="font-size:var(--text-sm)"><?= e($cr['vrl']) ?></span><?php endif; ?>
              <?php if ($cr['email']): ?><br><a href="mailto:<?= e($cr['email']) ?>" style="font-size:var(--text-sm)"><?= e($cr['email']) ?></a><?php endif; ?>
              <?php if ($cr['country']): ?><br><span style="font-size:var(--text-sm)"><?= e($cr['country']) ?></span><?php endif; ?>
            </dd></div>
          <?php endforeach; ?>
        </dl>
      </div>
      <?php endif; ?>

    </aside>

  </div><!-- /.profile-layout -->

  <!-- Sukutaulu — koko leveys -->
  <section class="pedigree profile-fullwidth">
    <h2>Sukutaulu</h2>
    <?php
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

    function pedigreeCell(?array $h, int $row): string {
        $style = 'grid-column:3; grid-row:' . $row;
        if (!$h) return '<div class="ped-cell ped-empty" style="' . $style . '">—</div>';
        return '<div class="ped-cell" style="' . $style . '">' . pedigreeHorseLink($h) . '</div>';
    }
    ?>
    <div class="ped-tree">
      <div class="ped-header">
        <div>Vanhemmat</div>
        <div>Isovanhemmat</div>
        <div>Isoisovanhemmat</div>
      </div>
      <div class="ped-grid">
        <div class="ped-cell ped-gen1" style="grid-column:1; grid-row:1/span 4"><?= $sire ? pedigreeHorseLink($sire) : '—' ?></div>
        <div class="ped-cell ped-gen1" style="grid-column:1; grid-row:5/span 4"><?= $dam  ? pedigreeHorseLink($dam)  : '—' ?></div>
        <div class="ped-cell ped-gen2" style="grid-column:2; grid-row:1/span 2"><?= $ss ? pedigreeHorseLink($ss) : '—' ?></div>
        <div class="ped-cell ped-gen2" style="grid-column:2; grid-row:3/span 2"><?= $sd ? pedigreeHorseLink($sd) : '—' ?></div>
        <div class="ped-cell ped-gen2" style="grid-column:2; grid-row:5/span 2"><?= $ds ? pedigreeHorseLink($ds) : '—' ?></div>
        <div class="ped-cell ped-gen2" style="grid-column:2; grid-row:7/span 2"><?= $dd ? pedigreeHorseLink($dd) : '—' ?></div>
        <?= pedigreeCell($sss, 1) ?>
        <?= pedigreeCell($ssd, 2) ?>
        <?= pedigreeCell($sds, 3) ?>
        <?= pedigreeCell($sdd, 4) ?>
        <?= pedigreeCell($dss, 5) ?>
        <?= pedigreeCell($dsd, 6) ?>
        <?= pedigreeCell($dds, 7) ?>
        <?= pedigreeCell($ddd, 8) ?>
      </div>
    </div>
    <?php if ($horse['pedigree_notes']): ?>
      <p style="margin-top:.75rem;font-size:var(--text-sm);color:var(--color-text-muted);"><?= nl2br(e($horse['pedigree_notes'])) ?></p>
    <?php endif; ?>
  </section>

  <!-- Varsat — koko leveys -->
  <?php if (!empty($foals)): ?>
  <section class="profile-fullwidth">
    <h2>Varsat</h2>
    <div class="comp-list">
      <div class="comp-header" style="grid-template-columns:120px 2fr 130px 80px 2fr 2fr 2fr">
        <div>Rotu</div><div>Varsan nimi</div><div>Syntymäpäivä</div><div>Tilanne</div><div>i/e. Vanhempi</div><div>Omistaja</div><div>Meriitit</div>
      </div>
      <?php foreach ($foals as $f):
        // Rotu + sukupuoli
        $breedGender = '';
        if ($f['breed_abbr']) $breedGender .= e($f['breed_abbr']);
        if ($f['gender'] && $f['gender'] !== 'tuntematon') {
            $breedGender .= ($breedGender ? '-' : '') . e($f['gender']);
        }

        // i/e — näytetään se vanhempi joka EI ole tämä hevonen
        if ((int)$f['sire_id'] === $id) {
            // Tämä hevonen on isä → näytetään emä
            $otherLabel = 'e.';
            $otherName  = $f['dam_name']  ?? null;
            $otherSlug  = $f['dam_slug']  ?? null;
        } else {
            // Tämä hevonen on emä → näytetään isä
            $otherLabel = 'i.';
            $otherName  = $f['sire_name'] ?? null;
            $otherSlug  = $f['sire_slug'] ?? null;
        }

        // Syntymäpäivä ja tilanne
        $birthStr  = $f['birth_date'] ? 's. ' . date('d.m.Y', strtotime($f['birth_date'])) : '—';
        $statusStr = $f['status'] === 'expected' ? 'Odotettu' : 'Syntynyt';

        // Omistaja
        $ownerStr = '';
        if ($f['owner_nickname']) {
            if ($f['owner_email']) {
                $ownerStr .= '<a href="mailto:' . e($f['owner_email']) . '">' . e($f['owner_nickname']) . '</a>';
            } else {
                $ownerStr .= e($f['owner_nickname']);
            }
        }
        if ($f['owner_vrl'])  $ownerStr .= ($ownerStr ? ' ' : '') . '(' . e($f['owner_vrl']) . ')';
        if ($ownerStr)        $ownerStr = 'om. ' . $ownerStr;
      ?>
      <div class="comp-row" style="grid-template-columns:120px 2fr 130px 80px 2fr 2fr 2fr">
        <div class="comp-cell cl-mono" style="font-size:var(--text-sm)"><?= $breedGender ?: '—' ?></div>
        <div class="comp-cell">
          <?php if ($f['foal_horse_id']): ?>
            <?php $foalUrl = $f['foal_horse_slug'] ? horseUrl(['slug' => $f['foal_horse_slug']]) : SITE_URL . '/pages/hevonen.php?id=' . (int)$f['foal_horse_id']; ?>
            <a href="<?= e($foalUrl) ?>"><strong><?= e($f['foal_name'] ?? '—') ?></strong></a>
          <?php else: ?>
            <strong><?= e($f['foal_name'] ?? '—') ?></strong>
          <?php endif; ?>
        </div>
        <div class="comp-cell cl-mono" style="font-size:var(--text-sm)"><?= $birthStr ?></div>
        <div class="comp-cell" style="font-size:var(--text-sm)"><?= e($statusStr) ?></div>
        <div class="comp-cell">
          <?php if ($otherName): ?>
            <?= e($otherLabel) ?>
            <?php if ($otherSlug): ?>
              <a href="<?= e(SITE_URL) ?>/pages/horse/<?= e(rawurlencode($otherSlug)) ?>"><?= e($otherName) ?></a>
            <?php else: ?>
              <?= e($otherName) ?>
            <?php endif; ?>
          <?php else: ?>—<?php endif; ?>
        </div>
        <div class="comp-cell" style="font-size:var(--text-sm)"><?= $ownerStr ?: '—' ?></div>
        <div class="comp-cell" style="font-size:var(--text-sm)"><?= $f['merits'] ? nl2br(e($f['merits'])) : '—' ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Kisakalenteri — koko leveys -->
  <section class="profile-fullwidth">
    <h2>Kisakalenteri</h2>
    <?php if (empty($competitions)): ?>
      <p style="color:var(--color-text-muted);font-family:var(--font-sans);font-size:var(--text-sm);">Ei kilpailutuloksia.</p>
    <?php else: ?>
      <div class="comp-list">
        <div class="comp-header">
          <div>PVM</div>
          <div>Laji</div>
          <div>Maa</div>
          <div>Järjestäjä</div>
          <div>Luokka</div>
          <div>Tulos</div>
          <div>Pisteet</div>
          <div>Huom</div>
        </div>
        <?php foreach ($competitions as $comp): ?>
          <div class="comp-row">
            <div class="comp-cell" data-label="PVM"><?= e(formatDate($comp['competition_date'])) ?></div>
            <div class="comp-cell" data-label="Laji"><?= e($comp['discipline'] ?? '—') ?></div>
            <div class="comp-cell" data-label="Maa"><?= e($comp['country'] ?? '—') ?></div>
            <div class="comp-cell" data-label="Järjestäjä">
              <?php if (!empty($comp['organizer_url'])): ?>
                <a href="<?= e($comp['organizer_url']) ?>" target="_blank" rel="noopener"><?= e($comp['organizer'] ?? '—') ?></a>
              <?php else: ?>
                <?= e($comp['organizer'] ?? '—') ?>
              <?php endif; ?>
            </div>
            <div class="comp-cell" data-label="Luokka"><?= e($comp['class'] ?? '—') ?></div>
            <div class="comp-cell" data-label="Tulos"><?= e($comp['placement'] ?? '—') ?></div>
            <div class="comp-cell" data-label="Pisteet"><?= $comp['points'] !== null ? e((string)$comp['points']) : '—' ?></div>
            <div class="comp-cell" data-label="Huom"><?= e($comp['notes'] ?? '') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Näyttelytulokset — koko leveys -->
  <section class="profile-fullwidth">
    <h2>Näyttelytulokset</h2>
    <?php if (empty($showrecords)): ?>
      <p style="color:var(--color-text-muted);font-family:var(--font-sans);font-size:var(--text-sm);">Ei näyttelytuloksia.</p>
    <?php else: ?>
      <div class="comp-list">
        <div class="comp-header">
          <div>PVM</div>
          <div>Laji</div>
          <div>Maa</div>
          <div>Järjestäjä</div>
          <div>Luokka</div>
          <div>Tulos</div>
          <div>Pisteet</div>
          <div>Tuomari</div>
          <div>Arvostelu</div>
        </div>
        <?php foreach ($showrecords as $s):
          $judgeLabel = trim(($s['judge_nickname'] ?? '') . ' ' . ($s['judge_stable_name'] ?? ''));
          $judgeHtml  = $judgeLabel !== '' && !empty($s['judge_email'])
              ? '<a href="mailto:' . e($s['judge_email']) . '">' . e($judgeLabel) . '</a>'
              : e($judgeLabel);
        ?>
          <div class="comp-row">
            <div class="comp-cell" data-label="PVM"><?= e(formatDate($s['show_date'])) ?></div>
            <div class="comp-cell" data-label="Laji"><?= e($s['discipline'] ?? '—') ?></div>
            <div class="comp-cell" data-label="Maa"><?= e($s['country'] ?? '—') ?></div>
            <div class="comp-cell" data-label="Järjestäjä">
              <?php if (!empty($s['photo_filename'])): ?>
                <img src="<?= e(UPLOADS_URL . $s['photo_filename']) ?>" alt="<?= e($s['photo_title'] ?? $s['photo_original_name'] ?? '') ?>"
                     style="width:28px;height:28px;object-fit:cover;border-radius:4px;border:1px solid var(--color-border-warm);vertical-align:middle;margin-right:.35rem;">
              <?php endif; ?>
              <?php if (!empty($s['organizer_url'])): ?>
                <a href="<?= e($s['organizer_url']) ?>" target="_blank" rel="noopener"><?= e($s['organizer'] ?? '—') ?></a>
              <?php else: ?>
                <?= e($s['organizer'] ?? '—') ?>
              <?php endif; ?>
            </div>
            <div class="comp-cell" data-label="Luokka"><?= e($s['class'] ?? '—') ?></div>
            <div class="comp-cell" data-label="Tulos"><?= e($s['placement'] ?? '—') ?></div>
            <div class="comp-cell" data-label="Pisteet"><?= $s['points'] !== null ? e((string)$s['points']) : '—' ?></div>
            <div class="comp-cell" data-label="Tuomari"><?= $judgeLabel !== '' ? $judgeHtml : '—' ?></div>
            <div class="comp-cell" data-label="Arvostelu"><?= $s['review'] ? nl2br(e($s['review'])) : '—' ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Postaukset — koko leveys -->
  <?php if (!empty($horsePosts)): ?>
  <section class="profile-fullwidth">
    <h2>Postaukset</h2>
    <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.6rem;">
      <?php foreach ($horsePosts as $hp): ?>
        <li>
          <a href="<?= e(SITE_URL) ?>/pages/postaus.php?slug=<?= rawurlencode($hp['slug']) ?>"
             style="font-weight:600;"><?= e($hp['title']) ?></a>
          <span style="color:var(--color-text-muted);font-size:var(--text-sm);margin-left:.5rem;"><?= e(formatDate($hp['created_at'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <!-- Kuvagalleria — koko leveys -->
  <section class="profile-fullwidth">
    <h2>Kuvagalleria</h2>
    <div class="gallery">
      <?php if (!empty($photos)): ?>
        <?php foreach ($photos as $photo): ?>
          <?php
            $altText  = e($photo['title'] ?? $photo['original_name'] ?? $horse['name']);
            $captionJ = htmlspecialchars($photo['caption'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $titleJ   = htmlspecialchars($photo['title']   ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
          ?>
          <div class="gallery-item" onclick="openLightbox(this)" title="Avaa suuremmaksi"
               data-caption="<?= $captionJ ?>" data-title="<?= $titleJ ?>">
            <img src="<?= e(UPLOADS_URL . $photo['filename']) ?>" alt="<?= $altText ?>">
            <?php if (!empty($photo['title'])): ?>
              <div class="gallery-item-label"><?= e($photo['title']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($i = 0; $i < 4; $i++): ?>
          <div class="gallery-item gallery-placeholder">
            <span>Ei kuvaa</span>
          </div>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- Lightbox -->
  <div id="lightbox" class="lightbox" onclick="closeLightbox()" style="display:none">
    <button class="lightbox-close" onclick="closeLightbox()" aria-label="Sulje">&times;</button>
    <div class="lightbox-inner" onclick="event.stopPropagation()">
      <img id="lightbox-img" src="" alt="">
      <div id="lightbox-caption" class="lightbox-caption" style="display:none">
        <strong id="lightbox-title"></strong>
        <span id="lightbox-text"></span>
      </div>
    </div>
  </div>
  <script>
  function openLightbox(el) {
    var img     = el.querySelector('img');
    var title   = el.dataset.title   || '';
    var caption = el.dataset.caption || '';
    document.getElementById('lightbox-img').src = img.src;
    document.getElementById('lightbox-img').alt = img.alt;
    document.getElementById('lightbox-title').textContent   = title;
    document.getElementById('lightbox-text').textContent    = caption;
    var capEl = document.getElementById('lightbox-caption');
    capEl.style.display = (title || caption) ? 'block' : 'none';
    document.getElementById('lightbox').style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
    document.body.style.overflow = '';
  }
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLightbox(); });
  </script>
</main>
<?php require __DIR__ . '/../src/includes/footer.php'; ?>
