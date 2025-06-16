<?php
declare(strict_types=1);

namespace Gimli\Http;

class Request
{
	/**
	 * @var string $HTTP_HOST
	 */
	public $HTTP_HOST;
	/**
	 * @var string $HTTP_CONNECTION
	 */
	public $HTTP_CONNECTION;
	/**
	 * @var string $HTTP_CACHE_CONTROL
	 */
	public $HTTP_CACHE_CONTROL;
	/**
	 * @var string $HTTP_DNT
	 */
	public $HTTP_DNT;
	/**
	 * @var string $HTTP_UPGRADE_INSECURE_REQUESTS
	 */
	public $HTTP_UPGRADE_INSECURE_REQUESTS;
	/**
	 * @var string $HTTP_USER_AGENT
	 */
	public $HTTP_ACCEPT;
	/**
	 * @var string $HTTP_ACCEPT_ENCODING
	 */
	public $HTTP_ACCEPT_ENCODING;
	/**
	 * @var string $HTTP_ACCEPT_LANGUAGE
	 */
	public $HTTP_ACCEPT_LANGUAGE;
	/**
	 * @var string $HTTP_COOKIE
	 */
	public $HTTP_COOKIE;
	/**
	 * @var string $PATH
	 */
	public $PATH;
	/**
	 * @var string $SERVER_SIGNATURE
	 */
	public $SERVER_SIGNATURE;
	/**
	 * @var string $SERVER_SOFTWARE
	 */
	public $SERVER_SOFTWARE;
	/**
	 * @var string $SERVER_NAME
	 */
	public $SERVER_NAME;
	/**
	 * @var string $SERVER_ADDR
	 */
	public $SERVER_ADDR;
	/**
	 * @var string $SERVER_PORT
	 */
	public $SERVER_PORT;
	/**
	 * @var string $REMOTE_ADDR
	 */
	public $REMOTE_ADDR;
	/**
	 * @var string $DOCUMENT_ROOT
	 */
	public $DOCUMENT_ROOT;
	/**
	 * @var string $REQUEST_SCHEME
	 */
	public $REQUEST_SCHEME;
	/**
	 * @var string $CONTEXT_PREFIX
	 */
	public $CONTEXT_PREFIX;
	/**
	 * @var string $CONTEXT_DOCUMENT_ROOT
	 */
	public $CONTEXT_DOCUMENT_ROOT;
	/**
	 * @var string $SERVER_ADMIN
	 */
	public $SERVER_ADMIN;
	/**
	 * @var string $SCRIPT_FILENAME
	 */
	public $SCRIPT_FILENAME;
	/**
	 * @var string $REMOTE_PORT
	 */
	public $REMOTE_PORT;
	/**
	 * @var string $GATEWAY_INTERFACE
	 */
	public $GATEWAY_INTERFACE;
	/**
	 * @var string $SERVER_PROTOCOL
	 */
	public $SERVER_PROTOCOL;
	/**
	 * @var string $REQUEST_METHOD
	 */
	public $REQUEST_METHOD;
	/**
	 * @var string $QUERY_STRING
	 */
	public $QUERY_STRING;
	/**
	 * @var string $REQUEST_URI
	 */
	public $REQUEST_URI;
	/**
	 * @var string $SCRIPT_NAME
	 */
	public $SCRIPT_NAME;
	/**
	 * @var string $PHP_SELF
	 */
	public $PHP_SELF;
	/**
	 * @var string $REQUEST_TIME_FLOAT
	 */
	public $REQUEST_TIME_FLOAT;
	/**
	 * @var int $REQUEST_TIME
	 */
	public $REQUEST_TIME;
	/**
	 * @var array $argv
	 */
	public $argv;
	/**
	 * @var int $argc
	 */
	public $argc;
	/**
	 * @var array $headers
	 */
	public array $headers;
	/**
	 * @var array $post
	 */
	protected array $post;
	/**
	 * @var array $get
	 */
	protected array $get;
	/**
	 * @var array $route_data
	 */
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
		
		if (PHP_SAPI !== 'cli') {
			$this->headers = getallheaders();
		}

		$this->argc = $server_values['argc'] ?? 0;
		$this->argv = $server_values['argv'] ?? [];
		
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

		$this->post = json_decode(file_get_contents('php://input'), TRUE) ?: [];
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
		return $this->post[$key] ?? NULL;
	}
}