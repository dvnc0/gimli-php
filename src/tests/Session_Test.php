<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Gimli\Session\Session;
use Gimli\Session\Session_Interface;

/**
 * Session test cases
 */
class Session_Test extends TestCase {

	/**
	 * @var Session|null $session
	 */
	private ?Session $session = null;

	/**
	 * @var array $original_server
	 */
	private array $original_server = [];

	/**
	 * @var array $original_session
	 */
	private array $original_session = [];

	/**
	 * @var bool $session_was_active
	 */
	private bool $session_was_active = false;

	/**
	 * Setup method
	 * 
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Backup original $_SERVER and $_SESSION state
		$this->original_server = $_SERVER;
		$this->original_session = $_SESSION ?? [];
		$this->session_was_active = session_status() === PHP_SESSION_ACTIVE;
		
		// Clean up any existing session
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_destroy();
		}
		
		// Reset static properties via reflection
		$this->resetSessionStaticProperties();
		
		// Set up a minimal $_SERVER environment
		$_SERVER = array_merge($_SERVER, [
			'HTTP_USER_AGENT' => 'PHPUnit Test Runner',
			'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
			'REMOTE_ADDR' => '127.0.0.1',
			'REQUEST_SCHEME' => 'http',
			'SERVER_PORT' => '80'
		]);
		
		$_SESSION = [];
	}

	/**
	 * Teardown method
	 * 
	 * @return void
	 */
	protected function tearDown(): void {
		// Clean up session if it was created during test
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_destroy();
		}
		
		// Restore original state
		$_SERVER = $this->original_server;
		$_SESSION = $this->original_session;
		
		// Reset static properties
		$this->resetSessionStaticProperties();
		
		// Restart session if it was originally active
		if ($this->session_was_active && session_status() === PHP_SESSION_NONE) {
			session_start();
		}
		
