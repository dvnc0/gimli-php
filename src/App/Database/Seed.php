<?php
declare(strict_types=1);

namespace Gimli\Database;

use Attribute;

#[\Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_PROPERTY)]
class Seed {
	/**
	 * @param string $type the type of seed
	 * @param array  $args the arguments for the seed
	 */
	public function __construct(
		public readonly string $type,
		public readonly array $args = []
	) {}
}