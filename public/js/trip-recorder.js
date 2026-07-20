(function () {
  const $ = (id) => document.getElementById(id);
  const startBtn = $('start-btn'), stopBtn = $('stop-btn');
  const IDLE_MS = 5 * 60 * 1000;        // ponytail: idle auto-stop 5 menit, tuning di device asli
  const MAX_JUMP_KM = 1;                // ponytail: buang lonjakan >1km antar update (outlier GPS)

  let watchId = null, last = null, distance = 0, startTs = 0, path = [], idleTimer = null, tick = null;
  let marker = null, liveLine = null;

  const map = window.AmictaMap.init('ride-map');
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((pos) => {
      map.setView([pos.coords.latitude, pos.coords.longitude], 15);
    });
  }

  function haversine(a, b) {
    const R = 6371, toRad = (d) => d * Math.PI / 180;
    const dLat = toRad(b[0] - a[0]), dLng = toRad(b[1] - a[1]);
    const s = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(s), Math.sqrt(1 - s));
  }

  function fmtDur(sec) {
    const m = String(Math.floor(sec / 60)).padStart(2, '0'), s = String(sec % 60).padStart(2, '0');
    return `${m}:${s}`;
  }

  function onPos(pos) {
    const p = [pos.coords.latitude, pos.coords.longitude];
    if (last) {
      const d = haversine(last, p);
      if (d <= MAX_JUMP_KM) {
        distance += d;
        $('distance').textContent = distance.toFixed(2);
      }
    }
    last = p;
    path.push(p);
    if (marker) marker.setLatLng(p);
    else marker = L.circleMarker(p, { color: '#0F766E', radius: 7, fillOpacity: 1 }).addTo(map);
    if (liveLine) liveLine.addLatLng(p);
    else liveLine = L.polyline([p], { color: '#0F766E', weight: 4 }).addTo(map);
    map.panTo(p);
    resetIdle();
  }

  function resetIdle() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(stop, IDLE_MS);
  }

  function start() {
    if (!navigator.geolocation) {
      $('gps-msg').textContent = 'Browser tidak mendukung GPS.';
      return;
    }
    navigator.geolocation.getCurrentPosition(() => {
      distance = 0;
      last = null;
      path = [];
      startTs = Date.now();
      $('distance').textContent = '0.00';
      if (liveLine) { liveLine.remove(); liveLine = null; }
      if (marker) { marker.remove(); marker = null; }
      watchId = navigator.geolocation.watchPosition(onPos, onErr, { enableHighAccuracy: true, maximumAge: 0 });
      tick = setInterval(() => {
        $('duration').textContent = fmtDur(Math.floor((Date.now() - startTs) / 1000));
      }, 1000);
      startBtn.classList.add('hidden');
      stopBtn.classList.remove('hidden');
      resetIdle();
    }, onErr, { enableHighAccuracy: true });
  }

  function onErr() {
    $('gps-msg').textContent = 'Izin GPS ditolak atau tidak tersedia.';
  }

  async function stop() {
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    clearInterval(tick);
    clearTimeout(idleTimer);
    watchId = null;
    const duration = Math.floor((Date.now() - startTs) / 1000);
    const token = document.querySelector('input[name="_token"]').value;
    await fetch('/trips', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      body: JSON.stringify({
        motorcycle_id: $('motor-select').value,
        distance_km: Number(distance.toFixed(2)),
        duration_seconds: duration,
        path,
      }),
    });
    window.location.href = '/dashboard';
  }

  startBtn.addEventListener('click', start);
  stopBtn.addEventListener('click', stop);
})();
