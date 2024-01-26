<?php
declare(strict_types=1);

namespace Gimli\Environment;

use Gimli\Environment\Environment_Base;

class Config extends Environment_Base {
	/** @var bool $is_live */
	public bool $is_live = FALSE;

	/** @var bool $is_dev */
	public bool $is_dev = FALSE;

	/** @var bool $is_staging */
	public bool $is_staging = FALSE;

	/** @var bool $is_cli */
	public bool $is_cli = FALSE;

	/** @var bool $is_unit_test */
	public bool $is_unit_test = FALSE;

	/** @var array $database */
	public array $database = [];
}