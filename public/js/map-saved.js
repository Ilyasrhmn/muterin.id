(function () {
  const map = window.MuterinMap.init('map');
  const token = window.MuterinMap.token();
  const $ = (id) => document.getElementById(id);

  const ICONS = ['fa-star', 'fa-flag', 'fa-heart', 'fa-bookmark', 'fa-wrench', 'fa-mug-hot',
    'fa-house', 'fa-camera', 'fa-road', 'fa-mountain', 'fa-utensils', 'fa-gas-pump', 'fa-location-dot'];
  const COLORS = ['#F59E0B', '#0EA5E9', '#0F766E', '#DC2626', '#6366F1', '#DB2777', '#65A30D', '#78716C'];

  let lists = [];
  let places = [];
  let markers = new Map();
  let picked = null;
  let placeFormMode = 'new'; // 'new' | id tempat yang diedit
  let filter = '';            // '' = semua, atau id list
  let hasFitted = false;
  let listFormMode = null;    // 'new' | id list yang diedit
  let lfIcon = ICONS[0];
  let lfColor = COLORS[0];

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }
  function listById(id) { return lists.find((l) => l.id === id); }
  function listItems() { return lists.map((l) => ({ value: l.id, label: l.name, icon: l.icon, color: l.color })); }

  const pfListSelect = window.MuterinFoldSelect.render($('pf-list'), [], { placeholder: 'Pilih list…' });

  function placeIcon(color, icon) {
    return L.divIcon({
      html: `<div class="flex items-center justify-center w-9 h-9 rounded-full shadow-lg border-2 bg-white" style="border-color:${color}">
                <i class="fas ${icon}" style="color:${color}"></i></div>`,
      className: 'custom-pin-marker',
      iconSize: [36, 36], iconAnchor: [18, 36], popupAnchor: [0, -36],
    });
  }

  function tooltipHtml(p) {
    const photo = p.photo_url
      ? `<img src="${esc(p.photo_url)}" alt="" style="width:100%;height:80px;object-fit:cover;border-radius:6px;margin-bottom:6px">` : '';
    return `<div style="min-width:150px;max-width:190px">${photo}
      <span style="display:inline-block;font-size:9px;font-weight:700;color:#fff;background:${p.list_color || '#64748B'};padding:1px 7px;border-radius:999px">${esc(p.list_name || '')}</span>
      <p style="font-weight:700;font-size:13px;color:#0F172A;margin:4px 0 0">${esc(p.title)}</p>
</div>`;
  }

  // --- Popup penuh dengan edit/hapus ---
  function openPlacePopup(p, latlng) {
    const el = document.createElement('div');
    const photo = p.photo_url
      ? `<img src="${esc(p.photo_url)}" alt="" style="width:100%;height:110px;object-fit:cover;border-radius:8px;margin-bottom:8px">` : '';
    el.innerHTML = `<div style="min-width:210px;max-width:230px">${photo}
      <span style="display:inline-block;font-size:10px;font-weight:700;color:#fff;background:${p.list_color || '#64748B'};padding:2px 8px;border-radius:999px">${esc(p.list_name || '')}</span>
      <p style="font-weight:700;font-size:14px;color:#0F172A;margin:6px 0 2px">${esc(p.title)}</p>
      ${p.description ? `<p style="font-size:12px;color:#475569;margin:0 0 6px">${esc(p.description)}</p>` : ''}
      <div style="display:flex;gap:6px;margin-top:4px">
        <button data-act="edit" style="flex:1;font-size:11px;font-weight:600;padding:6px;border-radius:8px;border:0;cursor:pointer;background:#F1F5F9;color:#0F172A">Edit</button>
        <button data-act="del" style="flex:1;font-size:11px;font-weight:600;padding:6px;border-radius:8px;border:0;cursor:pointer;background:#FEF2F2;color:#B91C1C">Hapus</button>
      </div></div>`;
    el.querySelector('[data-act="edit"]').onclick = () => { map.closePopup(); editPlace(p); };
    el.querySelector('[data-act="del"]').onclick = async () => {
      const ok = await window.MuterinDialog.confirm('Hapus tempat ini?', { danger: true, confirmText: 'Hapus' });
      if (!ok) return;
      fetch(`/peta/titik/${p.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } })
        .then(() => { map.closePopup(); refresh(); });
    };
    L.popup({ maxWidth: 250 }).setLatLng(latlng).setContent(el).openOn(map);
  }

  // --- Render list manager ---
  function renderLists() {
    const box = $('list-manager');
    box.innerHTML = '';
    const allBtn = document.createElement('button');
    allBtn.type = 'button';
    allBtn.className = 'w-full text-left px-3 py-2 rounded-lg text-sm transition ' + (filter === '' ? 'bg-primary/10 text-primary font-semibold' : 'hover:bg-muted/60 text-foreground');
    allBtn.textContent = `Semua (${places.length})`;
    allBtn.onclick = () => { filter = ''; render(); };
    box.appendChild(allBtn);

    lists.forEach((l) => {
      const row = document.createElement('div');
      row.className = 'flex items-center gap-2 px-2 py-1.5 rounded-lg ' + (filter === l.id ? 'bg-primary/10' : 'hover:bg-muted/60');
      const pick = document.createElement('button');
      pick.type = 'button';
      pick.className = 'flex items-center gap-2 flex-1 min-w-0 text-left text-sm';
      pick.innerHTML = `<span class="flex items-center justify-center w-6 h-6 rounded-full shrink-0" style="background:${l.color}22"><i class="fas ${esc(l.icon)}" style="color:${l.color};font-size:11px"></i></span>
        <span class="truncate ${filter === l.id ? 'font-semibold text-primary' : 'text-foreground'}">${esc(l.name)}</span>
        <span class="text-[11px] text-muted-fg shrink-0">${l.place_count}</span>`;
      pick.onclick = () => { filter = l.id; render(); };
      row.appendChild(pick);

      if (!l.is_default) {
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'text-muted-fg hover:text-primary shrink-0 text-xs px-1';
        edit.innerHTML = '<i class="fas fa-pen"></i>';
        edit.onclick = () => openListForm(l);
        row.appendChild(edit);

        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'text-muted-fg hover:text-accent shrink-0 text-xs px-1';
        del.innerHTML = '<i class="fas fa-trash"></i>';
        del.onclick = async () => {
          const ok = await window.MuterinDialog.confirm(`Hapus list "${l.name}" beserta ${l.place_count} tempat di dalamnya?`, { danger: true, confirmText: 'Hapus' });
          if (!ok) return;
          fetch(`/peta/titik/lists/${l.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } })
            .then(() => { if (filter === l.id) filter = ''; refresh(); });
        };
        row.appendChild(del);
      }
      box.appendChild(row);
    });
  }

  // --- Render markers + daftar tempat ---
  function render() {
    renderLists();
    const shown = places.filter((p) => filter === '' || p.place_list_id === filter);
    const keep = new Set(shown.map((p) => p.id));

    markers.forEach((layer, id) => { if (!keep.has(id)) { layer.remove(); markers.delete(id); } });
    shown.forEach((p) => {
      if (markers.has(p.id)) return;
      const layer = L.marker([p.lat, p.lng], { icon: placeIcon(p.list_color || '#64748B', p.list_icon || 'fa-location-dot') }).addTo(map);
      layer.bindTooltip(tooltipHtml(p), { direction: 'top', offset: [0, -30], className: 'community-pin-tooltip' });
      layer.on('click', () => openPlacePopup(p, [p.lat, p.lng]));
      markers.set(p.id, layer);
    });

    if (!hasFitted && shown.length) { hasFitted = true; window.MuterinMap.fitTo(map, shown.map((p) => [p.lat, p.lng])); }

    const list = $('place-list');
    list.innerHTML = '';
    if (!shown.length) { list.innerHTML = '<div class="p-6 text-center text-sm text-muted-fg">Belum ada tempat.</div>'; return; }
    shown.forEach((p) => {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'block w-full text-left p-3 rounded-xl hover:bg-muted/60 transition';
      row.innerHTML = `<div class="flex items-center gap-2"><i class="fas ${esc(p.list_icon || 'fa-location-dot')}" style="color:${p.list_color || '#64748B'};font-size:12px"></i>
        <p class="font-bold text-sm text-foreground truncate">${esc(p.title)}</p></div>
        <p class="text-[11px] text-muted-fg mt-0.5 ml-5">${esc(p.list_name || '')}</p>`;
      row.onclick = () => { map.setView([p.lat, p.lng], 15); openPlacePopup(p, [p.lat, p.lng]); };
      list.appendChild(row);
    });
  }

  function refresh() {
    fetch('/peta/titik/data', { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((b) => {
        lists = b.lists || [];
        places = b.places || [];
        pfListSelect.setItems(listItems());
        render();
      })
      .catch(() => {});
  }

  // --- List form (buat/edit) ---
  function paintPickers() {
    $('lf-icons').innerHTML = '';
    ICONS.forEach((ic) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'w-8 h-8 rounded-lg border flex items-center justify-center ' + (ic === lfIcon ? 'border-primary bg-primary/10' : 'border-border');
      b.innerHTML = `<i class="fas ${ic}"></i>`;
      b.onclick = () => { lfIcon = ic; paintPickers(); };
      $('lf-icons').appendChild(b);
    });
    $('lf-colors').innerHTML = '';
    COLORS.forEach((c) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'w-8 h-8 rounded-full border-2 ' + (c === lfColor ? 'border-foreground' : 'border-transparent');
      b.style.background = c;
      b.onclick = () => { lfColor = c; paintPickers(); };
      $('lf-colors').appendChild(b);
    });
  }

  function openListForm(list) {
    listFormMode = list ? list.id : 'new';
    lfIcon = list ? list.icon : ICONS[0];
    lfColor = list ? list.color : COLORS[0];
    $('lf-name').value = list ? list.name : '';
    $('lf-error').classList.add('hidden');
    paintPickers();
    $('list-form').classList.remove('hidden');
  }

  $('btn-new-list').onclick = () => openListForm(null);
  $('lf-cancel').onclick = () => { listFormMode = null; $('list-form').classList.add('hidden'); };
  $('lf-save').onclick = () => {
    const name = $('lf-name').value.trim();
    if (!name) { showErr('lf-error', 'Nama list wajib diisi.'); return; }
    const isNew = listFormMode === 'new';
    fetch(isNew ? '/peta/titik/lists' : `/peta/titik/lists/${listFormMode}`, {
      method: isNew ? 'POST' : 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ name, icon: lfIcon, color: lfColor }),
    }).then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        if (!ok) { showErr('lf-error', firstError(body) || 'Gagal menyimpan list.'); return; }
        listFormMode = null; $('list-form').classList.add('hidden'); refresh();
      });
  };

  // --- Place form (tambah / edit) ---
  function pickLocation(lat, lng) {
    placeFormMode = 'new';
    picked = { lat, lng };
    $('pf-coords').textContent = `Lokasi: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    $('pf-error').classList.add('hidden');
    $('pf-title').value = '';
    $('pf-desc').value = '';
    $('pf-photo').value = '';
    $('pf-photo-wrap').classList.remove('hidden');
    pfListSelect.setSelected(lists[0] ? lists[0].id : null);
    $('place-form').classList.remove('hidden');
    $('place-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function editPlace(p) {
    placeFormMode = p.id;
    picked = { lat: p.lat, lng: p.lng };
    $('pf-coords').textContent = 'Edit tempat';
    $('pf-error').classList.add('hidden');
    $('pf-title').value = p.title;
    $('pf-desc').value = p.description || '';
    $('pf-photo').value = '';
    $('pf-photo-wrap').classList.add('hidden'); // foto tak diubah lewat edit (YAGNI)
    pfListSelect.setSelected(p.place_list_id);
    $('place-form').classList.remove('hidden');
    $('place-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  map.on('click', (e) => pickLocation(e.latlng.lat, e.latlng.lng));

  $('btn-my-location').onclick = () => {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition((pos) => {
      map.setView([pos.coords.latitude, pos.coords.longitude], 15);
      pickLocation(pos.coords.latitude, pos.coords.longitude);
    });
  };

  // --- Search lokasi ---
  let searchTimeout;
  const searchInput = $('search-location');
  const searchResults = $('search-results');

  searchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();
    
    if (query.length < 3) {
      searchResults.classList.add('hidden');
      return;
    }

    searchTimeout = setTimeout(() => {
      const mapCenter = map.getCenter();
      fetch(`/map/geocode/search?q=${encodeURIComponent(query)}&lat=${mapCenter.lat}&lng=${mapCenter.lng}`, {
        headers: { Accept: 'application/json' }
      })
        .then(r => r.json())
        .then(data => {
          if (!data.results || !data.results.length) {
            searchResults.innerHTML = '<div class="p-3 text-sm text-muted-fg">Tidak ada hasil</div>';
            searchResults.classList.remove('hidden');
            return;
          }

          searchResults.innerHTML = data.results.map(r => 
            `<button type="button" class="w-full text-left px-3 py-2 hover:bg-muted/60 text-sm border-b border-border last:border-0" data-lat="${r.lat}" data-lng="${r.lng}">
              <div class="font-semibold text-foreground">${esc(r.label)}</div>
            </button>`
          ).join('');

          searchResults.querySelectorAll('button').forEach(btn => {
            btn.onclick = () => {
              const lat = parseFloat(btn.dataset.lat);
              const lng = parseFloat(btn.dataset.lng);
              map.setView([lat, lng], 16);
              pickLocation(lat, lng);
              searchInput.value = '';
              searchResults.classList.add('hidden');
            };
          });

          searchResults.classList.remove('hidden');
        })
        .catch(() => {
          searchResults.classList.add('hidden');
        });
    }, 300);
  });

  // Hide search results saat klik di luar
  document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
      searchResults.classList.add('hidden');
    }
  });

  $('pf-cancel').onclick = () => { picked = null; placeFormMode = 'new'; $('place-form').classList.add('hidden'); };
  $('pf-save').onclick = () => {
    if (!picked) return;
    const title = $('pf-title').value.trim();
    if (!title) { showErr('pf-error', 'Nama tempat wajib diisi.'); return; }
    const isNew = placeFormMode === 'new';
    const done = () => {
      picked = null; placeFormMode = 'new';
      $('place-form').classList.add('hidden');
      ['pf-title', 'pf-desc'].forEach((id) => { $(id).value = ''; });
      $('pf-photo').value = '';
      refresh();
    };
    $('pf-save').disabled = true;

    let req;
    if (isNew) {
      const fd = new FormData();
      fd.append('place_list_id', pfListSelect.getSelected());
      fd.append('lat', picked.lat);
      fd.append('lng', picked.lng);
      fd.append('title', title);
      fd.append('description', $('pf-desc').value.trim());
      if ($('pf-photo').files[0]) fd.append('photo', $('pf-photo').files[0]);
      req = fetch('/peta/titik', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' }, body: fd });
    } else {
      req = fetch(`/peta/titik/${placeFormMode}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body: JSON.stringify({ place_list_id: pfListSelect.getSelected(), title, description: $('pf-desc').value.trim() || null }),
      });
    }

    req.then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        if (!ok) { showErr('pf-error', firstError(body) || 'Gagal menyimpan.'); return; }
        done();
      })
      .catch(() => showErr('pf-error', 'Gagal menyimpan. Coba lagi.'))
      .finally(() => { $('pf-save').disabled = false; });
  };

  function firstError(body) {
    if (body && body.errors) return Object.values(body.errors)[0][0];
    return body && body.message;
  }
  function showErr(id, msg) { const e = $(id); e.textContent = msg; e.classList.remove('hidden'); }

  refresh();
})();
