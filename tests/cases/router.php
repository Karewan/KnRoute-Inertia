<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Karewan\KnRoute\Router;
use Karewan\KnRouteInertia\ErrorHandler;
use Karewan\KnRouteInertia\Inertia;
use Karewan\KnRouteInertia\InertiaMiddleware;
use Karewan\KnRouteInertia\RootView;
use Tests\Fixtures\ArraySessionStore;
use Tests\Fixtures\Controllers\PagesController;
use Tests\Fixtures\FakeSsrGateway;
use Tests\Response;

$session = new ArraySessionStore();
$ssr = new FakeSsrGateway();

Inertia::configure(
	rootView: static fn(RootView $view): string => "<!DOCTYPE html><html><head>{$view->head()}</head><body>{$view->app()}</body></html>",
	version: VERSION,
	share: static fn(): array => ['appName' => 'Fixtures'],
	session: $session,
	ssr: $ssr,
);

// One router serves every request, as in a long-running worker.
$router = new Router();
$router->addGlobalMiddleware(new InertiaMiddleware());
$router->setDefaultErrorHandler(new ErrorHandler('Error'));
$router->registerRoutesFromControllers(__DIR__ . '/../Fixtures/Controllers', null);

/**
 * Run a request through the router.
 * @param array<string,string> $headers
 * @param array<string,string> $post
 */
function dispatch(Router $router, string $method, string $uri, array $headers = [], array $post = []): string
{
	request($method, $uri, $headers);
	$_POST = $post;
	return output(fn() => $router->run());
}

/**
 * Run a request as a worker does: nothing but the middleware resets the adapter between requests.
 * @param array<string,string> $headers
 */
function serve(Router $router, string $method, string $uri, array $headers = [], ?Closure $bootstrap = null): string
{
	$_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'SERVER_PROTOCOL' => 'HTTP/1.1', 'HTTP_HOST' => 'app.test'];
	foreach ($headers as $name => $value) $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
	Response::reset();
	if ($bootstrap !== null) $bootstrap();
	return output(fn() => $router->run());
}

test('A full page load renders the root view', function () use ($router, $ssr): void {
	$ssr->calls = 0;
	$html = dispatch($router, 'GET', '/');
	assertSame(200, Response::$status, 'status');
	assertContains('<title>Home</title>', $html, 'SSR head');
	assertSame(1, $ssr->calls, 'rendered on the server');
	assertSame(['X-Inertia'], Response::all('Vary'), 'Vary');
});

test('An Inertia visit gets the page object', function () use ($router): void {
	$page = json(dispatch($router, 'GET', '/', inertia()));
	assertSame('Home', $page['component'], 'component');
	assertSame(['appName' => 'Fixtures', 'title' => 'Home', 'errors' => []], $page['props'], 'props');
	assertSame(['default' => ['stats']], $page['deferredProps'], 'deferred props');
	assertSame(['errors', 'appName'], $page['sharedProps'], 'shared props');
});

test('The deferred props are loaded by a partial reload', function () use ($router): void {
	$page = json(dispatch($router, 'GET', '/', inertia(['X-Inertia-Partial-Component' => 'Home', 'X-Inertia-Partial-Data' => 'stats'])));
	assertSame(['stats' => ['visits' => 3], 'errors' => []], $page['props'], 'props');
});

test('Outdated assets make the client reload the page, without running the action', function () use ($router): void {
	PagesController::$executions = 0;
	$body = dispatch($router, 'GET', '/?tab=2', inertia(['X-Inertia-Version' => 'v0']));

	assertSame(409, Response::$status, 'status');
	assertSame('/?tab=2', Response::get('X-Inertia-Location'), 'location');
	assertSame(VERSION, Response::get('X-Inertia-Version'), 'current version');
	assertSame('', $body, 'no body');
	assertSame(0, PagesController::$executions, 'the action did not run');

	dispatch($router, 'GET', '/', ['X-Inertia-Version' => 'v0']);
	assertSame(200, Response::$status, 'a full page load is never refused');
});

