<?php
declare(strict_types=1);

namespace Gimli\Http;

class Request
{
	public $HTTP_HOST;
	public $HTTP_CONNECTION;
	public $HTTP_CACHE_CONTROL;
	public $HTTP_DNT;
	public $HTTP_UPGRADE_INSECURE_REQUESTS;
	public $HTTP_USER_AGENT;
	public $HTTP_ACCEPT;
	public $HTTP_ACCEPT_ENCODING;
	public $HTTP_ACCEPT_LANGUAGE;
	public $HTTP_COOKIE;
	public $PATH;
	public $SERVER_SIGNATURE;
	public $SERVER_SOFTWARE;
	public $SERVER_NAME;
	public $SERVER_ADDR;
	public $SERVER_PORT;
	public $REMOTE_ADDR;
	public $DOCUMENT_ROOT;
	public $REQUEST_SCHEME;
	public $CONTEXT_PREFIX;
	public $CONTEXT_DOCUMENT_ROOT;
	public $SERVER_ADMIN;
	public $SCRIPT_FILENAME;
	public $REMOTE_PORT;
	public $GATEWAY_INTERFACE;
	public $SERVER_PROTOCOL;
	public $REQUEST_METHOD;
	public $QUERY_STRING;
	public $REQUEST_URI;
	public $SCRIPT_NAME;
	public $PHP_SELF;
	public $REQUEST_TIME_FLOAT;
	public $REQUEST_TIME;
	public $argv;
	public $argc;
	public array $headers;
	protected array $post;
	protected array $get;
	public array $route_data = [];

	/**
	 * Constructor
	 *
	 * @param array $server_values $_SERVER values
	 */
	public function __construct(array $server_values) {
		foreach (get_class_vars(get_class($this)) as $key => $value) {
			if (!empty($server_values[$key])) {
				$this->{$key} = $server_values[$key];
			}
		} 

		if (!empty($this->REQUEST_METHOD) && in_array(strtoupper($this->REQUEST_METHOD), ['POST', 'PUT', 'PATCH'])) {
			$this->createPostData();
		}

		if (!empty($this->REQUEST_METHOD) && strtoupper($this->REQUEST_METHOD) === 'GET') {
			$this->get = $_GET;
		}
		
		$this->headers = getallheaders();
	}

	/**
	 * Get a query parameter
	 *
	 * @param string $key The key of the query parameter
	 * @return mixed
	 */
	public function getQueryParam(string $key) {
		if (isset($this->get[$key])) {
			return $this->get[$key];
		}

		return NULL;
	}

	/**
	 * Get all query parameters
	 *
	 * @return array|null
	 */
	public function getQueryParams(): array|null {
		return empty($this->get) ? NULL : $this->get;
	}

	/**
	 * Create the post data values from $_POST or php://input
	 *
	 * @return void
	 */
	protected function createPostData(): void {
		if (!empty($_POST)) {
			$this->post = $_POST;
			return;
		}

		$this->post = json_decode(file_get_contents('php://input'), true) ?: [];
	}

	/**
	 * Get all post parameters
	 *
	 * @return array
	 */
	public function getPostParams(): array {
		return $this->post;
	}

	/**
	 * Get a post parameter
	 *
	 * @param string $key The key of the post parameter
	 * @return mixed
	 */
	public function getPostParam($key) {
		return $this->post[$key] ?: null;
	}
}