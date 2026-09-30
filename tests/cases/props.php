<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use Karewan\KnRouteInertia\Inertia;
use Karewan\KnRouteInertia\Props\ScrollMetadata;
use Tests\Fixtures\ArraySessionStore;
use Tests\Fixtures\Avatar;
use Tests\Fixtures\PageComponent;
use Tests\Fixtures\UserPermissions;

$reported = [];
Inertia::configure(
	rootView: static fn($view): string => $view->app(),
	version: VERSION,
	session: new ArraySessionStore(),
	reporter: static function (Throwable $e) use (&$reported): void {
		$reported[] = $e->getMessage();
	},
);

/** Headers of a partial reload of the Dashboard component. */
function partial(array $headers = []): array
{
	return inertia($headers + ['X-Inertia-Partial-Component' => 'Dashboard']);
}

test('Full visit resolves values and Closures, leaves out optional and deferred props', function (): void {
	$calls = [];
	$page = Inertia::page('Dashboard', [
		'title' => 'Dashboard',
		'count' => 42,
		'lazy' => function () use (&$calls): string {
			$calls[] = 'lazy';
			return 'computed';
		},
		'always' => Inertia::always(fn(): string => 'always'),
		'optional' => Inertia::optional(function () use (&$calls): string {
			$calls[] = 'optional';
			return 'optional';
		}),
		'permissions' => Inertia::defer(function () use (&$calls): array {
			$calls[] = 'permissions';
			return [];
		}),
		'teams' => Inertia::defer(fn(): array => [], 'attributes'),
		'projects' => Inertia::defer(fn(): array => [], 'attributes'),
	]);

	assertSame(['title' => 'Dashboard', 'count' => 42, 'lazy' => 'computed', 'always' => 'always', 'errors' => new stdClass()] == $page['props'], true, 'props');
	assertSame(['title', 'count', 'lazy', 'always', 'errors'], array_keys($page['props']), 'prop order');
	assertSame(['lazy'], $calls, 'only the sent Closures are called');
	assertSame(['default' => ['permissions'], 'attributes' => ['teams', 'projects']], $page['deferredProps'], 'deferred groups');
	assertSame('Dashboard', $page['component'], 'component');
	assertSame('/', $page['url'], 'url');
	assertSame(VERSION, $page['version'], 'version');
	assertTrue(!isset($page['mergeProps'], $page['onceProps'], $page['encryptHistory'], $page['clearHistory'], $page['flash']), 'no empty metadata');
});

test('The page object is encoded with the protocol types', function (): void {
	request('GET', '/users?page=2&sort=name', inertia());
	$json = output(fn() => Inertia::render('Users/Index'));
	assertSame(
		'{"component":"Users/Index","props":{"errors":{}},"url":"/users?page=2&sort=name","version":"v1","sharedProps":["errors"]}',
		$json,
		'JSON page',
	);
});

test('Enum cases name the component', function (): void {
	assertSame('Users/Index', Inertia::page(PageComponent::UsersIndex)['component'], 'backed enum');
});

test('Merge props carry their labels', function (): void {
	$page = Inertia::page('Dashboard', [
		'tags' => Inertia::merge(['a']),
		'notifications' => Inertia::merge(['n'])->prepend(),
		'chat' => Inertia::deepMerge(['messages' => []])->matchOn('messages.id'),
		'feed' => Inertia::merge(['posts' => [], 'announcements' => []])->append('posts', 'id')->prepend('announcements'),
		'many' => Inertia::merge([])->append(['users.data' => 'id', 'messages']),
		'results' => Inertia::defer(fn(): array => [])->deepMerge(),
	]);

	assertSame(['tags', 'feed.posts', 'many.users.data', 'many.messages'], $page['mergeProps'], 'mergeProps');
	assertSame(['notifications', 'feed.announcements'], $page['prependProps'], 'prependProps');
	assertSame(['chat', 'results'], $page['deepMergeProps'], 'deepMergeProps of a sent and a deferred prop');
	assertSame(['chat.messages.id', 'feed.posts.id', 'many.users.data.id'], $page['matchPropsOn'], 'matchPropsOn');
	assertTrue(!isset($page['props']['results']), 'the deferred merge prop is not sent');
});

