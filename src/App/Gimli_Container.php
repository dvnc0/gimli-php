<?php
declare(strict_types=1);
namespace Gimli;

use Gimli\Application;
use Gimli\Injector\Injector_Interface;
use Gimli\Injector\Injector;
use Gimli\Router\Router;
use Gimli\Environment\Config;
use Gimli\View\View_Engine_Interface;
use Gimli\View\Latte_Engine;

/**
 * @property Injector_Interface $Injector
 * @property Router $Router
 * @property Config $Config
 * @property View_Engine_Interface $View
 */
class Gimli_Container {
	public function __construct(Application $Application) {
		$this->Application = $Application;
	}

	public function getInjector(): Injector_Interface {
		if (!isset($this->Injector)) {
			$this->Injector = new Injector($this->Application);
		}
		return $this->Injector;
	}

	public function getRouter(): Router {
		if (!isset($this->Router)) {
			$this->Router = new Router($this->Application);
		}
		return $this->Router;
	}

	public function __call(string $name, array $arguments) {
		if (method_exists($this, $name)) {
			return $this->{$name}(...$arguments);
		}

		return null;
	}
}