<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use SessionHandlerInterface;

final class MemorySessionHandler implements SessionHandlerInterface
{
	/** @var array<string,string> */
	public static array $sessions = [];

	public function open(string $path, string $name): bool
	{
		return true;
	}

	public function close(): bool
	{
		return true;
	}

	public function read(string $id): string
	{
		return self::$sessions[$id] ?? '';
	}

	public function write(string $id, string $data): bool
	{
		self::$sessions[$id] = $data;
		return true;
	}

	public function destroy(string $id): bool
	{
		unset(self::$sessions[$id]);
		return true;
	}

	public function gc(int $max_lifetime): int
	{
		return 0;
	}
}
