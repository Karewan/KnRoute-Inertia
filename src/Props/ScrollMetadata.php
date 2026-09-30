<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

/** Pagination cursor of an infinite scroll prop: the pages the client can load next. */
final readonly class ScrollMetadata
{
	/**
	 * @param int|string|null $currentPage the page sent, a number or an opaque cursor
	 * @param int|string|null $previousPage null on the first page
	 * @param int|string|null $nextPage null on the last page
	 * @param string $pageName query string parameter carrying the page
	 */
	public function __construct(
		public int|string|null $currentPage,
		public int|string|null $previousPage = null,
		public int|string|null $nextPage = null,
		public string $pageName = 'page',
	) {}

	/** Metadata of a page number based pagination. */
	public static function forPage(int $page, bool $hasMore, string $pageName = 'page'): self
	{
		return new self($page, $page > 1 ? $page - 1 : null, $hasMore ? $page + 1 : null, $pageName);
	}

	/** @return array{pageName:string,previousPage:int|string|null,nextPage:int|string|null,currentPage:int|string|null} */
	public function toArray(): array
	{
		return [
			'pageName' => $this->pageName,
			'previousPage' => $this->previousPage,
			'nextPage' => $this->nextPage,
			'currentPage' => $this->currentPage,
		];
	}
}
