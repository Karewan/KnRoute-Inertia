<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Ssr;

final readonly class SsrResponse
{
	/**
	 * @param string $head elements of the document head (title, meta...)
	 * @param string $body the page object script and the rendered root element
	 */
	public function __construct(
		public string $head,
		public string $body,
	) {}
}
