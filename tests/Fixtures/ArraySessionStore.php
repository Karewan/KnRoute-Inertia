<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Karewan\KnRouteInertia\SessionStore;

final class ArraySessionStore implements SessionStore
{
	/** @var array<string,mixed> */
	public array $data = [];

	public function pull(string $key): mixed
	{
		$value = $this->data[$key] ?? null;
		unset($this->data[$key]);
		return $value;
	}

	public function put(string $key, mixed $value): void
	{
		$this->data[$key] = $value;
	}
}
