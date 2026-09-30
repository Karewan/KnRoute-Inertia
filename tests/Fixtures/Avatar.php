<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Karewan\KnRouteInertia\PropertyContext;
use Karewan\KnRouteInertia\ProvidesInertiaProperty;

final class Avatar implements ProvidesInertiaProperty
{
	public function __construct(private readonly int $size) {}

	public function toInertiaProperty(PropertyContext $context): mixed
	{
		return "{$context->component}:{$context->key}:{$this->size}:" . implode(',', array_keys($context->props));
	}
}
