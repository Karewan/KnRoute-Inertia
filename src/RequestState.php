<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

/**
 * State of the current request, replaced at the start of every request.
 * @internal
 */
final class RequestState
{
	public ?bool $inertia = null;

	public ?string $version = null;

	/** @var array<array-key,mixed>|null shared props of the configuration, resolved on first use */
	public ?array $configShared = null;

	/** @var array<array-key,mixed> */
	public array $shared = [];

	public ?bool $encryptHistory = null;

	public bool $ssrDisabled = false;

	// Pending data: sent with the page of this request, or kept in the session for the next page.

	/** @var array<string,mixed> */
	public array $flash = [];

	/** @var array<string,array<string,mixed>> errors by bag */
	public array $errors = [];

	public bool $clearHistory = false;

	public bool $preserveFragment = false;

	public function hasPending(): bool
	{
		return $this->flash || $this->errors || $this->clearHistory || $this->preserveFragment;
	}

	public function clearPending(): void
	{
		$this->flash = $this->errors = [];
		$this->clearHistory = $this->preserveFragment = false;
	}
}
