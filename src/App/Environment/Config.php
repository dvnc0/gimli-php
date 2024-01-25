<?php
declare(strict_types=1);

namespace Gimli\Environment;

use Gimli\Environment\Environment_Base;

class Config extends Environment_Base{
	public bool $is_live      = FALSE;
	public bool $is_dev       = FALSE;
	public bool $is_staging   = FALSE;
	public bool $is_cli       = FALSE;
	public bool $is_unit_test = FALSE;
	public array $database    = [];
}