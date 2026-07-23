(function () {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch((err) => {
        console.error('SW registration failed:', err);
      });
    });
  }

  if (localStorage.getItem('mtn_install_dismissed') === '1') return;

  let deferredPrompt = null;

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    showInstallButton();
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    hideInstallButton();
  });

  function showInstallButton() {
    if (document.getElementById('mtn-install-btn')) return;

    const wrap = document.createElement('div');
    wrap.id = 'mtn-install-btn';
    wrap.style.cssText = 'position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;'
      + 'display:flex;align-items:center;gap:12px;background:#0F766E;color:#fff;'
      + 'padding:12px 16px;border-radius:14px;box-shadow:0 8px 24px rgba(15,118,110,.35);'
      + 'font:600 14px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;'
      + 'max-width:360px;margin:0 auto;';

    wrap.innerHTML = '<span style="flex:1">Pasang aplikasi Muterin ke layar utama</span>'
      + '<button id="mtn-install-yes" style="background:#fff;color:#0F766E;border:none;'
      + 'border-radius:8px;padding:8px 12px;font:inherit;cursor:pointer;">Pasang</button>'
      + '<button id="mtn-install-no" style="background:transparent;color:#fff;border:none;'
      + 'font:inherit;cursor:pointer;opacity:.8;">&times;</button>';

    document.body.appendChild(wrap);

    document.getElementById('mtn-install-yes').addEventListener('click', async () => {
      hideInstallButton();
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      await deferredPrompt.userChoice;
      deferredPrompt = null;
    });

    document.getElementById('mtn-install-no').addEventListener('click', () => {
      localStorage.setItem('mtn_install_dismissed', '1');
      hideInstallButton();
    });
  }

  function hideInstallButton() {
    const el = document.getElementById('mtn-install-btn');
    if (el) el.remove();
  }
})();
