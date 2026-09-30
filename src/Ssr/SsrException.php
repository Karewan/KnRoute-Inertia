<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia\Ssr;

use RuntimeException;
use Throwable;

/** A page the SSR server did not render; the client renders it instead. */
final class SsrException extends RuntimeException
{
	/**
	 * @param string $type "render", "browser-api", "component-resolution" (classified by the SSR server),
	 * "connection" (server unreachable) or "response" (invalid answer)
	 */
	public function __construct(
		string $message,
		public readonly string $type = 'render',
		public readonly ?string $component = null,
		public readonly ?string $url = null,
		public readonly ?string $hint = null,
		public readonly ?string $browserApi = null,
		public readonly ?string $sourceLocation = null,
		public readonly ?string $stack = null,
		?Throwable $previous = null,
	) {
		parent::__construct($message, 0, $previous);
	}

	/**
	 * Build the exception from the error payload of the SSR server.
	 * @param array<string,mixed> $payload
	 */
	public static function fromPayload(array $payload, ?string $component = null, ?string $url = null): self
	{
		$string = static fn(string $key): ?string => isset($payload[$key]) && \is_string($payload[$key]) ? $payload[$key] : null;

		return new self(
			'SSR rendering failed: ' . ($string('error') ?? 'unknown error'),
			$string('type') ?? 'render',
			$string('component') ?? $component,
			$string('url') ?? $url,
			$string('hint'),
			$string('browserApi'),
			$string('sourceLocation'),
			$string('stack'),
		);
	}
}
