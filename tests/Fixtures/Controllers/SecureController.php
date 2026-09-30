<?php

declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use Karewan\KnRoute\Attributes\Get;
use Karewan\KnRouteInertia\Inertia;
use Karewan\KnRouteInertia\Middlewares\EncryptHistory;
use Karewan\KnRouteInertia\Middlewares\WithoutSsr;

#[EncryptHistory]
final class SecureController
{
	#[Get('/account')]
	public function account(): void
	{
		Inertia::render('Account');
	}

	#[Get('/account/public'), EncryptHistory(false), WithoutSsr]
	public function public(): void
	{
		Inertia::render('Account/Public');
	}
}
