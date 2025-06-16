<?php
declare(strict_types=1);

namespace Gimli\Router;

use Gimli\Http\Response;

class Dispatch {

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

		http_response_code($Response->response_code);

		foreach ($Response->headers as $header) {
			header($header);
		}

		if ($Response->is_json) {
			header("Content-Type: application/json");
		}

		echo $Response->response_body ?? '';
		return;
	}
}