<?php

declare(strict_types=1);

namespace Samples\Controllers;

use Karewan\KnRoute\Attributes\Delete;
use Karewan\KnRoute\Attributes\Get;
use Karewan\KnRoute\Attributes\Post;
use Karewan\KnRoute\Exceptions\HttpException;
use Karewan\KnRouteInertia\Inertia;
use Samples\Users;

final class UsersController
{
	#[Get('/users')]
	public function index(): void
	{
		$search = is_string($_GET['search'] ?? null) ? $_GET['search'] : '';

		Inertia::render('Users/Index', [
			'filters' => ['search' => $search],
			'users' => fn(): array => Users::all($search),
			// Only sent when a partial reload asks for it.
			'roles' => Inertia::optional(fn(): array => ['admin', 'member']),
		]);
	}

	#[Get('/users/create')]
	public function create(): void
	{
		Inertia::render('Users/Create');
	}

	#[Post('/users')]
	public function store(): void
	{
		$input = Inertia::input();
		// Answers the Precognition requests, and sends the errors back to the form.
		Inertia::validate(Users::validate($input));

		$id = Users::add(trim($input['name']), trim($input['email']));
		Inertia::flash(['success' => "User {$input['name']} created.", 'highlight' => $id]);
		Inertia::redirect('/users');
	}

	#[Delete('/users/{id:uint}')]
	public function delete(int $id): void
	{
		if (!Users::delete($id)) throw new HttpException(404);

		Inertia::flash('success', 'User deleted.');
		Inertia::back('/users');
	}
}
