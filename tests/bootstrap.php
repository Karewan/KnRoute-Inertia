<?php

declare(strict_types=1);

namespace Tests {
	/**
	 * Records the response written by the code under test. The header functions of the adapter and of
	 * KnRoute are redirected here: the CLI SAPI keeps no response headers.
	 */
	final class Response
	{
		public static int $status = 200;

		/** @var list<array{string,string}> name and value */
		public static array $headers = [];

		public static bool $sent = false;

		public static function reset(): void
		{
			self::$status = 200;
			self::$headers = [];
			self::$sent = false;
		}

		public static function header(string $header, bool $replace, int $code): void
		{
			if (str_starts_with($header, 'HTTP/')) return;

			[$name, $value] = array_map('trim', explode(':', $header, 2) + [1 => '']);
			if ($replace) self::remove($name);
			self::$headers[] = [$name, $value];

			if ($code > 0) {
				self::$status = $code;
			} elseif (strcasecmp($name, 'Location') === 0 && self::$status !== 201 && (self::$status < 300 || self::$status > 399)) {
				// PHP turns a response into a 302 when a Location header is sent without a redirect status.
				self::$status = 302;
			}
		}

		public static function remove(?string $name): void
		{
			self::$headers = $name === null ? [] : array_values(array_filter(
				self::$headers,
				static fn(array $header): bool => strcasecmp($header[0], $name) !== 0,
			));
		}

		/** @return list<string> */
		public static function lines(): array
		{
			return array_map(static fn(array $header): string => "{$header[0]}: {$header[1]}", self::$headers);
		}

		public static function get(string $name): ?string
		{
			$value = null;
			foreach (self::$headers as [$headerName, $headerValue]) {
				if (strcasecmp($headerName, $name) === 0) $value = $headerValue;
			}
			return $value;
		}

		/** @return list<string> */
		public static function all(string $name): array
		{
			$values = [];
			foreach (self::$headers as [$headerName, $headerValue]) {
				if (strcasecmp($headerName, $name) === 0) $values[] = $headerValue;
			}
			return $values;
		}

		public static function statusCode(int $code): int|bool
		{
			$previous = self::$status;
			if ($code > 0) self::$status = $code;
			return $code > 0 ? $previous : self::$status;
		}
	}
}

namespace Karewan\KnRouteInertia {
	function header(string $header, bool $replace = true, int $response_code = 0): void
	{
		\Tests\Response::header($header, $replace, $response_code);
	}

	function header_remove(?string $name = null): void
	{
		\Tests\Response::remove($name);
	}

	/** @return list<string> */
	function headers_list(): array
	{
		return \Tests\Response::lines();
	}

	function headers_sent(): bool
	{
		return \Tests\Response::$sent;
	}

	function http_response_code(int $response_code = 0): int|bool
	{
		return \Tests\Response::statusCode($response_code);
	}
}

namespace Karewan\KnRoute {
	function header(string $header, bool $replace = true, int $response_code = 0): void
	{
		\Tests\Response::header($header, $replace, $response_code);
	}

	function http_response_code(int $response_code = 0): int|bool
	{
		return \Tests\Response::statusCode($response_code);
	}
}

namespace {
	use Karewan\KnRouteInertia\Inertia;
	use Tests\Response;

	const VERSION = 'v1';

	require __DIR__ . '/../vendor/autoload.php';

	spl_autoload_register(static function (string $class): void {
		if (!str_starts_with($class, 'Tests\\Fixtures\\')) return;
		$file = __DIR__ . '/Fixtures/' . str_replace('\\', '/', substr($class, 15)) . '.php';
		if (is_file($file)) require $file;
	});

	$GLOBALS['failures'] = 0;

	function test(string $name, Closure $test): void
	{
		request();
		$level = ob_get_level();
		try {
			$test();
			echo "PASS  {$name}\n";
		} catch (Throwable $e) {
			$GLOBALS['failures']++;
			while (ob_get_level() > $level) ob_end_clean();
			echo "FAIL  {$name}\n      " . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
		}
	}

