<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Karewan\KnRouteInertia\Ssr\SsrException;
use Karewan\KnRouteInertia\Ssr\SsrGateway;
use Karewan\KnRouteInertia\Ssr\SsrResponse;

final class FakeSsrGateway implements SsrGateway
{
	public int $calls = 0;

	public bool $fail = false;

	public function render(array $page): ?SsrResponse
	{
		$this->calls++;
		if ($this->fail) throw new SsrException('SSR rendering failed: window is not defined', 'browser-api', $page['component']);

		return new SsrResponse("<title>{$page['component']}</title>", '<div data-server-rendered="true" id="app">rendered</div>');
	}
}
