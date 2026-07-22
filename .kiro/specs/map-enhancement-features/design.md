# Design Document: Map Enhancement Features

## Overview

This design enhances the map functionality by adding three major features: (1) "Use Current Location" buttons for pins and route planning, (2) Enhanced pin markers with custom icons like Google Maps, and (3) Story/context field for each pin. The implementation focuses on improving UX and visual appeal while maintaining the existing codebase structure.

## Architecture

The enhancement consists of three independent but complementary modules:
1. **Geolocation Module**: Handles browser geolocation API integration
2. **Pin Icon System**: Custom marker rendering with Font Awesome icons
3. **Story System**: Extended pin data model with story/context field

**Technology Stack:**
- Leaflet.js for map rendering
- Font Awesome 6 for icon library
- Browser Geolocation API
- Laravel backend for data persistence
- Tailwind CSS for styling

## Components and Interfaces

### 1. Geolocation Module (geolocation.js)

```javascript
/**
 * Geolocation utility module
 * Provides current location functionality with error handling
 */
window.AmictaGeolocation = (function() {
  
  /**
   * Check if geolocation is supported
   * @returns {boolean}
   */
  function isSupported() {
    return 'geolocation' in navigator;
  }
  
  /**
   * Get current position with promise-based API
   * @param {Object} options - Geolocation options
   * @returns {Promise<{lat: number, lng: number}>}
   */
  function getCurrentPosition(options = {}) {
    return new Promise((resolve, reject) => {
      if (!isSupported()) {
        reject(new Error('UNSUPPORTED'));
        return;
      }
      
      const defaultOptions = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 30000
      };
      
      navigator.geolocation.getCurrentPosition(
        (position) => {
          resolve({
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy: position.coords.accuracy
          });
        },
        (error) => {
          reject(error);
        },
        { ...defaultOptions, ...options }
      );
    });
  }
  
  /**
   * Get user-friendly error message
   * @param {GeolocationPositionError|Error} error
   * @returns {string}
   */
  function getErrorMessage(error) {
    if (error.message === 'UNSUPPORTED') {
      return 'Browser Anda tidak mendukung fitur lokasi';
    }
    
    switch (error.code) {
      case error.PERMISSION_DENIED:
        return 'Izin lokasi ditolak. Aktifkan di pengaturan browser Anda.';
      case error.POSITION_UNAVAILABLE:
        return 'Posisi tidak tersedia saat ini. Coba lagi nanti.';
      case error.TIMEOUT:
        return 'Waktu tunggu habis. Coba lagi.';
      default:
        return 'Gagal mendapatkan lokasi. Coba lagi.';
    }
  }
  
  return {
    isSupported,
    getCurrentPosition,
    getErrorMessage
  };
})();
```

### 2. Enhanced Pin Icon System

#### 2.1 Icon Mapping Configuration

```javascript
/**
 * Pin icon configuration
 * Maps categories to Font Awesome icons and colors
 */
window.AmictaPinIcons = {
  infrastruktur: {
    icon: 'fa-road-barrier',
    color: '#3B82F6', // blue
    bgColor: '#EFF6FF',
    label: 'Infrastruktur'
  },
  bencana: {
    icon: 'fa-triangle-exclamation',
    color: '#EF4444', // red
    bgColor: '#FEF2F2',
    label: 'Bencana'
  },
  layanan: {
    icon: 'fa-building',
    color: '#8B5CF6', // purple
    bgColor: '#F5F3FF',
    label: 'Layanan'
  },
  lainnya: {
    icon: 'fa-location-dot',
    color: '#6B7280', // gray
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
```

#### 2.2 Custom DivIcon Marker

```javascript
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
```

### 3. Current Location Button Component

```javascript
/**
 * Create "Use Current Location" button HTML
 * @param {string} context - 'pin' | 'route-start' | 'route-end'
 * @returns {HTMLElement}
 */
function createLocationButton(context) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-xl bg-primary/10 text-primary hover:bg-primary/20 transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed';
  button.innerHTML = `
    <i class="fas fa-location-crosshairs"></i>
    <span class="location-btn-text">Gunakan Lokasi Saya</span>
    <i class="fas fa-spinner fa-spin hidden location-btn-spinner"></i>
  `;
  
  button.dataset.locationBtn = context;
  
  return button;
}

/**
 * Set button loading state
 * @param {HTMLElement} button
 * @param {boolean} loading
 */
function setButtonLoading(button, loading) {
  const text = button.querySelector('.location-btn-text');
  const spinner = button.querySelector('.location-btn-spinner');
  
  if (loading) {
    text.textContent = 'Mengambil lokasi...';
    spinner.classList.remove('hidden');
    button.disabled = true;
  } else {
    text.textContent = 'Gunakan Lokasi Saya';
    spinner.classList.add('hidden');
    button.disabled = false;
  }
}
```

