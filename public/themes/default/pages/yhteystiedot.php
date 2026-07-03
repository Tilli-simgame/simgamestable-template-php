<?php
$page_title = 'Yhteystiedot';

require __DIR__ . '/../includes/header.php';
?>

<div class="page-title-band">
  <h1>Yhteystiedot</h1>
  <div class="breadcrumb">Etusivu › Yhteystiedot</div>
</div>

<main>
  <div class="contact-card">
    <div class="contact-card-icon">👤</div>
    <h2><?= e($stable_name) ?></h2>
    <?php if ($vrl_id !== ''): ?>
      <div class="vrl-badge"><?= e($vrl_id) ?></div>
    <?php endif; ?>

    <hr class="contact-divider">

    <?php if ($email !== ''): ?>
    <div class="contact-row">
      <div class="contact-icon">✉️</div>
      <div>
        <div class="contact-label">Sähköposti</div>
        <div class="contact-value">
          <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($nickname !== ''): ?>
    <div class="contact-row">
      <div class="contact-icon">🏷️</div>
      <div>
        <div class="contact-label">Nimimerkki</div>
        <div class="contact-value"><?= e($nickname) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($vrl_id !== ''): ?>
    <div class="contact-row">
      <div class="contact-icon">🔖</div>
      <div>
        <div class="contact-label">VRL-tunnus</div>
        <div class="contact-value"><span class="mono"><?= e($vrl_id) ?></span></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($forum_url !== ''): ?>
    <div class="contact-row">
      <div class="contact-icon">💬</div>
      <div>
        <div class="contact-label">Foorumi</div>
        <div class="contact-value">
          <a href="<?= e($forum_url) ?>" target="_blank" rel="noopener noreferrer">Profiili</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($email !== ''): ?>
    <div style="margin-top:1.75rem;">
      <a class="btn" href="mailto:<?= e($email) ?>">✉ Lähetä sähköpostia</a>
    </div>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
