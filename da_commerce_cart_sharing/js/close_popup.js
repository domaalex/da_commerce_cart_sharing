(function ($, Drupal) {
    Drupal.AjaxCommands.prototype.closePopup = function (ajax, response, status) {
        var closePopup = function(){
            // Close modal window.
            $('.ui-widget-header .ui-dialog-titlebar-close').trigger('click');
        };
        setTimeout(closePopup, response.delayTime);
    };
})(jQuery, Drupal);