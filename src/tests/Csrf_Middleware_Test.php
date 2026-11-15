<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Middleware\Csrf_Middleware;
use Gimli\Middleware\Middleware_Response;
use Gimli\Http\Request;
use Gimli\View\Csrf;
use Gimli\Session\Session;
use Gimli\Application;
use Gimli\Application_Registry;
use Gimli\Injector\Injector;

/**
 * @covers Gimli\Middleware\Csrf_Middleware
 */
class Csrf_Middleware_Test extends TestCase {
    
    private Application $mockApp;
    private Injector $mockInjector;
    private Request $mockRequest;
    private Session $mockSession;
    private array $sessionData = [];
    private array $originalPost;
    
    protected function setUp(): void {
        // Backup original $_POST
        $this->originalPost = $_POST;
        
        // Create mock session
        $this->mockSession = $this->createMock(Session::class);
        $this->sessionData = [];
        
        $this->mockSession->method('get')
            ->willReturnCallback(function($key) {
                return $this->sessionData[$key] ?? null;
            });
        
        $this->mockSession->method('set')
            ->willReturnCallback(function($key, $value) {
                $this->sessionData[$key] = $value;
            });
        
        $this->mockSession->method('has')
            ->willReturnCallback(function($key) {
                return isset($this->sessionData[$key]);
            });
        
        // Create mock request
        $this->mockRequest = $this->createMock(Request::class);
        
        // Create mock injector
        $this->mockInjector = $this->createMock(Injector::class);
        $this->mockInjector->method('resolve')
            ->willReturnCallback(function($class) {
                if ($class === Request::class) {
                    return $this->mockRequest;
                }
                if ($class === Session::class) {
                    return $this->mockSession;
                }
                return null;
            });
        
        // Create mock application
        $this->mockApp = $this->createMock(Application::class);
        $this->mockApp->Injector = $this->mockInjector;
        
        // Set up Application_Registry
        Application_Registry::set($this->mockApp);
    }
    
    protected function tearDown(): void {
        Application_Registry::clear();
        $_POST = $this->originalPost;
        $this->sessionData = [];
    }
    
    // === SAFE HTTP METHODS TESTS ===
    
    public function testAllowsGetRequest(): void {
        $this->mockRequest->REQUEST_METHOD = 'GET';
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertTrue($response->success);
        $this->assertEquals('', $response->message);
        $this->assertEquals('', $response->forward);
    }
    
    public function testAllowsHeadRequest(): void {
        $this->mockRequest->REQUEST_METHOD = 'HEAD';
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertTrue($response->success);
    }
    
    public function testAllowsOptionsRequest(): void {
        $this->mockRequest->REQUEST_METHOD = 'OPTIONS';
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertTrue($response->success);
    }
    
    // === STATE-CHANGING METHODS TESTS ===
    
    public function testBlocksPostRequestWithoutToken(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $_POST = ['username' => 'john', 'email' => 'john@example.com'];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertFalse($response->success);
        $this->assertEquals('CSRF token mismatch', $response->message);
        $this->assertNotNull($response->response);
        $this->assertSame(403, $response->response->response_code);
    }
    
    public function testBlocksPutRequestWithoutToken(): void {
        $this->mockRequest->REQUEST_METHOD = 'PUT';
        $this->mockRequest->REQUEST_URI = '/user/123';
        $_POST = ['name' => 'Updated Name'];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertFalse($response->success);
        $this->assertEquals('CSRF token mismatch', $response->message);
        $this->assertNotNull($response->response);
        $this->assertSame(403, $response->response->response_code);
    }
    
    public function testBlocksPatchRequestWithoutToken(): void {
        $this->mockRequest->REQUEST_METHOD = 'PATCH';
        $this->mockRequest->REQUEST_URI = '/user/123';
        $_POST = ['email' => 'new@example.com'];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertFalse($response->success);
        $this->assertEquals('CSRF token mismatch', $response->message);
        $this->assertNotNull($response->response);
        $this->assertSame(403, $response->response->response_code);
    }
    
    public function testBlocksDeleteRequestWithoutToken(): void {
        $this->mockRequest->REQUEST_METHOD = 'DELETE';
        $this->mockRequest->REQUEST_URI = '/user/123';
        $_POST = [];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertFalse($response->success);
        $this->assertEquals('CSRF token mismatch', $response->message);
        $this->assertNotNull($response->response);
        $this->assertSame(403, $response->response->response_code);
    }
    
