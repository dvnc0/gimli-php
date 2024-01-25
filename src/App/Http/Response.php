<?php
declare(strict_types=1);

namespace Gimli\Http;

class Response
{
	/**
	 * @var bool $success
	 */
	public bool $success;

	/**
	 * @var string $response_body
	 */
	public string $response_body;

	/**
	 * @var int $response_code
	 */
	public int $response_code;

	/**
	 * @var array $data
	 */
	public array $data;

	/**
	 * @var bool $is_json
	 */
	public bool $is_json = FALSE;

	/**
	 * Constructor
	 *
	 * @param string $response_body
	 * @param bool   $success
	 * @param array  $data
	 * @param int    $response_code
	 */
	public function __construct(string $response_body = '', bool $success = TRUE, array $data = [], int $response_code = 200) {
		$this->success       = $success;
		$this->response_body = $response_body;
		$this->response_code = $response_code;
		$this->data          = $data;
	}

	/**
	 * Sets the response body
	 *
	 * @param string $response_body
	 * @param bool   $success
	 * @param array  $data
	 * @param int    $response_code
	 * @return Response
	 */
	public function setResponse(string $response_body, bool $success = TRUE, array $data = [], int $response_code = 200) {
		$this->success       = $success;
		$this->response_body = $response_body;
		$this->response_code = $response_code;
		$this->data          = $data;
		return $this;
	}

	/**
	 * Sets the response body as JSON
	 *
	 * @param string $response_body
	 * @param bool   $success
	 * @param array  $data
	 * @param int    $response_code
	 * @return Response
	 */
	public function setJsonResponse(string $response_body, bool $success = TRUE, array $data = [], int $response_code = 200) {
		$this->is_json       = TRUE;
		$this->success       = $success;
		$this->response_body = json_encode(['success' => $success, 'body' => $response_body, 'data' => $data]);
		$this->response_code = $response_code;
		$this->data          = $data;
		return $this;
	}
}