<?php

namespace Drupal\webform_strawberryfield\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;

/**
 * Defines a route controller for Authority autocomplete form elements.
 *
 * @see webform_strawberryfield.routing.yml:8
 */
class AuthAutocompleteController extends ControllerBase implements ContainerInjectionInterface {


  /**
   * English Stop words.
   */
  const STOPWORDS_EN = [
    "a",
    "an",
    "and",
    "are",
    "as",
    "at",
    "be",
    "but",
    "by",
    "for",
    "if",
    "in",
    "into",
    "is",
    "it",
    "no",
    "not",
    "of",
    "on",
    "or",
    "such",
    "that",
    "the",
    "their",
    "then",
    "there",
    "these",
    "they",
    "this",
    "to",
    "was",
    "will",
    "with",
  ];

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
   * @param $vocab
   * @param $rdftype
   * @param $count
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function handleAutocomplete(Request $request, $auth_type, $vocab = 'subjects', $rdftype = NULL, $count) {
    $results = [];
    //@TODO pass count to the actual fetchers
    //@TODO maybe refactor into plugins so others can write any other reconciliators
    //@TODO if so, we can query the plugins and show in the webform builder options
    // Get the typed string from the URL, if it exists.
    if ($input = $request->query->get('q')) {
      switch ($auth_type) {
        case 'loc':
          $results = $this->loc($input, $vocab, $rdftype);
          break;
        case 'wikidata':
          $results = $this->wikidata($input);
          break;
        case 'aat':
          // @TODO this will be deprecated in 1.1. legacy so old access can be kept
          $results = $this->getty($input, 'aat', $rdftype);
          break;
        case 'getty':
          $results = $this->getty($input, $vocab, $rdftype);
          break;
        case 'viaf':
          $results = $this->viaf($input);
          break;
      }
    }

    return new JsonResponse($results);
  }

