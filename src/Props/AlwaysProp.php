<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

use Closure;

/** Sent on every response, including the partial reloads that do not select it. */
final class AlwaysProp implements Prop
{
	/** @param mixed $value the value, or a Closure producing it */
	public function __construct(private readonly mixed $value) {}

	public function resolve(): mixed
	{
		return $this->value instanceof Closure ? ($this->value)() : $this->value;
	}
}
