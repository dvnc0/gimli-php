<?php
declare(strict_types=1);

namespace Gimli\View;

use function Gimli\Environment\get_config_value;

class Latte_Engine {
	/**
	 * @var non-empty-string $template_base_dir
	 */
	protected string $template_base_dir;

	/**
	 * @var non-empty-string $app_root_dir
	 */
	protected string $app_root_dir;
	
	/**
	 * Constructor
	 * 
	 * @param non-empty-string $template_base_dir template base directory
	 * @param non-empty-string $app_root_dir      app_root_dir
	 * 
	 * @return void
	 */
	public function __construct(string $template_base_dir, string $app_root_dir) {
		$this->template_base_dir = $template_base_dir;
		$this->app_root_dir      = $app_root_dir;
	}

	/**
	 * render a view with Latte
	 * 
	 * @param non-empty-string $template_path template path
	 * @param array<string, mixed>  $template_data template data
	 * 
	 * @return string
	 */
	public function render(string $template_path, array $template_data = []): string {
		$latte = new \Latte\Engine;

		$temp_dir = get_config_value('template_temp_dir');

		if (substr($temp_dir, 0, 1) !== '/') {
			$temp_dir = '/' . $temp_dir;
		}
		
		$latte->setTempDirectory($this->app_root_dir . $temp_dir);

		$latte->addFunction(
			'getVue', function(string $path) {
				$file = file_get_contents($this->app_root_dir . '/public/js/manifest.json');
				$file = json_decode($file, TRUE);
				if (empty($file[$path])) {
					return;
				}
				$file_to_load = $file[$path];

				echo "<script src='/public/js/{$file_to_load['file']}' type='module' defer crossorigin></script>";
			}
		);

		$latte->addFunction(
			'getCss', function(string $path) {
				$file = file_get_contents($this->app_root_dir . '/public/js/manifest.json');
				$file = json_decode($file, TRUE);
				if (empty($file[$path])) {
					return;
				}
				$file_to_load = $file[$path]['css'];

				echo "<link href='/public/js/{$file_to_load[0]}' rel='stylesheet'>";
			}
		);

		$latte->addFunction(
			'csrf', function() {
				$token = Csrf::generate();
				echo "<input type='hidden' name='csrf_token' value='{$token}'>";
			}
		);

		$template_path_full = implode('/', array_filter([$this->app_root_dir, $this->template_base_dir, $template_path], 'strlen'));
		$template_path_full = str_replace('//', '/', $template_path_full);

		$output = $latte->renderToString($template_path_full, $template_data);

		return $output;
	}
}