<?php
declare(strict_types=1);

namespace Gimli\Events;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
class Event {
    public function __construct(
        public string $event_name,
        public ?string $description = null,
        public array $tags = [],
        public int $priority = 0
    ) {}
}