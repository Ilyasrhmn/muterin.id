// Shared helpers for the map feature pages (Leaflet loaded via CDN before this).
window.MuterinMap = {
  init(elId, center = [-6.2, 106.8], zoom = 12) {
    const map = L.map(elId).setView(center, zoom);
    const config = window.MuterinMapConfig || {};
    const mapbox = typeof config.accessToken === 'string' && config.accessToken.startsWith('pk.');
    const tiles = mapbox
      ? 'https://api.mapbox.com/styles/v1/' + config.style + '/tiles/512/{z}/{x}/{y}?access_token=' + encodeURIComponent(config.accessToken)
      : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const layer = L.tileLayer(tiles, mapbox ? {
      tileSize: 512,
      zoomOffset: -1,
      minZoom: 1,
      maxZoom: 22,
      attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> <a href="https://www.mapbox.com/map-feedback/" target="_blank" rel="noopener">Improve this map</a>',
    } : { attribution: '© OpenStreetMap' });
    let tileErrorReported = false;
    layer.on('tileerror', () => {
      if (tileErrorReported) return;
      tileErrorReported = true;
      window.MuterinToast?.error('Peta gagal dimuat. Periksa koneksi atau konfigurasi layanan peta.');
    });
    layer.addTo(map);
    const element = document.getElementById(elId);
    const controls = element.closest('.mtn-map-workspace')?.querySelector('.mtn-map-controls');
    if (controls) {
      const syncHeight = () => {
        if (window.matchMedia('(min-width:1100px)').matches) {
          element.style.setProperty('height', controls.getBoundingClientRect().height + 'px', 'important');
        } else {
          element.style.removeProperty('height');
        }
        map.invalidateSize({ pan: false });
      };
      new ResizeObserver(syncHeight).observe(controls);
      window.addEventListener('resize', syncHeight);
      syncHeight();
    }
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

window.MuterinMap.loadingList = function (container, count = 3, compact = false) {
  container.replaceChildren();
  container.setAttribute('aria-busy', 'true');
  container.setAttribute('aria-label', 'Memuat data');
  for (let i = 0; i < count; i++) {
    const row = document.createElement('div');
    row.className = compact ? 'flex items-center gap-2 px-3 py-2' : 'flex items-center gap-2 p-3 rounded-xl';
    row.setAttribute('aria-hidden', 'true');
    row.innerHTML = '<span class="mtn-skeleton" style="width:20px;height:20px;border-radius:50%;flex-shrink:0"></span>' +
      '<div style="flex:1"><div class="mtn-skeleton" style="width:65%;height:14px;border-radius:4px"></div>' +
      (compact ? '' : '<div class="mtn-skeleton" style="width:85%;height:11px;margin-top:6px;border-radius:4px"></div>') + '</div>';
    container.appendChild(row);
  }
};
window.MuterinMap.finishLoading = function (container) {
  container.removeAttribute('aria-busy');
  container.removeAttribute('aria-label');
};
window.MuterinMap.listError = function (container, message, retry) {
  window.MuterinMap.finishLoading(container);
  container.replaceChildren();
  const text = document.createElement('p');
  text.className = 'p-3 text-sm text-muted-fg';
  text.textContent = message;
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'px-3 py-2 text-sm text-primary';
  button.textContent = 'Coba lagi';
  button.onclick = retry;
  container.append(text, button);
};
window.MuterinMap.photoHtml = function (url, height, marginBottom) {
  if (!url) return '';
  const box = document.createElement('div');
  box.style.cssText = `position:relative;width:100%;height:${height}px;border-radius:8px;overflow:hidden;margin-bottom:${marginBottom}px`;
  box.setAttribute('aria-busy', 'true');
  box.innerHTML = '<div class="mtn-skeleton" aria-hidden="true" style="position:absolute;inset:0"></div>';
  const image = document.createElement('img');
  image.setAttribute('src', url);
  image.alt = '';
  image.style.cssText = 'width:100%;height:100%;object-fit:cover;position:relative;opacity:0';
  image.setAttribute('onload', "this.style.opacity=1;this.previousElementSibling.remove();this.parentElement.removeAttribute('aria-busy')");
  image.setAttribute('onerror', "const box=this.parentElement;box.removeAttribute('aria-busy');box.textContent='Foto tidak tersedia';box.style.display='flex';box.style.alignItems='center';box.style.justifyContent='center';box.style.fontSize='12px'");
  box.appendChild(image);
  return box.outerHTML;
};
