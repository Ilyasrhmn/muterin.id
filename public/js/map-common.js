// Shared helpers for the map feature pages (Leaflet loaded via CDN before this).
window.MuterinMap = {
  init(elId, center = [-6.2, 106.8], zoom = 12) {
    const map = L.map(elId).setView(center, zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
    }).addTo(map);
    return map;
  },
  token() {
    return document.querySelector('input[name="_token"]').value;
  },
  categoryColor(cat) {
    return { moment: '#0F766E', hazard: '#DC2626', quiet: '#D97706' }[cat] || '#64748B';
  },
  fitTo(map, latlngs) {
    if (latlngs.length) map.fitBounds(L.latLngBounds(latlngs), { padding: [40, 40] });
  },
};

// Selector yang bisa dilipat (accordion inline) — dipakai untuk memilih dari
// daftar ber-icon tanpa native <select> (yang tidak bisa merender <i> icon
// font) dan tanpa menumpuk chip kalau opsinya banyak/bisa nambah sendiri.
window.MuterinFoldSelect = {
  // container: elemen kosong tempat widget dirender.
  // items: [{ value, label, icon (kelas fa-*, atau null), color (hex) }]
  // opts: { selected, placeholder, onSelect(value) }
  // return: { getSelected, setSelected, setItems }
  render(container, items, opts) {
    const state = { selected: opts.selected ?? null, expanded: false };

    container.innerHTML = `
      <div class="rounded-xl border border-border overflow-hidden bg-surface">
        <button type="button" data-fold-btn aria-haspopup="listbox" aria-expanded="false"
                class="w-full flex items-center gap-2 px-3 py-2.5 text-sm text-left hover:bg-muted/60 transition">
          <span data-fold-icon class="flex items-center justify-center w-6 h-6 rounded-full shrink-0"></span>
          <span data-fold-label class="flex-1 truncate font-medium text-foreground"></span>
          <i data-fold-chevron class="fas fa-chevron-right text-xs text-muted-fg transition-transform duration-200"></i>
        </button>
        <div data-fold-wrap style="display:grid;grid-template-rows:0fr;transition:grid-template-rows .2s ease">
          <div style="overflow:hidden">
            <div data-fold-options role="listbox" class="border-t border-border max-h-56 overflow-y-auto"></div>
          </div>
        </div>
      </div>`;

    const btn = container.querySelector('[data-fold-btn]');
    const iconEl = container.querySelector('[data-fold-icon]');
    const labelEl = container.querySelector('[data-fold-label]');
    const chevron = container.querySelector('[data-fold-chevron]');
    const wrap = container.querySelector('[data-fold-wrap]');
    const optionsBox = container.querySelector('[data-fold-options]');

    function iconBadge(item, size) {
      if (!item || !item.icon) {
        return `<span style="display:inline-flex;align-items:center;justify-content:center;width:${size}px;height:${size}px;color:#94A3B8"><i class="fas fa-circle" style="font-size:${size * 0.3}px"></i></span>`;
      }
      return `<span style="display:inline-flex;align-items:center;justify-content:center;width:${size}px;height:${size}px;border-radius:999px;background:${item.color}1A"><i class="fas ${item.icon}" style="color:${item.color};font-size:${size * 0.5}px"></i></span>`;
    }
    function findItem(value) { return items.find((i) => i.value === value); }
    function esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    function renderHeader() {
      const item = findItem(state.selected);
      iconEl.innerHTML = iconBadge(item, 24);
      labelEl.textContent = item ? item.label : (opts.placeholder || 'Pilih…');
    }

    function renderOptions() {
      optionsBox.innerHTML = '';
      items.forEach((item) => {
        const active = item.value === state.selected;
        const row = document.createElement('button');
        row.type = 'button';
        row.setAttribute('role', 'option');
        row.setAttribute('aria-selected', active ? 'true' : 'false');
        row.className = 'w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-muted/60 transition ' + (active ? 'bg-primary/10' : '');
        row.innerHTML = `${iconBadge(item, 22)}<span class="flex-1 truncate ${active ? 'font-semibold text-primary' : 'text-foreground'}">${esc(item.label)}</span>`;
        row.onclick = () => {
          state.selected = item.value;
          renderHeader();
          renderOptions();
          collapse();
          btn.focus();
          opts.onSelect && opts.onSelect(item.value);
        };
        optionsBox.appendChild(row);
      });
    }

    function expand() {
      state.expanded = true;
      wrap.style.gridTemplateRows = '1fr';
      chevron.style.transform = 'rotate(90deg)';
      btn.setAttribute('aria-expanded', 'true');
    }
    function collapse() {
      state.expanded = false;
      wrap.style.gridTemplateRows = '0fr';
      chevron.style.transform = 'rotate(0deg)';
      btn.setAttribute('aria-expanded', 'false');
    }

    btn.onclick = () => { state.expanded ? collapse() : expand(); };
    btn.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        expand();
        optionsBox.querySelector('button')?.focus();
      }
    });
    optionsBox.addEventListener('keydown', (e) => {
      const opts2 = [...optionsBox.querySelectorAll('button')];
      const i = opts2.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); (opts2[i + 1] || opts2[0])?.focus(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); (opts2[i - 1] || opts2[opts2.length - 1])?.focus(); }
      else if (e.key === 'Escape') { e.preventDefault(); collapse(); btn.focus(); }
    });
    document.addEventListener('click', (e) => {
      if (!container.contains(e.target)) collapse();
    });

    renderHeader();
    renderOptions();

    return {
      getSelected: () => state.selected,
      setSelected(value) { state.selected = value; renderHeader(); renderOptions(); },
      setItems(newItems) { items = newItems; renderHeader(); renderOptions(); },
    };
  },
};
