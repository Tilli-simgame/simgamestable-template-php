/* ── Admin-hevoslistojen haku, suodatus ja lajittelu (selaimessa) ──────────
   Merkintä (ks. admin/includes/horse_filters.php):
     [data-hf-toolbar]   haku, talli, rotu, painotus, sukupuoli — suodattaa kaikki sivun listat
     [data-hf-list]      .compact-list; data-hf-key erottaa listojen lajittelun URL:ssa
       .hl-sort[data-sort="avain"]  otsikon lajittelupainike, lukee rivin data-avain-arvon
       .compact-list-row            rivi (+ perässä #cl-exp-<id> laajennus)
       .hl-empty                    "ei tuloksia" -viesti
     [data-hf-list-count="key"]  listakohtainen laskuri (valinnainen)
   Tila tallennetaan URL:iin (?q=&talli=&rotu=&painotus=&sp=&sort=&dir=), jotta se säilyy
   kun palataan muokkauksesta tai sivu päivitetään. */
(() => {
  const MIN_CHARS = 3;
  const toolbar = document.querySelector('[data-hf-toolbar]');
  if (!toolbar) return;

  const qInput   = toolbar.querySelector('[data-hf-q]');
  const qHint    = toolbar.querySelector('[data-hf-hint]');
  const breedSel = toolbar.querySelector('[data-hf-breed]');
  const discSel  = toolbar.querySelector('[data-hf-disc]');
  const stableSel= toolbar.querySelector('[data-hf-stable]');
  const gBtns    = [...toolbar.querySelectorAll('.filter-btn[data-gender]')];
  const countEl  = toolbar.querySelector('[data-hf-count]');
  const resetBtn = toolbar.querySelector('[data-hf-reset]');
  const collator = new Intl.Collator('fi', { sensitivity: 'base', numeric: true });
  const norm = s => s.toLocaleLowerCase('fi').normalize('NFD').replace(/[̀-ͯ]/g, '');

  const lists = [...document.querySelectorAll('[data-hf-list]')].map(el => {
    const key = el.dataset.hfKey || '';
    const rows = [...el.querySelectorAll('.compact-list-row')];
    rows.forEach(r => {
      r._search = norm(r.dataset.name + ' ' + (r.dataset.call || ''));
      r._disc   = r.dataset.disc ? r.dataset.disc.split('|') : [];
      r._exp    = document.getElementById('cl-exp-' + r.dataset.id);
    });
    return {
      el, rows, key,
      sortBtns: [...el.querySelectorAll('.hl-sort')],
      empty: el.querySelector('.hl-empty'),
      count: document.querySelector(`[data-hf-list-count="${key}"]`),
      sortParam: key ? key + '_sort' : 'sort',
      dirParam:  key ? key + '_dir'  : 'dir',
      sort: 'name', dir: 'asc',
    };
  });

  const state = { q: '', stable: '', breed: '', disc: '', gender: '' };
  const hasOption = (sel, v) => sel && [...sel.options].some(o => o.value === v);

  function readUrl() {
    const p = new URLSearchParams(location.search);
    state.q      = p.get('q') || '';
    state.breed  = hasOption(breedSel, p.get('rotu')) ? p.get('rotu') : '';
    state.disc   = hasOption(discSel, p.get('painotus')) ? p.get('painotus') : '';
    state.gender = p.get('sp') || '';
    state.stable = hasOption(stableSel, p.get('talli')) ? p.get('talli') : '';
    qInput.value = state.q;
    if (stableSel) stableSel.value = state.stable;
    if (breedSel) breedSel.value = state.breed;
    if (discSel)  discSel.value  = state.disc;
    lists.forEach(l => {
      const s = p.get(l.sortParam);
      if (s && l.sortBtns.some(b => b.dataset.sort === s)) l.sort = s;
      l.dir = p.get(l.dirParam) === 'desc' ? 'desc' : 'asc';
    });
  }

  function writeUrl() {
    const p = new URLSearchParams(location.search);
    ['added', 'updated', 'deleted'].forEach(k => p.delete(k));
    const set = (k, v, def = '') => (v && v !== def) ? p.set(k, v) : p.delete(k);
    set('q', state.q.trim()); set('rotu', state.breed); set('painotus', state.disc); set('sp', state.gender);
    set('talli', state.stable);
    lists.forEach(l => { set(l.sortParam, l.sort, 'name'); set(l.dirParam, l.dir, 'asc'); });
    const qs = p.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
  }

  function breedMatcher() {
    if (!state.breed) return () => true;
    if (state.breed === '-') return r => !r.dataset.breed;
    const opt = breedSel.selectedOptions[0];
    if (opt && opt.dataset.members) {
      const members = new Set(JSON.parse(opt.dataset.members));
      return r => members.has(r.dataset.breed);
    }
    return r => r.dataset.breed === state.breed;
  }

  function apply() {
    const q = norm(state.q.trim());
    const searching = q.length >= MIN_CHARS;
    const missing = MIN_CHARS - q.length;
    qHint.textContent = (q.length > 0 && !searching)
      ? `Kirjoita vielä ${missing} merkki${missing === 1 ? '' : 'ä'}…` : '';
    const breedOk = breedMatcher();

    let total = 0;
    lists.forEach(l => {
      let visible = 0;
      l.rows.forEach(r => {
        const show = (!searching || r._search.includes(q))
          && breedOk(r)
          && (!state.stable || (state.stable === '-' ? !r.dataset.stable : r.dataset.stable === state.stable))
          && (!state.disc || (state.disc === '-' ? r._disc.length === 0 : r._disc.includes(state.disc)))
          && (!state.gender || r.dataset.gender === state.gender);
        r.hidden = !show;
        if (r._exp) {
          r._exp.hidden = !show;
          if (!show && r._exp.classList.contains('open')) adminToggleExpand(r.dataset.id);
        }
        if (show) visible++;
      });
      if (l.empty) l.empty.style.display = visible ? 'none' : 'block';
      if (l.count) l.count.textContent = visible;
      l.sortBtns.forEach(b => b.setAttribute('aria-sort',
        b.dataset.sort === l.sort ? (l.dir === 'asc' ? 'ascending' : 'descending') : 'none'));
      total += visible;
    });
    countEl.textContent = total;
    resetBtn.hidden = !(state.q || state.stable || state.breed || state.disc || state.gender);
    gBtns.forEach(b => b.classList.toggle('active', b.dataset.gender === state.gender));
    writeUrl();
  }

  function sortList(l) {
    const key = r => l.sort === 'disc' ? r._disc.join(', ') : (r.dataset[l.sort] || '');
    const sign = l.dir === 'desc' ? -1 : 1;
    const sorted = [...l.rows].sort((a, b) => {
      const ka = key(a), kb = key(b);
      if (!ka !== !kb) return ka ? -1 : 1;   // tyhjät arvot aina loppuun
      return sign * collator.compare(ka, kb) || collator.compare(a.dataset.name, b.dataset.name);
    });
    const frag = document.createDocumentFragment();
    sorted.forEach(r => { frag.appendChild(r); if (r._exp) frag.appendChild(r._exp); });
    l.el.insertBefore(frag, l.empty || null);
  }

  qInput.addEventListener('input', () => { state.q = qInput.value; apply(); });
  if (breedSel) breedSel.addEventListener('change', () => { state.breed = breedSel.value; apply(); });
  if (discSel)  discSel.addEventListener('change',  () => { state.disc = discSel.value; apply(); });
  if (stableSel) stableSel.addEventListener('change', () => { state.stable = stableSel.value; apply(); });
  gBtns.forEach(b => b.addEventListener('click', () => { state.gender = b.dataset.gender; apply(); }));
  lists.forEach(l => l.sortBtns.forEach(b => b.addEventListener('click', () => {
    if (l.sort === b.dataset.sort) l.dir = l.dir === 'asc' ? 'desc' : 'asc';
    else { l.sort = b.dataset.sort; l.dir = b.dataset.sortDefault === 'desc' ? 'desc' : 'asc'; }
    sortList(l); apply();
  })));
  resetBtn.addEventListener('click', () => {
    Object.assign(state, { q: '', stable: '', breed: '', disc: '', gender: '' });
    qInput.value = '';
    if (stableSel) stableSel.value = '';
    if (breedSel) breedSel.value = '';
    if (discSel)  discSel.value  = '';
    apply(); qInput.focus();
  });

  readUrl();
  lists.forEach(sortList);
  apply();
})();
