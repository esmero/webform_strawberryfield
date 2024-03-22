<?php

namespace Drupal\webform_strawberryfield\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\UseCacheBackendTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Defines a route controller for Authority autocomplete form elements.
 *
 * @see webform_strawberryfield.routing.yml:8
 */
class AuthAutocompleteController extends ControllerBase implements ContainerInjectionInterface {

  use UseCacheBackendTrait;

  /**
   * Mark a 401 so we can cache the fact that a certain URL will simply fail.
   *
   * @var bool
   */
  public $notAllowed = FALSE;

  /**
   * Max time to live the Results cache
   */
  const MAX_CACHE_AGE = 604800;

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
   * The Time Service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;


  /**
   * The Current User.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * AuthAutocompleteController constructor.
   *
   * @param \GuzzleHttp\Client $httpClient
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   */
  public function __construct(Client $httpClient, TimeInterface $time, AccountInterface $current_user, CacheBackendInterface $cacheBackend, ConfigFactoryInterface $configFactory) {
    $this->httpClient = $httpClient;
    $this->time = $time;
    $this->currentUser = $current_user;
    $this->cacheBackend = $cacheBackend;
    $this->configFactory = $configFactory;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return \Drupal\Core\Controller\ControllerBase|\Drupal\webform_strawberryfield\Controller\AuthAutocompleteController
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('cache.data'),
      $container->get('config.factory')
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
  public function handleAutocomplete(Request $request, $auth_type, $vocab = 'subjects', $rdftype = NULL, $count = 10) {
    $results = [];


    //@TODO pass count to the actual fetchers
    //@TODO maybe refactor into plugins so others can write any other reconciliators
    //@TODO if so, we can query the plugins and show in the webform builder options
    // Get the typed string from the URL, if it exists.

    $apikey = Settings::get('webform_strawberryfield.europeana_entity_apikey');
    $input = $request->query->get('q');
    $csrf_token = $request->headers->get('X-CSRF-Token');
    $is_internal = FALSE;

    if (is_string($csrf_token)) {
      $request_base = $request->getSchemeAndHttpHost().':'.$request->getPort();
      $is_internal =  $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_ADDR'].':'.$_SERVER['SERVER_PORT'] == $request_base;
      if (!$is_internal) {
        $is_internal = $_SERVER['HTTP_HOST'] == $_SERVER['SERVER_NAME'];
      }
    }

    if ($input) {
      $rdftype_str = $rdftype ?? 'null';
      $apikey_hash = $apikey ?? 'null';
      $count = $count ?? 10;
      $cache_var = md5($auth_type.$input.$vocab.$rdftype_str.$count.$apikey_hash);
      $cache_id = 'webform_strawberry:auth_lod:' . $cache_var;
      $cached = $this->cacheGet($cache_id);
      if ($cached) {
        return new JsonResponse($cached->data);
      }
      if ($this->currentUser->isAnonymous() && !$is_internal) {
        sleep(1);
      }

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
        case 'mesh':
          $results = $this->mesh($input, $vocab, $rdftype);
          break;
        case 'snac':
          $results = $this->snac($input, $vocab, $rdftype);
          break;
        case 'europeana':
          if ($apikey) {
            $results = $this->europeana($input, $vocab, $apikey);
          }
          else {
            $this->messenger()->addError(
              $this->t("Can't query Europeana.Your Entity API key is not set. Please add it to your settings.php as \$settings['webform_strawberryfield.europeana_entity_apikey'] = 'yourapikey'"
              )
            );
          }
      }
    }
    // DO not cache NULL or FALSE. Those will be 401/403/500;
    if ($results && is_array($results)) {
      // Cut the results to the desired number
      // Easier than dealing with EACH API's custom return options
      // Sort by levenstein
      usort($results, fn($a, $b) => levenshtein($input, $a['label'] ?? '') <=> levenshtein($input, $b['label'] ?? ''));
      if (count($results) > $count) {
        $results = array_slice($results, 0, $count);
      }

      //setting cache for anonymous or logged in
      if (!$is_internal) {
        $this->cacheSet($cache_id, $results,
          ($this->time->getRequestTime() + static::MAX_CACHE_AGE),
          ['user:' . $this->currentUser->id()]);
      }
      else {
        // For internal calls. Where we have really no session or anything.
        $this->cacheSet($cache_id, $results, ($this->time->getRequestTime() + static::MAX_CACHE_AGE));
      }
    }
    else {
      $results = [];
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

    $input = rawurlencode($input);

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
    $input = rawurlencode($input);
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
      if (count($jsondata) > 0) {
        if (($jsondata['success'] ?? 0) == 1) {
          foreach (($jsondata['search'] ?? []) as $key => $item) {
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
   *    Can be either 'fuzzy' or 'subjects', exact for 1:1 preflabel or combined
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
        $original_search = $search_terms;
        if (count($search_terms) > 0) {
          $search_terms = strtolower(implode('* ', $search_terms));
          $search_terms = $search_terms.'*'; //adds an extra * for the last term
        }

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
            if (is_array($original_search)) {
              $original_search_string = implode(" ", $original_search);
            }
            else {
              $original_search_string = $original_search;
            }
            foreach ($jsondata['results']['bindings'] as $key => $item) {
              // We reapply original search because i had no luck with SPARQL binding the search for exact
              // So we have no T
              $term = isset($item['T']['value']) ? $item['T']['value'] : $original_search_string;
              $parent = isset($item['P']['value']) ? ' | Parent of: ' . $item['P']['value'] : '';
              $note = isset($item['Note']['value']) ? ' | (' . $item['Note']['value'] . ')' : '';
              $uri = isset($item['S']['value']) ? $item['S']['value'] : '';
              if ((strtolower(trim($term ?? '')) == strtolower($original_search_string)) ||
                str_starts_with(strtolower(trim($term ?? '')), strtolower($original_search_string))
              ) {
                array_unshift($results, [
                  'value' => $uri,
                  'label' => $term . $parent . $note,
                  'desc' => $parent . $note,
                ]);
              }
              else {
                $results[] = [
                  'value' => $uri,
                  'label' => $term . $parent . $note,
                  'desc' => $parent . $note,
                ];
              }
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
    $input = rawurlencode($input);
    $urlindex = '&query=' . $input;
    $baseurl = 'https://viaf.org/viaf/AutoSuggest?';
    $remoteUrl = $baseurl . $urlindex;

    $options['headers'] = ['Accept' => 'application/json'];
    $body = $this->getRemoteJsonData($remoteUrl, $options);

    $jsondata = [];
    $results = [];
    $jsondata = json_decode($body, TRUE) ?? [];
    $json_error = json_last_error();
    if ($json_error == JSON_ERROR_NONE) {
      if (count($jsondata) > 0) {
        if (isset($jsondata['result']) && is_array($jsondata['result']) && count($jsondata['result']) >= 1) {
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
   * @param $input
   *    The query
   * @param $vocab
   *   Europeana Entity Type requested
   *
   * @param $apikey
   *
   * @return array
   */
  protected function europeana($input, $vocab, string $apikey) {
    //@TODO make the following allowed list a constant since we use it in
    // \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformLoC
    if (!in_array($vocab, [
      'agent',
      'concept',
      'place',
      'timespan',
    ])) {
      // Drop before trying to hit non existing vocab
      $this->messenger()->addError(
        $this->t('@vocab for Europeana Entity Suggest autocomplete is not in in our allowed list.',
          [
            '@vocab' => $vocab,
          ]
        )
      );
      $results[] = [
        'value' => NULL,
        'label' => "Wrong Vocabulary {$vocab} in Europeana Entity Query",
      ];
      return $results;
    }

    $input = rawurlencode($input);

    $urlindex = "/suggest?text=" . $input . "&type=" . $vocab ."&wskey=". $apikey ;

    $baseurl = 'https://api.europeana.eu/entity';
    $remoteUrl = $baseurl . $urlindex;
    $options['headers'] = ['Accept' => 'application/ld+json'];
    $body = $this->getRemoteJsonData($remoteUrl, $options);
    $results = [];
    $jsondata = json_decode($body, TRUE);
    $json_error = json_last_error();
    if ($json_error == JSON_ERROR_NONE) {
      /*
       {
      "@context": [
         "https://www.w3.org/ns/ldp.jsonld",
         "http://www.europeana.eu/schemas/context/entity.jsonld",
       {
        "@language": "en"
       }
       ],
       "total": 10,
       "type": "ResultPage",
       "items": [
        {
        "type": "Agent"
        "id": "http://data.europeana.eu/agent/base/147466",
       "prefLabel": {
                "en": "Arturo Toscanini"
            },
        "dateOfBirth": "1867-03-25",
        "dateOfDeath": "1957-01-16",
        },
     { .. }
     ]
  }
      */
      // @NOTE: This is Entities API is 0.10.3 (December 2021)and it might chang. So review the API every 6 months
      if (isset($jsondata['total']) &&  $jsondata['total'] >= 1 && isset($jsondata['items']) && is_array($jsondata['items'])) {
        foreach ($jsondata['items'] as $key => $result) {
          $desc = NULL;
          if (($vocab == 'place') && isset($result['isPartOf']) && is_array($result['isPartOf'])) {
            foreach( $result['isPartOf'] as $partof) {
              $desc[] = reset($partof['prefLabel']);
            }
          }

          if (($vocab == 'agent') && isset($result['dateOfBirth'])) {
            $desc[] = $result['dateOfBirth'] . '/' . $result['dateOfDeath'] ?? '?';
          }

          $desc =  !empty($desc) ? ' (' . implode(', ', $desc) . ')' : NULL;
          $label = $result['prefLabel']['en'] ?? (reset($result['prefLabel']) ?? 'No Label');
          $label = empty($desc) ? $label : $label . $desc;
          $results[] = [
            'value' => $result['id'],
            'label' => $label,
            'desc' => $desc,
          ];
        }
      }
      else {
        $results[] = [
          'value' => NULL,
          'label' => "Sorry no match from Europeana {$vocab}",
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
   *    The query
   * @param $vocab
   *   The 'suggest' enabled endpoint at LoC
   *
   * @return array
   */
  protected function snac($input, $vocab, $rdftype) {
    //@TODO make the following allowed list a constant since we use it in
    // \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformLoC
    if (!in_array($vocab, [
      'Constellation',
      'rdftype',
    ])) {
      // Drop before tryin to hit non existing vocab
      $this->messenger()->addError(
        $this->t('@vocab for SNAC autocomplete is not in in our allowed list.',
          [
            '@vocab' => $vocab,
          ]
        )
      );
      $results[] = [
        'value' => NULL,
        'label' => "Wrong Vocabulary {$vocab} in SNAC Query",
      ];
      return $results;
    }


    $input = urlencode($input);


    $remoteUrl = "https://api.snaccooperative.org";
    $options = [
      'body' => json_encode([
        "command" => "search",
        "term" => $input,
        "entity_type" => $rdftype != "thing" ? $rdftype : NULL,
        "start" => 0,
        "count" => 10,
        "search_type" => "autocomplete",
      ]),
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/json'
      ]
    ];
    $body = $this->getRemoteJsonData($remoteUrl, $options, 'PUT');

    $jsondata = [];
    $results = [];
    $jsondata = json_decode($body, TRUE);
    $json_error = json_last_error();
    if ($json_error == JSON_ERROR_NONE) {
      if (!empty($jsondata['results']) &&  ($jsondata['total'] ?? 0) >= 1) {
        foreach ($jsondata['results'] as $key => $entry) {
          $nameEntry = reset($entry['nameEntries']);
          $results[] = [
            'value' => $entry['ark'] ?? $entry['entityType']['uri'],
            'label' => $nameEntry['original'],
          ];
        }
      }
      else {
        $results[] = [
          'value' => NULL,
          'label' => "Sorry no match from SNAC {$vocab}",
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
   *    The query
   * @param $vocab
   *   The 'suggest' enabled endpoint at LoC
   *
   * @return array
   */
  protected function mesh($input, $vocab, $rdftype) {

    //@TODO make the following allowed list a constant since we use it in
    // \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformMesh
    if (!in_array($vocab, [
      'descriptor',
      'term',
    ])) {
      // Drop before tryin to hit non existing vocab
      $this->messenger()->addError(
        $this->t('@vocab for MeSH autocomplete is not in in our allowed list.',
          [
            '@vocab' => $vocab,
          ]
        )
      );
      $results[] = [
        'value' => NULL,
        'label' => "Wrong Vocabulary {$vocab} in MeSH API Query",
      ];
      return $results;
    }

    // Here $rdftype acts as match
    if (!in_array($rdftype, [
      'startswith',
      'contains',
      'exact',
    ])) {
      // Drop before tryin to hit non existing vocab
      $this->messenger()->addError(
        $this->t('@rdftype Match type for MeSH autocomplete is not valid. It may be "exact","startswith" or "contains"',
          [
            '@rdftype' => $rdftype,
          ]
        )
      );
      $results[] = [
        'value' => NULL,
        'label' => "Wrong Match type for {$vocab} in MeSH API Query",
      ];
      return $results;
    }

    $input_encoded = rawurlencode($input);
    $urlindex = "/mesh/lookup/{$vocab}?label=" . $input_encoded .'&limit=10&match=' . $rdftype;
    $baseurl = 'https://id.nlm.nih.gov';
    $remoteUrl = $baseurl . $urlindex;
    $options['headers'] = ['Accept' => 'application/json'];
    $body = $this->getRemoteJsonData($remoteUrl, $options);
    $results = [];
    $jsondata = json_decode($body, TRUE);
    $json_error = json_last_error();
    if ($json_error == JSON_ERROR_NONE) {
      if (count($jsondata) > 0) {
        foreach ($jsondata as $entry) {
          if (strtolower(trim($entry['label'] ?? '')) == strtolower($input)) {
            array_unshift($results, [
              'value' => $entry['resource'],
              'label' => $entry['label'],
            ]);
          }
          else {
            $results[] = [
              'value' => $entry['resource'],
              'label' => $entry['label'],
            ];
          }
        }
      }
      else {
        $results[] = [
          'value' => NULL,
          'label' => "Sorry no match from MeSH for {$vocab}",
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
   * @return string
   *   A string that may be JSON (hopefully)
   */
  protected function getRemoteJsonData($remoteUrl, $options, $method = 'GET') {
    // This is expensive, reason why we process and store in cache
    if (empty($remoteUrl)) {
      // No need to alarm. all good. If not URL just return.
      return NULL;
    }
    if (!UrlHelper::isValid($remoteUrl, $absolute = TRUE)) {
      $this->messenger()->addError(
        $this->t('We can not fetch Data from @$remoteUrl, check your URL',
          ['@$remoteUrl' => $remoteUrl]
        )
      );
      return NULL;
    }
    try {
      if ($method == 'GET') {
        $request = $this->httpClient->get($remoteUrl, $options);
      }
      elseif ($method == 'POST') {
        $request = $this->httpClient->post($remoteUrl, $options);
      }
      elseif ($method == 'PUT') {
        $request = $this->httpClient->put($remoteUrl, $options);
      }
      else {
        return NULL;
      }
      // Do not cache if things go bad.
      if ($request->getStatusCode() == '401') {
        $this->setNotAllowed(TRUE);
        $this->useCaches = FALSE;
        return NULL;
        // Means we got a server Access Denied, we reply to whoever made the call.
      }
      if ($request->getStatusCode() == '404') {
        $this->useCaches = FALSE;
        return NULL;
      }
      if ($request->getStatusCode() == '500') {
        $this->useCaches = FALSE;
        return NULL;
      }
    }
    catch (ClientException $exception) {
      $this->useCaches = FALSE;
      $responseMessage = $exception->getMessage();
      $this->messenger()->addError(
        $this->t('We tried to contact @url but we could not. <br> The WEB says: @response. <br> Check that URL!',
          [
            '@url' => $remoteUrl,
            '@response' => $responseMessage,
          ]
        )
      );
      return NULL;
    }
    catch (ServerException $exception) {
      $this->useCaches = FALSE;
      $responseMessage = $exception->getMessage();
      $this->getLogger('webform_strawberryfield')
        ->error('We tried to contact @url but we could not. <br> The Remote server says: @response. <br> Check your query',
          [
            '@url' => $remoteUrl,
            '@response' => $responseMessage,
          ]
        );
      return NULL;
    }

    $body = $request->getBody()->getContents();
    return $body;
  }

  /**
   * @return bool
   */
  public function isNotAllowed(): bool {
    return $this->notAllowed;
  }

  /**
   * @param bool $notAllowed
   */
  public function setNotAllowed(bool $notAllowed): void {
    $this->notAllowed = $notAllowed;
  }

}
