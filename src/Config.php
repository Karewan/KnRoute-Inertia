<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

use Closure;
use Karewan\KnRouteInertia\Ssr\SsrGateway;

/**
 * Configuration given to Inertia::configure().
 * @internal
 */
final readonly class Config
{
	/**
	 * @param string|Closure(RootView):string $rootView
	 * @param string|Closure():(string|null)|null $version
	 * @param Closure():array<array-key,mixed>|null $share
	 * @param Closure(\Throwable):void $reporter
	 */
	public function __construct(
		public string|Closure $rootView,
		public string|Closure|null $version,
		public ?Closure $share,
		public ?SessionStore $session,
		public ?SsrGateway $ssr,
		public bool $encryptHistory,
		public bool $exposeSharedPropKeys,
		public int $jsonFlags,
		public Closure $reporter,
	) {}
}