	function finish(): never
	{
		exit($GLOBALS['failures'] > 0 ? 1 : 0);
	}

	/**
	 * Start a new request: server variables, empty response, fresh adapter state.
	 * @param array<string,string> $headers
	 */
	function request(string $method = 'GET', string $uri = '/', array $headers = []): void
	{
		$_SERVER = [
			'REQUEST_METHOD' => $method,
			'REQUEST_URI' => $uri,
			'SERVER_PROTOCOL' => 'HTTP/1.1',
			'HTTP_HOST' => 'app.test',
		];
		foreach ($headers as $name => $value) {
			$key = strtoupper(str_replace('-', '_', $name));
			$_SERVER[str_starts_with($key, 'CONTENT_') ? $key : "HTTP_{$key}"] = $value;
		}
		Response::reset();
		Inertia::resetRequest();
	}

	/**
	 * Headers of an Inertia visit.
	 * @param array<string,string> $extra
	 * @return array<string,string>
	 */
	function inertia(array $extra = []): array
	{
		return $extra + ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => VERSION];
	}

	/** A port nobody listens on. */
	function freePort(): int
	{
		$socket = stream_socket_server('tcp://127.0.0.1:0') ?: throw new RuntimeException('No free port');
		$port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
		fclose($socket);
		return $port;
	}

	/**
	 * Start the PHP built-in server with a router script, stopped at the end of the test file.
	 * @return string the base URL
	 */
	function startServer(string $script): string
	{
		$port = freePort();
		$server = proc_open(
			[PHP_BINARY, '-S', "127.0.0.1:{$port}", $script],
			[1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
			$pipes,
		);
		if (!is_resource($server)) throw new RuntimeException('Failed to start the built-in server');
		register_shutdown_function(static fn() => proc_terminate($server));

		for ($i = 0; $i < 100; $i++) {
			$socket = @stream_socket_client("tcp://127.0.0.1:{$port}", timeout: 0.05);
			if ($socket !== false) {
				fclose($socket);
				return "http://127.0.0.1:{$port}";
			}
			usleep(20000);
		}
		throw new RuntimeException('The built-in server did not start');
	}

	/** Run and return the output. */
	function output(Closure $callback): string
	{
		ob_start();
		try {
			$callback();
			return (string) ob_get_clean();
		} catch (Throwable $e) {
			ob_end_clean();
			throw $e;
		}
	}

	/** @return array<string,mixed> */
	function json(string $json): array
	{
		return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
	}

	function assertSame(mixed $expected, mixed $actual, string $label = 'value'): void
	{
		if ($actual !== $expected) {
			throw new RuntimeException(sprintf("%s:\n        expected %s\n        got      %s", $label, export($expected), export($actual)));
		}
	}

	function assertTrue(bool $condition, string $label): void
	{
		if (!$condition) throw new RuntimeException("{$label}: assertion failed");
	}

	function assertContains(string $needle, string $haystack, string $label): void
	{
		if (!str_contains($haystack, $needle)) throw new RuntimeException(sprintf('%s: %s not found in %s', $label, export($needle), export($haystack)));
	}

	function assertNotContains(string $needle, string $haystack, string $label): void
	{
		if (str_contains($haystack, $needle)) throw new RuntimeException(sprintf('%s: %s found in %s', $label, export($needle), export($haystack)));
	}

	/** @param class-string<Throwable> $class */
	function assertThrows(string $class, Closure $callback, string $label, ?string $message = null): Throwable
	{
		try {
			$callback();
		} catch (Throwable $e) {
			if (!$e instanceof $class) throw new RuntimeException("{$label}: expected {$class}, got " . get_class($e) . ": {$e->getMessage()}");
			if ($message !== null) assertContains($message, $e->getMessage(), "{$label} message");
			return $e;
		}
		throw new RuntimeException("{$label}: expected {$class}, nothing thrown");
	}

	function export(mixed $value): string
	{
		return is_string($value) ? var_export($value, true) : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
	}
}
