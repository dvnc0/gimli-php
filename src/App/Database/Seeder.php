<?php
declare(strict_types=1);

namespace Gimli\Database;

use ReflectionClass;

use function Gimli\Injector\resolve;
use function Gimli\Injector\resolve_fresh;

use Gimli\Database\Faker\Faker;

class Seeder {

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

	/**
	 * @param string $class_name the class name to seed
	 */
	public function __construct(
		protected string $class_name
	) {}

	/**
	 * Build the seeder
	 *
	 * @param string $class_name the class name to seed
	 * @return Seeder
	 */
	public static function make(string $class_name): Seeder {
		return new Seeder($class_name);
	}

	/**
	 * Get a random seed
	 *
	 * @return int
	 */
	public static function getRandomSeed(): int {
		return mt_rand();
	}

	/**
	 * Return an array of faked data
	 *
	 * @param int|null $seed the seed to use
	 * @return array
	 */
	public function getSeededData(int|null $seed = NULL): array {
		$seed_to_use = $seed ?? $this->seed;

		$Faker  = resolve_fresh(Faker::class, ['seed' => $seed_to_use]);
		$schema = $this->getSeedSchema();

		$data = $Faker->buildDataSet($schema, $this->with_data);

		return $data;
	}

	/**
	 * Create the record and save to the database
	 * Creates any callback records as well
	 *
	 * @return int the number of records seeded
	 */
	public function create(): int {
		$count         = $this->count ?? 1;
		$iterated_seed = $this->seed;
		for ($i = 0; $i < $count; $i++) {
			$result_set     = $this->getSeededData($iterated_seed);
			$iterated_seed += 1;

			$model = resolve($this->class_name);
			$model->loadFromDataSet($result_set);
			$model->save();
			
			if (empty($this->callback)) {
				continue;
			}

			$callback_array = call_user_func($this->callback, $result_set);
			foreach ($callback_array as $callback) {
				$callback->seed($iterated_seed);
				$iterated_seed = $callback->create();
			}
		}

		return $iterated_seed;
	}

	/**
	 * Set the data to seed with
	 *
	 * @param array $data the data to seed with
	 * @return self
	 */
	public function using(array $data): self {
		$this->with_data = $data;

		return $this;
	}

	/**
	 * Set the amount of times to repeat, not used with getSeededData
	 *
	 * @param int $count the number of times to seed
	 * @return self
	 */
	public function count(int $count): self {
		$this->count = $count;

		return $this;
	}

	/**
	 * Set a callback to run after seeding
	 *
	 * @param callable $callback the callback to run after seeding
	 * @return self
	 */
	public function callback(callable $callback): self {
		$this->callback = $callback;

		return $this;
	}

	/**
	 * Set the seed
	 *
	 * @param int $seed the seed to use
	 * @return self
	 */
	public function seed(int $seed): self {
		$this->seed = $seed;

		return $this;
	}

	/**
	 * Get the seed schema
	 *
	 * @return array the seed schema
	 */
	protected function getSeedSchema(): array {
		$reflection  = new ReflectionClass($this->class_name);
		$properties  = $reflection->getProperties();
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