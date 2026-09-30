<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

use Closure;
use Karewan\KnRouteInertia\Props\Concerns\MergesProps;
use Karewan\KnRouteInertia\Props\Concerns\ResolvesOnce;

/** Merged by the client with the value it already holds on partial reloads, replaced on full visits. */
final class MergeProp implements Prop, Mergeable, Onceable
{
	use MergesProps, ResolvesOnce;

	/** @param mixed $value the value, or a Closure producing it */
	public function __construct(private readonly mixed $value)
	{
		$this->merge = true;
	}

	public function resolve(): mixed
	{
		return $this->value instanceof Closure ? ($this->value)() : $this->value;
	}
}
