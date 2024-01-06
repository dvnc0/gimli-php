<?php
declare(strict_types=1);

namespace Gimli\View;

interface View_Engine_Interface
{
	public function render(string $template_path, array $template_data = []): string;
}