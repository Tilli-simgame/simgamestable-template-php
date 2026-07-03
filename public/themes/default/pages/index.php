<?php
$page_title = 'Etusivu';

require __DIR__ . '/../includes/header.php';
?>

<!-- Hero -->
<div class="frontpage-hero">
  <div class="frontpage-hero-content">
    <h1>Tervetuloa!</h1>
    <p>Täällä asuvat rakkaimmat hevosemme. Tutustu talliimme ja sen asukkaisiin!</p>
  </div>
</div>

<!-- Overlay-kortit -->
<div class="overlay-cards">
  <a class="overlay-card" href="<?= e(SITE_URL) ?>/pages/hevoset.php">
    <img src="https://picsum.photos/seed/horses1/320/160" alt="Hevoset">
    <div class="overlay-card-stat" id="stat-hevoset"><?= $horseCount ?></div>
    <h3>Hevosta tallissa</h3>
    <p>Tammoja, oriita ja kasvavaa nuorta sukupolvea.</p>
    <span class="overlay-card-link">Katso kaikki →</span>
  </a>

  <a class="overlay-card" href="<?= e(SITE_URL) ?>/pages/kasvatus.php">
    <img src="https://picsum.photos/seed/foal2024/320/160" alt="Varsat">
    <div class="overlay-card-stat" id="stat-varsat"><?= $foalCount ?></div>
    <h3>Varsaa <?= $thisYear ?></h3>
    <p>Syntyneet ja odotetut varsat — katso kasvatusohjelma.</p>
    <span class="overlay-card-link">Kasvatussivu →</span>
  </a>

  <?php
  // CR-02: Build $newsHref without calling e() here — escape at the single output
  // point (line below) to avoid double-encoding. rawurlencode() does not encode
  // `"` or `>`, so the assembled string MUST be passed through e() before output.
  $newsHref = $latestPost
      ? SITE_URL . '/pages/ajankohtaista/' . rawurlencode($latestPost['slug'])
      : SITE_URL . '/pages/ajankohtaista.php';
  $newsTitle   = $latestPost ? e($latestPost['title']) : 'Ajankohtaista';
  $newsExcerpt = $latestPost
      ? e(mb_substr($latestPost['content'], 0, 120, 'UTF-8')) . '…'
      : 'Lue uusimmat kuulumiset tallin blogista.';
  $newsDate    = $latestPost ? formatDate($latestPost['created_at']) : '';
  ?>
  <a class="overlay-card overlay-card--news" href="<?= e($newsHref) ?>">
    <img src="https://picsum.photos/seed/winter1/320/160" alt="Ajankohtaista">
    <div class="uutinen-tag" style="margin-bottom:.5rem;">📰 Ajankohtaista</div>
    <h3><?= $newsTitle ?></h3>
    <p><?= $newsExcerpt ?></p>
    <div class="uutinen-footer" style="margin-top:auto;padding-top:.75rem;">
      <span class="card-date"><?= e($newsDate) ?></span>
      <span class="overlay-card-link">Lue lisää →</span>
    </div>
  </a>
</div>

<!-- Esittely -->
<div class="frontpage-content">
  <div class="frontpage-esittely">
    <img src="https://picsum.photos/seed/barn2/800/200" alt="Talli">
    <h2>Tietoa tallista</h2>
    <p><?= e(SITE_NAME) ?> Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec semper scelerisque sem, et consectetur diam. Nam eros ipsum, dapibus sed odio eget, ullamcorper euismod eros. Nulla ut purus eros. Mauris sit amet faucibus ex. Nam tincidunt eros in sapien tincidunt, vel condimentum ipsum sagittis. Etiam at vehicula tellus. Proin bibendum ligula vitae nibh bibendum, non pharetra erat lacinia. Phasellus scelerisque tristique urna vitae bibendum. In bibendum, quam sed interdum euismod, nulla mauris mattis nisl, vitae aliquam ex felis eu odio. Vestibulum sed quam luctus eros facilisis aliquet a quis nisi.</p>

<p>Nulla finibus nisl at ipsum condimentum placerat. Vivamus vel vehicula orci, id tincidunt ante. Integer vestibulum dui augue, ac condimentum arcu pharetra sit amet. Nunc interdum odio sit amet vulputate convallis. Suspendisse rhoncus mauris id odio fermentum varius. Nam feugiat, arcu vel dignissim varius, metus massa lacinia sapien, eget convallis ligula nulla ac ex. Suspendisse vitae leo sed odio lobortis vulputate. Integer sit amet metus in velit sagittis mollis a non ipsum. Phasellus vitae leo non mauris vehicula pretium. Pellentesque dui orci, dapibus sed mauris eu, scelerisque scelerisque nisl.</p>

<p>Proin vehicula ex massa, vel pretium mi posuere ac. Praesent vel lorem feugiat, facilisis urna quis, tincidunt tortor. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Sed et ex hendrerit, efficitur odio et, faucibus sem. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Donec efficitur metus elit, nec pellentesque velit iaculis id. Sed quam lacus, euismod in massa id, placerat hendrerit nisi. Duis ex lectus, placerat nec aliquet eu, lobortis vel est. Nullam ut auctor velit, eget gravida velit. Pellentesque fermentum leo vitae arcu pellentesque, a scelerisque purus dictum. Duis nec erat id augue placerat ornare. Etiam sagittis eleifend lacus, dapibus luctus lectus dignissim sit amet. Nam eu pretium ante, ac iaculis risus. Maecenas orci velit, tincidunt id lacinia eget, blandit a dolor.</p>
  </div>

</div>

<script>
function animateCount(el, target, duration) {
  if (!el || target === 0) return;
  let start = 0;
  const step = target / (duration / 16);
  const timer = setInterval(() => {
    start = Math.min(start + step, target);
    el.textContent = Math.round(start);
    if (start >= target) clearInterval(timer);
  }, 16);
}
setTimeout(() => {
  animateCount(document.getElementById('stat-hevoset'), <?= json_encode($horseCount) ?>, 800);
  animateCount(document.getElementById('stat-varsat'),  <?= json_encode($foalCount) ?>,  600);
}, 150);
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
