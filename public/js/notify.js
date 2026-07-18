(function () {
  if (!('Notification' in window)) return;
  if (Notification.permission === 'default') Notification.requestPermission();

  const prev = JSON.parse(localStorage.getItem('mtn_colors') || '{}');
  const now = {};
  document.querySelectorAll('[data-item-id]').forEach((el) => {
    const id = el.dataset.itemId, color = el.dataset.color;
    now[id] = color;
    const before = prev[id];
    const worsened = (before === 'green' && color !== 'green') || (before !== 'red' && color === 'red');
    if (worsened && Notification.permission === 'granted') {
      new Notification('Waktunya cek perawatan', {
        body: `${el.dataset.itemName} kini ${color === 'red' ? 'LEWAT batas' : 'mendekati batas'}.`,
      });
    }
  });
  localStorage.setItem('mtn_colors', JSON.stringify(now));
})();
