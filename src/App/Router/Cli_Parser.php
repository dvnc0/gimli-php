<?php
declare(strict_types=1);

namespace Gimli\Router;

class Cli_Parser {
	public function __construct(
		protected array $args,
	) {}

	/**
	 * Parse the arguments
	 * 
	 * @return array
	 */
	public function parse(): array {
		$lexemes = [
			'dash' => '-',
			'word' => '/[a-zA-Z0-9_-]+/',
			'equal' => '=',
			'space' => '\s',
		];

		$arg_output = [
			'subcommand' => '',
			'options' => [],
			'flags' => [],
		];

		for ($index = 0; $index < count($this->args); $index++) {
			$arg = $this->args[$index];
			$parts = str_split($arg);
			$i = 0;
			$char = $parts[$i];

			if ($char === $lexemes['dash']) {
				if ($this->peak($i, $parts) === $lexemes['dash']) {
					$option = explode($lexemes['equal'], $arg);
					if (empty($option[1])) {
						if (preg_match($lexemes['word'], $this->args[$index + 1]) > 0) {
							[$option[1], $new_index] = $this->findOptionValue($index);
							$index = $new_index - 1;
						}
					}
					$arg_output['options'][] = [
						'option' => str_replace('--', '', $option[0]),
						'value' => $option[1],
					];
					continue;
				}
			}

			if ($char === $lexemes['dash']) {
				$flag = $this->peak($i, $parts);
				$arg_output['flags'][] = str_replace('-', '', $flag);
				continue;
			}

			if (preg_match($lexemes['word'], $arg) > 0) {
				$arg_output['subcommand'] = $arg;
				continue;
			}
		}

		return $arg_output;
	}

	/**
	 * Find the option value
	 * 
	 * @param int $index
	 * 
	 * @return array
	 */
	public function findOptionValue(int $index): array {
		$lexemes = [
			'dash' => '-',
			'word' => '/[a-zA-Z0-9_-]+/',
		];

		$i = $index + 1;
		$out_string = '';
		for ($i; $i < count($this->args); $i++) {
			if (preg_match($lexemes['word'], $this->args[$i]) > 0) {
				$parts = str_split($this->args[$i]);
				if ($parts[0] === $lexemes['dash']) {
					return [trim($out_string), $i--];
				}
				$out_string .= $this->args[$i] . ' ';
			} else {
				return [trim($out_string), $i--];
			}
		}

		return [trim($out_string), $i--];
	}

	/**
	 * Peak
	 * 
	 * @param int $index
	 * @param array $value
	 * 
	 * @return string
	 */
	public function peak(int $index, array $value): string {
		return $value[$index + 1];
	}
}