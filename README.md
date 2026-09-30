# KnRoute Inertia

Fast [Inertia.js v3](https://inertiajs.com) server-side adapter for the [KnRoute](https://github.com/Karewan/KnRoute) PHP 8.3+ router.

It implements the whole [Inertia v3 protocol](https://inertiajs.com/docs/v3/core-concepts/the-protocol): full page loads and Inertia visits, partial reloads with dot notation, always, optional, deferred, merge, once and infinite scroll props, shared data, flash data, validation errors and error bags, redirects (303, fragments, external locations), asset versioning, history encryption, Precognition, error pages and server-side rendering. The front end uses the official Inertia packages (`@inertiajs/vue3`, `@inertiajs/react` or `@inertiajs/svelte`).

## Table of contents

- [KnRoute Inertia](#knroute-inertia)
	- [Table of contents](#table-of-contents)
	- [Installation](#installation)
		- [Requirements](#requirements)
		- [Getting started](#getting-started)
	- [Server setup](#server-setup)
		- [Configure the adapter and the router](#configure-the-adapter-and-the-router)
		- [The root template](#the-root-template)
		- [Configuration options](#configuration-options)
	- [Client setup](#client-setup)
	- [Usage](#usage)
		- [Render a page](#render-a-page)
		- [Props](#props)
		- [Nested props and dot notation](#nested-props-and-dot-notation)
		- [Property providers](#property-providers)
		- [Shared data](#shared-data)
		- [Partial reloads](#partial-reloads)
		- [Deferred props](#deferred-props)
		- [Merging props](#merging-props)
		- [Infinite scroll](#infinite-scroll)
		- [Once props](#once-props)
		- [Redirects](#redirects)
		- [Flash data](#flash-data)
		- [Forms and validation errors](#forms-and-validation-errors)
		- [Precognition](#precognition)
		- [Error pages](#error-pages)
		- [Asset versioning](#asset-versioning)
		- [History encryption](#history-encryption)
		- [Server-side rendering](#server-side-rendering)
		- [Sessions](#sessions)
		- [CSRF protection](#csrf-protection)
		- [Long-running workers](#long-running-workers)
	- [Performance](#performance)
	- [Sample application](#sample-application)
	- [Formatting and linting](#formatting-and-linting)
	- [Tests](#tests)
	- [Changelog](#changelog)
	- [License](#license)

## Installation

### Requirements

- PHP 8.3+ with the JSON extension
- [KnRoute](https://github.com/Karewan/KnRoute) 4.1+
- An Inertia.js v3 client adapter

### Getting started

```shell
composer require karewan/knroute-inertia
```

## Server setup

### Configure the adapter and the router

Configure the adapter once, register `InertiaMiddleware` as the first global middleware, and let `ErrorHandler` render the HTTP errors:

```php
declare(strict_types=1);

use Karewan\KnRoute\Router;
use Karewan\KnRouteInertia\ErrorHandler;
use Karewan\KnRouteInertia\Inertia;
use Karewan\KnRouteInertia\InertiaMiddleware;

session_start();

Inertia::configure(
	rootView: __DIR__ . '/App/Views/app.php',
	version: APP_VERSION,
	share: static fn(): array => [
		'appName' => 'My app',
		'auth' => ['user' => fn() => currentUser()?->toArray()],
	],
);

$router = new Router();
$router->addGlobalMiddleware(new InertiaMiddleware());
$router->setDefaultErrorHandler(new ErrorHandler('Error'));
$router->registerRoutesFromControllers(
	controllersPath: __DIR__ . '/App/Controllers',
	cacheFile: __DIR__ . '/tmp/routes.php',
);
$router->run();
```

`InertiaMiddleware` is required:

- before the routing, it answers an Inertia visit made with outdated assets with a `409` reload, so its action never runs;
- after the action, it turns the `302` redirects of `PUT`, `PATCH` and `DELETE` Inertia visits into `303`, turns a redirect to a URL with a fragment into a `409` the client follows, keeps the flash data and errors that no page sent for the next page, and forgets the request state.

### The root template

A full page load renders the root template. When `rootView` is a file path, the template receives `$inertia` (a `RootView`), `$page` (the page object) and the view data as variables:

```php
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<script type="module" src="/build/app.js"></script>
	<?= $inertia->head('<title>My app</title>') ?>
</head>
<body>
	<?= $inertia->app() ?>
</body>
</html>
```

- `head(string $fallback = '')` prints the head elements rendered by the SSR server, or the fallback when the page is rendered by the client.
- `app(string $id = 'app')` prints the page object in a `<script data-page="app" type="application/json">` element followed by the root `<div id="app">`, or the server-rendered markup. The JSON escapes `<`, `>` and `/`, so no prop value can close the script element.

`rootView` may also be a Closure receiving the `RootView` and returning the document, to use the template engine of the application:

```php
Inertia::configure(
	rootView: static fn(RootView $inertia): string => view('App', ['inertia' => $inertia]),
);
```

### Configuration options

All the options of `Inertia::configure()` are named arguments:

| Option | Default | Description |
| --- | --- | --- |
| `rootView` | required | Path of the root template, or `Closure(RootView): string` |
| `version` | `null` | Asset version, or a Closure called at most once per request, only when the version is needed |
| `share` | `null` | `Closure(): array` returning the props shared by every page, called only when a page is rendered |
| `session` | `NativeSessionStore` | Keeps the data of a redirect for the next page, `false` without session |
| `ssr` | `null` | `SsrGateway` rendering the pages on the server, SSR disabled when `null` |
| `encryptHistory` | `false` | Encrypt the history state of every page |
| `exposeSharedPropKeys` | `true` | List the shared props in the page object (`sharedProps`), used by the instant visits |
| `jsonFlags` | `0` | Extra `json_encode()` flags of the page object, e.g. `JSON_PRESERVE_ZERO_FRACTION` |
| `reporter` | `error_log()` | `Closure(Throwable): void` reporting the rescued deferred props and the SSR failures |

The configuration is process-wide. Every other method applies to the current request.

## Client setup

Install the official Inertia v3 packages and the Vite plugin, for example with Vue:

```shell
pnpm add -D vite @vitejs/plugin-vue @inertiajs/vite @inertiajs/vue3 vue
```

```js
// vite.config.js
import inertia from "@inertiajs/vite";
import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vite";

export default defineConfig({
	plugins: [
		vue(),
		inertia(),
	],
	// public/ is the web root of PHP, not a folder of static files to copy
	publicDir: false,
	build: {
		manifest: true,
		outDir: "public/build",
		rolldownOptions: {
			input: "resources/js/app.js",
		},
	},
});
```

```js
// resources/js/app.js: the plugin resolves the components of ./Pages and mounts the application
import { createInertiaApp } from "@inertiajs/vue3";

createInertiaApp();
```

The root template loads the entry with the Vite development server in development, and the files listed by `public/build/.vite/manifest.json` in production. The [sample application](#sample-application) contains a small helper doing both.

## Usage

### Render a page

```php
use Karewan\KnRoute\Attributes\Get;
use Karewan\KnRouteInertia\Inertia;

final class EventsController
{
	#[Get('/events/{id:uint}')]
	public function show(int $id): void
	{
		$event = EventsDao::get($id) ?? throw new HttpException(404);

		Inertia::render('Events/Show', [
			'event' => ['id' => $event->id, 'title' => $event->title],
		]);
	}
}
```

`render(string|UnitEnum $component, array|ProvidesInertiaProperties $props = [], array $viewData = [], int $status = 200)` answers an Inertia visit with the page object as JSON (`X-Inertia: true`, `Vary: X-Inertia`) and a full page load with the root template. The component may be an enum case: the value of a backed enum, the name of a pure one. `$viewData` only reaches the root template, never the client. Like the `HttpUtils` output helpers, it returns control to the caller.

`Inertia::page()` builds the same page object without sending it.

Every prop is visible client-side: only send what the page needs.

### Props

| Prop | Full visit | Partial reload | Evaluated |
| --- | --- | --- | --- |
| `'users' => $users` | Sent | When selected | Always |
| `'users' => fn() => UsersDao::all()` | Sent | When selected | Only when sent |
| `Inertia::always(fn() => ...)` | Sent | Always sent | Always |
| `Inertia::optional(fn() => ...)` | Never | When selected | Only when sent |
| `Inertia::defer(fn() => ..., 'group')` | Announced, loaded by a follow-up request | When selected | Only when sent |
| `Inertia::merge($items)` | Sent, replaced | Merged by the client | Like a value or a Closure |
| `Inertia::once(fn() => ...)` | Sent once, then remembered | When selected | Only when sent |
| `Inertia::scroll(fn() => ..., $metadata)` | Sent with its cursor | Merged by the client | Only when sent |

Closures are resolved without argument. Any other object, including a `JsonSerializable`, is left to `json_encode()`. A Closure may also return a prop type, which then behaves as if it had been given directly.

### Nested props and dot notation

Closures and prop types are resolved at any depth, inside arrays and inside the arrays returned by Closures. Their metadata and the partial reloads use dot-notation paths:

```php
Inertia::render('Dashboard', [
	'auth' => fn() => [
		'user' => currentUser(),
		'notifications' => Inertia::defer(fn() => NotificationsDao::unread()),
	],
	// Nested into "auth" when the page is resolved
	'auth.permissions' => fn() => currentUser()->permissions(),
]);
```

```js
router.reload({
	only: [
		"auth.notifications",
	],
});
```

### Property providers

A `ProvidesInertiaProperty` value transforms itself, knowing its path, its sibling props and the component:

```php
final class Avatar implements ProvidesInertiaProperty
{
	public function __construct(private readonly User $user) {}

	public function toInertiaProperty(PropertyContext $context): mixed
	{
		return $this->user->avatarUrl();
	}
}
```

A `ProvidesInertiaProperties` listed without key is replaced by the props it returns, which lets pages reuse groups of props:

```php
Inertia::render('Profile', [
	'user' => $user,
	new UserPermissions($user), // canEdit, canDelete...
]);
```

### Shared data

Props shared by every page come from the `share` Closure of the configuration, called only when a page is rendered, and from `Inertia::share()` for the current request, for example in a middleware:

```php
Inertia::share('locale', 'fr');
Inertia::share(['team' => fn() => currentTeam()]);
Inertia::shareOnce('countries', fn() => CountriesDao::all());
```

Page props take precedence over shared props. `Inertia::getShared('team')` returns a shared prop, unresolved. The top-level keys of the shared props are listed in the `sharedProps` member of the page object, so an [instant visit](https://inertiajs.com/docs/v3/the-basics/instant-visits) carries them to the intermediate page.

### Partial reloads

A partial reload of the same component (`router.reload({ only: ['users'] })`, `except`, `<Link :only>`) only resolves the selected props, the always props and the validation errors. Wrap the expensive props in Closures so the unselected ones never run.

### Deferred props

```php
Inertia::render('Users/Index', [
	'users' => fn() => UsersDao::all(),
	'permissions' => Inertia::defer(fn() => PermissionsDao::all()),
	'teams' => Inertia::defer(fn() => TeamsDao::all(), 'attributes'),
	'projects' => Inertia::defer(fn() => ProjectsDao::all(), 'attributes'),
	'stats' => Inertia::defer(fn() => StatsDao::compute(), rescue: true),
]);
```

Deferred props are announced by the full visit and loaded by one follow-up request per group, in parallel. With `rescue: true`, a failure is reported, the prop is omitted and listed in `rescuedProps`, so the `<Deferred>` component shows its `rescue` slot instead of failing the response.

### Merging props

```php
Inertia::merge($items);                                // append at the root
Inertia::merge($items)->prepend();                     // prepend at the root
Inertia::merge($page)->append('data', matchOn: 'id');  // append to a nested array, update the items with the same id
Inertia::merge($forum)->append('posts')->prepend('announcements');
Inertia::merge($data)->append(['users.data' => 'id', 'messages']);
Inertia::deepMerge($chat)->matchOn('messages.id');
Inertia::defer(fn() => $results)->deepMerge();
```

Merging only happens on partial reloads: a full visit always replaces the prop. A prop listed in the `reset` option of the client is replaced.

### Infinite scroll

`Inertia::scroll()` sends a page of items and its cursor for the `<InfiniteScroll>` component. The items of the wrapper key (`data` by default) are appended, or prepended when the client scrolls backwards:

```php
$page = max(1, (int) ($_GET['page'] ?? 1));

Inertia::render('Posts/Index', [
	'posts' => Inertia::scroll(
		fn() => ['data' => PostsDao::page($page, 20)],
		ScrollMetadata::forPage($page, hasMore: $page < $lastPage),
	),
]);
```

`ScrollMetadata` also accepts cursors: `new ScrollMetadata($currentCursor, $previousCursor, $nextCursor, 'cursor')`. The metadata may be a Closure receiving the resolved data. `->defer()` loads the first page after the page is displayed.

### Once props

```php
'plans' => Inertia::once(fn() => PlansDao::all()),
'rates' => Inertia::once(fn() => RatesDao::all())->until(3600),        // seconds, DateInterval or DateTimeInterface
'roles' => Inertia::once(fn() => RolesDao::all())->as('roles'),         // shared by the pages using the same key
'prices' => Inertia::once(fn() => PricesDao::all())->fresh($changed),  // force a new value
'stats' => Inertia::defer(fn() => StatsDao::compute())->once(),         // also on optional and merge props
```

The client remembers a once prop and sends its key in `X-Inertia-Except-Once-Props`: the adapter skips it on the following visits until it expires. A partial reload selecting it always resolves it.

### Redirects

```php
Inertia::redirect('/users');                     // 302, or 303 after PUT, PATCH and DELETE
Inertia::back();                                 // the Referer of this host, or "/"
Inertia::back('/users');                         // with a fallback
Inertia::location('https://example.com/logout'); // full page visit, 409 + X-Inertia-Location for Inertia visits
Inertia::preserveFragment();                     // keep the fragment of the visited URL across the redirect
```

An Inertia visit redirected to a URL with a fragment gets a `409` with `X-Inertia-Redirect`, so the client keeps the fragment. `back()` only follows a `Referer` of the current host and redirects to its path: it is never an open redirect.

A redirect written with `HttpUtils::location()` works too: `InertiaMiddleware` applies the same rules to it after the action.

### Flash data

```php
Inertia::flash('success', 'User created.');
Inertia::flash(['success' => 'User created.', 'highlight' => $id]);
Inertia::redirect('/users');
```

Flash data is sent once with the next page, in `page.flash`, and never kept in the browser history: the page of this request when it is rendered, otherwise the page the redirect leads to (the data is kept in the session until then).

### Forms and validation errors

Inertia does not use `422` responses for forms: the action redirects back to the form with the errors, which reach the page in the `errors` prop, always sent, `{}` when empty.

```php
#[Post('/users')]
public function store(): void
{
	$input = Inertia::input();
	Inertia::validate(UsersValidator::errors($input)); // ['email' => 'The email address is invalid.']

	UsersDao::add($input);
	Inertia::flash('success', 'User created.');
	Inertia::redirect('/users');
}
```

- `Inertia::input()` returns the JSON body Inertia sends for a form without files, or `$_POST` for a multipart form.
- `Inertia::validate(array $errors, string $bag = 'default')` does nothing when `$errors` is empty. Otherwise it throws a `ValidationException` (a `422` `HttpException` carrying the errors in its `errors` extension). `ErrorHandler` sends them back: a redirect to the previous page for Inertia visits and page loads, a `422` JSON error with its `errors` member for `useHttp()` and the other JSON clients.
- `Inertia::withErrors($errors, $bag)` followed by `Inertia::back()` does the same without exception.

A field maps to one message or to a list of messages. With the `errorBag` option of the client (`X-Inertia-Error-Bag`), the errors are sent under the bag name. Errors given with a named bag are sent under that name.

When the application renders its errors with its own handler, `ErrorHandler::handleValidation(HttpError $error)` handles the validation errors and returns whether it did.

### Precognition

The Precognition validation of `useForm().withPrecognition()` and `<Form>` is answered by the action itself: `Inertia::validate()` detects the `Precognition` request, answers `204` or `422` with the errors of the validated fields only, and stops the request before the action stores anything. `Precognition` exposes the underlying helpers (`isRequest()`, `validateOnly()`, `filter()`, `pass()`, `fail()`).

### Error pages

`ErrorHandler` renders the KnRoute HTTP errors (`404`, `405`, `HttpException`...) as a page component for Inertia visits and page loads, and as a JSON error (`HttpUtils::outputError()`) for the other clients:

```php
$router->setDefaultErrorHandler(new ErrorHandler(
	component: 'Error',                         // null to never render an error page
	pageStatuses: [403, 404, 500, 503],         // null for every status
	props: fn(HttpError $error): array => ['status' => $error->code],
	fallback: fn(HttpError $error) => HttpUtils::outputError($error->code, $error->title, $error->detail),
));
```

The page receives `status`, `title` and `detail` by default, with the shared props, and keeps the error status. The client renders it instead of its error modal.

Exceptions that are not `HttpException` still leave `Router::run()`: catch them in the front controller and render the `500` page there if needed, with `(new ErrorHandler('Error'))->render(new HttpError(500, 'Server Error', '...'))`.

### Asset versioning

When the `version` changes, the next Inertia visit gets a `409` reload of the same URL, so the browser loads the new assets. The check runs before the routing. A version derived from a file (the Vite manifest...) should be given as a Closure: it is only computed for the requests that need it.

```php
version: static fn(): string => hash_file('xxh128', __DIR__ . '/public/build/.vite/manifest.json'),
```

### History encryption

```php
Inertia::encryptHistory();        // this page
Inertia::encryptHistory(false);   // not this page, whatever the configuration
Inertia::clearHistory();          // rotate the key, e.g. on logout: the encrypted history becomes unreadable
```

The route middleware `#[EncryptHistory]` (from `Karewan\KnRouteInertia\Middlewares`) encrypts the pages of a controller or an action, `#[EncryptHistory(false)]` opts out. Encryption needs a secure context (HTTPS or localhost).

### Server-side rendering

```php
use Karewan\KnRouteInertia\Ssr\HttpSsrGateway;

Inertia::configure(
	rootView: __DIR__ . '/App/Views/app.php',
	ssr: new HttpSsrGateway('http://127.0.0.1:13714/render', timeout: 2.0),
);
```

- Production: build the SSR bundle (`vite build --ssr`, the `@inertiajs/vite` plugin wraps the entry with the SSR server) and run it with Node (`node ssr/app.js`), under a process manager.
- Development: point the gateway to the endpoint of the Vite plugin, `http://localhost:5173/__inertia_ssr`, no separate server needed.

Only full page loads are rendered on the server, with one request for `head()` and `app()`. A failed rendering (server down, timeout, component error) is reported as an `SsrException` carrying the classification of the SSR server (`type`, `component`, `hint`, `sourceLocation`...), and the client renders the page: a broken SSR server never takes the site down. A reporter that rethrows it makes the failures visible in the end-to-end tests.

`Inertia::disableSsr()` or the route middleware `#[WithoutSsr]` render a request on the client only. `HttpSsrGateway::isHealthy()` checks the SSR server. Another renderer can implement `SsrGateway`.

### Sessions

The flash data, the errors and the history flags of a redirect are kept in the session until the next page reads them, under the `_inertia` key. The default `NativeSessionStore` uses `$_SESSION` with any save handler. A read never creates a session: it only resumes the session of a client that has its cookie. A write starts the session with `session_start()`, or with the starter of the application:

```php
Inertia::configure(
	rootView: $rootView,
	session: new NativeSessionStore(static fn() => AppSession::start()),
);
```

Implement `SessionStore` (`pull()` and `put()`) for another storage, or pass `session: false` when the application never redirects with data.

### CSRF protection

The Inertia client sends the `XSRF-TOKEN` cookie back in an `X-XSRF-TOKEN` header. A middleware of the application sets the cookie and checks the header on the state-changing methods:

```php
public function before(): void
{
	$token = $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
	setcookie('XSRF-TOKEN', $token, ['path' => '/', 'samesite' => 'Lax', 'secure' => true]);

	if (!in_array(HttpUtils::getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)
		&& !hash_equals($token, HttpUtils::getHeader('X-XSRF-TOKEN'))) {
		throw new HttpException(419, 'The page expired, please try again.');
	}
}
```

The cookie and header names can be changed with the `http` option of `createInertiaApp()`.

### Long-running workers

The adapter keeps no request data in static properties beyond the request: `InertiaMiddleware` forgets the state at the end of every request, including the requests it stops for a version change. State set before `Router::run()` (a share in the front controller) belongs to the request being handled. `Inertia::resetRequest()` clears it explicitly.

## Performance

The adapter adds a few microseconds to a request and never parses what it does not send:

- Closures and prop types that a response does not send are never called: optional and deferred props on full visits, unselected props on partial reloads, once props the client already holds.
- Plain data is traversed once, arrays are never copied when nothing changes in them, dot paths are only built where a prop type needs one, and the page object is encoded once, by `json_encode()`.
- The version check runs before the routing, the shared props are only resolved for pages, the SSR server is only called for full page loads, and the session is only started to write.
- No dependency beyond KnRoute, no reflection, no container.

`composer bench` on PHP 8.5 with OPcache, compared with `json_encode()` of the same plain data:

| Case | Adapter | `json_encode()` alone |
| --- | --- | --- |
| Typical page, 50 rows, lazy and deferred props | 34 µs | 17 µs |
| Partial reload of 1 prop next to 10 000 lazy rows | 5 µs | 0.1 µs |
| Infinite scroll page, 20 rows | 17 µs | 7 µs |
| 10 000 rows of nested arrays | 6.1 ms | 3.5 ms |

The difference on very large data comes from the traversal looking for nested prop types. Data wrapped in a `JsonSerializable` object, or given as objects, is left to `json_encode()` without traversal.

## Sample application

The `samples` folder contains a small application with Vue 3: shared props, deferred, optional and once props, partial reloads, forms with Precognition and validation errors, flash data, `DELETE` redirects, infinite scroll, history encryption, error pages, external redirects and SSR.

```shell
composer install
cd samples
pnpm install
pnpm build                     # client and SSR bundles
pnpm serve                     # http://localhost:8000
```

For SSR, run `pnpm ssr` and start PHP with `INERTIA_SSR=1`. For development with hot reload, run `pnpm dev` and start PHP with `VITE_DEV_SERVER=http://localhost:5173` (and `INERTIA_SSR=1` to render with the Vite plugin).

## Formatting and linting

The JavaScript, Vue, CSS and JSON files are formatted and linted with [Biome](https://biomejs.dev), configured by `biome.json`. PHP is not supported by Biome. From the root of the repository:

```shell
pnpm install
pnpm check                     # formatting, lint and import sorting
pnpm fix                       # apply the formatting and the safe fixes
```

## Tests

The test suite only requires PHP and Composer: no PHPUnit, no external server.

```shell
composer install
composer test
```

Every test file runs in its own PHP process. The suite covers the props resolution (full visits, partial reloads, nested props, metadata), the HTML and JSON responses, the root template escaping, SSR through a real HTTP server and its failures, the session flow of flash data and errors, redirects, Precognition, error pages and complete requests through the KnRoute router with a reused router, as in a worker.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

See [LICENSE.txt](LICENSE.txt).

```
Copyright © 2026 Florent VIALATTE (github.com/Karewan/KnRoute-Inertia)

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```
