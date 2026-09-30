<?php

declare(strict_types=1);

namespace Samples\Controllers;

use Karewan\KnRoute\Attributes\Get;
use Karewan\KnRouteInertia\Inertia;
use Samples\Users;

final class HomeController
{
	#[Get('/')]
	public function index(): void
	{
		Inertia::render('Home', [
			'time' => fn(): string => date('H:i:s'),
			// Loaded by a second request once the page is displayed.
			'stats' => Inertia::defer(function (): array {
				usleep(300000);
				return ['users' => count(Users::all()), 'php' => PHP_VERSION];
			}),
			// Resolved once, then remembered by the client while it visits the pages including it.
			'countries' => Inertia::once(fn(): array => ['France', 'Germany', 'Italy', 'Spain']),
		]);
	}

	#[Get('/docs')]
	public function docs(): void
	{
		// An external page: the client leaves the application.
		Inertia::location('https://inertiajs.com/docs/v3');
	}
}
