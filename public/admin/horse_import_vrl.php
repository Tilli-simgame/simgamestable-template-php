<?php
require_once __DIR__ . '/../src/includes/db.php';
requireRole('admin', 'mod');

// Load owner settings for JS
$settingsRows = getDB()->query(
    "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('owner_nickname','owner_vrl_id')"
)->fetchAll();
$_ownerSettings = [];
foreach ($settingsRows as $row) {
    $_ownerSettings[$row['setting_key']] = trim($row['setting_value'] ?? '');
}
$ownerNicknameForJs = mb_strtolower($_ownerSettings['owner_nickname'] ?? '', 'UTF-8');
$ownerVrlIdForJs    = mb_strtolower($_ownerSettings['owner_vrl_id']   ?? '', 'UTF-8');

$pageTitle = 'Tuo hevonen VRL:stä';
$csrfToken = generate_csrf_token();
require __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-page-header">
  <a href="<?= e(SITE_URL) ?>/admin/horses.php" class="back-link">← Hevoset</a>
  <h1>Tuo hevonen VRL:stä</h1>
</div>
<div class="admin-body">

<div class="admin-card">
  <h2>Hae VH-tunnuksella</h2>
  <p style="font-size:0.82rem;color:var(--color-text-muted,#6b5e52);margin:0 0 1rem 0;">
    Haku noutaa hevosen tiedot sekä kolmen polven sukutaulun suoraan VRL:stä.
    Kaikki löydetyt sukulaiset lisätään tietokantaan automaattisesti.
  </p>
  <div style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;flex:1;max-width:320px;">
      <label for="vh_input">VH-tunnus</label>
      <input type="text" id="vh_input" placeholder="Esim. VH14-014-0143" autocomplete="off">
    </div>
    <button id="btn_fetch" class="btn">Hae tiedot</button>
  </div>
  <div id="fetch_status" style="margin-top:0.75rem;font-size:0.82rem;"></div>
</div>

<div id="preview_section" style="display:none;">
  <div class="admin-card">
    <h2>Esikatselu — tuotavat hevoset</h2>
    <p style="font-size:0.82rem;color:var(--color-text-muted,#6b5e52);margin:0 0 1rem 0;">
      Kaikki tuotavat hevoset (pääkohde ja sukulaiset) lisätään evm=false-arvolla.
      Jos hevonen on jo tietokannassa saman VH-tunnuksen perusteella, sitä ei lisätä uudelleen.
    </p>

    <div style="margin-bottom:0.75rem;font-size:0.78rem;display:flex;gap:1rem;flex-wrap:wrap;">
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#2b6b2b;margin-right:4px;"></span>Tämä talli — tallennetaan kaikki tiedot</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#5a6a99;margin-right:4px;"></span>Sukulainen (toinen talli) — tallennetaan nimi, rotu, säkä, väri ja URL</span>
    </div>

    <div style="overflow-x:auto;">
      <table class="admin-table" id="preview_table">
        <thead>
          <tr>
            <th>Rooli</th>
            <th>Tyyppi</th>
            <th>Nimi</th>
            <th>VH-tunnus</th>
            <th>Rotu</th>
            <th>Sukupuoli</th>
            <th>Synt.</th>
            <th>Väri</th>
            <th>Säkä</th>
            <th>Omistaja</th>
          </tr>
        </thead>
        <tbody id="preview_tbody"></tbody>
      </table>
    </div>

    <div style="margin-top:1.25rem;display:flex;gap:0.75rem;align-items:center;">
      <button id="btn_import" class="btn" disabled>Tuo tietokantaan</button>
      <span id="import_status" style="font-size:0.82rem;"></span>
    </div>
  </div>
</div>

</div><!-- /.admin-body -->

