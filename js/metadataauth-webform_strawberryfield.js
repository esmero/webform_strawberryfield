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

    /**
     * Override of Drupal.autocomplete.splitValues to deal with metadata that contains ',' like names.
     */
    function autocompleteDoNotSplitValues(value) {
        var result = [];
        var quote = false;
        var current = '';
        var valueLength = value.length;
        var character = void 0;

        for (var i = 0; i < valueLength; i++) {
            character = value.charAt(i);
            current += character;
        }
        if (value.length > 0) {
            result.push($.trim(current));
        }

        return result;
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
     * Overrides  Drupal.autocomplete.options.search for no splitting of terms
     *
     * This is the only function where even is present, means we can decide per instance
     * and not on a global basis if splitting is needed or not.
     */
    Drupal.autocomplete.options.search = function searchHandler(event) {
        var options = Drupal.autocomplete.options;

        if (options.isComposing) {
            return false;
        }
        // Check if the element has a data-source-strawberry-autocomplete-key, if so,put a flag in the class
        // Why not per instance? All the methods we need to override are totally unaware of the attachment
        // or DOM element they are connected.
        if (typeof $(event.target).data('source-strawberry-autocomplete-key') !== 'undefined') {
            Drupal.autocomplete.options.sbf = true;
        }
        else {
            Drupal.autocomplete.options.sbf = false
        }

        var term = Drupal.autocomplete.extractLastTerm(event.target.value);

        if (term.length > 0 && options.firstCharacterBlacklist.indexOf(term[0]) !== -1) {
            return false;
        }

        return term.length >= options.minLength;
    }

    var originalAutocompleteSplitValues = Drupal.autocomplete.splitValues;
    var originalextractLastTerm = Drupal.autocomplete.extractLastTerm;

    Drupal.autocomplete.splitValues = function autocompleteSplitValues(value) {
        console.log('our own splitValues');
        // If global class flag set, use our own.
        // Global is only way i found here. I have no context of the element here
        // But also, since humans can only use one mouse at the time
        // and flag gets set during the search/input. It works same as
        // options.isComposing global
        if (Drupal.autocomplete.options.sbf) {
            return autocompleteDoNotSplitValues(value);
        } else {
            return originalAutocompleteSplitValues(value);
        }
    }

    Drupal.autocomplete.extractLastTerm = function extractLastTerm(terms) {
        console.log('our own extractLastTerm');
        if (Drupal.autocomplete.options.sbf) {
            return autocompleteDoNotSplitValues(terms).pop()
        } else {
            return originalextractLastTerm(terms);
        }
    }

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
                        var tempvalue = ui.item.value.trim();
                        ui.item.value = ui.item.label.trim();
                        ui.item.label = tempvalue.trim();

                        //$(event.target).parent('.form-type-textfield').next('div.form-type-url').children('input[data-strawberry-autocomplete-value]').val(ui.item.label);
                        var targetname = $(event.target).attr('name');
                        // Where to put the value
                        var targetsource = $(event.target).data('source-strawberry-autocomplete-key');
                        // This element's name last key
                        var targetdest = $(event.target).data('target-strawberry-autocomplete-key');

                        var stripped = targetname.substring(0, targetname.indexOf('['+targetsource+']'));

                        targetname = stripped + '['+targetdest+']';
                        var targetname_hidden = stripped + '[_hidden_]['+targetdest+']';

                        if ($("input[name='"+ targetname +"']").length > 0) {
                            $("input[name='" + targetname + "']").val(ui.item.label);
                        }
                        else if($("input[name='"+ targetname_hidden +"']").length > 0)  {
                            // This acts on a hidden URL when multiple composites are in a table
                            console.log('URL element is hidden but we got it!');
                            $("input[name='" + targetname_hidden + "']").val(ui.item.label);

                        }
                        else {
                            console.log('URL form sub element does not exist. No URL will be persisted');
                            // @TODO in this case we could ajax submit and resolve via a submit handler?
                            //this.closest("form").submit();
                            //submit form.
                        }

                        // Executes pre-existing select handler
                        var ret = oldSelect.apply(this, arguments);
                        return ret;
                    };
                });
        }
    };

    Drupal.webformstrawberryAutocomplete = autocomplete;

})(jQuery, Drupal, drupalSettings);