test('A form sent with errors goes back to the form with its errors', function () use ($router, $session): void {
	$body = dispatch($router, 'POST', '/users', inertia(['Referer' => 'https://app.test/users/create']), ['name' => '']);
	assertSame(302, Response::$status, 'redirect');
	assertSame('/users/create', Response::get('Location'), 'back to the form');
	assertSame('', $body, 'no body');
	assertSame(['errors' => ['default' => ['name' => 'The name is required.']]], $session->data[Inertia::SESSION_KEY], 'errors kept');

	$page = json(dispatch($router, 'GET', '/users/create', inertia(['X-Inertia-Error-Bag' => 'createUser'])));
	assertSame(['createUser' => ['name' => 'The name is required.']], $page['props']['errors'], 'errors of the form');
});

test('A PUT sent with errors goes back with a 303 and its named bag', function () use ($router, $session): void {
	dispatch($router, 'PUT', '/users/5', inertia(['Referer' => 'https://app.test/users/5/edit']));
	assertSame(303, Response::$status, 'status');
	assertSame('/users/5/edit', Response::get('Location'), 'location');
	assertSame(['updateUser' => ['name' => 'Required.']], $session->data[Inertia::SESSION_KEY]['errors'], 'named bag');
	$session->data = [];
});

test('A plain redirect keeps the flash data, and turns into a 303 after a PUT', function () use ($router, $session): void {
	dispatch($router, 'POST', '/users', inertia(), ['name' => 'Ada']);
	assertSame(302, Response::$status, 'POST redirect');
	assertSame(['flash' => ['message' => 'User Ada created']], $session->data[Inertia::SESSION_KEY], 'flash kept');

	$page = json(dispatch($router, 'GET', '/users/create', inertia()));
	assertSame(['message' => 'User Ada created'], $page['flash'], 'flash sent');

	dispatch($router, 'PUT', '/users/5', inertia(), ['name' => 'Ada']);
	assertSame(303, Response::$status, 'Inertia PUT');
	assertSame('/users/5', Response::get('Location'), 'location');

	dispatch($router, 'PUT', '/users/5', [], ['name' => 'Ada']);
	assertSame(302, Response::$status, 'other clients are left alone');
});

test('A plain redirect to a URL with a fragment becomes a 409 for Inertia visits', function () use ($router): void {
	dispatch($router, 'POST', '/comments', inertia());
	assertSame(409, Response::$status, 'status');
	assertSame('/posts/1#comment-3', Response::get('X-Inertia-Redirect'), 'X-Inertia-Redirect');
	assertSame(null, Response::get('Location'), 'no Location');

	dispatch($router, 'POST', '/comments');
	assertSame(302, Response::$status, 'a browser follows the redirect itself');
});

test('HTTP errors render the error page for Inertia visits and page loads', function () use ($router): void {
	$page = json(dispatch($router, 'GET', '/missing', inertia()));
	assertSame(404, Response::$status, 'status');
	assertSame('Error', $page['component'], 'error component');
	assertSame(['appName' => 'Fixtures', 'status' => 404, 'title' => 'Not Found', 'detail' => 'The requested resource was not found.', 'errors' => []], $page['props'], 'props');

	$html = dispatch($router, 'GET', '/forbidden', ['Accept' => 'text/html,application/xhtml+xml']);
	assertSame(403, Response::$status, 'page load status');
	assertContains('<title>Error</title>', $html, 'error page');

	$page = json(dispatch($router, 'DELETE', '/', inertia()));
	assertSame(405, Response::$status, 'method not allowed');
	assertSame('GET, HEAD, OPTIONS', Response::get('Allow'), 'Allow header of the router');
	assertSame(405, $page['props']['status'], 'error page');
});

