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
}