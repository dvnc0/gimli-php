<?php
declare(strict_types=1);

namespace Gimli\View;

use Gimli\View\View_Engine_Interface;

class Latte_Engine implements View_Engine_Interface
{
	protected string $template_base_dir;
	protected string $app_root_dir;
	
	public function __construct(string $template_base_dir, string $app_root_dir) {
		$this->template_base_dir = $template_base_dir;
		$this->app_root_dir = $app_root_dir;
	}

	public function render(string $template_path, array $template_data = []): string {
		$latte = new \Latte\Engine;
		
		$latte->setTempDirectory($this->app_root_dir . 'app/tmp');

		$latte->addFunction('getVue', function(string $path) {
			$file = file_get_contents($this->app_root_dir . 'public/js/manifest.json');
			$file = json_decode($file, true);
			if (empty($file[$path])) {
				return;
			}
			$file_to_load = $file[$path];

			echo "<script src='/public/js/{$file_to_load['file']}' type='module' defer crossorigin></script>";
		});

		$latte->addFunction('getCss', function(string $path) {
			$file = file_get_contents($this->app_root_dir . 'public/js/manifest.json');
			$file = json_decode($file, true);
			if (empty($file[$path])) {
				return;
			}
			$file_to_load = $file[$path];

			echo "<link href='/public/js/{$file_to_load['file']}' rel='stylesheet'>";
		});


		$output = $latte->renderToString($this->template_base_dir . $template_path, $template_data);

		return $output;
	}
}