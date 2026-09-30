<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props\Concerns;

use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use UnitEnum;

/** Implements Onceable: custom key, forced refresh and expiration. */
trait ResolvesOnce
{
	protected bool $once = false;

	protected bool $refresh = false;

	protected ?string $key = null;

	protected ?int $expiresAt = null;

	public function once(bool $value = true, UnitEnum|string|null $as = null, DateTimeInterface|DateInterval|int|null $until = null): static
	{
		$this->once = $value;
		if ($as !== null) $this->as($as);
		if ($until !== null) $this->until($until);
		return $this;
	}

	/** Share the remembered value between the pages using the same key, whatever the prop name. */
	public function as(UnitEnum|string $key): static
	{
		$this->key = match (true) {
			$key instanceof BackedEnum => (string) $key->value,
			$key instanceof UnitEnum => $key->name,
			default => $key,
		};
		return $this;
	}

	/** Send a new value even when the client already remembers one. */
	public function fresh(bool $value = true): static
	{
		$this->refresh = $value;
		return $this;
	}

	/**
	 * Expire the remembered value at a date, after an interval, or after a number of seconds.
	 */
	public function until(DateTimeInterface|DateInterval|int $delay): static
	{
		$this->expiresAt = match (true) {
			$delay instanceof DateTimeInterface => (int) $delay->format('Uv'),
			$delay instanceof DateInterval => (int) (new DateTimeImmutable())->add($delay)->format('Uv'),
			default => (time() + $delay) * 1000,
		};
		return $this;
	}

	public function shouldResolveOnce(): bool
	{
		return $this->once;
	}

	public function shouldBeRefreshed(): bool
	{
		return $this->refresh;
	}

	public function getKey(): ?string
	{
		return $this->key;
	}

	public function expiresAt(): ?int
	{
		return $this->expiresAt;
	}
}
