<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Application;
use Gimli\Application_Registry;
use Gimli\Events\Event_Manager;
use Gimli\Events\Event_Chain;

use function Gimli\Events\publish_event;
use function Gimli\Events\subscribe_event;
use function Gimli\Events\chain_events;
use function Gimli\Events\get_events_by_tag;
use function Gimli\Events\event_manager;

/**
 * @covers Gimli\Events\publish_event
 * @covers Gimli\Events\subscribe_event
 * @covers Gimli\Events\chain_events
 * @covers Gimli\Events\get_events_by_tag
 * @covers Gimli\Events\event_manager
 */
class Event_Helpers_Test extends TestCase {

    private string $tempDir;
    private array $serverVars;

    protected function setUp(): void {
        // Clear any existing Application_Registry
        Application_Registry::clear();
        
        // Create temporary directory for testing
        $this->tempDir = sys_get_temp_dir() . '/event_helpers_test_' . uniqid();
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
    }

    protected function tearDown(): void {
        // Clean up after each test
        Application_Registry::clear();
        
        // Remove temporary directory
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    // === PUBLISH_EVENT HELPER TESTS ===

    public function testPublishEventHelper(): void {
        $called = false;
        $receivedEvent = null;
        $receivedData = null;
        
        // Subscribe using the Event_Manager directly to verify helper works
        $eventManager = event_manager();
        $eventManager->subscribe('test.publish', function($event, $data) use (&$called, &$receivedEvent, &$receivedData) {
            $called = true;
            $receivedEvent = $event;
            $receivedData = $data;
        });
        
        // Use the helper to publish
        publish_event('test.publish', ['message' => 'hello world']);
        
        $this->assertTrue($called);
        $this->assertEquals('test.publish', $receivedEvent);
        $this->assertEquals(['message' => 'hello world'], $receivedData);
    }

    public function testPublishEventHelperWithoutData(): void {
        $called = false;
        
        $eventManager = event_manager();
        $eventManager->subscribe('test.empty', function($event, $data) use (&$called) {
            $called = true;
            $this->assertEquals([], $data);
        });
        
        publish_event('test.empty');
        
        $this->assertTrue($called);
    }

    public function testPublishEventHelperWithComplexData(): void {
        $receivedData = null;
        
        $eventManager = event_manager();
        $eventManager->subscribe('test.complex', function($event, $data) use (&$receivedData) {
            $receivedData = $data;
        });
        
        $complexData = [
            'user' => ['id' => 123, 'name' => 'John'],
            'action' => 'login',
            'timestamp' => time(),
            'metadata' => ['ip' => '127.0.0.1', 'user_agent' => 'test']
        ];
        
        publish_event('test.complex', $complexData);
        
        $this->assertEquals($complexData, $receivedData);
    }

    // === SUBSCRIBE_EVENT HELPER TESTS ===

    public function testSubscribeEventHelper(): void {
        $called = false;
        $receivedData = null;
        
        subscribe_event('test.subscribe', function($event, $data) use (&$called, &$receivedData) {
            $called = true;
            $receivedData = $data;
        });
        
        // Verify subscription worked by publishing
        publish_event('test.subscribe', ['test' => 'data']);
        
        $this->assertTrue($called);
        $this->assertEquals(['test' => 'data'], $receivedData);
    }

    public function testSubscribeEventHelperWithCallable(): void {
        $called = false;
        
        $callback = function($event, $data) use (&$called) {
            $called = true;
            $this->assertEquals('test.callable', $event);
        };
        
        subscribe_event('test.callable', $callback);
        publish_event('test.callable');
        
        $this->assertTrue($called);
    }

    public function testSubscribeEventHelperMultipleSubscribers(): void {
        $counter = 0;
        
        subscribe_event('test.multiple', function() use (&$counter) {
            $counter++;
        });
        
        subscribe_event('test.multiple', function() use (&$counter) {
            $counter += 2;
        });
        
        subscribe_event('test.multiple', function() use (&$counter) {
            $counter += 3;
        });
        
        publish_event('test.multiple');
        
        $this->assertEquals(6, $counter);
    }

    // === CHAIN_EVENTS HELPER TESTS ===

    public function testChainEventsHelper(): void {
        $executionOrder = [];
        
        // Subscribe to events to track execution
        subscribe_event('chain.first', function() use (&$executionOrder) {
            $executionOrder[] = 'first';
        });
        
        subscribe_event('chain.second', function() use (&$executionOrder) {
            $executionOrder[] = 'second';
        });
        
        subscribe_event('chain.third', function() use (&$executionOrder) {
            $executionOrder[] = 'third';
        });
        
        // Use the helper to create and execute chain
        chain_events()
            ->add('chain.first')
            ->add('chain.second')
            ->add('chain.third')
            ->execute();
        
        $this->assertEquals(['first', 'second', 'third'], $executionOrder);
    }

    public function testChainEventsHelperReturnsEventChain(): void {
        $chain = chain_events();
        
        $this->assertInstanceOf(Event_Chain::class, $chain);
    }

    public function testChainEventsHelperWithData(): void {
        $receivedData = [];
        
        subscribe_event('chain.data', function($event, $data) use (&$receivedData) {
            $receivedData[] = $data;
        });
        
        chain_events()
            ->add('chain.data', ['step' => 1])
            ->add('chain.data', ['step' => 2])
            ->add('chain.data', ['step' => 3])
            ->execute();
        
        $this->assertCount(3, $receivedData);
        $this->assertEquals(['step' => 1], $receivedData[0]);
        $this->assertEquals(['step' => 2], $receivedData[1]);
        $this->assertEquals(['step' => 3], $receivedData[2]);
    }

    // === GET_EVENTS_BY_TAG HELPER TESTS ===

    public function testGetEventsByTagHelper(): void {
        // Since we can't easily test with actual event classes,
        // test the empty case (no events registered with tags)
        $events = get_events_by_tag('test-tag');
        
        $this->assertIsArray($events);
        $this->assertEmpty($events);
    }

    public function testGetEventsByTagHelperWithNonExistentTag(): void {
        $events = get_events_by_tag('nonexistent-tag');
        
        $this->assertIsArray($events);
        $this->assertEmpty($events);
    }

    // === EVENT_MANAGER HELPER TESTS ===

    public function testEventManagerHelper(): void {
        $manager = event_manager();
        
        $this->assertInstanceOf(Event_Manager::class, $manager);
    }

    public function testEventManagerHelperReturnsSameInstance(): void {
        $manager1 = event_manager();
        $manager2 = event_manager();
        
        // Should be the same instance from the DI container
        $this->assertSame($manager1, $manager2);
    }

    public function testEventManagerHelperCanBeUsedDirectly(): void {
        $called = false;
        
        $manager = event_manager();
        $manager->subscribe('test.direct', function() use (&$called) {
            $called = true;
        });
        
        publish_event('test.direct');
        
        $this->assertTrue($called);
    }

    // === INTEGRATION TESTS ===

    public function testHelpersWorkTogether(): void {
        $workflow = [];
        
        // Use helper to subscribe
        subscribe_event('workflow.start', function() use (&$workflow) {
            $workflow[] = 'started';
        });
        
        subscribe_event('workflow.process', function($event, $data) use (&$workflow) {
            $workflow[] = 'processing: ' . $data['item'];
        });
        
        subscribe_event('workflow.complete', function() use (&$workflow) {
            $workflow[] = 'completed';
        });
        
        // Use helper to publish individual events
        publish_event('workflow.start');
        publish_event('workflow.process', ['item' => 'task1']);
        publish_event('workflow.process', ['item' => 'task2']);
        
        // Use helper to chain final events
        chain_events()
            ->add('workflow.process', ['item' => 'task3'])
            ->add('workflow.complete')
            ->execute();
        
        $expected = [
            'started',
            'processing: task1',
            'processing: task2',
            'processing: task3',
            'completed'
        ];
        
        $this->assertEquals($expected, $workflow);
    }

    public function testHelperFunctionsExist(): void {
        // Verify all helper functions are defined
        $this->assertTrue(function_exists('Gimli\Events\publish_event'));
        $this->assertTrue(function_exists('Gimli\Events\subscribe_event'));
        $this->assertTrue(function_exists('Gimli\Events\chain_events'));
        $this->assertTrue(function_exists('Gimli\Events\get_events_by_tag'));
        $this->assertTrue(function_exists('Gimli\Events\event_manager'));
    }

    public function testHelperFunctionsWithApplicationRegistry(): void {
        // Test that helpers properly use Application_Registry
        $originalApp = Application_Registry::get();
        
        // Clear registry temporarily
        Application_Registry::clear();
        
        // Should throw exception when no app is registered
        $this->expectException(Exception::class);
        publish_event('test.no.app');
    }
} 