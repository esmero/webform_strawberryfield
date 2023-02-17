<?php

namespace Drupal\webform_strawberryfield\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableJsonResponse;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;

/**
 * Defines a route controller for Nominatim form elements.
 *
 * @see https://operations.osmfoundation.org/policies/nominatim/ for usage policies.
 */
class NominatimController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * NominatimController constructor.
   *
   * @param \GuzzleHttp\Client $httpClient
   */
  public function __construct(Client $httpClient) {
    $this->httpClient = $httpClient;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Controller\ControllerBase|\Drupal\webform_strawberryfield\Controller\NominatimController
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
      );
  }

  /**
   * Handler for the Nominatim request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param $api_type
   * @param $count
   * @param string $lang
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function handleRequest(Request $request, $api_type = 'search', $count = 5, string $lang = '') {
    //@TODO pass count to the actual fetchers
    //@TODO maybe refactor into plugins so others can write any other reconciliators
    //@TODO if so, we can query the plugins and show in the webform builder options
    // Get the typed string from the URL, if it exists.
    $lang = !empty($lang) ? trim($lang) : $this->languageManager()->getCurrentLanguage()->getId();
    $results = [];

    // Switch on api_type, because queries will have different keys. But still check keys.
    switch($api_type) {
        case 'search':
          if ($input = $request->query->get('q')) {
            $results = $this->search($input, $count, $lang);
          }
          break;
        case 'reverse':
          if (($lat = $request->query->get('lat')) && ($lon = $request->query->get('lon'))) {
            $results = $this->reverse($lat, $lon, $lang);
          }
          break;
     }

    // Add Cache settings for Max-age and URL context.
    // You can use any of Drupal's contexts, tags, and time.
   /* $results['#cache'] = [
      'max-age' => 600,
      'contexts' => [
        'url',
      ],
    ];*/
    $response = new CacheableJsonResponse($results);
    $response->addCacheableDependency($request->getRequestUri());
    //$response->addCacheableDependency(CacheableMetadata::createFromRenderArray($results));
    return $response;
  }


  /**
   * @param $input
   * @param int $count
   * @param string $lang
   *
   * @return array
   */
  protected function search($input, int $count = 5, string $lang = 'en'){
    // The request we are going to do is something like
    // "https://nominatim.openstreetmap.org/search?q=West+87th+Street,NY&limit=5&format=geojson&addressdetails=1";
    $remoteUrl = 'https://nominatim.openstreetmap.org/search';

    $options['headers']=['Accept' => 'application/json', 'Accept-Language' => $lang];
    $options['query'] = [
      'q' => $input,
      'limit' => $count,
      'format' => 'geojson',
      'addressdetails' => 1
    ];

    return $this->processRequest($remoteUrl, $options);
  }

  /**
   * Takes lat/long and returns a single address from Nominatim API.
   *
   * @param $lat
   * @param $lon
   * @param string $lang
   *
   * @return array
   */
  public function reverse($lat, $lon, string $lang = 'en'){
    // The request we are going to do is something like
    // "https://nominatim.openstreetmap.org/reverse?format=geojson&lat=44.50155&lon=11.33989";
    $remoteUrl = 'https://nominatim.openstreetmap.org/reverse';

    $options['headers']=['Accept' => 'application/json', 'Accept-Language' => $lang];
    $options['query'] = [
      'lat' => $lat,
      'lon' => $lon,
      'format' => 'geojson',
      'addressdetails' => 1
    ];

    return $this->processRequest($remoteUrl, $options);
  }

  /**
   * Sends given request with options, returns results, and adds errors to messenger service.
   *
   * @param $remoteUrl
   * @param $options
   *
   * @return array|
   */
  protected function processRequest($remoteUrl, $options) {
    // Add an artificial delay of 1 second to aid in
    // https://operations.osmfoundation.org/policies/nominatim/
    sleep(1);
    $body = $this->getRemoteJsonData($remoteUrl, $options);

    $results = [];
    $jsondata = json_decode($body, TRUE);
    $json_error = json_last_error();
    if ($json_error == JSON_ERROR_NONE) {
      // nominatim will always return at least
      // {"type":"FeatureCollection","licence":"Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright","features":[]}
      if (isset($jsondata['type']) && $jsondata['type'] == 'FeatureCollection') {
        if (isset($jsondata['features'])) {
          foreach ($jsondata['features'] as $feature) {
            if ($feature["type"] == "Feature") {
              $results[] = [
                'label' => isset($feature["properties"]["display_name"]) ? $feature["properties"]["display_name"] : $this->t('unknown name for place'),
                'value' => $feature,
              ];
            }
          }
        }
      }
      else {
        // Means our call was correct, but nominatim failed. Could be temporary?
        $results[] = [
          'value' => NULL,
          'label' => 'Sorry https://nominatim.openstreetmap.org responsed with an error. Please Try again'
        ];
      }
      return  $results;
    }
    $this->messenger()->addError(
      $this->t('Looks like data fetched from @url with query options: @options is not in JSON format.<br> JSON says: @jsonerror <br>Please check your URL!',
        [
          '@url' => $remoteUrl,
          '@jsonerror' => $json_error,
          '@options' => http_build_query($options['query']),
        ]
      )
    );
    return [];
  }

  /**
   * @param $remoteUrl
   * @param $options
   *
   * @return array|string
   */
  protected function getRemoteJsonData($remoteUrl, $options) {
    // This is expensive, reason why we process and store in cache
    if (empty($remoteUrl)){
      // No need to alarm. all good. If not URL just return.
      return [];
    }
    if (!UrlHelper::isValid($remoteUrl, $absolute = TRUE)) {
      $this->messenger()->addError(
        $this->t('We can not fetch Data from @remoteUrl with query @options, check your URL',
          [
            '@remoteUrl' =>  $remoteUrl,
            '@options' => http_build_query($options['query']),
          ]
        )
      );
      return [];
    }


    try {
      $request = $this->httpClient->get($remoteUrl, $options);
    }
    catch(ClientException $exception) {
      $responseMessage = $exception->getMessage();
      $this->messenger()->addError(
        $this->t('We tried to contact @url with query @options but we could not. <br> The WEB says: @response. <br> Check that URL!',
          [
            '@url' => $remoteUrl,
            '@response' => $responseMessage,
            '@options' => http_build_query($options['query']),
          ]
        )
      );
      return [];
    }
    catch (ServerException $exception) {
      $responseMessage = $exception->getMessage();
      $this->getLogger('webform_strawberryfield')->error('Server Exception: We tried to contact the Nominatim @url with query @options but we could not. <br> The Remote server says: @response. <br> Check your query',
          [
            '@url' => $remoteUrl,
            '@response' => $responseMessage,
            '@options' => http_build_query($options['query']),
          ]
      );
      return [];
    }
    $body = $request->getBody()->getContents();
    return $body;
  }

}
