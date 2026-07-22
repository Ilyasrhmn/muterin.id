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
   * @returns {Promise<{lat: number, lng: number, accuracy: number}>}
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
