<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

/**
 * A prop resolved by the adapter instead of being sent as is.
 *
 * Its behaviour on full visits and partial reloads is given by the capability interfaces it
 * implements: IgnoreFirstLoad, Deferrable, Mergeable, Onceable and Rescuable.
 */
interface Prop
{
	/** Produce the value sent to the client. */
	public function resolve(): mixed;
}
