<?php
declare(strict_types=1);

namespace Gimli\Http;

class Response
{
	public bool $success;
	public string $response_body;
	public int $response_code;
	public array $data;
	public bool $is_json = false;

	public function __construct(string $response_body = '', bool $success = TRUE, array $data = [], int $response_code = 200) {
		$this->success       = $success;
		$this->response_body = $response_body;
		$this->response_code = $response_code;
		$this->data = $data;
	}

	public function setResponse(string $response_body, bool $success = TRUE, array $data = [], int $response_code = 200) {
		$this->success       = $success;
		$this->response_body = $response_body;
		$this->response_code = $response_code;
		$this->data = $data;
		return $this;
	}

	public function setJsonResponse(string $response_body, bool $success = TRUE, array $data = [], int $response_code = 200) {
		$this->is_json = true;
		$this->success       = $success;
		$this->response_body = json_encode(['success' => $success, 'body' => $response_body, 'data' => $data]);
		$this->response_code = $response_code;
		$this->data = $data;
		return $this;
	}
}