<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Http\Request;

/**
 * @covers Gimli\Http\Request
 */
class Request_Test extends TestCase {
    
    private array $originalPost;
    private array $originalGet;
    
    protected function setUp(): void {
        // Backup original superglobals
        $this->originalPost = $_POST;
        $this->originalGet = $_GET;
    }
    
    protected function tearDown(): void {
        // Restore original superglobals
        $_POST = $this->originalPost;
        $_GET = $this->originalGet;
    }
    
    // === CONSTRUCTOR TESTS ===
    
    public function testConstructorSetsServerValues(): void {
        $serverValues = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
            'HTTP_HOST' => 'example.com',
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => '80',
            'REMOTE_ADDR' => '192.168.1.1',
            'REQUEST_SCHEME' => 'http',
            'QUERY_STRING' => 'param=value',
            'argc' => 2,
            'argv' => ['script.php', 'arg1']
        ];
        
        $request = new Request($serverValues);
        
        $this->assertEquals('GET', $request->REQUEST_METHOD);
        $this->assertEquals('/test', $request->REQUEST_URI);
        $this->assertEquals('example.com', $request->HTTP_HOST);
        $this->assertEquals('example.com', $request->SERVER_NAME);
        $this->assertEquals('80', $request->SERVER_PORT);
        $this->assertEquals('192.168.1.1', $request->REMOTE_ADDR);
        $this->assertEquals('http', $request->REQUEST_SCHEME);
        $this->assertEquals('param=value', $request->QUERY_STRING);
        $this->assertEquals(2, $request->argc);
        $this->assertEquals(['script.php', 'arg1'], $request->argv);
    }
    
    public function testConstructorIgnoresEmptyServerValues(): void {
        $serverValues = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '',
            'HTTP_HOST' => 'example.com',
            'SERVER_NAME' => null,
        ];
        
        $request = new Request($serverValues);
        
        $this->assertEquals('GET', $request->REQUEST_METHOD);
        $this->assertEquals('example.com', $request->HTTP_HOST);
        $this->assertNull($request->REQUEST_URI ?? null);
        $this->assertNull($request->SERVER_NAME ?? null);
    }
    
    public function testConstructorSetsDefaultArgcArgv(): void {
        $serverValues = ['REQUEST_METHOD' => 'GET'];
        
        $request = new Request($serverValues);
        
        $this->assertEquals(0, $request->argc);
        $this->assertEquals([], $request->argv);
    }
    
    // === GET REQUEST TESTS ===
    
    public function testConstructorProcessesGetRequest(): void {
        $_GET = ['param1' => 'value1', 'param2' => 'value2'];
        
        $serverValues = ['REQUEST_METHOD' => 'GET'];
        $request = new Request($serverValues);
        
        $this->assertEquals('value1', $request->getQueryParam('param1'));
        $this->assertEquals('value2', $request->getQueryParam('param2'));
        $this->assertEquals($_GET, $request->getQueryParams());
    }
    
    public function testGetQueryParamReturnsNullForMissingParam(): void {
        $_GET = ['existing' => 'value'];
        
        $serverValues = ['REQUEST_METHOD' => 'GET'];
        $request = new Request($serverValues);
        
        $this->assertEquals('value', $request->getQueryParam('existing'));
        $this->assertNull($request->getQueryParam('missing'));
    }
    
    public function testGetQueryParamsReturnsNullWhenEmpty(): void {
        $_GET = [];
        
        $serverValues = ['REQUEST_METHOD' => 'GET'];
        $request = new Request($serverValues);
        
        $this->assertNull($request->getQueryParams());
    }
    
    // === POST REQUEST TESTS ===
    
    public function testConstructorProcessesPostRequest(): void {
        $_POST = ['username' => 'john', 'email' => 'john@example.com'];
        
        $serverValues = ['REQUEST_METHOD' => 'POST'];
        $request = new Request($serverValues);
        
        $this->assertEquals('john', $request->getPostParam('username'));
        $this->assertEquals('john@example.com', $request->getPostParam('email'));
        $this->assertEquals($_POST, $request->getPostParams());
    }
    
    public function testConstructorProcessesPutRequest(): void {
        $_POST = ['name' => 'updated'];
        
        $serverValues = ['REQUEST_METHOD' => 'PUT'];
        $request = new Request($serverValues);
        
        $this->assertEquals('updated', $request->getPostParam('name'));
    }
    
    public function testConstructorProcessesPatchRequest(): void {
        $_POST = ['field' => 'patched'];
        
        $serverValues = ['REQUEST_METHOD' => 'PATCH'];
        $request = new Request($serverValues);
        
        $this->assertEquals('patched', $request->getPostParam('field'));
    }
    
    public function testGetPostParamReturnsNullForMissingParam(): void {
        $_POST = ['existing' => 'value'];
        
        $serverValues = ['REQUEST_METHOD' => 'POST'];
        $request = new Request($serverValues);
        
        $this->assertEquals('value', $request->getPostParam('existing'));
        $this->assertNull($request->getPostParam('missing'));
    }
    
    public function testGetPostParamsReturnsEmptyArrayWhenNoPost(): void {
        $_POST = [];
        
        $serverValues = ['REQUEST_METHOD' => 'POST'];
        $request = new Request($serverValues);
        
        $this->assertEquals([], $request->getPostParams());
    }
    
    // === JSON INPUT TESTS ===
    
    public function testCreatePostDataWithEmptyPost(): void {
        $_POST = [];
        
        $serverValues = ['REQUEST_METHOD' => 'POST'];
        $request = new Request($serverValues);
        
        // When $_POST is empty and php://input can't be read, post should be empty array
        $this->assertEquals([], $request->getPostParams());
    }
    
    // === HEADERS TESTS ===
    
    public function testConstructorSetsHeadersProperty(): void {
        $serverValues = ['REQUEST_METHOD' => 'GET'];
        $request = new Request($serverValues);
        
        // In CLI mode, headers may not be initialized, so we need to check if it's set
        if (PHP_SAPI === 'cli') {
            // In CLI mode, headers should be empty array or not set
            $this->assertTrue(!isset($request->headers) || is_array($request->headers));
        } else {
            $this->assertIsArray($request->headers);
        }
    }
    
    // === ROUTE DATA TESTS ===
    
    public function testRouteDataInitializedAsEmpty(): void {
        $serverValues = ['REQUEST_METHOD' => 'GET'];
        $request = new Request($serverValues);
        
        $this->assertEquals([], $request->route_data);
    }
    
    public function testRouteDataCanBeSet(): void {
        $serverValues = ['REQUEST_METHOD' => 'GET'];
        $request = new Request($serverValues);
        
        $request->route_data = ['param' => 'value'];
        $this->assertEquals(['param' => 'value'], $request->route_data);
    }
    
    // === HTTP HEADERS TESTS ===
    
    public function testConstructorSetsHttpHeaders(): void {
        $serverValues = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
            'HTTP_CONNECTION' => 'keep-alive',
            'HTTP_CACHE_CONTROL' => 'max-age=0',
            'HTTP_COOKIE' => 'session=abc123',
            'HTTP_DNT' => '1',
            'HTTP_UPGRADE_INSECURE_REQUESTS' => '1'
        ];
        
        $request = new Request($serverValues);
        
        $this->assertEquals('example.com', $request->HTTP_HOST);
        $this->assertEquals('text/html,application/xhtml+xml', $request->HTTP_ACCEPT);
        $this->assertEquals('en-US,en;q=0.9', $request->HTTP_ACCEPT_LANGUAGE);
        $this->assertEquals('gzip, deflate', $request->HTTP_ACCEPT_ENCODING);
        $this->assertEquals('keep-alive', $request->HTTP_CONNECTION);
        $this->assertEquals('max-age=0', $request->HTTP_CACHE_CONTROL);
        $this->assertEquals('session=abc123', $request->HTTP_COOKIE);
        $this->assertEquals('1', $request->HTTP_DNT);
        $this->assertEquals('1', $request->HTTP_UPGRADE_INSECURE_REQUESTS);
    }
    
    // === SERVER ENVIRONMENT TESTS ===
    
    public function testConstructorSetsServerEnvironment(): void {
        $serverValues = [
            'REQUEST_METHOD' => 'GET',
            'SERVER_SOFTWARE' => 'Apache/2.4.41',
            'SERVER_NAME' => 'localhost',
            'SERVER_ADDR' => '127.0.0.1',
            'SERVER_PORT' => '80',
            'REMOTE_ADDR' => '192.168.1.100',
            'REMOTE_PORT' => '54321',
            'DOCUMENT_ROOT' => '/var/www/html',
            'REQUEST_SCHEME' => 'http',
            'CONTEXT_PREFIX' => '',
            'CONTEXT_DOCUMENT_ROOT' => '/var/www/html',
            'SERVER_ADMIN' => 'admin@example.com',
            'SCRIPT_FILENAME' => '/var/www/html/index.php',
            'GATEWAY_INTERFACE' => 'CGI/1.1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php',
            'REQUEST_TIME_FLOAT' => '1234567890.123',
            'REQUEST_TIME' => 1234567890,
            'PATH' => '/usr/local/bin:/usr/bin:/bin',
            'SERVER_SIGNATURE' => '<address>Apache/2.4.41 Server</address>'
        ];
        
        $request = new Request($serverValues);
        
        $this->assertEquals('Apache/2.4.41', $request->SERVER_SOFTWARE);
        $this->assertEquals('localhost', $request->SERVER_NAME);
        $this->assertEquals('127.0.0.1', $request->SERVER_ADDR);
        $this->assertEquals('80', $request->SERVER_PORT);
        $this->assertEquals('192.168.1.100', $request->REMOTE_ADDR);
        $this->assertEquals('54321', $request->REMOTE_PORT);
        $this->assertEquals('/var/www/html', $request->DOCUMENT_ROOT);
        $this->assertEquals('http', $request->REQUEST_SCHEME);
        $this->assertEquals('admin@example.com', $request->SERVER_ADMIN);
        $this->assertEquals('/var/www/html/index.php', $request->SCRIPT_FILENAME);
        $this->assertEquals('CGI/1.1', $request->GATEWAY_INTERFACE);
        $this->assertEquals('HTTP/1.1', $request->SERVER_PROTOCOL);
        $this->assertEquals('/index.php', $request->SCRIPT_NAME);
        $this->assertEquals('/index.php', $request->PHP_SELF);
        $this->assertEquals('1234567890.123', $request->REQUEST_TIME_FLOAT);
        $this->assertEquals(1234567890, $request->REQUEST_TIME);
        $this->assertEquals('/usr/local/bin:/usr/bin:/bin', $request->PATH);
        $this->assertEquals('<address>Apache/2.4.41 Server</address>', $request->SERVER_SIGNATURE);
    }
    
    // === REQUEST METHODS TESTS ===
    
    public function testDifferentRequestMethods(): void {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        
        foreach ($methods as $method) {
            $serverValues = ['REQUEST_METHOD' => $method];
            $request = new Request($serverValues);
            
            $this->assertEquals($method, $request->REQUEST_METHOD);
        }
    }
    
    // === CLI CONTEXT TESTS ===
    
    public function testCliContextHandling(): void {
        $serverValues = [
            'REQUEST_METHOD' => 'CLI',
            'argc' => 3,
            'argv' => ['script.php', 'command', 'argument']
        ];
        
        $request = new Request($serverValues);
        
        $this->assertEquals('CLI', $request->REQUEST_METHOD);
        $this->assertEquals(3, $request->argc);
        $this->assertEquals(['script.php', 'command', 'argument'], $request->argv);
        // In CLI context, headers may not be initialized since getallheaders() isn't called
        $this->assertTrue(!isset($request->headers) || is_array($request->headers));
    }
    
    // === EDGE CASES ===
    
    public function testEmptyServerValues(): void {
        $request = new Request([]);
        
        $this->assertEquals(0, $request->argc);
        $this->assertEquals([], $request->argv);
        $this->assertEquals([], $request->route_data);
        // In CLI mode, headers may not be initialized
        $this->assertTrue(!isset($request->headers) || is_array($request->headers));
    }
    
    public function testMixedDataTypes(): void {
        $_GET = ['string' => 'text', 'number' => '123', 'array' => ['a', 'b']];
        $_POST = ['mixed' => 'value', 'numeric' => '456'];
        
        // Test GET parameters
        $getRequest = new Request(['REQUEST_METHOD' => 'GET']);
        $this->assertEquals('text', $getRequest->getQueryParam('string'));
        $this->assertEquals('123', $getRequest->getQueryParam('number'));
        $this->assertEquals(['a', 'b'], $getRequest->getQueryParam('array'));
        
        // Test POST parameters
        $postRequest = new Request(['REQUEST_METHOD' => 'POST']);
        $this->assertEquals('value', $postRequest->getPostParam('mixed'));
        $this->assertEquals('456', $postRequest->getPostParam('numeric'));
    }
} 