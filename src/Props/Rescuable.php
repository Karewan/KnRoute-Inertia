<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

/** A prop whose failure is reported and omitted instead of failing the response. */
interface Rescuable
{
	public function shouldRescue(): bool;
}
