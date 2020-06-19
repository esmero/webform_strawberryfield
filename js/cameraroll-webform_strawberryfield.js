/**
 * @file
 * Adds an capture camera to inputs
 */

(function ($, Drupal, drupalSettings) {

    'use strict';

    /**
     * Attaches our custom show/hide node actions button
     *
     * @type {Drupal~behavior}
     *
     * @prop {Drupal~behaviorAttach} attach
     *   Attaches the autocomplete behaviors.
     */
    Drupal.behaviors.webformstrawberryCameraRoll = {
        attach: function (context, settings) {
            // Only react if the document contains a strawberry webform widget
            if ($('.path-node fieldset[data-strawberryfield-selector="strawberry-webform-widget"]').length) {
                $('input[type="file"].form-file').each(function(idx, item) {
                    console.log($(item).attr('accept'));
                    if ($(item).attr('accept') == 'image/*') {
                        // We will add by default jp2. If the element is configured to not support it, that is Ok
                        // Element validation handler will just complain and be will be safe.
                        $(item).attr('accept', 'image/*;capture=camera;image/jp2');
                    }
                    else if ($(item).attr('accept') == 'video/*') {
                        // We will add by default mp4. If the element is configured to not support it, that is Ok
                        // Element validation handler will just complain and be will be safe.
                        $(item).attr('accept', 'video/*;capture=camera;video/mp4');
                    }
                });
            }
        }
    }
})(jQuery, Drupal, drupalSettings);