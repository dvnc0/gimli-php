<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Router\Router;
use Gimli\Router\Route;
use Gimli\Router\Dispatch;
use Gimli\Application;
use Gimli\Application_Registry;
use Gimli\Injector\Injector;
use Gimli\Http\Request;
use Gimli\Http\Response;
use Gimli\Middleware\Middleware_Interface;
use Gimli\Middleware\Middleware_Response;
use Gimli\Events\Event_Manager;
use ReflectionClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;

/**
 * Router test cases - comprehensive coverage of Router class functionality
 */
class Router_Test extends TestCase {

	/**
	 * @var Router $router
	 */
	private Router $router;

	/**
	 * @var Application $app
	 */
	private Application $app;

	/**
	 * @var Injector $injector
	 */
	private Injector $injector;

	/**
	 * @var array $original_server
	 */
	private array $original_server = [];

	/**
	 * Setup method
	 * 
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Backup original globals
		$this->original_server = $_SERVER;
		header_remove();
		http_response_code(200);
		$flag = new \ReflectionProperty(Router::class, 'legacy_redirect_noticed');
		$flag->setAccessible(true);
		$flag->setValue(null, false);
		
		// Create mock application and dependencies
		$this->setupMockApplication();
	}

	/**
	 * Teardown method
	 * 
	 * @return void
	 */
	protected function tearDown(): void {
		// Restore original globals
		$_SERVER = $this->original_server;
		
		// Clear Application Registry
		Application_Registry::clear();
		
		parent::tearDown();
	}

	/**
	 * Setup mock application and dependencies
	 * 
	 * @return void
	 */
	private function setupMockApplication(): void {
		// Setup basic $_SERVER environment
		$_SERVER = array_merge($_SERVER, [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI' => '/',
			'SERVER_NAME' => 'localhost',
			'SERVER_PORT' => '80',
			'HTTPS' => '',
			'HTTP_HOST' => 'localhost',
			'SCRIPT_NAME' => '/index.php',
			'argv' => ['test-script'],
			'argc' => 1
		]);
		
		// Create temporary application and injector (circular dependency resolution)
		$tempApp = new Application('/tmp', $_SERVER);
		$this->injector = new Injector($tempApp);
		
		// Now create the proper application with the injector
		$this->app = Application::create('/tmp', $_SERVER, $this->injector);
		
		// Update injector's Application reference
		$reflection = new ReflectionClass($this->injector);
		$appProperty = $reflection->getProperty('Application');
		$appProperty->setAccessible(true);
		$appProperty->setValue($this->injector, $this->app);
		
		// Register dependencies
		$this->injector->register(Event_Manager::class, new Event_Manager());
		$this->injector->bind(Request::class, fn() => new Request($_SERVER));
		$this->injector->bind(Response::class, fn() => new Response());
		$this->injector->bind(Dispatch::class, fn() => new Dispatch());
		
		// Set Application Registry
		Application_Registry::set($this->app);
		
		// Create router with mocked application that returns false for isCli
		$mockApp = $this->createMock(Application::class);
		$mockApp->method('isCli')->willReturn(false);
		$mockApp->Injector = $this->injector;
		
		$this->router = new class($mockApp) extends Router {
			protected function queuedHeaders(): array {
				return array_merge(parent::queuedHeaders(), function_exists('xdebug_get_headers') ? xdebug_get_headers() : []);
			}
		};
		$this->router->Request = $this->injector->resolve(Request::class);
	}

	/**
	 * Create a mock controller for testing
	 * 
	 * @return void
	 */
	private function createMockController(): void {
		// Register a mock controller in the injector
		$mockController = new class {
			public function index(): Response {
				$response = new Response();
				$response->setResponse('Controller Index');
				return $response;
			}
			
			public function show(int $id): Response {
				$response = new Response();
				$response->setResponse("Show ID: $id");
				return $response;
			}
			
			public function __invoke(): Response {
				$response = new Response();
				$response->setResponse('Invokable Controller');
				return $response;
			}
		};
		
		$this->injector->register('TestController', $mockController);
	}

	/**
	 * Create a mock middleware for testing
	 * 
	 * @param bool $success
	 * @param string $forward
	 * @return void
	 */
	private function createMockMiddleware(bool $success = true, string $forward = ''): void {
		$mockMiddleware = new class($success, $forward) implements Middleware_Interface {
			private bool $success;
			private string $forward;
			
			public function __construct(bool $success, string $forward) {
				$this->success = $success;
				$this->forward = $forward;
			}
			
			public function process(): Middleware_Response {
				return new Middleware_Response($this->success, $this->forward);
			}
		};
		
		$this->injector->register('TestMiddleware', $mockMiddleware);
	}

	/**
	 * Register a middleware that returns the given Middleware_Response
	 */
	private function createResponseMiddleware(Middleware_Response $result, string $name = 'TestMiddleware'): void {
		$this->injector->register($name, new class($result) implements Middleware_Interface {
			public function __construct(private Middleware_Response $result) {}
			public function process(): Middleware_Response {
				return $this->result;
			}
		});
	}

