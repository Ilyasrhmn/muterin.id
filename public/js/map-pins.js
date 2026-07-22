/**
 * Pin icon configuration
 * Maps categories to Font Awesome icons and colors
 */
window.AmictaPinIcons = {
  hazard: {
    icon: 'fa-triangle-exclamation',
    color: '#DC2626', // red
    bgColor: '#FEF2F2',
    label: 'Jalan Rawan'
  },
  quiet: {
    icon: 'fa-road',
    color: '#D97706', // amber
    bgColor: '#FFFBEB',
    label: 'Jalan Sepi'
  },
  moment: {
    icon: 'fa-camera',
    color: '#0F766E', // primary/teal
    bgColor: '#F0FDFA',
    label: 'Momen'
  },
  // fallback untuk kategori lama
  infrastruktur: {
    icon: 'fa-road-barrier',
    color: '#3B82F6',
    bgColor: '#EFF6FF',
    label: 'Infrastruktur'
  },
  bencana: {
    icon: 'fa-triangle-exclamation',
    color: '#EF4444',
    bgColor: '#FEF2F2',
    label: 'Bencana'
  },
  layanan: {
    icon: 'fa-building',
    color: '#8B5CF6',
    bgColor: '#F5F3FF',
    label: 'Layanan'
  },
  lainnya: {
    icon: 'fa-location-dot',
    color: '#6B7280',
    bgColor: '#F9FAFB',
    label: 'Lainnya'
  }
};

/**
 * Get icon config for category
 * @param {string} category
 * @returns {Object}
 */
function getIconConfig(category) {
  return window.AmictaPinIcons[category] || window.AmictaPinIcons.lainnya;
}

/**
 * Create custom marker with Font Awesome icon
 * @param {number} lat
 * @param {number} lng
 * @param {string} category
 * @param {boolean} hasStory - Whether pin has a story
 * @returns {L.Marker}
 */
function createPinMarker(lat, lng, category, hasStory = false) {
  const config = getIconConfig(category);
  
  const iconHtml = `
    <div class="relative">
      <div class="flex items-center justify-center w-10 h-10 rounded-full shadow-lg border-2 transition-transform hover:scale-110" 
           style="background-color: ${config.bgColor}; border-color: ${config.color};">
        <i class="fas ${config.icon} text-lg" style="color: ${config.color};"></i>
      </div>
      ${hasStory ? `
        <div class="absolute -top-1 -right-1 w-5 h-5 bg-amber-500 rounded-full border-2 border-white flex items-center justify-center">
          <i class="fas fa-message text-white text-xs"></i>
        </div>
      ` : ''}
    </div>
  `;
  
  const icon = L.divIcon({
    html: iconHtml,
    className: 'custom-pin-marker',
    iconSize: [40, 40],
    iconAnchor: [20, 40],
    popupAnchor: [0, -40]
  });
  
  return L.marker([lat, lng], { icon });
}

/**
 * Format date for display
 * @param {string} dateString
 * @returns {string}
 */
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

/**
 * Create enhanced popup content for pin
 * @param {Object} pin - Pin data
 * @returns {HTMLElement}
 */
function createPinPopup(pin) {
  const config = getIconConfig(pin.category);
  
  const container = document.createElement('div');
  container.className = 'pin-popup';
  container.style.minWidth = '250px';
  
  container.innerHTML = `
    <div class="space-y-3">
      <!-- Header -->
      <div class="flex items-start gap-3">
        <div class="flex items-center justify-center w-10 h-10 rounded-full" 
             style="background-color: ${config.bgColor};">
          <i class="fas ${config.icon} text-lg" style="color: ${config.color};"></i>
        </div>
        <div class="flex-1 min-w-0">
          <h3 class="text-base font-bold text-foreground truncate">${pin.title}</h3>
          <p class="text-xs text-muted-fg">${config.label}</p>
        </div>
      </div>
      
      <!-- Story section -->
      ${pin.note ? `
        <div class="p-3 bg-amber-50 rounded-lg border border-amber-200">
          <div class="flex items-center gap-2 mb-1.5">
            <i class="fas fa-message text-amber-600 text-sm"></i>
            <span class="text-xs font-semibold text-amber-900">Cerita</span>
          </div>
          <p class="text-sm text-amber-900 leading-relaxed whitespace-pre-wrap">${pin.note}</p>
        </div>
      ` : ''}
      
      <!-- Metadata -->
      <div class="flex items-center gap-2 text-xs text-muted-fg">
        <i class="fas fa-calendar text-xs"></i>
        <span>${pin.created_at ? formatDate(pin.created_at) : ''}</span>
      </div>
    </div>
  `;
  
  return container;
}

(function () {
  const map = window.AmictaMap.init('map');
  const token = window.AmictaMap.token();
  const catInput = document.getElementById('pin-category');

  fetch('/map/data', { headers: { Accept: 'application/json' } })
    .then((r) => r.json())
    .then((d) => {
      const pts = [];
      d.pins.forEach((p) => {
        const marker = createPinMarker(p.lat, p.lng, p.category, !!p.note);
        const popup = createPinPopup(p);
        marker.bindPopup(popup).addTo(map);
        pts.push([p.lat, p.lng]);
      });
      window.AmictaMap.fitTo(map, pts);
    });

  map.on('click', async (e) => {
    await promptPinCreation(e.latlng.lat, e.latlng.lng, false);
  });

  async function promptPinCreation(lat, lng, isCurrentLocation = false) {
    const category = catInput.value;
    const result = await window.AmictaDialog.prompt(
      isCurrentLocation ? 'Tambah titik di lokasi Anda' : 'Judul titik?',
      {
        label: 'Judul',
        placeholder: 'mis. Jalan rusak',
        extra: { 
          label: 'Cerita/Catatan (opsional)', 
          placeholder: 'Ceritakan apa yang terjadi di sini...'
        },
      }
    );
    if (!result) return;
    
    fetch('/map/pins', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: JSON.stringify({ 
        category, 
        lat, 
        lng, 
        title: result.value, 
        note: result.extra || null 
      }),
    }).then(() => location.reload());
  }

  // Wire up current location button
  const btnLocPin = document.getElementById('btn-current-location-pin');
  if (btnLocPin) {
    btnLocPin.onclick = async () => {
      const textEl = document.getElementById('loc-pin-text');
      const spinnerEl = document.getElementById('loc-pin-spinner');

      const resetBtn = () => {
        textEl.textContent = 'Lokasi Saya';
        spinnerEl.classList.add('hidden');
        btnLocPin.disabled = false;
      };
      
      textEl.textContent = 'Mengambil lokasi...';
      spinnerEl.classList.remove('hidden');
      btnLocPin.disabled = true;
      
      try {
        const pos = await window.AmictaGeolocation.getCurrentPosition();
        map.flyTo([pos.lat, pos.lng], 16);
        await promptPinCreation(pos.lat, pos.lng, true);
      } catch (error) {
        await window.AmictaDialog.alert(window.AmictaGeolocation.getErrorMessage(error));
      } finally {
        resetBtn();
      }
    };
  }

  document.querySelectorAll('[data-delete-pin]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const ok = await window.AmictaDialog.confirm('Hapus titik ini?', { danger: true, confirmText: 'Hapus' });
      if (!ok) return;
      fetch(`/map/pins/${btn.dataset.deletePin}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      }).then(() => location.reload());
    });
  });
})();
