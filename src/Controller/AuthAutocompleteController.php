<?php

namespace Drupal\webform_strawberryfield\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Drupal\Component\Utility\UrlHelper;

/**
 * Defines a route controller for Authority autocomplete form elements.
 *
 * @see webform_strawberryfield.routing.yml:8
 */
class AuthAutocompleteController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;


  /**
   * AuthAutocompleteController constructor.
   *
   * @param \GuzzleHttp\Client $httpClient
   */
  public function __construct(Client $httpClient) {
    $this->httpClient = $httpClient;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Controller\ControllerBase|\Drupal\webform_strawberryfield\Controller\AuthAutocompleteController
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')

      );
  }

  /**
   * Handler for autocomplete request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param $auth_type
   * @param $count
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function handleAutocomplete(Request $request, $auth_type, $count) {
    $results = [];
    //@TODO pass count to the actual fetchers
    //@TODO maybe refactor into plugins so others can write any other reconciliators
    //@TODO if so, we can query the plugins and show in the webform builder options
    // Get the typed string from the URL, if it exists.
    if ($input = $request->query->get('q')) {
      switch($auth_type) {
        case 'loc':  $results = $this->loc($input);
        break;
        case 'wikidata': $results = $this->wikidata($input);
        break;
      }


    }

    return new JsonResponse($results);
  }

  /**
   * @param $input
   *
   * @return array
   */
  protected function loc($input){
    $input = urlencode($input);
    $urlindex =  '/authorities/subjects/suggest/?q=' . $input;
    $baseurl = 'https://id.loc.gov';
    $remoteUrl = $baseurl.$urlindex;
    $options['headers']=['Accept' => 'application/json'];
    $body = $this->getRemoteJsonData($remoteUrl, $options);

    $jsondata = [];
    $results = [];
    $jsondata = json_decode($body, TRUE);
    $json_error = json_last_error();
    if ($json_error == JSON_ERROR_NONE) {
      //LoC will always return at least one, the query string
      if (count($jsondata) > 1) {
        foreach ($jsondata[1] as $key => $label) {
          $results[] = [
            'value' => $jsondata[3][$key],
            'label' => $label
          ];
        }
      }
      else {
          $results[] = [
            'value' => NULL,
            'label' => 'Sorry no Match from LoC Subject Headings'
          ];
        }
      return  $results;
    }
    $this->messenger->addError(
      $this->t('Looks like data fetched from @url is not in JSON format.<br> JSON says: @$jsonerror <br>Please check your URL!',
        [
          '@url' => $remoteUrl,
          '@$jsonerror' => $json_error
        ]
      )
    );
    return [];
  }


  /**
   * @param $input
   *
   * @return array
   */
  protected function wikidata($input){
    $input = urlencode($input);
    $urlindex =  '&language=en&format=json&search=' . $input;
    $baseurl = 'https://www.wikidata.org/w/api.php?action=wbsearchentities';
    $remoteUrl = $baseurl.$urlindex;

    $options['headers']=['Accept' => 'application/json'];
    $body = $this->getRemoteJsonData($remoteUrl, $options);

    $jsondata = [];
    $results = [];
    $jsondata = json_decode($body, TRUE);
    $json_error = json_last_error();
    if ($json_error == JSON_ERROR_NONE) {
      //WIKIdata will give is an success key will always return at least one, the query string
      if (count($jsondata) > 1) {
        if ($jsondata['success'] == 1) {
          foreach ($jsondata['search'] as $key => $item) {
            $desc = (isset($item['description'])) ? '('.$item['description'].')':'';
            $results[] = [
              'value' => $item['concepturi'],
              'label' => $item['label'].' '.$desc,
              'desc' => $desc,
            ];
          }
        }
      }
      else {
        $results[] = [
          'value' => NULL,
          'label' => 'Sorry no Match from Wikidata Subject Headings'
        ];
      }
      return  $results;
    }
    $this->messenger->addError(
      $this->t('Looks like data fetched from @url is not in JSON format.<br> JSON says: @$jsonerror <br>Please check your URL!',
        [
          '@url' => $remoteUrl,
          '@$jsonerror' => $json_error
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
      $this->messenger->addError(
        $this->t('We can not fetch Data from @$remoteUrl, check your URL',
          ['@$remoteUrl' =>  $remoteUrl]
        )
      );
      return [];
    }


    try {
      $request = $this->httpClient->get($remoteUrl, $options);
    }
    catch(ClientException $exception) {
      $responseMessage = $exception->getMessage();
      $this->messenger->addError(
        $this->t('We tried to contact @url but we could not. <br> The WEB says: @response. <br> Check that URL!',
          [
            '@url' => $remoteUrl,
            '@response' => $responseMessage
          ]
        )
      );
      return [];
    }
    $body = $request->getBody()->getContents();
    return $body;
  }


}