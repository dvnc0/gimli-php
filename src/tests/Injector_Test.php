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

	public function testInjectorRegistersClass() {
		$Application = $this->getApplicationMock();

		$Injector = new Injector($Application);

		$Injector->register('test', new stdClass());

		$this->assertInstanceOf(stdClass::class, $Injector->resolve('test'));
	}

	public function testInjectorRegistersFromConstruct() {
		$Application = $this->getApplicationMock();

		$Injector = new Injector($Application, [
			'test' => new stdClass()
		]);

		$this->assertInstanceOf(stdClass::class, $Injector->resolve('test'));
	}

	public function testANonRegisteredClassIsCorrectlyResolved() {
		$Application = $this->getApplicationMock();

		$Injector = new Injector($Application);

		$Injector->register('test', new stdClass());
		$Injector->register('foo', new stdClass());

		$this->assertInstanceOf(Config::class, $Injector->resolve(Config::class));

		// Make sure it resolves again correctly, coverage this will hit from the resolved classes array
		$this->assertInstanceOf(Config::class, $Injector->resolve(Config::class));
	}

	public function testThatResolveFreshCreatesANewInstanceAndNotFromResolvedArray() {
		$Application = $this->getApplicationMock();

		$Injector = new Injector($Application);

		$Injector->register('test', new stdClass());
		$Injector->register('foo', new stdClass());

		$this->assertInstanceOf(Config::class, $Injector->resolve(Config::class));
		$this->assertInstanceOf(Config::class, $Injector->resolveFresh(Config::class));
	}

	public function testCallMethodResolvesClassAndCallsMethod() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		// Test calling a method without arguments
		$result = $Injector->call(Config::class, 'get', ['template_base_dir']);
		$this->assertIsString($result);
	}

	public function testCallMethodThrowsExceptionForNonExistentMethod() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		$this->expectException(\BadMethodCallException::class);
		$Injector->call(Config::class, 'nonExistentMethod');
	}

	public function testExtendsMethodAllowsExtendingResolvedClass() {
		$Application = $this->getApplicationMock();
		$Injector = new Injector($Application);

		// Test extending a class by adding a property
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

		// Test extending a class without returning a new instance
		$extended = $Injector->extends(Config::class, function($instance) {
			$instance->another_property = 'another_value';
			// Not returning anything - modifying in place
		});

		$this->assertInstanceOf(Config::class, $extended);
		$this->assertEquals('another_value', $extended->another_property);
	}
}