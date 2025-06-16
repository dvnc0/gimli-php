<?php
declare(strict_types=1);

namespace Gimli\Middleware;

use Gimli\View\Csrf;
use Gimli\Http\Request;
use Gimli\Middleware\Middleware_Interface;
use Gimli\Middleware\Middleware_Response;

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
			error_log("CSRF validation failed for " . $Request->REQUEST_URI);
			
			return new Middleware_Response(FALSE, '/error/csrf');
		}
		
		return new Middleware_Response(TRUE);
	}
} 