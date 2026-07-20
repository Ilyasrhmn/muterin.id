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

  map.on('click', async (e) => {
    const category = catInput.value;
    const result = await window.AmictaDialog.prompt('Judul titik?', {
      label: 'Judul',
      placeholder: 'mis. Jalan rusak',
      extra: { label: 'Catatan (opsional)', placeholder: 'Keterangan tambahan…' },
    });
    if (!result) return;
    fetch('/map/pins', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ category, lat: e.latlng.lat, lng: e.latlng.lng, title: result.value, note: result.extra || null }),
    }).then(() => location.reload());
  });

  document.querySelectorAll('[data-delete-pin]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const ok = await window.AmictaDialog.confirm('Hapus titik ini?', { danger: true, confirmText: 'Hapus' });
      if (!ok) return;
      fetch(`/map/pins/${btn.dataset.deletePin}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      }).then(() => location.reload());
    });
  });
})();
