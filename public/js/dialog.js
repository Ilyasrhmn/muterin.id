(function () {
  const root = document.getElementById('amicta-dialog');
  if (!root) return;

  const backdrop = root.querySelector('[data-dialog-backdrop]');
  const dialogContent = root.querySelector('[data-dialog-content]');
  const messageEl = root.querySelector('[data-dialog-message]');
  const fieldsEl = root.querySelector('[data-dialog-fields]');
  const inputLabel = root.querySelector('[data-dialog-input-label]');
  const input = root.querySelector('[data-dialog-input]');
  const extraWrap = root.querySelector('[data-dialog-extra-wrap]');
  const extraLabel = root.querySelector('[data-dialog-extra-label]');
  const extra = root.querySelector('[data-dialog-extra]');
  const cancelBtn = root.querySelector('[data-dialog-cancel]');
  const confirmBtn = root.querySelector('[data-dialog-confirm]');

  // Store focusable elements for focus trap
  let focusableElements = [];
  let firstFocusable = null;
  let lastFocusable = null;

  const BTN_PRIMARY = 'inline-flex items-center justify-center gap-2 font-heading font-semibold rounded-xl transition-all duration-200 text-sm px-4 py-2.5 bg-primary text-white hover:bg-primary-hover active:scale-95 cursor-pointer disabled:opacity-50 disabled:pointer-events-none';
  const BTN_DANGER = 'inline-flex items-center justify-center gap-2 font-heading font-semibold rounded-xl transition-all duration-200 text-sm px-4 py-2.5 bg-accent text-white hover:bg-accent-hover active:scale-95 cursor-pointer disabled:opacity-50 disabled:pointer-events-none';

  let resolver = null;
  let mode = 'confirm';
  let hasExtra = false;

  function updateFocusableElements() {
    const selector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
    focusableElements = Array.from(dialogContent.querySelectorAll(selector))
      .filter(el => !el.disabled && !el.classList.contains('hidden'));
    firstFocusable = focusableElements[0];
    lastFocusable = focusableElements[focusableElements.length - 1];
  }

  function trapFocus(e) {
    if (e.key !== 'Tab') return;

    if (e.shiftKey) {
      // Shift + Tab
      if (document.activeElement === firstFocusable) {
        e.preventDefault();
        lastFocusable.focus();
      }
    } else {
      // Tab
      if (document.activeElement === lastFocusable) {
        e.preventDefault();
        firstFocusable.focus();
      }
    }
  }

  function getScrollbarWidth() {
    return window.innerWidth - document.documentElement.clientWidth;
  }

  function lockBodyScroll() {
    document.body.style.overflow = 'hidden';
    document.body.style.paddingRight = getScrollbarWidth() + 'px';
  }

  function unlockBodyScroll() {
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  }

  function open() {
    root.classList.remove('hidden');
    lockBodyScroll();
    
    // Trigger reflow to enable transition
    root.offsetHeight;
    
    // Fade in and scale up
    root.classList.remove('opacity-0');
    dialogContent.classList.remove('scale-95');
    dialogContent.classList.add('scale-100');
    
    // Setup focus trap
    updateFocusableElements();
    document.addEventListener('keydown', trapFocus);
    
    // Focus first element after animation
    setTimeout(() => {
      if (mode === 'prompt') {
        input.focus();
      } else {
        confirmBtn.focus();
      }
    }, 50);
  }

  function close() {
    // Fade out and scale down
    root.classList.add('opacity-0');
    dialogContent.classList.remove('scale-100');
    dialogContent.classList.add('scale-95');
    
    // Remove focus trap
    document.removeEventListener('keydown', trapFocus);
    
    // Hide after animation completes
    setTimeout(() => {
      root.classList.add('hidden');
      unlockBodyScroll();
    }, 300);
  }

  function settle(result) {
    const r = resolver;
    resolver = null;
    close();
    if (r) r(result);
  }

  function onConfirm() {
    if (mode === 'prompt') {
      const value = input.value.trim();
      if (!value) return; // required
      settle(hasExtra ? { value, extra: extra.value } : value);
    } else if (mode === 'confirm') {
      settle(true);
    } else {
      settle(undefined);
    }
  }

  function onCancel() {
    if (mode === 'prompt') settle(null);
    else if (mode === 'confirm') settle(false);
    else settle(undefined);
  }

  confirmBtn.addEventListener('click', onConfirm);
  cancelBtn.addEventListener('click', onCancel);
  backdrop.addEventListener('click', onCancel);
  document.addEventListener('keydown', (e) => {
    if (root.classList.contains('hidden')) return;
    if (e.key === 'Escape') onCancel();
    else if (e.key === 'Enter' && mode !== 'prompt') onConfirm();
  });
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); onConfirm(); } });
  input.addEventListener('input', () => { confirmBtn.disabled = !input.value.trim(); });

  window.AmictaDialog = {
    confirm(message, opts = {}) {
      mode = 'confirm'; 
      hasExtra = false;
      messageEl.textContent = message;
      fieldsEl.classList.add('hidden');
      cancelBtn.classList.remove('hidden');
      confirmBtn.textContent = opts.confirmText || 'Ya';
      cancelBtn.textContent = opts.cancelText || 'Batal';
      confirmBtn.className = opts.danger ? BTN_DANGER : BTN_PRIMARY;
      confirmBtn.disabled = false;
      open();
      return new Promise((res) => { resolver = res; });
    },
    
    prompt(message, opts = {}) {
      mode = 'prompt'; 
      hasExtra = !!opts.extra;
      messageEl.textContent = message;
      fieldsEl.classList.remove('hidden');
      inputLabel.textContent = opts.label || '';
      input.placeholder = opts.placeholder || '';
      input.value = opts.defaultValue || '';
      
      if (hasExtra) {
        extraWrap.classList.remove('hidden');
        extraLabel.textContent = opts.extra.label || '';
        extra.placeholder = opts.extra.placeholder || '';
        extra.value = '';
      } else {
        extraWrap.classList.add('hidden');
      }
      
      cancelBtn.classList.remove('hidden');
      cancelBtn.textContent = 'Batal';
      confirmBtn.textContent = opts.confirmText || 'Simpan';
      confirmBtn.className = BTN_PRIMARY;
      confirmBtn.disabled = !input.value.trim();
      open();
      return new Promise((res) => { resolver = res; });
    },
    
    alert(message, opts = {}) {
      mode = 'alert'; 
      hasExtra = false;
      messageEl.textContent = message;
      fieldsEl.classList.add('hidden');
      cancelBtn.classList.add('hidden');
      confirmBtn.textContent = opts.confirmText || 'OK';
      confirmBtn.className = BTN_PRIMARY;
      confirmBtn.disabled = false;
      open();
      return new Promise((res) => { resolver = res; });
    },
  };
})();
