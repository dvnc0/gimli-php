<?php
declare(strict_types=1);

namespace Gimli\Database;

#[\Attribute]
class Seed {
	public function __construct(
		public readonly string $type,
		public readonly array $args = []
	) {}

	public function getType(): string {
		return $this->type;
	}

	public function getArgs(): array {
		return $this->args;
	}

}