    // === VALID TOKEN TESTS ===
    
    public function testAllowsPostRequestWithValidToken(): void {
        // Generate a valid token first
        $token = $this->generateValidToken();
        
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $_POST = ['csrf_token' => $token, 'username' => 'john'];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertTrue($response->success);
        $this->assertEquals('', $response->forward);
    }
    
    public function testAllowsPutRequestWithValidToken(): void {
        $token = $this->generateValidToken();
        
        $this->mockRequest->REQUEST_METHOD = 'PUT';
        $this->mockRequest->REQUEST_URI = '/user/123';
        $_POST = ['csrf_token' => $token, 'name' => 'Updated'];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertTrue($response->success);
    }
    
    public function testAllowsPatchRequestWithValidToken(): void {
        $token = $this->generateValidToken();
        
        $this->mockRequest->REQUEST_METHOD = 'PATCH';
        $this->mockRequest->REQUEST_URI = '/user/123';
        $_POST = ['csrf_token' => $token, 'email' => 'new@example.com'];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertTrue($response->success);
    }
    
    public function testAllowsDeleteRequestWithValidToken(): void {
        $token = $this->generateValidToken();
        
        $this->mockRequest->REQUEST_METHOD = 'DELETE';
        $this->mockRequest->REQUEST_URI = '/user/123';
        $_POST = ['csrf_token' => $token];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertTrue($response->success);
    }
    
    // === INVALID TOKEN TESTS ===
    
    public function testBlocksRequestWithInvalidToken(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $_POST = ['csrf_token' => 'invalid_token', 'username' => 'john'];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertFalse($response->success);
        $this->assertEquals('CSRF token mismatch', $response->message);
        $this->assertNotNull($response->response);
        $this->assertSame(403, $response->response->response_code);
    }
    
    public function testBlocksRequestWithExpiredToken(): void {
        // Generate token and manually expire it
        $token = $this->generateValidToken();
        $this->sessionData['csrf_token'][$token] = time() - 1; // Expired
        
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $_POST = ['csrf_token' => $token, 'username' => 'john'];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertFalse($response->success);
        $this->assertEquals('CSRF token mismatch', $response->message);
        $this->assertNotNull($response->response);
        $this->assertSame(403, $response->response->response_code);
    }
    
    public function testBlocksRequestWithEmptyToken(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $_POST = ['csrf_token' => '', 'username' => 'john'];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertFalse($response->success);
        $this->assertEquals('CSRF token mismatch', $response->message);
        $this->assertNotNull($response->response);
        $this->assertSame(403, $response->response->response_code);
    }
    
    // === API ENDPOINT BYPASS TESTS ===
    
    public function testBypassesApiEndpoints(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/api/users';
        $_POST = ['username' => 'john']; // No CSRF token
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertTrue($response->success);
        $this->assertEquals('', $response->forward);
    }
    
    public function testBypassesNestedApiEndpoints(): void {
        $this->mockRequest->REQUEST_METHOD = 'PUT';
        $this->mockRequest->REQUEST_URI = '/api/v1/users/123';
        $_POST = ['name' => 'Updated']; // No CSRF token
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertTrue($response->success);
    }
    
    public function testDoesNotBypassNonApiEndpoints(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/application/form'; // Not API
        $_POST = ['username' => 'john']; // No CSRF token
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertFalse($response->success);
        $this->assertEquals('CSRF token mismatch', $response->message);
        $this->assertNotNull($response->response);
        $this->assertSame(403, $response->response->response_code);
    }
    
    // === INTEGRATION TESTS ===
    
