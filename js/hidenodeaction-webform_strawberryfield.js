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
                    /* And hide, if present the close button but only show save if all are ready.
                    @TODO we need to figure out what if there are many webforms open at different states in the same
                    Entity Form.
                    */
                    $('.path-node .node-form div[data-drupal-selector="edit-actions"]').not('.webform-actions').show();
                    $('.path-node .node-form div[data-drupal-selector="edit-footer"]').not('.webform-actions').show();
                    $('.webform-confirmation').closest('[data-strawberryfield-selector="strawberry-webform-widget"]').each(function() {
                        var $id = $(this).attr('id') + '-strawberry-webform-close-modal';
                        $('#' + $id).toggleClass('js-hide');
                    })


               } else if (
                    $('div.field--widget-strawberryfield-webform-inline-widget').length ||
                    $('div.field--widget-strawberryfield-webform-widget').length
                   )
               {
                   $('.path-node .node-form div[data-drupal-selector="edit-actions"]').not('.webform-actions').hide();
                   $('.path-node .node-form div[data-drupal-selector="edit-footer"]').not('.webform-actions').hide();
               }
               var $moderationstate = $('select[data-drupal-selector="edit-moderation-state-0-state"]', context).once('show-hide-actions');
               if ($moderationstate.length) {

                   /* var $select = $moderationstate.on('change', function () {
                       $('.path-node .node-form div[data-drupal-selector="edit-actions"]').not('.webform-actions').show();

                   }); */
                }
                var $nodetitle = $('input[data-drupal-selector="edit-title-0-value"]', context).once('show-hide-actions');
                if ($nodetitle.length) {
                    var $select = $nodetitle.on('input', function () {
                        $('.path-node .node-form div[data-drupal-selector="edit-actions"]').not('.webform-actions').show();

                    });
                }
            }
        }
    }
})(jQuery, Drupal, drupalSettings);