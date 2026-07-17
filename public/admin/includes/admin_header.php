<?php
// Varmista että config on ladattu
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../../src/includes/config.php';
}
// Tunnista aktiivinen sivu URL:n perusteella
$_activePage = basename($_SERVER['PHP_SELF'], '.php');
// Haetaan väriteema (getDB() on jo alustettu kutsuvan sivun require:ssa)
if (!isset($GLOBALS['color_theme'])) {
    try {
        $db = getDB();
        $t = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'color_theme' LIMIT 1")->fetchColumn();
        $GLOBALS['color_theme'] = ($t !== false && $t !== '') ? $t : 'savi';
    } catch (Exception $e) {
        $GLOBALS['color_theme'] = 'savi';
    }
}
$_adminTheme = $GLOBALS['color_theme'];
?>
<!DOCTYPE html>
<html lang="fi" data-theme="<?= e($_adminTheme) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Admin — <?= e(SITE_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(SITE_URL) ?>/assets/css/style.css">
  <style>
    /* ── RESET & BASE ──────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: var(--font-sans, 'Segoe UI', system-ui, sans-serif); font-size: 0.875rem; background: var(--color-bg, #f9f7f4); color: var(--color-text, #2c2c2c); }

    /* ── SHELL ─────────────────────────────────── */
    .admin-shell { display: flex; min-height: 100vh; }

    /* ── SIDEBAR ───────────────────────────────── */
    .admin-sidebar {
      width: 220px; flex-shrink: 0;
      background: var(--color-primary, #3d2b1f);
      display: flex; flex-direction: column;
      position: sticky; top: 0; height: 100vh;
      overflow-y: auto;
    }
    .admin-sidebar-logo {
      padding: 1rem 1.5rem 1.25rem;
      border-bottom: 1px solid rgba(201,168,76,0.25);
      margin-bottom: 1rem;
    }
    .admin-sidebar-logo .logo-text {
      font-family: var(--font-serif, Georgia, serif);
      font-size: 1.05rem; color: var(--color-cream, #e8d5b7);
    }
    .admin-sidebar-logo .logo-sub {
      font-size: 0.7rem; color: var(--color-gold, #c9a84c);
      opacity: 0.8; margin-top: 2px;
    }
    .admin-nav-section {
      font-size: 0.65rem; color: rgba(201,168,76,0.5);
      padding: 0.75rem 1.5rem 0.4rem; text-transform: uppercase;
      letter-spacing: 0.08em; margin-top: 0.5rem;
    }
    .admin-nav-item {
      display: flex; align-items: center; gap: 0.5rem;
      padding: 0.55rem 1.5rem;
      color: rgba(232,213,183,0.75); font-size: 0.875rem;
      text-decoration: none; transition: all 0.15s ease;
    }
    .admin-nav-item:hover { color: var(--color-cream, #e8d5b7); background: rgba(255,255,255,0.06); }
    .admin-nav-item.active { color: var(--color-gold, #c9a84c); background: rgba(201,168,76,0.12); border-right: 3px solid var(--color-gold, #c9a84c); }
    .admin-sidebar-footer {
      margin-top: auto; padding: 1rem 1.5rem;
      border-top: 1px solid rgba(201,168,76,0.2);
    }
    .admin-sidebar-footer .sb-username { color: var(--color-cream, #e8d5b7); font-size: 0.75rem; }
    .admin-sidebar-footer .sb-logout-btn {
      background: none; border: none; padding: 0;
      color: var(--color-gold, #c9a84c); font-size: 0.75rem;
      opacity: 0.7; cursor: pointer; font-family: inherit;
      text-align: left;
    }
    .admin-sidebar-footer .sb-logout-btn:hover { opacity: 1; }

    /* ── MAIN AREA ─────────────────────────────── */
    .admin-main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow-y: auto; }
    .admin-page-header {
      background: var(--color-surface, #fff);
      border-bottom: 1px solid var(--color-border, #e0d5c5);
      padding: 1rem 2rem; display: flex; align-items: center; gap: 1rem;
      position: sticky; top: 0; z-index: 10;
    }
    .admin-page-header h1 {
      font-family: var(--font-serif, Georgia, serif);
      font-size: 1.4rem; color: var(--color-primary, #3d2b1f);
      font-weight: normal; margin: 0;
    }
    .admin-page-header .page-actions { margin-left: auto; display: flex; gap: 0.5rem; align-items: center; }
    .admin-page-header .back-link { font-size: 0.75rem; color: var(--color-text-muted, #6b5e52); text-decoration: none; }
    .admin-page-header .back-link:hover { color: var(--color-accent, #a0633a); }
    .admin-body { padding: 1.5rem 2rem; flex: 1; }

    /* ── HORSE CONTEXT BANNER ──────────────────── */
    .horse-ctx-banner {
      background: var(--color-parchment, #f5ede0);
      border-bottom: 1px solid var(--color-border-warm, #c9b89a);
      padding: 0.6rem 2rem; display: flex; align-items: center; gap: 1rem;
    }
    .horse-ctx-banner .hcb-name { font-family: var(--font-serif, Georgia, serif); font-size: 1.05rem; color: var(--color-primary, #3d2b1f); }
    .horse-ctx-banner .hcb-meta { font-size: 0.75rem; color: var(--color-text-muted, #6b5e52); }
    .horse-ctx-banner .hcb-back { margin-left: auto; font-size: 0.75rem; color: var(--color-accent, #a0633a); text-decoration: none; }
    .horse-ctx-banner .hcb-back:hover { text-decoration: underline; }

    /* ── BUTTONS ───────────────────────────────── */
    .btn { display: inline-block; background: var(--color-primary, #3d2b1f); color: var(--color-cream, #e8d5b7); border: none; border-radius: 6px; padding: 0.5rem 1rem; font-size: 0.875rem; text-decoration: none; cursor: pointer; font-family: inherit; transition: background 0.15s; }
    .btn:hover { background: #5a4030; }
    .btn-ghost { background: var(--color-surface, #fff); color: var(--color-text, #2c2c2c); border: 1px solid var(--color-border, #e0d5c5); border-radius: 6px; padding: 0.5rem 1rem; font-size: 0.875rem; cursor: pointer; font-family: inherit; transition: all 0.15s; }
    .btn-ghost:hover { border-color: var(--color-accent, #a0633a); color: var(--color-accent, #a0633a); }
    .btn-sm { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.78rem; text-decoration: none; border: 1px solid var(--color-border, #e0d5c5); background: var(--color-surface, #fff); cursor: pointer; font-family: inherit; color: var(--color-text, #2c2c2c); transition: all 0.15s; }
    .btn-sm:hover { background: var(--color-surface-warm, #faf6f0); }
    .btn-sm.btn-edit   { border-color: var(--color-accent, #a0633a); color: var(--color-accent, #a0633a); }
    .btn-sm.btn-edit:hover { background: #fdf5ef; }
    .btn-sm.btn-danger { border-color: #c9a0a0; color: var(--color-danger, #8a3030); }
    .btn-sm.btn-danger:hover { background: #fdf0f0; }
    .btn-sm.btn-view   { border-color: #7aad7a; color: #2b6b2b; }
    .btn-sm.btn-view:hover { background: #f0fdf0; }
    .btn-sm.btn-photos { border-color: #9a7ab0; color: #5a2a7a; }
    .btn-sm.btn-photos:hover { background: #f8f0ff; }

    /* ── COMPACT LIST (007-C) ──────────────────── */
    .compact-list { background: var(--color-surface, #fff); border: 1px solid var(--color-border, #e0d5c5); border-radius: 10px; overflow: hidden; box-shadow: 0 1px 2px rgba(61,43,31,0.07); }
    .compact-list-header { display: grid; gap: 1rem; align-items: center; padding: 0.5rem 1.25rem; background: var(--color-surface-accent, #f5ede0); border-bottom: 1px solid var(--color-border, #e0d5c5); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-muted, #6b5e52); font-weight: 600; }
    .compact-list-row { display: grid; gap: 1rem; align-items: center; padding: 0.6rem 1.25rem; border-bottom: 1px solid var(--color-border, #e0d5c5); transition: background 0.1s; cursor: pointer; }
    .compact-list-row:last-of-type { border-bottom: none; }
    .compact-list-row:hover { background: var(--color-surface-warm, #faf6f0); }
    .cl-name { font-weight: 600; color: var(--color-primary, #3d2b1f); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cl-meta { font-size: 0.78rem; color: var(--color-text-muted, #6b5e52); }
    .cl-mono { font-family: var(--font-mono, 'Courier New', monospace); font-size: 0.75rem; color: var(--color-text-muted, #6b5e52); }
    .cl-expand-btn { background: none; border: none; color: var(--color-text-muted, #6b5e52); cursor: pointer; font-size: 13px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: all 0.15s; }
    .cl-expand-btn:hover { background: var(--color-surface-accent, #f5ede0); color: var(--color-primary, #3d2b1f); }
    .cl-expanded { display: none; padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--color-border, #e0d5c5); background: var(--color-surface-warm, #faf6f0); }
    .cl-expanded.open { display: block; }
    .cl-expanded-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    /* ── GENDER BADGES ─────────────────────────── */
    .gbadge { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; }
    .gbadge-ori   { background: #e8f0ff; color: #3b5bdb; }
    .gbadge-tamma { background: #fce8f3; color: #9c36b5; }
    .gbadge-ruuna { background: #e8f5e8; color: #2b8a3e; }

    /* ── STATUS BADGES ─────────────────────────── */
    .sbadge { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 0.72rem; font-weight: 600; }
    .sbadge-born     { background: #e8f5e8; color: #2b6b2b; }
    .sbadge-expected { background: #fff8e0; color: #9a7000; }

    /* ── PLACEMENT BADGES ──────────────────────── */
    .pbadge { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; font-size: 0.72rem; font-weight: 700; }
    .pbadge-1 { background: #fff3cd; color: #7a5f00; border: 1px solid #f0c040; }
    .pbadge-2 { background: #f0f0f0; color: #555;    border: 1px solid #ccc; }
    .pbadge-3 { background: #fde8d8; color: #8a3d00; border: 1px solid #e0a070; }
    .pbadge-x { background: var(--color-surface-warm, #faf6f0); color: var(--color-text-muted, #6b5e52); border: 1px solid var(--color-border, #e0d5c5); }

    /* ── STAT CARDS (dashboard) ────────────────── */
    .admin-stat-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .admin-stat-card { background: var(--color-surface, #fff); border: 1px solid var(--color-border, #e0d5c5); border-radius: 8px; padding: 1rem 1.25rem; flex: 1; min-width: 120px; box-shadow: 0 1px 2px rgba(61,43,31,0.07); }
    .admin-stat-card .stat-icon { font-size: 1.2rem; margin-bottom: 0.25rem; }
    .admin-stat-card .stat-num { font-family: var(--font-serif, Georgia, serif); font-size: 1.875rem; color: var(--color-gold, #c9a84c); line-height: 1; }
    .admin-stat-card .stat-label { font-size: 0.7rem; color: var(--color-text-muted, #6b5e52); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.05em; }

    /* ── COMP STAT ROW ─────────────────────────── */
    .comp-stat-row { display: flex; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .comp-stat-card { background: var(--color-surface, #fff); border: 1px solid var(--color-border, #e0d5c5); border-radius: 8px; padding: 0.75rem 1rem; flex: 1; min-width: 80px; }
    .comp-stat-card .cs-num { font-family: var(--font-serif, Georgia, serif); font-size: 1.5rem; color: var(--color-gold, #c9a84c); line-height: 1; }
    .comp-stat-card .cs-label { font-size: 0.7rem; color: var(--color-text-muted, #6b5e52); text-transform: uppercase; letter-spacing: 0.05em; }

    /* ── PHOTO GRID (010-B) ────────────────────── */
    .admin-photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; }
    .admin-photo-card { display: flex; flex-direction: column; gap: 0.5rem; }
    .admin-photo-thumb { position: relative; aspect-ratio: 1; border: 1px solid var(--color-border, #e0d5c5); border-radius: 8px; overflow: hidden; cursor: pointer; background: var(--color-parchment, #f5ede0); transition: all 0.15s; }
    .admin-photo-thumb:hover { border-color: var(--color-accent, #a0633a); transform: scale(1.02); box-shadow: 0 4px 8px rgba(61,43,31,0.1); }
    .admin-photo-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .photo-order-badge { position: absolute; bottom: 4px; left: 4px; background: rgba(0,0,0,0.5); color: #fff; font-size: 10px; padding: 1px 5px; border-radius: 3px; font-family: var(--font-mono, monospace); }
    .photo-profile-badge { position: absolute; bottom: 4px; right: 4px; background: var(--color-gold, #c9a84c); color: #3d2b1f; font-size: 9px; padding: 1px 5px; border-radius: 3px; font-weight: 700; text-transform: uppercase; }
    .photo-delete-form { position: absolute; top: 4px; right: 4px; display: none; }
    .admin-photo-thumb:hover .photo-delete-form { display: block; }
    .photo-delete-btn { background: rgba(138,48,48,0.85); color: #fff; border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
    .photo-delete-btn:hover { background: rgba(138,48,48,1); }
    .photo-meta-form { display: flex; flex-direction: column; gap: 0.3rem; }
    .photo-meta-form .form-control-sm { font-size: 0.78rem; padding: 0.25rem 0.45rem; border: 1px solid var(--color-border, #e0d5c5); border-radius: 5px; font-family: inherit; background: var(--color-surface, #fff); color: var(--color-text, #2c2c2c); width: 100%; transition: border-color 0.15s; }
    .photo-meta-form .form-control-sm:focus { outline: none; border-color: var(--color-accent, #a0633a); }
    .photo-meta-form textarea.form-control-sm { resize: vertical; min-height: 40px; }
    .photo-upload-limit { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.75rem; background: var(--color-surface, #fff); border: 1px solid var(--color-border, #e0d5c5); border-radius: 6px; margin-bottom: 1rem; font-size: 0.75rem; color: var(--color-text-muted, #6b5e52); }
    .photo-limit-track { flex: 1; height: 5px; background: var(--color-border, #e0d5c5); border-radius: 3px; overflow: hidden; }
    .photo-limit-fill { height: 100%; border-radius: 3px; background: var(--color-gold, #c9a84c); }
    .photo-limit-fill.full { background: var(--color-danger, #8a3030); }

    /* ── PHOTO PICKER (näyttelytulos ↔ kuva) ───── */
    .photo-pick-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .photo-pick-thumb { width: 60px; height: 60px; border-radius: 6px; border: 2px solid var(--color-border, #e0d5c5); overflow: hidden; cursor: pointer; background: var(--color-parchment, #f5ede0); transition: all 0.15s; display: flex; align-items: center; justify-content: center; }
    .photo-pick-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .photo-pick-thumb:hover { border-color: var(--color-accent, #a0633a); }
    .photo-pick-thumb.selected { border-color: var(--color-gold, #c9a84c); box-shadow: 0 0 0 2px rgba(201,168,76,0.35); }
    .photo-pick-none { font-size: 0.6rem; color: var(--color-text-muted, #6b5e52); text-align: center; padding: 2px; line-height: 1.2; }
    .sr-photo-thumb { width: 28px; height: 28px; border-radius: 4px; object-fit: cover; vertical-align: middle; border: 1px solid var(--color-border, #e0d5c5); }

    /* ── SLIDE-IN PANEL (008-B) ────────────────── */
    .admin-slide-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.3); z-index: 500; display: none; }
    .admin-slide-overlay.open { display: block; }
    .admin-slide-panel { position: fixed; top: 0; right: -440px; width: 420px; height: 100vh; background: var(--color-surface, #fff); box-shadow: 0 10px 24px rgba(61,43,31,0.18); z-index: 501; display: flex; flex-direction: column; transition: right 0.25s ease; border-left: 1px solid var(--color-border, #e0d5c5); }
    .admin-slide-panel.open { right: 0; }
    .admin-slide-header { background: var(--color-primary, #3d2b1f); padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid var(--color-gold, #c9a84c); }
    .admin-slide-header h2 { font-family: var(--font-serif, Georgia, serif); font-size: 1.15rem; color: var(--color-cream, #e8d5b7); font-weight: normal; margin: 0; }
    .admin-slide-close { background: none; border: none; color: var(--color-cream, #e8d5b7); opacity: 0.6; cursor: pointer; font-size: 20px; line-height: 1; padding: 0; }
    .admin-slide-close:hover { opacity: 1; }
    .admin-slide-body { flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .admin-slide-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--color-border, #e0d5c5); display: flex; gap: 0.75rem; }

    /* ── MODAL (009-A) ─────────────────────────── */
    .admin-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 500; display: none; align-items: center; justify-content: center; }
    .admin-modal-overlay.open { display: flex; }
    .admin-modal { background: var(--color-surface, #fff); border-radius: 10px; box-shadow: 0 10px 24px rgba(61,43,31,0.18); width: 500px; max-width: 95vw; overflow: hidden; }
    .admin-modal-header { background: var(--color-primary, #3d2b1f); padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid var(--color-gold, #c9a84c); }
    .admin-modal-header h2 { font-family: var(--font-serif, Georgia, serif); font-size: 1.15rem; color: var(--color-cream, #e8d5b7); font-weight: normal; margin: 0; }
    .admin-modal-close { background: none; border: none; color: var(--color-cream, #e8d5b7); opacity: 0.6; cursor: pointer; font-size: 20px; line-height: 1; padding: 0; }
    .admin-modal-close:hover { opacity: 1; }
    .admin-modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .admin-modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--color-border, #e0d5c5); display: flex; gap: 0.75rem; }

    /* ── FORM FIELDS ───────────────────────────── */
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 0.2rem; margin-bottom: 0; }
    .form-group label { font-size: 0.72rem; color: var(--color-text-muted, #6b5e52); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
    .form-group input, .form-group select, .form-group textarea { border: 1px solid var(--color-border, #e0d5c5); border-radius: 6px; padding: 0.4rem 0.6rem; font-size: 0.875rem; font-family: inherit; background: var(--color-surface, #fff); color: var(--color-text, #2c2c2c); transition: border-color 0.15s; width: 100%; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--color-accent, #a0633a); }
    .form-group textarea { min-height: 80px; resize: vertical; }

    /* ── CHECKBOX GRID ────────────────────────── */
    .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.25rem 0.5rem; padding: 0.5rem 0; }
    .checkbox-label { display: flex; align-items: center; gap: 0.45rem; font-size: 0.85rem; color: var(--color-text, #2c2c2c); cursor: pointer; padding: 0.25rem 0.35rem; border-radius: 4px; font-weight: normal; text-transform: none; letter-spacing: 0; }
    .checkbox-label:hover { background: var(--color-surface-warm, #faf6f0); }
    .checkbox-label input[type=checkbox] { width: auto; border: 1px solid var(--color-border, #e0d5c5); border-radius: 3px; cursor: pointer; accent-color: var(--color-accent, #a0633a); }

    /* ── AUTOCOMPLETE ──────────────────────────── */
    .ac-wrap { position: relative; }
    .ac-wrap .ac-text { width: 100%; }
    .ac-list { display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 200;
      background: var(--color-surface,#fff); border: 1px solid var(--color-accent,#a0633a);
      border-top: none; border-radius: 0 0 6px 6px; max-height: 220px; overflow-y: auto;
      margin: 0; padding: 0; list-style: none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .ac-list.open { display: block; }
    .ac-item { padding: 0.4rem 0.6rem; font-size: 0.875rem; cursor: pointer; color: var(--color-text,#2c2c2c); }
    .ac-item:hover, .ac-item.ac-active { background: var(--color-accent-light,#f5ede4); }
    .ac-item strong { color: var(--color-accent,#a0633a); font-weight: 700; }

    /* ── FLASH MESSAGES ────────────────────────── */
    .flash-ok  { background: #e8f5e8; border: 1px solid #c3e6c3; color: #2b6b2b; padding: 0.6rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
    .flash-err { background: #fdf0f0; border: 1px solid #e6c3c3; color: #8a3030; padding: 0.6rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.875rem; }
    .flash-err ul { margin: 0.25rem 0 0 1.25rem; padding: 0; }

    /* ── SECTION CARD ──────────────────────────── */
    .admin-card { background: var(--color-surface, #fff); border: 1px solid var(--color-border, #e0d5c5); border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 2px rgba(61,43,31,0.07); margin-bottom: 1.5rem; }
    .admin-card h2 { font-family: var(--font-serif, Georgia, serif); font-size: 1.15rem; color: var(--color-primary, #3d2b1f); font-weight: normal; margin: 0 0 1rem 0; padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border, #e0d5c5); }

    /* ── TABLE (legacy forms still use it) ─────── */
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th { background: var(--color-surface-accent, #f5ede0); color: var(--color-text-muted, #6b5e52); padding: 0.5rem 0.75rem; text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid var(--color-border, #e0d5c5); }
    .admin-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid var(--color-border, #e0d5c5); font-size: 0.875rem; vertical-align: middle; }
    .admin-table tr:last-child td { border-bottom: none; }
    .admin-table tr:hover td { background: var(--color-surface-warm, #faf6f0); }

    /* ── CONTACT CARD ─────────────────────────── */
    .contact-card { background: var(--color-surface-warm,#faf6f0); border: 1px solid var(--color-border,#e0d5c5); border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.85rem; color: var(--color-text,#2c2c2c); margin-bottom: 0.5rem; }
    .contact-card a { color: var(--color-accent,#a0633a); }

    /* ── JS HELPERS ────────────────────────────── */
    .js-slide-trigger { cursor: pointer; }

    @media (max-width: 768px) {
      .admin-sidebar { display: none; }
      .form-row, .form-row-3 { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="admin-shell">
  <!-- ── SIDEBAR ───────────────────────────── -->
  <aside class="admin-sidebar">
    <div class="admin-sidebar-logo">
      <div class="logo-text">🐴 Virtuaalitalli</div>
      <div class="logo-sub">Hallintapaneeli</div>
    </div>
    <?php $role = currentRole(); ?>
    <nav>
      <div class="admin-nav-section">Päävalikko</div>
      <?php if (in_array($role, ['admin','mod','author'], true)): ?>
      <a class="admin-nav-item <?= in_array($_activePage, ['index']) ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/">⊞ Dashboard</a>
      <?php endif; ?>
      <?php if (in_array($role, ['admin','mod'], true)): ?>
      <a class="admin-nav-item <?= in_array($_activePage, ['horses','horse_add','horse_edit','horse_delete']) ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/horses.php">🐎 Hevoset</a>
      <?php endif; ?>
      <?php if (in_array($role, ['admin','mod'], true)): ?>
      <a class="admin-nav-item <?= in_array($_activePage, ['contacts','contact_add','contact_edit']) ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/contacts.php">📒 Osoitekirja</a>
      <?php endif; ?>
      <?php if (in_array($role, ['admin','mod'], true)): ?>
      <a class="admin-nav-item <?= $_activePage === 'sukulaiset' ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/sukulaiset.php">🌳 Sukulaiset</a>
      <?php endif; ?>
      <?php if (in_array($role, ['admin','mod'], true)): ?>
      <a class="admin-nav-item <?= $_activePage === 'horse_import_vrl' ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/horse_import_vrl.php">📥 Tuo VRL:stä</a>
      <?php endif; ?>
      <?php if (in_array($role, ['admin','mod'], true)): ?>
      <a class="admin-nav-item <?= in_array($_activePage, ['kasvatus_all','foals']) ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/kasvatus_all.php">🌱 Kasvatus</a>
      <?php endif; ?>
      <?php if (in_array($role, ['admin','mod'], true)): ?>
      <a class="admin-nav-item <?= in_array($_activePage, ['kilpailut_all','competitions']) ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/kilpailut_all.php">🏆 Kilpailut</a>
      <?php endif; ?>
      <?php if (in_array($role, ['admin','mod'], true)): ?>
      <a class="admin-nav-item <?= in_array($_activePage, ['showrecords_all','showrecords']) ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/showrecords_all.php">🎀 Näyttelyt</a>
      <?php endif; ?>
      <div class="admin-nav-section">Media</div>
      <?php if (in_array($role, ['admin','mod'], true)): ?>
      <a class="admin-nav-item <?= in_array($_activePage, ['kuvat_all','photos','photo_delete']) ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/kuvat_all.php">📷 Kuvat</a>
      <?php endif; ?>
      <?php if (in_array($role, ['admin','mod','author'], true)): ?>
      <a class="admin-nav-item <?= strpos($_SERVER['PHP_SELF'], '/admin/posts') !== false ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/posts.php">📝 Postaukset</a>
      <?php endif; ?>
      <div class="admin-nav-section">Sivusto</div>
      <?php if (in_array($role, ['admin'], true)): ?>
      <a class="admin-nav-item <?= in_array($_activePage, ['users','user_add','user_edit'], true) ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/users.php">👥 Käyttäjät</a>
      <?php endif; ?>
      <?php if (in_array($role, ['admin'], true)): ?>
      <a class="admin-nav-item <?= $_activePage === 'deletions' ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/deletions.php">🗑️ Poistopyynnöt</a>
      <?php endif; ?>
      <?php if (in_array($role, ['admin'], true)): ?>
      <a class="admin-nav-item <?= $_activePage === 'settings' ? 'active' : '' ?>"
         href="<?= e(SITE_URL) ?>/admin/settings.php">⚙️ Asetukset</a>
      <?php endif; ?>
      <a class="admin-nav-item" href="<?= e(SITE_URL) ?>/" target="_blank">🔗 Julkinen sivu</a>
    </nav>
    <div class="admin-sidebar-footer">
      <div class="sb-username"><?= e($_SESSION['admin_username'] ?? '') ?></div>
      <a href="<?= e(SITE_URL) ?>/admin/change_password.php" class="sb-logout-btn" style="display:block;margin-top:0.3rem;">Vaihda salasana</a>
      <form method="post" action="<?= e(SITE_URL) ?>/admin/logout.php" style="margin-top:0.3rem">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
        <button type="submit" class="sb-logout-btn">Kirjaudu ulos →</button>
      </form>
    </div>
  </aside>
  <!-- ── MAIN ──────────────────────────────── -->
  <div class="admin-main">
