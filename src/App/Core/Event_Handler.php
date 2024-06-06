<?php
declare(strict_types=1);

namespace Gimli\Core;

class Event_Handler {
	/**
	 * @var array $subscribers
	 */
	protected array $subscribers = [];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->subscribers = [];
	}

	/**
	 * Subscribe to an event
	 *
	 * @param string $event    event
	 * @param callable $callback callback
	 * @return void
	 */
	public function subscribe(string $event, callable $callback): void {
		if (!isset($this->subscribers[$event])) {
			$this->subscribers[$event] = [];
		}
		$this->subscribers[$event][] = $callback;
	}

	/**
	 * Publish an event
	 *
	 * @param string $event event
	 * @param array $args  arguments
	 * @return void
	 */
	public function publish(string $event, array $args = []): void {
		if (isset($this->subscribers[$event])) {
			foreach ($this->subscribers[$event] as $callback) {
				call_user_func_array($callback, $args);
			}
		}
	}

	/**
	 * Unsubscribe from an event
	 *
	 * @param string $event event
	 * @param callable $callback callback
	 * @return void
	 */
	public function unsubscribe(string $event, callable $callback): void {
		if (isset($this->subscribers[$event])) {
			$key = array_search($callback, $this->subscribers[$event]);
			if ($key !== false) {
				unset($this->subscribers[$event][$key]);
			}
		}
	}

	/**
	 * Get the subscribers for an event
	 *
	 * @param string $event event
	 * @return array
	 */
	public function getSubscribers(string $event): array {
		return $this->subscribers[$event] ?? [];
	}

	/**
	 * Get all subscribers
	 *
	 * @return array
	 */
	public function getAllSubscribers(): array {
		return $this->subscribers;
	}

	/**
	 * Clear all subscribers
	 *
	 * @return void
	 */
	public function clearAllSubscribers(): void {
		$this->subscribers = [];
	}

	/**
	 * Clear subscribers for an event
	 *
	 * @param string $event event
	 * @return void
	 */
	public function clearSubscribers(string $event): void {
		unset($this->subscribers[$event]);
	}
}