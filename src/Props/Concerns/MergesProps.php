<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props\Concerns;

/** Implements Mergeable: append or prepend at the root or at nested paths, deep merge, item matching. */
trait MergesProps
{
	protected bool $merge = false;

	protected bool $deepMerge = false;

	protected bool $append = true;

	/** @var list<string> */
	protected array $appendsAtPaths = [];

	/** @var list<string> */
	protected array $prependsAtPaths = [];

	/** @var list<string> */
	protected array $matchOn = [];

	public function merge(): static
	{
		$this->merge = true;
		return $this;
	}

	public function deepMerge(): static
	{
		$this->deepMerge = true;
		$this->merge = true;
		return $this;
	}

	/**
	 * Fields identifying the items, so the client updates an item it already holds instead of adding it again.
	 * @param string|list<string> $matchOn paths relative to the prop, e.g. "id" or "data.id"
	 */
	public function matchOn(string|array $matchOn): static
	{
		$this->matchOn = is_string($matchOn) ? [$matchOn] : array_values($matchOn);
		$this->merge = true;
		return $this;
	}

	/**
	 * Append the incoming items: at the root (true), in the nested array at $path, or in several nested
	 * arrays given as a list of paths or as a map of path => matching field.
	 * @param bool|string|array<int|string,string> $path
	 */
	public function append(bool|string|array $path = true, ?string $matchOn = null): static
	{
		$this->merge = true;

		if (is_bool($path)) {
			$this->append = $path;
		} elseif (is_string($path)) {
			$this->appendsAtPaths[] = $path;
			if ($matchOn !== null) $this->matchOn[] = "{$path}.{$matchOn}";
		} else {
			foreach ($path as $key => $value) {
				is_int($key) ? $this->append($value) : $this->append($key, $value);
			}
		}

		return $this;
	}

	/**
	 * Prepend the incoming items, with the same forms as append().
	 * @param bool|string|array<int|string,string> $path
	 */
	public function prepend(bool|string|array $path = true, ?string $matchOn = null): static
	{
		$this->merge = true;

		if (is_bool($path)) {
			$this->append = !$path;
		} elseif (is_string($path)) {
			$this->prependsAtPaths[] = $path;
			if ($matchOn !== null) $this->matchOn[] = "{$path}.{$matchOn}";
		} else {
			foreach ($path as $key => $value) {
				is_int($key) ? $this->prepend($value) : $this->prepend($key, $value);
			}
		}

		return $this;
	}

	public function shouldMerge(): bool
	{
		return $this->merge;
	}

	public function shouldDeepMerge(): bool
	{
		return $this->deepMerge;
	}

	public function appendsAtRoot(): bool
	{
		return $this->append && !$this->appendsAtPaths && !$this->prependsAtPaths;
	}

	public function prependsAtRoot(): bool
	{
		return !$this->append && !$this->appendsAtPaths && !$this->prependsAtPaths;
	}

	/** @return list<string> */
	public function appendsAtPaths(): array
	{
		return $this->appendsAtPaths;
	}

	/** @return list<string> */
	public function prependsAtPaths(): array
	{
		return $this->prependsAtPaths;
	}

	/** @return list<string> */
	public function matchesOn(): array
	{
		return $this->matchOn;
	}
}
