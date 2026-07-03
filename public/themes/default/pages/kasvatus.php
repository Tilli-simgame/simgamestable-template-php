<?php
$page_title = 'Kasvatus';

require __DIR__ . '/../includes/header.php';

$genderFi = ['ori' => 'Ori', 'tamma' => 'Tamma', 'ruuna' => 'Ruuna', 'tuntematon' => ''];
?>

<div class="page-title-band">
  <h1>Kasvatus</h1>
  <div class="breadcrumb">Etusivu › Kasvatus</div>
</div>

<main style="max-width:900px;margin:0 auto;padding:0 1.5rem 3rem;">

  <?php if (empty($allFoals)): ?>
    <p style="color:var(--color-text-muted);font-family:var(--font-sans);">Tallissa ei ole vielä kasvatustietoja.</p>
  <?php else: ?>

    <!-- Suodatuspainikkeet -->
    <div class="filter-bar" style="margin-bottom:1.5rem;">
      <label>Näytä:</label>
      <button class="filter-btn active" data-filter="kaikki">Kaikki</button>
      <button class="filter-btn" data-filter="expected">Odotetut</button>
      <button class="filter-btn" data-filter="born">Syntyneet</button>
    </div>

    <?php if (!empty($expected)): ?>
      <div class="foal-section" data-section="expected">
        <div class="foal-section-header expected">
          <h2>Odotetut varsat</h2>
          <span class="section-count"><?= count($expected) ?></span>
        </div>
        <div class="foal-list">
          <?php foreach ($expected as $foal): ?>
            <div class="foal-card" data-status="expected">
              <div class="foal-thumb">
                <div class="no-photo">🐴</div>
              </div>
              <div class="foal-info">
                <?php if ($foal['foal_name']): ?>
                  <span class="foal-name"><?= e($foal['foal_name']) ?></span>
                <?php else: ?>
                  <span class="foal-unnamed">Nimetön varsa</span>
                <?php endif; ?>
                <span class="foal-parents">
                  <?php if ($foal['sire_name']): ?>
                    <a href="<?= e(SITE_URL . '/pages/horse/' . rawurlencode($foal['sire_slug'] ?? $foal['sire_name'])) ?>"><?= e($foal['sire_name']) ?></a>
                  <?php else: ?>—<?php endif; ?>
                  ×
                  <?php if ($foal['dam_name']): ?>
                    <a href="<?= e(SITE_URL . '/pages/horse/' . rawurlencode($foal['dam_slug'] ?? $foal['dam_name'])) ?>"><?= e($foal['dam_name']) ?></a>
                  <?php else: ?>—<?php endif; ?>
                </span>
                <?php if ($foal['birth_date']): ?>
                  <span class="foal-year"><?= e(date('d.m.Y', strtotime($foal['birth_date']))) ?></span>
                <?php endif; ?>
              </div>
              <div class="foal-status"><span class="status-expected">Odotettu</span></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($born)): ?>
      <div class="foal-section" data-section="born">
        <div class="foal-section-header">
          <h2>Syntyneet varsat</h2>
          <span class="section-count"><?= count($born) ?></span>
        </div>
        <div class="foal-list">
          <?php foreach ($born as $foal): ?>
            <div class="foal-card" data-status="born">
              <div class="foal-thumb">
                <div class="no-photo">🐴</div>
              </div>
              <div class="foal-info">
                <?php if ($foal['foal_name']): ?>
                  <span class="foal-name"><?= e($foal['foal_name']) ?></span>
                <?php else: ?>
                  <span class="foal-unnamed">Nimetön</span>
                <?php endif; ?>
                <span class="foal-parents">
                  <?php if ($foal['sire_name']): ?>
                    <a href="<?= e(SITE_URL . '/pages/horse/' . rawurlencode($foal['sire_slug'] ?? $foal['sire_name'])) ?>"><?= e($foal['sire_name']) ?></a>
                  <?php else: ?>—<?php endif; ?>
                  ×
                  <?php if ($foal['dam_name']): ?>
                    <a href="<?= e(SITE_URL . '/pages/horse/' . rawurlencode($foal['dam_slug'] ?? $foal['dam_name'])) ?>"><?= e($foal['dam_name']) ?></a>
                  <?php else: ?>—<?php endif; ?>
                </span>
                <?php if ($foal['birth_date']): ?>
                  <span class="foal-year"><?= e(date('d.m.Y', strtotime($foal['birth_date']))) ?></span>
                <?php endif; ?>
                <?php if (!empty($foal['gender'])): ?>
                  <span class="foal-year"><?= e($genderFi[$foal['gender']] ?? $foal['gender']) ?></span>
                <?php endif; ?>
              </div>
              <div class="foal-status"><span class="status-born">Syntynyt</span></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</main>

<script>
document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const filter = this.dataset.filter;
    document.querySelectorAll('.foal-section').forEach(sec => {
      if (filter === 'kaikki' || sec.dataset.section === filter) {
        sec.style.display = '';
      } else {
        sec.style.display = 'none';
      }
    });
  });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
