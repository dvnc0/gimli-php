<?php
declare(strict_types=1);

namespace Gimli\Database\Faker;

class Faker_Factory {
	public function __construct(
		protected int $seed
	) {
		mt_srand($this->seed);
	}

	/**
	 * Get a data file
	 *
	 * @param string $filename
	 * @return array
	 */
	protected function getDataFile(string $filename): array {
		$file = file_get_contents(__DIR__ . '/data/' . $filename);
		return json_decode($file, true);
	}

	/**
	 * Set the seed
	 *
	 * @param int $seed
	 * @return void
	 */
	public function setSeed(int $seed): void {
		$this->seed = $seed;
		mt_srand($this->seed);
	}

	/**
	 * Get the seed
	 *
	 * @return int
	 */
	public function getSeed(): int {
		return $this->seed;
	}

	/**
	 * Get a random integer
	 *
	 * @param int $min
	 * @param int $max
	 * @return int
	 */
	public function getRandomInt(int $min = 1, int $max = 100): int {
		return mt_rand($min, $max);
	}

	/**
	 * Get a random float
	 *
	 * @param float $min
	 * @param float $max
	 * @return float
	 */
	public function getRandomFloat(float $min, float $max): float {
		return $min + mt_rand() / mt_getrandmax() * ($max - $min);
	}

	/**
	 * Get one of the options
	 *
	 * @param array $options
	 * @return mixed
	 */
	public function options(array $options) {
		return $options[mt_rand(0, count($options) - 1)];
	}

	/**
	 * Get a random date
	 *
	 * @param string $format
	 * @param string $min
	 * @param string $max
	 * @return string
	 */
	public function date(string $format = 'Y-m-d H:i:s', string $min = '1970-01-01 00:00:00', string $max = '2024-01-01 00:00:00'): string {
		$min = strtotime($min);
		$max = strtotime($max);
		$rand = mt_rand($min, $max);
		return date($format, $rand);
	}

	/**
	 * Get a random boolean
	 *
	 * @return bool
	 */
	public function bool(): bool {
		return (bool) mt_rand(0, 1);
	}

	/**
	 * Get a random email
	 *
	 * @return string
	 */
	public function email(): string {
		$domains = $this->getDataFile('domain_tlds.json');
		$first_names = $this->getDataFile('first_names.json');
		$last_names = $this->getDataFile('last_names.json');
		$domain_name = $this->getDataFile('words.json');
		$domain = $this->options($domain_name);
		$tld = $this->options($domains);
		$first_name = $this->options($first_names);
		$last_name = $this->options($last_names);

		if (mt_rand(0, 100) <= 20) {
			$special_chars = ['\'', '-', '_', '!'];
			$first_name = substr_replace($first_name, $this->options($special_chars), mt_rand(0, strlen($first_name)), 0);
		}

		return strtolower($first_name) . '.' . strtolower($last_name) . '@' . $domain . $tld;
	}

	/**
	 * get first name
	 *
	 * @return string
	 */
	public function firstName(): string {
		$first_names = $this->getDataFile('first_names.json');
		return ucfirst(strtolower($this->options($first_names)));
	}

	/**
	 * get last name
	 *
	 * @return string
	 */
	public function lastName(): string {
		$last_names = $this->getDataFile('last_names.json');
		return ucfirst(strtolower($this->options($last_names)));
	}

	/**
	 * get middle name
	 *
	 * @return string
	 */
	public function middleName(): string {
		$middle_names = $this->getDataFile('middle_names.json');
		return ucfirst(strtolower($this->options($middle_names)));
	}

	/**
	 * get middle initial
	 *
	 * @return string
	 */
	public function middleInitial(): string {
		$middle_names = $this->getDataFile('middle_names.json');
		$middle_name = $this->options($middle_names);
		return strtoupper($middle_name[0]);
	}

