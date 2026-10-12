/* künye — ziyaretçi tarafı. Sayfalar JS olmadan da çalışır; bu dosya yalnızca deneyimi hızlandırır. */
(() => {
  'use strict';
  const d = document;
  const body = d.body;
  const sprite = body.dataset.sprite || '';
  const $ = (s, el = d) => el.querySelector(s);
  const $$ = (s, el = d) => Array.from(el.querySelectorAll(s));
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const icon = (n, cls = 'icon') => `<svg class="${cls}" aria-hidden="true"><use href="${sprite}#i-${n}"/></svg>`;
  const FOLD = { 'İ': 'i', 'I': 'i', 'ı': 'i', 'Ş': 's', 'ş': 's', 'Ğ': 'g', 'ğ': 'g', 'Ü': 'u', 'ü': 'u', 'Ö': 'o', 'ö': 'o', 'Ç': 'c', 'ç': 'c', 'Â': 'a', 'â': 'a', 'Î': 'i', 'î': 'i', 'Û': 'u', 'û': 'u' };
  const fold = (s) => String(s).replace(/[İIıŞşĞğÜüÖöÇçÂâÎîÛû]/g, (c) => FOLD[c]).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
  const words = (s) => fold(s).split(/[^a-z0-9]+/).filter(Boolean);
  const nf = new Intl.NumberFormat('tr-TR');
  window.kunye = { $, $$, esc, icon, fold };

  /* kelime düzeyinde benzerlik (Text::similar ile aynı kurallar) */
  const similar = (a, b) => {
    const wa = words(a), wb = words(b);
    if (!wa.length || !wb.length) return false;
    if (wa.join('') === wb.join('')) return true;
    const sa = [...new Set(wa)], sb = [...new Set(wb)];
    const sub = (x, y) => x.every((w) => y.includes(w));
    if (sub(sa, sb) || sub(sb, sa)) return true;
    const num = (w) => /^\d+$/.test(w);
    const na = sa.filter(num), nb = sb.filter(num);
    if ((na.length === 0) !== (nb.length === 0)) {
      const aa = sa.filter((w) => !num(w)), ab = sb.filter((w) => !num(w));
      const [sm, bg] = aa.length <= ab.length ? [aa, ab] : [ab, aa];
      if (sm.length >= 2 && sub(sm, bg)) return true;
    }
    return false;
  };
  window.kunye.similar = similar;

  /* ------------------------------------------------ onay isteyen düğmeler */
  d.addEventListener('click', (e) => {
    const b = e.target.closest('[data-confirm]');
    if (b && !confirm(b.dataset.confirm)) { e.preventDefault(); e.stopImmediatePropagation(); }
  }, true);

  /* ------------------------------------------------ arama önerileri */
  const RECENT = 'kunye-son';
  const recent = {
    get() { try { return JSON.parse(localStorage.getItem(RECENT) || '[]'); } catch { return []; } },
    add(q) {
      q = q.trim(); if (!q) return;
      try { localStorage.setItem(RECENT, JSON.stringify([q, ...this.get().filter((x) => x !== q)].slice(0, 5))); } catch { /* gizli pencere */ }
    },
  };

  $$('form.search[data-suggest]').forEach((form) => {
    const input = $('.search__input', form);
    const box = $('.suggest', form);
    const initial = box.innerHTML;
    const endpoint = form.dataset.suggest;
    const action = form.getAttribute('action');
    let timer = 0, ctrl = null, last = null;

    const items = () => $$('.suggest__item', box);
    const setActive = (i) => {
      const list = items();
      list.forEach((el, j) => el.classList.toggle('is-active', j === i));
      if (list[i]) { input.setAttribute('aria-activedescendant', list[i].id); list[i].scrollIntoView({ block: 'nearest' }); }
    };
    const open = (on) => { form.classList.toggle('is-open', on); input.setAttribute('aria-expanded', on ? 'true' : 'false'); };

    const renderEmpty = () => {
      const r = recent.get();
      let html = '';
      if (r.length) {
        html += '<div class="suggest__label">Son aramalar</div>' + r.map((q) =>
          `<a class="suggest__item" href="${esc(action)}?q=${encodeURIComponent(q)}"><span class="suggest__icon suggest__icon--plain">${icon('clock')}</span><span class="suggest__text"><span>${esc(q)}</span></span></a>`).join('');
      }
      box.innerHTML = html + initial;
    };

    const render = (q, data) => {
      let html = '';
      const sec = (label, list, fn) => { if (list.length) html += `<div class="suggest__label">${label}</div>` + list.map(fn).join(''); };
      sec('Kategoriler', data.kategoriler, (c) => `<a class="suggest__item" href="${esc(c.url)}"><span class="suggest__icon">${icon(c.ikon)}</span><span class="suggest__text"><span>${c.html}</span>${c.alt ? `<small>${esc(c.alt)}</small>` : ''}</span><span class="suggest__meta">${nf.format(c.n)} öğe</span></a>`);
      sec('Öğeler', data.ogeler, (o) => `<a class="suggest__item" href="${esc(o.url)}">${o.gorsel ? `<img class="suggest__thumb" src="${esc(o.gorsel)}" width="38" height="38" alt="">` : `<span class="suggest__icon">${icon('box')}</span>`}<span class="suggest__text"><span>${o.html}</span><small>${esc(o.alt)}</small></span></a>`);
      sec('Özellik değerleri', data.degerler, (v) => `<a class="suggest__item" href="${esc(v.url)}"><span class="suggest__icon">${icon('tag')}</span><span class="suggest__text"><span>${v.html}</span><small>${esc(v.alt)}</small></span><span class="suggest__meta">${nf.format(v.n)} öğe</span></a>`);
      if (!html) html = `<div class="suggest__label">Sonuç yok</div>`;
      html += `<div class="suggest__foot"><span class="keys"><kbd>↑</kbd><kbd>↓</kbd>&nbsp;gez</span><span class="keys"><kbd>↵</kbd>&nbsp;aç</span><a href="${esc(action)}?q=${encodeURIComponent(q)}">“${esc(q)}” için tüm sonuçlar →</a></div>`;
      box.innerHTML = html;
      items().forEach((el, i) => { el.id = `${form.id || 's'}-o${i}`; el.setAttribute('role', 'option'); });
    };

    const query = () => {
      const q = input.value.trim();
      if (q === last) return;
      last = q;
      if (!q) { renderEmpty(); return; }
      if (ctrl) ctrl.abort();
      ctrl = new AbortController();
      fetch(`${endpoint}?q=${encodeURIComponent(q)}`, { signal: ctrl.signal, headers: { Accept: 'application/json' } })
        .then((r) => r.json()).then((data) => { render(q, data); open(true); })
        .catch(() => {});
    };

    input.addEventListener('focus', () => { if (!input.value.trim()) renderEmpty(); open(true); });
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(query, 110); });
    input.addEventListener('keydown', (e) => {
      const list = items();
      const i = list.findIndex((el) => el.classList.contains('is-active'));
      if (e.key === 'ArrowDown') { e.preventDefault(); setActive(Math.min(list.length - 1, i + 1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(Math.max(0, i - 1)); }
      else if (e.key === 'Enter' && i >= 0) { e.preventDefault(); recent.add(input.value); location.href = list[i].href; }
      else if (e.key === 'Escape') { input.blur(); open(false); }
    });
    form.addEventListener('submit', () => recent.add(input.value));
    box.addEventListener('mousedown', (e) => { if (e.target.closest('.suggest__item')) recent.add(input.value); });
    d.addEventListener('click', (e) => { if (!form.contains(e.target)) open(false); });
  });

  d.addEventListener('keydown', (e) => {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || /input|textarea|select/i.test(e.target.tagName) || e.target.isContentEditable) return;
    const s = $('.search__input');
    if (s) { e.preventDefault(); s.focus(); s.select(); }
  });

  /* ------------------------------------------------ liste: filtreler */
  const results = () => $('#sonuc');
  let navCtrl = null;
  const go = (url, push = true) => {
    const main = results();
    if (!main) { location.href = url; return; }
    if (navCtrl) navCtrl.abort();
    navCtrl = new AbortController();
    main.classList.add('is-loading');
    fetch(url, { signal: navCtrl.signal, headers: { 'X-Kunye-Partial': '1' } })
      .then((r) => r.text().then((html) => [r, html]))
      .then(([r, html]) => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const next = $('#sonuc', doc);
        if (!next) { location.href = r.url; return; }
        const openState = $$('details.f', main).reduce((m, el) => (m[el.dataset.f] = el.open, m), {});
        main.replaceWith(next);
        $$('details.f', next).forEach((el) => { if (el.dataset.f in openState && !el.querySelector('.f__badge')) el.open = openState[el.dataset.f]; });
        d.title = doc.title;
        if (push) history.pushState({ k: 1 }, '', r.url);
        initRanges();
      })
      .catch((err) => { if (err.name !== 'AbortError') location.href = url; });
  };

  const submitFilters = (form) => {
    const fd = new FormData(form);
    const sort = $('[data-sort]');
    if (sort) fd.set('sirala', sort.value);
    const params = new URLSearchParams();
    for (const [k, v] of fd) if (v !== '') params.append(k, v);
    const qs = params.toString();
    go(form.getAttribute('action') + (qs ? '?' + qs : ''));
  };

  d.addEventListener('change', (e) => {
    const form = e.target.closest('form[data-filters]');
    if (form && !e.target.matches('[data-fsearch],[data-osearch],[data-lo],[data-hi]')) submitFilters(form);
    if (e.target.matches('[data-sort]')) { const f = $('form[data-filters]'); if (f) submitFilters(f); }
  });
  d.addEventListener('submit', (e) => {
    if (e.target.matches('form[data-filters]')) { e.preventDefault(); submitFilters(e.target); }
  });
  d.addEventListener('click', (e) => {
    const a = e.target.closest('#sonuc .chip__x, #sonuc .clear, #sonuc .empty a, #sonuc .pager a[rel=prev]');
    if (a && !e.metaKey && !e.ctrlKey) { e.preventDefault(); go(a.href); }
    const more = e.target.closest('#sonuc [data-more]');
    if (more && !e.metaKey && !e.ctrlKey) {
      e.preventDefault();
      more.textContent = 'Yükleniyor…';
      fetch(more.href).then((r) => r.text()).then((html) => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const rows = $('[data-rows]', doc), pager = $('#sonuc .pager', doc);
        $('[data-rows]').append(...(rows ? rows.children : []));
        const cur = $('#sonuc .pager');
        if (pager) cur.replaceWith(pager); else cur.remove();
        const p = $('#sonuc .pager .is-on'); if (p) p.remove();
        const prev = $('#sonuc .pager [rel=prev]'); if (prev) prev.remove();
      }).catch(() => { location.href = more.href; });
    }
  });
  window.addEventListener('popstate', () => { if (results()) go(location.href, false); });

  // özellik ve seçenek arama
  d.addEventListener('input', (e) => {
    if (e.target.matches('[data-fsearch]')) {
      const q = fold(e.target.value);
      $$('details.f').forEach((el) => { el.hidden = q !== '' && !fold(el.dataset.f).includes(q); });
      $$('details.f-rest').forEach((el) => { if (q) el.open = true; });
    }
    if (e.target.matches('[data-osearch]')) {
      const q = fold(e.target.value);
      const body = e.target.closest('.f__body');
      $$('.check', body).forEach((el) => { el.hidden = q !== '' && !fold(el.textContent).includes(q); });
      const more = $('.f__opts-more', body); if (more && q) more.open = true;
    }
  });

  // aralık çubuğu: farklı değerler arasında adım adım
  const initRanges = () => {
    $$('[data-range]').forEach((r) => {
      if (r.dataset.ready) return;
      r.dataset.ready = '1';
      const vals = (r.dataset.values || '').split(',').filter(Boolean).map(Number);
      const lo = $('[data-lo]', r), hi = $('[data-hi]', r), min = $('[data-min]', r), max = $('[data-max]', r);
      const track = $('.range__track', r);
      if (!lo || !hi || vals.length < 2) return;
      const n = vals.length - 1;
      const fmt = (v) => String(v).replace('.', ',');
      const sync = (src) => {
        let a = +lo.value, b = +hi.value;
        if (a > b) { if (src === lo) lo.value = a = b; else hi.value = b = a; }
        track.style.setProperty('--from', (a / n * 100) + '%');
        track.style.setProperty('--to', (b / n * 100) + '%');
        min.value = a === 0 ? '' : fmt(vals[a]);
        max.value = b === n ? '' : fmt(vals[b]);
        $$('.hist span', r).forEach((s, i, all) => {
          const from = Math.floor(i * vals.length / all.length), to = Math.max(from, Math.floor((i + 1) * vals.length / all.length) - 1);
          s.classList.toggle('on', to >= a && from <= b && (a > 0 || b < n));
        });
      };
      [lo, hi].forEach((el) => {
        el.addEventListener('input', () => sync(el));
        el.addEventListener('change', () => { sync(el); submitFilters(r.closest('form')); });
      });
    });
  };
  initRanges();

  /* ------------------------------------------------ favori */
  d.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-fav]');
    if (!form) return;
    e.preventDefault();
    const btn = $('button', form);
    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' } })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((data) => {
        btn.classList.toggle('is-fav', data.favori);
        btn.setAttribute('aria-pressed', data.favori ? 'true' : 'false');
        const label = data.favori ? 'Favorilerden çıkar' : 'Favorilere ekle';
        btn.setAttribute('aria-label', label); btn.title = label;
        const span = $('span', btn); if (span) span.textContent = label;
      })
      .catch(() => form.submit());
  });

  /* ------------------------------------------------ galeri */
  d.addEventListener('click', (e) => {
    const t = e.target.closest('[data-pic]');
    if (!t) return;
    e.preventDefault();
    const box = $('[data-gallery]');
    box.innerHTML = t.dataset.pic;
    $$('.hero__thumbs a').forEach((a) => a.classList.toggle('is-on', a === t));
  });

  /* ------------------------------------------------ içindekiler: görünen grubu işaretle */
  const toc = $('.toc:not(.ptoc)');
  if (toc && 'IntersectionObserver' in window) {
    const links = $$('a', toc);
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (en.isIntersecting) links.forEach((a) => a.classList.toggle('is-on', a.hash === '#' + en.target.id));
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    links.forEach((a) => { const g = d.getElementById(a.hash.slice(1)); if (g) io.observe(g); });
  }

  /* ------------------------------------------------ seçim kutusu: var olan değerler + benzer uyarısı */
  const optionCache = {};
  const loadOptions = (url) => (optionCache[url] ??= fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json()).catch(() => []));

  window.kunye.combo = (input, { onPick } = {}) => {
    if (input.dataset.comboReady) return;
    input.dataset.comboReady = '1';
    const wrap = input.closest('.input') || input.parentElement;
    wrap.classList.add('combo');
    const list = d.createElement('span');
    list.className = 'combo__list';
    list.setAttribute('role', 'listbox');
    wrap.append(list);
    const warn = d.createElement('span');
    warn.className = 'combo__warn';
    warn.hidden = true;
    if (!onPick) {
      // uyarı, kutuyla aynı ızgara hücresinde kalsın
      const cell = d.createElement('div');
      cell.className = 'combo-cell';
      wrap.before(cell);
      cell.append(wrap, warn);
    }
    let opts = [], active = -1;

    const pick = (name) => {
      if (onPick) onPick(name); else { input.value = name; input.dispatchEvent(new Event('input', { bubbles: true })); }
      wrap.classList.remove('is-open');
      check();
    };
    const check = () => {
      const v = input.value.trim();
      if (!v || onPick) { warn.hidden = true; return; }
      const exact = opts.some((o) => fold(o.ad) === fold(v));
      const sims = exact ? [] : opts.filter((o) => similar(o.ad, v)).slice(0, 2);
      warn.hidden = !sims.length;
      if (sims.length) {
        warn.innerHTML = `Yeni değer. Benzeri var: ${sims.map((s) => `<i>${esc(s.ad)}</i> (${nf.format(s.n)} öğe) <button type="button" data-v="${esc(s.ad)}">Bunu kullan</button>`).join(' · ')}`;
      }
    };
    warn.addEventListener('click', (e) => { const b = e.target.closest('button[data-v]'); if (b) { input.value = b.dataset.v; input.dispatchEvent(new Event('input', { bubbles: true })); check(); } });

    const render = () => {
      const q = fold(input.value);
      const match = opts.filter((o) => !q || fold(o.ad).includes(q)).slice(0, 8);
      const exact = opts.some((o) => fold(o.ad) === q);
      list.innerHTML = match.map((o, i) => `<a href="#" data-v="${esc(o.ad)}" class="${i === active ? 'is-active' : ''}">${esc(o.ad)} <span class="n">${nf.format(o.n)} öğe</span></a>`).join('')
        + (q && !exact ? `<a href="#" class="new" data-v="${esc(input.value.trim())}">+ Yeni değer olarak ekle</a>` : '');
      wrap.classList.toggle('is-open', list.children.length > 0 && d.activeElement === input);
    };
    list.addEventListener('mousedown', (e) => { const a = e.target.closest('a[data-v]'); if (a) { e.preventDefault(); pick(a.dataset.v); } });
    input.addEventListener('focus', () => loadOptions(input.dataset.combo).then((o) => { opts = o; render(); }));
    input.addEventListener('input', () => { active = -1; render(); if (!onPick) warn.hidden = true; });
    input.addEventListener('blur', () => { setTimeout(() => wrap.classList.remove('is-open'), 120); check(); });
    input.addEventListener('keydown', (e) => {
      const links = $$('a', list);
      if (!wrap.classList.contains('is-open') || !links.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(links.length - 1, active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(0, active - 1); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); pick(links[active].dataset.v); return; }
      else if (e.key === 'Escape') { wrap.classList.remove('is-open'); return; }
      else return;
      links.forEach((a, i) => a.classList.toggle('is-active', i === active));
    });
    loadOptions(input.dataset.combo).then((o) => { opts = o; if (input.value) check(); });
  };
  $$('input[data-combo]').forEach((el) => window.kunye.combo(el));

  /* ------------------------------------------------ düzeltme önerisi */
  const propose = $('form[data-propose]');
  if (propose) {
    const specs = $$('.spec[data-ein]', propose);
    const sbar = $('[data-sbar]', propose), sbarN = $('[data-sbar-n]', propose);
    const sheet = $('[data-sheet]', propose), rlist = $('[data-rlist]', propose);
    const field = (spec) => $('input:not([type=radio]), textarea', spec);
    const norm = (v) => v.split(/\r?\n/).map((s) => s.trim()).filter(Boolean).join('\n');
    const current = (spec) => {
      const seg = $('.seg', spec);
      if (seg) { const c = $('input:checked', seg); return { now: c ? c.value : '', orig: seg.dataset.orig }; }
      const f = field(spec);
      return { now: norm(f.value), orig: norm(f.dataset.orig || '') };
    };
    const display = (spec) => {
      const seg = $('.seg', spec);
      if (seg) { const c = $('input:checked', seg); return c && c.value !== '' ? (c.value === '1' ? 'Var' : 'Yok') : 'bilinmiyor'; }
      const f = field(spec), unit = $('.unit', spec);
      const v = norm(f.value).replace(/\n/g, ', ');
      return v ? v + (unit ? ' ' + unit.textContent : '') : 'silinsin';
    };
    const update = () => {
      let n = 0;
      specs.forEach((s) => { const { now, orig } = current(s); const ch = now !== orig; s.classList.toggle('is-changed', ch); if (ch) n++; });
      const isNew = !!$('input[name=ad]', propose);
      if (sbar) { sbar.hidden = n === 0 && !isNew; sbarN.textContent = n; }
      return n;
    };
    const reset = (spec) => {
      const seg = $('.seg', spec);
      if (seg) $$('input', seg).forEach((r) => { r.checked = r.value === seg.dataset.orig; });
      else { const f = field(spec); f.value = f.dataset.orig || ''; }
      update();
    };
    propose.addEventListener('input', update);
    propose.addEventListener('change', update);
    propose.addEventListener('click', (e) => {
      const u = e.target.closest('[data-undo]');
      if (u) reset(u.closest('.spec'));
      if (e.target.closest('[data-review]')) {
        rlist.innerHTML = specs.filter((s) => s.classList.contains('is-changed')).map((s) => {
          const old = s.dataset.origText;
          return `<li data-for="${esc($('dt label', s).htmlFor)}"><span class="p">${esc(s.dataset.group)} › <b>${esc($('dt', s).textContent)}</b></span>`
            + `<span class="v2">${old ? `<span class="dold">${esc(old)}</span>` : '<span class="dempty">bilgi yok</span>'}${icon('arrow')}<span class="dnew">${esc(display(s))}</span></span>`
            + `<button type="button" class="icon-btn icon-btn--sm" aria-label="Kaldır" data-rm>${icon('trash')}</button></li>`;
        }).join('') || '<li><span class="p">Henüz bir değeri değiştirmedin.</span></li>';
        sheet.classList.add('is-open');
        if (sbar) sbar.hidden = true;
        const first = $('input[name=kaynak]', sheet); if (first) setTimeout(() => first.focus(), 30);
      }
      if (e.target.closest('[data-close]')) { sheet.classList.remove('is-open'); update(); }
      const rm = e.target.closest('[data-rm]');
      if (rm) {
        const li = rm.closest('li');
        const f = d.getElementById(li.dataset.for);
        if (f) reset(f.closest('.spec'));
        li.remove();
        if (!update() && !$('input[name=ad]', propose)) sheet.classList.remove('is-open');
      }
    });
    d.addEventListener('keydown', (e) => { if (e.key === 'Escape' && sheet.classList.contains('is-open')) { sheet.classList.remove('is-open'); update(); } });
    // gönder düğmesi yalnızca pencerede; sayfa içindeki Enter tuşu formu göndermesin
    propose.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && e.target.matches('.groups input')) { e.preventDefault(); e.target.blur(); }
    });
    sheet.classList.remove('is-open');
    update();
  }
})();
