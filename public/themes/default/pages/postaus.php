<?php
// Suomalaiset kuukaudet — ei strftime() (deprecated PHP 8.1+)
$MONTHS_FI = [
    1=>'Tammikuu', 2=>'Helmikuu', 3=>'Maaliskuu', 4=>'Huhtikuu',
    5=>'Toukokuu', 6=>'Kesäkuu',  7=>'Heinäkuu',  8=>'Elokuu',
    9=>'Syyskuu', 10=>'Lokakuu', 11=>'Marraskuu', 12=>'Joulukuu'
];

require __DIR__ . '/../includes/header.php';
?>

<div class="page-title-band">
  <h1><?= e($post['title']) ?></h1>
  <div class="breadcrumb">Etusivu › <a href="<?= e(SITE_URL) ?>/pages/ajankohtaista.php">Ajankohtaista</a> › <?= e($post['title']) ?></div>
</div>

<main class="container" style="padding: 2rem 1rem;">
  <div class="post-layout">

    <!-- Artikkeli -->
    <article>
      <span class="post-article__date"><?= formatDate($post['created_at']) ?></span>
      <div class="post-body">
        <?= nl2br(e($post['content'])) ?>
      </div>
    </article>

    <!-- Sticky sidebar -->
    <?php
      $postYear  = (int)date('Y', strtotime($post['created_at']));
      $postMonth = (int)date('n', strtotime($post['created_at']));
    ?>
    <aside class="post-sidebar">

      <!-- Prev/next -->
      <nav class="post-prevnext" aria-label="Postausnavigaatio">
        <div class="post-prevnext__label">Navigointi</div>
        <div class="post-prevnext__links">
          <?php if ($prev): ?>
            <a class="prev" href="<?= e(SITE_URL) ?>/pages/ajankohtaista/<?= rawurlencode($prev['slug']) ?>">
              ← <?= e($prev['title']) ?>
            </a>
          <?php endif; ?>
          <?php if ($next): ?>
            <a class="next" href="<?= e(SITE_URL) ?>/pages/ajankohtaista/<?= rawurlencode($next['slug']) ?>">
              <?= e($next['title']) ?> →
            </a>
          <?php endif; ?>
          <?php if (!$prev && !$next): ?>
            <span style="color:var(--color-muted);font-size:var(--text-sm)">Ei muita postauksia</span>
          <?php endif; ?>
        </div>
      </nav>

      <!-- Arkisto -->
      <div class="archive-sidebar">
        <h2 class="archive-sidebar__header">🗓 Arkisto</h2>
        <?php foreach ($archive as $yr => $months): ?>
          <details<?= ((int)$yr === $postYear) ? ' open' : '' ?>>
            <summary><?= (int)$yr ?></summary>
            <ul class="archive-sidebar__months">
              <?php foreach ($months as $mo => $cnt):
                $active = ((int)$yr === $postYear && (int)$mo === $postMonth);
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
