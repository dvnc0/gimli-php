<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Events\Event_Chain;
use Gimli\Events\Event_Manager;

/**
 * @covers Gimli\Events\Event_Chain
 */
class Event_Chain_Test extends TestCase {
    
    private Event_Manager $eventManager;
    private Event_Chain $eventChain;
    
    protected function setUp(): void {
        $this->eventManager = new Event_Manager();
        $this->eventChain = new Event_Chain($this->eventManager);
    }
    
    public function testAddSingleEvent() {
        $result = $this->eventChain->add('test.event', ['data' => 'value']);
        
        // Should return the same instance for chaining
        $this->assertSame($this->eventChain, $result);
    }
    
    public function testAddMultipleEvents() {
        $this->eventChain
            ->add('first.event', ['data' => 'first'])
            ->add('second.event', ['data' => 'second'])
            ->add('third.event', ['data' => 'third']);
        
        // Test that we can chain multiple add calls
        $this->assertInstanceOf(Event_Chain::class, $this->eventChain);
    }
    
    public function testExecuteEventsInOrder() {
        $executionOrder = [];
        
        // Subscribe to events to track execution order
        $this->eventManager->subscribe('first.event', function($event, $data) use (&$executionOrder) {
            $executionOrder[] = 'first';
        });
        
        $this->eventManager->subscribe('second.event', function($event, $data) use (&$executionOrder) {
            $executionOrder[] = 'second';
        });
        
        $this->eventManager->subscribe('third.event', function($event, $data) use (&$executionOrder) {
            $executionOrder[] = 'third';
        });
        
        // Add events to chain and execute
        $this->eventChain
            ->add('first.event', ['data' => 'first'])
            ->add('second.event', ['data' => 'second'])
            ->add('third.event', ['data' => 'third'])
            ->execute();
        
        // Verify execution order
        $this->assertEquals(['first', 'second', 'third'], $executionOrder);
    }
    
    public function testExecuteWithEventData() {
        $receivedData = [];
        
        $this->eventManager->subscribe('data.event', function($event, $data) use (&$receivedData) {
            $receivedData[] = $data;
        });
        
        $this->eventChain
            ->add('data.event', ['message' => 'hello'])
            ->add('data.event', ['message' => 'world'])
            ->execute();
        
        $this->assertCount(2, $receivedData);
        $this->assertEquals(['message' => 'hello'], $receivedData[0]);
        $this->assertEquals(['message' => 'world'], $receivedData[1]);
    }
    
    public function testExecuteEmptyChain() {
        // Should not throw an exception when executing empty chain
        $this->eventChain->execute();
        
        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }
    
    public function testExecuteWithNonExistentEvents() {
        // Should not throw an exception even if events don't have subscribers
        $this->eventChain
            ->add('nonexistent.event1', ['data' => 'test1'])
            ->add('nonexistent.event2', ['data' => 'test2'])
            ->execute();
        
        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }
} 