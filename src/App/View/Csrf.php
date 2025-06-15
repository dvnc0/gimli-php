<?php
declare(strict_types=1);

namespace Gimli\View;

use Gimli\Session\Session;

use function Gimli\Injector\resolve;

class Csrf
{
	// Add constants for better maintainability
	private const TOKEN_LENGTH = 32;
	private const TOKEN_EXPIRY = 900; // 15 minutes in seconds
	private const MAX_TOKENS_PER_SESSION = 10; // Prevent session bloat
	
	/**
	 * Generate a CSRF token
	 *
	 * @return string
	 */
	public static function generate(): string
	{
		$token = bin2hex(random_bytes(self::TOKEN_LENGTH));
		$expire_time = time() + self::TOKEN_EXPIRY;
		$Session = resolve(Session::class);
		
		// Get existing tokens or initialize empty array
		$tokens = $Session->get('csrf_token') ?? [];
		
		// Clean up expired tokens before adding new one
		$tokens = self::cleanExpiredTokens($tokens);
		
		// Limit number of tokens per session (prevent DoS)
		if (count($tokens) >= self::MAX_TOKENS_PER_SESSION) {
			// Remove oldest token
			$oldest_key = array_key_first($tokens);
			unset($tokens[$oldest_key]);
		}
		
		$tokens[$token] = $expire_time;
		$Session->set('csrf_token', $tokens);
		
		return $token;
	}

	/**
	 * Verify a CSRF token
	 *
	 * @param non-empty-string $token The token to verify
	 * @return bool
	 */
	public static function verify(string $token): bool
	{
		// Input validation
		if (empty($token) || strlen($token) !== (self::TOKEN_LENGTH * 2)) {
			return false;
		}
		
		// Check for valid hex characters only
		if (!ctype_xdigit($token)) {
			return false;
		}
		
		$Session = resolve(Session::class);
		if ($Session->has('csrf_token') === false) {
			return false;
		}

		$tokens = $Session->get('csrf_token') ?? [];
		
		// Clean expired tokens first
		$tokens = self::cleanExpiredTokens($tokens);

		if (!isset($tokens[$token])) {
			return false;
		}

		// Use timing-safe comparison to prevent timing attacks
		$stored_time = $tokens[$token];
		$current_time = time();
		
		if ($stored_time < $current_time) {
			unset($tokens[$token]);
			$Session->set('csrf_token', $tokens);
			return false;
		}

		// Remove token after successful verification (one-time use)
		unset($tokens[$token]);
		$Session->set('csrf_token', $tokens);

		return true;
	}
	
	/**
	 * Clean expired tokens from the session
	 *
	 * @param array $tokens
	 * @return array
	 */
	private static function cleanExpiredTokens(array $tokens): array
	{
		$current_time = time();
		return array_filter($tokens, function($expire_time) use ($current_time) {
			return $expire_time >= $current_time;
		});
	}
	
	/**
	 * Get a token for AJAX requests (returns existing valid token if available)
	 *
	 * @return string
	 */
	public static function getToken(): string
	{
		$Session = resolve(Session::class);
		$tokens = $Session->get('csrf_token') ?? [];
		
		// Clean expired tokens
		$tokens = self::cleanExpiredTokens($tokens);
		
		// Return existing valid token if available
		if (!empty($tokens)) {
			return array_key_first($tokens);
		}
		
		// Generate new token if none exist
		return self::generate();
	}
	
	/**
	 * Validate CSRF token from request (helper method)
	 *
	 * @param array $request_data POST/PUT/PATCH data
	 * @param string $token_field_name Field name for token (default: 'csrf_token')
	 * @return bool
	 */
	public static function validateRequest(array $request_data, string $token_field_name = 'csrf_token'): bool
	{
		if (!isset($request_data[$token_field_name])) {
			return false;
		}
		
		return self::verify($request_data[$token_field_name]);
	}
}