<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props\Concerns;

/** Implements Deferrable for the props that are not deferred by default. */
trait DefersProps
{
	protected bool $deferred = false;

	protected string $deferGroup = 'default';

	/** Skip the prop on full visits: the client loads it with the other props of its group. */
	public function defer(string $group = 'default'): static
	{
		$this->deferred = true;
		$this->deferGroup = $group;
		return $this;
	}

	public function shouldDefer(): bool
	{
		return $this->deferred;
	}

	public function group(): string
	{
		return $this->deferGroup;
	}
}
