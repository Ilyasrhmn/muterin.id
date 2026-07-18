(function () {
  const map = window.AmictaMap.init('map');
  const token = window.AmictaMap.token();
  const catInput = document.getElementById('pin-category');

  fetch('/map/data', { headers: { Accept: 'application/json' } })
    .then((r) => r.json())
    .then((d) => {
      const pts = [];
      d.pins.forEach((p) => {
        L.circleMarker([p.lat, p.lng], {
          color: window.AmictaMap.categoryColor(p.category), fillOpacity: 0.7, radius: 8,
        }).bindPopup(`<b>${p.title}</b><br>${p.note ?? ''}`).addTo(map);
        pts.push([p.lat, p.lng]);
      });
      window.AmictaMap.fitTo(map, pts);
    });

  map.on('click', (e) => {
    const category = catInput.value;
    const title = prompt('Judul titik?');
    if (!title) return;
    const note = prompt('Catatan (opsional)?') || null;
    fetch('/map/pins', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ category, lat: e.latlng.lat, lng: e.latlng.lng, title, note }),
    }).then(() => location.reload());
  });

  document.querySelectorAll('[data-delete-pin]').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!confirm('Hapus titik ini?')) return;
      fetch(`/map/pins/${btn.dataset.deletePin}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      }).then(() => location.reload());
    });
  });
})();
