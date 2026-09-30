<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

/** A prop value that transforms itself, knowing where it is sent. */
interface ProvidesInertiaProperty
{
	public function toInertiaProperty(PropertyContext $context): mixed;
}
