<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Karewan\KnRoute\Exceptions\StopRequestException;
use Karewan\KnRouteInertia\Inertia;
use Karewan\KnRouteInertia\Precognition;
use Karewan\KnRouteInertia\RootView;
use Karewan\KnRouteInertia\ValidationException;
use Tests\Fixtures\ArraySessionStore;
use Tests\Fixtures\FakeSsrGateway;
use Tests\Response;

$session = new ArraySessionStore();
$ssr = new FakeSsrGateway();
$reported = [];

function configure(ArraySessionStore $session, ?FakeSsrGateway $ssr = null, array &$reported = [], bool $encryptHistory = false): void
{
	Inertia::configure(
		rootView: __DIR__ . '/../Fixtures/root.php',
		version: VERSION,
		session: $session,
		ssr: $ssr,
		encryptHistory: $encryptHistory,
		reporter: static function (Throwable $e) use (&$reported): void {
			$reported[] = $e;
		},
	);
}

configure($session);

/** The page object embedded in an HTML document. */
function embeddedPage(string $html): array
{
	preg_match('#<script data-page="app" type="application/json">(.*?)</script>#s', $html, $match) || throw new RuntimeException('No page script');
	return json($match[1]);
}

test('An Inertia visit gets the page object as JSON', function (): void {
	request('GET', '/users', inertia());
	$body = output(fn() => Inertia::render('Users/Index', ['users' => [['id' => 1, 'name' => 'Émile']]]));

	assertSame(200, Response::$status, 'status');
	assertSame('application/json', Response::get('Content-Type'), 'content type');
	assertSame('true', Response::get('X-Inertia'), 'X-Inertia');
	assertSame(['X-Inertia'], Response::all('Vary'), 'Vary');
	assertContains('"name":"Émile"', $body, 'unescaped unicode');
	assertContains('"url":"/users"', $body, 'unescaped slashes');
	assertSame('Users/Index', json($body)['component'], 'component');
});

test('A full page load gets the root template with the page object', function (): void {
	request('GET', '/users?page=2');
	Response::header('Vary: Accept-Encoding', true, 0);
	$html = output(fn() => Inertia::render('Users/Index', ['users' => []], ['description' => 'All the users'], 202));

	assertSame(202, Response::$status, 'status');
	assertSame('text/html; charset=utf-8', Response::get('Content-Type'), 'content type');
	assertSame(['Accept-Encoding', 'X-Inertia'], Response::all('Vary'), 'Vary is added to the existing one');
	assertSame(null, Response::get('X-Inertia'), 'no X-Inertia header');
	assertContains('<title>Fallback</title>', $html, 'head fallback without SSR');
	assertContains('content="All the users"', $html, 'view data');
	assertContains('data-component="Users/Index"', $html, '$page variable');
	assertContains('<div id="app"></div>', $html, 'root element');
	assertSame('/users?page=2', embeddedPage($html)['url'], 'embedded page');
});

test('The embedded page object cannot close its script element', function (): void {
	$page = Inertia::page('Post', ['body' => '</script><script>alert(1)</script><!--<script>', 'amp' => 'a & b', 'unicode' => "é \u{2028}"]);
	$view = new RootView($page, [], 0, null, static function (): void {});
	$html = $view->app('app"><x');

	assertNotContains('</script><script>', $html, 'no closing tag from a prop');
	assertNotContains('<!--', $html, 'no comment opening from a prop');
	assertContains(substr(json_encode('</script>', JSON_HEX_TAG), 1, -1), $html, 'escaped tags and slash');
	assertContains('"amp":"a & b"', $html, 'no HTML entities in the JSON');
	assertContains('data-page="app&quot;&gt;&lt;x"', $html, 'escaped root id');
	preg_match('#type="application/json">(.*)</script>#', $html, $match);
	assertSame('</script><script>alert(1)</script><!--<script>', json($match[1])['props']['body'], 'the client decodes the original value');
});

