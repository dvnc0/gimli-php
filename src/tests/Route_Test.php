<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Router\Route;

/**
 * @covers Gimli\Router\Route
 */
class Route_Test extends TestCase {
    
    protected function setUp(): void {
        // Reset the Route singleton between tests
        $reflection = new ReflectionClass(Route::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $instance->setAccessible(false);
    }
    
    public function testGetInstanceReturnsSameInstance() {
        $instance1 = Route::getInstance();
        $instance2 = Route::getInstance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(Route::class, $instance1);
    }
    
    public function testGetRouteDefinition() {
        $callback = function() { return 'Hello World'; };
        $route = Route::get('/test', $callback);
        
        $this->assertInstanceOf(Route::class, $route);
        
        $routes = Route::build();
        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/test', $routes['GET']);
        $this->assertEquals('/test', $routes['GET']['/test']['route']);
        $this->assertEquals($callback, $routes['GET']['/test']['handler']);
    }
    
    public function testPostRouteDefinition() {
        $callback = function() { return 'Posted'; };
        Route::post('/submit', $callback);
        
        $routes = Route::build();
        $this->assertArrayHasKey('POST', $routes);
        $this->assertArrayHasKey('/submit', $routes['POST']);
        $this->assertEquals($callback, $routes['POST']['/submit']['handler']);
    }
    
    public function testPutRouteDefinition() {
        $callback = function() { return 'Updated'; };
        Route::put('/update', $callback);
        
        $routes = Route::build();
        $this->assertArrayHasKey('PUT', $routes);
        $this->assertArrayHasKey('/update', $routes['PUT']);
    }
    
    public function testPatchRouteDefinition() {
        $callback = function() { return 'Patched'; };
        Route::patch('/patch', $callback);
        
        $routes = Route::build();
        $this->assertArrayHasKey('PATCH', $routes);
        $this->assertArrayHasKey('/patch', $routes['PATCH']);
    }
    
    public function testDeleteRouteDefinition() {
        $callback = function() { return 'Deleted'; };
        Route::delete('/delete', $callback);
        
        $routes = Route::build();
        $this->assertArrayHasKey('DELETE', $routes);
        $this->assertArrayHasKey('/delete', $routes['DELETE']);
    }
    
    public function testAnyRouteDefinition() {
        $callback = function() { return 'Any Method'; };
        Route::any('/any', $callback);
        
        $routes = Route::build();
        
        // Should create routes for all HTTP methods
        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('POST', $routes);
        $this->assertArrayHasKey('PUT', $routes);
        $this->assertArrayHasKey('PATCH', $routes);
        $this->assertArrayHasKey('DELETE', $routes);
        
        $this->assertArrayHasKey('/any', $routes['GET']);
        $this->assertArrayHasKey('/any', $routes['POST']);
        $this->assertArrayHasKey('/any', $routes['PUT']);
        $this->assertArrayHasKey('/any', $routes['PATCH']);
        $this->assertArrayHasKey('/any', $routes['DELETE']);
    }
    
    public function testCliRouteDefinition() {
        $callback = function() { return 'CLI Command'; };
        Route::cli('test-command', $callback);
        
        $routes = Route::build();
        $this->assertArrayHasKey('CLI', $routes);
        $this->assertArrayHasKey('test-command', $routes['CLI']);
        $this->assertEquals($callback, $routes['CLI']['test-command']['handler']);
    }
    
    public function testRouteWithNamedParameters() {
        $callback = function($id, $slug) { return "ID: $id, Slug: $slug"; };
        Route::get('/posts/:integer#id/comments/:slug#slug', $callback);
        
        $routes = Route::build();
        $route = $routes['GET']['/posts/:integer/comments/:slug'];
        
        $this->assertEquals('/posts/:integer/comments/:slug', $route['route']);
        $this->assertEquals(['id', 'slug'], $route['arg_names']);
        $this->assertEquals($callback, $route['handler']);
    }
    
    public function testRouteGroup() {
        Route::group('/admin', function() {
            Route::get('/dashboard', function() { return 'Admin Dashboard'; });
            Route::get('/users', function() { return 'Admin Users'; });
        });
        
        $routes = Route::build();
        
        $this->assertArrayHasKey('/admin/dashboard', $routes['GET']);
        $this->assertArrayHasKey('/admin/users', $routes['GET']);
        $this->assertEquals('/admin/dashboard', $routes['GET']['/admin/dashboard']['route']);
        $this->assertEquals('/admin/users', $routes['GET']['/admin/users']['route']);
    }
    
    public function testNestedRouteGroups() {
        Route::group('/api', function() {
            Route::group('/v1', function() {
                Route::get('/users', function() { return 'API V1 Users'; });
            });
        });
        
        $routes = Route::build();
        
        $this->assertArrayHasKey('/api/v1/users', $routes['GET']);
        $this->assertEquals('/api/v1/users', $routes['GET']['/api/v1/users']['route']);
    }
    
    public function testRouteGroupWithMiddleware() {
        Route::group('/protected', function() {
            Route::get('/dashboard', function() { return 'Protected Dashboard'; });
        }, ['AuthMiddleware']);
        
        $routes = Route::build();
        $route = $routes['GET']['/protected/dashboard'];
        
        $this->assertArrayHasKey('middleware', $route);
        $this->assertContains('AuthMiddleware', $route['middleware']);
    }
    
    public function testAddMiddlewareToRoute() {
        Route::get('/secure', function() { return 'Secure'; })
             ->addMiddleware('SecurityMiddleware');
        
        $routes = Route::build();
        $route = $routes['GET']['/secure'];
        
        $this->assertArrayHasKey('middleware', $route);
        $this->assertContains('SecurityMiddleware', $route['middleware']);
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
    
    public function testCallbackFormatting() {
        // Test string controller@method format
        Route::get('/string-format', 'TestController@index');
        
        $routes = Route::build();
        $this->assertEquals('TestController@index', $routes['GET']['/string-format']['handler']);
    }
    
    public function testArrayCallbackFormatting() {
        // Test array [Controller::class, 'method'] format gets converted to string
        Route::get('/array-format', ['TestController', 'show']);
        
        $routes = Route::build();
        $this->assertEquals('TestController@show', $routes['GET']['/array-format']['handler']);
    }
    
    public function testSingleActionController() {
        // Test single action controller (class name only)
        Route::get('/single-action', 'TestController');
        
        $routes = Route::build();
        $this->assertEquals('TestController', $routes['GET']['/single-action']['handler']);
    }
    
    public function testGroupMiddlewareInheritance() {
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
} 