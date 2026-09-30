<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

use Closure;
use Karewan\KnRouteInertia\Props\Concerns\MergesProps;
use Karewan\KnRouteInertia\Props\Concerns\ResolvesOnce;

/** Announced on a full visit, then loaded by the client with the other props of its group. */
final class DeferProp implements Prop, Deferrable, IgnoreFirstLoad, Mergeable, Onceable, Rescuable
{
	use MergesProps, ResolvesOnce;

	/**
	 * @param string $group props of a group are loaded by the same request, groups in parallel
	 * @param bool $rescue report a failure and omit the prop instead of failing the response
	 */
	public function __construct(
		private readonly Closure $callback,
		private readonly string $group = 'default',
		private readonly bool $rescue = false,
	) {}

	public function resolve(): mixed
	{
		return ($this->callback)();
	}

	public function shouldDefer(): bool
	{
		return true;
	}

	public function group(): string
	{
		return $this->group;
	}

	public function shouldRescue(): bool
	{
		return $this->rescue;
	}
}
