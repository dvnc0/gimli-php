<?php
declare(strict_types=1);

namespace Gimli\Database;

class Model {

	/**
	 * @var string $primary_key
	 */
	protected string $primary_key = 'id';

	/**
	 * @var string $table_name
	 */
	protected string $table_name = '';

	/**
	 * @var bool $is_loaded
	 */
	protected bool $is_loaded = false;

	/**
	 * @var array $ignored_fields
	 */
	protected array $ignored_fields = [
		'ignored_fields', 
		'Database', 
		'table_name', 
		'primary_key', 
		'is_loaded',
	];

	/**
	 * construct
	 * 
	 * @param Database $Database
	 */
	public function __construct(
		protected Database $Database,
	) {}

	/**
	 * Save the model
	 *
	 * @return bool
	 */
	public function load(string $where, array $params = []): bool {
		$sql = <<<SQL
			SELECT * FROM {$this->table_name}
			WHERE {$where} 
			ORDER BY {$this->primary_key} ASC 
			LIMIT 1;
		SQL;

		$row = $this->Database->fetchRow($sql, $params);

		if (empty($row)) {
			return false;
		}

		foreach ($row as $key => $value) {
			if (!in_array($key, $this->ignored_fields)) {
				$this->$key = $value;
			}
		}

		$this->is_loaded = true;
		return true;
	}

	/**
	 * Save the model
	 *
	 * @return bool
	 */
	public function save(): bool {
		$this->beforeSave();
		$data = [];
		foreach ($this as $key => $value) {
			if (!in_array($key, $this->ignored_fields) && $key !== $this->primary_key) {
				$data[$key] = $value;
			}
		}

		if ($this->is_loaded) {
			$where = "{$this->primary_key} = :{$this->primary_key}";
			$params = [":{$this->primary_key}" => $this->{$this->primary_key}];
			return $this->Database->update($this->table_name, $where, $data, $params);
		}

		$this->Database->insert($this->table_name, $data);

		$this->{$this->primary_key} = (int) $this->Database->lastInsertId();

		$this->is_loaded = true;
		$this->afterSave();
		return true;
	}

	/**
	 * Reset the model
	 */
	public function reset(): void {
		foreach ($this as $key => $value) {
			if (!in_array($key, $this->ignored_fields)) {
				unset($this->$key);
			}
		}
		$this->is_loaded = false;
	}

	/**
	 * Get the data
	 *
	 * @return array
	 */
	public function getData(): array {
		$data = [];
		foreach ($this as $key => $value) {
			if (!in_array($key, $this->ignored_fields)) {
				$data[$key] = $value;
			}
		}
		return $data;
	}

	/**
	 * Check if the model is loaded
	 *
	 * @return bool
	 */
	public function isLoaded(): bool {
		return $this->is_loaded === true;
	}

	/**
	 * Load the model from a data set, used with Seeders
	 *
	 * @param array $data the data to load the Model with
	 */
	public function loadFromDataSet(array $data): void {
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}
	}

	/**
	 * Before save hook
	 */
	protected function beforeSave(): void {
		return;
	}

	/**
	 * After save hook
	 */
	protected function afterSave(): void {
		return;
	}
}