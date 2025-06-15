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
		'fillable_fields',
		'guarded_fields',
	];

	/**
	 * @var array $fillable_fields
	 */
	protected array $fillable_fields = [];

	/**
	 * @var array $guarded_fields
	 */
	protected array $guarded_fields = [];

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
		$this->afterLoad();
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
	 * @param bool $is_loaded is the model loaded
	 * @return void
	 */
	public function loadFromDataSet(array $data, bool $is_loaded = true): void {
		foreach ($data as $key => $value) {
			if ($this->isFillable($key)) {
				$this->$key = $value;
			}
		}
		$this->is_loaded = $is_loaded;
		$this->afterLoad();
	}

	/**
	 * Create a new model from an array
	 *
	 * @param array $data the data to create the Model 
	 * @return void
	 */
	public function createFromDataSet(array $data): void {
		foreach ($data as $key => $value) {
			if ($this->isFillable($key)) {
				$this->$key = $value;
			}
		}
		$this->is_loaded = false;
	}

	protected function isFillable(string $key): bool {
		// Skip ignored framework fields
		if (in_array($key, $this->ignored_fields)) {
			return false;
		}
	
		// Skip primary key
		if ($key === $this->primary_key) {
			return false;
		}
	
		// Skip if property doesn't exist
		if (!property_exists($this, $key)) {
			return false;
		}
	
		// Skip non-public properties (additional security layer)
		$reflection = new \ReflectionProperty($this, $key);
		if (!$reflection->isPublic()) {
			return false;
		}

		// Whitelist approach: if fillable is defined, only allow those
		if (!empty($this->fillable_fields)) {
			return in_array($key, $this->fillable_fields);
		}
	
		// Blacklist approach: if guarded is defined, block those
		if (!empty($this->guarded_fields)) {
			return !in_array($key, $this->guarded_fields);
		}
	
		// Default: allow if passes all checks
		return true;
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

	/**
	 * After load hook
	 */
	protected function afterLoad(): void {
		return;
	}
}