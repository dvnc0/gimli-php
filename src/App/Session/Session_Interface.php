<?php
declare(strict_types=1);

namespace Gimli\Session;

interface Session_Interface {
	/**
	 * get method
	 * 
	 * @param non-empty-string $key key to get
	 * 
	 * @return mixed
	 */
	public function get(string $key): mixed;

	/**
	 * set method
	 * 
	 * @param non-empty-string $key   key to set
	 * @param mixed            $value value to set
	 * 
	 * @return void
	 */
	public function set(string $key, mixed $value): void;

	/**
	 * delete method
	 * 
	 * @param non-empty-string $key key to delete
	 * 
	 * @return void
	 */
	public function delete(string $key): void;

	/**
	 * clear method
	 * 
	 * @return void
	 */
	public function clear(): void;

	/**
	 * has method
	 * 
	 * @param non-empty-string $key key to check for
	 * 
	 * @return bool
	 */
	public function has(string $key): bool;
}