	/**
	 * get random string
	 *
	 * @param int $length
	 * @param string $prefix
	 * @return string
	 */
	public function getRandomString(int $length, string $prefix = ''): string {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[mt_rand(0, $charactersLength - 1)];
		}
		return $prefix . $randomString;
	}

	/**
	 * get full name
	 *
	 * @return string
	 */
	public function fullName(): string {
		return $this->firstName() . ' ' . $this->lastName();
	}

	/**
	 * get full name with middle initial
	 *
	 * @return string
	 */
	public function fullNameWithMiddleInitial(): string {
		return $this->firstName() . ' ' . $this->middleInitial() . '. ' . $this->lastName();
	}

	/**
	 * get full name with middle name
	 *
	 * @return string
	 */
	public function fullNameWithMiddleName(): string {
		return $this->firstName() . ' ' . $this->middleName() . ' ' . $this->lastName();
	}

	/**
	 * get a variable amount of words
	 *
	 * @param int $count
	 * @return string
	 */
	public function words(int $count = 3): string {
		$words = $this->getDataFile('words.json');
		$word_count = count($words);
		$selected_words = [];
		for ($i = 0; $i < $count; $i++) {
			$selected_words[] = $words[mt_rand(0, $word_count - 1)];
		}
		return implode(' ', $selected_words);
	}

	/**
	 * password
	 *
	 * @param  string $password
	 * @param  string $salt
	 * @return string
	 */
	public function password(string $password = 'password', string $salt = ''): string {
		return password_hash($password . $salt, PASSWORD_DEFAULT);
	}

	/**
	 * username
	 *
	 * @return string
	 */
	public function username(): string {
		$emotions = $this->getDataFile('emotions.json');
		$animals = $this->getDataFile('animals.json');
		
		$emotion = $this->options($emotions);
		$animal = $this->options($animals);

		return $emotion . '_' . $animal . $this->getRandomInt(1, 100);
	}

	/**
	 * get a paragraph
	 *
	 * @param int $count
	 * @return string
	 */
	public function paragraphs(int $count = 3): string {
		$paragraphs = $this->getDataFile('words.json');
		$selected_paragraphs = [];
		for ($i = 0; $i < $count; $i++) {
			$word_count = \mt_rand(50, 200);
			$sentence_count = \mt_rand(5, 10);

			$selected_words = [];
			for ($j = 0; $j < $word_count; $j++) {
				$selected_words[] = $paragraphs[mt_rand(0, count($paragraphs) - 1)];
			}

			$selected_sentences = [];
			for ($j = 0; $j < $sentence_count; $j++) {
				$selected_sentences[] = ucfirst(implode(' ', array_slice($selected_words, $j * 10, 10)));
			}

			$selected_paragraphs[] = ucfirst(implode('. ', $selected_sentences) . '.');
		}
		return implode("\n\n", $selected_paragraphs);
	}

	/**
	 * get a url
	 *
	 * @return string
	 */
	public function url(): string {
		$domains = $this->getDataFile('domain_tlds.json');
		$domain_name = $this->getDataFile('words.json');
		$domain = $this->options($domain_name);
		$tld = $this->options($domains);

		return 'https://' . $domain . $tld;
	}

	/**
	 * get a phone number
	 *
	 * @return string
	 */
	public function phoneNumber(): string{
		$area_codes = $this->getDataFile('area_codes.json');
		$area_code = $this->options($area_codes);
		$exchange_code = $this->getRandomInt(200, 999);
		$subscriber_number = $this->getRandomInt(1000, 9999);

		return '1' . $area_code . '-' . $exchange_code . '-' . $subscriber_number;
	}

	/**
	 * get a full address
	 *
	 * @return string
	 */
	public function fullAddress(): string {
		$street_names = $this->getDataFile('address_line_1.json');
		$city_names = $this->getDataFile('address_city.json');
		$state_names = $this->getDataFile('address_state.json');
		$zip_codes = $this->getDataFile('address_zip.json');

		$street = $this->options($street_names);
		$city_name = $this->options($city_names);
		$state_name = $this->options($state_names);
		$zip_code = $this->options($zip_codes);

		return $street . ', ' . $city_name . ', ' . $state_name . ' ' . $zip_code;
	}

	/**
	 * get a street address
	 *
	 * @return string
	 */
	public function addressLine1(): string {
		$street_names = $this->getDataFile('address_line_1.json');
		$street = $this->options($street_names);
		return $street;
	}

	/**
	 * get a street address
	 *
	 * @return string
	 */
	public function addressLine2(): string {
		$street_names = $this->getDataFile('address_line_2.json');
		$street = $this->options($street_names);
		return $street;
	}

	/**
	 * get a city
	 *
	 * @return string
	 */
	public function city(): string {
		$city_names = $this->getDataFile('address_city.json');
		$city_name = $this->options($city_names);
		return $city_name;
	}

	/**
	 * get a state
	 *
	 * @return string
	 */
	public function state(): string {
		$state_names = $this->getDataFile('address_state.json');
		$state_name = $this->options($state_names);
		return $state_name;
	}

	/**
	 * get a state
	 *
	 * @return string
	 */
	public function stateLong(): string {
		$state_names = $this->getDataFile('state.json');
		$state_name = $this->options($state_names);
		$state_name = str_replace('_', ' ', $state_name);
		return $state_name;
	}

	/**
	 * get a zip code
	 *
	 * @return string
	 */
	public function zip(): string {
		$zip_codes = $this->getDataFile('address_zip.json');
		$zip_code = $this->options($zip_codes);
		return $zip_code;
	}

	/**
	 * Get a tiny int (0 or 1)
	 *
	 * @return integer
	 */
	public function tinyInt(): int {
		return $this->options([0, 1]);
	}
}