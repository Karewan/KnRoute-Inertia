<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

final readonly class PropertyContext
{
	/**
	 * @param string $key dot-notation path of the prop
	 * @param array<array-key,mixed> $props the props of the same level, not resolved yet
	 * @param string $component the page component
	 */
	public function __construct(
		public string $key,
		public array $props,
		public string $component,
	) {}
}
