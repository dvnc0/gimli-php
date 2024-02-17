<?php
declare(strict_types=1);

namespace Gimli\Environment;

use Gimli\Application;

class Container_Base {

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
	 * Magic method
	 *
	 * @param non-empty-string $name property name
	 * @return mixed
	 */
	public function __get(string $name) {
		if (property_exists($this, $name)) {
			return $this->{$name};
		}
		$name_as_method = ucfirst($name);
		return $this->{$name_as_method}();
	}

}