test('HTTP errors of other clients use the fallback', function () use ($router): void {
	$error = json(dispatch($router, 'GET', '/forbidden', ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']));
	assertSame(403, Response::$status, 'status');
	assertSame(['status' => 403, 'path' => '/forbidden', 'title' => 'Forbidden', 'detail' => 'Admins only.'], $error, 'JSON error');

	dispatch($router, 'GET', '/missing', ['Accept' => '*/*']);
	assertSame('application/json; charset=utf-8', Response::get('Content-type'), 'no page for a client without HTML');
});

test('Validation errors of a JSON client are a 422 with the errors', function () use ($router, $session): void {
	$error = json(dispatch($router, 'POST', '/users', ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'], ['name' => '']));
	assertSame(422, Response::$status, 'status');
	assertSame(['name' => 'The name is required.'], $error['errors'], 'errors');
	assertSame([], $session->data, 'nothing kept');
});

test('Precognition requests validate without running the action', function () use ($router, $session): void {
	$body = dispatch($router, 'POST', '/users', ['Precognition' => 'true', 'X-Requested-With' => 'XMLHttpRequest'], ['name' => '']);
	assertSame(422, Response::$status, 'invalid');
	assertSame(['name' => 'The name is required.'], json($body)['errors'], 'errors');

	$body = dispatch($router, 'POST', '/users', ['Precognition' => 'true', 'X-Requested-With' => 'XMLHttpRequest'], ['name' => 'Ada']);
	assertSame(204, Response::$status, 'valid');
	assertSame('', $body, 'no body');
	assertSame([], $session->data, 'the action did not run');

	$body = dispatch($router, 'PUT', '/users/5', ['Precognition' => 'true', 'Precognition-Validate-Only' => 'name']);
	assertSame(422, Response::$status, 'a ValidationException is answered too');
	assertSame(['name' => 'Required.'], json($body)['errors'], 'errors');
});

test('Route middlewares encrypt the history and disable SSR', function () use ($router, $ssr): void {
	assertSame(true, json(dispatch($router, 'GET', '/account', inertia()))['encryptHistory'] ?? null, 'class middleware');
	assertSame(null, json(dispatch($router, 'GET', '/account/public', inertia()))['encryptHistory'] ?? null, 'method middleware overrides');

	$ssr->calls = 0;
	dispatch($router, 'GET', '/account/public');
	assertSame(0, $ssr->calls, 'SSR disabled');
});

test('Nothing leaks from one request to the next', function () use ($router): void {
	$page = json(dispatch($router, 'GET', '/share', inertia()));
	assertSame('from a previous request', $page['props']['leak'], 'shared in the first request');
	assertSame(true, $page['encryptHistory'], 'encrypted in the first request');

	$page = json(dispatch($router, 'GET', '/', inertia()));
	assertTrue(!isset($page['props']['leak']), 'no shared prop from the previous request');
	assertTrue(!isset($page['encryptHistory']), 'no history flag from the previous request');
});

test('Props shared before the router runs reach the page, then are forgotten', function () use ($router): void {
	$page = json(serve($router, 'GET', '/', inertia(), static fn() => Inertia::share('tenant', 'acme')));
	assertSame('acme', $page['props']['tenant'], 'shared before run()');

	$page = json(serve($router, 'GET', '/', inertia()));
	assertTrue(!isset($page['props']['tenant']), 'forgotten by the next request');
});

test('A request stopped by a version change leaves no state behind', function () use ($router): void {
	serve($router, 'GET', '/', inertia(['X-Inertia-Version' => 'v0']), static fn() => Inertia::share('stale', true));
	assertSame(409, Response::$status, 'version change');

	$html = serve($router, 'GET', '/');
	assertSame('text/html; charset=utf-8', Response::get('Content-Type'), 'the next request is not taken for an Inertia visit');
	assertNotContains('stale', $html, 'no shared prop of the stopped request');
});

finish();
