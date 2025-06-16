<?php
declare(strict_types=1);

namespace Gimli\Events;

abstract class Event_Abstract implements Event_Interface {
	/**
	 * Validate the arguments
	 *
	 * @param array $args the arguments to validate
	 * @return bool the result of the validation
	 */
	public function validate(array $args): bool {
		return TRUE;
	}

	/**
	 * Get the required parameters
	 *
	 * @return array the required parameters
	 */
	public function getRequiredParameters(): array {
		return [];
	}

	/**
	 * Get the optional parameters
	 *
	 * @return array the optional parameters
	 */
	public function getOptionalParameters(): array {
		return [];
	}
}