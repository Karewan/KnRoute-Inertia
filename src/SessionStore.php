<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

/**
 * Keeps the flash data, the validation errors and the history flags of a redirect for the next page.
 */
interface SessionStore
{
	/** Read and remove a value, null when absent. */
	public function pull(string $key): mixed;

	public function put(string $key, mixed $value): void;
}
