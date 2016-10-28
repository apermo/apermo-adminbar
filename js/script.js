jQuery(document).ready(function ($) {
    $('#blog_public').attr('disabled', true );
    $('.option-site-visibility td fieldset').append('<p class="description">'+apermo_adminbar.disabled_message+'</p>');
});