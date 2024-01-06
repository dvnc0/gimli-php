<?php
declare(strict_types=1);

namespace Gimli;

use Gimli\Injector\Injector_Interface;
use Gimli\Http\Request;
use Gimli\Gimli_Container;
use Gimli\Router\Router;
use Gimli\Environment\Config;

/**
 * @property Injector_Interface $Injector
 * @property Router $Router
 * @property Config $Config
 */
class Application {
	/**
	 * @var non-empty-string $app_root
	 */
	protected string $app_root;

	/**
	 * @var Request $Request
	 */
	protected Request $Request;

	/**
	 * @var Gimli_Container $Container
	 */
	protected Gimli_Container $Container;

	/**
	 * @var Config $Config
	 */
	public Config $Config;

	/**
	 * Constructor
	 *
	 * @param non-empty-string $app_root
	 * @param array $server_variables $_SERVER values
	 */
	public function __construct(string $app_root, array $server_variables) {
		$this->app_root = $app_root;
		$this->Request = new Request($server_variables);
		$this->Container = new Gimli_Container($this);
	}

	/**
	 * Magic method to allow access to protected properties
	 *
	 * @param non-empty-string $name
	 * @return mixed
	 */
	public function __get(string $name) {
		if (property_exists($this, $name)) {
			return $this->{$name};
		}
		$name_as_method = 'get' . ucfirst($name);
		return $this->Container->{$name_as_method}();
	}
}