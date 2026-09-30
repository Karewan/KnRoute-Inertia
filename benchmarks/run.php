<?php

declare(strict_types=1);

/*
 * Cost of building and encoding the page object, compared with json_encode() of the same plain data.
 * Run with: composer bench
 */

namespace Karewan\KnRouteInertia {
	// The CLI keeps no headers: the benchmark only measures the adapter.
	function header(string $header, bool $replace = true, int $response_code = 0): void {}
}

namespace {
	use Karewan\KnRouteInertia\Inertia;
	use Karewan\KnRouteInertia\Props\ScrollMetadata;

	require __DIR__ . '/../vendor/autoload.php';

	Inertia::configure(
		rootView: static fn($view): string => "<!DOCTYPE html><html><head>{$view->head()}</head><body>{$view->app()}</body></html>",
		version: 'b3d5e9f30',
		share: static fn(): array => [
			'auth' => ['user' => ['id' => 7, 'name' => 'Jonathan', 'email' => 'jonathan@example.com', 'roles' => ['admin']]],
			'locale' => 'en',
		],
		session: false,
	);

	/** @return list<array<string,mixed>> */
	function rows(int $count): array
	{
		$rows = [];
		for ($i = 0; $i < $count; $i++) {
			$rows[] = [
				'id' => $i,
				'name' => "User {$i}",
				'email' => "user{$i}@example.com",
				'active' => $i % 3 !== 0,
				'score' => $i * 1.5,
				'created_at' => '2026-09-30 08:00:00',
				'team' => ['id' => $i % 10, 'name' => 'Team ' . ($i % 10)],
				'tags' => ['a', 'b', 'c'],
				'notes' => null,
				'role' => 'member',
			];
		}
		return $rows;
	}

	function server(array $headers = []): void
	{
		$_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users?page=2', 'HTTP_HOST' => 'app.test'];
		foreach ($headers as $name => $value) $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
		Inertia::resetRequest();
	}

	/** @return float microseconds per iteration */
	function measure(int $iterations, Closure $callback): float
	{
		for ($i = 0; $i < min(50, $iterations); $i++) $callback();
		$start = hrtime(true);
		for ($i = 0; $i < $iterations; $i++) $callback();
		return (hrtime(true) - $start) / $iterations / 1000;
	}

	function report(string $name, float $adapter, float $baseline): void
	{
		printf("%-52s %9.1f µs   json_encode() alone %8.1f µs   adapter work %+8.1f µs\n", $name, $adapter, $baseline, $adapter - $baseline);
	}

	$inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => 'b3d5e9f30'];
	$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

	echo 'PHP ' . PHP_VERSION . ', OPcache ' . (function_exists('opcache_get_status') && opcache_get_status() !== false ? 'on' : 'off') . "\n\n";

	// A typical page: a table page, a few lazy and deferred props.
	$typical = static fn(): array => [
		'users' => rows(50),
		'filters' => ['search' => '', 'sort' => 'name', 'page' => 2],
		'companies' => static fn(): array => [['id' => 1, 'name' => 'ACME'], ['id' => 2, 'name' => 'Globex']],
		'stats' => Inertia::defer(static fn(): array => ['total' => 1234]),
		'roles' => Inertia::optional(static fn(): array => ['admin', 'member']),
		'can' => ['create' => true, 'export' => false],
	];
	$typicalPlain = ['users' => rows(50), 'filters' => ['search' => '', 'sort' => 'name', 'page' => 2], 'companies' => [['id' => 1, 'name' => 'ACME'], ['id' => 2, 'name' => 'Globex']], 'can' => ['create' => true, 'export' => false]];

	server($inertia);
	$props = $typical();
	report('Typical page (50 rows), Inertia visit', measure(5000, static function () use ($props): void {
		Inertia::resetRequest();
		ob_start();
		Inertia::render('Users/Index', $props);
		ob_end_clean();
	}), measure(5000, static fn() => json_encode(['component' => 'Users/Index', 'props' => $typicalPlain], $flags)));

	server();
	report('Typical page (50 rows), full page load', measure(5000, static function () use ($props): void {
		Inertia::resetRequest();
		ob_start();
		Inertia::render('Users/Index', $props);
		ob_end_clean();
	}), measure(5000, static fn() => json_encode(['component' => 'Users/Index', 'props' => $typicalPlain], $flags | JSON_HEX_TAG)));

	// Large data: 10 000 rows of arrays, the traversal is the only extra work.
	$large = ['users' => rows(10000), 'total' => 10000];
	server($inertia);
	report('10 000 rows, Inertia visit', measure(40, static function () use ($large): void {
		Inertia::resetRequest();
		ob_start();
		Inertia::render('Users/Index', $large);
		ob_end_clean();
	}), measure(40, static fn() => json_encode(['component' => 'Users/Index', 'props' => $large], $flags)));

	// The same rows produced by a Closure.
	report('10 000 rows from a Closure, Inertia visit', measure(40, static function () use ($large): void {
		Inertia::resetRequest();
		ob_start();
		Inertia::render('Users/Index', ['users' => static fn(): array => $large['users'], 'total' => 10000]);
		ob_end_clean();
	}), measure(40, static fn() => json_encode(['component' => 'Users/Index', 'props' => $large], $flags)));

	// A partial reload selecting one prop among heavy ones.
	server($inertia + ['X-Inertia-Partial-Component' => 'Users/Index', 'X-Inertia-Partial-Data' => 'stats']);
	$heavy = ['users' => static fn(): array => rows(10000), 'stats' => Inertia::defer(static fn(): array => ['total' => 1234]), 'total' => 10000];
	report('Partial reload of 1 prop next to 10 000 lazy rows', measure(5000, static function () use ($heavy): void {
		Inertia::resetRequest();
		ob_start();
		Inertia::render('Users/Index', $heavy);
		ob_end_clean();
	}), measure(5000, static fn() => json_encode(['component' => 'Users/Index', 'props' => ['stats' => ['total' => 1234]]], $flags)));

	// Infinite scroll page, the props are built by every request.
	server($inertia);
	$posts = rows(20);
	report('Infinite scroll page (20 rows)', measure(5000, static function () use ($posts): void {
		Inertia::resetRequest();
		ob_start();
		Inertia::render('Posts/Index', ['posts' => Inertia::scroll(static fn(): array => ['data' => $posts], ScrollMetadata::forPage(2, true))]);
		ob_end_clean();
	}), measure(5000, static fn() => json_encode(['component' => 'Posts/Index', 'props' => ['posts' => ['data' => $posts]]], $flags)));
}
