<?php
declare(strict_types=1);

namespace Gimli\Middleware;

use Gimli\Middleware\Middleware_Response;

interface Middleware_Interface {
	/**
	 * Processes the middleware
	 * 
	 * @return Middleware_Response the middleware response
	 */
	public function process(): Middleware_Response;
}