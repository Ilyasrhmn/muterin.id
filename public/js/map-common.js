// Shared helpers for the map feature pages (Leaflet loaded via CDN before this).
window.AmictaMap = {
  init(elId, center = [-6.2, 106.8], zoom = 12) {
    const map = L.map(elId).setView(center, zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
    }).addTo(map);
    return map;
  },
  token() {
    return document.querySelector('input[name="_token"]').value;
  },
  categoryColor(cat) {
    return { moment: '#2563EB', hazard: '#EF4444', quiet: '#22C55E' }[cat] || '#64748B';
  },
  fitTo(map, latlngs) {
    if (latlngs.length) map.fitBounds(L.latLngBounds(latlngs), { padding: [40, 40] });
  },
};
