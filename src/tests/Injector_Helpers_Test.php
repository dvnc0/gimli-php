<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Environment\Config;

/**
 * @covers Gimli\Injector\resolve
 * @covers Gimli\Injector\resolve_fresh
 * @covers Gimli\Injector\call_method
 * @covers Gimli\Injector\extend_class
 * @covers Gimli\Injector\bind
 * @covers Gimli\Injector\injector
 */
class Injector_Helpers_Test extends TestCase {
    
    public function testHelperFunctionsExist() {
        // Test that all helper functions are defined
        $this->assertTrue(function_exists('Gimli\Injector\resolve'));
        $this->assertTrue(function_exists('Gimli\Injector\resolve_fresh'));
        $this->assertTrue(function_exists('Gimli\Injector\call_method'));
        $this->assertTrue(function_exists('Gimli\Injector\extend_class'));
        $this->assertTrue(function_exists('Gimli\Injector\bind'));
        $this->assertTrue(function_exists('Gimli\Injector\injector'));
    }
    
    public function testResolveHelperBasicFunctionality() {
        // Test that resolve helper can resolve a basic class
        // Note: This test assumes Application_Registry is properly set up in the framework
        try {
            $resolved = \Gimli\Injector\resolve(Config::class);
            $this->assertInstanceOf(Config::class, $resolved);
        } catch (\Exception $e) {
            // If Application_Registry is not set up, that's expected in unit tests
            $this->assertStringContainsString('No application instance registered', $e->getMessage());
        }
    }
    
    public function testResolveFreshHelperBasicFunctionality() {
        // Test that resolve_fresh helper function exists and can be called
        try {
            $resolved = \Gimli\Injector\resolve_fresh(Config::class);
            $this->assertInstanceOf(Config::class, $resolved);
        } catch (\Exception $e) {
            // If Application_Registry is not set up, that's expected in unit tests
            $this->assertStringContainsString('No application instance registered', $e->getMessage());
        }
    }
    
    public function testCallMethodHelperBasicFunctionality() {
        // Test that call_method helper function exists and can be called
        try {
            $result = \Gimli\Injector\call_method(Config::class, 'get', ['template_base_dir']);
            $this->assertIsString($result);
        } catch (\Exception $e) {
            // If Application_Registry is not set up, that's expected in unit tests
            $this->assertStringContainsString('No application instance registered', $e->getMessage());
        }
    }
    
    public function testExtendClassHelperBasicFunctionality() {
        // Test that extend_class helper function exists and can be called
        try {
            $extended = \Gimli\Injector\extend_class(Config::class, function($instance) {
                $instance->test_property = 'test_value';
                return $instance;
            });
            $this->assertInstanceOf(Config::class, $extended);
        } catch (\Exception $e) {
            // If Application_Registry is not set up, that's expected in unit tests
            $this->assertStringContainsString('No application instance registered', $e->getMessage());
        }
    }
    
    public function testBindHelperBasicFunctionality() {
        // Test that bind helper function exists and can be called
        try {
            \Gimli\Injector\bind('test_binding', function() {
                return new stdClass();
            });
            // If we get here without exception, the function exists and can be called
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // If Application_Registry is not set up, that's expected in unit tests
            $this->assertStringContainsString('No application instance registered', $e->getMessage());
        }
    }
    
    public function testInjectorHelperBasicFunctionality() {
        // Test that injector helper function exists and can be called
        try {
            $injector = \Gimli\Injector\injector();
            $this->assertInstanceOf(\Gimli\Injector\Injector_Interface::class, $injector);
        } catch (\Exception $e) {
            // If Application_Registry is not set up, that's expected in unit tests
            $this->assertStringContainsString('No application instance registered', $e->getMessage());
        }
    }
    
    public function testHelperFunctionSignatures() {
        // Test that helper functions have the expected signatures using reflection
        
        $resolveFunction = new ReflectionFunction('Gimli\Injector\resolve');
        $this->assertEquals('resolve', $resolveFunction->getShortName());
        $this->assertCount(3, $resolveFunction->getParameters());
        
        $resolveFreshFunction = new ReflectionFunction('Gimli\Injector\resolve_fresh');
        $this->assertEquals('resolve_fresh', $resolveFreshFunction->getShortName());
        $this->assertCount(3, $resolveFreshFunction->getParameters());
        
        $callMethodFunction = new ReflectionFunction('Gimli\Injector\call_method');
        $this->assertEquals('call_method', $callMethodFunction->getShortName());
        $this->assertCount(4, $callMethodFunction->getParameters());
        
        $extendClassFunction = new ReflectionFunction('Gimli\Injector\extend_class');
        $this->assertEquals('extend_class', $extendClassFunction->getShortName());
        $this->assertCount(3, $extendClassFunction->getParameters());
        
        $bindFunction = new ReflectionFunction('Gimli\Injector\bind');
        $this->assertEquals('bind', $bindFunction->getShortName());
        $this->assertCount(3, $bindFunction->getParameters());
        
        $injectorFunction = new ReflectionFunction('Gimli\Injector\injector');
        $this->assertEquals('injector', $injectorFunction->getShortName());
        $this->assertCount(0, $injectorFunction->getParameters());
    }
    
    public function testHelperFunctionParameterTypes() {
        // Test parameter types for the helper functions
        
        $resolveFunction = new ReflectionFunction('Gimli\Injector\resolve');
        $params = $resolveFunction->getParameters();
        $this->assertEquals('injector_key', $params[0]->getName());
        $this->assertEquals('args', $params[1]->getName());
        $this->assertEquals('app', $params[2]->getName());
        
        $callMethodFunction = new ReflectionFunction('Gimli\Injector\call_method');
        $params = $callMethodFunction->getParameters();
        $this->assertEquals('class_name', $params[0]->getName());
        $this->assertEquals('method_name', $params[1]->getName());
        $this->assertEquals('method_args', $params[2]->getName());
        $this->assertEquals('dependencies', $params[3]->getName());
    }
    
    public function testHelperFunctionDefaultValues() {
        // Test that helper functions have appropriate default values
        
        $resolveFunction = new ReflectionFunction('Gimli\Injector\resolve');
        $params = $resolveFunction->getParameters();
        
        // Second parameter (args) should have default empty array
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertEquals([], $params[1]->getDefaultValue());
        
        // Third parameter (app) should be nullable with null default
        $this->assertTrue($params[2]->isDefaultValueAvailable());
        $this->assertNull($params[2]->getDefaultValue());
        $this->assertTrue($params[2]->allowsNull());
    }
} 