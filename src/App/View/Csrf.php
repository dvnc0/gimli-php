<?php
declare(strict_types=1);

namespace Gimli\View;

use Gimli\Session\Session;

use function Gimli\Injector\resolve;

class Csrf
{
	public static function generate(): string
	{
		$token = bin2hex(random_bytes(32));
		$expire_time = time() + 60 * 15;
		$Session = resolve(Session::class);
		$tokens = $Session->get('csrf_tokens');
		$tokens[$token] = $expire_time;
		$Session->set('csrf_token', $tokens);
		return $token;
	}

	public static function verify(string $token): bool
	{
		$Session = resolve(Session::class);
		if ($Session->has('csrf_token') === false) {
			return false;
		}

		$tokens = $Session->get('csrf_token');

		if (isset($tokens[$token]) === false) {
			return false;
		}

		if ($tokens[$token] < time()) {
			unset($tokens[$token]);
			$Session->set('csrf_token', $tokens);
			return false;
		}

		unset($tokens[$token]);
		$Session->set('csrf_token', $tokens);

		return true;
	}
}