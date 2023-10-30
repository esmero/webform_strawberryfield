/**
 * @file
 * Expands the behaviour of the default autocompletion to fill 2 fields.
 */

(function ($, Drupal, once, drupalSettings) {

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
            if ($('.node-form fieldset[data-strawberryfield-selector="strawberry-webform-widget"]').length) {
                if ($('.webform-confirmation',context).length) {
                    // Exclude webform own edit-actions containter
                    /* And hide, if present the close button but only show save if all are ready.
                    @TODO we need to figure out what if there are many webforms open at different states in the same
                    Entity Form.
                    */
                    $('.node-form div[data-drupal-selector="edit-actions"]').not('.webform-actions').show();
                    $('.node-form div[data-drupal-selector="edit-footer"]').not('.webform-actions').show();
                    $('.webform-confirmation').closest('[data-strawberryfield-selector="strawberry-webform-widget"]').each(function() {
                        var $id = $(this).attr('id') + '-strawberry-webform-close-modal';
                        $('#' + $id).toggleClass('js-hide');
                    })

               } else if (
                    $('div.field--widget-strawberryfield-webform-inline-widget .webform-submission-form').length ||
                    $('div.field--widget-strawberryfield-webform-widget .webform-submission-form').length
                   )
               {
                   $('.node-form div[data-drupal-selector="edit-actions"]').not('.webform-actions').hide();
                   $('.node-form div[data-drupal-selector="edit-footer"]').not('.webform-actions').hide();
               }

               const elementsToAttach = once('show-hide-actions', 'select[data-drupal-selector="edit-moderation-state-0-state"', context);
               var $moderationstate =  $(elementsToAttach);
               if ($moderationstate.length) {
                    // Nothing so far.
                }
                const elementsToAttach2 = once('show-hide-actions2', 'input[data-drupal-selector="edit-title-0-value"]', context);
                var $nodetitle = $(elementsToAttach2);
                if ($nodetitle.length) {
                    var $select = $nodetitle.on('input', function () {
                        $('.node-form div[data-drupal-selector="edit-actions"]').not('.webform-actions').show();
                    });
                }
            }
        }
    }
})(jQuery, Drupal, once, drupalSettings);
