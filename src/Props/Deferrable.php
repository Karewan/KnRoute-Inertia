<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

/** A prop announced on a full visit and loaded by the client in a follow-up request of its group. */
interface Deferrable
{
	public function shouldDefer(): bool;

	public function group(): string;
}