test('A Closure can render the root view', function () use ($session): void {
	Inertia::configure(rootView: static fn(RootView $view): string => "<main>{$view->data['title']}|{$view->page['component']}</main>", session: $session);
	request();
	assertSame('<main>Hi|Home</main>', output(fn() => Inertia::render('Home', viewData: ['title' => 'Hi'])), 'document');
	configure($session);
});

test('The template output is dropped when the template fails', function () use ($session): void {
	Inertia::configure(rootView: __DIR__ . '/../Fixtures/failing.php', session: $session);
	request();
	$level = ob_get_level();
	$output = output(fn() => assertThrows(RuntimeException::class, fn() => Inertia::render('Home'), 'failing template', 'template failure'));
	assertSame($level, ob_get_level(), 'output buffers are closed');
	assertSame('', $output, 'partial output dropped');
	assertSame([], Response::$headers, 'no header');
	configure($session);
});

test('SSR renders the head and the body', function () use ($session, $ssr, &$reported): void {
	configure($session, $ssr, $reported);
	request();
	$ssr->calls = 0;
	$html = output(fn() => Inertia::render('Users/Index'));

	assertContains('<title>Users/Index</title>', $html, 'SSR head replaces the fallback');
	assertNotContains('Fallback', $html, 'no fallback');
	assertContains('<div data-server-rendered="true" id="app">rendered</div>', $html, 'SSR body');
	assertNotContains('data-page=', $html, 'the SSR body carries its own page script');
	assertSame(1, $ssr->calls, 'one SSR request for the head and the body');
});

test('A failed SSR rendering is reported and the client renders the page', function () use ($session, $ssr, &$reported): void {
	configure($session, $ssr, $reported);
	request();
	$ssr->fail = true;
	$reported = [];
	$html = output(fn() => Inertia::render('Users/Index'));
	$ssr->fail = false;

	assertContains('<title>Fallback</title>', $html, 'head fallback');
	assertSame('Users/Index', embeddedPage($html)['component'], 'client rendering');
	assertSame(1, count($reported), 'reported');
	assertSame('browser-api', $reported[0]->type, 'SSR error type');
});

test('SSR is skipped for Inertia visits and when disabled', function () use ($session, $ssr, &$reported): void {
	configure($session, $ssr, $reported);
	$ssr->calls = 0;
	request('GET', '/', inertia());
	output(fn() => Inertia::render('Users/Index'));
	request();
	Inertia::disableSsr();
	$html = output(fn() => Inertia::render('Users/Index'));

	assertSame(0, $ssr->calls, 'no SSR request');
	assertContains('<title>Fallback</title>', $html, 'client rendering');
	configure($session);
});

test('Flash data and errors survive the redirect and are sent once', function () use ($session): void {
	request('POST', '/users', inertia(['Referer' => 'https://app.test/users/create?step=2']));
	Inertia::flash('message', 'User created');
	Inertia::flash(['newUserId' => 7]);
	Inertia::withErrors(['name' => 'Required.']);
	Inertia::clearHistory();
	Inertia::back();

	assertSame(302, Response::$status, 'redirect status');
	assertSame('/users/create?step=2', Response::get('Location'), 'relative previous URL');
	assertSame([
		'flash' => ['message' => 'User created', 'newUserId' => 7],
		'errors' => ['default' => ['name' => 'Required.']],
		'clearHistory' => true,
	], $session->data[Inertia::SESSION_KEY], 'session');

	request('GET', '/users/create', inertia());
	$page = Inertia::page('Users/Create');
	assertSame(['message' => 'User created', 'newUserId' => 7], $page['flash'], 'flash sent with the next page');
	assertSame(['name' => 'Required.'], $page['props']['errors'], 'errors of the next page');
	assertSame(true, $page['clearHistory'], 'history cleared');
	assertSame([], $session->data, 'consumed');

	request('GET', '/users/create', inertia());
	$page = Inertia::page('Users/Create');
	assertTrue(!isset($page['flash']) && !isset($page['clearHistory']), 'sent once');
});

