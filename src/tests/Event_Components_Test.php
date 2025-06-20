<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Events\Event;
use Gimli\Events\Event_Abstract;
use Gimli\Events\Event_Interface;

/**
 * @covers Gimli\Events\Event
 * @covers Gimli\Events\Event_Abstract
 * @covers Gimli\Events\Event_Interface
 */
class Event_Components_Test extends TestCase {

    // === EVENT ATTRIBUTE TESTS ===

    public function testEventAttributeConstruction(): void {
        $event = new Event('test.event');
        
        $this->assertEquals('test.event', $event->event_name);
        $this->assertNull($event->description);
        $this->assertEquals([], $event->tags);
        $this->assertEquals(0, $event->priority);
    }

    public function testEventAttributeWithAllParameters(): void {
        $event = new Event(
            'user.registered',
            'Triggered when a user registers',
            ['user', 'registration'],
            10
        );
        
        $this->assertEquals('user.registered', $event->event_name);
        $this->assertEquals('Triggered when a user registers', $event->description);
        $this->assertEquals(['user', 'registration'], $event->tags);
        $this->assertEquals(10, $event->priority);
    }

    public function testEventAttributeWithPartialParameters(): void {
        $event = new Event(
            'order.completed',
            'Order processing finished',
            ['order', 'commerce']
        );
        
        $this->assertEquals('order.completed', $event->event_name);
        $this->assertEquals('Order processing finished', $event->description);
        $this->assertEquals(['order', 'commerce'], $event->tags);
        $this->assertEquals(0, $event->priority); // Default value
    }

    public function testEventAttributeWithEmptyTags(): void {
        $event = new Event('test.empty.tags', 'Test event', []);
        
        $this->assertEquals('test.empty.tags', $event->event_name);
        $this->assertEquals('Test event', $event->description);
        $this->assertEquals([], $event->tags);
    }

    public function testEventAttributeWithMultipleTags(): void {
        $tags = ['user', 'authentication', 'security', 'login'];
        $event = new Event('user.login', 'User login event', $tags);
        
        $this->assertEquals($tags, $event->tags);
    }

    public function testEventAttributeWithNullDescription(): void {
        $event = new Event('test.null.description', null, ['test']);
        
        $this->assertNull($event->description);
        $this->assertEquals('test.null.description', $event->event_name);
    }

    public function testEventAttributeWithNegativePriority(): void {
        $event = new Event('test.negative.priority', 'Test event', [], -5);
        
        $this->assertEquals(-5, $event->priority);
    }

    public function testEventAttributeMultipleInstances(): void {
        $firstEvent = new Event('first.event', 'First event', ['first'], 10);
        $secondEvent = new Event('second.event', 'Second event', ['second'], 5);
        
        $this->assertEquals('first.event', $firstEvent->event_name);
        $this->assertEquals(10, $firstEvent->priority);
        
        $this->assertEquals('second.event', $secondEvent->event_name);
        $this->assertEquals(5, $secondEvent->priority);
        
        // Test that they are separate instances
        $this->assertNotSame($firstEvent, $secondEvent);
    }

    // === EVENT_INTERFACE TESTS ===

    public function testEventInterfaceMethodSignatures(): void {
        // Test that Event_Interface has the expected method signatures
        $reflection = new ReflectionClass(Event_Interface::class);
        
        $this->assertTrue($reflection->hasMethod('execute'));
        $this->assertTrue($reflection->hasMethod('validate'));
        $this->assertTrue($reflection->hasMethod('getRequiredParameters'));
        $this->assertTrue($reflection->hasMethod('getOptionalParameters'));
        
        $executeMethod = $reflection->getMethod('execute');
        $this->assertEquals('void', $executeMethod->getReturnType()->getName());
        
        $validateMethod = $reflection->getMethod('validate');
        $this->assertEquals('bool', $validateMethod->getReturnType()->getName());
    }

    // === EVENT_ABSTRACT TESTS ===

    public function testEventAbstractDefaultMethods(): void {
        // Create a concrete implementation for testing
        $eventHandler = $this->createEventHandler();
        
        // Test default implementations
        $this->assertTrue($eventHandler->validate([]));
        $this->assertTrue($eventHandler->validate(['any' => 'data']));
        $this->assertEquals([], $eventHandler->getRequiredParameters());
        $this->assertEquals([], $eventHandler->getOptionalParameters());
    }

    public function testEventAbstractImplementsInterface(): void {
        $eventHandler = $this->createEventHandler();
        
        $this->assertInstanceOf(Event_Interface::class, $eventHandler);
    }

    // === HELPER METHODS ===

    private function createEventHandler(): Event_Abstract {
        return new class extends Event_Abstract {
            public $executed = false;
            public $receivedEvent = null;
            public $receivedArgs = null;
            
            public function execute(string $event_name, array $args = []): void {
                $this->executed = true;
                $this->receivedEvent = $event_name;
                $this->receivedArgs = $args;
            }
        };
    }

    private function createEventHandlerWithValidation(): Event_Abstract {
        return new class extends Event_Abstract {
            public function validate(array $args): bool {
                return isset($args['required_field']) && !empty($args['required_field']);
            }
            
            public function execute(string $event_name, array $args = []): void {
                // Implementation
            }
        };
    }

    private function createEventHandlerWithParameters(): Event_Abstract {
        return new class extends Event_Abstract {
            public function getRequiredParameters(): array {
                return ['user_id', 'action'];
            }
            
            public function getOptionalParameters(): array {
                return ['timestamp', 'metadata'];
            }
            
            public function execute(string $event_name, array $args = []): void {
                // Implementation
            }
        };
    }

