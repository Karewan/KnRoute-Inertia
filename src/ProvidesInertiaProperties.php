<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

/**
 * A group of props reused by several pages. Listed without a key in the props (or in the shared
 * props), it is replaced by the props it provides.
 */
interface ProvidesInertiaProperties
{
	/** @return array<string,mixed> */
	public function toInertiaProperties(RenderContext $context): array;
}
