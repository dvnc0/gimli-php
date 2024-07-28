<?php
declare(strict_types=1);

namespace Gimli\Events;

interface Event_Interface {
	public function execute(array $args = []): void;
}