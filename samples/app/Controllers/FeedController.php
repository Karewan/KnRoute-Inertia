<?php

declare(strict_types=1);

namespace Samples\Controllers;

use Karewan\KnRoute\Attributes\Get;
use Karewan\KnRouteInertia\Inertia;
use Karewan\KnRouteInertia\Props\ScrollMetadata;

final class FeedController
{
	private const int PAGES = 5;

	private const int PER_PAGE = 15;

	#[Get('/feed')]
	public function index(): void
	{
		$page = max(1, min(self::PAGES, (int) ($_GET['page'] ?? 1)));

		Inertia::render('Feed', [
			'posts' => Inertia::scroll(
				fn(): array => ['data' => array_map(
					static fn(int $id): array => ['id' => $id, 'title' => "Post #{$id}"],
					range(($page - 1) * self::PER_PAGE + 1, $page * self::PER_PAGE),
				)],
				ScrollMetadata::forPage($page, $page < self::PAGES),
			),
		]);
	}
}
