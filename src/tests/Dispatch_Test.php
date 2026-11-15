<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Router\Dispatch;
use Gimli\Http\Response;

/**
 * @covers Gimli\Router\Dispatch
 */
class Dispatch_Test extends TestCase {
    
    private Dispatch $dispatch;
    
    protected function setUp(): void {
        $this->dispatch = new Dispatch();
    }
    
    public function testDispatchBasicResponse() {
        $response = new Response();
        $response->setResponse('Hello World');
        
        // Capture output
        ob_start();
        $this->dispatch->dispatch($response);
        $output = ob_get_clean();
        
        $this->assertEquals('Hello World', $output);
    }
    
    public function testDispatchWithCustomStatusCode() {
        $response = new Response();
        $response->setResponse('Created', true, [], 201);
        
        // We can't easily test http_response_code() in unit tests,
        // but we can test that the method doesn't throw exceptions
        ob_start();
        $this->dispatch->dispatch($response);
        $output = ob_get_clean();
        
        $this->assertEquals('Created', $output);
    }
    
    public function testDispatchWithHeaders() {
        $response = new Response();
        $response->setResponse('Content with headers')
                 ->setHeader('X-Custom-Header: test-value')
                 ->setHeader('X-Another-Header: another-value');
        
        // We can't easily test header() calls in unit tests,
        // but we can verify the dispatch doesn't throw exceptions
        ob_start();
        $this->dispatch->dispatch($response);
        $output = ob_get_clean();
        
        $this->assertEquals('Content with headers', $output);
    }
    
    public function testDispatchJsonResponse() {
        $response = new Response();
        $response->setJsonResponse(['message' => 'Hello', 'status' => 'success']);
        
        ob_start();
        $this->dispatch->dispatch($response);
        $output = ob_get_clean();
        
        $expectedJson = json_encode(['success' => true, 'body' => ['message' => 'Hello', 'status' => 'success'], 'text' => 'OK']);
        $this->assertEquals($expectedJson, $output);
    }
    
    public function testDispatchCliResponse() {
        $response = new Response();
        $response->setResponse('CLI output message');
        
        ob_start();
        $this->dispatch->dispatch($response, true); // CLI mode
        $output = ob_get_clean();
        
        $this->assertEquals('CLI output message', $output);
    }
    
    public function testDispatchEmptyResponse() {
        $response = new Response();
        // Don't set any response body
        
        ob_start();
        $this->dispatch->dispatch($response);
        $output = ob_get_clean();
        
        $this->assertEquals('', $output);
    }
    
    public function testDispatchNullResponseBody() {
        $response = new Response();
        $response->response_body = '';
        
        ob_start();
        $this->dispatch->dispatch($response);
        $output = ob_get_clean();
        
        $this->assertEquals('', $output);
    }
    
    public function testDispatchCliWithEmptyResponse() {
        $response = new Response();
        // Empty response in CLI mode
        
        ob_start();
        $this->dispatch->dispatch($response, true);
        $output = ob_get_clean();
        
        $this->assertEquals('', $output);
    }
    
    public function testDispatchWithMultipleHeaders() {
        $response = new Response();
                 $response->setResponse('Test content')
                 ->setHeader('Cache-Control: no-cache')
                 ->setHeader('Content-Language: en')
                 ->setHeader('X-Powered-By: Gimli');
        
        // Test that multiple headers don't cause issues
        ob_start();
        $this->dispatch->dispatch($response);
        $output = ob_get_clean();
        
        $this->assertEquals('Test content', $output);
    }
    
    public function testDispatchJsonWithCustomStatusAndHeaders() {
        $response = new Response();
        $response->setJsonResponse(['error' => 'Not found'], 'Not found', false, 404)
                 ->setHeader('X-Error-Code: NOT_FOUND');
        
        ob_start();
        $this->dispatch->dispatch($response);
        $output = ob_get_clean();
        
        $expectedJson = json_encode(['success' => false, 'body' => ['error' => 'Not found'], 'text' => 'Not found']);
        $this->assertEquals($expectedJson, $output);
    }

    public function testStatusSetAfterHeaders403WithWwwAuthenticateStays403(): void {
        header_remove();
        $response = (new Response())->setResponse('', false, [], 403)->setHeader('WWW-Authenticate: Basic');
        ob_start();
        $this->dispatch->dispatch($response);
        ob_end_clean();
        $this->assertSame(403, http_response_code());
    }

    public function testStatusSetAfterHeaders401WithLocationStays401(): void {
        header_remove();
        $response = (new Response())->setResponse('', false, [], 401)->setHeader('Location: /login');
        ob_start();
        $this->dispatch->dispatch($response);
        ob_end_clean();
        $this->assertSame(401, http_response_code());
    }

