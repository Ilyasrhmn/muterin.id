(function () {
  document.querySelectorAll('[data-photo-picker]').forEach((wrap) => {
    const input = wrap.querySelector('[data-photo-picker-input]');
    const filenameEl = wrap.querySelector('[data-photo-picker-filename]');

    function open(useCamera) {
      if (useCamera) input.setAttribute('capture', 'environment');
      else input.removeAttribute('capture');
      input.click();
    }

    wrap.querySelector('[data-photo-picker-camera]').addEventListener('click', () => open(true));
    wrap.querySelector('[data-photo-picker-gallery]').addEventListener('click', () => open(false));
    input.addEventListener('change', () => {
      filenameEl.textContent = input.files[0] ? input.files[0].name : '';
    });
  });
})();
