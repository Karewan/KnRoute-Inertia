<?php

declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use Karewan\KnRoute\Attributes\Get;
use Karewan\KnRoute\Attributes\Post;
use Karewan\KnRoute\Attributes\Put;
use Karewan\KnRoute\Exceptions\HttpException;
use Karewan\KnRoute\HttpUtils;
use Karewan\KnRouteInertia\Inertia;

final class PagesController
{
	public static int $executions = 0;

	#[Get('/')]
	public function home(): void
	{
		self::$executions++;
		Inertia::render('Home', ['title' => 'Home', 'stats' => Inertia::defer(fn(): array => ['visits' => 3])]);
	}

	#[Get('/users/create')]
	public function create(): void
	{
		Inertia::render('Users/Create');
	}

	#[Post('/users')]
	public function store(): void
	{
		$name = $_POST['name'] ?? '';
		Inertia::validate($name === '' ? ['name' => 'The name is required.'] : []);

		Inertia::flash('message', "User {$name} created");
		// A plain KnRoute redirect: the middleware keeps the flash data for the next page.
		HttpUtils::location('/users');
	}

	#[Put('/users/{id:uint}')]
	public function update(int $id): void
	{
		if (($_POST['name'] ?? '') === '') throw new \Karewan\KnRouteInertia\ValidationException(['name' => 'Required.'], 'updateUser');
		HttpUtils::location("/users/{$id}");
	}

	#[Post('/comments')]
	public function comment(): void
	{
		HttpUtils::location('/posts/1#comment-3');
	}

	#[Get('/forbidden')]
	public function forbidden(): void
	{
		throw new HttpException(403, 'Admins only.');
	}

	#[Get('/share')]
	public function share(): void
	{
		Inertia::share('leak', 'from a previous request');
		Inertia::encryptHistory();
		Inertia::render('Shared');
	}
}
