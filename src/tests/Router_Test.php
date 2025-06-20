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
		
		$this->router = new Router($mockApp);
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

	public function testMiddlewareFailureRedirect(): void {
		$this->createMockMiddleware(false, '/login');
		
		$routes = $this->getBasicRouteStructure();
		$routes['GET']['/protected'] = [
			'route' => '/protected',
			'handler' => fn() => 'Protected Content',
			'middleware' => ['TestMiddleware']
		];
		
		$_SERVER['REQUEST_URI'] = '/protected';
		$this->router->Request = new Request($_SERVER);
		$this->router->addRoutes($routes);
		
		// Mock header function is complex, so we'll just verify no output
		ob_start();
		$this->router->run();
		$output = ob_get_clean();
		
		$this->assertEquals('', $output); // Should not output content due to redirect
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
} 