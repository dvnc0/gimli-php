<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Http\Response;

/**
 * @covers Gimli\Http\Response
 */
class Response_Test extends TestCase {
    
    public function testConstructorWithDefaults(): void {
        $response = new Response();
        
        $this->assertEquals('', $response->response_body);
        $this->assertTrue($response->success);
        $this->assertEquals([], $response->data);
        $this->assertEquals(200, $response->response_code);
        $this->assertFalse($response->is_json);
        $this->assertEquals([], $response->headers);
    }
    
    public function testConstructorWithParameters(): void {
        $response = new Response('Hello World', false, ['key' => 'value'], 404);
        
        $this->assertEquals('Hello World', $response->response_body);
        $this->assertFalse($response->success);
        $this->assertEquals(['key' => 'value'], $response->data);
        $this->assertEquals(404, $response->response_code);
        $this->assertFalse($response->is_json);
        $this->assertEquals([], $response->headers);
    }
    
    public function testSetResponseWithDefaults(): void {
        $response = new Response();
        $result = $response->setResponse();
        
        $this->assertSame($response, $result);
        $this->assertEquals('', $response->response_body);
        $this->assertTrue($response->success);
        $this->assertEquals([], $response->data);
        $this->assertEquals(200, $response->response_code);
    }
    
    public function testSetResponseWithParameters(): void {
        $response = new Response();
        $result = $response->setResponse('Updated content', false, ['error' => 'Not found'], 404);
        
        $this->assertSame($response, $result);
        $this->assertEquals('Updated content', $response->response_body);
        $this->assertFalse($response->success);
        $this->assertEquals(['error' => 'Not found'], $response->data);
        $this->assertEquals(404, $response->response_code);
    }
    
    public function testSetJsonResponseWithDefaults(): void {
        $response = new Response();
        $result = $response->setJsonResponse();
        
        $this->assertSame($response, $result);
        $this->assertTrue($response->is_json);
        $this->assertTrue($response->success);
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals([], $response->data);
        
        $expectedJson = json_encode(['success' => true, 'body' => [], 'text' => 'OK']);
        $this->assertEquals($expectedJson, $response->response_body);
    }
    
    public function testSetJsonResponseWithParameters(): void {
        $response = new Response();
        $body = ['users' => [['id' => 1, 'name' => 'John']], 'total' => 1];
        $result = $response->setJsonResponse($body, 'Users retrieved successfully', true, 200);
        
        $this->assertSame($response, $result);
        $this->assertTrue($response->is_json);
        $this->assertTrue($response->success);
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals($body, $response->data);
        
        $expectedJson = json_encode([
            'success' => true,
            'body' => $body,
            'text' => 'Users retrieved successfully'
        ]);
        $this->assertEquals($expectedJson, $response->response_body);
    }
    
    public function testSetJsonResponseWithError(): void {
        $response = new Response();
        $errorBody = ['error_code' => 'VALIDATION_FAILED', 'field' => 'email'];
        $result = $response->setJsonResponse($errorBody, 'Validation failed', false, 400);
        
        $this->assertSame($response, $result);
        $this->assertTrue($response->is_json);
        $this->assertFalse($response->success);
        $this->assertEquals(400, $response->response_code);
        $this->assertEquals($errorBody, $response->data);
        
        $expectedJson = json_encode([
            'success' => false,
            'body' => $errorBody,
            'text' => 'Validation failed'
        ]);
        $this->assertEquals($expectedJson, $response->response_body);
    }
    
    public function testSetHeader(): void {
        $response = new Response();
        $result = $response->setHeader('Content-Type: application/json');
        
        $this->assertSame($response, $result);
        $this->assertEquals(['Content-Type: application/json'], $response->headers);
    }
    
    public function testSetMultipleHeaders(): void {
        $response = new Response();
        
        $response->setHeader('Content-Type: application/json')
                 ->setHeader('Cache-Control: no-cache')
                 ->setHeader('X-Custom-Header: custom-value');
        
        $expectedHeaders = [
            'Content-Type: application/json',
            'Cache-Control: no-cache',
            'X-Custom-Header: custom-value'
        ];
        $this->assertEquals($expectedHeaders, $response->headers);
    }
    
    public function testFluentInterfaceChaining(): void {
        $response = new Response();
        
        $result = $response->setResponse('Initial content', true, ['initial' => 'data'], 200)
                          ->setHeader('Content-Type: text/html')
                          ->setHeader('Cache-Control: max-age=3600');
        
        $this->assertSame($response, $result);
        $this->assertEquals('Initial content', $response->response_body);
        $this->assertTrue($response->success);
        $this->assertEquals(['initial' => 'data'], $response->data);
        $this->assertEquals(200, $response->response_code);
        $this->assertEquals([
            'Content-Type: text/html',
            'Cache-Control: max-age=3600'
        ], $response->headers);
    }
    
    public function testJsonResponseWithComplexData(): void {
        $response = new Response();
        $complexData = [
            'users' => [
                ['id' => 1, 'name' => 'John', 'active' => true],
                ['id' => 2, 'name' => 'Jane', 'active' => false]
            ],
            'meta' => [
                'total' => 2,
                'page' => 1,
                'per_page' => 10,
                'has_more' => false
            ]
        ];
        
        $response->setJsonResponse($complexData, 'Data retrieved', true, 200);
        
        $this->assertEquals($complexData, $response->data);
        $expectedJson = json_encode([
            'success' => true,
            'body' => $complexData,
            'text' => 'Data retrieved'
        ]);
        $this->assertEquals($expectedJson, $response->response_body);
    }
}
