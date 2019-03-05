/**
 * @file
 * Expands the behaviour of the default autocompletion to fill 2 fields.
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
    Drupal.behaviors.webformstrawberryHideNodeActions = {
        attach: function (context, settings) {
            // Only react if the document contains a strawberry webform widget
            if ($('.path-node fieldset[data-strawberryfield-selector="strawberry-webform-widget"]').length) {
                if ($('.webform-confirmation',context).length) {
                    // Exclude webform own edit-actions containter
                    $('.path-node div[data-drupal-selector="edit-actions"]').not('.webform-actions').show();
                } else if ($('div.field--widget-strawberryfield-webform-inline-widget').length) {
                    $('.path-node div[data-drupal-selector="edit-actions"]').not('.webform-actions').hide();
                }
            }
        }
    };
})(jQuery, Drupal, drupalSettings);