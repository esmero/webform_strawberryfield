# Webform Strawberry Field
A module that provides Drupal 8/9 Webform ( == awesome piece of code) integrations for StrawberryField so you can really have control over your Metadata ingests. This is part of the Archipelago Commons Project.

# Setup

This module provides many LoD Autocomplete suggester Webform Elements, but only *The Europeana Entity Suggester* for now requires you to provide an `APIKEY`.
To be able to use the Europeana Suggester edit your Drupal `settings.php` file (located normally in `web/sites/default/settings.php`) and add the following line:

```PHP
$settings['webform_strawberryfield.europeana_entity_apikey'] = 'thekey';
```

Save and clear caches.

In its current state the Europeana Entity API (Alpha 0.5) uses a static APIKEY (not the same as other APIs) and can be found at https://pro.europeana.eu/page/entity#suggest

If using https://github.com/esmero/archipelago-deployment this is not needed and will be provided by the deployment.

## Help

Having issues with this module? Check out the Archipelago Commons google groups for tech + emotional support + updates.

* [Archipelago Commons](https://groups.google.com/forum/#!forum/archipelago-commons)

## Demo

* archipelago.nyc (http://archipelago.nyc)

## Caring & Coding + Fixing

* [Diego Pino](https://github.com/DiegoPino)
* [Giancarlo Birello](https://github.com/giancarlobi)
* [Allison Lund](https://github.com/alliomeria)

## Acknowledgments

This software is a [Metropolitan New York Library Council](https://metro.org) Open-Source initiative and part of the Archipelago Commons project.

## License

[GPLv3](http://www.gnu.org/licenses/gpl-3.0.txt)

