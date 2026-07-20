(function () {
  const map = window.AmictaMap.init('map');
  const token = window.AmictaMap.token();
  let points = [];
  let markers = [];
  let routeLine = null;
  let lastRoute = null;

  const saved = JSON.parse(document.getElementById('plans-data').textContent || '[]');
  const statusEl = document.getElementById('route-status');

  function setStatus(text, isError) {
    statusEl.textContent = text || '';
    statusEl.classList.toggle('text-accent', !!isError);
    statusEl.classList.toggle('text-muted-fg', !isError);
  }

  function computeRoute() {
    if (points.length < 2) return;
    setStatus('Menghitung rute...', false);
    fetch('/map/route', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ waypoints: points }),
    })
      .then((r) => r.json().then((body) => ({ ok: r.ok, body })))
      .then(({ ok, body }) => {
        if (!ok) {
          setStatus(body.error || 'Gagal menghitung rute jalan. Coba lagi sebentar.', true);
          lastRoute = null;
          return;
        }
        lastRoute = body;
        if (routeLine) routeLine.setLatLngs(body.geometry);
        else routeLine = L.polyline(body.geometry, { color: '#0F766E', weight: 4 }).addTo(map);
        setStatus(`${body.distance_km} km · ${body.duration_minutes} menit`, false);
      })
      .catch(() => {
        setStatus('Gagal menghitung rute jalan. Coba lagi sebentar.', true);
        lastRoute = null;
      });
  }

  map.on('click', (e) => {
    const p = [e.latlng.lat, e.latlng.lng];
    points.push(p);
    const isFirst = points.length === 1;
    const marker = L.circleMarker(p, { color: isFirst ? '#059669' : '#DC2626', radius: 6, fillOpacity: 1 }).addTo(map);
    markers.push(marker);
    computeRoute();
  });

  document.getElementById('save-plan').addEventListener('click', () => {
    if (!lastRoute) { alert('Klik minimal 2 titik di peta dan tunggu rute selesai dihitung dulu.'); return; }
    const name = prompt('Nama rencana rute?');
    if (!name) return;
    fetch('/map/plans', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({
        name,
        points,
        route_geometry: lastRoute.geometry,
        distance_km: lastRoute.distance_km,
        duration_minutes: lastRoute.duration_minutes,
      }),
    }).then(() => location.reload());
  });

  document.getElementById('reset-plan').addEventListener('click', () => location.reload());

  document.querySelectorAll('[data-view-plan]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const plan = saved.find((p) => String(p.id) === btn.dataset.viewPlan);
      if (!plan) return;
      const pts = plan.route_geometry_json || plan.points_json;
      L.polyline(pts, { color: '#EF4444', weight: 4, dashArray: '6 6' }).addTo(map);
      window.AmictaMap.fitTo(map, pts);
    });
  });

  document.querySelectorAll('[data-delete-plan]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!confirm('Hapus rencana ini?')) return;
      fetch(`/map/plans/${btn.dataset.deletePlan}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      }).then(() => location.reload());
    });
  });
})();
