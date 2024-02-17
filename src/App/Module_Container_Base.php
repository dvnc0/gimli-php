<?php
declare(strict_types=1);
namespace Gimli;

use Gimli\Application;

class Module_Container_Base {
	/**
	 * @var Application $Application
	 */
	protected Application $Application;

	/**
	 * Constructor
	 * 
	 * @param Application $Application Application container
	 */
	public function __construct(Application $Application) {
		$this->Application = $Application;
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
		return $this->{$name_as_method}();
	}

	/**
	 * magic method
	 * 
	 * @param string $name      non-empty-string property name
	 * @param array  $arguments arguments
	 * 
	 * @return null
	 */
	public function __call(string $name, array $arguments) {
		if (method_exists($this, $name)) {
			return $this->{$name}(...$arguments);
		}

		return NULL;
	}
}
