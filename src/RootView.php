<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

use Closure;
use Karewan\KnRouteInertia\Ssr\SsrException;
use Karewan\KnRouteInertia\Ssr\SsrGateway;
use Karewan\KnRouteInertia\Ssr\SsrResponse;

/**
 * The root template of a full page load: it prints head() in the document head and app() in the body.
 *
 * With SSR, both print the markup rendered by the SSR server, requested once on first use. Without SSR,
 * or when the rendering failed, head() prints its fallback and app() the page object and the root element.
 */
final class RootView
{
	private SsrResponse|false|null $ssrResponse = null;

	/**
	 * @param array<string,mixed> $page the page object
	 * @param array<string,mixed> $data view data, only available to the template
	 * @param Closure(\Throwable):void $reporter
	 * @internal built by Inertia::render()
	 */
	public function __construct(
		public readonly array $page,
		public readonly array $data,
		private readonly int $jsonFlags,
		private readonly ?SsrGateway $ssr,
		private readonly Closure $reporter,
	) {}

	/**
	 * Head elements rendered by the SSR server, or the fallback when the page is rendered on the client.
	 * @param string $fallback trusted HTML, e.g. the default title
	 */
	public function head(string $fallback = ''): string
	{
		$ssr = $this->ssr();
		return $ssr === null ? $fallback : $ssr->head;
	}

	/**
	 * The page object script and the root element the client mounts the application on.
	 * @param string $id root element id, as configured on the client
	 */
	public function app(string $id = 'app'): string
	{
		$ssr = $this->ssr();
		if ($ssr !== null) return $ssr->body;

		$id = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return "<script data-page=\"{$id}\" type=\"application/json\">{$this->json()}</script><div id=\"{$id}\"></div>";
	}

	/**
	 * The page object as JSON, safe inside a script element: "<", ">" and "/" are escaped so no prop value
	 * can close the element or switch the parser state. HTML entities must never be used there.
	 */
	public function json(): string
	{
		return json_encode(
			$this->page,
			(($this->jsonFlags | JSON_UNESCAPED_UNICODE) & ~JSON_UNESCAPED_SLASHES) | JSON_HEX_TAG | JSON_THROW_ON_ERROR,
		);
	}

	/** Whether the page was rendered by the SSR server. */
	public function isServerRendered(): bool
	{
		return $this->ssr() !== null;
	}

	private function ssr(): ?SsrResponse
	{
		if ($this->ssrResponse === null) {
			$this->ssrResponse = false;
			if ($this->ssr !== null) {
				try {
					$this->ssrResponse = $this->ssr->render($this->page) ?? false;
				} catch (SsrException $e) {
					// A broken SSR server must never take the site down: the client renders the page.
					($this->reporter)($e);
				}
			}
		}

		return $this->ssrResponse ?: null;
	}
}
