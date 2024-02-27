<?php
declare(strict_types=1);

namespace Gimli\Router;

use Gimli\Http\Response;

class Dispatch {

	/**
	 * dispatch changes
	 *
	 * @param Response $Response Response from Controllers
	 * @return void
	 */
	public function dispatch(Response $Response) {
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