### 4. Enhanced Pin Creation Flow (map-pins.js)

```javascript
// Updated map click handler with current location support
map.on('click', async (e) => {
  await promptPinCreation(e.latlng.lat, e.latlng.lng);
});

// Add floating action button for current location
function addCurrentLocationFAB() {
  if (!window.AmictaGeolocation.isSupported()) return;
  
  const fab = document.createElement('button');
  fab.className = 'fixed bottom-24 right-6 z-[999] w-14 h-14 bg-primary text-white rounded-full shadow-lift hover:shadow-xl transition-all hover:scale-110 active:scale-95';
  fab.innerHTML = '<i class="fas fa-location-crosshairs text-xl"></i>';
  fab.title = 'Tambah pin di lokasi saya';
  
  fab.onclick = async () => {
    try {
      fab.innerHTML = '<i class="fas fa-spinner fa-spin text-xl"></i>';
      fab.disabled = true;
      
      const pos = await window.AmictaGeolocation.getCurrentPosition();
      
      fab.innerHTML = '<i class="fas fa-location-crosshairs text-xl"></i>';
      fab.disabled = false;
      
      // Pan map to current location
      map.flyTo([pos.lat, pos.lng], 16);
      
      // Prompt pin creation at current location
      await promptPinCreation(pos.lat, pos.lng, true);
      
    } catch (error) {
      fab.innerHTML = '<i class="fas fa-location-crosshairs text-xl"></i>';
      fab.disabled = false;
      
      const msg = window.AmictaGeolocation.getErrorMessage(error);
      await window.AmictaDialog.alert(msg);
    }
  };
  
  document.body.appendChild(fab);
}

async function promptPinCreation(lat, lng, isCurrentLocation = false) {
  const category = document.getElementById('pin-category').value;
  
  const result = await window.AmictaDialog.prompt(
    isCurrentLocation ? 'Tambah titik di lokasi Anda' : 'Judul titik?',
    {
      label: 'Judul',
      placeholder: 'mis. Jalan rusak',
      extra: { 
        label: 'Cerita/Catatan (opsional)', 
        placeholder: 'Ceritakan apa yang terjadi di sini...',
        rows: 3
      },
    }
  );
  
  if (!result) return;
  
  const payload = {
    category,
    lat,
    lng,
    title: result.value,
    note: result.extra || null,
    is_current_location: isCurrentLocation
  };
  
  fetch('/map/pins', {
    method: 'POST',
    headers: { 
      'Content-Type': 'application/json', 
      'X-CSRF-TOKEN': window.AmictaMap.token(), 
      Accept: 'application/json' 
    },
    body: JSON.stringify(payload),
  }).then(() => location.reload());
}
```

### 5. Route Planning Current Location Integration

```javascript
// Add current location buttons to route planning UI
function enhanceRoutePlanning() {
  const startContainer = document.querySelector('[data-route-start-container]');
  const endContainer = document.querySelector('[data-route-end-container]');
  
  if (window.AmictaGeolocation.isSupported()) {
    // Add to start point
    const startBtn = createLocationButton('route-start');
    startBtn.onclick = () => handleRouteLocationClick('start');
    startContainer.appendChild(startBtn);
    
    // Add to end point
    const endBtn = createLocationButton('route-end');
    endBtn.onclick = () => handleRouteLocationClick('end');
    endContainer.appendChild(endBtn);
  }
}

async function handleRouteLocationClick(type) {
  const button = document.querySelector(`[data-location-btn="route-${type}"]`);
  
  try {
    setButtonLoading(button, true);
    
    const pos = await window.AmictaGeolocation.getCurrentPosition();
    
    // Reverse geocode to get location label
    const loc = await reverseGeocode(pos.lat, pos.lng);
    loc.lat = pos.lat;
    loc.lng = pos.lng;
    
    setButtonLoading(button, false);
    
    // Set as start or end point
    if (type === 'start') {
      setStart(loc);
    } else {
      setEnd(loc);
    }
    
    // Pan map to location
    map.flyTo([pos.lat, pos.lng], 14);
    
  } catch (error) {
    setButtonLoading(button, false);
    
    const msg = window.AmictaGeolocation.getErrorMessage(error);
    await window.AmictaDialog.alert(msg);
  }
}
```

