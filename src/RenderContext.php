<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

final readonly class RenderContext
{
	/** @param string $component the page component */
	public function __construct(public string $component) {}
}
