<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Middlewares;

use Attribute;
use Karewan\KnRoute\IMiddleware;
use Karewan\KnRouteInertia\Inertia;
use Override;

/** Route middleware: encrypt the history state of the pages of a controller or an action. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class EncryptHistory implements IMiddleware
{
	public function __construct(private readonly bool $encrypt = true) {}

	#[Override]
	public function before(): void
	{
		Inertia::encryptHistory($this->encrypt);
	}

	#[Override]
	public function after(): void {}
}
