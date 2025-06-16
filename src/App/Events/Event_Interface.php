<?php
declare(strict_types=1);

namespace Gimli\Events;

interface Event_Interface {
	/**
	 * Execute the event
	 *
	 * @param string $event_name the name of the event
	 * @param array  $args       the arguments for the event
	 * @return void
	 */
	public function execute(string $event_name, array $args = []): void;
	
	/**
	 * Validate the arguments
	 *
	 * @param array $args the arguments to validate
	 * @return bool the result of the validation
	 */
	public function validate(array $args): bool;
	
	/**
	 * Get the required parameters
	 *
	 * @return array the required parameters
	 */
	public function getRequiredParameters(): array;
	
	/**
	 * Get the optional parameters
	 *
	 * @return array the optional parameters
	 */
	public function getOptionalParameters(): array;
}