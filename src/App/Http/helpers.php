<?php
declare(strict_types=1);

namespace Gimli\Http;

use Gimli\Http\Response;
use Gimli\Application_Registry;

if (!function_exists('Gimli\Http\response')) {
	/**
	 * Returns a response
	 *
	 * @param string $response_body the response body
	 * @param bool   $success       the success status
	 * @param int    $response_code the response code
	 * @param array  $data          the data to set in the response
	 * @return Response
	 */
	function response(string $response_body = '', bool $success = TRUE, int $response_code = 200, array $data = []): Response {
		$Response = Application_Registry::get()->Injector->resolve(Response::class);
		$Response->setResponse(response_body: $response_body, success: $success, response_code: $response_code, data: $data);
		return $Response;
	}
}

if (!function_exists('Gimli\Http\redirect')) {
	/**
	 * Redirects to a URL
	 *
	 * @param string $url           the URL to redirect to
	 * @param int    $response_code the response code
	 * @return Response
	 */
	function redirect(string $url, int $response_code = 302): Response {
		$Response = Application_Registry::get()->Injector->resolve(Response::class);
		$Response->setHeader('Location: ' . $url);
		$Response->setResponse(response_code: $response_code);
		return $Response;
	}
}

if (!function_exists('Gimli\Http\json_response')) {
	/**
	 * Returns a JSON response
	 *
	 * @param array  $body          the body of the response
	 * @param string $message       the message of the response
	 * @param bool   $success       the success status
	 * @param int    $response_code the response code
	 * @return Response
	 */
	function json_response(array $body, string $message = 'OK', bool $success = TRUE, int $response_code = 200): Response {
		$Response = Application_Registry::get()->Injector->resolve(Response::class);
		$Response->setJsonResponse($body, $message, $success, $response_code);
		return $Response;
	}
}

if (!function_exists('Gimli\Http\redirect_on_success')) {
	/**
	 * Redirects to a URL on success
	 *
	 * @param string $url     the URL to redirect to
	 * @param bool   $success the success status
	 * @param string $message the message to set in the response
	 * @return Response
	 */
	function redirect_on_success(string $url, bool $success, string $message = ''): Response {
		$Response      = Application_Registry::get()->Injector->resolve(Response::class);
		$response_code = $success ? 302 : 200;
		if ($success) {
			$Response->setHeader('Location: ' . $url);
		}
		$Response->setResponse(response_body: $message, success: $success, response_code: $response_code);
		return $Response;
	}
}

if (!function_exists('Gimli\Http\redirect_on_failure')) {
	/**
	 * Redirects to a URL on failure
	 *
	 * @param string $url     the URL to redirect to
	 * @param bool   $success the success status
	 * @param string $message the message to set in the response
	 * @return Response
	 */
	function redirect_on_failure(string $url, bool $success, string $message = ''): Response {
		$Response      = Application_Registry::get()->Injector->resolve(Response::class);
		$response_code = $success ? 200 : 302;
		if (!$success) {
			$Response->setHeader('Location: ' . $url);
		}
		$Response->setResponse(response_body: $message, success: $success, response_code: $response_code);
		return $Response;
	}
}