test('Once props are remembered by key and expire in milliseconds', function (): void {
	$before = time() * 1000;
	$page = Inertia::page('Dashboard', [
		'plans' => Inertia::once(fn(): array => ['basic']),
		'memberRoles' => Inertia::once(fn(): array => ['admin'])->as('roles'),
		'rates' => Inertia::once(fn(): array => [])->until(60),
		'at' => Inertia::once(fn(): int => 1)->until(new DateTimeImmutable('@1893456000')),
	]);

	assertSame(['basic'], $page['props']['plans'], 'once prop sent on the first visit');
	assertSame(['prop' => 'plans', 'expiresAt' => null], $page['onceProps']['plans'], 'without expiration');
	assertSame(['prop' => 'memberRoles', 'expiresAt' => null], $page['onceProps']['roles'], 'custom key');
	$expiresAt = $page['onceProps']['rates']['expiresAt'];
	assertTrue($expiresAt >= $before + 60000 && $expiresAt <= (time() + 60) * 1000, 'expiration in seconds');
	assertSame(1893456000000, $page['onceProps']['at']['expiresAt'], 'expiration at a date');
});

test('Once props the client holds are skipped on Inertia visits, unless refreshed', function (): void {
	request('GET', '/', inertia(['X-Inertia-Except-Once-Props' => 'plans,roles']));
	$calls = 0;
	$page = Inertia::page('Billing', [
		'plans' => Inertia::once(function () use (&$calls): array {
			$calls++;
			return [];
		}),
		'availableRoles' => Inertia::once(fn(): array => ['r'])->as('roles'),
		'fresh' => Inertia::once(fn(): string => 'new')->fresh(),
		'rates' => Inertia::once(fn(): array => ['eur']),
	]);

	assertSame(0, $calls, 'the held once prop is not resolved');
	assertSame(['fresh', 'rates', 'errors'], array_keys($page['props']), 'held once props are left out');
	assertSame(['plans', 'roles', 'fresh', 'rates'], array_keys($page['onceProps']), 'every once prop keeps its metadata');

	request('GET', '/', ['X-Inertia-Except-Once-Props' => 'plans']);
	$page = Inertia::page('Billing', ['plans' => Inertia::once(fn(): array => ['basic'])]);
	assertSame(['basic'], $page['props']['plans'], 'a full page load always resolves once props');
});

test('Optional, deferred and merge props can be once props', function (): void {
	$page = Inertia::page('Dashboard', [
		'permissions' => Inertia::defer(fn(): array => [])->once(),
		'activity' => Inertia::merge(fn(): array => ['a'])->once(),
		'categories' => Inertia::optional(fn(): array => [])->once(as: 'cats'),
	]);

	assertSame(['activity', 'errors'], array_keys($page['props']), 'props');
	assertSame(['permissions', 'activity', 'cats'], array_keys($page['onceProps']), 'once metadata');
	assertSame(['default' => ['permissions']], $page['deferredProps'], 'deferred');
});

test('Prop types are resolved inside arrays and inside Closures, with dot paths', function (): void {
	$page = Inertia::page('Dashboard', [
		'auth' => fn(): array => [
			'user' => ['name' => 'Jonathan'],
			'notifications' => Inertia::defer(fn(): array => ['n']),
			'invoices' => Inertia::optional(fn(): array => ['i']),
			'lazy' => fn(): string => 'nested closure',
		],
		'menu' => [
			'items' => [['label' => 'Home', 'count' => fn(): int => 3]],
			'feed' => Inertia::merge(['x'])->append('data'),
		],
	]);

	assertSame(['user' => ['name' => 'Jonathan'], 'lazy' => 'nested closure'], $page['props']['auth'], 'nested props inside a Closure');
	assertSame(['items' => [['label' => 'Home', 'count' => 3]], 'feed' => ['x']], $page['props']['menu'], 'nested props inside arrays');
	assertSame(['default' => ['auth.notifications']], $page['deferredProps'], 'nested deferred path');
	assertSame(['menu.feed.data'], $page['mergeProps'], 'nested merge path');
});

test('Plain data is returned without copies or changes', function (): void {
	$rows = [];
	for ($i = 0; $i < 50; $i++) $rows[] = ['id' => $i, 'name' => "user {$i}", 'tags' => ['a', 'b'], 'meta' => ['score' => 1.5, 'empty' => []]];
	$page = Inertia::page('Users', ['users' => $rows, 'object' => new ArrayObject([1])]);

	assertSame($rows, $page['props']['users'], 'rows');
	assertTrue($page['props']['object'] instanceof ArrayObject, 'other objects are left to json_encode()');
});

