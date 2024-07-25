<?php
declare(strict_types=1);

namespace Gimli\Database\Faker;

class Faker {

	public function __construct(
		protected int $seed
	) {}

	/**
	 * Build a data set
	 *
	 * @param array $schema
	 * @param array $provided_data
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
	 * @param array $field
	 * @return mixed
	 */
	public function generateField(array $field): mixed {
		$Faker_Factory = new Faker_Factory($this->seed);
		switch ($field['type']) {
			case 'int':
			case 'integer':
			case 'number':
			case 'cents':
				return $Faker_Factory->getRandomInt(...$field['args']);
			case 'float':
			case 'decimal':
			case 'money':
			case 'price':
				return $Faker_Factory->getRandomFloat(...$field['args']);
			case 'one_of':
				return $Faker_Factory->oneOf($field['args']);
			case 'date':
				return $Faker_Factory->randomDate($field['args']['format'], $field['args']['min'] ?? '1970-01-01', $field['args']['max'] ?? '2024-01-01');
			case 'bool':
				return $Faker_Factory->getRandomBool();
			case 'email':
				return $Faker_Factory->email();
			case 'unique_id':
			case 'random_string':
				return $Faker_Factory->getRandomString($field['args']['length'], $field['args']['prefix'] ?? '');
			case 'first_name':
				return $Faker_Factory->firstName();
			case 'last_name':
				return $Faker_Factory->lastName();
			case 'full_name':
				return $Faker_Factory->fullName();
			case 'full_name_with_middle':
				return $Faker_Factory->fullNameWithMiddleName();
			case 'full_name_with_middle_initial':
				return $Faker_Factory->fullNameWithMiddleInitial();
			case 'middle_name':
				return $Faker_Factory->middleName();
			case 'middle_initial':
				return $Faker_Factory->middleInitial();
			case 'words':
			case 'sentence':
			case 'short_text':
				return $Faker_Factory->words($field['args']['count'] ?? 3);
			case 'password':
				return $Faker_Factory->password($field['args']['password'] ?? 'password', $field['args']['salt'] ?? 'salt');
			case 'username':
				return $Faker_Factory->username();
			case 'paragraph':
			case 'long_text':
				return $Faker_Factory->paragraphs($field['args']['count'] ?? 3);
			case 'phone_number':
				return $Faker_Factory->phoneNumber();
			case 'url':
				return $Faker_Factory->url();
			case 'address':
				return $Faker_Factory->fullAddress();
			case 'city':
				return $Faker_Factory->city();
			case 'state':
				return $Faker_Factory->state();
			case 'zip':
				return $Faker_Factory->zip();
			case 'state_full':
				return $Faker_Factory->stateLong();
			case 'phone_number':
				return $Faker_Factory->phoneNumber();
			default:
				return $Faker_Factory->words(1);
		}
	}
}