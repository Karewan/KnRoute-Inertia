<?php

declare(strict_types=1);

namespace Samples\Controllers;

use Karewan\KnRoute\Attributes\Get;
use Karewan\KnRoute\Attributes\Post;
use Karewan\KnRouteInertia\Inertia;
use Karewan\KnRouteInertia\Middlewares\EncryptHistory;

#[EncryptHistory]
final class AccountController
{
	#[Get('/account')]
	public function index(): void
	{
		Inertia::render('Account', ['iban' => 'FR76 3000 6000 0112 3456 7890 189']);
	}

	#[Post('/logout')]
	public function logout(): void
	{
		// The encrypted history of the account pages becomes unreadable.
		Inertia::clearHistory();
		Inertia::flash('success', 'Logged out.');
		Inertia::redirect('/');
	}
}