    private function createComplexEventHandler(): Event_Abstract {
        return new class extends Event_Abstract {
            public $executed = false;
            public $validationCalled = false;
            
            public function getRequiredParameters(): array {
                return ['user_id', 'updated_fields'];
            }
            
            public function getOptionalParameters(): array {
                return ['previous_values', 'timestamp'];
            }
            
            public function validate(array $args): bool {
                $this->validationCalled = true;
                
                if (!is_numeric($args['user_id']) || $args['user_id'] <= 0) {
                    return false;
                }
                
                if (!is_array($args['updated_fields']) || empty($args['updated_fields'])) {
                    return false;
                }
                
                return true;
            }
            
            public function execute(string $event_name, array $args = []): void {
                $this->executed = true;
            }
        };
    }

    // === TESTS USING HELPER METHODS ===

    public function testEventAbstractExecution(): void {
        $eventHandler = $this->createEventHandler();
        
        $eventHandler->execute('test.event', ['data' => 'value']);
        
        $this->assertTrue($eventHandler->executed);
        $this->assertEquals('test.event', $eventHandler->receivedEvent);
        $this->assertEquals(['data' => 'value'], $eventHandler->receivedArgs);
    }

    public function testEventAbstractWithCustomValidation(): void {
        $eventHandler = $this->createEventHandlerWithValidation();
        
        $this->assertTrue($eventHandler->validate(['required_field' => 'value']));
        $this->assertFalse($eventHandler->validate(['required_field' => '']));
        $this->assertFalse($eventHandler->validate(['other_field' => 'value']));
        $this->assertFalse($eventHandler->validate([]));
    }

    public function testEventAbstractWithParameters(): void {
        $eventHandler = $this->createEventHandlerWithParameters();
        
        $this->assertEquals(['user_id', 'action'], $eventHandler->getRequiredParameters());
        $this->assertEquals(['timestamp', 'metadata'], $eventHandler->getOptionalParameters());
    }

    public function testCompleteEventClass(): void {
        $eventHandler = $this->createComplexEventHandler();
        
        // Test the complete functionality
        $validArgs = [
            'user_id' => 123,
            'updated_fields' => ['name', 'email'],
            'timestamp' => time()
        ];
        
        $this->assertTrue($eventHandler->validate($validArgs));
        $this->assertTrue($eventHandler->validationCalled);
        
        $eventHandler->execute('user.profile.updated', $validArgs);
        $this->assertTrue($eventHandler->executed);
    }

    public function testEventAbstractComplexValidation(): void {
        $eventHandler = new class extends Event_Abstract {
            public function getRequiredParameters(): array {
                return ['email', 'age'];
            }
            
            public function validate(array $args): bool {
                if (!isset($args['email']) || !filter_var($args['email'], FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
                
                if (!isset($args['age']) || !is_numeric($args['age']) || $args['age'] < 0) {
                    return false;
                }
                
                return true;
            }
            
            public function execute(string $event_name, array $args = []): void {
                // Implementation
            }
        };
        
        $this->assertTrue($eventHandler->validate(['email' => 'test@example.com', 'age' => 25]));
        $this->assertFalse($eventHandler->validate(['email' => 'invalid-email', 'age' => 25]));
        $this->assertFalse($eventHandler->validate(['email' => 'test@example.com', 'age' => -5]));
        $this->assertFalse($eventHandler->validate(['email' => 'test@example.com', 'age' => 'not-a-number']));
    }

    public function testEventInterfaceImplementation(): void {
        $eventHandler = new class implements Event_Interface {
            public $executed = false;
            public $receivedEvent = null;
            public $receivedArgs = null;
            
            public function execute(string $event_name, array $args = []): void {
                $this->executed = true;
                $this->receivedEvent = $event_name;
                $this->receivedArgs = $args;
            }
            
            public function validate(array $args): bool {
                return true;
            }
            
            public function getRequiredParameters(): array {
                return ['id'];
            }
            
            public function getOptionalParameters(): array {
                return ['name'];
            }
        };
        
        // Test interface methods
        $this->assertTrue($eventHandler->validate([]));
        $this->assertEquals(['id'], $eventHandler->getRequiredParameters());
        $this->assertEquals(['name'], $eventHandler->getOptionalParameters());
        
        $eventHandler->execute('test.interface', ['id' => 123, 'name' => 'test']);
        
        $this->assertTrue($eventHandler->executed);
        $this->assertEquals('test.interface', $eventHandler->receivedEvent);
        $this->assertEquals(['id' => 123, 'name' => 'test'], $eventHandler->receivedArgs);
    }

    public function testEventInterfaceWithComplexValidation(): void {
        $eventHandler = new class implements Event_Interface {
            public function execute(string $event_name, array $args = []): void {
                // Implementation
            }
            
            public function validate(array $args): bool {
                // Complex validation logic
                if (empty($args)) {
                    return false;
                }
                
                $required = $this->getRequiredParameters();
                foreach ($required as $param) {
                    if (!array_key_exists($param, $args)) {
                        return false;
                    }
                }
                
                return true;
            }
            
            public function getRequiredParameters(): array {
                return ['user_id', 'event_type'];
            }
            
            public function getOptionalParameters(): array {
                return ['context', 'metadata'];
            }
        };
        
        $this->assertTrue($eventHandler->validate([
            'user_id' => 123,
            'event_type' => 'login',
            'context' => 'web'
        ]));
        
        $this->assertFalse($eventHandler->validate(['user_id' => 123])); // Missing event_type
        $this->assertFalse($eventHandler->validate([])); // Empty args
    }
} 