<script>
(function () {
  'use strict';

  // ── Config ──────────────────────────────────────────────────────────
  const PROXY   = 'https://tight-lake-e0b8.anniina-sipria.workers.dev/?url=';
  const SAVE_URL = <?= json_encode(SITE_URL . '/admin/api/vrl_import_save.php') ?>;
  const CSRF    = <?= json_encode($csrfToken) ?>;
  const OWNER_NICKNAME = <?= json_encode($ownerNicknameForJs) ?>;
  const OWNER_VRL_ID   = <?= json_encode($ownerVrlIdForJs) ?>;
  const VH_RE   = /^VH\d{2}-\d{3}-\d{4}$/i;

  const FIELD_LABELS = new Set([
    'Rotu','Sukupuoli','Säkäkorkeus','Syntynyt','Väri','Sivut',
    'Kotitalli','Kasvattajanimi','Kasvattaja','Omistajat','Painotus'
  ]);

  // ── DOM refs ────────────────────────────────────────────────────────
  const vhInput       = document.getElementById('vh_input');
  const btnFetch      = document.getElementById('btn_fetch');
  const fetchStatus   = document.getElementById('fetch_status');
  const previewSection = document.getElementById('preview_section');
  const previewTbody  = document.getElementById('preview_tbody');
  const btnImport     = document.getElementById('btn_import');
  const importStatus  = document.getElementById('import_status');

  // ── Helpers ─────────────────────────────────────────────────────────
  function setFetchStatus(msg, isErr = false) {
    fetchStatus.innerHTML = isErr
      ? `<span style="color:#8a3030">${msg}</span>`
      : msg;
  }

  function setImportStatus(msg, isErr = false) {
    importStatus.innerHTML = isErr
      ? `<span style="color:#8a3030">${msg}</span>`
      : `<span style="color:#2b6b2b">${msg}</span>`;
  }

  function esc(s) {
    return (s ?? '').toString()
      .replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function buildHorseUrl(vh) {
    return `https://virtuaalihevoset.net/virtuaalihevoset/hevonen/${encodeURIComponent(vh)}`;
  }
  function buildSukuUrl(vh) {
    return `https://virtuaalihevoset.net/virtuaalihevoset/hevonen/${encodeURIComponent(vh)}/suku`;
  }

  async function fetchHtml(targetUrl) {
    const url = PROXY + encodeURIComponent(targetUrl);
    const res = await fetch(url, { headers: { Accept: 'text/html' } });
    if (!res.ok) throw new Error(`HTTP ${res.status} ${res.statusText}`);
    return res.text();
  }

  function getLinesFromDoc(doc) {
    const raw = (doc.body?.innerText?.trim().length)
      ? doc.body.innerText
      : (doc.body?.textContent ?? doc.documentElement.textContent);
    return raw.split('\n').map(s => s.replace(/\s+/g,' ').trim()).filter(Boolean);
  }

  function looksLikeLabelLine(line) {
    for (const lbl of FIELD_LABELS) {
      if (line === lbl || line.startsWith(lbl + ' ')) return true;
    }
    return false;
  }

  function extractField(lines, label) {
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      if (line.startsWith(label + ' ')) return line.slice(label.length).trim();
      if (line === label) {
        const next = (lines[i + 1] || '').trim();
        if (!next || looksLikeLabelLine(next)) return '';
        return next;
      }
    }
    return '';
  }

  function normalizeEmpty(v) {
    const t = (v || '').trim();
    return t === '-' ? '' : t;
  }

  function extractDate(value) {
    const m = (value || '').match(/\b\d{2}\.\d{2}\.\d{4}\b/);
    return m ? m[0] : '';
  }

  function looksLikeHeight(value) {
    return /\d{2,3}\s*cm/i.test(value || '');
  }

  async function fetchHorseDetails(vh) {
    const horseUrl = buildHorseUrl(vh);
    const html = await fetchHtml(horseUrl);
    const doc  = new DOMParser().parseFromString(html, 'text/html');
    const lines = getLinesFromDoc(doc);

    const rawSakakorkeus = normalizeEmpty(extractField(lines, 'Säkäkorkeus'));
    const rawSyntynyt    = normalizeEmpty(extractField(lines, 'Syntynyt'));

    const h2 = doc.querySelector('h2');
    const title = h2 ? h2.textContent.trim() : '';

    return {
      vh,
      horseUrl,
      title,
      rotu          : normalizeEmpty(extractField(lines, 'Rotu')),
      sukupuoli     : normalizeEmpty(extractField(lines, 'Sukupuoli')),
      sakakorkeus   : looksLikeHeight(rawSakakorkeus) ? rawSakakorkeus : '',
      syntynyt      : extractDate(rawSyntynyt),
      vari          : normalizeEmpty(extractField(lines, 'Väri')),
      sivut         : normalizeEmpty(extractField(lines, 'Sivut')),
      kotitalli     : normalizeEmpty(extractField(lines, 'Kotitalli')),
      kasvattajanimi: normalizeEmpty(extractField(lines, 'Kasvattajanimi')),
      kasvattaja    : normalizeEmpty(extractField(lines, 'Kasvattaja')),
      omistajat     : normalizeEmpty(extractField(lines, 'Omistajat')),
    };
  }

  function parsePedigreeFromText(text) {
    const cutAt = text.indexOf('Suvun tiedot');
    const main  = cutAt > 0 ? text.slice(0, cutAt) : text;
    const results = [];

    const withVh = /(\b(?:i|e)(?:i|e){0,2}\.)\s+([^\n(]+?)\s*\(\s*(VH\d{2}-\d{3}-\d{4})\s*\)\s*([^\n]*)/gi;
    let m;
    while ((m = withVh.exec(main)) !== null) {
      results.push({
        mark        : m[1].trim(),
        name        : m[2].trim().replace(/\s+/g,' '),
        vh          : m[3].toUpperCase(),
        pedigreeExtra: (m[4] || '').trim(),
        known       : true,
      });
    }

    const unknown = /(\b(?:i|e)(?:i|e){0,2}\.)\s+(Tuntematon [^\n]+)/gi;
    while ((m = unknown.exec(main)) !== null) {
      const mark = m[1].trim();
      const name = m[2].trim().replace(/\s+/g,' ');
      if (!results.some(r => r.mark === mark)) {
        results.push({ mark, name, vh: '', pedigreeExtra: '', known: false });
      }
    }

    const score = (mark) => {
      const side  = mark.startsWith('i') ? 0 : 1;
      const depth = mark.replace('.','').length;
      return side * 100 + depth;
    };
    results.sort((a, b) => score(a.mark) - score(b.mark));
    return results;
  }

  async function mapLimit(items, limit, mapper) {
    const out = new Array(items.length);
    let i = 0;
    const workers = Array.from({ length: Math.min(limit, items.length) }, async () => {
      while (i < items.length) { const idx = i++; out[idx] = await mapper(items[idx], idx); }
    });
    await Promise.all(workers);
    return out;
  }

  // ── State ────────────────────────────────────────────────────────────
  let _horse     = null; // main horse details
  let _relatives = [];   // merged relatives with details

  // ── Render preview ──────────────────────────────────────────────────
  function roleBadge(mark) {
    const labels = {
      'i.':'Isä','e.':'Emä',
      'ii.':'Is.isä','ie.':'Is.emä','ei.':'Em.isä','ee.':'Em.emä',
      'iii.':'Is.is.isä','iie.':'Is.is.emä','iei.':'Is.em.isä','iee.':'Is.em.emä',
      'eii.':'Em.is.isä','eie.':'Em.is.emä','eei.':'Em.em.isä','eee.':'Em.em.emä',
    };
    return labels[mark] ?? mark;
  }

  function isOwnedByStable(omistajat) {
    if (!OWNER_NICKNAME && !OWNER_VRL_ID) return true;
    const field = (omistajat || '').toLowerCase();
    if (!field) return false;
    if (OWNER_NICKNAME && field.includes(OWNER_NICKNAME)) return true;
    if (OWNER_VRL_ID   && field.includes(OWNER_VRL_ID))   return true;
    return false;
  }

  function typeBadge(owned) {
    return owned
      ? `<span style="color:#2b6b2b;font-weight:600;font-size:0.75rem;">● Tämä talli</span>`
      : `<span style="color:#5a6a99;font-weight:600;font-size:0.75rem;">● Sukulainen</span>`;
  }

  function renderPreview(horse, relatives) {
    previewTbody.innerHTML = '';

    // Main horse row
    const mainTr = document.createElement('tr');
    mainTr.style.background = 'var(--color-surface-accent,#f5ede0)';
    const mainOwned = isOwnedByStable(horse.omistajat);
    mainTr.innerHTML = `
      <td><strong>Pääkohde</strong></td>
      <td>${typeBadge(mainOwned)}</td>
      <td><strong>${esc(horse.title.replace(/\s*\(VH\d{2}-\d{3}-\d{4}\)\s*$/, ''))}</strong></td>
      <td class="cl-mono">${esc(horse.vh)}</td>
      <td>${esc(horse.rotu)}</td>
      <td>${mainOwned ? esc(horse.sukupuoli) : '<span style="color:#aaa">—</span>'}</td>
      <td>${mainOwned ? esc(horse.syntynyt)  : '<span style="color:#aaa">—</span>'}</td>
      <td>${esc(horse.vari)}</td>
      <td>${esc(horse.sakakorkeus)}</td>
      <td>${esc(horse.omistajat)}</td>
    `;
    previewTbody.appendChild(mainTr);

    for (const r of relatives) {
      const owned = isOwnedByStable(r.omistajat);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><span style="display:inline-block;padding:1px 7px;border-radius:999px;font-size:11px;background:#f1f1f1;">${esc(roleBadge(r.mark))}</span></td>
        <td>${typeBadge(owned)}</td>
        <td>${esc(r.name)}</td>
        <td class="cl-mono">${r.vh ? esc(r.vh) : '<span style="color:#aaa">—</span>'}</td>
        <td>${esc(r.rotu || '')}</td>
        <td>${owned ? esc(r.sukupuoli || '') : '<span style="color:#aaa">—</span>'}</td>
        <td>${owned ? esc(r.syntynyt  || '') : '<span style="color:#aaa">—</span>'}</td>
        <td>${esc(r.vari || '')}</td>
        <td>${esc(r.sakakorkeus || '')}</td>
        <td>${esc(r.omistajat || '')}</td>
      `;
      previewTbody.appendChild(tr);
    }

    previewSection.style.display = '';
    btnImport.disabled = false;
  }

  // ── Fetch ────────────────────────────────────────────────────────────
  btnFetch.addEventListener('click', async () => {
    const vh = vhInput.value.trim().toUpperCase();
    if (!VH_RE.test(vh)) {
      setFetchStatus('Anna VH-tunnus muodossa VH14-014-0143.', true);
      return;
    }

    btnFetch.disabled = true;
    btnImport.disabled = true;
    previewSection.style.display = 'none';
    _horse = null;
    _relatives = [];

    try {
      setFetchStatus('Haetaan hevosen perustiedot…');
      const inputDetails = await fetchHorseDetails(vh);

      setFetchStatus('Haetaan sukutaulu…');
      const sukuHtml = await fetchHtml(buildSukuUrl(vh));
      const sukuDoc  = new DOMParser().parseFromString(sukuHtml, 'text/html');
      const sukuText = sukuDoc.body?.innerText?.trim().length
        ? sukuDoc.body.innerText
        : (sukuDoc.body?.textContent ?? sukuDoc.documentElement.textContent);

      const relatives = parsePedigreeFromText(sukuText);
      const knownVh   = [...new Set(relatives.filter(r => r.known && r.vh).map(r => r.vh))];

      setFetchStatus(`Löytyi ${relatives.length} sukumerkintää. Haetaan ${knownVh.length} hevosen tiedot…`);

      const detailsList = await mapLimit(knownVh, 4, async (rvh) => {
        try { return await fetchHorseDetails(rvh); }
        catch (e) { return { vh: rvh, error: e.message }; }
      });

      const byVh = new Map(detailsList.filter(d => d?.vh).map(d => [d.vh, d]));

      // Suodatetaan pois Tuntematon-hevoset (ei VH-tunnusta ja nimi alkaa 'Tuntematon')
      const merged = relatives
        .filter(r => !(!r.vh && /^Tuntematon\b/i.test(r.name)))
        .map(r => {
        const d = r.vh ? byVh.get(r.vh) : null;
        return {
          ...r,
          rotu          : d?.rotu           ?? '',
          sukupuoli     : d?.sukupuoli       ?? '',
          sakakorkeus   : d?.sakakorkeus     ?? '',
          syntynyt      : d?.syntynyt        ?? '',
          vari          : d?.vari            ?? '',
          sivut         : d?.sivut           ?? '',
          kotitalli     : d?.kotitalli       ?? '',
          kasvattajanimi: d?.kasvattajanimi  ?? '',
          kasvattaja    : d?.kasvattaja      ?? '',
          omistajat     : d?.omistajat       ?? '',
          horseUrl      : d?.horseUrl        ?? '',
        };
      });

      _horse     = inputDetails;
      _relatives = merged;

      renderPreview(inputDetails, merged);
      setFetchStatus(`Valmis — ${merged.length} sukulaista löydetty. Tarkista tiedot ja tuo tietokantaan.`);
    } catch (err) {
      console.error(err);
      setFetchStatus('Haku epäonnistui: ' + err.message, true);
    } finally {
      btnFetch.disabled = false;
    }
  });

  // ── Import ───────────────────────────────────────────────────────────
  btnImport.addEventListener('click', async () => {
    if (!_horse) return;

    btnImport.disabled = true;
    setImportStatus('Tallennetaan…');

    try {
      const payload = {
        csrf_token: CSRF,
        horse     : _horse,
        relatives : _relatives,
      };

      const res = await fetch(SAVE_URL, {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify(payload),
      });

      const json = await res.json();

      if (!json.ok) {
        setImportStatus('Virhe: ' + (json.error ?? 'Tuntematon virhe'), true);
        btnImport.disabled = false;
        return;
      }

      setImportStatus(
        `✅ Tuonti valmis — lisätty ${json.inserted} hevosta, ohitettu ${json.skipped} (jo olemassa).`
      );

      // Redirect to the newly imported main horse after short delay
      if (json.mainHorseId) {
        setTimeout(() => {
          window.location.href = <?= json_encode(SITE_URL . '/admin/horse_edit.php?id=') ?> + json.mainHorseId;
        }, 1500);
      }
    } catch (err) {
      console.error(err);
      setImportStatus('Pyyntö epäonnistui: ' + err.message, true);
      btnImport.disabled = false;
    }
  });

  // Allow Enter key in VH input
  vhInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') btnFetch.click();
  });
})();
</script>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
