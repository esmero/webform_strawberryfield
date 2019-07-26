/**
 * @file
 * Expands the behaviour of the default autocompletion to fill 2 fields.
 */

(function ($, Drupal, drupalSettings) {

    'use strict';

    // Bail if no Drupal Core autocomplete framework
    if (!Drupal.autocomplete) {
        return;
    }

    function selectHandlerDual(event, ui) {
        var terms = Drupal.autocomplete.splitValues(event.target.value);

        terms.pop();

        terms.push(ui.item.value);

        event.target.value = terms.join(', ');

        return false;
    }




    var autocomplete = {};

    /**
     * Retrieves the custom settings for an autocomplete-enabled input field.
     *
     * @param {HTMLElement} input
     *   The input field.
     * @param {object} globalSettings
     *   The object containing global settings. If none is passed, drupalSettings
     *   is used instead.
     *
     * @return {object}
     *   The effective settings for the given input fields, with defaults set if
     *   applicable.
     */
    autocomplete.getSettings = function (input, globalSettings) {
        globalSettings = globalSettings || drupalSettings || {};
        // Set defaults for all known settings.
        var settings = {
            delay: 1,
            min_length: 3,
        };
        var search = $(input).data('strawberry-autocomplete');
        if (search
            && globalSettings.webform_strawberryfield_autocomplete
            && globalSettings.webform_strawberryfield_autocomplete[search]) {
            $.extend(settings, globalSettings.webform_strawberryfield_autocomplete[search]);
        }
        return settings;
    };

    /**
     * Attaches our custom autocomplete settings to all affected fields.
     *
     * @type {Drupal~behavior}
     *
     * @prop {Drupal~behaviorAttach} attach
     *   Attaches the autocomplete behaviors.
     */
    Drupal.behaviors.webformstrawberryAutocomplete = {
        attach: function (context, settings) {
            // Find all our fields with autocomplete settings
            $(context)
                .find('.ui-autocomplete-input[data-strawberry-autocomplete]')
                .once('strawberry-autocomplete-auth')
                .each(function () {
                    var uiAutocomplete = $(this).data('ui-autocomplete');
                    if (!uiAutocomplete) {
                        return;
                    }
                    var $element = uiAutocomplete.menu.element;
                    $element.addClass('strawberry-autocomplete-auth');
                    var elementSettings = autocomplete.getSettings(this, settings);
                    if (elementSettings['delay']) {
                        uiAutocomplete.options['delay'] = elementSettings['delay'];
                    }
                    if (elementSettings['min_length']) {
                        uiAutocomplete.options['minLength'] = elementSettings['min_length'];
                    }
                    // Override the "select" callback of the jQuery UI autocomplete.
                    var oldSelect = uiAutocomplete.options.select;
                    uiAutocomplete.options.select = function (event, ui) {
                        // Invert value/label. Put label inside autocomplete, value in the next uri input
                        // This piece helps users disambiguate if the handler gives us a description too.
                        if ((ui.item.desc) && (ui.item.desc.length)) {
                            console.log('Additional Description is :' + ui.item.desc);
                            ui.item.label = ui.item.label.substring(0, ui.item.label.indexOf(ui.item.desc));
                        }
                        var tempvalue = ui.item.value;
                        ui.item.value = ui.item.label;
                        ui.item.label = tempvalue;

                        //$(event.target).parent('.form-type-textfield').next('div.form-type-url').children('input[data-strawberry-autocomplete-value]').val(ui.item.label);
                        var targetname = $(event.target).attr('name');
                        // Where to put the value
                        var targetsource = $(event.target).data('source-strawberry-autocomplete-key');
                        // This element's name last key
                        var targetdest = $(event.target).data('target-strawberry-autocomplete-key');


                        var stripped = targetname.substring(0, targetname.indexOf('['+targetsource+']'));

                        targetname = stripped + '['+targetdest+']';
                        console.log(targetname);
                        $("input[name='"+ targetname +"']").val(ui.item.label);

                        var ret = oldSelect.apply(this, arguments);
                        return ret;
                    };
                });
        }
    };

    Drupal.webformstrawberryAutocomplete = autocomplete;

})(jQuery, Drupal, drupalSettings);