  /**
   * @param $input
   *    The query
   * @param $vocab
   *   The 'suggest' enabled endpoint at LoC
   *
   * @return array
   */
  protected function loc($input, $vocab, $rdftype) {
    //@TODO make the following allowed list a constant since we use it in
    // \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformLoC
    if (!in_array($vocab, [
      'relators',
      'subjects',
      'names',
      'genreForms',
      'graphicMaterials',
      'geographicAreas',
      'rdftype',
    ])) {
      // Drop before tryin to hit non existing vocab
      $this->messenger()->addError(
        $this->t('@vocab for LoC autocomplete is not in in our allowed list.',
          [
            '@vocab' => $vocab,
          ]
        )
      );
      $results[] = [
        'value' => NULL,
        'label' => "Wrong Vocabulary {$vocab} in LoC Query",
      ];
      return $results;
    }
    // So happens that some are Vocabularies and some Authorities
    // So So we need to subclassify
    $endpoint = [
      'relators' => 'vocabulary',
      'subjects' => 'authorities',
      'names' => 'authorities',
      'genreForms' => 'authorities',
      'graphicMaterials' => 'vocabulary',
      'geographicAreas' => 'vocabulary',
      'rdftype' => '',
    ];
    $path = $endpoint[$vocab];

    $input = urlencode($input);

    if ($vocab == 'rdftype') {
      $urlindex = "/suggest/?q=" . $input . "&rdftype=" . $rdftype;
    }
    else {
      $urlindex = "/{$path}/{$vocab}/suggest/?q=" . $input;
    }

    $baseurl = 'https://id.loc.gov';
    $remoteUrl = $baseurl . $urlindex;
    $options['headers'] = ['Accept' => 'application/json'];
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
            'label' => $label,
          ];
        }
      }
      else {
        $results[] = [
          'value' => NULL,
          'label' => "Sorry no match from LoC {$vocab} {$path}",
        ];
      }
      return $results;
    }
    $this->messenger()->addError(
      $this->t('Looks like data fetched from @url is not in JSON format.<br> JSON says: @jsonerror <br>Please check your URL!',
        [
          '@url' => $remoteUrl,
          '@jsonerror' => $json_error,
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
  protected function wikidata($input) {
    $input = urlencode($input);
    $urlindex = '&language=en&format=json&search=' . $input;
    $baseurl = 'https://www.wikidata.org/w/api.php?action=wbsearchentities';
    $remoteUrl = $baseurl . $urlindex;

    $options['headers'] = ['Accept' => 'application/json'];
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
            $desc = (isset($item['description'])) ? '(' . $item['description'] . ')' : NULL;
            $results[] = [
              $label = empty($desc) ? $item['label'] : $item['label'] . ' ' . $desc,
              'value' => $item['concepturi'],
              'label' => $label,
              'desc' => $desc,
            ];
          }
        }
      }
      else {
        $results[] = [
          'value' => NULL,
          'label' => 'Sorry no Match from Wikidata Subject Headings',
        ];
      }
      return $results;
    }
    $this->messenger()->addError(
      $this->t('Looks like data fetched from @url is not in JSON format.<br> JSON says: @jsonerror <br>Please check your URL!',
        [
          '@url' => $remoteUrl,
          '@jsonerror' => $json_error,
        ]
      )
    );
    return [];
  }


  /**
   * @param $input
   * @param string $vocab
   *
   * @param string $mode
   *    Can be either 'fuzzy' or 'subjects', exact for 1:1 preflalbe or combined
   *    subjects will be deprecated in 1.1
   * @return array
   */
  protected function getty($input, $vocab = 'aat', $mode = 'fuzzy') {

    if (!in_array($mode, ['fuzzy', 'subjects', 'exact', 'terms'])) {
      // Drop before trying to hit non existing vocab
      $this->messenger()->addError(
        $this->t('@mode mode for @vocab Getty autocomplete is not in in our allowed list.',
          [
            '@mode' => $mode,
            '@vocab' => $vocab,
          ]
        )
      );
      $results[] = [
        'value' => NULL,
        'label' => "Wrong Query Mode {$mode} in Getty {$vocab} Query",
        'desc' => NULL,
      ];
      return $results;
    }


    $input = trim($input);
    $queries = [];
    $results = [];
    // Limit the size here: max 64 chars.
    if (strlen($input) > 64) {
      return $results[] = [
        'value' => NULL,
        'label' => "Sorry query is too long! Try with less characters",
        'desc' => NULL,
      ];
    }
    //split in pieces
    $input_parts = explode(' ', $input);
    $clean_input = array_diff($input_parts, $this::STOPWORDS_EN);
    if (!empty($clean_input)) {
      // @see http://vocab.getty.edu/queries#Case-insensitive_Full_Text_Search_Query
      // Build the SPARQL query
      if (in_array($mode, ["subjects","fuzzy"])) {
        $toremove = ['-', '.', '(', ')', '|' . '+', '$', '#', '@', '*'];
        $search = str_replace($toremove, ' ', $clean_input);
        $search = array_map('trim', $search);
        $search = array_filter($search);
        $original_search = $search;
        $search = array_map(function ($value) {
          return strtolower($value) . '*';
        }, $search);
        $search = implode(' AND ', $search);
        // Note to myself: removing order by asc(lcase(str(?T)))
        $query_fuzzy = <<<SPARQL
      SELECT ?S ?T ?P ?Note {
        ?S a skos:Concept; luc:text !searchterm; skos:inScheme <http://vocab.getty.edu/!vocab/> ;
           gvp:prefLabelGVP [xl:literalForm ?T].
        optional {?S gvp:parentStringAbbrev ?P}
        optional {?S skos:scopeNote [dct:language gvp_lang:en; rdf:value ?Note]}
        }
        LIMIT !number
SPARQL;
        $search = '"' . $search . '"';
        $query_fuzzy = preg_replace('!\s+!', ' ', $query_fuzzy);
        // use Drupal\Component\Render\FormattableMarkup; has no pass through option
        // Anymore, so use native PHP.
        // If we have more than one word we will use extra single quote to make
        // a closer to exact match.
        $queries[] = strtr(trim($query_fuzzy), [
          '!searchterm' => $search,
          '!vocab' => $vocab,
          '!number' => 10
        ]);
      }
      elseif ($mode == "exact") {
        $search_exact = array_map('trim', $clean_input);
        $search_exact = strtolower(implode(' ', $search_exact));
        $original_search = $search_exact;
        $query_exact = <<<SPARQL
        select distinct ?S ?T ?P ?Note {
          ?S skos:inScheme <http://vocab.getty.edu/!vocab/> ;
          gvp:prefLabelGVP/xl:literalForm !searchterm@en .
          optional {?S gvp:parentStringAbbrev ?P}
          optional {?S skos:scopeNote [dct:language gvp_lang:en; rdf:value ?Note]}
        }
        LIMIT !number
SPARQL;
        $search_exact = '"' . $search_exact . '"';
        $query_exact = preg_replace('!\s+!', ' ', $query_exact);
        $queries[] = strtr(trim($query_exact), [
          '!searchterm' => $search_exact,
          '!vocab' => $vocab,
          '!number' => 1
        ]);
      }
      elseif ($mode == "terms") {
        $toremove = ['-', '.', '(', ')', '|' . '+', '$', '#', '@', '*'];
        $search_terms = str_replace($toremove, ' ', $clean_input);
        $search_terms = array_map('trim', $search_terms);
        $search_terms = array_filter($search_terms);
        if (count($search_terms) > 0) {
          $search_terms = strtolower(implode('* ', $search_terms));
          $search_terms = $search_terms.'*'; //adds an extra * for the last term
        }
        $original_search = $search_terms;
        $query_terms = <<<SPARQL
        select distinct ?S ?T ?P ?Note {
          ?S a gvp:Concept; luc:term !searchterm; skos:inScheme <http://vocab.getty.edu/!vocab/>.
          ?S gvp:prefLabelGVP [xl:literalForm ?T]
          optional {?S gvp:parentStringAbbrev ?P}
          optional {?S skos:scopeNote [dct:language gvp_lang:en; rdf:value ?Note]}
        }
        LIMIT !number
SPARQL;

        /*
         *   ?S a gvp:Concept; luc:term "actors* (performing artists)*"; skos:inScheme aat:.
          ?S gvp:prefLabelGVP [xl:literalForm ?T]
          optional {?S gvp:parentStringAbbrev ?P}
          optional {?S skos:scopeNote [dct:language gvp_lang:en; rdf:value ?Note]}
         *
         */
        $search_terms = '"' . $search_terms . '"';
        $query_terms= preg_replace('!\s+!', ' ', $query_terms);
        // use Drupal\Component\Render\FormattableMarkup; has no pass through option
        // Anymore, so use native PHP.
        // If we have more than one word we will use extra single quote to make
        // a closer to exact match.
        $queries[] = strtr(trim($query_terms), [
          '!searchterm' => $search_terms,
          '!vocab' => $vocab,
          '!number' => 10
        ]);
      }


      $bodies = [];
      $baseurl = 'http://vocab.getty.edu/sparql.json';
      // I leave this as an array in case we want to combine modes in the future.
      foreach($queries as $query) {
        error_log($query);
        $options = ['query' => ['query' => $query]];
        $url = Url::fromUri($baseurl, $options);
        $remoteUrl = $url->toString() . '&_implicit=false&implicit=true&_equivalent=false&_form=%2Fsparql';
        $options['headers'] = ['Accept' => 'application/sparql-results+json'];
        $bodies[] = $this->getRemoteJsonData($remoteUrl, $options);
      }
      // This is how a result here looks like
      /*
     "results" : {
     "bindings" : [ {
      "S" : {
        "type" : "uri",
        "value" : "http://vocab.getty.edu/aat/300185403"
      },
      "T" : {
        "xml:lang" : "en",
        "type" : "literal",
        "value" : "<conditions and effects for architecture>"
      },
      "P" : {
        "type" : "literal",
        "value" : "<conditions and effects by specific type>, Conditions and Effects (hierarchy name), Physical Attributes Facet"
      },
      "Note" : {
        "xml:lang" : "en",
        "type" : "literal",
        "value" : "For additional terminology, see the more general \"status of property.\""
      }
    }
       */



      $jsonfail = FALSE;
      foreach($bodies as $body) {
        $jsondata = json_decode($body, TRUE);
        $json_error = json_last_error();
        if ($json_error == JSON_ERROR_NONE) {
          if (isset($jsondata['results']) && count($jsondata['results']['bindings']) > 0) {
            foreach ($jsondata['results']['bindings'] as $key => $item) {
              // We reapply original search because i had no luck with SPARQL binding the search for exact
              // So we have no T
              $term = isset($item['T']['value']) ? $item['T']['value'] : $original_search;
              $parent = isset($item['P']['value']) ? ' | Parent of: ' . $item['P']['value'] : '';
              $note = isset($item['Note']['value']) ? ' | (' . $item['Note']['value'] . ')' : '';
              $uri = isset($item['S']['value']) ? $item['S']['value'] : '';
              $results[] = [
                'value' => $uri,
                'label' => $term . $parent . $note,
                'desc' => $parent . $note,
              ];
            }
          }
        }
        else {
          $jsonfail = TRUE;
        }
      }
      if (empty($results)) {
        $results[] = [
          'value' => NULL,
          'label' => 'Sorry no Match from Getty ' . $vocab . ' Vocabulary',
          'desc' => NULL,
        ];
      }
      if ($jsonfail) {
        $this->messenger()->addError(
          $this->t('Looks like data fetched from @url is not in JSON format.<br> JSON says: @jsonerror <br>Please check your URL!',
            [
              '@url' => $remoteUrl,
              '@jsonerror' => $json_error,
            ]
          )
        );
      }
    }
    return $results;
  }

  /**
   * @param $input
   *
   * @return array
   */
  protected function viaf($input) {
    $input = urlencode($input);
    $urlindex = '&query=' . $input;
    $baseurl = 'https://viaf.org/viaf/AutoSuggest?';
    $remoteUrl = $baseurl . $urlindex;

    $options['headers'] = ['Accept' => 'application/json'];
    $body = $this->getRemoteJsonData($remoteUrl, $options);

    $jsondata = [];
    $results = [];
    $jsondata = json_decode($body, TRUE);
    $json_error = json_last_error();
    if ($json_error == JSON_ERROR_NONE) {
      //WIKIdata will give is an success key will always return at least one, the query string
      if (count($jsondata) > 1) {
        if (count($jsondata['result']) >= 1) {
          foreach ($jsondata['result'] as $key => $item) {
            $desc = (isset($item['nametype'])) ? '(' . $item['nametype'] . ')' : NULL;
            $results[] = [
              $label = empty($desc) ? $item['displayForm'] : $item['displayForm'] . ' ' . $desc,
              'value' => "https://viaf.org/viaf/" . $item['viafid'],
              'label' => $label,
              'desc' => $desc,
            ];
          }
        }
      }
      else {
        $results[] = [
          'value' => NULL,
          'label' => 'Sorry no Match from VIAF',
        ];
      }
      return $results;
    }
    $this->messenger()->addError(
      $this->t('Looks like data fetched from @url is not in JSON format.<br> JSON says: @jsonerror <br>Please check your URL!',
        [
          '@url' => $remoteUrl,
          '@jsonerror' => $json_error,
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
    if (empty($remoteUrl)) {
      // No need to alarm. all good. If not URL just return.
      return [];
    }
    if (!UrlHelper::isValid($remoteUrl, $absolute = TRUE)) {
      $this->messenger()->addError(
        $this->t('We can not fetch Data from @$remoteUrl, check your URL',
          ['@$remoteUrl' => $remoteUrl]
        )
      );
      return [];
    }


    try {
      $request = $this->httpClient->get($remoteUrl, $options);
    } catch (ClientException $exception) {
      $responseMessage = $exception->getMessage();
      $this->messenger()->addError(
        $this->t('We tried to contact @url but we could not. <br> The WEB says: @response. <br> Check that URL!',
          [
            '@url' => $remoteUrl,
            '@response' => $responseMessage,
          ]
        )
      );
      return [];
    } catch (ServerException $exception) {
      $responseMessage = $exception->getMessage();
      $this->loggerFactory->get('webform_strawberryfield')
        ->error('We tried to contact @url but we could not. <br> The Remote server says: @response. <br> Check your query',
          [
            '@url' => $remoteUrl,
            '@response' => $responseMessage,
          ]
        );
      return [];
    }
    $body = $request->getBody()->getContents();
    return $body;
  }

}
