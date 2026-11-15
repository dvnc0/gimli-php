<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Gimli\Middleware\Middleware_Response;
use Gimli\Http\Response;

class Middleware_Response_Test extends TestCase {

	public function testConstructorDefaults(): void {
		$r = new Middleware_Response(true);
		$this->assertTrue($r->success);
		$this->assertSame('', $r->message);
		$this->assertSame('', $r->forward);
		$this->assertNull($r->response);
	}

	public function testConstructorStoresAllFields(): void {
		$resp = new Response();
		$r = new Middleware_Response(false, 'm', '/f', $resp);
		$this->assertFalse($r->success);
		$this->assertSame('m', $r->message);
		$this->assertSame('/f', $r->forward);
		$this->assertSame($resp, $r->response);
	}

	public function testJsonDefaults(): void {
		$r = Middleware_Response::json();
		$this->assertFalse($r->success);
		$this->assertSame('Unauthorized', $r->message);
		$this->assertInstanceOf(Response::class, $r->response);
		$this->assertTrue($r->response->is_json);
		$this->assertSame(401, $r->response->response_code);
		$this->assertSame(
			['success' => false, 'body' => [], 'text' => 'Unauthorized'],
			json_decode($r->response->response_body, true)
		);
	}

	public function testJsonCustom(): void {
		$r = Middleware_Response::json(['k' => 'v'], 'Slow down', 429, ['Retry-After: 30']);
		$this->assertSame('Slow down', $r->message);
		$this->assertSame(429, $r->response->response_code);
		$this->assertContains('Retry-After: 30', $r->response->headers);
		$this->assertSame('Slow down', json_decode($r->response->response_body, true)['text']);
	}

	public function testJsonInvalidUtf8DoesNotThrow(): void {
		$r = Middleware_Response::json(['bad' => "\xB1\x31"]);
		$this->assertNotFalse(json_decode($r->response->response_body, true));
		$this->assertNotSame('', $r->response->response_body);
	}

	public function testJsonReturnsFreshResponseEachTime(): void {
		$a = Middleware_Response::json();
		$b = Middleware_Response::json();
		$this->assertNotSame($a->response, $b->response);
	}

	public function testRedirectDefault(): void {
		$r = Middleware_Response::redirect('/login');
		$this->assertFalse($r->success);
		$this->assertSame('/login', $r->forward);
		$this->assertSame(302, $r->response->response_code);
		$this->assertContains('Location: /login', $r->response->headers);
	}

	public function testRedirectCustomStatus(): void {
		$r = Middleware_Response::redirect('/x', 301);
		$this->assertSame(301, $r->response->response_code);
	}

	public function testRedirectBoundaries(): void {
		$this->assertSame(300, Middleware_Response::redirect('/x', 300)->response->response_code);
		$this->assertSame(399, Middleware_Response::redirect('/x', 399)->response->response_code);
	}

	public static function badRedirectStatuses(): array {
		return [[200], [299], [400], [0], [-1], [400]];
	}

	#[DataProvider('badRedirectStatuses')]
	public function testRedirectNon3xxThrows(int $code): void {
		$this->expectException(\InvalidArgumentException::class);
		Middleware_Response::redirect('/x', $code);
	}

	public static function badRedirectUrls(): array {
		return [["/a\nb"], ["/a\rb"], ["/a\tb"], ["/a\x00b"], ["/a\x7fb"]];
	}

	#[DataProvider('badRedirectUrls')]
	public function testRedirectRejectsControlCharacters(string $url): void {
		$this->expectException(\InvalidArgumentException::class);
		Middleware_Response::redirect($url);
	}

	public static function badFailureStatuses(): array {
		return [[200], [299], [300], [399], [600], [999]];
	}

	public static function goodFailureStatuses(): array {
		return [[400], [401], [403], [599]];
	}

	#[DataProvider('badFailureStatuses')]
	public function testJsonRejectsOutOfRangeStatus(int $code): void {
		$this->expectException(\InvalidArgumentException::class);
		Middleware_Response::json([], 'x', $code);
	}

	#[DataProvider('badFailureStatuses')]
	public function testRespondRejectsOutOfRangeStatus(int $code): void {
		$this->expectException(\InvalidArgumentException::class);
		Middleware_Response::respond((new Response())->setResponse('x', false, [], $code));
	}

	#[DataProvider('goodFailureStatuses')]
	public function testJsonAcceptsErrorStatus(int $code): void {
		$this->assertSame($code, Middleware_Response::json([], 'x', $code)->response->response_code);
	}

	#[DataProvider('goodFailureStatuses')]
	public function testRespondAcceptsErrorStatus(int $code): void {
		$r = Middleware_Response::respond((new Response())->setResponse('x', false, [], $code));
		$this->assertSame($code, $r->response->response_code);
	}

	public function testRespondWrapsResponse(): void {
		$resp = (new Response())->setResponse('no', false, [], 403);
		$r = Middleware_Response::respond($resp, 'denied');
		$this->assertFalse($r->success);
		$this->assertSame('denied', $r->message);
		$this->assertSame($resp, $r->response);
	}

	public function testJsonNanFailsLoudly(): void {
		$this->expectException(\TypeError::class);
		Middleware_Response::json(['n' => NAN]);
	}

	public function testSetJsonResponseNanFailsLoudly(): void {
		$this->expectException(\TypeError::class);
		(new Response())->setJsonResponse(['n' => NAN]);
	}

	public function testSetJsonResponseSubstitutesInvalidUtf8(): void {
		$r = (new Response())->setJsonResponse(['bad' => "a\xB1b"]);
		$decoded = json_decode($r->response_body, true);
		$this->assertSame("a\u{FFFD}b", $decoded['body']['bad']);
	}

	public function testJsonSubstitutesInvalidUtf8(): void {
		$r = Middleware_Response::json(['bad' => "a\xB1b"]);
		$this->assertSame("a\u{FFFD}b", json_decode($r->response->response_body, true)['body']['bad']);
	}
}
