<?php
declare(strict_types=1);

namespace Gimli\View;

use Gimli\Application;
use Gimli\View\Latte_Engine;

if (!function_exists('Gimli\View\render')) {
	/**
	 * View helper
	 * 
	 * @param string $view
	 * @param array $data
	 * 
	 * @return string
	 */
	function render(string $template, array $data = []): string {
		$view = Application::get()->Injector->resolve(Latte_Engine::class);
		return $view->render($template, $data);
	}
}