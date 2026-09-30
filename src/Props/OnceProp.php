<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

use Closure;
use Karewan\KnRouteInertia\Props\Concerns\ResolvesOnce;

/** Resolved once, then remembered by the client across the pages that include it. */
final class OnceProp implements Prop, Onceable
{
	use ResolvesOnce;

	public function __construct(private readonly Closure $callback)
	{
		$this->once = true;
	}

	public function resolve(): mixed
	{
		return ($this->callback)();
	}
}
