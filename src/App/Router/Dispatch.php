<?php
declare(strict_types=1);

namespace Gimli\Router;

class Dispatch
{
	public function dispatch($data) {
		http_response_code($data->response_code);

		if ($data->is_json) {
			header("Content-Type: application/json");
		}

		echo $data->response_body ?? '';
		return;
	}
}