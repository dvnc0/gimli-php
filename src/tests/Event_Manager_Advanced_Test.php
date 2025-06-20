<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Events\Event_Manager;
use Gimli\Events\Event_Interface;
use Gimli\Events\Event_Abstract;
use Gimli\Events\Event;
use Gimli\Application;
use Gimli\Application_Registry;

/**
 * @covers Gimli\Events\Event_Manager
 */
class Event_Manager_Advanced_Test extends TestCase {

    private Event_Manager $eventManager;
    private string $tempDir;
    private array $serverVars;

    protected function setUp(): void {
        // Clear any existing Application_Registry
        Application_Registry::clear();
        
        // Create temporary directory for testing
        $this->tempDir = sys_get_temp_dir() . '/event_manager_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        // Setup server variables for Application
        $this->serverVars = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php'
        ];
        
        // Create Application with proper setup
        $app = Application::create($this->tempDir, $this->serverVars);
        Application_Registry::set($app);
        
        $this->eventManager = new Event_Manager();
    }

    protected function tearDown(): void {
        Application_Registry::clear();
        
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    // === PARAMETER VALIDATION TESTS ===

    public function testParameterValidationWithRequiredParameters(): void {
        // Create a test event class with required parameters
        $testEvent = new class extends Event_Abstract {
            public function getRequiredParameters(): array {
                return ['user_id', 'email'];
            }
            
            public function execute(string $event_name, array $args = []): void {
                // Test implementation
            }
        };
        
        $className = get_class($testEvent);
        $this->eventManager->subscribe('test.validation', $className);
        
        // Test with missing required parameters
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid arguments for event test.validation');
        
        $this->eventManager->publish('test.validation', ['user_id' => 123]); // Missing email
    }

    public function testParameterValidationWithValidParameters(): void {
        $executed = false;
        
        $testEvent = new class extends Event_Abstract {
            public $executed = false;
            
            public function getRequiredParameters(): array {
                return ['user_id', 'email'];
            }
            
            public function getOptionalParameters(): array {
                return ['name'];
            }
            
            public function execute(string $event_name, array $args = []): void {
                $this->executed = true;
            }
        };
        
        $className = get_class($testEvent);
        $this->eventManager->subscribe('test.valid', $className);
        
        // Should not throw exception with valid parameters
        $this->eventManager->publish('test.valid', [
            'user_id' => 123,
            'email' => 'test@example.com',
            'name' => 'John Doe' // Optional parameter
        ]);
        
        // Verify execution happened without exception
        $this->assertTrue(true);
    }

    public function testParameterValidationWithUnknownParameters(): void {
        $testEvent = new class extends Event_Abstract {
            public function getRequiredParameters(): array {
                return ['user_id'];
            }
            
            public function getOptionalParameters(): array {
                return ['email'];
            }
            
            public function execute(string $event_name, array $args = []): void {
                // Test implementation
            }
        };
        
        $className = get_class($testEvent);
        $this->eventManager->subscribe('test.unknown', $className);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid arguments for event test.unknown');
        
        $this->eventManager->publish('test.unknown', [
            'user_id' => 123,
            'unknown_param' => 'value' // This should cause validation to fail
        ]);
    }

    public function testParameterValidationWithNoParameters(): void {
        $executed = false;
        
        $testEvent = new class extends Event_Abstract {
            public $executed = false;
            
            public function execute(string $event_name, array $args = []): void {
                $this->executed = true;
            }
        };
        
        $className = get_class($testEvent);
        $this->eventManager->subscribe('test.no.params', $className);
        
        // Should work with any parameters when none are defined
        $this->eventManager->publish('test.no.params', ['any' => 'data']);
        
        $this->assertTrue(true);
    }

    // === CUSTOM VALIDATION TESTS ===

    public function testCustomValidationSuccess(): void {
        $executed = false;
        
        $testEvent = new class extends Event_Abstract {
            public $executed = false;
            
            public function getRequiredParameters(): array {
                return ['email'];
            }
            
            public function validate(array $args): bool {
                return filter_var($args['email'], FILTER_VALIDATE_EMAIL) !== false;
            }
            
            public function execute(string $event_name, array $args = []): void {
                $this->executed = true;
            }
        };
        
        $className = get_class($testEvent);
        $this->eventManager->subscribe('test.custom.valid', $className);
        
        // Should succeed with valid email
        $this->eventManager->publish('test.custom.valid', ['email' => 'test@example.com']);
        
        $this->assertTrue(true);
    }

    public function testCustomValidationFailure(): void {
        $testEvent = new class extends Event_Abstract {
            public function getRequiredParameters(): array {
                return ['email'];
            }
            
            public function validate(array $args): bool {
                return filter_var($args['email'], FILTER_VALIDATE_EMAIL) !== false;
            }
            
            public function execute(string $event_name, array $args = []): void {
                // Should not be called
            }
        };
        
        $className = get_class($testEvent);
        $this->eventManager->subscribe('test.custom.invalid', $className);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom validation failed for event test.custom.invalid');
        
        $this->eventManager->publish('test.custom.invalid', ['email' => 'invalid-email']);
    }

    // === ERROR HANDLING TESTS ===

    public function testPublishWithCallbackException(): void {
        $this->eventManager->subscribe('test.exception', function($event, $data) {
            throw new RuntimeException('Test exception from callback');
        });
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception from callback');
        
        $this->eventManager->publish('test.exception');
    }

    public function testPublishWithEventClassException(): void {
        $testEvent = new class extends Event_Abstract {
            public function execute(string $event_name, array $args = []): void {
                throw new RuntimeException('Test exception from event class');
            }
        };
        
        $className = get_class($testEvent);
        $this->eventManager->subscribe('test.class.exception', $className);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test exception from event class');
        
        $this->eventManager->publish('test.class.exception');
    }

    // === REGISTER METHOD TESTS ===

    public function testRegisterMultipleClasses(): void {
        // Create test event classes
        $testEvent1 = new class extends Event_Abstract {
            public $executed = false;
            
            public function execute(string $event_name, array $args = []): void {
                $this->executed = true;
            }
        };
        
        $testEvent2 = new class extends Event_Abstract {
            public $executed = false;
            
            public function execute(string $event_name, array $args = []): void {
                $this->executed = true;
            }
        };
        
        $classes = [get_class($testEvent1), get_class($testEvent2)];
        
        // Should not throw exception (though without attributes, nothing will be registered)
        $this->eventManager->register($classes);
        
        $this->assertTrue(true);
    }

    // === ADVANCED SUBSCRIBER TESTS ===

    public function testStringCallbackSubscription(): void {
        $testEvent = new class extends Event_Abstract {
            public $executed = false;
            public $receivedEvent = null;
            public $receivedArgs = null;
            
            public function execute(string $event_name, array $args = []): void {
                $this->executed = true;
                $this->receivedEvent = $event_name;
                $this->receivedArgs = $args;
            }
        };
        
        $className = get_class($testEvent);
        $this->eventManager->subscribe('test.string.callback', $className);
        
        $this->eventManager->publish('test.string.callback', ['test' => 'data']);
        
        // Verify that the event was processed (no exception means success)
        $this->assertTrue(true);
    }

    public function testNonEventInterfaceStringCallback(): void {
        // Create a class that doesn't implement Event_Interface
        $nonEventClass = new class {
            public function someMethod() {
                return 'not an event';
            }
        };
        
        $className = get_class($nonEventClass);
        $this->eventManager->subscribe('test.non.event', $className);
        
        // This should be treated as a regular callable and fail
        $this->expectException(TypeError::class);
        
        $this->eventManager->publish('test.non.event');
    }

    // === METADATA TESTS ===

    public function testGetEventsByMultipleTags(): void {
        // Test with empty metadata (no events registered with attributes)
        $events = $this->eventManager->getEventsByTags(['tag1', 'tag2']);
        
        $this->assertIsArray($events);
        $this->assertEmpty($events);
    }

    public function testGetEventsByTagsWithEmptyArray(): void {
        $events = $this->eventManager->getEventsByTags([]);
        
        $this->assertIsArray($events);
        $this->assertEmpty($events);
    }

    public function testGetAllEventsEmpty(): void {
        $events = $this->eventManager->getAllEvents();
        
        $this->assertIsArray($events);
        $this->assertEmpty($events);
    }

    // === PRIORITY ORDERING TESTS ===

    public function testSubscriberPriorityOrdering(): void {
        $executionOrder = [];
        
        // Add subscribers with different priorities
        $this->eventManager->subscribe('test.priority.order', function() use (&$executionOrder) {
            $executionOrder[] = 'priority-5';
        }, 5);
        
        $this->eventManager->subscribe('test.priority.order', function() use (&$executionOrder) {
            $executionOrder[] = 'priority-10';
        }, 10);
        
        $this->eventManager->subscribe('test.priority.order', function() use (&$executionOrder) {
            $executionOrder[] = 'priority-1';
        }, 1);
        
        $this->eventManager->subscribe('test.priority.order', function() use (&$executionOrder) {
            $executionOrder[] = 'priority-15';
        }, 15);
        
        $this->eventManager->publish('test.priority.order');
        
        // Should execute in descending priority order
        $this->assertEquals(['priority-15', 'priority-10', 'priority-5', 'priority-1'], $executionOrder);
    }

    // === EDGE CASES ===

    public function testPublishEmptyEventName(): void {
        // Should not crash with empty event name
        $this->eventManager->publish('');
        
        $this->assertTrue(true);
    }

    public function testSubscribeEmptyEventName(): void {
        $callback = function() {};
        
        // Should not crash with empty event name
        $this->eventManager->subscribe('', $callback);
        
        $this->assertTrue(true);
    }

    public function testHasSubscribersEmptyEventName(): void {
        $result = $this->eventManager->hasSubscribers('');
        
        $this->assertIsBool($result);
    }

    public function testGetSubscribersEmptyEventName(): void {
        $subscribers = $this->eventManager->getSubscribers('');
        
        $this->assertIsArray($subscribers);
    }

    public function testChainMethodReturnsEventChain(): void {
        $chain = $this->eventManager->chain();
        
        $this->assertInstanceOf(Gimli\Events\Event_Chain::class, $chain);
    }

    // === COMPLEX INTEGRATION TESTS ===

    public function testComplexEventWorkflow(): void {
        $workflow = [];
        
        // Set up a complex workflow with different types of subscribers
        $this->eventManager->subscribe('workflow.start', function($event, $data) use (&$workflow) {
            $workflow[] = 'started: ' . $data['process_id'];
        }, 10);
        
        $this->eventManager->subscribe('workflow.start', function($event, $data) use (&$workflow) {
            $workflow[] = 'logging start';
        }, 5);
        
        // Add an event class subscriber
        $processEvent = new class extends Event_Abstract {
            public function getRequiredParameters(): array {
                return ['process_id', 'data'];
            }
            
            public function execute(string $event_name, array $args = []): void {
                // Simulate processing
            }
        };
        
        $this->eventManager->subscribe('workflow.process', get_class($processEvent));
        
        $this->eventManager->subscribe('workflow.complete', function($event, $data) use (&$workflow) {
            $workflow[] = 'completed: ' . $data['process_id'];
        });
        
        // Execute the workflow
        $this->eventManager->publish('workflow.start', ['process_id' => 'proc-123']);
        $this->eventManager->publish('workflow.process', ['process_id' => 'proc-123', 'data' => 'test']);
        $this->eventManager->publish('workflow.complete', ['process_id' => 'proc-123']);
        
        $this->assertCount(3, $workflow);
        $this->assertEquals('started: proc-123', $workflow[0]); // Higher priority first
        $this->assertEquals('logging start', $workflow[1]);
        $this->assertEquals('completed: proc-123', $workflow[2]);
    }
} 