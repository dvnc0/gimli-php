<?php
declare(strict_types=1);

namespace Gimli\Injector;

use ReflectionClass;
use Gimli\Application;
use Gimli\Injector\Injector_Interface;

class Injector implements Injector_Interface {
	
	/**
	 * @var array $resolved_classes
	 */
	protected array $resolved_classes = [];

	/**
	 * @var array $registered_classes
	 */
	protected array $registered_classes = [];

	/**
	 * @var Application $Application
	 */
	protected Application $Application;

	/**
	 * Constructor
	 *
	 * @param Application $Application        Application
	 * @param array       $registered_classes registered classes
	 */
	public function __construct(Application $Application, array $registered_classes = []) {
		$this->registered_classes = $registered_classes;
		$this->Application        = $Application;
	}

	/**
	 * Registers a class instance
	 *
	 * @param string $class_name class name
	 * @param object $instance   instance
	 * @return void
	 */
	public function register(string $class_name, object $instance) {
		$this->registered_classes[$class_name] = $instance;
	}

	/**
	 * Resolves a class instance
	 *
	 * @param string $class_name   class name
	 * @param array  $dependencies dependencies
	 * @return object
	 */
	public function resolve(string $class_name, array $dependencies = []): object {
		if (!empty($this->resolved_classes[$class_name])) {
			return $this->resolved_classes[$class_name];
		}

		if (!empty($this->registered_classes[$class_name])) {
			return $this->registered_classes[$class_name];
		}

		return $this->createFreshInstance($class_name, $dependencies);
	}

	/**
	 * Resolves a fresh class instance
	 *
	 * @param string $class_name   class name
	 * @param array  $dependencies dependencies
	 * @return object
	 */
	public function resolveFresh(string $class_name, array $dependencies = []): object {
		return $this->createFreshInstance($class_name, $dependencies);
	}

	/**
	 * Creates a fresh class instance
	 *
	 * @param string $class_name   class name
	 * @param array  $dependencies dependencies
	 * @return object
	 */
	protected function createFreshInstance(string $class_name, array $dependencies): object {
		$dependencies[Application::class] = $this->Application;
		$dependencies[Injector::class]    = $this;

		$reflection  = new ReflectionClass($class_name);
		$constructor = $reflection->getConstructor();

		if (empty($constructor)) {
			$instance                            = new $class_name();
			$this->resolved_classes[$class_name] = $instance;

			return $instance;
		}

		$constructor_params = $constructor->getParameters();
		$constructor_args   = [];

		foreach ($constructor_params as $param) {
			$param_type  = $param->getType();
			$param_class = $param_type === NULL ? '' : $param_type->getName(); // @phpstan-ignore-line
			$param_name  = $param->getName();

			if (!empty($param_class)) {
				if (!empty($dependencies[$param_class])) {
					$constructor_args[] = $dependencies[$param_class];
					continue;
				} 
				
				if (class_exists($param_class)) {
					$constructor_args[] = $this->resolve($param_class);
					continue;
				}
			}

			if (!empty($param_name) && !empty($dependencies[$param_name])) {
				$constructor_args[] = $dependencies[$param_name];
				continue;
			}

			if ($param->isDefaultValueAvailable()) {
				$constructor_args[] = $param->getDefaultValue();
				continue;
			}

			if ($param->allowsNull()) {
				$constructor_args[] = NULL;
				continue;
			}

			continue;
		}

		$instance = $reflection->newInstanceArgs($constructor_args);

		$this->resolved_classes[$class_name] = $instance;

		return $instance;
			
	}

}