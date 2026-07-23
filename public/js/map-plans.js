(function () {
  const map = window.MuterinMap.init('map');
  const token = window.MuterinMap.token();
  const saved = JSON.parse(document.getElementById('plans-data').textContent || '[]');
  const $ = (id) => document.getElementById(id);

  const searchInput = $('search-input');
  const searchResults = $('search-results');
  const statusEl = $('route-status');

  let start = null;      // {lat, lng, label}
  let end = null;
  let via = [];
  let startMarker = null;
  let endMarker = null;
  let viaMarkers = [];
  let routeLine = null;
  let lastRoute = null;
  let requestSeq = 0;
  let communityMarkers = [];

  const CAT_LABEL = {
    sepi: 'sepi', gelap: 'gelap', rawan: 'rawan', rusak: 'rusak', banjir: 'banjir', momen: 'momen',
  };
  const CAT_COLOR = {
    sepi: '#D97706', gelap: '#6366F1', rawan: '#DC2626', rusak: '#78716C', banjir: '#0EA5E9', momen: '#0F766E',
  };

  function clearCommunity() {
    communityMarkers.forEach((m) => m.remove());
    communityMarkers = [];
    const w = document.getElementById('community-warning');
    if (w) w.classList.add('hidden');
  }

  function checkCommunity(geometry) {
    clearCommunity();
    fetch('/peta/komunitas/near-route', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ geometry }),
    })
      .then((r) => (r.ok ? r.json() : null))
      .then((b) => {
        if (!b || !b.pins || !b.pins.length) return;
        const counts = {};
        b.pins.forEach((p) => {
          counts[p.category] = (counts[p.category] || 0) + 1;
          const color = CAT_COLOR[p.category] || '#64748B';
          const m = L.circleMarker([p.lat, p.lng], {
            color, radius: 7, fillColor: color, fillOpacity: 1, weight: 2,
          }).addTo(map).bindPopup(`<b>${p.title}</b><br>${CAT_LABEL[p.category] || p.category}`);
          communityMarkers.push(m);
        });
        const parts = Object.entries(counts).map(([c, n]) => `${n} ${CAT_LABEL[c] || c}`);
        const el = document.getElementById('community-warning');
        const txt = document.getElementById('community-warning-text');
        if (el && txt) {
          txt.textContent = `Rutemu lewat ${b.pins.length} titik komunitas (${parts.join(', ')}).`;
          el.classList.remove('hidden');
        }
      })
      .catch(() => {}); // ponytail: non-fatal, jangan ganggu alur rute
  }

  /**
   * Handle route location button click
   * @param {string} type - 'start' | 'end'
   */
  async function handleRouteLocationClick(type) {
    const btn = $(type === 'start' ? 'btn-current-location-start' : 'btn-current-location-end');
    const textEl = $(type === 'start' ? 'loc-start-text' : 'loc-end-text');
    const spinnerEl = $(type === 'start' ? 'loc-start-spinner' : 'loc-end-spinner');

    const resetBtn = () => {
      textEl.textContent = 'Gunakan Lokasi Saya';
      spinnerEl.classList.add('hidden');
      btn.disabled = false;
    };
    
    // Loading state
    textEl.textContent = 'Mengambil lokasi...';
    spinnerEl.classList.remove('hidden');
    btn.disabled = true;
    
    try {
      const pos = await window.MuterinGeolocation.getCurrentPosition();
      const loc = await reverseGeocode(pos.lat, pos.lng);
      loc.lat = pos.lat;
      loc.lng = pos.lng;
      
      if (type === 'start') {
        setStart(loc);
      } else {
        setEnd(loc);
      }
      
      map.flyTo([pos.lat, pos.lng], 14);
      
    } catch (error) {
      await window.MuterinDialog.alert(window.MuterinGeolocation.getErrorMessage(error));
    } finally {
      resetBtn();
    }
  }

  // Wire up current location buttons
  const btnStart = $('btn-current-location-start');
  const btnEnd = $('btn-current-location-end');
  if (btnStart) btnStart.onclick = () => handleRouteLocationClick('start');
  if (btnEnd) btnEnd.onclick = () => handleRouteLocationClick('end');

  function setStatus(text, isError) {
    statusEl.textContent = text || '';
    statusEl.classList.toggle('text-accent', !!isError);
    statusEl.classList.toggle('text-muted-fg', !isError);
  }

  function fmtDuration(min) {
    if (min < 60) return `${min} menit`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m ? `${h} jam ${m} menit` : `${h} jam`;
  }

  async function reverseGeocode(lat, lng) {
    try {
      const res = await fetch(`/map/geocode/reverse?lat=${lat}&lng=${lng}`, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('reverse failed');
      return await res.json();
    } catch {
      return { label: 'Lokasi tanpa nama', lat, lng };
    }
  }

  // --- Location action popup (RideLink-style "set as start / via / destination") ---
  function openLocationPopup(loc) {
    const el = document.createElement('div');
    el.style.minWidth = '190px';
    el.innerHTML = `
      <p style="font-weight:600;font-size:13px;color:#0F172A;margin:0 0 8px">${loc.label}</p>
      <div style="display:flex;flex-direction:column;gap:6px">
        <button data-act="start" style="text-align:left;font-size:12px;font-weight:600;padding:7px 10px;border-radius:8px;border:0;cursor:pointer;background:#ECFDF5;color:#047857">Jadikan Titik Awal</button>
        <button data-act="via" style="text-align:left;font-size:12px;font-weight:600;padding:7px 10px;border-radius:8px;border:0;cursor:pointer;background:#FFFBEB;color:#B45309;${(start && end) ? '' : 'display:none'}">Tambah Titik Singgah</button>
        <button data-act="end" style="text-align:left;font-size:12px;font-weight:600;padding:7px 10px;border-radius:8px;border:0;cursor:pointer;background:#FEF2F2;color:#B91C1C">Jadikan Titik Tujuan</button>
      </div>`;
    el.querySelector('[data-act="start"]').onclick = () => { setStart(loc); map.closePopup(); };
    el.querySelector('[data-act="end"]').onclick = () => { setEnd(loc); map.closePopup(); };
    el.querySelector('[data-act="via"]').onclick = () => { addVia(loc); map.closePopup(); };
    L.popup({ closeButton: true, autoClose: true }).setLatLng([loc.lat, loc.lng]).setContent(el).openOn(map);
  }

  map.on('click', async (e) => {
    const { lat, lng } = e.latlng;
    L.popup().setLatLng([lat, lng]).setContent('Memuat lokasi…').openOn(map);
    const loc = await reverseGeocode(lat, lng);
    openLocationPopup(loc);
  });

  // --- Markers ---
  function markerFor(loc, color) {
    return L.circleMarker([loc.lat, loc.lng], { color, radius: 8, fillColor: color, fillOpacity: 1, weight: 2 }).addTo(map);
  }

  function setStart(loc) {
    start = loc;
    if (startMarker) startMarker.remove();
    startMarker = markerFor(loc, '#059669');
    renderPanel();
    maybeRoute();
  }

  function setEnd(loc) {
    end = loc;
    if (endMarker) endMarker.remove();
    endMarker = markerFor(loc, '#DC2626');
    renderPanel();
    maybeRoute();
  }

  function addVia(loc) {
    via.push(loc);
    viaMarkers.push(markerFor(loc, '#F59E0B'));
    renderPanel();
    maybeRoute();
  }

  // --- Panel rendering ---
  function labelState(el, loc) {
    el.textContent = loc ? loc.label : 'Belum dipilih';
    el.classList.toggle('text-foreground', !!loc);
    el.classList.toggle('text-muted-fg', !loc);
  }

  function renderPanel() {
    labelState($('start-label'), start);
    labelState($('end-label'), end);
    $('clear-start').classList.toggle('hidden', !start);
    $('clear-end').classList.toggle('hidden', !end);

    const viaList = $('via-list');
    viaList.innerHTML = '';
    via.forEach((v, i) => {
      const row = document.createElement('div');
      row.className = 'flex items-center gap-3 p-3 rounded-xl bg-muted/50';
      row.innerHTML =
        '<span class="w-3 h-3 rounded-full bg-amber-500 shrink-0"></span>' +
        '<div class="flex-1 min-w-0"><p class="text-[10px] font-bold uppercase tracking-wider text-muted-fg">Titik Singgah</p>' +
        `<p class="text-sm text-foreground truncate">${v.label}</p></div>`;
      const rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'text-muted-fg hover:text-accent shrink-0 text-lg leading-none';
      rm.innerHTML = '&times;';
      rm.onclick = () => {
        via.splice(i, 1);
        viaMarkers[i].remove();
        viaMarkers.splice(i, 1);
        renderPanel();
        maybeRoute();
      };
      row.appendChild(rm);
      viaList.appendChild(row);
    });
  }

  $('clear-start').onclick = () => {
    start = null;
    if (startMarker) { startMarker.remove(); startMarker = null; }
    clearRoute();
    renderPanel();
  };

  $('clear-end').onclick = () => {
    end = null;
    if (endMarker) { endMarker.remove(); endMarker = null; }
    clearRoute();
    renderPanel();
  };

  function clearRoute() {
    if (routeLine) { routeLine.remove(); routeLine = null; }
    lastRoute = null;
    $('route-summary').classList.add('hidden');
    setStatus('', false);
    clearCommunity();
  }

  // --- Route ---
  function maybeRoute() {
    if (!(start && end)) { clearRoute(); return; }
    const waypoints = [start, ...via, end].map((p) => [p.lat, p.lng]);
    const seq = ++requestSeq;
    setStatus('Menghitung rute…', false);
    fetch('/map/route', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ waypoints }),
    })
      .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        if (seq !== requestSeq) return;
        if (!ok) {
          setStatus(body.error || 'Gagal menghitung rute jalan. Coba lagi sebentar.', true);
          lastRoute = null;
          $('route-summary').classList.add('hidden');
          return;
        }
        lastRoute = body;
        if (routeLine) routeLine.setLatLngs(body.geometry);
        else routeLine = L.polyline(body.geometry, { color: '#0F766E', weight: 5 }).addTo(map);
        window.MuterinMap.fitTo(map, body.geometry);
        setStatus('', false);
        $('route-distance').textContent = `${body.distance_km} km`;
        $('route-duration').textContent = fmtDuration(body.duration_minutes);
        $('route-summary').classList.remove('hidden');
        checkCommunity(body.geometry);
      })
      .catch(() => {
        if (seq !== requestSeq) return;
        setStatus('Gagal menghitung rute jalan. Coba lagi sebentar.', true);
        lastRoute = null;
      });
  }

  // --- Search ---
  function runSearch() {
    const q = searchInput.value.trim();
    if (q.length < 2) return;
    const c = map.getCenter();
    setStatus('Mencari…', false);
    fetch(`/map/geocode/search?q=${encodeURIComponent(q)}&lat=${c.lat}&lng=${c.lng}`, { headers: { Accept: 'application/json' } })
      .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        setStatus('', false);
        if (!ok) { setStatus(body.error || 'Gagal mencari lokasi. Coba lagi sebentar.', true); return; }
        renderResults(body.results || []);
      })
      .catch(() => setStatus('Gagal mencari lokasi. Coba lagi sebentar.', true));
  }

  function renderResults(results) {
    searchResults.innerHTML = '';
    if (!results.length) {
      searchResults.innerHTML = '<p class="px-3 py-2.5 text-sm text-muted-fg">Tidak ada hasil.</p>';
      searchResults.classList.remove('hidden');
      return;
    }
    results.forEach((loc) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'block w-full text-left px-3 py-2.5 text-sm text-foreground hover:bg-muted transition';
      b.textContent = loc.label;
      b.onclick = () => {
        searchResults.classList.add('hidden');
        searchInput.value = '';
        map.flyTo([loc.lat, loc.lng], 15);
        openLocationPopup(loc);
      };
      searchResults.appendChild(b);
    });
    searchResults.classList.remove('hidden');
  }

  $('search-btn').onclick = runSearch;
  searchInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); runSearch(); } });

  // --- Save / Reset ---
  $('save-plan').onclick = async () => {
    if (!lastRoute) {
      await window.MuterinDialog.alert('Tentukan titik awal & tujuan dulu, lalu tunggu rutenya selesai dihitung.');
      return;
    }
    const name = await window.MuterinDialog.prompt('Nama rencana rute?', { label: 'Nama', placeholder: 'mis. Rumah ke Kantor' });
    if (!name) return;
    const waypoints = [start, ...via, end].map((p) => [p.lat, p.lng]);
    fetch('/map/plans', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({
        name,
        points: waypoints,
        route_geometry: lastRoute.geometry,
        distance_km: lastRoute.distance_km,
        duration_minutes: lastRoute.duration_minutes,
        start_label: start.label,
        end_label: end.label,
      }),
    }).then(() => location.reload());
  };

  $('reset-plan').onclick = () => location.reload();

  // --- Saved plans preview ---
  document.querySelectorAll('[data-view-plan]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const plan = saved.find((p) => String(p.id) === btn.dataset.viewPlan);
      if (!plan) return;
      const pts = plan.route_geometry_json || plan.points_json;
      if (routeLine) routeLine.remove();
      routeLine = L.polyline(pts, { color: '#EF4444', weight: 4, dashArray: '6 6' }).addTo(map);
      window.MuterinMap.fitTo(map, pts);
      const startEl = $('start-label');
      const endEl = $('end-label');
      startEl.textContent = plan.start_label || 'Titik Awal';
      startEl.classList.add('text-foreground');
      startEl.classList.remove('text-muted-fg');
      endEl.textContent = plan.end_label || 'Titik Tujuan';
      endEl.classList.add('text-foreground');
      endEl.classList.remove('text-muted-fg');
      $('route-distance').textContent = plan.distance_km ? `${plan.distance_km} km` : '';
      $('route-duration').textContent = plan.duration_minutes ? fmtDuration(plan.duration_minutes) : '';
      $('route-summary').classList.remove('hidden');
    });
  });

  document.querySelectorAll('[data-delete-plan]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const ok = await window.MuterinDialog.confirm('Hapus rencana ini?', { danger: true, confirmText: 'Hapus' });
      if (!ok) return;
      fetch(`/map/plans/${btn.dataset.deletePlan}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      }).then(() => location.reload());
    });
  });

  renderPanel();
})();
