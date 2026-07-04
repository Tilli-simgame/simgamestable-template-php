<?php
// Suomalaiset kuukaudet — ei strftime() (deprecated PHP 8.1+)
$MONTHS_FI = [
    1=>'Tammikuu', 2=>'Helmikuu', 3=>'Maaliskuu', 4=>'Huhtikuu',
    5=>'Toukokuu', 6=>'Kesäkuu',  7=>'Heinäkuu',  8=>'Elokuu',
    9=>'Syyskuu', 10=>'Lokakuu', 11=>'Marraskuu', 12=>'Joulukuu'
];

$page_title = 'Ajankohtaista';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-title-band">
  <h1>Ajankohtaista</h1>
  <div class="breadcrumb">Etusivu › Ajankohtaista</div>
</div>

<main class="container" style="padding: 2rem 1rem;">
  <div class="post-layout">

    <!-- Postauslista -->
    <div>
      <?php if ($yearFilter > 0 && $monthFilter > 0): ?>
        <p style="margin:.75rem 0 1.25rem;">
          Näytetään: <?= e($MONTHS_FI[$monthFilter] ?? (string)$monthFilter) ?> <?= $yearFilter ?>
          — <a href="ajankohtaista.php">Näytä kaikki</a>
        </p>
      <?php elseif ($yearFilter > 0): ?>
        <p style="margin:.75rem 0 1.25rem;">
          Näytetään: <?= $yearFilter ?>
          — <a href="ajankohtaista.php">Näytä kaikki</a>
        </p>
      <?php endif; ?>

      <?php if (empty($posts)): ?>
        <p>Ei vielä postauksia.</p>
      <?php else: ?>
        <ul class="post-list">
          <?php foreach ($posts as $post):
            $excerpt = mb_substr($post['content'], 0, 200, 'UTF-8');
            if (mb_strlen($post['content'], 'UTF-8') > 200) $excerpt .= '…';
          ?>
          <li>
            <a class="post-list-card"
               href="<?= e(SITE_URL) ?>/pages/ajankohtaista/<?= rawurlencode($post['slug']) ?>">
              <div class="post-list-card__body">
                <h2 class="post-list-card__title"><?= e($post['title']) ?></h2>
                <span class="post-list-card__date"><?= formatDate($post['created_at']) ?></span>
                <p class="post-list-card__excerpt"><?= e($excerpt) ?></p>
              </div>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Sticky sidebar -->
    <?php $firstYr = array_key_first($archive); ?>
    <aside class="post-sidebar">
      <div class="archive-sidebar">
        <h2 class="archive-sidebar__header">🗓 Arkisto</h2>
        <?php foreach ($archive as $yr => $months): ?>
          <details<?= ((int)$yr === (int)$firstYr) ? ' open' : '' ?>>
            <summary><?= (int)$yr ?></summary>
            <ul class="archive-sidebar__months">
              <?php foreach ($months as $mo => $cnt):
                $active = ($yearFilter === (int)$yr && $monthFilter === (int)$mo);
              ?>
                <li>
                  <a href="<?= e(SITE_URL) ?>/pages/ajankohtaista.php?year=<?= (int)$yr ?>&amp;month=<?= (int)$mo ?>"
                     class="<?= $active ? 'active' : '' ?>">
                    <?= e($MONTHS_FI[$mo] ?? (string)$mo) ?>
                    <span class="archive-sidebar__cnt"><?= (int)$cnt ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endforeach; ?>
        <?php if (empty($archive)): ?>
          <p style="padding:.75rem 1rem;color:var(--color-cream);font-size:var(--text-sm);">Ei postauksia.</p>
        <?php endif; ?>
      </div>
    </aside>

  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
