(function () {
  const token = document.querySelector('input[name="_token"]').value;
  const map = L.map('map').setView([-6.2, 106.8], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);

  const colors = { moment: 'blue', hazard: 'red', quiet: 'green' };
  let planPoints = [], planLine = null;

  function loadData() {
    fetch('/map/data', { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then((d) => {
        d.pins.forEach((p) => L.circleMarker([p.lat, p.lng], { color: colors[p.category] })
          .bindPopup(`<b>${p.title}</b><br>${p.category}<br>${p.note ?? ''}<br>
            <a href="#" onclick="deletePin(${p.id});return false;">hapus</a>`).addTo(map));
        d.trips.forEach((t) => t.path_json && L.polyline(t.path_json, { color: 'purple', weight: 3 }).addTo(map));
      });
  }

  window.deletePin = (id) => fetch(`/map/pins/${id}`, {
    method: 'DELETE',
    headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
  }).then(() => location.reload());

  map.on('click', (e) => {
    const mode = document.getElementById('mode').value;
    if (mode === 'view') return;
    if (mode === 'plan') {
      planPoints.push([e.latlng.lat, e.latlng.lng]);
      if (planLine) planLine.setLatLngs(planPoints);
      else planLine = L.polyline(planPoints, { color: 'orange' }).addTo(map);
      return;
    }
    const title = prompt('Judul pin?');
    if (!title) return;
    const note = prompt('Catatan (opsional)?') || null;
    fetch('/map/pins', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ category: mode, lat: e.latlng.lat, lng: e.latlng.lng, title, note }),
    }).then(() => location.reload());
  });

  document.getElementById('save-plan').addEventListener('click', () => {
    if (planPoints.length < 2) {
      alert('Klik minimal 2 titik dulu.');
      return;
    }
    const name = prompt('Nama rencana rute?');
    if (!name) return;
    fetch('/map/plans', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ name, points: planPoints }),
    }).then(() => alert('Rencana disimpan.'));
  });

  loadData();
})();
