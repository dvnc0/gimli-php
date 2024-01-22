<?php
declare(strict_types=1);

namespace Gimli\Session;

interface Session_Interface {
	public function get(string $key): mixed;
	public function set(string $key, mixed $value): void;
	public function delete(string $key): void;
	public function clear(): void;
	public function has(string $key): bool;
}