### 6. Enhanced Popup with Story Display

```javascript
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
        <span>${formatDate(pin.created_at)}</span>
      </div>
      
      <!-- Actions -->
      <div class="flex gap-2 pt-2 border-t border-border">
        <button data-edit-pin="${pin.id}" 
                class="flex-1 px-3 py-2 text-sm font-semibold rounded-lg bg-muted hover:bg-muted/80 text-foreground transition">
          <i class="fas fa-edit mr-1.5"></i>Edit
        </button>
        <button data-delete-pin="${pin.id}"
                class="flex-1 px-3 py-2 text-sm font-semibold rounded-lg bg-accent/10 hover:bg-accent/20 text-accent transition">
          <i class="fas fa-trash mr-1.5"></i>Hapus
        </button>
      </div>
    </div>
  `;
  
  return container;
}

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
```

### 7. Database Migration for Story Field

The `note` field already exists in pins table based on current code, so no migration needed. We'll just enhance how it's used and displayed.

## Data Models

### Pin Model (Enhanced)

```php
// app/Models/Pin.php - Already exists, no changes needed
class Pin extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'lat',
        'lng',
        'title',
        'note', // This is our "story" field
    ];
    
    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];
    
    // Helper to check if pin has story
    public function hasStory(): bool
    {
        return !empty($this->note);
    }
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Geolocation Permission Handling

*For any* geolocation request, if the browser denies permission, the system should display an appropriate error message and keep manual location selection available.

**Validates: Requirements 1.5, 6.2**

### Property 2: Icon Mapping Consistency

*For any* pin category, the system should always display the same icon and color combination, ensuring visual consistency across the map.

**Validates: Requirements 3.2, 3.3, 3.4, 3.5**

### Property 3: Story Preservation

*For any* pin with a story, editing the pin should preserve the story content unless explicitly changed by the user.

**Validates: Requirements 4.5**

### Property 4: Current Location Accuracy

*For any* successful geolocation request, the returned coordinates should be within the accuracy radius reported by the browser API.

**Validates: Requirements 1.3, 2.3**

### Property 5: Fallback Icon Rendering

*For any* pin with an undefined or invalid category, the system should render the default "lainnya" icon instead of failing.

**Validates: Requirements 5.5**

### Property 6: Loading State Consistency

*For any* current location button click, the button should show loading state during the async operation and restore to normal state afterwards, regardless of success or failure.

**Validates: Requirements 8.1, 8.2, 8.3, 8.5**

## Error Handling

1. **Geolocation Timeout**: If position request times out (10s), show user-friendly message and allow retry
2. **Permission Denied**: Show instructions to enable location in browser settings
3. **Position Unavailable**: Suggest checking internet connection and GPS
4. **Unsupported Browser**: Hide current location features gracefully
5. **Icon Loading Failure**: Use fallback default icon if Font Awesome fails to load
6. **Story Too Long**: Limit story field to 500 characters with client-side validation

## Testing Strategy

### Unit Tests

1. **Geolocation Module**: Test all error codes map to correct messages
2. **Icon Configuration**: Test getIconConfig returns correct config for each category
3. **Marker Creation**: Test createPinMarker generates correct HTML structure
4. **Story Display**: Test popup shows story section only when note exists

### Integration Tests

1. Test complete flow: Click location button → get position → create pin
2. Test route planning: Use current location as start → set destination → calculate route
3. Test pin with story: Create pin with story → reload → verify story persists
4. Test icon rendering: Create pins in all categories → verify correct icons display
5. Test error handling: Deny permission → verify error message → manual pin creation still works

### Property Tests

Property-based tests should be written using a JavaScript property testing library (e.g., fast-check) with minimum 100 iterations each.

1. **Property 1 Test**: Generate random geolocation errors, verify correct error message returned
2. **Property 2 Test**: Generate random pin categories, verify icon config is consistent
3. **Property 3 Test**: Generate random pin data with stories, verify stories display in popup
4. **Property 4 Test**: Generate random coordinates, verify markers render at correct positions
5. **Property 5 Test**: Generate invalid categories, verify fallback icon is used
6. **Property 6 Test**: Generate random async delays, verify loading states behave correctly

### Browser Testing

- Chrome/Edge: Test geolocation API with different permission states
- Firefox: Test icon rendering with Font Awesome
- Safari iOS: Test on mobile with real GPS
- Test on slow connections to verify loading states

