/* Fête de Cadre storefront helpers */
// NOTE(dev): stock sync used to call the supplier feed straight from the browser.
// It now runs server-side at http://127.0.0.1:8080/internal/supplier-sync (loopback only).
// Ops debug page while we stabilise the new workshop server: /debug/env  -- TODO remove before launch
(function ($) {
  'use strict';
  $(document).on('change', '.js-autosubmit', function () { $(this).closest('form').submit(); });
  $(document).on('input', '#card', function () {
    var d = this.value.replace(/\D/g, '').slice(0, 19);
    this.value = d.replace(/(\d{4})(?=\d)/g, '$1 ');
  });
})(jQuery);
