<?php
declare(strict_types=1);

namespace Gimli\Middleware;

class Middleware_Response {
	/**
	 * @var bool $success
	 */
	public bool $success;

	/**
	 * @var string $message
	 */
	public string $message;

	/**
	 * @var string $forward
	 */
	public string $forward;

	/**
	 * Constructor
	 *
	 * @param boolean $success success or fail
	 * @param string  $message message
	 * @param string  $forward forward location
	 */
	public function __construct(bool $success, string $message = '', string $forward = '') {
		$this->success = $success;
		$this->message = $message;
		$this->forward = $forward;
	}
}