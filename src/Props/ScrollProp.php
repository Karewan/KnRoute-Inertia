<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

use Closure;
use Karewan\KnRouteInertia\Props\Concerns\DefersProps;
use Karewan\KnRouteInertia\Props\Concerns\MergesProps;
use LogicException;

/**
 * Paginated data of an infinite scroll: the items of the wrapper key are appended, or prepended when the
 * client scrolls backwards, and the page object carries the pagination cursor.
 */
final class ScrollProp implements Prop, Deferrable, Mergeable
{
	use DefersProps, MergesProps;

	private mixed $resolved = null;

	private bool $isResolved = false;

	/**
	 * @param mixed $value the paginated data, or a Closure producing it
	 * @param ScrollMetadata|Closure(mixed):ScrollMetadata $metadata the cursor, or a Closure deriving it from the data
	 * @param string $wrapper key of the items inside the paginated data
	 */
	public function __construct(
		private readonly mixed $value,
		private readonly ScrollMetadata|Closure $metadata,
		private readonly string $wrapper = 'data',
	) {
		$this->merge = true;
		$this->appendsAtPaths = [$wrapper];
	}

	/** Merge at the wrapper key in the direction the client scrolls. */
	public function configureMergeIntent(bool $prepend): void
	{
		$this->appendsAtPaths = $prepend ? [] : [$this->wrapper];
		$this->prependsAtPaths = $prepend ? [$this->wrapper] : [];
	}

	/** @return array{pageName:string,previousPage:int|string|null,nextPage:int|string|null,currentPage:int|string|null} */
	public function metadata(): array
	{
		if ($this->metadata instanceof ScrollMetadata) return $this->metadata->toArray();

		$metadata = ($this->metadata)($this->resolve());
		if (!$metadata instanceof ScrollMetadata) {
			throw new LogicException(sprintf('The scroll metadata callback must return a %s instance', ScrollMetadata::class));
		}
		return $metadata->toArray();
	}

	public function resolve(): mixed
	{
		// Resolved once: the metadata callback receives the same data as the page.
		if (!$this->isResolved) {
			$this->resolved = $this->value instanceof Closure ? ($this->value)() : $this->value;
			$this->isResolved = true;
		}
		return $this->resolved;
	}
}
