<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

use Closure;
use Karewan\KnRouteInertia\Props\Concerns\ResolvesOnce;

/** Never sent on a full visit, and not announced: only a partial reload selecting it resolves it. */
final class OptionalProp implements Prop, IgnoreFirstLoad, Onceable
{
	use ResolvesOnce;

	public function __construct(private readonly Closure $callback) {}

	public function resolve(): mixed
	{
		return ($this->callback)();
	}
}
