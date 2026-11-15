<?php
declare(strict_types=1);

namespace Gimli\Middleware;

use Gimli\View\Csrf;
use Gimli\Http\Request;
use Gimli\Http\Response;
use Gimli\Middleware\Middleware_Interface;
use Gimli\Middleware\Middleware_Response;

use function Gimli\Http\sanitize_for_log;
use function Gimli\Injector\resolve;

class Csrf_Middleware implements Middleware_Interface
{
	/**
	 * Process the CSRF middleware
	 *
	 * @return Middleware_Response the middleware response
	 */
	public function process(): Middleware_Response {
		$Request = resolve(Request::class);
		
		// Only check CSRF for state-changing methods
		$methods_to_check = ['POST', 'PUT', 'PATCH', 'DELETE'];
		
		if (!in_array($Request->REQUEST_METHOD, $methods_to_check)) {
			return new Middleware_Response(TRUE);
		}
		
		// Skip CSRF for API endpoints (if they use different auth)
		if (str_starts_with($Request->REQUEST_URI, '/api/')) {
			return new Middleware_Response(TRUE);
		}
		
		$post_data = $_POST;
		
		if (!Csrf::validateRequest($post_data)) {
			// Log security event
			error_log("CSRF validation failed for " . sanitize_for_log((string) $Request->REQUEST_URI));
			
			if ($this->expectsJson($Request)) {
				return Middleware_Response::json([], 'CSRF token mismatch', 403, ['Vary: Accept, X-Requested-With']);
			}

			$Response = new Response('CSRF token mismatch', FALSE, [], 403);
			$Response->setHeader('Content-Type: text/plain; charset=UTF-8');
			$Response->setHeader('Vary: Accept, X-Requested-With');

			return Middleware_Response::respond($Response, 'CSRF token mismatch');
		}
		
		return new Middleware_Response(TRUE);
	}

	/**
	 * Does the client look like XHR/fetch wanting JSON?
	 *
	 * @param Request $Request the current request
	 *
	 * @return bool
	 */
	protected function expectsJson(Request $Request): bool {
		if (strtolower((string) $Request->HTTP_X_REQUESTED_WITH) === 'xmlhttprequest') {
			return TRUE;
		}

		return stripos((string) $Request->HTTP_ACCEPT, 'application/json') !== FALSE;
	}
} 