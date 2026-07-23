(function () {
  const map = window.MuterinMap.init('map');
  const all = [];

  fetch('/map/data', { headers: { Accept: 'application/json' } })
    .then((r) => r.json())
    .then((d) => {
      d.trips.forEach((t) => {
        if (t.path_json && t.path_json.length) {
          L.polyline(t.path_json, { color: '#0F766E', weight: 4, opacity: 0.8 }).addTo(map);
          t.path_json.forEach((p) => all.push(p));
        }
      });
      window.MuterinMap.fitTo(map, all);
    });
})();
