<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Environment\Config;

/**
 * @covers Gimli\Environment\Config
*/
class Config_Test extends TestCase {

	protected function getConfigArray(): array {
		return [
			'is_live' => FALSE,
			'is_dev' => TRUE,
			'is_staging' => FALSE,
			'is_cli' => FALSE,
			'is_unit_test' => FALSE,
			'database' => [
				'driver' => 'mysql',
				'host' => '',
				'database' => '',
				'username' => '',
				'password' => '',
				'port' => 3306,
			],
			'autoload_routes' => TRUE,
			'route_directory' => '/App/Routes/',
			'enable_latte' => TRUE,
			'template_base_dir' => 'App/views/',
			'template_temp_dir' => 'tmp',
		];
	}

	public function testThatConfigIsLoaded() {

		$mock_config_array = $this->getConfigArray();

		$Config = new Config($mock_config_array);

		$this->assertEquals($Config->is_live, FALSE);
		$this->assertEquals($Config->is_dev, TRUE);
		$this->assertEquals($Config->is_staging, FALSE);
		$this->assertEquals($Config->is_cli, FALSE);
		$this->assertEquals($Config->is_unit_test, FALSE);
		$this->assertEquals($Config->database['host'], '');
	}

	public function testThatSetAdjustsConfig() {

		$mock_config_array = $this->getConfigArray();

		$Config = new Config($mock_config_array);

		$Config->set('is_live', FALSE);
		$Config->set('is_dev', TRUE);
		$Config->set('is_staging', TRUE);
		$Config->set('is_cli', TRUE);
		$Config->set('is_unit_test', TRUE);

		$this->assertEquals($Config->is_live, FALSE);
		$this->assertEquals($Config->is_dev, TRUE);
		$this->assertEquals($Config->is_staging, TRUE);
		$this->assertEquals($Config->is_cli, TRUE);
		$this->assertEquals($Config->is_unit_test, TRUE);
	}

	public function testThatSetAdjustsConfigWithDotNotation() {

		$mock_config_array = $this->getConfigArray();

		$Config = new Config($mock_config_array);

		$Config->set('database.host', 'foo.bar');

		$this->assertEquals($Config->database['host'], 'foo.bar');
	}

	public function testThatGetReturnsCorrectValue() {

		$mock_config_array = $this->getConfigArray();

		$Config = new Config($mock_config_array);

		$this->assertEquals($Config->get('is_live'), FALSE);
		$this->assertEquals($Config->get('is_dev'), TRUE);
		$this->assertEquals($Config->get('is_staging'), FALSE);
		$this->assertEquals($Config->get('is_cli'), FALSE);
		$this->assertEquals($Config->get('is_unit_test'), FALSE);
		$this->assertEquals($Config->get('database.host'), '');
	}

	public function testHasIsAccurate() {

		$mock_config_array = $this->getConfigArray();

		$Config = new Config($mock_config_array);

		$this->assertEquals($Config->has('is_live'), TRUE);
		$this->assertEquals($Config->has('is_dev'), TRUE);
		$this->assertEquals($Config->has('is_staging'), TRUE);
		$this->assertEquals($Config->has('is_cli'), TRUE);
		$this->assertEquals($Config->has('is_unit_test'), TRUE);
		$this->assertEquals($Config->has('database.host'), TRUE);
		$this->assertEquals($Config->has('database.username'), TRUE);
		$this->assertEquals($Config->has('database.password'), TRUE);
		$this->assertEquals($Config->has('database.database'), TRUE);
		$this->assertEquals($Config->has('database.port'), TRUE);
		$this->assertEquals($Config->has('database.foo.bar'), FALSE);
		$this->assertEquals($Config->has('foo.bar'), FALSE);
		$this->assertEquals($Config->has('foo'), FALSE);
	}
}