	/**
	 * Run a protected route guarded by TestMiddleware; returns [output, handler_ran]
	 */
	private function runProtected(string $uri = '/protected', ?array $extra_server = null): array {
		$ran = false;
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/protected'] = [
			'route' => '/protected',
			'handler' => function() use (&$ran) { $ran = true; return 'Protected Content'; },
			'middleware' => ['TestMiddleware']
		];
		$_SERVER['REQUEST_URI'] = $uri;
		foreach (($extra_server ?? []) as $k => $v) {
			$_SERVER[$k] = $v;
		}
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		ob_start();
		$this->router->run();
		$out = ob_get_clean();
		return [$out, $ran];
	}

	/**
	 * Last header value with the given name (headers persist/override within a process)
	 */
	private function lastHeader(string $name): ?string {
		$found = null;
		foreach (xdebug_get_headers() as $h) {
			if (stripos($h, $name . ':') === 0) {
				$found = trim(substr($h, strlen($name) + 1));
			}
		}
		return $found;
	}

	/**
	 * Get basic route structure for testing
	 * 
	 * @return array
	 */
	private function getBasicRouteStructure(): array {
		return [
			'GET' => [],
			'POST' => [],
			'PUT' => [],
			'DELETE' => [],
			'PATCH' => [],
			'CLI' => []  // Always include CLI routes to prevent undefined key errors
		];
	}

	// === BASIC ROUTING TESTS ===

	public function testRouterConstruction(): void {
		$this->assertInstanceOf(Router::class, $this->router);
		$this->assertInstanceOf(Request::class, $this->router->Request);
	}

	public function testAddRoutesSuccess(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/'] = [
			'route' => '/',
			'handler' => fn() => 'Home'
		];
		
		$this->router->addRoutes($routes);
		$this->assertTrue(true); // No exception thrown
	}

	public function testAddRoutesEmptyThrowsException(): void {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('No routes provided');
		
		$this->router->addRoutes([]);
	}

	public function testSimpleRouteMatching(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/'] = [
			'route' => '/',
			'handler' => fn() => 'Home'
		];
		
		$this->router->addRoutes($routes);
		
		// Capture output
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('Home', $output);
	}

	public function testRouteNotFound(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/existing'] = [
			'route' => '/existing',
			'handler' => fn() => 'Found'
		];
		
		$_SERVER['REQUEST_URI'] = '/nonexistent';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		// Capture output
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('404 page not found', $output);
	}

	public function testInvalidRequestMethod(): void {
		$_SERVER['REQUEST_METHOD'] = 'INVALID';
		$this->router->Request = new Request($_SERVER);
		
		$routes = $this->getBasicRouteStructure();
		$this->router->addRoutes($routes);
		
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Request method INVALID not allowed');
		
		$this->router->run();
	}

	// === PARAMETER ROUTING TESTS ===

	public function testRouteWithParameters(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/user/:id'] = [
			'route' => '/user/:id',
			'handler' => fn($id) => "User ID: $id",
			'arg_names' => ['id']
		];
		
		$_SERVER['REQUEST_URI'] = '/user/123';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('User ID: 123', $output);
	}

	public function testRouteWithMultipleParameters(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/user/:id/post/:slug'] = [
			'route' => '/user/:id/post/:slug',
			'handler' => fn($id, $slug) => "User: $id, Post: $slug",
			'arg_names' => ['id', 'slug']
		];
		
		$_SERVER['REQUEST_URI'] = '/user/456/post/my-article';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('User: 456, Post: my-article', $output);
	}

	public function testParameterValidation(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/user/:all'] = [  // Use :all pattern which matches any characters
			'route' => '/user/:all',
			'handler' => fn($id) => "User ID: $id",
			'arg_names' => ['id'],
			'param_types' => [
				'id' => 'integer'  // But validate as integer
			]
		];
		
		$this->router->addRoutes($routes);
		
		// Test with invalid parameter (non-integer)
		$_SERVER['REQUEST_URI'] = '/user/abc';
		$this->router->Request = new Request($_SERVER);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('Bad Request: Invalid parameters', $output);
	}

	public function testParameterTooLong(): void {
		// Modify the Router's patterns to allow longer strings for testing
		$reflection = new ReflectionClass($this->router);
		$patternsProperty = $reflection->getProperty('patterns');
		$patternsProperty->setAccessible(true);
		$patterns = $patternsProperty->getValue($this->router);
		$patterns[':all'] = "([a-zA-Z0-9._-]{1,2000})"; // Allow up to 2000 chars
		$patternsProperty->setValue($this->router, $patterns);
		
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/user/:all'] = [
			'route' => '/user/:all',
			'handler' => fn($id) => "User ID: $id",
			'arg_names' => ['id']
		];
		
		$this->router->addRoutes($routes);
		
		// Test with parameter that's too long (over 1000 chars)
		$longId = str_repeat('1', 1001);
		$_SERVER['REQUEST_URI'] = "/user/$longId";
		$this->router->Request = new Request($_SERVER);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('Bad Request: Invalid parameters', $output);
	}

	// === CONTROLLER TESTS ===

	public function testControllerRouting(): void {
		$this->createMockController();
		
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/controller'] = [
			'route' => '/controller',
			'handler' => 'TestController@index'
		];
		
		$this->router->addRoutes($routes);
		
		$_SERVER['REQUEST_URI'] = '/controller';
		$this->router->Request = new Request($_SERVER);
		
		// The controller is called and returns a Response, which is dispatched
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		// The Dispatch should output the response content
		$this->assertEquals('Controller Index', $output);
	}

	public function testInvokableController(): void {
		$this->createMockController();
		
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/invokable'] = [
			'route' => '/invokable',
			'handler' => 'TestController'
		];
		
		$this->router->addRoutes($routes);
		
		$_SERVER['REQUEST_URI'] = '/invokable';
		$this->router->Request = new Request($_SERVER);
		
		// The invokable controller is called and returns a Response
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		// The Dispatch should output the response content
		$this->assertEquals('Invokable Controller', $output);
	}

	// === MIDDLEWARE TESTS ===

	public function testMiddlewareSuccess(): void {
		$this->createMockMiddleware(true);
		
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/protected'] = [
			'route' => '/protected',
			'handler' => fn() => 'Protected Content',
			'middleware' => ['TestMiddleware']
		];
		
		$_SERVER['REQUEST_URI'] = '/protected';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('Protected Content', $output);
	}

	/**
	 * Legacy positional form: new Middleware_Response(false, '/login') puts the URL in message.
	 */
	#[RequiresFunction('xdebug_get_headers')]
	public function testMiddlewareFailureRedirect(): void {
		$this->createMockMiddleware(false, '/login');
		$deprecations = [];
		set_error_handler(function(int $no, string $str) use (&$deprecations) {
			$deprecations[] = $str;
			return true;
		}, E_USER_DEPRECATED);

		try {
			[$out, $ran] = $this->runProtected();
		} finally {
			restore_error_handler();
		}

		$this->assertSame('', $out);
		$this->assertFalse($ran);
		$this->assertSame(302, http_response_code());
		$this->assertSame('/login', $this->lastHeader('Location'));
		$this->assertCount(1, $deprecations);
		$this->assertStringContainsString("Middleware_Response::redirect('/login')", $deprecations[0]);
	}

	public static function unsafeLegacyMessages(): array {
		return [
			'protocol relative' => ['//evil.com'],
			'scheme' => ['http://evil.com'],
			'crlf' => ["/a\r\nSet-Cookie: x=1"],
			'backslash' => ['/a\\b'],
			'plain text' => ['plain message'],
			'tab' => ["/\t/evil.com"],
			'vertical tab' => ["/\x0b/evil"],
			'space' => ['/ requires login'],
			'non-ascii' => ["/caf\u{e9}"],
			'nul' => ["/a\x00b"],
			'double slash inside' => ['/a//b'],
			'dot segment' => ['/./x'],
			'dot double slash' => ['/.//x'],
		];
	}

	#[DataProvider('unsafeLegacyMessages')]
	#[RequiresFunction('xdebug_get_headers')]
	public function testLegacyUnsafeMessageIsNotRedirected(string $msg): void {
		$this->createMockMiddleware(false, $msg);
		[$out, $ran] = $this->runProtected();
		$this->assertSame('', $out);
		$this->assertFalse($ran);
		$this->assertSame(403, http_response_code());
		$this->assertNull($this->lastHeader('Location'));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testLegacyDeprecationFiresOncePerProcess(): void {
		$deprecations = [];
		set_error_handler(function(int $no, string $str) use (&$deprecations) {
			$deprecations[] = $str;
			return true;
		}, E_USER_DEPRECATED);
		try {
			$this->createMockMiddleware(false, '/login');
			$this->runProtected();
			header_remove();
			$this->runProtected();
		} finally {
			restore_error_handler();
		}
		$this->assertCount(1, $deprecations);
		$this->assertSame('/login', $this->lastHeader('Location'));
		$this->assertSame(302, http_response_code());
	}

	public static function safeLegacyMessages(): array {
		return [['/login#top'], ['/search?q[]=a'], ['/a/.b'], ['/.hidden']];
	}

	#[DataProvider('safeLegacyMessages')]
	#[RequiresFunction('xdebug_get_headers')]
	public function testLegacySafeMessageRedirects(string $msg): void {
		$this->createMockMiddleware(false, $msg);
		set_error_handler(fn() => true, E_USER_DEPRECATED);
		try {
			[, $ran] = $this->runProtected();
		} finally {
			restore_error_handler();
		}
		$this->assertFalse($ran);
		$this->assertSame(302, http_response_code());
		$this->assertSame($msg, $this->lastHeader('Location'));
	}

	public static function badForwards(): array {
		return [["/a\tb"], ["/a\x00b"], ["/a\nb"]];
	}

	#[DataProvider('badForwards')]
	#[RequiresFunction('xdebug_get_headers')]
	public function testForwardWithControlCharsGives403(string $forward): void {
		$this->createResponseMiddleware(new Middleware_Response(false, '', $forward));
		[$out, $ran] = $this->runProtected();
		$this->assertFalse($ran);
		$this->assertSame('', $out);
		$this->assertSame(403, http_response_code());
		$this->assertNull($this->lastHeader('Location'));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testResponseOwnVaryKeptAlongsideVaryOrigin(): void {
		$r = Middleware_Response::json([], 'Unauthorized', 401, ['Vary: Accept-Encoding']);
		$this->createResponseMiddleware($r);
		$this->runProtected('/protected', ['HTTP_ORIGIN' => 'https://app.example']);
		$vary = strtolower(implode(',', $this->allHeaders('Vary')));
		$this->assertStringContainsString('origin', $vary);
		$this->assertStringContainsString('accept-encoding', $vary);
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testQueuedAllowCredentialsSuppressesOriginEcho(): void {
		header('Access-Control-Allow-Credentials: true');
		$this->createResponseMiddleware(Middleware_Response::json());
		$this->runProtected('/protected', ['HTTP_ORIGIN' => 'https://app.example']);
		$this->assertSame([], $this->allHeaders('Access-Control-Allow-Origin'));
		$this->assertSame(401, http_response_code());
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testResponseAllowCredentialsSuppressesOriginEcho(): void {
		$r = Middleware_Response::json([], 'Unauthorized', 401, ['Access-Control-Allow-Credentials: true']);
		$this->createResponseMiddleware($r);
		$this->runProtected('/protected', ['HTTP_ORIGIN' => 'https://app.example']);
		$this->assertSame([], $this->allHeaders('Access-Control-Allow-Origin'));
	}

	private function allHeaders(string $name): array {
		$out = [];
		foreach (xdebug_get_headers() as $h) {
			if (stripos($h, $name . ':') === 0) {
				$out[] = trim(substr($h, strlen($name) + 1));
			}
		}
		return $out;
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testCorsEchoesOriginWithVaryOnResponseBranch(): void {
		$this->createResponseMiddleware(Middleware_Response::json());
		$this->runProtected('/protected', ['HTTP_ORIGIN' => 'https://app.example']);
		$this->assertSame(['https://app.example'], $this->allHeaders('Access-Control-Allow-Origin'));
		$this->assertStringContainsStringIgnoringCase('origin', implode(',', $this->allHeaders('Vary')));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testCorsOnBare403(): void {
		$this->createResponseMiddleware(new Middleware_Response(false));
		$this->runProtected('/protected', ['HTTP_ORIGIN' => 'https://app.example']);
		$this->assertSame(403, http_response_code());
		$this->assertSame(['https://app.example'], $this->allHeaders('Access-Control-Allow-Origin'));
		$this->assertStringContainsStringIgnoringCase('origin', implode(',', $this->allHeaders('Vary')));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testCorsOnForwardBranch(): void {
		$this->createResponseMiddleware(new Middleware_Response(false, '', '/login'));
		$this->runProtected('/protected', ['HTTP_ORIGIN' => 'https://app.example']);
		$this->assertSame(['https://app.example'], $this->allHeaders('Access-Control-Allow-Origin'));
		$this->assertSame('/login', $this->lastHeader('Location'));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testCorsOnLegacyBranch(): void {
		$this->createMockMiddleware(false, '/login');
		set_error_handler(fn() => true, E_USER_DEPRECATED);
		try {
			$this->runProtected('/protected', ['HTTP_ORIGIN' => 'https://app.example']);
		} finally {
			restore_error_handler();
		}
		$this->assertSame(['https://app.example'], $this->allHeaders('Access-Control-Allow-Origin'));
		$this->assertSame('/login', $this->lastHeader('Location'));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testNullOriginNotEchoed(): void {
		$this->createResponseMiddleware(Middleware_Response::json());
		$this->runProtected('/protected', ['HTTP_ORIGIN' => 'null']);
		$this->assertSame([], $this->allHeaders('Access-Control-Allow-Origin'));
		$this->assertSame(401, http_response_code());
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testNoOriginNoCorsHeaders(): void {
		unset($_SERVER['HTTP_ORIGIN']);
		$this->createResponseMiddleware(Middleware_Response::json());
		$this->runProtected();
		$this->assertSame([], $this->allHeaders('Access-Control-Allow-Origin'));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testPreQueuedAcaoNotOverwritten(): void {
		header('Access-Control-Allow-Origin: https://trusted.example');
		$this->createResponseMiddleware(Middleware_Response::json());
		$this->runProtected('/protected', ['HTTP_ORIGIN' => 'https://app.example']);
		$this->assertSame(['https://trusted.example'], $this->allHeaders('Access-Control-Allow-Origin'));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testConstructorResponseBelow400Gives403(): void {
		$r = new Response();
		$r->setResponse('ok', true, [], 200);
		$this->createResponseMiddleware(new Middleware_Response(false, 'x', '', $r));
		[$out, $ran] = $this->runProtected();
		$this->assertFalse($ran);
		$this->assertSame(403, http_response_code());
		$this->assertSame('', $out);
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testEmptyFailureReturns403(): void {
		$this->createResponseMiddleware(new Middleware_Response(false));
		[$out, $ran] = $this->runProtected();
		$this->assertSame('', $out);
		$this->assertFalse($ran);
		$this->assertSame(403, http_response_code());
		$this->assertNull($this->lastHeader('Location'));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testJson401EndToEnd(): void {
		$this->createResponseMiddleware(Middleware_Response::json(['reason' => 'no token'], 'Unauthorized', 401, ['WWW-Authenticate: Bearer']));
		[$out, $ran] = $this->runProtected('/protected', ['HTTP_ORIGIN' => 'https://app.example']);

		$this->assertFalse($ran);
		$this->assertSame(401, http_response_code());
		$this->assertSame('application/json', $this->lastHeader('Content-Type'));
		$this->assertSame('Bearer', $this->lastHeader('WWW-Authenticate'));
		$this->assertSame('https://app.example', $this->lastHeader('Access-Control-Allow-Origin'));
		$this->assertSame(
			['success' => false, 'body' => ['reason' => 'no token'], 'text' => 'Unauthorized'],
			json_decode($out, true)
		);
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testRedirectFactoryEndToEnd(): void {
		$this->createResponseMiddleware(Middleware_Response::redirect('/login', 303));
		[$out, $ran] = $this->runProtected();
		$this->assertFalse($ran);
		$this->assertSame('', $out);
		$this->assertSame(303, http_response_code());
		$this->assertSame('/login', $this->lastHeader('Location'));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testExplicitForwardRedirects302(): void {
		$this->createResponseMiddleware(new Middleware_Response(false, 'denied', '/login'));
		[$out, $ran] = $this->runProtected();
		$this->assertFalse($ran);
		$this->assertSame(302, http_response_code());
		$this->assertSame('/login', $this->lastHeader('Location'));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testResponseTakesPrecedenceOverForward(): void {
		$r = new Response();
		$r->setResponse('nope', false, [], 418);
		$this->createResponseMiddleware(new Middleware_Response(false, 'x', '/login', $r));
		[$out, ] = $this->runProtected();
		$this->assertSame('nope', $out);
		$this->assertSame(418, http_response_code());
		$this->assertNull($this->lastHeader('Location'));
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testWwwAuthenticateWith403Stays403(): void {
		$r = new Response();
		$r->setResponse('forbidden', false, [], 403)->setHeader('WWW-Authenticate: Basic');
		$this->createResponseMiddleware(Middleware_Response::respond($r));
		$this->runProtected();
		$this->assertSame(403, http_response_code());
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testMiddleware401AfterEarlier404Route(): void {
		// A 404 request first must not leak status into a later middleware-401 request
		$this->router->Request = new Request(array_merge($_SERVER, ['REQUEST_URI' => '/does-not-exist']));
		$this->router->addRoutes($this->getBasicRouteStructure());
		ob_start();
		$this->router->run();
		ob_end_clean();
		$this->assertSame(404, http_response_code());

		header_remove();
		$this->createResponseMiddleware(Middleware_Response::json([], 'Unauthorized', 401));
		[, $ran] = $this->runProtected();
		$this->assertFalse($ran);
		$this->assertSame(401, http_response_code());
	}

	public function testMiddlewareSuccessStillRunsHandler(): void {
		$this->createResponseMiddleware(new Middleware_Response(true));
		[$out, $ran] = $this->runProtected();
		$this->assertTrue($ran);
		$this->assertSame('Protected Content', $out);
	}

	public function testFactoriesDoNotMutateInjectorSingleton(): void {
		$singleton = new Response();
		$this->injector->register(Response::class, $singleton);
		Middleware_Response::json(['a' => 1], 'x', 401, ['X-A: b']);
		Middleware_Response::redirect('/x');
		Middleware_Response::respond((new Response())->setResponse('z', false, [], 500));
		$this->assertSame(200, $singleton->response_code);
		$this->assertSame([], $singleton->headers);
		$this->assertFalse($singleton->is_json);
	}

	public function testInvalidMiddleware(): void {
		// Register invalid middleware (not implementing interface)
		$this->injector->register('InvalidMiddleware', new stdClass());
		
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/test'] = [
			'route' => '/test',
			'handler' => fn() => 'Test',
			'middleware' => ['InvalidMiddleware']
		];
		
		$_SERVER['REQUEST_URI'] = '/test';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		$this->expectException(Error::class);
		$this->expectExceptionMessage('Object of class stdClass could not be converted to string');
		
		$this->router->run();
	}

	// === TYPE CASTING TESTS ===

	public function testSafeCastInteger(): void {
		$reflection = new ReflectionClass($this->router);
		$method = $reflection->getMethod('safeCastType');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->router, '123', 'int', 'test_param');
		$this->assertSame(123, $result);
	}

	public function testSafeCastFloat(): void {
		$reflection = new ReflectionClass($this->router);
		$method = $reflection->getMethod('safeCastType');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->router, '123.45', 'float', 'test_param');
		$this->assertSame(123.45, $result);
	}

	public function testSafeCastString(): void {
		$reflection = new ReflectionClass($this->router);
		$method = $reflection->getMethod('safeCastType');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->router, 123, 'string', 'test_param');
		$this->assertSame('123', $result);
	}

	public function testSafeCastBoolean(): void {
		$reflection = new ReflectionClass($this->router);
		$method = $reflection->getMethod('safeCastType');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->router, 'true', 'bool', 'test_param');
		$this->assertTrue($result);
		
		$result = $method->invoke($this->router, 'false', 'bool', 'test_param');
		$this->assertFalse($result);
	}

	public function testSafeCastInvalidType(): void {
		$reflection = new ReflectionClass($this->router);
		$method = $reflection->getMethod('safeCastType');
		$method->setAccessible(true);
		
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Unsupported cast type: invalid');
		
		$method->invoke($this->router, 'value', 'invalid', 'test_param');
	}

	// === PARAMETER VALIDATION TESTS ===

	public function testValidateParameterTypeInteger(): void {
		$reflection = new ReflectionClass($this->router);
		$method = $reflection->getMethod('validateParameterType');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->router, 'id', '123', 'int');
		$this->assertSame(123, $result);
	}

	public function testValidateParameterTypeSlug(): void {
		$reflection = new ReflectionClass($this->router);
		$method = $reflection->getMethod('validateParameterType');
		$method->setAccessible(true);
		
		$result = $method->invoke($this->router, 'slug', 'my-article-123', 'slug');
		$this->assertEquals('my-article-123', $result);
	}

	public function testValidateParameterTypeInvalidInteger(): void {
		$reflection = new ReflectionClass($this->router);
		$method = $reflection->getMethod('validateParameterType');
		$method->setAccessible(true);
		
		$this->expectException(Exception::class);
		$this->expectExceptionMessage("Parameter 'id' must be an integer");
		
		$method->invoke($this->router, 'id', 'abc', 'int');
	}

	// === TRAILING SLASH TESTS ===

	public function testTrailingSlashRemoval(): void {
		// Set trailing slash matters to false so they get removed
		$reflection = new ReflectionClass($this->router);
		$property = $reflection->getProperty('trailing_slash_matters');
		$property->setAccessible(true);
		$property->setValue($this->router, false);
		
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/test'] = [
			'route' => '/test',
			'handler' => fn() => 'Test Page'
		];
		
		$this->router->addRoutes($routes);
		
		// Test with trailing slash - should be removed and match /test
		$_SERVER['REQUEST_URI'] = '/test/';
		$this->router->Request = new Request($_SERVER);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('Test Page', $output);
	}

	// === QUERY STRING HANDLING ===

	public function testQueryStringIgnored(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/search'] = [
			'route' => '/search',
			'handler' => fn() => 'Search Results'
		];
		
		$_SERVER['REQUEST_URI'] = '/search?q=test&page=1';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('Search Results', $output);
	}

	// === HTTP METHODS TESTS ===

	public function testPostMethod(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		
		$routes = $this->getBasicRouteStructure();
		$routes['POST']['/submit'] = [
			'route' => '/submit',
			'handler' => fn() => 'Form Submitted'
		];
		
		$_SERVER['REQUEST_URI'] = '/submit';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('Form Submitted', $output);
	}

	public function testPutMethod(): void {
		$_SERVER['REQUEST_METHOD'] = 'PUT';
		
		$routes = $this->getBasicRouteStructure();
		$routes['PUT']['/update'] = [
			'route' => '/update',
			'handler' => fn() => 'Resource Updated'
		];
		
		$_SERVER['REQUEST_URI'] = '/update';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('Resource Updated', $output);
	}

	public function testDeleteMethod(): void {
		$_SERVER['REQUEST_METHOD'] = 'DELETE';
		
		$routes = $this->getBasicRouteStructure();
		$routes['DELETE']['/delete'] = [
			'route' => '/delete',
			'handler' => fn() => 'Resource Deleted'
		];
		
		$_SERVER['REQUEST_URI'] = '/delete';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('Resource Deleted', $output);
	}

	public function testPatchMethod(): void {
		$_SERVER['REQUEST_METHOD'] = 'PATCH';
		
		$routes = $this->getBasicRouteStructure();
		$routes['PATCH']['/patch'] = [
			'route' => '/patch',
			'handler' => fn() => 'Resource Patched'
		];
		
		$_SERVER['REQUEST_URI'] = '/patch';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('Resource Patched', $output);
	}

	// === MAGIC METHOD TESTS ===

	public function testMagicGetProperty(): void {
		// Test accessing Request property through magic __get
		$request = $this->router->Request;
		$this->assertInstanceOf(Request::class, $request);
		
		// Test accessing Injector property through magic __get  
		$injector = $this->router->Injector;
		$this->assertInstanceOf(Injector::class, $injector);
		
		// Both should return the same instances
		$this->assertSame($request, $this->router->Request);
		$this->assertSame($injector, $this->router->Injector);
	}

	public function testMagicGetApplicationProperty(): void {
		$injector = $this->router->Injector;
		$this->assertInstanceOf(Injector::class, $injector);
	}

	// === LITERAL ROUTE PATH MATCHING (regex escaping) ===

	/**
	 * Register a single GET route and dispatch a URI. Returns the output.
	 *
	 * @param string $route_path
	 * @param string $uri
	 * @param array $arg_names
	 * The route is registered under a key that differs from the path so the
	 * `$path === $uri` shortcut is bypassed and the compiled regex is exercised.
	 *
	 * @return string
	 */
	private function dispatchRoute(string $route_path, string $uri, array $arg_names = []): string {
		$routes = $this->getBasicRouteStructure();
		$route = [
			'route' => $route_path,
			'handler' => fn(...$args) => 'matched:' . implode(',', $args),
		];
		if (!empty($arg_names)) {
			$route['arg_names'] = $arg_names;
		}
		$routes['GET']['key:' . $route_path] = $route;

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI'] = $uri;
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);

		ob_start();
		$this->router->run();
		return ob_get_clean();
	}

	public static function literalNearMissProvider(): array {
		return [
			'sitemap exact'        => ['/sitemap.xml', '/sitemap.xml', true],
			'sitemap Z'            => ['/sitemap.xml', '/sitemapZxml', false],
			'sitemap dash'         => ['/sitemap.xml', '/sitemap-xml', false],
			'sitemap underscore'   => ['/sitemap.xml', '/sitemap_xml', false],
			'sitemap no dot'       => ['/sitemap.xml', '/sitemapxml', false],
			'feed exact'           => ['/feed.rss', '/feed.rss', true],
			'feed near miss'       => ['/feed.rss', '/feedXrss', false],
			'feed near miss 2'     => ['/feed.rss', '/feed-rss', false],
			'manifest exact'       => ['/manifest.json', '/manifest.json', true],
			'manifest near miss'   => ['/manifest.json', '/manifestXjson', false],
			'manifest near miss 2' => ['/manifest.json', '/manifest_json', false],
			'plus exact'           => ['/a+b', '/a+b', true],
			'plus not quantifier'  => ['/a+b', '/aab', false],
			'plus not quantifier 2' => ['/a+b', '/aaab', false],
			'plus not quantifier 3' => ['/a+b', '/ab', false],
			'parens exact'         => ['/f(1)', '/f(1)', true],
			'parens not group'     => ['/f(1)', '/f1', false],
			'brackets exact'       => ['/c[ab]', '/c[ab]', true],
			'brackets not class'   => ['/c[ab]', '/ca', false],
			'brackets not class 2' => ['/c[ab]', '/cb', false],
			'dollar exact'         => ['/a$b', '/a$b', true],
			'dollar not anchor'    => ['/a$', '/a', false],
			'caret exact'          => ['/a^b', '/a^b', true],
			'caret not anchor'     => ['/^a', '/a', false],
			'pipe exact'           => ['/a|b', '/a|b', true],
			'pipe not alternation' => ['/a|b', '/a', false],
			'pipe not alternation 2' => ['/a|b', 'b', false],
			'star not quantifier'  => ['/a*b', '/aab', false],
			'question not optional' => ['/a?b', '/ab', false],
			'brace not quantifier' => ['/a{2}', '/aa', false],
			'dotstar not wildcard' => ['/x.*y', '/xanythingy', false],
		];
	}

	#[DataProvider('literalNearMissProvider')]
	public function testLiteralRoutePathMatchedLiterally(string $route, string $uri, bool $should_match): void {
		$output = $this->dispatchRoute($route, $uri);

		if ($should_match) {
			$this->assertSame('matched:', $output);
		} else {
			$this->assertSame('404 page not found', $output);
		}
	}

	public function testLiteralRouteWithRegexCharsMatchesWhenPlaceholderPresent(): void {
		// Non-exact path so the regex branch is used (not the === shortcut)
		$output = $this->dispatchRoute('/v1.0/user/:id', '/v1.0/user/42', ['id']);
		$this->assertSame('matched:42', $output);

		$output = $this->dispatchRoute('/v1.0/user/:id', '/v1X0/user/42', ['id']);
		$this->assertSame('404 page not found', $output);
	}

	public function testRegexMetaCharsWithPlaceholderAreLiteral(): void {
		$this->assertSame('matched:7', $this->dispatchRoute('/a+b/:id', '/a+b/7', ['id']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/a+b/:id', '/aab/7', ['id']));
		$this->assertSame('matched:7', $this->dispatchRoute('/(x)/:id', '/(x)/7', ['id']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/(x)/:id', '/x/7', ['id']));
		$this->assertSame('matched:7', $this->dispatchRoute('/a|b/:id', '/a|b/7', ['id']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/a|b/:id', '/a/7', ['id']));
		$this->assertSame('matched:7', $this->dispatchRoute('/c[ab]/:id', '/c[ab]/7', ['id']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/c[ab]/:id', '/ca/7', ['id']));
	}

	public function testSlugPlaceholderWithDottedLiteralSuffix(): void {
		// :slug does not allow '.', so the capture is well-defined
		$this->assertSame('matched:hello', $this->dispatchRoute('/posts/:slug.json', '/posts/hello.json', ['slug']));
		$this->assertSame('matched:my-post_1', $this->dispatchRoute('/posts/:slug.json', '/posts/my-post_1.json', ['slug']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/posts/:slug.json', '/posts/helloXjson', ['slug']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/posts/:slug.json', '/posts/hello-json', ['slug']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/posts/:slug.json', '/posts/hello.jsonX', ['slug']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/posts/:slug.json', '/posts/a.b.json', ['slug']));
	}

	public function testAllPlaceholderWithDottedLiteralSuffix(): void {
		// :all may contain '.', backtracking gives the greedy-then-trimmed capture
		$this->assertSame('matched:a', $this->dispatchRoute('/files/:all.txt', '/files/a.txt', ['name']));
		$this->assertSame('matched:a.b', $this->dispatchRoute('/files/:all.txt', '/files/a.b.txt', ['name']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/files/:all.txt', '/files/aXtxt', ['name']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/files/:all.txt', '/files/a-txt', ['name']));
	}

	public function testPlaceholderFollowedByDotAndPlaceholder(): void {
		$this->assertSame(
			'matched:report,2024',
			$this->dispatchRoute('/dl/:alpha.:integer', '/dl/report.2024', ['name', 'year'])
		);
		$this->assertSame('404 page not found', $this->dispatchRoute('/dl/:alpha.:integer', '/dl/reportX2024', ['name', 'year']));
	}

	public function testAlphanumericPrecedenceOverAlpha(): void {
		// ':alphanumeric' must not be mangled into ':alpha' + 'numeric'
		$this->assertSame('matched:abc123', $this->dispatchRoute('/x/:alphanumeric', '/x/abc123', ['v']));
		$this->assertSame('matched:abc', $this->dispatchRoute('/x/:alphanumeric', '/x/abc', ['v']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/x/:alphanumeric', '/x/abc-123', ['v']));

		$this->assertSame('matched:abc', $this->dispatchRoute('/x/:alpha', '/x/abc', ['v']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/x/:alpha', '/x/abc123', ['v']));
	}

	public function testAllPlaceholderTypesStillExpand(): void {
		$uuid = '123e4567-e89b-12d3-a456-426614174000';
		$this->assertSame('matched:' . $uuid, $this->dispatchRoute('/u/:uuid', '/u/' . $uuid, ['v']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/u/:uuid', '/u/not-a-uuid', ['v']));

		$this->assertSame('matched:12', $this->dispatchRoute('/i/:integer', '/i/12', ['v']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/i/:integer', '/i/1x', ['v']));

		$this->assertSame('matched:3.14', $this->dispatchRoute('/n/:numeric', '/n/3.14', ['v']));
		$this->assertSame('matched:3', $this->dispatchRoute('/n/:numeric', '/n/3', ['v']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/n/:numeric', '/n/3x14', ['v']));

		$this->assertSame('matched:5', $this->dispatchRoute('/d/:id', '/d/5', ['v']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/d/:id', '/d/0', ['v']));

		$this->assertSame('matched:my-slug', $this->dispatchRoute('/s/:slug', '/s/my-slug', ['v']));
		$this->assertSame('matched:a.b-c', $this->dispatchRoute('/a/:all', '/a/a.b-c', ['v']));
	}

	public function testLiteralRouteIsSelectedAmongMultipleRoutes(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/sitemap.xml'] = ['route' => '/sitemap.xml', 'handler' => fn() => 'sitemap'];
		$routes['GET']['/user/:id'] = ['route' => '/user/:id', 'handler' => fn($id) => "user:$id", 'arg_names' => ['id']];
		$this->router->addRoutes($routes);

		foreach ([['/sitemap.xml', 'sitemap'], ['/user/9', 'user:9'], ['/sitemapZxml', '404 page not found']] as [$uri, $expected]) {
			$_SERVER['REQUEST_URI'] = $uri;
			$this->router->Request = new Request($_SERVER);
			ob_start();
			$this->router->run();
			$this->assertSame($expected, ob_get_clean(), $uri);
		}
	}

	// === OPTIONS PATH ESCAPING ===

	/**
	 * Run an OPTIONS request and return the Allow header value (or null).
	 */
	private function optionsAllow(array $routes, string $uri): ?string {
		$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
		$_SERVER['REQUEST_URI'] = $uri;
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);

		$reflection = new ReflectionClass($this->router);
		$prop = $reflection->getProperty('auto_handle_options');
		$prop->setAccessible(true);
		$prop->setValue($this->router, true);

		$method = $reflection->getMethod('handleOptionsRequest');
		$method->setAccessible(true);

		ob_start();
		$method->invoke($this->router, $uri);
		ob_end_clean();

		$headers = xdebug_get_headers();
		foreach ($headers as $h) {
			if (stripos($h, 'Allow:') === 0) {
				return trim(substr($h, 6));
			}
		}
		return null;
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testOptionsLiteralPathEscaped(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/sitemap.xml'] = ['route' => '/sitemap.xml', 'handler' => fn() => 'x'];
		$routes['POST']['/sitemap.xml'] = ['route' => '/sitemap.xml', 'handler' => fn() => 'x'];

		$allow = $this->optionsAllow($routes, '/sitemapZxml');
		$this->assertNotNull($allow, 'Allow header was not set');
		$this->assertSame('OPTIONS', $allow);
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testOptionsLiteralPathExactAndPlaceholder(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['key:/feed.rss'] = ['route' => '/feed.rss', 'handler' => fn() => 'x'];
		$routes['PUT']['key:/posts/:slug.json'] = ['route' => '/posts/:slug.json', 'handler' => fn() => 'x', 'arg_names' => ['slug']];

		$allow = $this->optionsAllow($routes, '/feed.rss');
		$this->assertNotNull($allow, 'Allow header was not set');
		$this->assertSame('GET, OPTIONS', $allow);
		$this->assertSame('PUT, OPTIONS', $this->optionsAllow($routes, '/posts/hello.json'));
		$this->assertSame('OPTIONS', $this->optionsAllow($routes, '/posts/helloXjson'));
	}

	// === compilePattern() DIRECT TESTS ===

	private function compile(string $route): string {
		$method = (new ReflectionClass($this->router))->getMethod('compilePattern');
		$method->setAccessible(true);
		return $method->invoke($this->router, $route);
	}

	public function testCompilePatternExactOutput(): void {
		$this->assertSame('#^/sitemap\.xml$#D', $this->compile('/sitemap.xml'));
		$this->assertSame('#^/posts/([a-zA-Z0-9-_]{1,100})\.json$#D', $this->compile('/posts/:slug.json'));
		$this->assertSame('#^/a/([a-zA-Z0-9]{1,50})$#D', $this->compile('/a/:alphanumeric'));
		$this->assertSame('#^/a/([a-zA-Z]{1,50})$#D', $this->compile('/a/:alpha'));
	}

	public function testCompilePatternEscapesMetaCharacters(): void {
		$this->assertSame('#^/a\+b$#D', $this->compile('/a+b'));
		$this->assertSame('#^/f\(1\)$#D', $this->compile('/f(1)'));
		$this->assertSame('#^/c\[ab\]$#D', $this->compile('/c[ab]'));
		$this->assertSame('#^/a\|b$#D', $this->compile('/a|b'));
		$this->assertSame('#^/a\$b$#D', $this->compile('/a$b'));
		$this->assertSame('#^/a\^b$#D', $this->compile('/a^b'));
		$this->assertSame('#^/a\?b$#D', $this->compile('/a?b'));
		$this->assertSame('#^/a\*b$#D', $this->compile('/a*b'));
		$this->assertSame('#^/a\{2\}$#D', $this->compile('/a{2}'));
		$this->assertSame('#^/a\#b$#D', $this->compile('/a#b'));
	}

	public function testCompilePatternEndsWithDollarHashD(): void {
		foreach (['/', '/x', '/x/:id', '/sitemap.xml'] as $route) {
			$this->assertStringEndsWith('$#D', $this->compile($route));
			$this->assertStringStartsWith('#^', $this->compile($route));
		}
	}

	public static function compiledMatchProvider(): array {
		return [
			['/a$b', '/a$b', true],
			['/a$b', '/ab', false],
			['/a^b', '/a^b', true],
			['/a^b', '/ab', false],
			['/a?b', '/a?b', true],
			['/a?b', '/ab', false],
			['/a?b', '/b', false],
			['/a*b', '/a*b', true],
			['/a*b', '/aab', false],
			['/a*b', '/b', false],
			['/a{2}', '/a{2}', true],
			['/a{2}', '/aa', false],
			['/a+b', '/a+b', true],
			['/a+b', '/aab', false],
			['/f(1)', '/f(1)', true],
			['/f(1)', '/f1', false],
			['/c[ab]', '/c[ab]', true],
			['/c[ab]', '/ca', false],
			['/a|b', '/a|b', true],
			['/a|b', '/a', false],
			['/a#b', '/a#b', true],
			['/a#b', '/ab', false],
			['/sitemap.xml', '/sitemap.xml', true],
			['/sitemap.xml', '/sitemapZxml', false],
			['/feed.rss', '/feed.rss', true],
			['/feed.rss', '/feedXrss', false],
			['/manifest.json', '/manifest.json', true],
			['/manifest.json', '/manifestXjson', false],
			['/posts/:slug.json', '/posts/hello.json', true],
			['/posts/:slug.json', '/posts/helloXjson', false],
		];
	}

	#[DataProvider('compiledMatchProvider')]
	public function testCompiledPatternMatching(string $route, string $uri, bool $expected): void {
		$this->assertSame($expected ? 1 : 0, preg_match($this->compile($route), $uri), "$route vs $uri");
	}

	public function testCompilePatternCapturesOnlyPlaceholders(): void {
		preg_match($this->compile('/a.b/:slug.json'), '/a.b/hello.json', $m);
		$this->assertSame(['/a.b/hello.json', 'hello'], $m);
	}

	public function testTrailingNewlineDoesNotMatchExactRoute(): void {
		$this->assertSame(0, preg_match($this->compile('/admin'), "/admin\n"));
		$this->assertSame(1, preg_match($this->compile('/admin'), '/admin'));
		// and through the router, with the regex branch forced by a differing key
		$this->assertSame('404 page not found', $this->dispatchRoute('/admin', "/admin\n"));
		$this->assertSame('matched:', $this->dispatchRoute('/admin', '/admin'));
	}

	// === :path PLACEHOLDER ===

	public function testPathPlaceholderCapturesSegments(): void {
		$this->assertSame('matched:a', $this->dispatchRoute('/docs/:path', '/docs/a', ['page']));
		$this->assertSame('matched:a/b/c.md', $this->dispatchRoute('/docs/:path', '/docs/a/b/c.md', ['page']));
		$this->assertSame('matched:a-b_c~d/e.f', $this->dispatchRoute('/docs/:path', '/docs/a-b_c~d/e.f', ['page']));
	}

	public static function pathRejectedProvider(): array {
		return [
			'empty'              => ['/docs/'],
			'double slash'       => ['/docs//a'],
			'trailing slash'     => ['/docs/a/'],
			'dotdot'             => ['/docs/../etc/passwd'],
			'dotdot mid'         => ['/docs/a/../b'],
			'dotfile'            => ['/docs/.env'],
			'dotfile nested'     => ['/docs/a/.env'],
			'single dot'         => ['/docs/.'],
			'bad char'           => ['/docs/a b'],
			'backslash'          => ['/docs/a\\b'],
			'newline'            => ["/docs/a\n"],
			'very long'          => ['/docs/' . str_repeat('a', 5000)],
			'very long segments' => ['/docs/' . str_repeat('a/', 3000) . 'a'],
		];
	}

	#[DataProvider('pathRejectedProvider')]
	public function testPathPlaceholderRejects(string $uri): void {
		$this->assertSame('404 page not found', $this->dispatchRoute('/docs/:path', $uri, ['page']));
	}

	public function testPathPlaceholderMixedWithOtherPlaceholders(): void {
		$this->assertSame(
			'matched:42,a/b/c',
			$this->dispatchRoute('/u/:id/files/:path.zip', '/u/42/files/a/b/c.zip', ['id', 'p'])
		);
		$this->assertSame(
			'matched:42,x',
			$this->dispatchRoute('/u/:id/files/:path.zip', '/u/42/files/x.zip', ['id', 'p'])
		);
		$this->assertSame('404 page not found', $this->dispatchRoute('/u/:id/files/:path.zip', '/u/42/files/a/b/cXzip', ['id', 'p']));
		$this->assertSame('404 page not found', $this->dispatchRoute('/u/:id/files/:path.zip', '/u/0/files/a.zip', ['id', 'p']));
		$this->assertSame(
			'matched:7,a/b',
			$this->dispatchRoute('/:id/:path', '/7/a/b', ['id', 'rest'])
		);
	}

	public function testPathPlaceholderArgPairingByName(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['key:/u/:id/files/:path'] = [
			'route' => '/u/:id/files/:path',
			'handler' => fn($id, $p) => "id=$id;p=$p",
			'arg_names' => ['id', 'p'],
		];
		$_SERVER['REQUEST_URI'] = '/u/9/files/x/y.txt';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		ob_start();
		$this->router->run();
		$this->assertSame('id=9;p=x/y.txt', ob_get_clean());
	}

	public function testPathPlaceholderCompiledHasSingleCaptureGroup(): void {
		$compiled = $this->compile('/docs/:path');
		$this->assertStringStartsWith('#^/docs/', $compiled);
		$this->assertStringEndsWith('$#D', $compiled);
		$this->assertNotSame('#^/docs/:path$#D', $compiled);
		$this->assertSame(1, preg_match($compiled, '/docs/a/b.md', $m));
		$this->assertSame(['/docs/a/b.md', 'a/b.md'], $m);
		$this->assertSame(0, preg_match($compiled, '/docs/a/'));
	}

	public function testPathPlaceholderNoCatastrophicBacktracking(): void {
		$compiled = $this->compile('/docs/:path');
		$junk = [
			'/docs/' . str_repeat('a', 20000) . '!',
			'/docs/' . str_repeat('a/', 10000) . '!',
			'/docs/' . str_repeat('a.', 10000) . '/',
			'/docs/' . str_repeat('a/a.', 5000) . '/',
		];
		foreach ($junk as $uri) {
			$start = microtime(true);
			$result = preg_match($compiled, $uri);
			$elapsed = microtime(true) - $start;
			$this->assertNotFalse($result, 'preg_match error: ' . preg_last_error_msg());
			$this->assertSame(0, $result);
			$this->assertLessThan(1.0, $elapsed);
		}
	}

	#[RequiresFunction('xdebug_get_headers')]
	public function testOptionsPathPlaceholder(): void {
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['key:/docs/:path'] = ['route' => '/docs/:path', 'handler' => fn() => 'x', 'arg_names' => ['p']];
		$this->assertSame('GET, OPTIONS', $this->optionsAllow($routes, '/docs/a/b.md'));
		$this->assertSame('OPTIONS', $this->optionsAllow($routes, '/docs/../x'));
	}
}