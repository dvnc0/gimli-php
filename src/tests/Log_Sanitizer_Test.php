<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use function Gimli\Http\sanitize_for_log;

class Log_Sanitizer_Test extends TestCase {

	public function testPlainTextUnchanged(): void {
		$this->assertSame('hello /path?x=1', sanitize_for_log('hello /path?x=1'));
	}

	public function testEmptyString(): void {
		$this->assertSame('', sanitize_for_log(''));
	}

	public function testControlCharsEscaped(): void {
		$this->assertSame('a\nb\rc', sanitize_for_log("a\nb\rc"));
		$this->assertSame('a\000b', sanitize_for_log("a\x00b"));
		$this->assertSame('a\177b', sanitize_for_log("a\x7fb"));
		$this->assertSame('a\tb', sanitize_for_log("a\tb"));
	}

	public function testNoRawControlCharsRemain(): void {
		$out = sanitize_for_log(implode('', array_map('chr', range(0, 31))) . "\x7f");
		$this->assertSame(0, preg_match('/[\x00-\x1F\x7F]/', $out));
	}

	public function testDefaultTruncationAt200(): void {
		$this->assertSame(200, strlen(sanitize_for_log(str_repeat('a', 500))));
	}

	public function testCustomMaxLength(): void {
		$this->assertSame('abc', sanitize_for_log('abcdef', 3));
	}

	public function testTruncatesBeforeEscaping(): void {
		// 3 raw bytes kept, then each newline escapes to 2 chars
		$this->assertSame('\n\n\n', sanitize_for_log("\n\n\n\n\n", 3));
	}

	public function testLogInjectionPayloadNeutralised(): void {
		$out = sanitize_for_log("x\r\n[ERROR] forged");
		$this->assertStringNotContainsString("\n", $out);
		$this->assertStringNotContainsString("\r", $out);
	}
}