test('Property providers expand, at the top level and nested, and property objects transform themselves', function (): void {
	$page = Inertia::page('Profile', [
		'user' => 'Jonathan',
		new UserPermissions(true),
		'avatar' => new Avatar(128),
		'nested' => ['team' => 'Inertia', new UserPermissions(false)],
	]);

	assertSame([
		'user' => 'Jonathan',
		'avatar' => 'Profile:avatar:128:user,canEdit,isAdmin,component,avatar,nested',
		'nested' => ['team' => 'Inertia', 'canEdit' => false, 'isAdmin' => false, 'component' => 'Profile'],
		'canEdit' => true,
		'isAdmin' => true,
		'component' => 'Profile',
	] == array_diff_key($page['props'], ['errors' => true]), true, 'props');

	$page = Inertia::page('Profile', new UserPermissions(true));
	assertSame(true, $page['props']['isAdmin'], 'a provider as the props');
});

test('Shared props merge under the page props and are listed', function (): void {
	Inertia::share('appName', 'KnRoute');
	Inertia::share(['auth' => fn(): array => ['user' => 'Jonathan'], 'locale' => 'fr']);
	Inertia::share('flashes.count', 1);
	$page = Inertia::page('Dashboard', ['locale' => 'en', 'auth.token' => 'abc']);

	assertSame('KnRoute', $page['props']['appName'], 'shared prop');
	assertSame('en', $page['props']['locale'], 'the page prop wins');
	assertSame(['user' => 'Jonathan', 'token' => 'abc'], $page['props']['auth'], 'a dot key nests into a lazy shared prop');
	assertSame(['count' => 1], $page['props']['flashes'], 'a shared dot key is nested');
	assertSame(['errors', 'appName', 'auth', 'locale', 'flashes'], $page['sharedProps'], 'shared keys');
	assertSame('KnRoute', Inertia::getShared('appName'), 'getShared');
	assertSame(1, Inertia::getShared('flashes.count'), 'getShared with a dot key');
	assertSame('none', Inertia::getShared('missing.key', 'none'), 'getShared default');
});

test('Shared once props', function (): void {
	Inertia::shareOnce('countries', fn(): array => ['FR'])->until(3600);
	$page = Inertia::page('Dashboard');
	assertSame(['FR'], $page['props']['countries'], 'shared once prop');
	assertTrue(isset($page['onceProps']['countries']['expiresAt']), 'shared once metadata');
});

test('Partial reload with only resolves the selected props and the always props', function (): void {
	request('GET', '/', partial(['X-Inertia-Partial-Data' => 'users,optional']));
	$calls = [];
	$page = Inertia::page('Dashboard', [
		'users' => function () use (&$calls): array {
			$calls[] = 'users';
			return ['u'];
		},
		'companies' => function () use (&$calls): array {
			$calls[] = 'companies';
			return ['c'];
		},
		'optional' => Inertia::optional(fn(): string => 'selected'),
		'deferred' => Inertia::defer(fn(): string => 'not selected'),
		'always' => Inertia::always('always'),
	]);

	assertSame(['users'], $calls, 'only the selected Closures run');
	assertSame(['users', 'optional', 'always', 'errors'], array_keys($page['props']), 'selected props');
	assertTrue(!isset($page['deferredProps']), 'no deferred metadata on a partial reload');
});

test('Partial reload with except, and with both lists', function (): void {
	request('GET', '/', partial(['X-Inertia-Partial-Except' => 'users,always']));
	$page = Inertia::page('Dashboard', ['users' => 1, 'companies' => 2, 'always' => Inertia::always(3)]);
	assertSame(['companies', 'always', 'errors'], array_keys($page['props']), 'except, always props are immune');

	request('GET', '/', partial(['X-Inertia-Partial-Data' => 'users,companies', 'X-Inertia-Partial-Except' => 'companies']));
	$page = Inertia::page('Dashboard', ['users' => 1, 'companies' => 2, 'roles' => 3]);
	assertSame(['users', 'errors'], array_keys($page['props']), 'except removes from only');
});

test('Partial reload of another component is a full visit', function (): void {
	request('GET', '/', inertia(['X-Inertia-Partial-Component' => 'Other', 'X-Inertia-Partial-Data' => 'users']));
	$page = Inertia::page('Dashboard', ['users' => 1, 'companies' => 2, 'optional' => Inertia::optional(fn(): int => 3)]);
	assertSame(['users', 'companies', 'errors'], array_keys($page['props']), 'full visit props');
});

