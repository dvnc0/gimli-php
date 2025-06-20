<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Http\Response;
use Gimli\Application;
use Gimli\Application_Registry;
use Gimli\Injector\Injector;

/**
 * @covers Gimli\Http\response
 * @covers Gimli\Http\redirect
 * @covers Gimli\Http\json_response
 * @covers Gimli\Http\redirect_on_success
 * @covers Gimli\Http\redirect_on_failure
 */
class Http_Helpers_Test extends TestCase {
    
    private Application $mockApp;
    private Injector $mockInjector;
    
    protected function setUp(): void {
        // Create mock application and injector
        $this->mockApp = $this->createMock(Application::class);
        $this->mockInjector = $this->createMock(Injector::class);
        
        // Configure injector to return Response instances
        $this->mockInjector->method('resolve')
            ->with(Response::class)
            ->willReturnCallback(function() {
                return new Response();
            });
        
        $this->mockApp->Injector = $this->mockInjector;
        
        // Set the mock application in the registry
        Application_Registry::set($this->mockApp);
    }
    
    protected function tearDown(): void {
        Application_Registry::clear();
    }
    
    // === RESPONSE HELPER TESTS ===
    
    public function testResponseHelperWithDefaults(): void {
        $response = \Gimli\Http\response();
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('', $response->response_body);
        $this->assertTrue($response->success);
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals([], $response->data);
    }
    
    public function testResponseHelperWithParameters(): void {
        $response = \Gimli\Http\response('Hello World', false, 404, ['error' => 'Not found']);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('Hello World', $response->response_body);
        $this->assertFalse($response->success);
        $this->assertEquals(404, $response->response_code);
        $this->assertEquals(['error' => 'Not found'], $response->data);
    }
    
    // === REDIRECT HELPER TESTS ===
    
    public function testRedirectHelperWithDefaults(): void {
        $response = \Gimli\Http\redirect('/dashboard');
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(['Location: /dashboard'], $response->headers);
        $this->assertEquals(302, $response->response_code);
        $this->assertEquals('', $response->response_body);
        $this->assertTrue($response->success);
    }
    
    public function testRedirectHelperWithCustomCode(): void {
        $response = \Gimli\Http\redirect('/login', 301);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(['Location: /login'], $response->headers);
        $this->assertEquals(301, $response->response_code);
    }
    
    // === JSON RESPONSE HELPER TESTS ===
    
    public function testJsonResponseHelperWithDefaults(): void {
        $body = ['message' => 'Success'];
        $response = \Gimli\Http\json_response($body);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->is_json);
        $this->assertTrue($response->success);
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals($body, $response->data);
        
        $expectedJson = json_encode([
            'success' => true,
            'body' => $body,
            'text' => 'OK'
        ]);
        $this->assertEquals($expectedJson, $response->response_body);
    }
    
    public function testJsonResponseHelperWithParameters(): void {
        $body = ['users' => [['id' => 1, 'name' => 'John']]];
        $response = \Gimli\Http\json_response($body, 'Users retrieved', true, 200);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->is_json);
        $this->assertTrue($response->success);
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals($body, $response->data);
        
        $expectedJson = json_encode([
            'success' => true,
            'body' => $body,
            'text' => 'Users retrieved'
        ]);
        $this->assertEquals($expectedJson, $response->response_body);
    }
    
    public function testJsonResponseHelperWithError(): void {
        $body = ['error' => 'Validation failed'];
        $response = \Gimli\Http\json_response($body, 'Bad Request', false, 400);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->is_json);
        $this->assertFalse($response->success);
        $this->assertEquals(400, $response->response_code);
        $this->assertEquals($body, $response->data);
        
        $expectedJson = json_encode([
            'success' => false,
            'body' => $body,
            'text' => 'Bad Request'
        ]);
        $this->assertEquals($expectedJson, $response->response_body);
    }
    
    // === REDIRECT ON SUCCESS HELPER TESTS ===
    
    public function testRedirectOnSuccessWhenSuccessful(): void {
        $response = \Gimli\Http\redirect_on_success('/dashboard', true, 'Login successful');
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(['Location: /dashboard'], $response->headers);
        $this->assertEquals(302, $response->response_code);
        $this->assertEquals('Login successful', $response->response_body);
        $this->assertTrue($response->success);
    }
    
    public function testRedirectOnSuccessWhenNotSuccessful(): void {
        $response = \Gimli\Http\redirect_on_success('/dashboard', false, 'Login failed');
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals([], $response->headers); // No redirect header
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals('Login failed', $response->response_body);
        $this->assertFalse($response->success);
    }
    
    public function testRedirectOnSuccessWithEmptyMessage(): void {
        $response = \Gimli\Http\redirect_on_success('/dashboard', true);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(['Location: /dashboard'], $response->headers);
        $this->assertEquals(302, $response->response_code);
        $this->assertEquals('', $response->response_body);
        $this->assertTrue($response->success);
    }
    
    // === REDIRECT ON FAILURE HELPER TESTS ===
    
    public function testRedirectOnFailureWhenSuccessful(): void {
        $response = \Gimli\Http\redirect_on_failure('/error', true, 'Operation successful');
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals([], $response->headers); // No redirect header
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals('Operation successful', $response->response_body);
        $this->assertTrue($response->success);
    }
    
    public function testRedirectOnFailureWhenNotSuccessful(): void {
        $response = \Gimli\Http\redirect_on_failure('/error', false, 'Operation failed');
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(['Location: /error'], $response->headers);
        $this->assertEquals(302, $response->response_code);
        $this->assertEquals('Operation failed', $response->response_body);
        $this->assertFalse($response->success);
    }
    
    public function testRedirectOnFailureWithEmptyMessage(): void {
        $response = \Gimli\Http\redirect_on_failure('/error', false);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(['Location: /error'], $response->headers);
        $this->assertEquals(302, $response->response_code);
        $this->assertEquals('', $response->response_body);
        $this->assertFalse($response->success);
    }
    
    // === INTEGRATION TESTS ===
    
    public function testAllHelpersUseApplicationRegistry(): void {
        // This test verifies that all helpers properly use the Application_Registry
        // by ensuring they can create Response instances through dependency injection
        
        $response1 = \Gimli\Http\response('test');
        $response2 = \Gimli\Http\redirect('/test');
        $response3 = \Gimli\Http\json_response(['test' => 'data']);
        $response4 = \Gimli\Http\redirect_on_success('/test', true);
        $response5 = \Gimli\Http\redirect_on_failure('/test', false);
        
        $this->assertInstanceOf(Response::class, $response1);
        $this->assertInstanceOf(Response::class, $response2);
        $this->assertInstanceOf(Response::class, $response3);
        $this->assertInstanceOf(Response::class, $response4);
        $this->assertInstanceOf(Response::class, $response5);
    }
}
