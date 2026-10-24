/* Petal & Stem storefront helpers */
// NOTE(dev): stock refresh used to call the supplier sync directly from the browser.
// It now runs server side at http://127.0.0.1:3000/internal/inventory-sync (loopback only).
// Debug info for ops: /debug/env  -- TODO remove before launch
(function ($) {
  'use strict';
  // quantity steppers
  $(document).on('click', '[data-step]', function () {
    var $input = $(this).closest('.qty').find('input[type=number]');
    var v = parseInt($input.val(), 10) || 1;
    v = Math.min(parseInt($input.attr('max'), 10) || 50, Math.max(parseInt($input.attr('min'), 10) || 1, v + parseInt($(this).data('step'), 10)));
    $input.val(v).trigger('change');
  });
  // auto-submit basket quantity changes
  $(document).on('change', '.js-autosubmit', function () { $(this).closest('form').submit(); });
  // card number spacing
  $(document).on('input', '#card', function () {
    var digits = this.value.replace(/\D/g, '').slice(0, 19);
    this.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ');
  });
})(jQuery);