test('Partial reload selects nested props with dot notation', function (): void {
	request('GET', '/', partial(['X-Inertia-Partial-Data' => 'auth.notifications,menu']));
	$page = Inertia::page('Dashboard', [
		'auth' => [
			'user' => fn(): string => 'not selected',
			'notifications' => Inertia::defer(fn(): array => ['n'])->merge(),
			'invoices' => Inertia::optional(fn(): array => ['i']),
		],
		'menu' => ['items' => fn(): array => ['m'], 'hidden' => Inertia::optional(fn(): string => 'selected with its parent')],
		'other' => 'not selected',
	]);

	assertSame(['notifications' => ['n']], $page['props']['auth'], 'nested selection');
	assertSame(['items' => ['m'], 'hidden' => 'selected with its parent'], $page['props']['menu'], 'a selected parent selects its children');
	assertSame(['auth', 'menu', 'errors'], array_keys($page['props']), 'top level');
	assertSame(['auth.notifications'], $page['mergeProps'], 'merge metadata of the selected prop');
});

test('Partial reload excludes nested props with dot notation', function (): void {
	request('GET', '/', partial(['X-Inertia-Partial-Except' => 'auth.token']));
	$page = Inertia::page('Dashboard', ['auth' => ['user' => 'u', 'token' => fn(): string => 'secret'], 'title' => 't']);
	assertSame(['user' => 'u'], $page['props']['auth'], 'nested exclusion');
	assertSame('t', $page['props']['title'], 'other props');
});

test('Partial reload without only nor except resolves every prop', function (): void {
	request('GET', '/', partial());
	$page = Inertia::page('Dashboard', ['a' => 1, 'optional' => Inertia::optional(fn(): int => 2), 'deferred' => Inertia::defer(fn(): int => 3)]);
	assertSame(['a' => 1, 'optional' => 2, 'deferred' => 3], array_diff_key($page['props'], ['errors' => true]), 'props');
});

test('Deferred props of a group are loaded by a partial reload with merge metadata', function (): void {
	request('GET', '/', partial(['X-Inertia-Partial-Data' => 'teams,projects']));
	$page = Inertia::page('Dashboard', [
		'teams' => Inertia::defer(fn(): array => ['t'], 'attributes')->append('data', 'id'),
		'projects' => Inertia::defer(fn(): array => ['p'], 'attributes'),
		'permissions' => Inertia::defer(fn(): array => ['x']),
	]);

	assertSame(['teams', 'projects', 'errors'], array_keys($page['props']), 'group props');
	assertSame(['teams.data'], $page['mergeProps'], 'merge path');
	assertSame(['teams.data.id'], $page['matchPropsOn'], 'match path');
});

test('A rescued deferred prop is reported and omitted, the others fail the response', function () use (&$reported): void {
	request('GET', '/', partial(['X-Inertia-Partial-Data' => 'permissions,roles']));
	$reported = [];
	$page = Inertia::page('Dashboard', [
		'permissions' => Inertia::defer(fn(): never => throw new RuntimeException('database down'), rescue: true),
		'roles' => Inertia::defer(fn(): array => ['admin']),
	]);

	assertSame(['roles', 'errors'], array_keys($page['props']), 'the rescued prop is omitted, not null');
	assertSame(['permissions'], $page['rescuedProps'], 'rescuedProps');
	assertSame(['database down'], $reported, 'the failure is reported');

	request('GET', '/', partial(['X-Inertia-Partial-Data' => 'permissions']));
	assertThrows(RuntimeException::class, fn() => Inertia::page('Dashboard', [
		'permissions' => Inertia::defer(fn(): never => throw new RuntimeException('database down')),
	]), 'unrescued failure', 'database down');
});

test('Reset props are replaced instead of merged', function (): void {
	request('GET', '/', partial(['X-Inertia-Partial-Data' => 'posts,users', 'X-Inertia-Reset' => 'posts,users']));
	$page = Inertia::page('Dashboard', [
		'posts' => Inertia::merge(['p']),
		'users' => Inertia::scroll(['data' => ['u']], ScrollMetadata::forPage(1, true)),
	]);

	assertTrue(!isset($page['mergeProps']), 'no merge label for the reset props');
	assertSame(true, $page['scrollProps']['users']['reset'], 'scroll reset flag');
});

