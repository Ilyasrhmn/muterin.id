# Design Document: Dialog UX Improvement

## Overview

This design improves the dialog/popup component UX by adding backdrop blur effects, fixing z-index issues, implementing smooth animations, and enhancing accessibility. The implementation focuses on CSS-based solutions with JavaScript enhancements for body scroll management and focus trapping.

## Architecture

The dialog system consists of three main layers:
1. **Backdrop Layer**: Semi-transparent overlay with blur effect
2. **Dialog Container**: Centered container with flexbox alignment
3. **Dialog Content**: The actual dialog box with content and buttons

**Technology Stack:**
- Tailwind CSS for styling with custom backdrop-blur utilities
- Vanilla JavaScript for dialog logic and accessibility features
- CSS transitions and transforms for animations

## Components and Interfaces

### 1. Dialog HTML Structure (dialog.blade.php)

```html
<div id="amicta-dialog" 
     class="fixed inset-0 z-[9999] hidden opacity-0 transition-opacity duration-300"
     role="dialog" 
     aria-modal="true"
     aria-labelledby="dialog-message">
    
    <!-- Backdrop with blur -->
    <div data-dialog-backdrop 
         class="absolute inset-0 bg-slate-900/60 backdrop-blur-md transition-all duration-300"></div>
    
    <!-- Dialog content wrapper -->
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-surface rounded-2xl border border-border shadow-2xl w-full max-w-sm p-6 
                    transform scale-95 transition-all duration-300"
             data-dialog-content>
            
            <p id="dialog-message" 
               data-dialog-message 
               class="text-sm text-foreground font-medium"></p>
            
            <!-- Input fields -->
            <div data-dialog-fields class="mt-4 space-y-3 hidden">
                <label class="block space-y-1.5">
                    <span data-dialog-input-label 
                          class="text-xs font-medium text-muted-fg"></span>
                    <input data-dialog-input 
                           type="text"
                           class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm 
                                  focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none
                                  transition-colors duration-200">
                </label>
                <label data-dialog-extra-wrap class="block space-y-1.5 hidden">
                    <span data-dialog-extra-label 
                          class="text-xs font-medium text-muted-fg"></span>
                    <textarea data-dialog-extra 
                              rows="2"
                              class="w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm 
                                     focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none
                                     transition-colors duration-200"></textarea>
                </label>
            </div>
            
            <!-- Action buttons -->
            <div class="mt-6 flex justify-end gap-2">
                <button data-dialog-cancel 
                        type="button"
                        class="inline-flex items-center justify-center gap-2 font-heading font-semibold 
                               rounded-xl transition-all duration-200 text-sm px-4 py-2.5 
                               border border-border bg-surface text-foreground hover:bg-muted 
                               active:scale-95 cursor-pointer">
                    Batal
                </button>
                <button data-dialog-confirm 
                        type="button"
                        class="inline-flex items-center justify-center gap-2 font-heading font-semibold 
                               rounded-xl transition-all duration-200 text-sm px-4 py-2.5 
                               bg-primary text-white hover:bg-primary-hover 
                               active:scale-95 cursor-pointer 
                               disabled:opacity-50 disabled:pointer-events-none">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>
```

**Key Changes:**
- Root element has `opacity-0` initially and `transition-opacity` for fade effect
- Z-index increased to `z-[9999]` to ensure it's always on top
- Backdrop has `backdrop-blur-md` and increased opacity to `bg-slate-900/60`
- Dialog content wrapper uses flex centering
- Dialog content has `scale-95` transform initially for scale-up animation
- All transitions use `duration-300` for consistency
- Buttons have `active:scale-95` for press feedback

### 2. Dialog JavaScript Enhancement (dialog.js)

```javascript
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

  function lockBodyScroll() {
    document.body.style.overflow = 'hidden';
    document.body.style.paddingRight = getScrollbarWidth() + 'px';
  }

  function unlockBodyScroll() {
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  }

  function getScrollbarWidth() {
    return window.innerWidth - document.documentElement.clientWidth;
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
      if (!value) return;
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
  
  input.addEventListener('keydown', (e) => { 
    if (e.key === 'Enter') { 
      e.preventDefault(); 
      onConfirm(); 
    } 
  });
  
  input.addEventListener('input', () => { 
    confirmBtn.disabled = !input.value.trim(); 
  });

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
```

