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

	/** @var string $web_route_file */
	public string $web_route_file = '/App/Routes/web.php';

	/** @var bool $use_web_route_file */
	public bool $use_web_route_file = FALSE;

	/** @var bool $enable_latte */
	public bool $enable_latte = TRUE;

	/** @var string $api_route_file */
	public string $template_base_dir = 'App/views';
}