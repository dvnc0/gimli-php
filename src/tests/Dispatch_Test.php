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
} 