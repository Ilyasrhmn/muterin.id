(function () {
  const map = window.AmictaMap.init('map');
  const all = [];

  fetch('/map/data', { headers: { Accept: 'application/json' } })
    .then((r) => r.json())
    .then((d) => {
      d.trips.forEach((t) => {
        if (t.path_json && t.path_json.length) {
          L.polyline(t.path_json, { color: '#2563EB', weight: 4, opacity: 0.8 }).addTo(map);
          t.path_json.forEach((p) => all.push(p));
        }
      });
      window.AmictaMap.fitTo(map, all);
    });
})();
