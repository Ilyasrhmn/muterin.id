(function () {
  const map = window.AmictaMap.init('map');
  const token = window.AmictaMap.token();
  const $ = (id) => document.getElementById(id);

  const CAT = {
    sepi:   { label: 'Jalan Sepi',        color: '#D97706', icon: 'fa-road' },
    gelap:  { label: 'Penerangan Minim',  color: '#6366F1', icon: 'fa-lightbulb' },
    rawan:  { label: 'Rawan Kriminal',    color: '#DC2626', icon: 'fa-triangle-exclamation' },
    rusak:  { label: 'Jalan Rusak',       color: '#78716C', icon: 'fa-road-barrier' },
    banjir: { label: 'Rawan Banjir',      color: '#0EA5E9', icon: 'fa-water' },
    momen:  { label: 'Momen',             color: '#0F766E', icon: 'fa-camera' },
  };
  const TIME = { siang: 'Siang', malam: 'Malam', kapanpun: 'Kapan pun' };

  let pins = [];
  let markers = new Map(); // id -> layer
  let picked = null;       // {lat, lng} lokasi yang sedang ditandai
  let filter = '';
  let hasFitted = false;   // fitTo once when pins first arrive, so user pans/zooms freely after

  function catColor(c) { return (CAT[c] || {}).color || '#64748B'; }
  function catLabel(c) { return (CAT[c] || {}).label || c; }
  function catIcon(c) { return (CAT[c] || {}).icon || 'fa-location-dot'; }

  function pinIcon(category) {
    const color = catColor(category);
    return L.divIcon({
      html: `<div class="flex items-center justify-center w-9 h-9 rounded-full shadow-lg border-2 bg-white" style="border-color:${color}">
               <i class="fas ${catIcon(category)}" style="color:${color}"></i>
             </div>`,
      className: 'custom-pin-marker',
      iconSize: [36, 36],
      iconAnchor: [18, 36],
      popupAnchor: [0, -36],
    });
  }

  // Kartu ringkas untuk hover: foto (jika ada) + judul + kategori.
  function tooltipHtml(p) {
    const photo = p.photo_url
      ? `<img src="${esc(p.photo_url)}" alt="" style="width:100%;height:80px;object-fit:cover;border-radius:6px;margin-bottom:6px">` : '';
    return `
      <div style="min-width:160px;max-width:190px">
        ${photo}
        <span style="display:inline-block;font-size:9px;font-weight:700;color:#fff;background:${catColor(p.category)};padding:1px 7px;border-radius:999px">${esc(catLabel(p.category))}</span>
        <p style="font-weight:700;font-size:13px;color:#0F172A;margin:4px 0 0">${esc(p.title)}</p>
      </div>`;
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (m) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }

  // --- Popup kartu titik ---
  function popupHtml(p) {
    const photo = p.photo_url
      ? `<img src="${esc(p.photo_url)}" alt="" style="width:100%;height:110px;object-fit:cover;border-radius:8px;margin-bottom:8px">` : '';
    const who = p.contributor ? `Ditandai oleh ${esc(p.contributor)}` : 'Ditandai oleh pengguna anonim';
    const del = p.is_mine
      ? `<button data-act="del" style="font-size:11px;color:#B91C1C;background:none;border:0;cursor:pointer;padding:0;margin-top:6px">Hapus titik</button>` : '';
    return `
      <div style="min-width:210px;max-width:230px">
        ${photo}
        <span style="display:inline-block;font-size:10px;font-weight:700;color:#fff;background:${catColor(p.category)};padding:2px 8px;border-radius:999px">${esc(catLabel(p.category))}</span>
        <p style="font-weight:700;font-size:14px;color:#0F172A;margin:6px 0 2px">${esc(p.title)}</p>
        ${p.description ? `<p style="font-size:12px;color:#475569;margin:0 0 4px">${esc(p.description)}</p>` : ''}
        <p style="font-size:11px;color:#64748B;margin:0">Berlaku: ${esc(TIME[p.time_context] || p.time_context)}</p>
        <p style="font-size:11px;color:#64748B;margin:2px 0 8px">${who}</p>
        <div style="display:flex;align-items:center;gap:6px">
          <button data-act="yes" style="flex:1;font-size:11px;font-weight:600;padding:6px;border-radius:8px;border:0;cursor:pointer;background:#ECFDF5;color:#047857">Masih di sini</button>
          <button data-act="no" style="flex:1;font-size:11px;font-weight:600;padding:6px;border-radius:8px;border:0;cursor:pointer;background:#FEF2F2;color:#B91C1C">Udah nggak</button>
        </div>
        <p style="font-size:11px;color:#64748B;margin:6px 0 0" data-count>Dikonfirmasi ${p.confirm_count} orang</p>
        ${del}
      </div>`;
  }

  function openPinPopup(p, latlng) {
    const el = document.createElement('div');
    el.innerHTML = popupHtml(p);
    const vote = (still) => {
      fetch(`/peta/komunitas/${p.id}/confirm`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        body: JSON.stringify({ still_there: still }),
      }).then((r) => r.json()).then((b) => {
        p.confirm_count = b.confirm_count;
        el.querySelector('[data-count]').textContent = `Dikonfirmasi ${b.confirm_count} orang`;
      });
    };
    el.querySelector('[data-act="yes"]').onclick = () => vote(true);
    el.querySelector('[data-act="no"]').onclick = () => vote(false);
    const delBtn = el.querySelector('[data-act="del"]');
    if (delBtn) {
      delBtn.onclick = async () => {
        const ok = await window.AmictaDialog.confirm('Hapus titik ini?', { danger: true, confirmText: 'Hapus' });
        if (!ok) return;
        fetch(`/peta/komunitas/${p.id}`, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
        }).then(() => { map.closePopup(); refresh(); });
      };
    }
    L.popup({ maxWidth: 250 }).setLatLng(latlng).setContent(el).openOn(map);
  }

  // --- Render markers + list ---
  function render() {
    const shown = pins.filter((p) => !filter || p.category === filter);
    const keep = new Set(shown.map((p) => p.id));

    markers.forEach((layer, id) => {
      if (!keep.has(id)) { layer.remove(); markers.delete(id); }
    });

    shown.forEach((p) => {
      if (markers.has(p.id)) return;
      const layer = L.marker([p.lat, p.lng], { icon: pinIcon(p.category) }).addTo(map);
      layer.bindTooltip(tooltipHtml(p), { direction: 'top', offset: [0, -30], className: 'community-pin-tooltip' });
      layer.on('click', () => openPinPopup(p, [p.lat, p.lng]));
      markers.set(p.id, layer);
    });

    if (!hasFitted && shown.length) {
      hasFitted = true;
      window.AmictaMap.fitTo(map, shown.map((p) => [p.lat, p.lng]));
    }

    const list = $('pin-list');
    list.innerHTML = '';
    if (!shown.length) {
      list.innerHTML = '<div class="p-6 text-center text-sm text-muted-fg">Belum ada titik.</div>';
      return;
    }
    shown.forEach((p) => {
      const row = document.createElement('button');
      row.type = 'button';
      row.className = 'block w-full text-left p-3 rounded-xl hover:bg-muted/60 transition';
      row.innerHTML =
        `<div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:${catColor(p.category)}"></span>` +
        `<p class="font-bold text-sm text-foreground truncate">${esc(p.title)}</p></div>` +
        `<p class="text-[11px] text-muted-fg mt-0.5 ml-4.5">${esc(catLabel(p.category))} · ${p.contributor ? esc(p.contributor) : 'anonim'} · ${p.confirm_count} konfirmasi</p>`;
      row.onclick = () => { map.setView([p.lat, p.lng], 15); openPinPopup(p, [p.lat, p.lng]); };
      list.appendChild(row);
    });
  }

  // --- Fetch / refresh ---
  function refresh() {
    fetch('/peta/komunitas/data', { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((b) => { pins = b.pins || []; render(); })
      .catch(() => {});
  }

  // --- Add-pin flow ---
  function pickLocation(lat, lng) {
    picked = { lat, lng };
    $('add-coords').textContent = `Lokasi: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    $('add-error').classList.add('hidden');
    $('add-form').classList.remove('hidden');
    $('add-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  map.on('click', (e) => pickLocation(e.latlng.lat, e.latlng.lng));

  $('btn-my-location').onclick = () => {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition((pos) => {
      const { latitude, longitude } = pos.coords;
      map.setView([latitude, longitude], 15);
      pickLocation(latitude, longitude);
    });
  };

  $('add-cancel').onclick = () => { picked = null; $('add-form').classList.add('hidden'); };

  $('add-submit').onclick = () => {
    if (!picked) return;
    const title = $('f-title').value.trim();
    if (!title) { showAddError('Judul wajib diisi.'); return; }

    const fd = new FormData();
    fd.append('category', $('f-category').value);
    fd.append('lat', picked.lat);
    fd.append('lng', picked.lng);
    fd.append('title', title);
    fd.append('description', $('f-description').value.trim());
    fd.append('time_context', $('f-time').value);
    fd.append('is_anonymous', $('f-anon').checked ? 1 : 0);
    if ($('f-photo').files[0]) fd.append('photo', $('f-photo').files[0]);

    $('add-submit').disabled = true;
    fetch('/peta/komunitas', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: fd,
    })
      .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        if (!ok) { showAddError(firstError(body) || 'Gagal menyimpan titik.'); return; }
        resetForm();
        refresh();
      })
      .catch(() => showAddError('Gagal menyimpan titik. Coba lagi.'))
      .finally(() => { $('add-submit').disabled = false; });
  };

  function firstError(body) {
    if (body && body.errors) return Object.values(body.errors)[0][0];
    return body && body.message;
  }
  function showAddError(msg) { const e = $('add-error'); e.textContent = msg; e.classList.remove('hidden'); }
  function resetForm() {
    picked = null;
    $('add-form').classList.add('hidden');
    ['f-title', 'f-description'].forEach((id) => { $(id).value = ''; });
    $('f-photo').value = '';
    $('f-anon').checked = false;
  }

  $('filter-category').onchange = (e) => { filter = e.target.value; render(); };

  // --- Boot ---
  refresh();
  setInterval(refresh, 30000); // ponytail: polling 30s; ganti ke WebSocket kalau perlu
})();
