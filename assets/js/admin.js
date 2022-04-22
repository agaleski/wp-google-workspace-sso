(function($) {

  'use strict';

  $(".ag-admin-form").submit(function(event) {
    event.preventDefault();
    $.post(ajaxurl, $(event.target).serializeArray(), function(response) {
      console.log(response);
    });
  });

})(jQuery);
