<?php
declare(strict_types=1);

namespace Gimli\Events;

use Gimli\Application;
use Gimli\Events\Event_Manager;

if (!function_exists('Gimli\Events\publish_event')) {
	/**
	 * Publish an event
	 *
	 * @param string $event_name
	 * @param array  $args
	 * @return void
	 */
	function publish_event(string $event_name, array $args = []): void {
		$Event_Manager = Application::get()->Injector->resolve(Event_Manager::class);
		$Event_Manager->publish($event_name, $args);
	}
}

if (!function_exists('Gimli\Events\subscribe_event')) {
	/**
	 * Subscribe to an event
	 *
	 * @param string   $event_name
	 * @param callable|string $callback
	 * @return void
	 */
	function subscribe_event(string $event_name, callable|string $callback): void {
		$Event_Manager = Application::get()->Injector->resolve(Event_Manager::class);
		$Event_Manager->subscribe($event_name, $callback);
	}
}