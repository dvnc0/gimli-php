<?php
declare(strict_types=1);

namespace Gimli\Database\Faker;

use function Gimli\Injector\resolve_fresh;

class Faker {

	/**
	 * @var array $lookup_table the lookup table for the faker methods
	 */
	protected array $lookup_table = [
		'int' => 'getRandomInt',
		'integer' => 'getRandomInt',
		'number' => 'getRandomInt',
		'cents' => 'getRandomInt',
		'float' => 'getRandomFloat',
		'decimal' => 'getRandomFloat',
		'money' => 'getRandomFloat',
		'price' => 'getRandomFloat',
		'one_of' => 'options',
		'date' => 'date',
		'bool' => 'getRandomBool',
		'email' => 'email',
		'unique_id' => 'getRandomString',
		'random_string' => 'getRandomString',
		'first_name' => 'firstName',
		'last_name' => 'lastName',
		'full_name' => 'fullName',
		'full_name_with_middle' => 'fullNameWithMiddleName',
		'full_name_with_middle_initial' => 'fullNameWithMiddleInitial',
		'middle_name' => 'middleName',
		'middle_initial' => 'middleInitial',
		'words' => 'words',
		'sentence' => 'words',
		'short_text' => 'words',
		'password' => 'password',
		'username' => 'username',
		'paragraph' => 'paragraphs',
		'long_text' => 'paragraphs',
		'phone_number' => 'phoneNumber',
		'url' => 'url',
		'address' => 'fullAddress',
		'city' => 'city',
		'state' => 'state',
		'zip' => 'zip',
		'state_full' => 'stateLong',
		'tiny_int' => 'tinyInt',
		'always' => 'always',
		'uuid' => 'uuid',
	];

	/**
	 * construct
	 * 
	 * @param int $seed the seed for the faker
	 */
	public function __construct(
		protected int $seed
	) {}

	/**
	 * Build a data set
	 *
	 * @param array $schema        the schema for the data set
	 * @param array $provided_data the provided data
	 * @return array
	 */
	public function buildDataSet(array $schema, array $provided_data = []): array {
		$data = [];
		foreach ($schema as $field) {
			if (array_key_exists($field['name'], $provided_data)) {
				$data[$field['name']] = $provided_data[$field['name']];
				continue;
			}
			$data[$field['name']] = $this->generateField($field);
		}
		return $data;
	}

	/**
	 * Generate a field value
	 *
	 * @param array $field the field to generate
	 * @return mixed
	 */
	public function generateField(array $field): mixed {
		$Faker_Factory = resolve_fresh(Faker_Factory::class, ['seed' => $this->seed]);
		if (array_key_exists($field['type'], $this->lookup_table)) {
			$method = $this->lookup_table[$field['type']];
			if ($method === 'options') {
				return $Faker_Factory->$method($field['args']);
			}
			return $Faker_Factory->$method(...$field['args']);
		}

		return NULL;
	}
}