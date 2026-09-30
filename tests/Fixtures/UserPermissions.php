<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Karewan\KnRouteInertia\ProvidesInertiaProperties;
use Karewan\KnRouteInertia\RenderContext;

final class UserPermissions implements ProvidesInertiaProperties
{
	public function __construct(private readonly bool $admin) {}

	public function toInertiaProperties(RenderContext $context): array
	{
		return ['canEdit' => $this->admin, 'isAdmin' => $this->admin, 'component' => $context->component];
	}
}
