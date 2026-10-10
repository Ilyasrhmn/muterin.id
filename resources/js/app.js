import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

document.querySelectorAll('[data-countup]').forEach((el) => {
  el.textContent = Number(el.dataset.countup).toLocaleString('id-ID');
});

const sidebar = document.getElementById('app-sidebar');
if (sidebar) {
  const trigger = document.getElementById('drawer-trigger');
  const desktop = window.matchMedia('(min-width: 1024px)');
  desktop.addEventListener('change', () => {
    if (desktop.matches) sidebar.inert = false;
    else sidebar.inert = trigger?.getAttribute('aria-expanded') !== 'true';
  });
  sidebar.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab' || desktop.matches) return;
    const targets = [...sidebar.querySelectorAll('a[href], button:not([disabled])')]
      .filter((el) => !el.closest('[inert]') && el.getClientRects().length);
    const first = targets[0];
    const last = targets[targets.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
  });
  const shell = sidebar.closest('[x-data]');
  new MutationObserver(() => {
    if (trigger?.getAttribute('aria-expanded') === 'false' && sidebar.contains(document.activeElement)) trigger.focus();
  }).observe(shell, { attributes: true, subtree: true, attributeFilter: ['aria-expanded'] });
}
