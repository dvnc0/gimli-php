<?php
declare(strict_types=1);

namespace Gimli\Database;

use ReflectionClass;

use function Gimli\Injector\resolve;
use Gimli\Database\Faker\Faker;

class Seeder_Factory {

	/**
	 * @var int $seed
	 */
	protected int $seed;

	/**
	 * @var int $count
	 */
	protected int $count;

	/**
	 * @var callable $callback
	 */
	protected $callback;

	/**
	 * @var array $with_data
	 */
	protected array $with_data = [];

	public function __construct(
		protected string $class_name
	) {}

	/**
	 * Build the seeder
	 *
	 * @param string $table_name
	 * @param array $fields
	 * @param int $count
	 * @return Seeder_Factory
	 */
	public static function make(string $class_name): Seeder_Factory {
		return new Seeder_Factory($class_name);
	}

	/**
	 * Get a random seed
	 *
	 * @return int
	 */
	public static function getRandomSeed(): int {
		return mt_rand(1, 1000000);
	}

	/**
	 * Return an array of faked data
	 *
	 * @param int $seed
	 * @return array
	 */
	public function getSeededData(): array {
		if (empty($this->seed)) {
			$this->seed = self::getRandomSeed();
		}
		
		$Faker = resolve(Faker::class, ['seed' => $this->seed]);
		$schema = $this->getSeedSchema();

		$data = $Faker->buildDataSet($schema, $this->with_data);

		return $data;
	}

	/**
	 * Set the data to seed with
	 *
	 * @param array $data
	 * @return Seeder_Factory
	 */
	public function with(array $data): self {
		$this->with_data = $data;

		return $this;
	}

	/**
	 * Set the amount of times to repeat, not used with getSeededData
	 *
	 * @param int $count
	 * @return Seeder_Factory
	 */
	public function count(int $count): self {
		$this->count = $count;

		return $this;
	}

	/**
	 * Set a callback to run after seeding
	 *
	 * @param callable $callback
	 * @return Seeder_Factory
	 */
	public function callback(callable $callback): self {
		$this->callback = $callback;

		return $this;
	}

	/**
	 * Set the seed
	 *
	 * @param int $seed
	 * @return Seeder_Factory
	 */
	public function seed(int $seed): self {
		$this->seed = $seed;

		return $this;
	}

	/**
	 * Get the seed schema
	 *
	 * @return array
	 */
	protected function getSeedSchema(): array {
		$reflection = new ReflectionClass($this->class_name);
		$properties = $reflection->getProperties();
		$seeded_data = [];

		foreach ($properties as $property) {
			$seed = $property->getAttributes(Seed::class);

			if (empty($seed)) {
				continue;
			}

			$seeded_data[] = [
				'name' => $property->getName(),
				'type' => $seed[0]->getArguments()['type'],
				'args' => $seed[0]->getArguments()['args'] ?? [],
			];
		}

		return $seeded_data;
	}
}