<?php
$page_title = 'Hevoset';

require __DIR__ . '/../includes/header.php';

$genderFi = ['ori' => 'Ori', 'tamma' => 'Tamma', 'ruuna' => 'Ruuna', 'käkky' => 'Käkky'];
?>

<div class="page-title-band">
  <h1>Tallin hevoset</h1>
  <div class="breadcrumb">Etusivu › Hevoset</div>
</div>

<main>
  <?php if (empty($horses)): ?>
    <p style="color:var(--color-text-muted);font-family:var(--font-sans);">Tallissa ei ole vielä hevosia.</p>
  <?php else: ?>

    <!-- Suodatuspainikkeet -->
    <div class="filter-bar">
      <label>Sukupuoli:</label>
      <button class="filter-btn active" data-filter="kaikki">Kaikki</button>
      <button class="filter-btn" data-filter="tamma">Tammat</button>
      <button class="filter-btn" data-filter="ori">Oriit</button>
      <button class="filter-btn" data-filter="ruuna">Ruunat</button>
      <button class="filter-btn" data-filter="käkky">Käkyt</button>
    </div>

    <!-- Listakorteista -->
    <div class="horse-list" id="horse-list">
      <?php foreach ($horses as $horse): ?>
        <div class="horse-list-card" data-gender="<?= e($horse['gender']) ?>">
          <?php if ($horse['filename']): ?>
            <div class="card-img">
              <img src="<?= e(UPLOADS_URL . $horse['filename']) ?>" alt="<?= e($horse['name']) ?>">
            </div>
          <?php else: ?>
            <div class="card-img">🐴</div>
          <?php endif; ?>
          <div class="card-body">
            <h3><a href="<?= e(horseUrl($horse)) ?>"><?= e($horse['name']) ?></a></h3>
            <div class="meta-row">
              <?php if ($horse['breed_name']): ?><span><?= e($horse['breed_name']) ?></span><?php endif; ?>
              <span><?= e($genderFi[$horse['gender']] ?? $horse['gender']) ?></span>
              <?php if ($horse['birth_date']): ?>
                <span><?= e((string)calculateAge($horse['birth_date'])) ?> v.</span>
              <?php endif; ?>
            </div>
            <?php if ($horse['discipline_names']): ?>
              <span class="card-tag"><?= e($horse['discipline_names']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</main>

<script>
document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const filter = this.dataset.filter;
    document.querySelectorAll('.horse-list-card').forEach(card => {
      card.style.display = (filter === 'kaikki' || card.dataset.gender === filter) ? '' : 'none';
    });
  });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
