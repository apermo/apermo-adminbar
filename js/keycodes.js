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
        if ( navigator.platform == 'MacIntel' || navigator.platform == 'iPhone' || navigator.platform == 'iPad'  ) {
            // Mac/iPhone
            if ( ! ( e.metaKey && e.ctrlKey ) ) {
                return;
            }
        } else {
            // Anything else
            if ( ! ( e.altKey && e.shiftKey ) ) {
                return;
            }
        }

        switch ( e.which ) {
            case 65: //a
                adminbar_toggle();
                break;
            case 87: //w
                watermark_toggle();
                break;
        }
    });
});