<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Karewan\KnRouteInertia\Ssr\HttpSsrGateway;
use Karewan\KnRouteInertia\Ssr\SsrException;

// The PHP built-in server stands in for the Node SSR server.
$url = startServer(__DIR__ . '/../Fixtures/ssr_server.php');
$port = (int) parse_url($url, PHP_URL_PORT);

$gateway = new HttpSsrGateway("http://127.0.0.1:{$port}/render", 0.5);

/** @return array<string,mixed> */
function page(string $component, array $props = []): array
{
	return ['component' => $component, 'props' => $props + ['errors' => new stdClass()], 'url' => '/a/b?c=d', 'version' => 'v1'];
}

test('The SSR server renders the head and the body', function () use ($gateway): void {
	$response = $gateway->render(page('Users/Index', ['name' => 'Émile <b>']));
	assertSame("<title>Users/Index</title>\n<meta name=\"description\" content=\"SSR\">", $response->head, 'head');
	assertContains('<div data-server-rendered="true" id="app">Émile &lt;b&gt;</div>', $response->body, 'body');
});

test('The page object is posted as compact JSON', function () use ($gateway): void {
	$body = $gateway->render(page('Echo', ['name' => 'Émile/é']))->body;
	assertSame('{"component":"Echo","props":{"name":"Émile/é","errors":{}},"url":"/a/b?c=d","version":"v1"}', $body, 'request body');
});

test('A classified SSR error keeps its details', function () use ($gateway): void {
	$e = assertThrows(SsrException::class, fn() => $gateway->render(page('Crash')), 'render error', 'window is not defined');
	assertSame('browser-api', $e->type, 'type');
	assertSame('Crash', $e->component, 'component');
	assertSame('/crash', $e->url, 'url');
	assertSame('The global window object', $e->browserApi, 'browser API');
	assertSame('Move the code to a lifecycle hook.', $e->hint, 'hint');
	assertSame('resources/js/Pages/Crash.vue:14:3', $e->sourceLocation, 'source location');
});

test('An invalid answer is an SSR error', function () use ($gateway): void {
	$e = assertThrows(SsrException::class, fn() => $gateway->render(page('Garbage')), 'invalid body', 'invalid body');
	assertSame('response', $e->type, 'type');
	assertSame('Garbage', $e->component, 'component');

	$e = assertThrows(SsrException::class, fn() => (new HttpSsrGateway(str_replace('/render', '/missing', "http://127.0.0.1:{$GLOBALS['port']}/render")))->render(page('A')), 'unknown endpoint');
	assertSame('render', $e->type, 'type of an error without payload');
});

test('An unreachable or slow SSR server is a connection error', function () use ($gateway): void {
	$unreachable = new HttpSsrGateway('http://127.0.0.1:' . freePort() . '/render', 0.5);
	$e = assertThrows(SsrException::class, fn() => $unreachable->render(page('A', ['x' => 1])), 'unreachable', 'SSR server unreachable');
	assertSame('connection', $e->type, 'type');
	assertSame('A', $e->component, 'component');
	assertSame(false, $unreachable->isHealthy(), 'unhealthy');

	$start = microtime(true);
	$e = assertThrows(SsrException::class, fn() => $gateway->render(page('Slow')), 'timeout', 'timed out');
	assertSame('connection', $e->type, 'type');
	assertTrue(microtime(true) - $start < 0.75, 'the timeout is honored');
});

test('The health endpoint', function () use ($gateway): void {
	assertSame(true, $gateway->isHealthy(), 'healthy');
});

test('Errors of the application handler never escape the gateway', function () use ($gateway): void {
	set_error_handler(static fn(int $severity, string $message): never => throw new ErrorException($message, 0, $severity));
	try {
		$unreachable = new HttpSsrGateway('http://127.0.0.1:' . freePort() . '/render', 0.5);
		assertThrows(SsrException::class, fn() => $unreachable->render(page('A')), 'warning turned into the SSR error');
	} finally {
		restore_error_handler();
	}
});

finish();
