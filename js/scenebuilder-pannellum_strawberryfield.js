(function ($, Drupal, drupalSettings, pannellum) {



    function marker(event, $marker, $container) {
        var pos = mousePosition(event,$container);
        $marker.css("left", pos.x + 'px');
        $marker.css("top", pos.y + 'px');
        $marker.css("opacity", '1.0');
        $marker.css("display", 'block');
        $marker.fadeIn('slow');
        $marker.fadeOut('slow');
    }


    function mousePosition(event,$container) {
        var bounds = $container.getBoundingClientRect();
        var pos = {};
        // pageX / pageY needed for iOS
        pos.x = (event.clientX || event.pageX) - bounds.left;
        pos.y = (event.clientY || event.pageY) - bounds.top;
        return pos;
    }

    Drupal.AjaxCommands.prototype.webform_strawberryfield_pannellum_editor_addHotSpot = function(ajax, response, status) {
        console.log('adding hotspot');
        // we need to find the first  '.strawberry-panorama-item' id that is child of data-drupal-selector="edit-panorama-tour-hotspots"
        console.log(response.selector);
        $targetScene = $(response.selector).find('.strawberry-panorama-item').attr("id");
        console.log($targetScene);
        if (response.hasOwnProperty('hotspot')) {
            $scene = Drupal.FormatStrawberryfieldPanoramas.panoramas.get($targetScene);
            // add click handlers for new Hotspots if they have an URL.
            // Empty URLs are handled by Drupal.FormatStrawberryfieldhotspotPopUp()
            console.log(response);
            if (response.hotspot.hasOwnProperty('URL')) {
                response.hotspot.clickHandlerFunc = Drupal.FormatStrawberryfieldhotspotPopUp;
                response.hotspot.clickHandlerArgs = response.hotspot.URL;
            }

            $scene.panorama.addHotSpot(response.hotspot);
        }
    };


    Drupal.AjaxCommands.prototype.webform_strawberryfield_pannellum_editor_removeHotSpot = function(ajax, response, status) {
        console.log('removing hotspot');
        // we need to find the first  '.strawberry-panorama-item' id that is child of data-drupal-selector="edit-panorama-tour-hotspots"
        console.log(response.selector);
        $targetScene = $(response.selector).find('.strawberry-panorama-item').attr("id");
        console.log($targetScene);
        if (response.hasOwnProperty('hotspotid') && response.hasOwnProperty('sceneid')) {
            $scene = Drupal.FormatStrawberryfieldPanoramas.panoramas.get($targetScene);
            // add click handlers for new Hotspots if they have an URL.
            // Empty URLs are handled by Drupal.FormatStrawberryfieldhotspotPopUp()
            console.log(response);
            $scene.panorama.removeHotSpot(response.hotspotid.id);
            console.log("removing hotspot with id " +response.hotspotid.id);
        }
    };




    Drupal.behaviors.webform_strawberryfield_pannellum_editor = {
        attach: function(context, settings) {
            $('.strawberry-panorama-item[data-iiif-image]').once('attache_pne')
                .each(function (index, value) {
                    var hotspots = [];
                    // Get the node uuid for this element
                    var element_id = $(this).attr("id");
                    console.log('Checking for loaded Panoramatour builder Hotspots');
                    console.log(drupalSettings.webform_strawberryfield.WebformPanoramaTour);
                    // Feed with existing Hotspots comming from the Webform Element data.
                    for (var parentselector in drupalSettings.webform_strawberryfield.WebformPanoramaTour) {
                        if (Object.prototype.hasOwnProperty.call(drupalSettings.webform_strawberryfield.WebformPanoramaTour, parentselector)) {
                          $targetScene = $("[data-webform_strawberryfield-selector='" + parentselector + "']").find('.strawberry-panorama-item').attr("id");
                            console.log(parentselector);
                            console.log($targetScene);
                            $scene = Drupal.FormatStrawberryfieldPanoramas.panoramas.get($targetScene);
                            if ((typeof $scene !== 'undefined')) {
                                if (drupalSettings.webform_strawberryfield.WebformPanoramaTour[parentselector]!== null
                                && typeof drupalSettings.webform_strawberryfield.WebformPanoramaTour[parentselector][Symbol.iterator] === 'function'
                                ) {
                                    drupalSettings.webform_strawberryfield.WebformPanoramaTour[parentselector].forEach(function (hotspotdata, key) {
                                        console.log(hotspotdata);
                                        if (hotspotdata.hasOwnProperty('URL')) {
                                            hotspotdata.clickHandlerFunc = Drupal.FormatStrawberryfieldhotspotPopUp;
                                            hotspotdata.clickHandlerArgs = hotspotdata.URL;
                                        }
                                        $scene.panorama.addHotSpot(hotspotdata);
                                    });
                               }
                            }
                        }
                    }

                    // Check if we got some data passed via Drupal settings.
                    if (typeof(drupalSettings.format_strawberryfield.pannellum[element_id]) != 'undefined') {
                        console.log('initializing Panellum Panorama Builder')
                        console.log(Drupal.FormatStrawberryfieldPanoramas.panoramas);
                        Drupal.FormatStrawberryfieldPanoramas.panoramas.forEach(function(item, key) {

                            var element_id_marker = element_id + '_marker';
                            var $newmarker = $( "<div class='hotspot_marker_wrapper'><div class='hotspot_editor_marker' id='" + element_id_marker +"'></div></div>");

                            $("#" +element_id+ " .pnlm-ui").append( $newmarker );

                            item.panorama.on('mousedown', function clicker(e) {

                                $hotspot_cors = item.panorama.mouseEventToCoords(e);
                                console.log(item.panorama.getHfov());
                                var $jquerycontainer = $(item.panorama.getContainer());

                                $button_container = $jquerycontainer.closest("[data-drupal-selector='edit-panorama-tour-hotspots-temp']");

                                $button_container.find("[  data-drupal-hotspot-property='yaw']").val($hotspot_cors[1]);
                                $button_container.find("[  data-drupal-hotspot-property='pitch']").val($hotspot_cors[0]);
                                $button_container.find("[  data-drupal-hotspot-property='hfov']").val(item.panorama.getHfov());

                                marker(e,$newmarker,item.panorama.getContainer());

                                }
                            );
                        });
                    }

                })}}

})(jQuery, Drupal, drupalSettings, window.pannellum);
