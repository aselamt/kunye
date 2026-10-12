/* künye — panel. Tüm formlar JS olmadan da çalışır; bu dosya sürükle-bırak, jetonlar ve anlık yükleme ekler. */
(() => {
  'use strict';
  const d = document;
  const { $, $$, esc, icon } = window.kunye;
  const csrf = d.body.dataset.csrf;
  const post = (url, data = {}) => {
    const fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    fd.set('_csrf', csrf);
    return fetch(url, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' } });
  };

  /* süzme formları: seçim değişince gönder */
  $$('form[data-autosubmit]').forEach((f) => {
    f.addEventListener('change', (e) => {
      if (e.target.matches('select')) {
        if (e.target.dataset.reset) { const other = f.elements[e.target.dataset.reset]; if (other) other.disabled = true; }
        f.submit();
      }
    });
  });

  /* "Ekle" düğmeleri sayfayı ilgili gruba geri kaydırsın */
  d.addEventListener('click', (e) => {
    const b = e.target.closest('[data-anchor]');
    if (b) { const f = b.form; if (f && f.elements._ankor) f.elements._ankor.value = b.dataset.anchor; }
  });

  /* ------------------------------------------------ çoklu seçim: jetonlar */
  $$('textarea.tokens-src').forEach((ta) => {
    const box = d.createElement('div');
    box.className = 'tokens';
    const input = d.createElement('input');
    input.placeholder = 'Ekle…';
    input.setAttribute('aria-label', (ta.getAttribute('aria-label') || '') + ' — değer ekle');
    if (ta.dataset.combo) input.dataset.combo = ta.dataset.combo;
    const values = () => ta.value.split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
    const write = (list) => { ta.value = list.join('\n'); ta.dispatchEvent(new Event('change', { bubbles: true })); render(); };
    const add = (v) => {
      v = v.trim(); if (!v) return;
      const list = values();
      if (!list.some((x) => window.kunye.fold(x) === window.kunye.fold(v))) list.push(v);
      write(list); input.value = '';
    };
    const render = () => {
      $$('.token', box).forEach((t) => t.remove());
      values().forEach((v, i) => {
        const t = d.createElement('span');
        t.className = 'token';
        t.innerHTML = `${esc(v)}<button type="button" aria-label="${esc(v)} kaldır">${icon('close')}</button>`;
        t.querySelector('button').addEventListener('click', () => { const l = values(); l.splice(i, 1); write(l); });
        box.insertBefore(t, input);
      });
    };
    input.addEventListener('keydown', (e) => {
      if ((e.key === 'Enter' || e.key === ',') && !box.querySelector('.combo.is-open .is-active')) { e.preventDefault(); add(input.value); }
      else if (e.key === 'Backspace' && !input.value) { const l = values(); l.pop(); write(l); }
    });
    input.addEventListener('blur', () => setTimeout(() => add(input.value), 150));
    box.append(input);
    ta.hidden = true;
    ta.after(box);
    render();
    if (input.dataset.combo) {
      const wrap = d.createElement('span');
      wrap.className = 'input';
      wrap.style.cssText = 'flex:1;min-width:120px;border:0;padding:0;min-height:28px;box-shadow:none;background:none';
      input.replaceWith(wrap);
      wrap.append(input);
      window.kunye.combo(input, { onPick: add });
    }
  });

  /* ------------------------------------------------ görseller: sürükle-bırak yükleme */
  const drop = $('label.drop[data-drop]');
  const imgs = $('[data-imgs]');
  const refreshBadges = () => {
    $$('.imgc', imgs).forEach((c, i) => {
      let b = $('.imgc__badge', c);
      if (i === 0 && !b) { b = d.createElement('span'); b.className = 'imgc__badge'; b.textContent = 'Kapak'; $('.imgc__pic', c).append(b); }
      if (i > 0 && b) b.remove();
    });
  };
  const saveOrder = () => {
    refreshBadges();
    post(imgs.dataset.order, { sira: $$('.imgc[data-img]', imgs).map((c) => c.dataset.img).join(',') });
  };
  if (drop && imgs) {
    const file = $('input[type=file]', drop);
    const upload = (f) => {
      const card = d.createElement('figure');
      card.className = 'imgc imgc--busy';
      card.style.margin = '0';
      card.innerHTML = `<div class="imgc__pic">${icon('image')}</div><figcaption class="imgc__meta">${esc(f.name)}<div class="bar"><span style="width:0%"></span></div></figcaption>`;
      imgs.append(card);
      const bar = $('.bar span', card);
      const fd = new FormData();
      fd.append('gorsel', f);
      fd.append('_csrf', csrf);
      const xhr = new XMLHttpRequest();
      xhr.open('POST', drop.dataset.drop);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'fetch');
      xhr.upload.onprogress = (e) => { if (e.lengthComputable) bar.style.width = Math.round(e.loaded / e.total * 90) + '%'; };
      xhr.onload = () => {
        let data = {};
        try { data = JSON.parse(xhr.responseText); } catch { /* */ }
        if (data.gorseller && data.gorseller.length) {
          const tmp = d.createElement('div');
          tmp.innerHTML = data.gorseller[0].html;
          card.replaceWith(tmp.firstElementChild);
          refreshBadges();
        } else {
          $('.imgc__meta', card).innerHTML = `<span style="color:var(--bad)">${esc((data.hatalar || [data.hata || 'Yüklenemedi'])[0])}</span>`;
          card.classList.remove('imgc--busy');
          setTimeout(() => card.remove(), 6000);
        }
      };
      xhr.onerror = () => { $('.imgc__meta', card).textContent = 'Bağlantı hatası'; };
      xhr.send(fd);
    };
    const handle = (files) => { Array.from(files).forEach(upload); file.value = ''; };
    file.addEventListener('change', () => handle(file.files));
    ['dragenter', 'dragover'].forEach((t) => drop.addEventListener(t, (e) => { if (e.dataTransfer.types.includes('Files')) { e.preventDefault(); drop.classList.add('is-over'); } }));
    ['dragleave', 'drop'].forEach((t) => drop.addEventListener(t, () => drop.classList.remove('is-over')));
    drop.addEventListener('drop', (e) => { if (e.dataTransfer.files.length) { e.preventDefault(); handle(e.dataTransfer.files); } });

    // sıralama
    let dragged = null;
    imgs.addEventListener('dragstart', (e) => { dragged = e.target.closest('.imgc[data-img]'); if (dragged) dragged.classList.add('is-drag'); });
    imgs.addEventListener('dragend', () => { if (dragged) dragged.classList.remove('is-drag'); $$('.is-over', imgs).forEach((c) => c.classList.remove('is-over')); dragged = null; });
    imgs.addEventListener('dragover', (e) => {
      const over = e.target.closest('.imgc[data-img]');
      if (!dragged || !over || over === dragged) return;
      e.preventDefault();
      const r = over.getBoundingClientRect();
      over.parentNode.insertBefore(dragged, e.clientX < r.left + r.width / 2 ? over : over.nextSibling);
    });
    imgs.addEventListener('drop', (e) => { if (dragged) { e.preventDefault(); saveOrder(); } });

    // sil / kapak: sayfayı yenilemeden
    imgs.addEventListener('click', (e) => {
      const b = e.target.closest('.imgc__tools button[formaction]');
      if (!b) return;
      e.preventDefault();
      const card = b.closest('.imgc');
      post(b.getAttribute('formaction')).then(() => {
        if (b.hasAttribute('data-img-del')) card.remove(); else { imgs.prepend(card); }
        refreshBadges();
        saveOrder();
      });
    });
  }

  /* ------------------------------------------------ şablon: grup ve özellikleri sürükleyerek sırala */
  const sortable = (container, itemSel, handleSel, after) => {
    let dragged = null;
    container.addEventListener('mousedown', (e) => {
      const h = e.target.closest(handleSel);
      const item = h && h.closest(itemSel);
      if (item && item.parentElement === container) item.draggable = true;
    });
    container.addEventListener('dragstart', (e) => {
      const item = e.target.closest(itemSel);
      if (!item || item.parentElement !== container || !item.draggable) return;
      dragged = item; item.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move';
      e.stopPropagation();
    });
    container.addEventListener('dragover', (e) => {
      if (!dragged) return;
      const over = e.target.closest(itemSel);
      if (!over || over === dragged || over.parentElement !== container) return;
      e.preventDefault();
      const r = over.getBoundingClientRect();
      container.insertBefore(dragged, e.clientY < r.top + r.height / 2 ? over : over.nextSibling);
    });
    container.addEventListener('dragend', () => {
      if (!dragged) return;
      dragged.classList.remove('is-drag'); dragged.draggable = false; dragged = null;
      after();
    });
  };
  const groups = $('[data-groups]');
  if (groups) {
    sortable(groups, '[data-group]', '[data-grip]', () => $$('[data-gsort]', groups).forEach((el, i) => { el.value = i + 1; }));
    $$('[data-props]', groups).forEach((list) => sortable(list, '[data-prop]', '[data-grip]', () => $$('[data-psort]', list).forEach((el, i) => { el.value = i + 1; })));
  }

  /* ------------------------------------------------ değerler: seçim çubuğu */
  const vform = $('form[data-values]');
  if (vform) {
    const bar = $('[data-mbar]', vform);
    const upd = () => {
      if (!bar) return;
      const sel = $$('input[name="sec[]"]:checked', vform);
      bar.hidden = sel.length < 2;
      const n = sel.reduce((s, c) => s + (+c.dataset.n || 0), 0);
      $('[data-mbar-n]', bar).textContent = `${sel.length} değer`;
      $('[data-mbar-items]', bar).textContent = `seçili · ${n} kullanım etkilenecek`;
      const target = $('[data-target]', bar);
      const keys = sel.map((c) => c.value);
      $$('option', target).forEach((o) => { if (o.value) o.hidden = !keys.includes(o.value); });
      if (target.value && !keys.includes(target.value)) target.value = '';
    };
    vform.addEventListener('change', upd);
    vform.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && e.target.matches('.vname input')) { e.preventDefault(); $('button[name=adlandir]', e.target.closest('.vname')).click(); }
    });
    upd();
  }

  /* ------------------------------------------------ öneri kutusu */
  const review = $('form[data-review]');
  if (review) {
    const approve = $('[data-approve]', review);
    const upd = () => {
      const boxes = $$('input[name^="ok["]', review);
      boxes.forEach((c) => c.closest('[data-drow]').classList.toggle('is-off', !c.checked));
      const n = boxes.filter((c) => c.checked).length;
      if (approve) { approve.textContent = `Seçilenleri onayla (${n})`; approve.disabled = n === 0; }
    };
    review.addEventListener('change', upd);
    review.addEventListener('click', (e) => {
      const all = e.target.closest('[data-check-all]');
      if (all) { e.preventDefault(); const boxes = $$('input[name^="ok["]', review); const on = !boxes.every((c) => c.checked); boxes.forEach((c) => { c.checked = on; }); upd(); }
      const use = e.target.closest('[data-use]');
      if (use) {
        e.preventDefault();
        const row = use.closest('[data-drow]');
        const det = $('details.dedit', row);
        det.open = true;
        const f = $('input, textarea', det);
        if (f.tagName === 'TEXTAREA') f.value = f.value.split('\n').map((l) => (window.kunye.fold(l) === window.kunye.fold(use.dataset.for) ? use.dataset.use : l)).join('\n');
        else f.value = use.dataset.use;
        use.closest('.dwarn').innerHTML = `<span><b>Düzeltildi:</b> ${esc(use.dataset.use)} kullanılacak.</span>`;
      }
    });
    upd();
  }

  /* bağlantıyı kopyala */
  d.addEventListener('click', (e) => {
    const b = e.target.closest('[data-copy-btn]');
    if (!b) return;
    const inp = $('[data-copy]', b.parentElement);
    inp.select();
    (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.reject()).then(() => { b.textContent = 'Kopyalandı'; }).catch(() => d.execCommand('copy'));
  });
})();
