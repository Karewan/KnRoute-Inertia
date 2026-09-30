<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

use Karewan\KnRoute\Exceptions\StopRequestException;
use Karewan\KnRoute\IMiddleware;
use Override;

/**
 * Global middleware of the Inertia application, registered first:
 * $router->addGlobalMiddleware(new InertiaMiddleware());
 *
 * Before the routing, it answers the Inertia visits made with outdated assets, so their action never
 * runs. After the action, it fixes the redirects of Inertia visits (303 after PUT, PATCH and DELETE, 409
 * for a URL with a fragment), keeps the flash data and errors no page sent for the next one, and forgets
 * the request state.
 */
final class InertiaMiddleware implements IMiddleware
{
	#[Override]
	public function before(): void
	{
		if (Inertia::handleVersionChange()) {
			// A failed before hook gets no after hook: the request ends here.
			Inertia::finishRequest();
			throw new StopRequestException();
		}
	}

	#[Override]
	public function after(): void
	{
		Inertia::finishRequest();
	}
}
