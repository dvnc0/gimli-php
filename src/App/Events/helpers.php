<?php
declare(strict_types=1);

namespace Gimli\Events;

use Gimli\Application_Registry;
use Gimli\Events\Event_Manager;

if (!function_exists('Gimli\Events\publish_event')) {
	/**
	 * Publish an event
	 *
	 * @param string $event_name the name of the event
	 * @param array  $args       the arguments for the event
	 * @return void
	 */
	function publish_event(string $event_name, array $args = []): void {
		$Event_Manager = Application_Registry::get()->Injector->resolve(Event_Manager::class);
		$Event_Manager->publish($event_name, $args);
	}
}

if (!function_exists('Gimli\Events\subscribe_event')) {
	/**
	 * Subscribe to an event
	 *
	 * @param string          $event_name the name of the event
	 * @param callable|string $callback   the callback to subscribe to the event
	 * @return void
	 */
	function subscribe_event(string $event_name, callable|string $callback): void {
		$Event_Manager = Application_Registry::get()->Injector->resolve(Event_Manager::class);
		$Event_Manager->subscribe($event_name, $callback);
	}
}

if (!function_exists('Gimli\Events\chain_events')) {
	/**
	 * Create a new event chain
	 * 
	 * @return Event_Chain the event chain
	 */
	function chain_events(): Event_Chain {
		$Event_Manager = Application_Registry::get()->Injector->resolve(Event_Manager::class);
		return $Event_Manager->chain();
	}
}

if (!function_exists('Gimli\Events\get_events_by_tag')) {
	/**
	 * Get events by tag
	 * 
	 * @param string $tag the tag to get events by
	 * @return array<string, array{description: ?string, tags: array, class: string}>
	 */
	function get_events_by_tag(string $tag): array {
		$Event_Manager = Application_Registry::get()->Injector->resolve(Event_Manager::class);
		return $Event_Manager->getEventsByTag($tag);
	}
}

if (!function_exists('Gimli\Events\event_manager')) {
	/**
	 * Get the event manager
	 * 
	 * @return Event_Manager the event manager
	 */
	function event_manager(): Event_Manager {
		return Application_Registry::get()->Injector->resolve(Event_Manager::class);
	}
}