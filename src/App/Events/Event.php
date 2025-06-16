<?php
declare(strict_types=1);

namespace Gimli\Events;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
class Event {
	/**
	 * @param string      $event_name  the name of the event
	 * @param string|null $description the description of the event
	 * @param array       $tags        the tags for the event
	 * @param int         $priority    the priority of the event
	 */
	public function __construct(
		public string $event_name,
		public ?string $description = NULL,
		public array $tags = [],
		public int $priority = 0
	) {}
}