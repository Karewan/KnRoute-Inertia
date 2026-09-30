<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Ssr;

/** Renders the pages on the server, through the Inertia SSR server or any other renderer. */
interface SsrGateway
{
	/**
	 * @param array<string,mixed> $page the page object
	 * @return SsrResponse|null null to leave the rendering to the client
	 * @throws SsrException when the rendering failed: it is reported and the client renders the page
	 */
	public function render(array $page): ?SsrResponse;
}
