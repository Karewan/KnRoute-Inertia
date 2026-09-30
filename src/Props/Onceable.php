<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Props;

/** A prop resolved once, then remembered by the client across the pages that include it. */
interface Onceable
{
	public function shouldResolveOnce(): bool;

	public function shouldBeRefreshed(): bool;

	/** Key shared by the pages that reuse the value, null to use the prop path. */
	public function getKey(): ?string;

	/** Expiration timestamp in milliseconds, null when the value never expires. */
	public function expiresAt(): ?int;
}
