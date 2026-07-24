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
    showInstallBanner();
    window.dispatchEvent(new CustomEvent('mtn:install-available'));
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    hideInstallBanner();
    window.dispatchEvent(new CustomEvent('mtn:install-done'));
  });

  window.mtnInstallApp = async function () {
    if (!deferredPrompt) return false;
    deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice;
    deferredPrompt = null;
    hideInstallBanner();
    window.dispatchEvent(new CustomEvent('mtn:install-done'));
    return choice.outcome === 'accepted';
  };

  function showInstallBanner() {
    if (document.getElementById('mtn-install-banner')) return;

    const wrap = document.createElement('div');
    wrap.id = 'mtn-install-banner';
    wrap.style.cssText = 'position:fixed;left:16px;right:16px;bottom:16px;z-index:9999;'
      + 'display:flex;flex-direction:column;gap:10px;background:#0F766E;color:#fff;'
      + 'padding:14px 16px;border-radius:16px;box-shadow:0 8px 24px rgba(15,118,110,.35);'
      + 'font:14px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;'
      + 'max-width:360px;margin:0 auto;';

    wrap.innerHTML = '<div>'
      + '<p style="font-weight:600;margin:0 0 2px">Pasang aplikasi Muterin di HP kamu</p>'
      + '<p style="opacity:.8;margin:0;font-size:12px">Akses lebih cepat dari layar utama dan tetap jalan walau sinyal lagi lemah.</p>'
      + '</div>'
      + '<div style="display:flex;gap:8px">'
      + '<button id="mtn-install-yes" style="flex:1;background:#fff;color:#0F766E;border:none;'
      + 'border-radius:10px;padding:9px 12px;font:inherit;font-weight:600;white-space:nowrap;cursor:pointer;">Unduh Aplikasi</button>'
      + '<button id="mtn-install-no" style="background:transparent;color:#fff;border:none;'
      + 'font:inherit;opacity:.8;cursor:pointer;white-space:nowrap;padding:9px 4px;">Nanti saja</button>'
      + '</div>';

    document.body.appendChild(wrap);

    document.getElementById('mtn-install-yes').addEventListener('click', () => {
      window.mtnInstallApp();
    });

    document.getElementById('mtn-install-no').addEventListener('click', () => {
      localStorage.setItem('mtn_install_dismissed', '1');
      hideInstallBanner();
    });
  }

  function hideInstallBanner() {
    const el = document.getElementById('mtn-install-banner');
    if (el) el.remove();
  }
})();
