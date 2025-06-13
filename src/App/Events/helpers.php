<?php
declare(strict_types=1);

namespace Gimli\Events;

use Gimli\Application_Registry;
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
		$Event_Manager = Application_Registry::get()->Injector->resolve(Event_Manager::class);
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
		$Event_Manager = Application_Registry::get()->Injector->resolve(Event_Manager::class);
		$Event_Manager->subscribe($event_name, $callback);
	}
}

if (!function_exists('Gimli\Events\chain_events')) {
    /**
     * Create a new event chain
     * 
     * @return Event_Chain
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
     * @param string $tag
     * @return array<string, array{description: ?string, tags: array, class: string}>
     */
    function get_events_by_tag(string $tag): array {
        $Event_Manager = Application_Registry::get()->Injector->resolve(Event_Manager::class);
        return $Event_Manager->getEventsByTag($tag);
    }
}