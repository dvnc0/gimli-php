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
		$Session = resolve(Session::class);
		$Session->set('csrf_token', $token);
		return $token;
	}

	public static function verify(string $token): bool
	{
		$Session = resolve(Session::class);
		if ($Session->has('csrf_token') === false) {
			return false;
		}

		if ($Session->get('csrf_token') !== $token) {
			return false;
		}

		return true;
	}
}