<?php
declare(strict_types=1);

namespace Gimli\Middleware;

use Gimli\Middleware\Middleware_Response;
use Gimli\Application;
use Gimli\Injector\Injector;

/**
 * Middleware_Base
 *
 * @property Application $Application
 * @property Injector $Injector
 */
abstract class Middleware_Base
{
	/**
	 * @var Application $Application
	 */
	public Application $Application;

	/**
	 * Constructor
	 *
	 * @param Application $Application Application
	 */
	public function __construct(Application $Application) {
		$this->Application = $Application;
	}
	
	/**
	 * Processes the middleware
	 * 
	 * @return Middleware_Response
	 */
	abstract public function process(): Middleware_Response;

	/**
	 * Magic method
	 * 
	 * @param non-empty-string $name property name
	 * 
	 * @return object
	 */
	public function __get(string $name) {
		if (property_exists($this, $name)) {
			return $this->{$name};
		}
		return $this->Application->{$name};
	}
}