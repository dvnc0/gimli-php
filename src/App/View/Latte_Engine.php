<?php
declare(strict_types=1);

namespace Gimli\View;

class Latte_Engine {
	protected string $template_base_dir;
	protected string $app_root_dir;
	
	/**
	 * Constructor
	 * 
	 * @param string $template_base_dir template base directory
	 * @param string $app_root_dir      app_root_dir
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
	 * @param string $template_path template path
	 * @param array  $template_data template data
	 * 
	 * @return string
	 */
	public function render(string $template_path, array $template_data = []): string {
		$latte = new \Latte\Engine;
		
		$latte->setTempDirectory($this->app_root_dir . '/tmp');

$latte->addFunction(
				'getVue', function(string $path) {
				$file = file_get_contents($this->app_root_dir . 'public/js/manifest.json');
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
				$file = file_get_contents($this->app_root_dir . 'public/js/manifest.json');
				$file = json_decode($file, TRUE);
				if (empty($file[$path])) {
					return;
				}
				$file_to_load = $file[$path];

				echo "<link href='/public/js/{$file_to_load['file']}' rel='stylesheet'>";
				}
);

		$template_path_full = implode('/', array_filter([$this->app_root_dir, $this->template_base_dir, $template_path], 'strlen'));
		$template_path_full = str_replace('//', '/', $template_path_full);

		$output = $latte->renderToString($template_path_full, $template_data);

		return $output;
	}
}