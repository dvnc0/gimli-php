<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Router\Route;

/**
 * @covers Gimli\Router\Route
 * Note: Testing Route class functionality since Router requires complex dependency setup
 */
class Router_Test extends TestCase {
    
    protected function setUp(): void {
        // Reset the Route singleton between tests
        $reflection = new ReflectionClass(Route::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $instance->setAccessible(false);
    }
    
    public function testRouteBuilding() {
        // Test that routes can be built and added
        Route::get('/test-route', function() { return 'test'; });
        Route::post('/api/users', function() { return 'create user'; });
        
        $routes = Route::build();
        
        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('POST', $routes);
        $this->assertArrayHasKey('/test-route', $routes['GET']);
        $this->assertArrayHasKey('/api/users', $routes['POST']);
    }
    
    public function testRouteWithMiddleware() {
        Route::get('/protected', function() { return 'protected'; })
             ->addMiddleware('AuthMiddleware');
        
        $routes = Route::build();
        $route = $routes['GET']['/protected'];
        
        $this->assertArrayHasKey('middleware', $route);
        $this->assertContains('AuthMiddleware', $route['middleware']);
    }
    
    public function testCliRouteBuilding() {
        Route::cli('test-command', function() { return 'CLI test'; });
        
        $routes = Route::build();
        
        $this->assertArrayHasKey('CLI', $routes);
        $this->assertArrayHasKey('test-command', $routes['CLI']);
    }
    
    public function testRouteWithParameters() {
        Route::get('/user/:id/posts/:slug', function($id, $slug) { 
            return "User $id, Post $slug"; 
        });
        
        $routes = Route::build();
        $route = $routes['GET']['/user/:id/posts/:slug'];
        
        $this->assertEquals('/user/:id/posts/:slug', $route['route']);
        $this->assertIsCallable($route['handler']);
    }
    
    public function testRouteWithNamedParameters() {
        Route::get('/product/:integer#id/:slug#name', function($id, $name) { 
            return "Product ID: $id, Name: $name"; 
        });
        
        $routes = Route::build();
        $route = $routes['GET']['/product/:integer/:slug'];
        
        $this->assertEquals('/product/:integer/:slug', $route['route']);
        $this->assertEquals(['id', 'name'], $route['arg_names']);
    }
    
    public function testRouteGroups() {
        Route::group('/api', function() {
            Route::get('/users', function() { return 'API Users'; });
            Route::post('/users', function() { return 'Create User'; });
        });
        
        $routes = Route::build();
        
        $this->assertArrayHasKey('/api/users', $routes['GET']);
        $this->assertArrayHasKey('/api/users', $routes['POST']);
        $this->assertEquals('/api/users', $routes['GET']['/api/users']['route']);
        $this->assertEquals('/api/users', $routes['POST']['/api/users']['route']);
    }
    
    public function testNestedRouteGroups() {
        Route::group('/api', function() {
            Route::group('/v1', function() {
                Route::get('/status', function() { return 'API v1 Status'; });
            });
        });
        
        $routes = Route::build();
        
        $this->assertArrayHasKey('/api/v1/status', $routes['GET']);
        $this->assertEquals('/api/v1/status', $routes['GET']['/api/v1/status']['route']);
    }
    
    public function testRouteGroupWithMiddleware() {
        Route::group('/admin', function() {
            Route::get('/dashboard', function() { return 'Dashboard'; });
            Route::get('/settings', function() { return 'Settings'; })
                 ->addMiddleware('ExtraMiddleware');
        }, ['AdminMiddleware', 'AuthMiddleware']);
        
        $routes = Route::build();
        
        // Dashboard should have group middleware
        $dashboardRoute = $routes['GET']['/admin/dashboard'];
        $this->assertContains('AdminMiddleware', $dashboardRoute['middleware']);
        $this->assertContains('AuthMiddleware', $dashboardRoute['middleware']);
        
        // Settings should have both group middleware and its own middleware
        $settingsRoute = $routes['GET']['/admin/settings'];
        $this->assertContains('AdminMiddleware', $settingsRoute['middleware']);
        $this->assertContains('AuthMiddleware', $settingsRoute['middleware']);
        $this->assertContains('ExtraMiddleware', $settingsRoute['middleware']);
    }
    
    public function testAnyRouteCreatesAllMethods() {
        Route::any('/flexible', function() { return 'Any method works'; });
        
        $routes = Route::build();
        
        // Should create routes for all HTTP methods
        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('POST', $routes);
        $this->assertArrayHasKey('PUT', $routes);
        $this->assertArrayHasKey('PATCH', $routes);
        $this->assertArrayHasKey('DELETE', $routes);
        
        $this->assertArrayHasKey('/flexible', $routes['GET']);
        $this->assertArrayHasKey('/flexible', $routes['POST']);
        $this->assertArrayHasKey('/flexible', $routes['PUT']);
        $this->assertArrayHasKey('/flexible', $routes['PATCH']);
        $this->assertArrayHasKey('/flexible', $routes['DELETE']);
    }
    
    public function testControllerStringFormatting() {
        Route::get('/controller-test', 'TestController@index');
        
        $routes = Route::build();
        $route = $routes['GET']['/controller-test'];
        
        $this->assertEquals('TestController@index', $route['handler']);
    }
    
    public function testControllerArrayFormatting() {
        Route::get('/array-controller', ['TestController', 'show']);
        
        $routes = Route::build();
        $route = $routes['GET']['/array-controller'];
        
        $this->assertEquals('TestController@show', $route['handler']);
    }
    
    public function testSingleActionController() {
        Route::get('/single-action', 'TestController');
        
        $routes = Route::build();
        $route = $routes['GET']['/single-action'];
        
        $this->assertEquals('TestController', $route['handler']);
    }
    
    public function testMultipleMiddlewareOnRoute() {
        Route::get('/multi-secure', function() { return 'Multi Secure'; })
             ->addMiddleware('AuthMiddleware')
             ->addMiddleware('SecurityMiddleware');
        
        $routes = Route::build();
        $route = $routes['GET']['/multi-secure'];
        
        $this->assertContains('AuthMiddleware', $route['middleware']);
        $this->assertContains('SecurityMiddleware', $route['middleware']);
    }
    
    public function testRouteSingletonPattern() {
        $instance1 = Route::getInstance();
        $instance2 = Route::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(Route::class, $instance1);
    }
} 