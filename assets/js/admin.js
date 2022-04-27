(function ($) {

    'use strict';

    let notice  = $('.ag-notification'),
        header  = notice.find('h3'),
        message = notice.find('p')
    ;

    function prepare(color) {
        notice.hide()
          .css('background-color', color)
        ;
    }

    function flash () {
        notice.show()
            .delay(5000)
            .fadeOut(1000, function () {
                notice.hide();
            })
        ;
    }

    $(".ag-settings form").submit(function (event) {
        event.preventDefault();
        $.ajax({
            type: 'POST',
            url:  ajaxurl,
            data: $(event.target).serializeArray(),
            success: function (result) {
                prepare('green');
                header.text('Success');
                message.text(result.data.message);
                flash();
            },
            error: function (request) {
                prepare('red');
                header.text('Error');
                message.text(request.responseJSON.data.error);
                flash();
            }
        });
    });

})(jQuery);