test('Pending data rendered in the same request never reaches the session', function () use ($session): void {
	request('POST', '/users', inertia());
	Inertia::flash('highlight', 3);
	Inertia::withErrors(['name' => 'Required.']);
	$page = Inertia::page('Users/Create');
	Inertia::finishRequest();

	assertSame(['highlight' => 3], $page['flash'], 'flash');
	assertSame(['name' => 'Required.'], $page['props']['errors'], 'errors');
	assertSame([], $session->data, 'session untouched');
});

test('Data of successive redirects is merged until a page sends it', function () use ($session): void {
	request('POST', '/a');
	Inertia::flash('first', 1);
	Inertia::redirect('/b');
	request('GET', '/b');
	Inertia::flash('second', 2);
	Inertia::redirect('/c');
	request('GET', '/c', inertia());

	assertSame(['first' => 1, 'second' => 2], Inertia::page('C')['flash'], 'both flashes');
});

test('Pending data of a request without page nor redirect is kept for the next page', function () use ($session): void {
	request('POST', '/api/star');
	Inertia::flash('starred', true);
	Inertia::finishRequest();
	assertSame(['flash' => ['starred' => true]], $session->data[Inertia::SESSION_KEY], 'kept');
	request();
	assertSame(['starred' => true], Inertia::page('A')['flash'], 'sent');
});

test('History encryption follows the configuration and the request', function () use ($session): void {
	configure($session, encryptHistory: true);
	request();
	assertSame(true, Inertia::page('A')['encryptHistory'], 'encrypted by default');
	Inertia::encryptHistory(false);
	assertTrue(!isset(Inertia::page('A')['encryptHistory']), 'disabled for the request');

	configure($session);
	request();
	Inertia::encryptHistory();
	assertSame(true, Inertia::page('A')['encryptHistory'], 'enabled for the request');
	request();
	assertTrue(!isset(Inertia::page('A')['encryptHistory']), 'reset by the next request');
});

test('The fragment can be preserved across a redirect', function (): void {
	request('GET', '/article/old-slug', inertia());
	Inertia::preserveFragment();
	Inertia::redirect('/article/new-slug');
	request('GET', '/article/new-slug', inertia());
	assertSame(true, Inertia::page('Article')['preserveFragment'], 'preserveFragment');
});

test('Redirects use 303 after PUT, PATCH and DELETE', function (): void {
	foreach (['POST' => 302, 'PUT' => 303, 'PATCH' => 303, 'DELETE' => 303, 'GET' => 302] as $method => $status) {
		request($method, '/users/1', inertia());
		Inertia::redirect('/users');
		assertSame($status, Response::$status, "{$method} redirect");
		assertSame('/users', Response::get('Location'), "{$method} location");
	}
	request('PUT', '/users/1', inertia());
	Inertia::redirect('/users', 301);
	assertSame(301, Response::$status, 'explicit status');
});

test('An Inertia visit redirected to a URL with a fragment gets a 409', function (): void {
	request('POST', '/comments', inertia());
	Inertia::redirect('/posts/1#comment-3');
	assertSame(409, Response::$status, 'status');
	assertSame('/posts/1#comment-3', Response::get('X-Inertia-Redirect'), 'X-Inertia-Redirect');
	assertSame(null, Response::get('Location'), 'no Location');

	request('GET', '/posts', inertia(['Purpose' => 'prefetch']));
	Inertia::redirect('/posts/1#top');
	assertSame(302, Response::$status, 'a prefetch is redirected');

	request('POST', '/comments');
	Inertia::redirect('/posts/1#comment-3');
	assertSame('/posts/1#comment-3', Response::get('Location'), 'a browser keeps the fragment itself');
});

test('back() only follows a Referer of this host', function (): void {
	request('POST', '/lang', ['Referer' => 'https://evil.example/phishing']);
	Inertia::back('/home');
	assertSame('/home', Response::get('Location'), 'foreign Referer');

	request('POST', '/lang', ['Referer' => 'https://APP.test//evil.example/x']);
	Inertia::back();
	assertSame('/evil.example/x', Response::get('Location'), 'never protocol-relative');

	request('POST', '/lang');
	Inertia::back('/fallback');
	assertSame('/fallback', Response::get('Location'), 'no Referer');
});

