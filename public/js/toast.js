(function () {
  const ROOT_ID = 'mtn-toast-root';
  const DURATION = 4000;

  const ICONS = {
    success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    warning: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    error: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
  };
  const COLORS = {
    success: { bg: '#ECFDF5', fg: '#047857', icon: '#059669' },
    warning: { bg: '#FFFBEB', fg: '#B45309', icon: '#D97706' },
    error: { bg: '#FEF2F2', fg: '#B91C1C', icon: '#DC2626' },
  };

  function ensureRoot() {
    let root = document.getElementById(ROOT_ID);
    if (root) return root;
    root = document.createElement('div');
    root.id = ROOT_ID;
    root.setAttribute('aria-live', 'polite');
    root.setAttribute('aria-atomic', 'true');
    root.style.cssText = 'position:fixed;top:16px;left:0;right:0;z-index:99999;'
      + 'display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none;';
    document.body.appendChild(root);
    return root;
  }

  function show(message, type) {
    type = COLORS[type] ? type : 'success';
    const root = ensureRoot();
    const c = COLORS[type];

    const el = document.createElement('div');
    el.className = 'mtn-toast-enter';
    el.style.cssText = 'pointer-events:auto;display:flex;align-items:flex-start;gap:10px;'
      + 'max-width:380px;width:calc(100vw - 32px);background:#fff;border-radius:14px;'
      + 'box-shadow:0 10px 30px rgba(15,23,42,.15);padding:12px 14px;cursor:pointer;';
    el.innerHTML = `<div style="width:28px;height:28px;border-radius:999px;background:${c.bg};color:${c.icon};display:flex;align-items:center;justify-content:center;flex-shrink:0">${ICONS[type]}</div>`
      + `<p style="flex:1;font-size:13px;font-weight:600;color:${c.fg};margin:5px 0 0;line-height:1.4">${message}</p>`
      + `<button type="button" aria-label="Tutup" style="background:none;border:0;color:#94A3B8;cursor:pointer;padding:4px;font-size:16px;line-height:1">&times;</button>`;

    let timer;
    function dismiss() {
      clearTimeout(timer);
      el.className = 'mtn-toast-exit';
      el.addEventListener('animationend', () => el.remove(), { once: true });
    }
    el.addEventListener('click', dismiss);
    timer = setTimeout(dismiss, DURATION);

    root.appendChild(el);
  }

  window.MuterinToast = {
    show,
    success: (msg) => show(msg, 'success'),
    warning: (msg) => show(msg, 'warning'),
    error: (msg) => show(msg, 'error'),
  };

  // Relay: beberapa alur JS (mis. simpan rencana rute) sengaja full-page
  // location.reload() sesudah sukses -- pesan toast gak akan hidup lewat
  // reload kalau cuma dipanggil langsung, jadi dititip lewat sessionStorage
  // sebelum reload, lalu ditembak balik di sini pas skrip ini load ulang.
  const pending = sessionStorage.getItem('mtn_pending_toast');
  if (pending) {
    sessionStorage.removeItem('mtn_pending_toast');
    try {
      const { type, message } = JSON.parse(pending);
      window.addEventListener('DOMContentLoaded', () => show(message, type));
    } catch (e) { /* ponytail: pesan korup, abaikan diam-diam -- bukan hal fatal */ }
  }
})();
