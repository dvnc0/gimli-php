<?php
declare(strict_types=1);

namespace Gimli;

use Gimli\Injector\Injector_Interface;
use Gimli\Http\Request;
use Gimli\Gimli_Container;
use Gimli\Router\Router;
use Gimli\Environment\Config;
use Gimli\View\View_Engine_Interface;
use Gimli\Session\Session_Interface;
use Gimli\Environment\Container_Base;

/**
 * @property Injector_Interface $Injector
 * @property Router $Router
 * @property Config $Config
 * @property View_Engine_Interface $View
 * @property Session_Interface $Session
 * @property Container_Base $Module
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
	 * @var View_Engine_Interface $View
	 */
	public View_Engine_Interface $View;

	/**
	 * @var Session_Interface $Session
	 */
	public Session_Interface $Session;

	/**
	 * @var Container_Base $Module
	 */
	public Container_Base $Module;

	/**
	 * Constructor
	 *
	 * @param non-empty-string $app_root         The application root path
	 * @param array            $server_variables $_SERVER values
	 */
	public function __construct(string $app_root, array $server_variables) {
		$this->app_root  = $app_root;
		$this->Request   = new Request($server_variables);
		$this->Container = new Gimli_Container($this);
	}

	/**
	 * Magic method
	 *
	 * @param non-empty-string $name property name
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