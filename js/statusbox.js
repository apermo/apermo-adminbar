jQuery(document).ready(function($){
    $('.has-static').click(function(){
        if ( $(this).hasClass('static') ) {
            $(this).removeClass('hover static');
        } else {
            $(this).addClass('static');
        }
    })
});