		$this->session = null;
		parent::tearDown();
	}

	/**
	 * Reset Session static properties using reflection
	 * 
	 * @return void
	 */
	private function resetSessionStaticProperties(): void {
		try {
			$reflection = new ReflectionClass(Session::class);
			
			// Reset initialized flag
			$initialized_property = $reflection->getProperty('initialized');
			$initialized_property->setAccessible(true);
			$initialized_property->setValue(null, false);
			
			// Reset instance
			$instance_property = $reflection->getProperty('instance');
			$instance_property->setAccessible(true);
			$instance_property->setValue(null, null);
			
			// Reset security config to defaults
			$config_property = $reflection->getProperty('security_config');
			$config_property->setAccessible(true);
			$config_property->setValue(null, [
				'regenerate_interval' => 300,
				'max_lifetime' => 7200,
				'absolute_max_lifetime' => 28800,
				'max_data_size' => 1048576,
				'allowed_keys_pattern' => '/^[a-zA-Z0-9._-]+$/',
				'enable_fingerprinting' => true,
				'enable_ip_validation' => false,
				'cookie_httponly' => true,
				'cookie_secure' => 'auto',
				'cookie_samesite' => 'Strict',
				'use_strict_mode' => true,
				'use_only_cookies' => true,
				'cookie_lifetime' => 0,
				'gc_probability' => 1,
				'gc_divisor' => 100,
				'entropy_length' => 32,
				'hash_function' => 'sha256',
				'hash_bits_per_character' => 6,
			]);
		} catch (ReflectionException $e) {
			// If reflection fails, continue with test
		}
	}

	/**
	 * Create a session instance for testing
	 * 
	 * @param array|null $config
	 * @return Session
	 */
	private function createSession(?array $config = null): Session {
		$this->session = new Session($config);
		return $this->session;
	}

	// === BASIC FUNCTIONALITY TESTS ===

	public function testSessionImplementsInterface(): void {
		$session = $this->createSession();
		$this->assertInstanceOf(Session_Interface::class, $session);
	}

	public function testBasicSetAndGet(): void {
		$session = $this->createSession();
		
		$session->set('test_key', 'test_value');
		$this->assertEquals('test_value', $session->get('test_key'));
	}

	public function testGetNonExistentKey(): void {
		$session = $this->createSession();
		$this->assertNull($session->get('non_existent_key'));
	}

	public function testHasMethod(): void {
		$session = $this->createSession();
		
		$session->set('existing_key', 'value');
		$this->assertTrue($session->has('existing_key'));
		$this->assertFalse($session->has('non_existent_key'));
	}

	public function testDeleteMethod(): void {
		$session = $this->createSession();
		
		$session->set('key_to_delete', 'value');
		$this->assertTrue($session->has('key_to_delete'));
		
		$session->delete('key_to_delete');
		$this->assertFalse($session->has('key_to_delete'));
	}

	public function testClearMethod(): void {
		$session = $this->createSession();
		
		$session->set('key1', 'value1');
		$session->set('key2', 'value2');
		
		$session->clear();
		
		$this->assertFalse($session->has('key1'));
		$this->assertFalse($session->has('key2'));
		
		// Verify security metadata is preserved
		$this->assertTrue(isset($_SESSION['_gimli_session_created']));
		$this->assertTrue(isset($_SESSION['_gimli_session_last_activity']));
	}

	public function testGetAllMethod(): void {
		$session = $this->createSession();
		
		$session->set('key1', 'value1');
		$session->set('key2', 'value2');
		
		$all_data = $session->getAll();
		
		$this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $all_data);
		
		// Verify internal keys are filtered out
		$this->assertArrayNotHasKey('_gimli_session_created', $all_data);
		$this->assertArrayNotHasKey('_gimli_session_last_activity', $all_data);
	}

	// === DOT NOTATION TESTS ===

	public function testDotNotationSet(): void {
		$session = $this->createSession();
		
		$session->set('user.name', 'John Doe');
		$session->set('user.email', 'john@example.com');
		
		$this->assertEquals('John Doe', $session->get('user.name'));
		$this->assertEquals('john@example.com', $session->get('user.email'));
	}

	public function testDotNotationGet(): void {
		$session = $this->createSession();
		
		$session->set('config.database.host', 'localhost');
		$this->assertEquals('localhost', $session->get('config.database.host'));
		
		// Test getting non-existent nested key
		$this->assertNull($session->get('config.database.non_existent'));
	}

	public function testDotNotationHas(): void {
		$session = $this->createSession();
		
		$session->set('app.settings.theme', 'dark');
		
		$this->assertTrue($session->has('app.settings.theme'));
		$this->assertFalse($session->has('app.settings.nonexistent'));
		$this->assertFalse($session->has('app.nonexistent.theme'));
	}

	public function testDotNotationDelete(): void {
		$session = $this->createSession();
		
		$session->set('user.profile.age', 30);
		$session->set('user.profile.city', 'New York');
		
		$this->assertTrue($session->has('user.profile.age'));
		
		$session->delete('user.profile.age');
		
		$this->assertFalse($session->has('user.profile.age'));
		$this->assertTrue($session->has('user.profile.city')); // Other keys should remain
	}

	// === CONFIGURATION TESTS ===

	public function testCustomConfiguration(): void {
		$config = [
			'max_lifetime' => 1800, // 30 minutes
			'regenerate_interval' => 600, // 10 minutes
			'enable_fingerprinting' => false,
		];
		
		$session = $this->createSession($config);
		$this->assertInstanceOf(Session::class, $session);
	}

	public function testStaticConfigure(): void {
		$config = [
			'max_lifetime' => 3600,
			'enable_fingerprinting' => false,
		];
		
		Session::configure($config);
		$session = $this->createSession();
		$this->assertInstanceOf(Session::class, $session);
	}

	public function testGetInstance(): void {
		$instance1 = Session::getInstance();
		$instance2 = Session::getInstance();
		
		$this->assertSame($instance1, $instance2);
		$this->assertInstanceOf(Session::class, $instance1);
	}

	// === VALIDATION TESTS ===

	public function testEmptyKeyValidation(): void {
		$session = $this->createSession();
		
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Session key cannot be empty');
		
		$session->set('', 'value');
	}

	public function testLongKeyValidation(): void {
		$session = $this->createSession();
		
		$long_key = str_repeat('a', 256); // Too long
		
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Session key too long');
		
		$session->set($long_key, 'value');
	}

	public function testInvalidKeyCharacters(): void {
		$session = $this->createSession();
		
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Session key contains invalid characters');
		
		$session->set('key with spaces', 'value');
	}

	public function testInternalKeyAccess(): void {
		$session = $this->createSession();
		
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Cannot access internal session keys');
		
		$session->get('_gimli_session_created');
	}

	public function testDataSizeValidation(): void {
		$session = $this->createSession(['max_data_size' => 100]); // Very small limit
		
		$large_data = str_repeat('x', 200); // Too large
		
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Session data too large');
		
		$session->set('large_key', $large_data);
	}

	// === SECURITY TESTS ===

	public function testSessionRegenerate(): void {
		$session = $this->createSession();
		
		// Set some data
		$session->set('test_key', 'test_value');
		$old_id = $session->getId();
		
		$result = $session->regenerate();
		
		$this->assertTrue($result);
		$this->assertNotEquals($old_id, $session->getId());
		$this->assertEquals('test_value', $session->get('test_key')); // Data should persist
	}

	public function testSessionDestroy(): void {
		$session = $this->createSession();
		
		$session->set('test_key', 'test_value');
		$this->assertTrue($session->has('test_key'));
		
		$result = $session->destroy();
		
		$this->assertTrue($result);
		$this->assertEquals(PHP_SESSION_NONE, session_status());
	}

	public function testSessionValidation(): void {
		$session = $this->createSession();
		$this->assertTrue($session->isValid());
	}

	public function testGetSessionId(): void {
		$session = $this->createSession();
		$session_id = $session->getId();
		
		$this->assertIsString($session_id);
		$this->assertNotEmpty($session_id);
	}

	// === FINGERPRINTING TESTS ===

	public function testFingerprintingDisabled(): void {
		$session = $this->createSession(['enable_fingerprinting' => false]);
		
		// Should work without issues
		$session->set('test_key', 'test_value');
		$this->assertEquals('test_value', $session->get('test_key'));
	}

	public function testFingerprintingWithDifferentUserAgent(): void {
		$session = $this->createSession(['enable_fingerprinting' => true]);
		
		// Initial setup
		$session->set('test_key', 'test_value');
		
		// Change user agent (simulate fingerprint mismatch)
		$_SERVER['HTTP_USER_AGENT'] = 'Different Browser';
		
		// Should throw exception on next access due to fingerprint mismatch
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Session fingerprint mismatch');
		
		$session->get('test_key');
	}

	// === IP VALIDATION TESTS ===

	public function testIpValidationDisabled(): void {
		$session = $this->createSession(['enable_ip_validation' => false]);
		
		$session->set('test_key', 'test_value');
		
		// Change IP
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
		
		// Should still work since IP validation is disabled
		$this->assertEquals('test_value', $session->get('test_key'));
	}

	public function testIpValidationEnabled(): void {
		$session = $this->createSession(['enable_ip_validation' => true]);
		
		$session->set('test_key', 'test_value');
		
		// Change IP
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
		
		// Should throw exception due to IP mismatch
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Session IP mismatch');
		
		$session->get('test_key');
	}

	// === TIMEOUT TESTS ===

	public function testInactivityTimeout(): void {
		$session = $this->createSession([
			'max_lifetime' => 1, // 1 second timeout
		]);
		
		$session->set('test_key', 'test_value');
		
		// Simulate time passing by manually setting last activity
		$_SESSION['_gimli_session_last_activity'] = time() - 2; // 2 seconds ago
		
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Session expired due to inactivity');
		
		$session->get('test_key');
	}

	public function testAbsoluteTimeout(): void {
		$session = $this->createSession([
			'absolute_max_lifetime' => 1, // 1 second absolute max
		]);
		
		$session->set('test_key', 'test_value');
		
		// Simulate session being very old
		$_SESSION['_gimli_session_created'] = time() - 2; // 2 seconds ago
		
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Session expired due to absolute age limit');
		
		$session->get('test_key');
	}

	// === HTTPS DETECTION TESTS ===

	public function testHttpsDetectionWithHttpsHeader(): void {
		$_SERVER['HTTPS'] = 'on';
		$session = $this->createSession();
		$this->assertInstanceOf(Session::class, $session);
	}

	public function testHttpsDetectionWithForwardedProto(): void {
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
		$session = $this->createSession();
		$this->assertInstanceOf(Session::class, $session);
	}

	public function testHttpsDetectionWithPort443(): void {
		$_SERVER['SERVER_PORT'] = '443';
		$session = $this->createSession();
		$this->assertInstanceOf(Session::class, $session);
	}

	// === IP DETECTION TESTS ===

	public function testClientIpWithCloudflare(): void {
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.1';
		$session = $this->createSession(['enable_ip_validation' => true]);
		$this->assertInstanceOf(Session::class, $session);
	}

	public function testClientIpWithXForwardedFor(): void {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.2, 198.51.100.1';
		$session = $this->createSession(['enable_ip_validation' => true]);
		$this->assertInstanceOf(Session::class, $session);
	}

	// === PWA COMPATIBILITY TESTS ===

	public function testPwaUserAgentNormalization(): void {
		// Test WebView indicator normalization
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 10; SM-G975F) [wv] Chrome/91.0';
		$session = $this->createSession(['enable_fingerprinting' => true]);
		
		$session->set('test_key', 'test_value');
		$this->assertEquals('test_value', $session->get('test_key'));
	}

	// === ERROR HANDLING TESTS ===

	public function testSessionNotActiveHandling(): void {
		// Destroy any active session
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_destroy();
		}
		
		// Session should auto-initialize when needed
		$session = $this->createSession();
		$session->set('test_key', 'test_value');
		$this->assertEquals('test_value', $session->get('test_key'));
	}

	public function testRegenerateWithInactiveSession(): void {
		$session = $this->createSession();
		
		// Manually destroy session
		session_destroy();
		
		// Should return false when session is not active
		$result = $session->regenerate();
		$this->assertFalse($result);
	}

	public function testDestroyWithInactiveSession(): void {
		$session = $this->createSession();
		
		// Manually destroy session
		session_destroy();
		
		// Should return false when session is already destroyed
		$result = $session->destroy();
		$this->assertFalse($result);
	}

	// === COMPLEX SCENARIO TESTS ===

	public function testComplexNestedData(): void {
		$session = $this->createSession();
		
		$complex_data = [
			'user' => [
				'id' => 123,
				'profile' => [
					'name' => 'John Doe',
					'settings' => [
						'theme' => 'dark',
						'notifications' => true
					]
				]
			]
		];
		
		$session->set('app_data', $complex_data);
		$retrieved_data = $session->get('app_data');
		
		$this->assertEquals($complex_data, $retrieved_data);
	}

	public function testMixedDotNotationAndRegular(): void {
		$session = $this->createSession();
		
		$session->set('regular_key', 'regular_value');
		$session->set('nested.key', 'nested_value');
		$session->set('deep.nested.key', 'deep_value');
		
		$all_data = $session->getAll();
		
		$this->assertEquals('regular_value', $all_data['regular_key']);
		$this->assertEquals('nested_value', $all_data['nested']['key']);
		$this->assertEquals('deep_value', $all_data['deep']['nested']['key']);
	}

	public function testSessionPersistenceAcrossInstances(): void {
		$session1 = $this->createSession();
		$session1->set('persistent_key', 'persistent_value');
		
		// Create a new instance (should use same session)
		$session2 = new Session();
		$this->assertEquals('persistent_value', $session2->get('persistent_key'));
	}
} 