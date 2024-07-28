<?php
declare(strict_types=1);

namespace Gimli\Events;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Event {
	/**
	 * @param string $event_name
	 */
	public function __construct(
		readonly public string $event_name,
	) {}
}