# Event System

Gimli includes a flexible event system that allows you to implement the publisher-subscriber pattern in your application. This enables loose coupling between components and makes your code more maintainable and extensible.

## Core Concepts

The event system is built around these key components:

- **Event Manager**: Central hub that manages event subscriptions and publishing
- **Events**: Named triggers that can pass data to listeners
- **Event Handlers**: Classes or callables that respond to specific events
- **Event Chain**: A way to queue multiple events to be executed in sequence

## Basic Usage

### Publishing Events

To trigger an event from anywhere in your application:

```php
// Using the helper function
use function Gimli\Events\publish_event;

publish_event('user.registered', [
    'user_id' => 123,
    'email' => 'user@example.com'
]);

// Or directly using the Event Manager
use Gimli\Application_Registry;
use Gimli\Events\Event_Manager;

$Event_Manager = Application_Registry::get()->Injector->resolve(Event_Manager::class);
$Event_Manager->publish('user.registered', [
    'user_id' => 123,
    'email' => 'user@example.com'
]);
```

### Subscribing to Events

There are multiple ways to subscribe to events:

#### Using Helper Function

```php
// Using the helper function with a closure
use function Gimli\Events\subscribe_event;

subscribe_event('user.registered', function(string $event, array $data) {
    // Handle the event
    $user_id = $data['user_id'];
    // Do something with the user ID
});
```

#### Using Event Manager

```php
// Using the Event Manager directly with a closure
$Event_Manager = Application_Registry::get()->Injector->resolve(Event_Manager::class);

$Event_Manager->subscribe('user.registered', function(string $event, array $data) {
    // Handle the event
}, 10); // Optional priority - higher numbers execute first
```

#### Using Event Classes

For more complex event handling, you can create dedicated event handler classes:

```php
<?php
declare(strict_types=1);

namespace App\Events;

use Gimli\Events\Event_Abstract;
use Gimli\Events\Event;

#[Event('user.registered', description: 'Triggered when a user registers', tags: ['user', 'registration'], priority: 10)]
class User_Registration_Handler extends Event_Abstract {
    // Define required parameters
    public function getRequiredParameters(): array {
        return ['user_id', 'email'];
    }
    
    // Define optional parameters
    public function getOptionalParameters(): array {
        return ['referral_code'];
    }
    
    // Custom validation if needed
    public function validate(array $args): bool {
        return filter_var($args['email'], FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // Handle the event
    public function execute(string $event_name, array $args = []): void {
        $user_id = $args['user_id'];
        $email = $args['email'];
        
        // Your event handling logic here
        // For example, send a welcome email
    }
}
```

#### Registering Event Classes

```php
// Register a single event handler class
$Event_Manager = Application_Registry::get()->Injector->resolve(Event_Manager::class);
$Event_Manager->registerClass(User_Registration_Handler::class);

// Register multiple event handler classes
$Event_Manager->register([
    User_Registration_Handler::class,
    User_Login_Handler::class,
    User_Password_Reset_Handler::class
]);

// In Config.php
'events' => [
    User_Registration_Handler::class,
    User_Login_Handler::class,
    User_Password_Reset_Handler::class
]

// Helper function to get event manager
event_manager()->register([
    User_Registration_Handler::class,
    User_Login_Handler::class,
    User_Password_Reset_Handler::class
]);
```

## Event Chains

Event chains allow you to queue multiple events to be executed in sequence:

```php
// Using the helper function
use function Gimli\Events\chain_events;

chain_events()
    ->add('user.validated', ['user_id' => 123])
    ->add('user.registered', ['user_id' => 123, 'email' => 'user@example.com'])
    ->add('notification.send', ['recipient' => 'user@example.com', 'template' => 'welcome'])
    ->execute();

// Or using the Event Manager directly
$chain = $Event_Manager->chain();
$chain->add('user.validated', ['user_id' => 123])
      ->add('user.registered', ['user_id' => 123, 'email' => 'user@example.com'])
      ->add('notification.send', ['recipient' => 'user@example.com', 'template' => 'welcome'])
      ->execute();
```

## Event Tagging and Metadata

Events can be tagged and given descriptions for better organization and documentation:

```php
#[Event('backup.complete', 
    description: 'Triggered when a backup process completes', 
    tags: ['backup', 'system', 'maintenance'], 
    priority: 5)]
class Backup_Complete_Handler extends Event_Abstract {
    // ...
}
```

You can then retrieve events by tag:

```php
// Get all events with a specific tag
use function Gimli\Events\get_events_by_tag;

$backupEvents = get_events_by_tag('backup');

// Or using the Event Manager
$systemEvents = $Event_Manager->getEventsByTag('system');

// Get events with any of multiple tags
$maintenanceEvents = $Event_Manager->getEventsByTags(['backup', 'maintenance']);
```

## Event Priorities

When multiple handlers are subscribed to the same event, you can control their execution order using priorities:

```php
// Higher priority (20) executes before lower priority (10)
$Event_Manager->subscribe('user.registered', $firstHandler, 20);
$Event_Manager->subscribe('user.registered', $secondHandler, 10);

// Or set the priority in the Event attribute
#[Event('user.registered', priority: 20)]
class High_Priority_Handler extends Event_Abstract {
    // ...
}
```

## Application Events

Gimli Framework itself publishes several system events you can hook into:

```php
// Example system events you can subscribe to
$Event_Manager->subscribe('gimli.application.start', function($event, $args) {
    // Application has started
    $startTime = $args['time'];
});

$Event_Manager->subscribe('gimli.application.end', function($event, $args) {
    // Application is finishing
    $endTime = $args['time'];
    $executionTime = $endTime - $startTime;
});
```

## Best Practices

1. **Use descriptive event names**:
   - Use dot notation for namespacing (e.g., 'user.registered', 'order.completed')
   - Make names action-oriented and past tense to indicate something that has happened

2. **Include complete data**:
   - Pass all relevant data with the event to avoid subscribers needing to fetch additional data
   - Use the required/optional parameter definitions to enforce data contracts

3. **Keep handlers focused**:
   - Each handler should do one thing well
   - For complex workflows, chain multiple focused events together

4. **Document your events**:
   - Use descriptions and tags in Event attributes
   - Consider creating an events catalog for your application

5. **Be careful with exceptions**:
   - Event handlers that throw exceptions will interrupt the execution of subsequent handlers
   - Consider catching exceptions within your handlers if appropriate 