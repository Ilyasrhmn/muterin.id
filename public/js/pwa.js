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
      + 'display:flex;align-items:center;gap:12px;background:#fff;color:#0F172A;'
      + 'padding:12px 14px;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,.15);'
      + 'font:14px -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;'
      + 'max-width:400px;margin:0 auto;';

    wrap.innerHTML = '<div style="width:44px;height:44px;border-radius:12px;background:#0F766E;'
      + 'flex-shrink:0;display:flex;align-items:center;justify-content:center">'
      + '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="1.8" '
      + 'stroke-linecap="round" stroke-linejoin="round">'
      + '<circle cx="5.5" cy="16.5" r="3.5"/><circle cx="18.5" cy="16.5" r="3.5"/>'
      + '<path d="M5.5 16.5h7l4-6h-9M15 10.5l1.5 3M8 6h3l2 4.5"/></svg>'
      + '</div>'
      + '<div style="flex:1;min-width:0">'
      + '<p style="font-weight:700;margin:0;font-size:14px;color:#0F172A">Install Muterin</p>'
      + '<p style="margin:2px 0 0;font-size:12px;color:#64748B">Akses lebih cepat langsung dari layar beranda HP-mu.</p>'
      + '</div>'
      + '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:5px;flex-shrink:0">'
      + '<button id="mtn-install-yes" style="background:#0F766E;color:#fff;border:none;'
      + 'border-radius:8px;padding:7px 14px;font:inherit;font-weight:600;font-size:13px;white-space:nowrap;cursor:pointer;">Install</button>'
      + '<button id="mtn-install-no" style="background:transparent;color:#64748B;border:none;'
      + 'font:inherit;font-size:12px;cursor:pointer;padding:0;">Nanti</button>'
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
