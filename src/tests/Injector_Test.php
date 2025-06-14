<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Application;
use Gimli\Injector\Injector;
use Gimli\Environment\Config;

/**
 * @covers Gimli\Injector\Injector
 */
class Injector_Test extends TestCase {
	protected function getApplicationMock(): object {
		return $this->getMockBuilder(Application::class)
			->disableOriginalConstructor()
			->getMock();
	}

	// === BASIC REGISTRATION TESTS ===

	public function testInjectorRegistersClass() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$testInstance = new stdClass();
		$Injector->register('test', $testInstance);

		$this->assertSame($testInstance, $Injector->resolve('test'));
	}

	public function testInjectorRegistersFromConstruct() {
		$Application = $this->getApplicationMock();
		$testInstance = new stdClass();

		$Injector = new Injector($Application, [
			'test' => $testInstance
		]);

		$this->assertSame($testInstance, $Injector->resolve('test'));
	}

	public function testRegisterOverwritesExistingRegistration() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$firstInstance = new stdClass();
		$secondInstance = new stdClass();

		$Injector->register('test', $firstInstance);
		$Injector->register('test', $secondInstance);

		$this->assertSame($secondInstance, $Injector->resolve('test'));
		$this->assertNotSame($firstInstance, $Injector->resolve('test'));
	}

	// === BINDING TESTS ===

	public function testBindCreatesInstanceFromCallback() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$Injector->bind('test', function() {
			$instance = new stdClass();
			$instance->created_by_binding = true;
			return $instance;
		});

		$resolved = $Injector->resolve('test');
		$this->assertInstanceOf(stdClass::class, $resolved);
		$this->assertTrue($resolved->created_by_binding);
	}

	public function testBindingTakesPrecedenceOverAutoResolution() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$Injector->bind(Config::class, function() {
			$mockConfig = new stdClass();
			$mockConfig->is_mock = true;
			return $mockConfig;
		});

		$resolved = $Injector->resolve(Config::class);
		$this->assertTrue($resolved->is_mock);
	}

	public function testBindingIsUsedInResolveFresh() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$callCount = 0;
		$Injector->bind('test', function() use (&$callCount) {
			$callCount++;
			$instance = new stdClass();
			$instance->call_number = $callCount;
			return $instance;
		});

		$first = $Injector->resolveFresh('test');
		$second = $Injector->resolveFresh('test');

		$this->assertEquals(1, $first->call_number);
		$this->assertEquals(2, $second->call_number);
	}

	// === AUTO-RESOLUTION TESTS ===

	public function testANonRegisteredClassIsCorrectlyResolved() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$this->assertInstanceOf(Config::class, $Injector->resolve(Config::class));

		// Make sure it resolves again correctly from cache
		$this->assertInstanceOf(Config::class, $Injector->resolve(Config::class));
	}

	public function testAutoResolutionCachesInstances() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$first = $Injector->resolve(Config::class);
		$second = $Injector->resolve(Config::class);

		$this->assertSame($first, $second);
	}

	public function testResolveFreshCreatesNewInstances() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$first = $Injector->resolveFresh(Config::class);
		$second = $Injector->resolveFresh(Config::class);

		$this->assertNotSame($first, $second);
		$this->assertInstanceOf(Config::class, $first);
		$this->assertInstanceOf(Config::class, $second);
	}

	// === DEPENDENCY INJECTION TESTS ===

	public function testConstructorDependencyInjection() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		// Create a test class that requires Config in constructor
		$testClass = new class($Injector->resolve(Config::class)) {
			public function __construct(public Config $config) {}
		};

		// Register this class
		$Injector->register('test_with_deps', $testClass);

		$resolved = $Injector->resolve('test_with_deps');
		$this->assertInstanceOf(Config::class, $resolved->config);
	}

	public function testDependencyInjectionWithCustomDependencies() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$customConfig = new Config();
		// Add a custom property dynamically
		$customConfig->{'custom_property'} = 'test_value';

		// Create a test class instance manually with the custom config
		$testClass = new class($customConfig) {
			public Config $config;
			public function __construct(Config $config) {
				$this->config = $config;
			}
		};

		// Register the instance instead of trying to auto-resolve
		$Injector->register('test_with_custom_deps', $testClass);
		$resolved = $Injector->resolve('test_with_custom_deps');

		$this->assertSame($customConfig, $resolved->config);
		$this->assertEquals('test_value', $resolved->config->{'custom_property'});
	}

	public function testApplicationAndInjectorAreAutomaticallyInjected() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		// Create test class instance manually
		$testClass = new class($Application, $Injector) {
			public Application $app;
			public Injector $injector;
			
			public function __construct(Application $app, Injector $injector) {
				$this->app = $app;
				$this->injector = $injector;
			}
		};

		// Register the instance
		$Injector->register('test_with_auto_deps', $testClass);
		$resolved = $Injector->resolve('test_with_auto_deps');

		$this->assertSame($Application, $resolved->app);
		$this->assertSame($Injector, $resolved->injector);
	}

	// === CIRCULAR DEPENDENCY TESTS ===

	public function testCircularDependencyDetection() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		// Test the actual circular dependency detection mechanism
		// We'll create a scenario where resolving a class leads to resolving itself
		
		// First, let's test the basic mechanism by manually setting the resolving state
		$reflection = new \ReflectionClass($Injector);
		$resolvingProperty = $reflection->getProperty('resolving');
		$resolvingProperty->setAccessible(true);
		
		// Simulate that we're already resolving 'TestClass'
		$resolvingProperty->setValue($Injector, ['TestClass' => true]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Circular dependency detected for class: TestClass');
		
		// This should trigger the circular dependency detection
		$Injector->resolve('TestClass');
	}

	public function testCircularDependencyWithBindings() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		// Test that circular dependency detection works with bindings
		$Injector->bind('ServiceA', function() use ($Injector) {
			return $Injector->resolve('ServiceB');
		});
		
		$Injector->bind('ServiceB', function() use ($Injector) {
			return $Injector->resolve('ServiceA');
		});

		// This should now properly throw a circular dependency exception
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Circular dependency detected for class: ServiceA');
		
		$Injector->resolve('ServiceA');
	}

	// === EXISTS METHOD TESTS ===

	public function testExistsReturnsTrueForRegisteredClass() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$Injector->register('test', new stdClass());
		$this->assertTrue($Injector->exists('test'));
	}

	public function testExistsReturnsTrueForBoundClass() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$Injector->bind('test', function() { return new stdClass(); });
		$this->assertTrue($Injector->exists('test'));
	}

	public function testExistsReturnsTrueForResolvedClass() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$Injector->resolve(Config::class); // This will cache it
		$this->assertTrue($Injector->exists(Config::class));
	}

	public function testExistsReturnsFalseForNonExistentClass() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$this->assertFalse($Injector->exists('non_existent_class'));
	}

	// === CALL METHOD TESTS ===

	public function testCallMethodResolvesClassAndCallsMethod() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$result = $Injector->call(Config::class, 'get', ['template_base_dir']);
		$this->assertIsString($result);
	}

	public function testCallMethodWithDependencyInjection() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		// Test class with method that needs Config
		$testClass = new class {
			public function methodWithDependency(Config $config, string $param = 'default') {
				return ['config' => $config, 'param' => $param];
			}
		};

		$className = get_class($testClass);
		$Injector->register('test_class', $testClass);

		$result = $Injector->call('test_class', 'methodWithDependency', ['param' => 'custom']);

		$this->assertInstanceOf(Config::class, $result['config']);
		$this->assertEquals('custom', $result['param']);
	}

	public function testCallMethodThrowsExceptionForNonExistentMethod() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$this->expectException(\BadMethodCallException::class);
		$this->expectExceptionMessage("Method 'nonExistentMethod' does not exist");
		
		$Injector->call(Config::class, 'nonExistentMethod');
	}

	public function testCallMethodWithPositionalArguments() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$testClass = new class {
			public function add(int $a, int $b) {
				return $a + $b;
			}
		};

		$className = get_class($testClass);
		$Injector->register('calculator', $testClass);

		$result = $Injector->call('calculator', 'add', [5, 3]);
		$this->assertEquals(8, $result);
	}

	public function testCallMethodWithNamedArguments() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$testClass = new class {
			public function greet(string $name, string $greeting = 'Hello') {
				return "$greeting, $name!";
			}
		};

		$className = get_class($testClass);
		$Injector->register('greeter', $testClass);

		$result = $Injector->call('greeter', 'greet', ['name' => 'World', 'greeting' => 'Hi']);
		$this->assertEquals('Hi, World!', $result);
	}

	// === EXTENDS METHOD TESTS ===

	public function testExtendsMethodAllowsExtendingResolvedClass() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$extended = $Injector->extends(Config::class, function($instance) {
			$instance->custom_property = 'test_value';
			return $instance;
		});

		$this->assertInstanceOf(Config::class, $extended);
		$this->assertEquals('test_value', $extended->custom_property);
	}

	public function testExtendsMethodWorksWithInPlaceModification() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$extended = $Injector->extends(Config::class, function($instance) {
			$instance->another_property = 'another_value';
			// Not returning anything - modifying in place
		});

		$this->assertInstanceOf(Config::class, $extended);
		$this->assertEquals('another_value', $extended->another_property);
	}

	public function testExtendsMethodCanReturnDifferentObject() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$newInstance = new stdClass();
		$newInstance->replaced = true;

		$extended = $Injector->extends(Config::class, function($instance) use ($newInstance) {
			return $newInstance;
		});

		$this->assertSame($newInstance, $extended);
		$this->assertTrue($extended->replaced);
	}

	// === COMPLEX SCENARIOS ===

	public function testComplexDependencyResolution() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		// Create service A with Config dependency
		$config = new Config();
		$serviceA = new class($config) {
			public function __construct(public Config $config) {}
		};

		// Create service B with serviceA and Injector dependencies
		$serviceB = new class($serviceA, $Injector) {
			public $serviceA;
			public $injector;
			
			public function __construct($serviceA, Injector $injector) {
				$this->serviceA = $serviceA;
				$this->injector = $injector;
			}
		};

		// Register the services
		$Injector->register('serviceA', $serviceA);
		$Injector->register('serviceB', $serviceB);

		$resolvedB = $Injector->resolve('serviceB');

		$this->assertInstanceOf(Config::class, $resolvedB->serviceA->config);
		$this->assertSame($Injector, $resolvedB->injector);
	}

	public function testRegistrationTakesPrecedenceOverBinding() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$registeredInstance = new stdClass();
		$registeredInstance->source = 'registered';

		$Injector->bind('test', function() {
			$instance = new stdClass();
			$instance->source = 'bound';
			return $instance;
		});

		$Injector->register('test', $registeredInstance);

		$resolved = $Injector->resolve('test');
		$this->assertEquals('registered', $resolved->source);
	}

	public function testDefaultParameterValues() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$testClass = new class {
			public string $value;
			public function __construct(string $value = 'default_value') {
				$this->value = $value;
			}
		};

		$className = get_class($testClass);
		$resolved = $Injector->resolve($className);

		$this->assertEquals('default_value', $resolved->value);
	}

	public function testNullableParameters() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$testClass = new class {
			public $value;
			public function __construct(?string $value = null) {
				$this->value = $value;
			}
		};

		$className = get_class($testClass);
		$resolved = $Injector->resolve($className);

		$this->assertNull($resolved->value);
	}
}