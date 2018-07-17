( function ($) {
  $('.warning-alert-incompatible').each( function () {
   $(this).parents().parent( "tr" )
    .removeClass("active")
    .css( 'background-color', '#f2dede');
  });
})(jQuery);