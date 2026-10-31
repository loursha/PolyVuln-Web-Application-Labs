/* PolyVuln Bank web client */
$(function () {
  $('#navToggle').on('click', function () { $('#sidebar').toggleClass('open'); });
  $(document).on('click', function (e) {
    if ($('#sidebar').hasClass('open') && !$(e.target).closest('#sidebar,#navToggle').length) $('#sidebar').removeClass('open');
  });
  $('[data-toggle="tooltip"]').tooltip();

  // copy-to-clipboard for account numbers
  $(document).on('click', '[data-copy]', function () {
    var v = $(this).data('copy'), $b = $(this);
    if (navigator.clipboard) navigator.clipboard.writeText(String(v));
    var o = $b.html(); $b.html('<i class="fas fa-check"></i> Copied');
    setTimeout(function () { $b.html(o); }, 1400);
  });

  // reveal/hide balances
  $('#toggleBalance').on('click', function () {
    $('.js-amt').toggleClass('d-none');
    $(this).find('i').toggleClass('fa-eye fa-eye-slash');
  });
});
