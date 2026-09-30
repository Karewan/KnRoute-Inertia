<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Ssr;

use JsonException;

/**
 * Posts the page object to the Inertia SSR server: the Node server started from the SSR bundle in
 * production, or the endpoint of the @inertiajs/vite plugin in development.
 */
final class HttpSsrGateway implements SsrGateway
{
	private readonly string $healthUrl;

	/**
	 * @param string $url render endpoint, e.g. http://127.0.0.1:13714/render, or http://localhost:5173/__inertia_ssr
	 * with the Vite development server
	 * @param float $timeout seconds to wait for the rendered page before rendering it on the client
	 * @param string|null $healthUrl health endpoint, derived from a /render URL by default
	 */
	public function __construct(
		private readonly string $url = 'http://127.0.0.1:13714/render',
		private readonly float $timeout = 2.0,
		?string $healthUrl = null,
	) {
		$this->healthUrl = $healthUrl ?? (str_ends_with($url, '/render') ? substr($url, 0, -7) . '/health' : $url);
	}

	public function render(array $page): SsrResponse
	{
		$component = \is_string($page['component'] ?? null) ? $page['component'] : null;
		$url = \is_string($page['url'] ?? null) ? $page['url'] : null;

		[$status, $body] = $this->request(
			'POST',
			$this->url,
			json_encode($page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
			$component,
			$url,
		);

		try {
			$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new SsrException("SSR server answered {$status} with an invalid body", 'response', $component, $url, previous: $e);
		}

		if ($status < 200 || $status > 299) {
			throw SsrException::fromPayload(\is_array($data) ? $data : [], $component, $url);
		}

		if (!\is_array($data) || !\is_string($data['body'] ?? null)) {
			throw new SsrException('SSR server answered without a rendered body', 'response', $component, $url);
		}

		$head = $data['head'] ?? [];
		return new SsrResponse(\is_array($head) ? implode("\n", array_filter($head, 'is_string')) : '', $data['body']);
	}

	/** Whether the SSR server answers its health endpoint. */
	public function isHealthy(): bool
	{
		try {
			[$status] = $this->request('GET', $this->healthUrl, null, null, null);
			return $status >= 200 && $status <= 299;
		} catch (SsrException) {
			return false;
		}
	}

	/** @return array{int,string} status and body */
	private function request(string $method, string $url, ?string $content, ?string $component, ?string $pageUrl): array
	{
		$options = [
			'method' => $method,
			'header' => "Accept: application/json\r\n" . ($content === null ? '' : "Content-Type: application/json\r\n"),
			'timeout' => $this->timeout,
			'ignore_errors' => true,
			'follow_location' => 0,
		];
		if ($content !== null) $options['content'] = $content;

		// The stream warnings are turned into the exception, whatever the error handler of the application.
		$error = null;
		set_error_handler(static function (int $severity, string $message) use (&$error): bool {
			$error = $message;
			return true;
		});
		$start = hrtime(true);
		try {
			$stream = fopen($url, 'rb', false, stream_context_create(['http' => $options]));
			$body = $stream === false ? false : stream_get_contents($stream);
			$metadata = $stream === false ? [] : stream_get_meta_data($stream);
		} finally {
			restore_error_handler();
			if (isset($stream) && $stream !== false) fclose($stream);
		}

		if ($stream === false || $body === false || ($metadata['timed_out'] ?? false)) {
			// A server that does not answer in time only makes fopen() report a failed request.
			$timedOut = ($metadata['timed_out'] ?? false) || (hrtime(true) - $start) / 1e9 >= $this->timeout;
			throw new SsrException(
				'SSR server unreachable: ' . ($timedOut ? "timed out after {$this->timeout}s" : ($error ?? 'no response')),
				'connection',
				$component,
				$pageUrl,
			);
		}

		$status = 0;
		foreach ($metadata['wrapper_data'] ?? [] as $header) {
			if (\is_string($header) && preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) $status = (int) $match[1];
		}

		return [$status, $body];
	}
}
