<?php
declare(strict_types=1);

namespace Gimli\Events;

use ReflectionClass;
use Gimli\Events\Event;

use function Gimli\Injector\resolve_fresh;

class Event_Manager {
	/**
	 * @var array $subscribers
	 */
	protected array $subscribers = [];

	/**
	 * Constructor
	 * 
	 * @param string $event
	 * @param callable|Event_Interface $callback
	 * 
	 * @return void
	 */
	public function subscribe(string $event, callable|Event_Interface $callback): void {
		if (!isset($this->subscribers[$event])) {
			$this->subscribers[$event] = [];
		}
		$this->subscribers[$event][] = $callback;
	}

		/**
	 * Register a class
	 * 
	 * @param string $class_name
	 * 
	 * @return void
	 */
	public function register(string $class_name): void  {
		$reflection = new ReflectionClass($class_name);
		$attributes = $reflection->getAttributes(Event::class);
		foreach ($attributes as $attribute) {
			$event_name = $attribute->newInstance()->event_name;
			if (is_a($class_name, Event_Interface::class, true)) {
				$this->subscribe($event_name, resolve_fresh($class_name));
				continue;
			}
		}
	}

	/**
	 * Publish an event
	 * 
	 * @param string $event
	 * @param array $args
	 * 
	 * @return void
	 */
	public function publish(string $event, array $args = []): void {
		if (isset($this->subscribers[$event])) {
			foreach ($this->subscribers[$event] as $callback) {
				if (is_a($callback, Event_Interface::class)) {
					$callback->execute($args);
					continue;
				}
				call_user_func_array($callback, [$event, $args]);
			}
		}
	}

	/**
	 * Check if an event has subscribers
	 * 
	 * @param string $event
	 * 
	 * @return bool
	 */
	public function hasSubscribers(string $event): bool {
		return isset($this->subscribers[$event]);
	}

	/**
	 * Get the subscribers for an event
	 * 
	 * @param string $event
	 * 
	 * @return array
	 */
	public function getSubscribers(string $event): array {
		return $this->subscribers[$event] ?? [];
	}
}