(function () {
  const map = window.AmictaMap.init('map');
  const token = window.AmictaMap.token();
  let points = [];
  let line = null;

  // Preview a saved plan when its list item is clicked
  const saved = JSON.parse(document.getElementById('plans-data').textContent || '[]');

  function drawPoints() {
    if (line) line.setLatLngs(points);
    else if (points.length) line = L.polyline(points, { color: '#2563EB', weight: 4 }).addTo(map);
  }

  map.on('click', (e) => {
    points.push([e.latlng.lat, e.latlng.lng]);
    L.circleMarker([e.latlng.lat, e.latlng.lng], { color: '#2563EB', radius: 5, fillOpacity: 1 }).addTo(map);
    drawPoints();
  });

  document.getElementById('save-plan').addEventListener('click', () => {
    if (points.length < 2) { alert('Klik minimal 2 titik di peta dulu.'); return; }
    const name = prompt('Nama rencana rute?');
    if (!name) return;
    fetch('/map/plans', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ name, points }),
    }).then(() => location.reload());
  });

  document.getElementById('reset-plan').addEventListener('click', () => location.reload());

  document.querySelectorAll('[data-view-plan]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const plan = saved.find((p) => String(p.id) === btn.dataset.viewPlan);
      if (!plan) return;
      const pts = plan.points_json;
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
