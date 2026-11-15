<?php
declare(strict_types=1);

namespace Gimli\Middleware;

use Gimli\Http\Response;
use InvalidArgumentException;

class Middleware_Response {
	/**
	 * @var bool $success
	 */
	public bool $success;

	/**
	 * @var string $message
	 */
	public string $message;

	/**
	 * @var string $forward
	 */
	public string $forward;

	/**
	 * @var Response|null $response full response to send when the middleware fails
	 */
	public ?Response $response;

	/**
	 * Constructor
	 *
	 * @param boolean       $success  success or fail
	 * @param string        $message  message
	 * @param string        $forward  forward location
	 * @param Response|null $response full response to send on failure
	 */
	public function __construct(bool $success, string $message = '', string $forward = '', ?Response $response = NULL) {
		$this->success  = $success;
		$this->message  = $message;
		$this->forward  = $forward;
		$this->response = $response;
	}

	/**
	 * Fail with a JSON response, e.g. a 401 for an API client
	 *
	 * The body is the standard envelope: {"success": false, "body": $body, "text": $message}.
	 * A 401 should carry a WWW-Authenticate header, pass it in $headers.
	 *
	 * @param array             $body    data for the "body" key
	 * @param string            $message text for the "text" key, also stored as the message
	 * @param int               $status  HTTP status code, 400 to 599
	 * @param array<int,string> $headers raw header lines, e.g. 'WWW-Authenticate: Bearer'
	 *
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public static function json(array $body = [], string $message = 'Unauthorized', int $status = 401, array $headers = []): self {
		self::assertErrorStatus($status);

		$Response = (new Response())->setJsonResponse($body, $message, FALSE, $status);

		foreach ($headers as $header) {
			$Response->setHeader($header);
		}

		return new self(FALSE, $message, '', $Response);
	}

	/**
	 * Fail with a redirect. The URL must be trusted, never pass user input.
	 *
	 * @param string $url    location to redirect to
	 * @param int    $status 3xx status code
	 *
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public static function redirect(string $url, int $status = 302): self {
		if ($status < 300 || $status > 399) {
			throw new InvalidArgumentException("Redirect status must be 3xx, got {$status}");
		}

		if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
			throw new InvalidArgumentException("Redirect URL must not contain control characters");
		}

		$Response = new Response('', FALSE, [], $status);
		$Response->setHeader('Location: ' . $url);

		return new self(FALSE, '', $url, $Response);
	}

	/**
	 * Fail with an arbitrary error response, use redirect() for redirects
	 *
	 * @param Response $response the response to send, status 400 to 599
	 * @param string   $message  message
	 *
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public static function respond(Response $response, string $message = ''): self {
		self::assertErrorStatus($response->response_code);

		return new self(FALSE, $message, '', $response);
	}

	/**
	 * Only 4xx and 5xx are valid for a failing middleware response (redirects have their own factory)
	 *
	 * @param int $status status code
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	protected static function assertErrorStatus(int $status): void {
		if ($status < 400 || $status > 599) {
			throw new InvalidArgumentException("A failing middleware response must have a status of 400 to 599, got {$status}");
		}
	}
}
