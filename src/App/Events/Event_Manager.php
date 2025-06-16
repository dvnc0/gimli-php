<?php
declare(strict_types=1);

namespace Gimli\Events;

use ReflectionClass;
use Gimli\Events\Event;
use Gimli\Events\Event_Interface;
use Throwable;

use function Gimli\Injector\resolve_fresh;

class Event_Manager {
	/**
	 * @var array<string, array<array{callback: callable|string, priority: int}>> $subscribers
	 */
	protected array $subscribers = [];

	/**
	 * @var array<string, array{description: ?string, tags: array, class: string}> $eventMetadata
	 */
	protected array $eventMetadata = [];

	/**
	 * Subscribe to an event
	 * 
	 * @param string $event Event name
	 * @param callable|string $callback Event handler
	 * @param int $priority Handler priority (higher numbers execute first)
	 * @return void
	 */
	public function subscribe(string $event, callable|string $callback, int $priority = 0): void {
		if (!isset($this->subscribers[$event])) {
			$this->subscribers[$event] = [];
		}
		
		$this->subscribers[$event][] = [
			'callback' => $callback,
			'priority' => $priority
		];
		
		// Sort by priority
		usort($this->subscribers[$event], fn($a, $b) => $b['priority'] - $a['priority']);
	}

	/**
	 * Register classes
	 * 
	 * @param array $classes_to_register
	 * @return void
	 */
	public function register(array $classes_to_register): void {
		foreach ($classes_to_register as $class_name) {
			$this->registerClass($class_name);
		}
	}

	/**
	 * Register a class
	 * 
	 * @param string $class_name
	 * @return void
	 */
	public function registerClass(string $class_name): void {
		$reflection = new ReflectionClass($class_name);
		$attributes = $reflection->getAttributes(Event::class);
		
		foreach ($attributes as $attribute) {
			$event = $attribute->newInstance();
			$event_key = $event->event_name;
			
			if (is_a($class_name, Event_Interface::class, true)) {
				$this->subscribe($event_key, $class_name, $event->priority);
				
				// Store metadata
				$this->eventMetadata[$event_key] = [
					'description' => $event->description,
					'tags' => $event->tags,
					'class' => $class_name
				];
				continue;
			}
		}
	}

	/**
	 * Get events by tag
	 * 
	 * @param string $tag
	 * @return array<string, array{description: ?string, tags: array, class: string}>
	 */
	public function getEventsByTag(string $tag): array {
		return array_filter(
			$this->eventMetadata,
			fn($metadata) => in_array($tag, $metadata['tags'])
		);
	}

	/**
	 * Validate event arguments against required and optional parameters
	 * 
	 * @param Event_Interface $instance
	 * @param array $args the arguments to validate
	 * @return bool
	 */
	protected function validateEventParameters(Event_Interface $instance, array $args): bool {
		$required = $instance->getRequiredParameters();
		$optional = $instance->getOptionalParameters();
		$allParams = array_merge($required, $optional);
		
		if (empty($allParams)) {
			return true;
		}
		
		// Check for required parameters
		foreach ($required as $param) {
			if (!array_key_exists($param, $args)) {
				return false;
			}
		}
		
		// Check for unknown parameters
		foreach ($args as $key => $value) {
			if (!in_array($key, $allParams)) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Publish an event
	 * 
	 * @param string $event
	 * @param array $args the arguments to publish
	 * @return void
	 * @throws Throwable
	 */
	public function publish(string $event, array $args = []): void {
		if (isset($this->subscribers[$event])) {
			try {
				foreach ($this->subscribers[$event] as $subscriber) {
					$callback = $subscriber['callback'];
					
					if (is_string($callback)) {
						$instance = resolve_fresh($callback);
						if (is_a($instance, Event_Interface::class)) {
							// Validate parameters
							if (!$this->validateEventParameters($instance, $args)) {
								$required = implode(', ', $instance->getRequiredParameters());
								$optional = implode(', ', $instance->getOptionalParameters());
								throw new \InvalidArgumentException(
									"Invalid arguments for event {$event}. " .
									"Required: [{$required}], Optional: [{$optional}]"
								);
							}
							
							// Run custom validation if exists
							if (method_exists($instance, 'validate')) {
								if (!$instance->validate($args)) {
									throw new \InvalidArgumentException(
										"Custom validation failed for event {$event}"
									);
								}
							}
							
							$instance->execute($event, $args);
							continue;
						}
					}
					call_user_func_array($callback, [$event, $args]);
				}
			} catch (Throwable $e) {
				error_log('Error publishing event: ' . $e->getMessage());
				throw $e;
			}
		}
	}

	/**
	 * Get all registered events
	 * 
	 * @return array<string, array{description: ?string, tags: array, class: string}>
	 */
	public function getAllEvents(): array {
		return $this->eventMetadata;
	}

	/**
	 * Get events by multiple tags
	 * 
	 * @param array $tags the tags to get events by
	 * @return array<string, array{description: ?string, tags: array, class: string}>
	 */
	public function getEventsByTags(array $tags): array {
		return array_filter(
			$this->eventMetadata,
			fn($metadata) => !empty(array_intersect($tags, $metadata['tags']))
		);
	}

	/**
	 * Check if an event has subscribers
	 * 
	 * @param string $event
	 * @return bool
	 */
	public function hasSubscribers(string $event): bool {
		return isset($this->subscribers[$event]);
	}

	/**
	 * Get the subscribers for an event
	 * 
	 * @param string $event
	 * @return array the subscribers for the event
	 */
	public function getSubscribers(string $event): array {
		return $this->subscribers[$event] ?? [];
	}

	/**
	 * Get metadata for an event
	 * 
	 * @param string $event
	 * @return array|null the metadata for the event
	 */
	public function getEventMetadata(string $event): ?array {
		return $this->eventMetadata[$event] ?? null;
	}

	/**
	 * Create a new event chain
	 * 
	 * @return Event_Chain the event chain
	 */
	public function chain(): Event_Chain {
		return new Event_Chain($this);
	}
}