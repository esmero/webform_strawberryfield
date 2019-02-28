/**
 * @file
 * Expands the behaviour of the default autocompletion to fill 2 fields.
 */

(function ($, Drupal, drupalSettings) {

    'use strict';

    /**
     * Attaches our custom show/hide node submit button
     *
     * @type {Drupal~behavior}
     *
     * @prop {Drupal~behaviorAttach} attach
     *   Attaches the autocomplete behaviors.
     */
    Drupal.behaviors.webformstrawberryHideNodeActions = {
        attach: function (context, settings) {
            // Find all our fields with autocomplete settings

            if ($('.webform-confirmation',context).length) {
                $('input[data-drupal-selector="edit-submit"]').show();
            }


        }
    };
})(jQuery, Drupal, drupalSettings);
