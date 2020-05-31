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
            console.log('hey!');
            // Only react if the document contains a strawberry webform widget
            if ($('.path-node fieldset[data-strawberryfield-selector="strawberry-webform-widget"]').length) {
                $('input[type="file"].form-file').each(function(idx, item) {
                    console.log($(item).attr('accept'));
                    if ($(item).attr('accept') == 'image/*') {
                        $(item).attr('accept', 'image/*;capture=camera');
                    }
                });
            }
        }
    }
})(jQuery, Drupal, drupalSettings);