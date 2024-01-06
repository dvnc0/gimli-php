<?php
declare(strict_types=1);

namespace Gimli\Injector;

interface Injector_Interface {
	public function register(string $class_name, object $instance);
	public function resolve(string $class_name, array $dependencies = []): object;
	public function resolveFresh(string $class_name, array $dependencies = []): object;
}