test('location() makes the client load the page, with a 409 for Inertia visits', function () use ($session): void {
	request('POST', '/logout', inertia());
	Response::header('Location: /stale', true, 0);
	Inertia::flash('bye', true);
	Inertia::location('https://auth.example/logout');
	assertSame(409, Response::$status, 'status');
	assertSame('https://auth.example/logout', Response::get('X-Inertia-Location'), 'X-Inertia-Location');
	assertSame(null, Response::get('Location'), 'no Location');
	assertSame(['flash' => ['bye' => true]], $session->data[Inertia::SESSION_KEY], 'pending data kept');
	$session->data = [];

	request('GET', '/sso');
	Inertia::location('https://auth.example/login');
	assertSame(302, Response::$status, 'plain redirect for a browser');
	assertSame('https://auth.example/login', Response::get('Location'), 'location');
});

test('An unencodable page fails before any header is sent', function (): void {
	request('GET', '/', inertia());
	assertThrows(JsonException::class, fn() => output(fn() => Inertia::render('A', ['bad' => "\xB1\x31"])), 'invalid UTF-8');
	assertSame([], Response::$headers, 'no header');
});

test('Precognition answers the validation result', function (): void {
	request('POST', '/users', ['Precognition' => 'true', 'Precognition-Validate-Only' => 'email', 'Content-Type' => 'application/json']);
	assertSame(true, Precognition::isRequest(), 'Precognition request');
	assertSame(['email'], Precognition::validateOnly(), 'validated fields');

	$body = output(fn() => assertThrows(StopRequestException::class, fn() => Inertia::validate(['email' => ['Taken.'], 'name' => 'Required.']), 'stops'));
	assertSame(422, Response::$status, 'invalid status');
	assertSame('true', Response::get('Precognition'), 'Precognition header');
	assertSame(['Precognition'], Response::all('Vary'), 'Vary');
	assertSame(['message' => 'The given data was invalid.', 'errors' => ['email' => ['Taken.']]], json($body), 'only the validated fields');

	request('POST', '/users', ['Precognition' => 'true', 'Precognition-Validate-Only' => 'email']);
	$body = output(fn() => assertThrows(StopRequestException::class, fn() => Inertia::validate(['name' => 'Required.']), 'stops'));
	assertSame(204, Response::$status, 'valid status');
	assertSame('true', Response::get('Precognition-Success'), 'success header');
	assertSame('', $body, 'no body');
});

test('Inertia::validate() throws the errors of an ordinary request', function (): void {
	request('POST', '/users', inertia());
	Inertia::validate([]);
	$e = assertThrows(ValidationException::class, fn() => Inertia::validate(['name' => 'Required.'], 'createUser'), 'invalid data');
	assertSame(422, $e->getStatusCode(), 'status');
	assertSame(['errors' => ['name' => 'Required.'], 'errorBag' => 'createUser'], $e->getExtensions(), 'extensions');
});

test('input() reads the JSON body Inertia sends, or the form fields', function (): void {
	$_POST = ['name' => 'form'];
	request('POST', '/users', ['Content-Type' => 'multipart/form-data; boundary=x']);
	assertSame(['name' => 'form'], Inertia::input(), 'form fields');

	request('POST', '/users', inertia(['Content-Type' => 'application/json']));
	assertSame([], Inertia::input(), 'never the form fields of a JSON request');
	$_POST = [];

	$url = startServer(__DIR__ . '/../Fixtures/input_server.php');
	$body = file_get_contents($url, false, stream_context_create(['http' => [
		'method' => 'POST',
		'header' => "Content-Type: application/json\r\nX-Inertia: true\r\n",
		'content' => '{"name":"Ada","tags":["a"],"nested":{"x":1}}',
	]]));
	assertSame(['name' => 'Ada', 'tags' => ['a'], 'nested' => ['x' => 1]], json($body), 'JSON body');

	$body = file_get_contents($url, false, stream_context_create(['http' => [
		'method' => 'POST',
		'header' => "Content-Type: application/json\r\n",
		'content' => 'not json',
	]]));
	assertSame([], json($body), 'invalid JSON');
});

finish();
