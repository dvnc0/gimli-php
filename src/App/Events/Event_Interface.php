<?php
declare(strict_types=1);

namespace Gimli\Events;

interface Event_Interface {
	public function execute(string $event_name, array $args = []): void;
}