test('Scroll props carry their cursor and merge in the scroll direction', function (): void {
	$page = Inertia::page('Posts', [
		'posts' => Inertia::scroll(fn(): array => ['data' => [1, 2], 'total' => 9], ScrollMetadata::forPage(1, true)),
		'comments' => Inertia::scroll(
			['items' => [3], 'cursor' => 'abc'],
			fn(array $data): ScrollMetadata => new ScrollMetadata($data['cursor'], null, 'def', 'cursor'),
			'items',
		),
		'later' => Inertia::scroll(fn(): array => ['data' => []], ScrollMetadata::forPage(2, false))->defer(),
	]);

	assertSame(['data' => [1, 2], 'total' => 9], $page['props']['posts'], 'paginated data');
	assertSame(['pageName' => 'page', 'previousPage' => null, 'nextPage' => 2, 'currentPage' => 1, 'reset' => false], $page['scrollProps']['posts'], 'page cursor');
	assertSame(['pageName' => 'cursor', 'previousPage' => null, 'nextPage' => 'def', 'currentPage' => 'abc', 'reset' => false], $page['scrollProps']['comments'], 'metadata derived from the data');
	assertSame(['posts.data', 'comments.items', 'later.data'], $page['mergeProps'], 'merged at the wrapper key');
	assertSame(['default' => ['later']], $page['deferredProps'], 'deferred scroll prop');
	assertTrue(!isset($page['scrollProps']['later']), 'no cursor for a deferred scroll prop on the full visit');

	request('GET', '/', inertia(['X-Inertia-Partial-Component' => 'Posts', 'X-Inertia-Partial-Data' => 'posts', 'X-Inertia-Infinite-Scroll-Merge-Intent' => 'prepend']));
	$page = Inertia::page('Posts', ['posts' => Inertia::scroll(['data' => [0]], ScrollMetadata::forPage(3, true))]);
	assertSame(['posts.data'], $page['prependProps'], 'prepended when scrolling backwards');
	assertSame(2, $page['scrollProps']['posts']['previousPage'], 'previous page');
});

test('A Closure returning a prop type takes part in the filtering and the metadata', function (): void {
	$page = Inertia::page('Dashboard', [
		'stats' => fn() => Inertia::defer(fn(): array => ['s']),
		'feed' => fn() => Inertia::merge(['f']),
	]);
	assertSame(['feed', 'errors'], array_keys($page['props']), 'the returned deferred prop is left out');
	assertSame(['default' => ['stats']], $page['deferredProps'], 'deferred');
	assertSame(['feed'], $page['mergeProps'], 'merge');
});

test('Validation errors are an object, scoped by the error bag header', function (): void {
	Inertia::withErrors(['email' => 'The email is invalid.', 'name' => ['Required.', 'Too short.']]);
	$page = Inertia::page('Users/Create');
	assertSame(['email' => 'The email is invalid.', 'name' => ['Required.', 'Too short.']], $page['props']['errors'], 'default bag');

	request('GET', '/', inertia(['X-Inertia-Error-Bag' => 'createUser']));
	Inertia::withErrors(['email' => 'The email is invalid.']);
	assertSame(['createUser' => ['email' => 'The email is invalid.']], Inertia::page('Users/Create')['props']['errors'], 'error bag header');

	request();
	Inertia::withErrors(['name' => 'Required.'], 'createCompany');
	assertSame(['createCompany' => ['name' => 'Required.']], Inertia::page('Users/Create')['props']['errors'], 'named bag');

	request('GET', '/', partial(['X-Inertia-Partial-Data' => 'users', 'X-Inertia-Partial-Except' => 'errors']));
	assertTrue(Inertia::page('Dashboard', ['users' => 1])['props']['errors'] instanceof stdClass, 'errors on every partial reload');

	request();
	assertSame('custom', Inertia::page('Dashboard', ['errors' => 'custom'])['props']['errors'], 'an errors prop of the page wins');
});

test('Version from a Closure is resolved once per request', function (): void {
	$calls = 0;
	Inertia::configure(rootView: static fn($view): string => '', version: function () use (&$calls): string {
		$calls++;
		return 'lazy';
	}, session: false);
	request();
	Inertia::page('A');
	Inertia::page('B');
	assertSame(1, $calls, 'calls in the first request');
	request();
	assertSame('lazy', Inertia::getVersion(), 'version');
	assertSame(2, $calls, 'called again by the next request');

	Inertia::configure(rootView: static fn($view): string => '', session: false);
	request();
	assertSame('', Inertia::page('A')['version'], 'no version');
});

test('The page URL keeps the query string, never becomes protocol-relative', function (): void {
	request('GET', '/events/80/?tab=1');
	assertSame('/events/80/?tab=1', Inertia::page('A')['url'], 'origin form');
	request('GET', '//evil.example/path');
	assertSame('/evil.example/path', Inertia::page('A')['url'], 'leading slashes');
	request('GET', 'http://app.test/absolute?x=1');
	assertSame('/absolute?x=1', Inertia::page('A')['url'], 'absolute form');
});

finish();
