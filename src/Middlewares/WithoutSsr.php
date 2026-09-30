<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Middlewares;

use Attribute;
use Karewan\KnRoute\IMiddleware;
use Karewan\KnRouteInertia\Inertia;
use Override;

/** Route middleware: render the pages of a controller or an action on the client only. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class WithoutSsr implements IMiddleware
{
	#[Override]
	public function before(): void
	{
		Inertia::disableSsr();
	}

	#[Override]
	public function after(): void {}
}