    public function testCompleteWorkflow(): void {
        // Test the complete workflow: generate token, submit form, verify
        $token = $this->generateValidToken();
        
        // Simulate form submission
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/user/profile';
        $_POST = [
            'csrf_token' => $token,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertTrue($response->success);
        
        // Token should be consumed (one-time use)
        $secondResponse = $middleware->process();
        $this->assertFalse($secondResponse->success);
    }
    
    public function testMultipleValidTokens(): void {
        // Generate multiple tokens
        $token1 = $this->generateValidToken();
        $token2 = $this->generateValidToken();
        
        // First request with token1
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form1';
        $_POST = ['csrf_token' => $token1, 'data' => 'value1'];
        
        $middleware = new Csrf_Middleware();
        $response1 = $middleware->process();
        $this->assertTrue($response1->success);
        
        // Second request with token2 (should still work)
        $_POST = ['csrf_token' => $token2, 'data' => 'value2'];
        $response2 = $middleware->process();
        $this->assertTrue($response2->success);
    }
    
    // === EDGE CASES ===
    
    public function testHandlesEmptyPostData(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $_POST = [];
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        $this->assertInstanceOf(Middleware_Response::class, $response);
        $this->assertFalse($response->success);
        $this->assertEquals('CSRF token mismatch', $response->message);
        $this->assertNotNull($response->response);
        $this->assertSame(403, $response->response->response_code);
    }
    
    public function testFailureIsPlainTextForBrowserRequest(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $_POST = [];

        $response = (new Csrf_Middleware())->process();

        $this->assertFalse($response->success);
        $this->assertSame('', $response->forward);
        $this->assertFalse($response->response->is_json);
        $this->assertSame(403, $response->response->response_code);
        $this->assertSame('CSRF token mismatch', $response->response->response_body);
    }

    public function testFailureIsJsonForXhrRequest(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $this->mockRequest->HTTP_X_REQUESTED_WITH = 'XMLHttpRequest';
        $_POST = [];

        $response = (new Csrf_Middleware())->process();

        $this->assertFalse($response->success);
        $this->assertTrue($response->response->is_json);
        $this->assertSame(403, $response->response->response_code);
        $decoded = json_decode($response->response->response_body, true);
        $this->assertFalse($decoded['success']);
        $this->assertSame('CSRF token mismatch', $decoded['text']);
    }

    public function testFailureIsJsonForAcceptJson(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $this->mockRequest->HTTP_ACCEPT = 'application/json';
        $_POST = [];

        $response = (new Csrf_Middleware())->process();

        $this->assertTrue($response->response->is_json);
        $this->assertSame(403, $response->response->response_code);
    }

    public function testServerSuperglobalAloneDoesNotTriggerJson(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $_POST = [];

        $had = array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER);
        $prior = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        try {
            $response = (new Csrf_Middleware())->process();
        } finally {
            if ($had) {
                $_SERVER['HTTP_X_REQUESTED_WITH'] = $prior;
            } else {
                unset($_SERVER['HTTP_X_REQUESTED_WITH']);
            }
        }

        $this->assertFalse($response->success);
        $this->assertFalse($response->response->is_json);
        $this->assertSame(403, $response->response->response_code);
    }

    public function testFailureCarriesVaryHeaderTextVariant(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $_POST = [];
        $response = (new Csrf_Middleware())->process();
        $this->assertContains('Vary: Accept, X-Requested-With', $response->response->headers);
    }

    public function testFailureCarriesVaryHeaderJsonVariant(): void {
        $this->mockRequest->REQUEST_METHOD = 'POST';
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $this->mockRequest->HTTP_ACCEPT = 'application/json';
        $_POST = [];
        $response = (new Csrf_Middleware())->process();
        $this->assertContains('Vary: Accept, X-Requested-With', $response->response->headers);
    }

    public function testHandlesNullRequestMethod(): void {
        $this->mockRequest->REQUEST_METHOD = null;
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        // Should pass since null is not in the checked methods array
        $this->assertTrue($response->success);
    }
    
    public function testCaseInsensitiveMethodCheck(): void {
        $this->mockRequest->REQUEST_METHOD = 'post'; // lowercase
        $this->mockRequest->REQUEST_URI = '/form/submit';
        $_POST = ['username' => 'john']; // No CSRF token
        
        $middleware = new Csrf_Middleware();
        $response = $middleware->process();
        
        // Should pass since 'post' != 'POST' (case sensitive comparison)
        $this->assertTrue($response->success);
    }
    
    // === HELPER METHODS ===
    
    private function generateValidToken(): string {
        $token = bin2hex(random_bytes(32));
        $expireTime = time() + 900; // 15 minutes
        
        if (!isset($this->sessionData['csrf_token'])) {
            $this->sessionData['csrf_token'] = [];
        }
        
        $this->sessionData['csrf_token'][$token] = $expireTime;
        
        return $token;
    }
}
