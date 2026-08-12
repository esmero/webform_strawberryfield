<?php

namespace Drupal\Tests\webform_strawberryfield\Unit\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform_strawberryfield\Controller\AuthAutocompleteController;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the LoD autocomplete handlers behave when an Authority is down.
 */
#[Group('webform_strawberryfield')]
#[CoversClass(AuthAutocompleteController::class)]
class AuthAutocompleteControllerTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')
      ->willReturn($this->createMock(LoggerChannelInterface::class));

    // Getty builds its SPARQL endpoint with Url::fromUri().
    $url_assembler = $this->createMock(UnroutedUrlAssemblerInterface::class);
    $url_assembler->method('assemble')
      ->willReturnCallback(fn($uri) => $uri);

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('logger.factory', $logger_factory);
    $container->set('unrouted_url_assembler', $url_assembler);
    $container->set('state', $this->createMock(StateInterface::class));
    \Drupal::setContainer($container);
  }

  /**
   * Builds a controller whose HTTP client replies with the given responses.
   *
   * @param \GuzzleHttp\Psr7\Response ...$queue
   *   What the remote Authority answers, in order.
   *
   * @return \Drupal\webform_strawberryfield\Controller\AuthAutocompleteController
   *   The controller under test.
   */
  protected function controllerReplyingWith(Response ...$queue): AuthAutocompleteController {
    return new AuthAutocompleteController(
      new Client(['handler' => HandlerStack::create(new MockHandler($queue))]),
      $this->createMock(TimeInterface::class),
      $this->createMock(AccountInterface::class),
      $this->createMock(CacheBackendInterface::class),
      $this->createMock(ConfigFactoryInterface::class)
    );
  }

  /**
   * Invokes one of the controller's protected Authority handlers.
   */
  protected function invoke(AuthAutocompleteController $controller, string $method, array $args) {
    return (new \ReflectionMethod($controller, $method))->invoke($controller, ...$args);
  }

  /**
   * Runs a callable, returning every PHP deprecation the module's code raised.
   *
   * @return array
   *   A [result, deprecations] pair.
   */
  protected function collectDeprecations(callable $callable): array {
    $module_src = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    $deprecations = [];
    set_error_handler(
      function ($errno, $errstr, $errfile) use (&$deprecations, $module_src) {
        if ($errno === E_DEPRECATED && str_starts_with((string) $errfile, $module_src)) {
          $deprecations[] = $errstr;
        }
        return TRUE;
      },
      E_ALL
    );
    try {
      $result = $callable();
    }
    finally {
      restore_error_handler();
    }
    return [$result, $deprecations];
  }

  /**
   * The Authority handlers and the arguments they take.
   *
   * @return array
   *   Test cases keyed by Authority name.
   */
  public static function authorityHandlerProvider(): array {
    return [
      'LoC' => ['loc', ['Doe, John', 'names', NULL]],
      'Wikidata' => ['wikidata', ['Doe, John']],
      'VIAF' => ['viaf', ['Doe, John']],
      'Europeana' => ['europeana', ['Doe, John', 'agent', 'anapikey']],
      'SNAC' => ['snac', ['Doe, John', 'person', NULL]],
      'MeSH' => ['mesh', ['Anemia', 'descriptor', NULL]],
      'Getty' => ['getty', ['Anemia', 'aat', 'fuzzy']],
    ];
  }

  /**
   * An Authority that times out must not raise a PHP deprecation.
   *
   * ::getRemoteJsonData() returns NULL when the remote endpoint answers with a
   * 5xx (which is how id.loc.gov presents while it is timing out) and feeding
   * that NULL to json_decode() is deprecated as of PHP 8.1.
   */
  #[DataProvider('authorityHandlerProvider')]
  public function testHandlerDoesNotDeprecateWhenAuthorityIsDown(string $method, array $args): void {
    // Two responses queued: Getty issues more than one request per lookup.
    $controller = $this->controllerReplyingWith(
      new Response(504, [], 'Gateway Timeout'),
      new Response(504, [], 'Gateway Timeout')
    );

    [$results, $deprecations] = $this->collectDeprecations(
      fn() => $this->invoke($controller, $method, $args)
    );

    $this->assertSame([], $deprecations, 'No PHP deprecation is raised when the Authority can not be reached.');
    $this->assertIsArray($results, 'The handler still answers the autocomplete with an array.');
  }

  /**
   * The same must hold when the Authority answers with an empty body.
   */
  #[DataProvider('authorityHandlerProvider')]
  public function testHandlerDoesNotDeprecateOnEmptyBody(string $method, array $args): void {
    $controller = $this->controllerReplyingWith(
      new Response(200, [], ''),
      new Response(200, [], '')
    );

    [$results, $deprecations] = $this->collectDeprecations(
      fn() => $this->invoke($controller, $method, $args)
    );

    $this->assertSame([], $deprecations);
    $this->assertIsArray($results);
  }

  /**
   * A working LoC lookup keeps returning its suggestions untouched.
   *
   * This is the control: the fix must not move the success path.
   */
  public function testLocStillReturnsSuggestions(): void {
    // The shape id.loc.gov/authorities/names/suggest/ answers with.
    $payload = json_encode([
      'Doe',
      ['Doe, John', 'Doe, Jane'],
      ['', ''],
      ['https://id.loc.gov/authorities/names/n1', 'https://id.loc.gov/authorities/names/n2'],
    ]);
    $controller = $this->controllerReplyingWith(new Response(200, [], $payload));

    [$results, $deprecations] = $this->collectDeprecations(
      fn() => $this->invoke($controller, 'loc', ['Doe', 'names', NULL])
    );

    $this->assertSame([], $deprecations);
    $this->assertSame([
      [
        'value' => 'https://id.loc.gov/authorities/names/n1',
        'label' => 'Doe, John',
      ],
      [
        'value' => 'https://id.loc.gov/authorities/names/n2',
        'label' => 'Doe, Jane',
      ],
    ], $results);
  }

  /**
   * An Authority answering with a JSON scalar must not break count().
   *
   * Several handlers count() what they decoded, and json_decode('null') or
   * json_decode('"a string"') is not countable.
   */
  public function testJsonScalarDoesNotBreakTheHandler(): void {
    $controller = $this->controllerReplyingWith(new Response(200, [], 'null'));

    [$results, $deprecations] = $this->collectDeprecations(
      fn() => $this->invoke($controller, 'loc', ['Doe', 'names', NULL])
    );

    $this->assertSame([], $deprecations);
    $this->assertIsArray($results);
  }

  /**
   * A malformed body is still reported to the user as malformed JSON.
   */
  public function testMalformedJsonIsStillReported(): void {
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->once())
      ->method('addError')
      ->with($this->callback(function ($message) {
        return str_contains((string) $message, 'is not in JSON format');
      }));
    \Drupal::getContainer()->set('messenger', $messenger);

    $controller = $this->controllerReplyingWith(new Response(200, [], '<html>not json</html>'));

    $this->assertSame([], $this->invoke($controller, 'loc', ['Doe', 'names', NULL]));
  }

  /**
   * An unreachable Authority is reported as such, not as malformed JSON.
   */
  public function testUnreachableAuthorityIsNotReportedAsMalformedJson(): void {
    $messages = [];
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->method('addError')
      ->willReturnCallback(function ($message) use (&$messages) {
        $messages[] = (string) $message;
      });
    \Drupal::getContainer()->set('messenger', $messenger);

    $controller = $this->controllerReplyingWith(new Response(504, [], 'Gateway Timeout'));
    $this->invoke($controller, 'loc', ['Doe', 'names', NULL]);

    $this->assertNotEmpty($messages, 'The user is told something went wrong.');
    foreach ($messages as $message) {
      $this->assertStringNotContainsString('is not in JSON format', $message);
    }
  }

  /**
   * The ORCID token request also survives an unreachable orcid.org.
   */
  public function testOrcidAuthDoesNotDeprecateWhenOrcidIsDown(): void {
    $controller = $this->controllerReplyingWith(new Response(504, [], 'Gateway Timeout'));

    [$token, $deprecations] = $this->collectDeprecations(
      fn() => $controller->getOrcIDAuth('a-client-id', 'a-secret')
    );

    $this->assertSame([], $deprecations);
    $this->assertFalse($token, 'No token is handed back when ORCID can not be reached.');
  }

}
