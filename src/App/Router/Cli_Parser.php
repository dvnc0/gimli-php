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

			if ($char === $lexemes['dash'] && $this->peak($i, $parts) === $lexemes['dash']) {
				$option = explode($lexemes['equal'], $arg);
				if ($this->isOptionWithNoEqual($option[1] ?? '', $index, $lexemes['word'])) {
					[$option[1], $new_index] = $this->findOptionValue($index);
					$index = $new_index - 1;
				}

				if (!empty($option[1])) {
					$arg_output['options'][str_replace('--', '', $option[0])] = $option[1];
					continue;
				}

				$arg_output['flags'][] = str_replace('--', '', $option[0]);
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
	 * Check if the option has no equal
	 * --foo-bar some value vs --foo-bar=some value
	 * 
	 * @param string $option
	 * @param int $index
	 * @param string $lexeme
	 * 
	 * @return bool
	 */
	protected function isOptionWithNoEqual(string $option, int $index, string $lexeme): bool {
		return (
			empty($option) 
			&& !empty($this->args[$index + 1]) 
			&& preg_match($lexeme, $this->args[$index + 1]) > 0
		) === true;
	}

	/**
	 * Find the option value
	 * 
	 * @param int $index
	 * 
	 * @return array
	 */
	protected function findOptionValue(int $index): array {
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
	protected function peak(int $index, array $value): string {
		return $value[$index + 1];
	}
}