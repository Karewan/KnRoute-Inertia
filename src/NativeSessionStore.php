<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

use Closure;
use LogicException;
use RuntimeException;

/**
 * Session store backed by $_SESSION, whatever its save handler.
 *
 * A read never creates a session: it resumes the session of the client only when its cookie is present.
 * A write starts the session when needed.
 */
final class NativeSessionStore implements SessionStore
{
	private readonly Closure $starter;

	/**
	 * @param Closure():void|null $starter starts the session the way the application does, session_start() by default
	 */
	public function __construct(?Closure $starter = null)
	{
		$this->starter = $starter ?? static function (): void {
			if (!session_start()) throw new RuntimeException('Failed to start the session');
		};
	}

	public function pull(string $key): mixed
	{
		if (!$this->isActive(false) || !isset($_SESSION[$key])) return null;

		$value = $_SESSION[$key];
		unset($_SESSION[$key]);
		return $value;
	}

	public function put(string $key, mixed $value): void
	{
		if (!$this->isActive(true)) throw new LogicException('The session is not active');

		$_SESSION[$key] = $value;
	}

	private function isActive(bool $start): bool
	{
		$status = session_status();
		if ($status === PHP_SESSION_ACTIVE) return true;
		if ($status === PHP_SESSION_DISABLED) return false;
		if (!$start && !isset($_COOKIE[session_name()])) return false;

		($this->starter)();
		return session_status() === PHP_SESSION_ACTIVE;
	}
}
