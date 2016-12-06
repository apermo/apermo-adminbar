jQuery(document).ready(function ($) {
    function adminbar_toggle() {
        $('#wpadminbar').addClass('touched').fadeToggle(200,function () {
            $('body').toggleClass('admin-bar');
            if ( $('body').hasClass('admin-bar') ) {
                $('html').addClass('six').css('margin-top', '');
            } else {
                $('html').attr('style', function(i,s) { return (s||'') + ' margin-top: inherit !important;' });
            }
        });
    }

    function watermark_toggle() {
        $('.apermo-adminbar-watermark').fadeToggle();
    }


    $(document).on('keydown', function ( e ) {
        if ( e.ctrlKey ) {
            switch ( e.which ) {
                case 69: //e
                    adminbar_toggle();
                    break;
                case 87: //w
                    watermark_toggle();
                    break;
            }
        }
    });
});