    /**
     * All current header values for a name (xdebug exposes what PHP will send).
     */
    private function headerValues(string $name): array {
        $out = [];
        foreach (xdebug_get_headers() as $h) {
            if (stripos($h, $name . ':') === 0) {
                $out[] = trim(substr($h, strlen($name) + 1));
            }
        }
        return $out;
    }

    private function send(Response $response): void {
        ob_start();
        $this->dispatch->dispatch($response);
        ob_end_clean();
    }

    #[\PHPUnit\Framework\Attributes\RequiresFunction('xdebug_get_headers')]
    public function testDefault200WithLocationBecomes302(): void {
        header_remove();
        http_response_code(200);
        $response = (new Response())->setResponse('', true)->setHeader('Location: /next');
        $this->send($response);
        $this->assertSame(302, http_response_code());
        $this->assertSame(['/next'], $this->headerValues('Location'));
    }

    public function testExplicit401WithLocationStays401(): void {
        header_remove();
        $response = (new Response())->setResponse('', false, [], 401)->setHeader('Location: /login');
        $this->send($response);
        $this->assertSame(401, http_response_code());
    }

    #[\PHPUnit\Framework\Attributes\RequiresFunction('xdebug_get_headers')]
    public function testResponseSetCookieDoesNotWipePriorCookie(): void {
        header_remove();
        header('Set-Cookie: first=1; Path=/', false);
        $response = (new Response())->setResponse('x', true)->setHeader('Set-Cookie: second=2; Path=/');
        $this->send($response);
        $cookies = $this->headerValues('Set-Cookie');
        $this->assertContains('first=1; Path=/', $cookies);
        $this->assertContains('second=2; Path=/', $cookies);
    }

    #[\PHPUnit\Framework\Attributes\RequiresFunction('xdebug_get_headers')]
    public function testRepeatedWwwAuthenticateHeadersAreBothSent(): void {
        header_remove();
        $response = (new Response())->setResponse('', false, [], 401)
            ->setHeader('WWW-Authenticate: Bearer')
            ->setHeader('WWW-Authenticate: Basic realm="x"');
        $this->send($response);
        $this->assertSame(['Bearer', 'Basic realm="x"'], $this->headerValues('WWW-Authenticate'));
    }

    #[\PHPUnit\Framework\Attributes\RequiresFunction('xdebug_get_headers')]
    public function testUserContentTypeOverridesJsonDefault(): void {
        header_remove();
        $response = new Response();
        $response->setJsonResponse(['a' => 1])->setHeader('Content-Type: application/problem+json');
        $this->send($response);
        $this->assertSame(['application/problem+json'], $this->headerValues('Content-Type'));
    }

    #[\PHPUnit\Framework\Attributes\RequiresFunction('xdebug_get_headers')]
    public function testJsonDefaultContentTypeWhenNoOverride(): void {
        header_remove();
        $response = new Response();
        $response->setJsonResponse(['a' => 1]);
        $this->send($response);
        $this->assertSame(['application/json'], $this->headerValues('Content-Type'));
    }

    public static function nonCountingContentTypes(): array {
        return [['Content-Type : x'], ['Content-type'], [' Content-Type: x']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonCountingContentTypes')]
    #[\PHPUnit\Framework\Attributes\RequiresFunction('xdebug_get_headers')]
    public function testMalformedContentTypeDoesNotSuppressJsonDefault(string $bad): void {
        header_remove();
        $response = new Response();
        $response->setJsonResponse(['a' => 1])->setHeader($bad);
        $this->send($response);
        $this->assertContains('application/json', $this->headerValues('Content-Type'));
    }

    #[\PHPUnit\Framework\Attributes\RequiresFunction('xdebug_get_headers')]
    public function testLowercaseContentTypeSuppressesJsonDefault(): void {
        header_remove();
        $response = new Response();
        $response->setJsonResponse(['a' => 1])->setHeader('content-type:application/problem+json');
        $this->send($response);
        $this->assertSame(['application/problem+json'], $this->headerValues('Content-Type'));
    }

    #[\PHPUnit\Framework\Attributes\RequiresFunction('xdebug_get_headers')]
    public function testResponseVaryHeadersAreAppended(): void {
        header_remove();
        header('Vary: Origin', false);
        $response = (new Response())->setResponse('x', true)->setHeader('Vary: Accept-Encoding');
        $this->send($response);
        $vary = strtolower(implode(',', $this->headerValues('Vary')));
        $this->assertStringContainsString('origin', $vary);
        $this->assertStringContainsString('accept-encoding', $vary);
    }
}
