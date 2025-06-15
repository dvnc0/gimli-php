<?php
declare(strict_types=1);

namespace Gimli\View;

use Gimli\Application_Registry;
use Gimli\View\Latte_Engine;

if (!function_exists('Gimli\View\render')) {
	/**
	 * View helper
	 * 
	 * @param non-empty-string $template
	 * @param array<string, mixed> $data
	 * 
	 * @return string
	 */
	function render(string $template, array $data = []): string {
		$view = Application_Registry::get()->Injector->resolve(Latte_Engine::class);
		return $view->render($template, $data);
	}
}