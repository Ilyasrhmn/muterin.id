(function () {
  const map = window.AmictaMap.init('map');
  map.zoomControl.setPosition('topright');
  const token = window.AmictaMap.token();
  const saved = JSON.parse(document.getElementById('plans-data').textContent || '[]');

  const $ = (id) => document.getElementById(id);
  const statusEl = $('route-status');
  const infoPanel = $('info-panel');
  const routePanel = $('route-panel');
  const searchInput = $('search-input');
  const searchResults = $('search-results');

  [infoPanel, routePanel, searchResults, searchInput.closest('div')].forEach((el) => {
    if (el) L.DomEvent.disableClickPropagation(el);
  });

  let start = null;
  let end = null;
  let via = [];
  let startMarker = null;
  let endMarker = null;
  let viaMarkers = [];
  let routeLine = null;
  let lastRoute = null;
  let requestSeq = 0;
  let pending = null;

  function setStatus(text, isError) {
    statusEl.textContent = text || '';
    statusEl.classList.toggle('text-accent', !!isError);
    statusEl.classList.toggle('text-muted-fg', !isError);
  }

  function showInfo(loc) {
    pending = loc;
    routePanel.classList.add('hidden');
    $('info-label').textContent = loc.label;
    $('info-coords').textContent = `${loc.lat.toFixed(5)}, ${loc.lng.toFixed(5)}`;
    $('btn-add-via').classList.toggle('hidden', !(start && end));
    infoPanel.classList.remove('hidden');
  }

  function hideInfo() {
    infoPanel.classList.add('hidden');
    pending = null;
  }

  async function reverseGeocode(lat, lng) {
    const res = await fetch(`/map/geocode/reverse?lat=${lat}&lng=${lng}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error('reverse failed');
    return res.json();
  }

  map.on('click', async (e) => {
    const { lat, lng } = e.latlng;
    $('info-label').textContent = 'Memuat lokasi...';
    $('info-coords').textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    routePanel.classList.add('hidden');
    $('btn-add-via').classList.toggle('hidden', !(start && end));
    infoPanel.classList.remove('hidden');
    try {
      const loc = await reverseGeocode(lat, lng);
      showInfo(loc);
    } catch {
      showInfo({ label: 'Lokasi tanpa nama', lat, lng });
    }
  });

  function markerFor(loc, color) {
    return L.circleMarker([loc.lat, loc.lng], { color, radius: 7, fillOpacity: 1 }).addTo(map);
  }

  $('btn-set-start').addEventListener('click', () => {
    if (!pending) return;
    start = pending;
    if (startMarker) startMarker.remove();
    startMarker = markerFor(start, '#059669');
    hideInfo();
    maybeRoute();
  });

  $('btn-set-end').addEventListener('click', () => {
    if (!pending) return;
    end = pending;
    if (endMarker) endMarker.remove();
    endMarker = markerFor(end, '#DC2626');
    hideInfo();
    maybeRoute();
  });

  $('btn-add-via').addEventListener('click', () => {
    if (!pending || !(start && end)) return;
    via.push(pending);
    viaMarkers.push(markerFor(pending, '#F59E0B'));
    hideInfo();
    maybeRoute();
  });

  function maybeRoute() {
    if (!(start && end)) return;
    const waypoints = [start, ...via, end].map((p) => [p.lat, p.lng]);
    computeRoute(waypoints);
  }

  function computeRoute(waypoints) {
    const seq = ++requestSeq;
    setStatus('Menghitung rute...', false);
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
          return;
        }
        lastRoute = body;
        if (routeLine) routeLine.setLatLngs(body.geometry);
        else routeLine = L.polyline(body.geometry, { color: '#0F766E', weight: 5 }).addTo(map);
        setStatus('', false);
        showRoutePanel(body);
      })
      .catch(() => {
        if (seq !== requestSeq) return;
        setStatus('Gagal menghitung rute jalan. Coba lagi sebentar.', true);
        lastRoute = null;
      });
  }

  function fmtDuration(min) {
    if (min < 60) return `${min} menit`;
    const h = Math.floor(min / 60);
    const m = min % 60;
    return m ? `${h} jam ${m} menit` : `${h} jam`;
  }

  function showRoutePanel(route) {
    hideInfo();
    $('route-start-label').textContent = start.label;
    $('route-end-label').textContent = end.label;
    $('route-distance').textContent = `${route.distance_km} km`;
    $('route-duration').textContent = fmtDuration(route.duration_minutes);
    routePanel.classList.remove('hidden');
  }

  function runSearch() {
    const q = searchInput.value.trim();
    if (q.length < 2) return;
    const c = map.getCenter();
    setStatus('Mencari...', false);
    fetch(`/map/geocode/search?q=${encodeURIComponent(q)}&lat=${c.lat}&lng=${c.lng}`, { headers: { Accept: 'application/json' } })
      .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        setStatus('', false);
        if (!ok) {
          setStatus(body.error || 'Gagal mencari lokasi. Coba lagi sebentar.', true);
          return;
        }
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
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'block w-full text-left px-3 py-2.5 text-sm text-foreground hover:bg-muted transition';
      btn.textContent = loc.label;
      btn.addEventListener('click', () => {
        searchResults.classList.add('hidden');
        searchInput.value = loc.label;
        map.flyTo([loc.lat, loc.lng], 15);
        showInfo(loc);
      });
      searchResults.appendChild(btn);
    });
    searchResults.classList.remove('hidden');
  }

  $('search-btn').addEventListener('click', runSearch);
  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      runSearch();
    }
  });

  $('save-plan').addEventListener('click', async () => {
    if (!lastRoute) {
      await window.AmictaDialog.alert('Tentukan titik awal & tujuan dulu, tunggu rute selesai dihitung.');
      return;
    }
    const name = await window.AmictaDialog.prompt('Nama rencana rute?', { label: 'Nama', placeholder: 'mis. Rumah ke Kantor' });
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
  });

  $('reset-plan').addEventListener('click', () => location.reload());

  document.querySelectorAll('[data-view-plan]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const plan = saved.find((p) => String(p.id) === btn.dataset.viewPlan);
      if (!plan) return;
      const pts = plan.route_geometry_json || plan.points_json;
      if (routeLine) routeLine.remove();
      routeLine = L.polyline(pts, { color: '#EF4444', weight: 4, dashArray: '6 6' }).addTo(map);
      window.AmictaMap.fitTo(map, pts);
      hideInfo();
      $('route-start-label').textContent = plan.start_label || 'Titik Awal';
      $('route-end-label').textContent = plan.end_label || 'Titik Tujuan';
      $('route-distance').textContent = plan.distance_km ? `${plan.distance_km} km` : '';
      $('route-duration').textContent = plan.duration_minutes ? fmtDuration(plan.duration_minutes) : '';
      routePanel.classList.remove('hidden');
    });
  });

  document.querySelectorAll('[data-delete-plan]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const ok = await window.AmictaDialog.confirm('Hapus rencana ini?', { danger: true, confirmText: 'Hapus' });
      if (!ok) return;
      fetch(`/map/plans/${btn.dataset.deletePlan}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      }).then(() => location.reload());
    });
  });
})();
