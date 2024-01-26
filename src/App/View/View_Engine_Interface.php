<?php
declare(strict_types=1);

namespace Gimli\View;

interface View_Engine_Interface
{
	/**
	 * render a view
	 * 
	 * @param string $template_path template path
	 * @param array  $template_data template data
	 * 
	 * @return string
	 */
	public function render(string $template_path, array $template_data = []): string;
}