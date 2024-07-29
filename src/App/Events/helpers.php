<?php
declare(strict_types=1);

namespace Gimli\Events;

use Gimli\Application;

if (!function_exists('Gimli\Events\publish_event')) {
	/**
	 * Publish an event
	 *
	 * @param string $event_name
	 * @param array  $args
	 * @return void
	 */
	function publish_event(string $event_name, array $args = []): void {
		$Event_Manager = Application::get()->Injector->resolve('Event_Manager');
		$Event_Manager->publish($event_name, $args);
	}
}