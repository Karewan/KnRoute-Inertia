<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

/** A prop merged by the client with the value it already holds, on partial reloads. */
interface Mergeable
{
	public function shouldMerge(): bool;

	public function shouldDeepMerge(): bool;

	public function appendsAtRoot(): bool;

	public function prependsAtRoot(): bool;

	/** @return list<string> */
	public function appendsAtPaths(): array;

	/** @return list<string> */
	public function prependsAtPaths(): array;

	/** @return list<string> */
	public function matchesOn(): array;
}