**Key JavaScript Enhancements:**
- `lockBodyScroll()` / `unlockBodyScroll()`: Prevents background scrolling and compensates for scrollbar width
- `trapFocus()`: Implements focus trap to keep keyboard navigation within dialog
- `updateFocusableElements()`: Dynamically finds all focusable elements
- `open()` / `close()`: Enhanced with animation sequencing using classes
- Focus management: Auto-focus first input or confirm button

### 3. Tailwind Configuration

Ensure `backdrop-blur` utilities are available in `tailwind.config.js`:

```javascript
module.exports = {
  // ... existing config
  theme: {
    extend: {
      backdropBlur: {
        'md': '12px',
      },
    },
  },
  variants: {
    extend: {
      backdropFilter: ['responsive'],
    },
  },
}
```

## Data Models

No data model changes required - this is purely a UI/UX enhancement.

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Backdrop Blur Visibility

*For any* dialog open state, the backdrop element should have the `backdrop-blur-md` class applied and be visible.

**Validates: Requirements 1.1, 1.3**

### Property 2: Body Scroll Lock During Dialog

*For any* dialog that is currently open, the body element should have `overflow: hidden` style applied.

**Validates: Requirements 4.1, 4.2**

### Property 3: Focus Trap Boundary

*For any* dialog with multiple focusable elements, pressing Tab at the last element should move focus to the first element, and Shift+Tab at the first element should move to the last element.

**Validates: Requirements 4.4**

### Property 4: Z-Index Supremacy

*For any* page state with a dialog open, the dialog root element should have a z-index value higher than all other positioned elements on the page.

**Validates: Requirements 2.1, 2.4**

### Property 5: Animation State Consistency

*For any* dialog transition (opening or closing), the opacity and transform classes should be synchronized - both should transition together without visual glitches.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4**

### Property 6: Scrollbar Compensation

*For any* body scroll lock, the padding-right applied to the body should equal the scrollbar width to prevent layout shift.

**Validates: Requirements 5.5**

## Error Handling

1. **Missing Dialog Element**: If `#amicta-dialog` doesn't exist, the IIFE returns early without errors
2. **Focus Trap Edge Cases**: Filter out disabled and hidden elements from focusable list
3. **Transition Timing**: Use `setTimeout` to ensure animations complete before state changes
4. **Browser Compatibility**: Fallback to darker overlay if `backdrop-filter` is not supported

## Testing Strategy

### Unit Tests

1. **Dialog Opening**: Test that `open()` adds correct classes and locks body scroll
2. **Dialog Closing**: Test that `close()` removes classes and unlocks scroll after timeout
3. **Focus Management**: Test that first focusable element receives focus on open
4. **Scrollbar Width Calculation**: Test `getScrollbarWidth()` returns correct value

### Property Tests

Property-based tests should be written using a JavaScript property testing library (e.g., fast-check, jsverify) with minimum 100 iterations each.

1. **Property 1 Test**: Generate random dialog states, open dialog, verify backdrop has blur classes
2. **Property 2 Test**: Generate random sequences of open/close operations, verify body overflow state matches dialog state
3. **Property 3 Test**: Generate random sequences of Tab/Shift+Tab keypresses in open dialog, verify focus never leaves dialog
4. **Property 4 Test**: Generate random page elements with varying z-indices, verify dialog z-index is always highest
5. **Property 5 Test**: Generate random dialog transitions, verify opacity and transform classes are always synchronized
6. **Property 6 Test**: Generate random scrollbar widths (simulate different browsers), verify padding equals scrollbar width

### Integration Tests

1. Test complete user flow: click trigger → dialog opens with animation → interact → close
2. Test keyboard navigation: Tab through all elements → verify focus trap works
3. Test multiple dialogs: Open dialog 1 → close → open dialog 2 → verify no state leakage
4. Test backdrop click: Open dialog → click backdrop → verify dialog closes

### Browser Testing

- Chrome/Edge (Chromium): Full backdrop-blur support
- Firefox: Full backdrop-blur support (check older versions)
- Safari: Test with `-webkit-backdrop-filter` prefix
- Mobile browsers: Test touch interactions and viewport handling

