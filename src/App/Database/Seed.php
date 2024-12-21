<?php
declare(strict_types=1);

namespace Gimli\Database;
use Attribute;

#[\Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_PROPERTY)]
class Seed {
	public function __construct(
		public readonly string $type,
		public readonly array $args = []
	) {}
}