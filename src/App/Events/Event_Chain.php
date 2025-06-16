<?php
declare(strict_types=1);

namespace Gimli\Events;

class Event_Chain {
	/**
	 * @var array<array{event: string, args: array}> $events
	 */
	protected array $events = [];
	
	/**
	 * @var Event_Manager $Event_Manager
	 */
	protected Event_Manager $Event_Manager;
	
	/**
	 * Constructor
	 * 
	 * @param Event_Manager $Event_Manager
	 */
	public function __construct(Event_Manager $Event_Manager) {
		$this->Event_Manager = $Event_Manager;
	}
	
	/**
	 * Add an event to the chain
	 * 
	 * @param string $event Event name
	 * @param array  $args  Event arguments
	 * @return Event_Chain
	 */
	public function add(string $event, array $args = []): Event_Chain {
		$this->events[] = [
			'event' => $event,
			'args' => $args
		];
		return $this;
	}
	
	/**
	 * Execute all events in the chain
	 * 
	 * @return void
	 * @throws Throwable
	 */
	public function execute(): void {
		foreach ($this->events as $event) {
			$this->Event_Manager->publish($event['event'], $event['args']);
		}
	}
}