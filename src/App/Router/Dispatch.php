<?php
declare(strict_types=1);

namespace Gimli\Router;

use Gimli\Http\Response;

class Dispatch {

	/**
	 * Header names that may legitimately repeat, these are appended instead of replaced
	 *
	 * @var array<int, string> $append_headers
	 */
	protected array $append_headers = ['set-cookie', 'www-authenticate', 'vary'];

	/**
	 * dispatch changes
	 *
	 * @param Response $Response Response from Controllers
	 * @param bool     $is_cli   If the request is from CLI
	 * @return void
	 */
	public function dispatch(Response $Response, bool $is_cli = FALSE): void {
		if ($is_cli === TRUE) {
			echo $Response->response_body ?? '';
			return;
		}

		if (headers_sent()) {
			error_log("Response headers and status not sent, output has already started");
			echo $Response->response_body ?? '';
			return;
		}

		// A plain 200 is set first so PHP can still turn it into a 302 when a Location header follows
		if ($Response->response_code === 200) {
			http_response_code(200);
		}

		$has_content_type = $this->sendHeaders($Response);

		// Any other code is set after the headers so PHP does not rewrite it
		if ($Response->response_code !== 200) {
			http_response_code($Response->response_code);
		}

		if ($Response->is_json && $has_content_type === FALSE) {
			header("Content-Type: application/json");
		}

		echo $Response->response_body ?? '';
		return;
	}

	/**
	 * Send the response headers
	 *
	 * @param Response $Response Response from Controllers
	 * @return bool Whether the response set its own Content-Type
	 */
	protected function sendHeaders(Response $Response): bool {
		$has_content_type = FALSE;

		foreach ($Response->headers as $header) {
			$name = '';

			if (preg_match('/^([A-Za-z0-9-]+):/', $header, $matches) === 1) {
				$name = strtolower($matches[1]);
			}

			if ($name === 'content-type') {
				$has_content_type = TRUE;
			}

			header($header, !in_array($name, $this->append_headers, TRUE));
		}

		return $has_content_type;
	}
}
