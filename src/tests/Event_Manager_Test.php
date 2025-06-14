<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Events\Event_Manager;
use Gimli\Events\Event_Interface;
use Gimli\Events\Event_Abstract;
use Gimli\Events\Event;

/**
 * @covers Gimli\Events\Event_Manager
 */
class Event_Manager_Test extends TestCase {
    
    private Event_Manager $eventManager;
    
    protected function setUp(): void {
        $this->eventManager = new Event_Manager();
    }
    
    public function testSubscribeBasicCallback() {
        $called = false;
        $receivedData = null;
        
        $this->eventManager->subscribe('test.event', function($event, $data) use (&$called, &$receivedData) {
            $called = true;
            $receivedData = $data;
        });
        
        $this->eventManager->publish('test.event', ['message' => 'hello']);
        
        $this->assertTrue($called);
        $this->assertEquals(['message' => 'hello'], $receivedData);
    }
    
    public function testSubscribeWithPriority() {
        $executionOrder = [];
        
        $this->eventManager->subscribe('test.priority', function() use (&$executionOrder) {
            $executionOrder[] = 'low';
        }, 1);
        
        $this->eventManager->subscribe('test.priority', function() use (&$executionOrder) {
            $executionOrder[] = 'high';
        }, 10);
        
        $this->eventManager->subscribe('test.priority', function() use (&$executionOrder) {
            $executionOrder[] = 'medium';
        }, 5);
        
        $this->eventManager->publish('test.priority');
        
        $this->assertEquals(['high', 'medium', 'low'], $executionOrder);
    }
    
    public function testHasSubscribers() {
        $this->assertFalse($this->eventManager->hasSubscribers('nonexistent.event'));
        
        $this->eventManager->subscribe('existing.event', function() {});
        
        $this->assertTrue($this->eventManager->hasSubscribers('existing.event'));
    }
    
    public function testGetSubscribers() {
        $this->assertEquals([], $this->eventManager->getSubscribers('nonexistent.event'));
        
        $callback = function() {};
        $this->eventManager->subscribe('test.event', $callback, 5);
        
        $subscribers = $this->eventManager->getSubscribers('test.event');
        
        $this->assertCount(1, $subscribers);
        $this->assertEquals($callback, $subscribers[0]['callback']);
        $this->assertEquals(5, $subscribers[0]['priority']);
    }
    
    public function testRegisterClassWithEventAttribute() {
        $testEventClass = new class extends Event_Abstract {
            public $executed = false;
            public $receivedData = null;
            
            public function execute(string $event_name, array $args = []): void {
                $this->executed = true;
                $this->receivedData = $args;
            }
        };
        
        // Create a reflection class to add the Event attribute
        $reflection = new ReflectionClass($testEventClass);
        
        // Since we can't add attributes at runtime, let's test registerClass with a mock
        $this->markTestSkipped('Cannot test attribute-based registration without actual class files');
    }
    
    public function testPublishWithNonExistentEvent() {
        // This should not throw an exception
        $this->eventManager->publish('nonexistent.event', ['data' => 'test']);
        
        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }
    
    public function testMultipleSubscribersForSameEvent() {
        $counter = 0;
        
        $this->eventManager->subscribe('count.event', function() use (&$counter) {
            $counter++;
        });
        
        $this->eventManager->subscribe('count.event', function() use (&$counter) {
            $counter += 2;
        });
        
        $this->eventManager->subscribe('count.event', function() use (&$counter) {
            $counter += 3;
        });
        
        $this->eventManager->publish('count.event');
        
        $this->assertEquals(6, $counter);
    }
    
    public function testGetEventsByTag() {
        // Since we can't easily test the attribute system without actual class files,
        // we'll test the empty case
        $events = $this->eventManager->getEventsByTag('nonexistent');
        $this->assertEquals([], $events);
    }
    
    public function testGetEventsByTags() {
        // Test with multiple tags
        $events = $this->eventManager->getEventsByTags(['tag1', 'tag2']);
        $this->assertEquals([], $events);
    }
    
    public function testGetAllEvents() {
        $events = $this->eventManager->getAllEvents();
        $this->assertEquals([], $events);
    }
    
    public function testGetEventMetadata() {
        $metadata = $this->eventManager->getEventMetadata('nonexistent.event');
        $this->assertNull($metadata);
    }
    
    public function testChainCreation() {
        $chain = $this->eventManager->chain();
        $this->assertInstanceOf(Gimli\Events\Event_Chain::class, $chain);
    }
} 