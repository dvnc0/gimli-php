<?php
declare(strict_types=1);

namespace Gimli\Http;

use Gimli\Http\Response;
use Gimli\Application;

if (!function_exists('Gimli\Http\response')) {
	/**
	 * Returns a response
	 *
	 * @param string $response_body
	 * @param bool $success
	 * @param int $response_code
	 * @return Response
	 */
	function response(string $response_body = '', bool $success = TRUE, int $response_code = 200, array $data = []): Response {
		$Response = Application::get()->Injector->resolve(Response::class);
		$Response->setResponse(response_body: $response_body, success: $success, response_code: $response_code, data: $data);
		return $Response;
	}
}

if (!function_exists('Gimli\Http\redirect')) {
	/**
	 * Redirects to a URL
	 *
	 * @param string $url
	 * @param int $response_code
	 * @return Response
	 */
	function redirect(string $url, int $response_code = 302): Response {
		$Response = Application::get()->Injector->resolve(Response::class);
		$Response->setHeader('Location: ' . $url);
		$Response->setResponse(response_code: $response_code);
		return $Response;
	}
}

if (!function_exists('Gimli\Http\json_response')) {
	/**
	 * Returns a JSON response
	 *
	 * @param array $body
	 * @param string $message
	 * @param bool $success
	 * @param int $response_code
	 * @return Response
	 */
	function json_response(array $body, string $message = 'OK', bool $success = TRUE, int $response_code = 200): Response {
		$Response = Application::get()->Injector->resolve(Response::class);
		$Response->setJsonResponse($body, $message, $success, $response_code);
		return $Response;
	}
}

if (!function_exists('Gimli\Http\redirect_on_success')) {
	/**
	 * Redirects to a URL on success
	 *
	 * @param string $url
	 * @param bool $success
	 * @param string $message
	 * @return Response
	 */
	function redirect_on_success(string $url, bool $success, string $message = ''): Response {
		$Response = Application::get()->Injector->resolve(Response::class);
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
	 * @param string $url
	 * @param bool $success
	 * @param string $message
	 * @return Response
	 */
	function redirect_on_failure(string $url, bool $success, string $message = ''): Response {
		$Response = Application::get()->Injector->resolve(Response::class);
		$response_code = $success ? 200 : 302;
		if (!$success) {
			$Response->setHeader('Location: ' . $url);
		}
		$Response->setResponse(response_body: $message, success: $success, response_code: $response_code);
		return $Response;
	}
}