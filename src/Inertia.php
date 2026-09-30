<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

use BackedEnum;
use Closure;
use Karewan\KnRoute\Exceptions\StopRequestException;
use Karewan\KnRoute\HttpUtils;
use Karewan\KnRouteInertia\Props\AlwaysProp;
use Karewan\KnRouteInertia\Props\DeferProp;
use Karewan\KnRouteInertia\Props\MergeProp;
use Karewan\KnRouteInertia\Props\OnceProp;
use Karewan\KnRouteInertia\Props\OptionalProp;
use Karewan\KnRouteInertia\Props\ScrollMetadata;
use Karewan\KnRouteInertia\Props\ScrollProp;
use Karewan\KnRouteInertia\Ssr\SsrGateway;
use LogicException;
use stdClass;
use Throwable;
use UnitEnum;

/**
 * Inertia.js v3 server-side adapter for KnRoute.
 *
 * The configuration is set once per process with configure(). The request state (shared props, flash
 * data, errors, history flags) lives until InertiaMiddleware ends the request, which keeps the adapter safe
 * in long-running workers.
 */
final class Inertia
{
	/** Session key of the data kept for the next page. */
	public const string SESSION_KEY = '_inertia';

	private static ?Config $config = null;

	private static ?RequestState $state = null;

	/**
	 * Configure the adapter, once per process.
	 * @param string|Closure(RootView):string $rootView root template of the full page loads: the path of a PHP
	 * template receiving $inertia (RootView), $page and the view data, or a Closure returning the document
	 * @param string|Closure():(string|null)|null $version asset version: a full page load replaces the client
	 * when it changes. A Closure is called at most once per request, only when the version is needed.
	 * @param Closure():array<array-key,mixed>|null $share props shared by every page, called when a page is rendered
	 * @param SessionStore|false|null $session keeps the data of a redirect for the next page, $_SESSION by
	 * default, false without session
	 * @param SsrGateway|null $ssr server-side rendering, disabled when null
	 * @param bool $encryptHistory encrypt the history state of every page by default
	 * @param bool $exposeSharedPropKeys list the shared props in the page object, for the instant visits
	 * @param int $jsonFlags extra json_encode() flags of the page object
	 * @param Closure(Throwable):void|null $reporter reports the rescued deferred props and the SSR failures,
	 * error_log() by default
	 */
	public static function configure(
		string|Closure $rootView,
		string|Closure|null $version = null,
		?Closure $share = null,
		SessionStore|false|null $session = null,
		?SsrGateway $ssr = null,
		bool $encryptHistory = false,
		bool $exposeSharedPropKeys = true,
		int $jsonFlags = 0,
		?Closure $reporter = null,
	): void {
		self::$config = new Config(
			$rootView,
			$version,
			$share,
			$session === false ? null : ($session ?? new NativeSessionStore()),
			$ssr,
			$encryptHistory,
			$exposeSharedPropKeys,
			$jsonFlags,
			$reporter ?? static function (Throwable $e): void {
				error_log((string) $e);
			},
		);
		self::$state = null;
	}

	/**
	 * Forget the state of the request: shared props, pending data, request flags.
	 * InertiaMiddleware does it at the end of every request.
	 */
	public static function resetRequest(): void
	{
		self::$state = null;
	}

	/**
	 * End the request: fix the redirects of Inertia visits, keep the pending data that no page sent for the
	 * next one, then forget the state so a long-running worker starts the next request clean.
	 * Called by InertiaMiddleware.
	 */
	public static function finishRequest(): void
	{
		$status = http_response_code();
		if (\is_int($status) && $status >= 301 && $status <= 308 && !headers_sent() && self::isInertiaRequest()) {
			self::fixRedirect($status);
		}

		if (self::$state?->hasPending()) self::persist(self::$state);
		self::$state = null;
	}

	// Rendering

	/**
	 * Render a page: the page object for an Inertia visit, the root template for a full page load.
	 * @param string|UnitEnum $component the page component, or an enum case naming it
	 * @param array<array-key,mixed>|ProvidesInertiaProperties $props
	 * @param array<string,mixed> $viewData data of the root template, never sent to the client
	 */
	public static function render(
		string|UnitEnum $component,
		array|ProvidesInertiaProperties $props = [],
		array $viewData = [],
		int $status = 200,
	): void {
		$page = self::page($component, $props);
		$config = self::config();

		if (self::isInertiaRequest()) {
			$json = json_encode($page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | $config->jsonFlags);
			header('Content-Type: application/json', true, $status);
			header(Header::INERTIA . ': true');
			header('Vary: ' . Header::INERTIA, false);
			echo $json;
			return;
		}

		$view = new RootView($page, $viewData, $config->jsonFlags, self::state()->ssrDisabled ? null : $config->ssr, $config->reporter);
		$html = $config->rootView instanceof Closure ? ($config->rootView)($view) : self::renderTemplate($config->rootView, $view);
		header('Content-Type: text/html; charset=utf-8', true, $status);
		header('Vary: ' . Header::INERTIA, false);
		echo $html;
	}

	/**
	 * Build the page object render() sends, and consume the pending data it carries.
	 * @param string|UnitEnum $component the page component, or an enum case naming it
	 * @param array<array-key,mixed>|ProvidesInertiaProperties $props
	 * @return array<string,mixed>
	 */
	public static function page(string|UnitEnum $component, array|ProvidesInertiaProperties $props = []): array
	{
		$config = self::config();
		$state = self::state();
		$component = match (true) {
			$component instanceof BackedEnum => (string) $component->value,
			$component instanceof UnitEnum => $component->name,
			default => $component,
		};

		$resolver = new PropsResolver($component, self::isInertiaRequest(), $config->reporter);
		$props = $resolver->resolve(self::sharedProps(), $props instanceof ProvidesInertiaProperties ? [$props] : $props);

		$stored = $config->session?->pull(self::SESSION_KEY);
		if (!\is_array($stored)) $stored = [];

		// Validation errors are an always prop: present on every response, partial reloads included.
		if (!\array_key_exists('errors', $props)) $props['errors'] = self::errors($stored, $state->errors);

		$page = [
			'component' => $component,
			'props' => $props,
			'url' => self::requestUrl(),
			'version' => self::getVersion(),
		];

		if ($config->exposeSharedPropKeys) $page['sharedProps'] = ['errors', ...array_diff($resolver->sharedKeys(), ['errors'])];
		$page += $resolver->metadata();

		if ($state->clearHistory || !empty($stored['clearHistory'])) $page['clearHistory'] = true;
		if ($state->encryptHistory ?? $config->encryptHistory) $page['encryptHistory'] = true;
		$flash = \is_array($stored['flash'] ?? null) ? array_replace($stored['flash'], $state->flash) : $state->flash;
		if ($flash) $page['flash'] = $flash;
		if ($state->preserveFragment || !empty($stored['preserveFragment'])) $page['preserveFragment'] = true;

		$state->clearPending();
		return $page;
	}

	// Redirects

	/**
	 * Redirect after an action. The pending data is kept for the next page, a PUT, PATCH or DELETE request
	 * is redirected with 303 so the browser follows with a GET, and an Inertia visit to a URL with a fragment
	 * is redirected with a 409 so the client keeps the fragment.
	 */
	public static function redirect(string $url, ?int $status = null): void
	{
		self::persist(self::state());

		if (str_contains($url, '#') && self::isInertiaRequest() && !self::isPrefetch()) {
			header(Header::REDIRECT . ": {$url}", true, 409);
			return;
		}

		header("Location: {$url}", true, $status ?? self::redirectStatus());
	}

	/**
	 * Redirect to the previous page (the Referer of this host), or to the fallback.
	 */
	public static function back(string $fallback = '/', ?int $status = null): void
	{
		self::redirect(self::previousUrl() ?? $fallback, $status);
	}

	/**
	 * Full page visit, to an external site or a page outside the Inertia application.
	 */
	public static function location(string $url): void
	{
		self::persist(self::state());

		if (self::isInertiaRequest()) {
			header_remove('Location');
			header(Header::LOCATION . ": {$url}", true, 409);
			return;
		}

		header("Location: {$url}", true, self::redirectStatus());
	}

	/**
	 * The Referer when it is a page of this host, as a relative URL: never an open redirect.
	 */
	public static function previousUrl(): ?string
	{
		$referer = HttpUtils::getReferer();
		if ($referer === '') return null;

		$parts = parse_url($referer);
		if (!\is_array($parts) || !isset($parts['host']) || strcasecmp($parts['host'], HttpUtils::getHost()) !== 0) return null;

		$url = '/' . ltrim($parts['path'] ?? '', '/');
		return isset($parts['query']) ? "{$url}?{$parts['query']}" : $url;
	}

	// Request state

	/**
	 * Share props with every page of the request.
	 * @param string|array<array-key,mixed>|ProvidesInertiaProperties $key a prop name (dot notation nests it),
	 * several props, or a property provider
	 */
	public static function share(string|array|ProvidesInertiaProperties $key, mixed $value = null): void
	{
		$state = self::state();

		if (\is_array($key)) {
			$state->shared = array_merge($state->shared, $key);
		} elseif ($key instanceof ProvidesInertiaProperties) {
			$state->shared[] = $key;
		} else {
			$state->shared[$key] = $value;
		}
	}

	/**
	 * A shared prop, dot notation allowed, or every shared prop without key. Unresolved: Closures and prop
	 * types are returned as is.
	 */
	public static function getShared(?string $key = null, mixed $default = null): mixed
	{
		$shared = self::sharedProps();
		if ($key === null) return $shared;
		if (\array_key_exists($key, $shared)) return $shared[$key];

		foreach (explode('.', $key) as $segment) {
			if (!\is_array($shared) || !\array_key_exists($segment, $shared)) return $default;
			$shared = $shared[$segment];
		}
		return $shared;
	}

	/**
	 * Flash data sent once with the next page: never kept in the browser history.
	 * @param string|UnitEnum|array<string,mixed> $key
	 */
	public static function flash(string|UnitEnum|array $key, mixed $value = null): void
	{
		$state = self::state();

		if (\is_array($key)) {
			$state->flash = array_replace($state->flash, $key);
			return;
		}

		$key = match (true) {
			$key instanceof BackedEnum => (string) $key->value,
			$key instanceof UnitEnum => $key->name,
			default => $key,
		};
		$state->flash[$key] = $value;
	}

	/**
	 * Validation errors of the next page: this page when it is rendered, or the page redirected to.
	 * @param array<string,string|list<string>> $errors messages by field, one message or a list
	 * @param string $bag named bag, for a page with several forms
	 */
	public static function withErrors(array $errors, string $bag = 'default'): void
	{
		if (!$errors) return;

		$state = self::state();
		$state->errors[$bag] = isset($state->errors[$bag]) ? array_replace($state->errors[$bag], $errors) : $errors;
	}

	/**
	 * End the request when it is a Precognition request, answered with the validation result, or when the
	 * validation failed: the errors go back to the form through the ErrorHandler.
	 * @param array<string,string|list<string>> $errors messages by field, empty when the data is valid
	 * @throws ValidationException|StopRequestException
	 */
	public static function validate(array $errors, string $bag = 'default'): void
	{
		if (Precognition::isRequest()) {
			$errors = Precognition::filter($errors);
			$errors ? Precognition::fail($errors) : Precognition::pass();
			throw new StopRequestException();
		}

		if ($errors) throw new ValidationException($errors, $bag);
	}

	/** Encrypt the history state of this page, or not, whatever the configuration. */
	public static function encryptHistory(bool $encrypt = true): void
	{
		self::state()->encryptHistory = $encrypt;
	}

	/** Make the encrypted history unreadable, e.g. on logout. Applies to the next page. */
	public static function clearHistory(): void
	{
		self::state()->clearHistory = true;
	}

	/** Keep the fragment of the visited URL across the redirect. */
	public static function preserveFragment(): void
	{
		self::state()->preserveFragment = true;
	}

	/** Render this request on the client only. */
	public static function disableSsr(bool $disable = true): void
	{
		self::state()->ssrDisabled = $disable;
	}

	/**
	 * The data sent by the client: the JSON body of a form without files (Inertia sends JSON by
	 * default), otherwise the form fields of $_POST.
	 * @return array<array-key,mixed>
	 */
	public static function input(): array
	{
		if (!str_contains(HttpUtils::getContentType(), 'json')) return $_POST;

		$data = HttpUtils::getJsonBody(true);
		return \is_array($data) ? $data : [];
	}

	/** Whether the request is an Inertia visit, answered with a page object. */
	public static function isInertiaRequest(): bool
	{
		$state = self::state();
		return $state->inertia ??= HttpUtils::getHeader(Header::INERTIA) !== '';
	}

	/** Whether the request prefetches a page. */
	public static function isPrefetch(): bool
	{
		return strcasecmp(HttpUtils::getHeader('Purpose'), 'prefetch') === 0
			|| strcasecmp(HttpUtils::getHeader('Sec-Purpose'), 'prefetch') === 0
			|| strcasecmp(HttpUtils::getHeader('X-Moz'), 'prefetch') === 0;
	}

	/** The current asset version. */
	public static function getVersion(): string
	{
		$state = self::state();
		if ($state->version === null) {
			$version = self::config()->version;
			$state->version = (string) ($version instanceof Closure ? $version() : $version);
		}
		return $state->version;
	}

	/**
	 * Answer an Inertia GET visit made with outdated assets with a full page load of the same URL.
	 * Called by InertiaMiddleware before the routing, so the action never runs for nothing.
	 * @return bool whether the response was produced
	 */
	public static function handleVersionChange(): bool
	{
		if (HttpUtils::getMethod() !== 'GET' || !self::isInertiaRequest()) return false;

		$version = self::getVersion();
		if (HttpUtils::getHeader(Header::VERSION) === $version) return false;

		// The pending data of the session stays for the page loaded next.
		header(Header::LOCATION . ': ' . self::requestUrl(), true, 409);
		header(Header::VERSION . ": {$version}");
		return true;
	}

	// Props

	/** Sent on every response, partial reloads included. */
	public static function always(mixed $value): AlwaysProp
	{
		return new AlwaysProp($value);
	}

	/** Never sent on a full visit, only when a partial reload selects it. */
	public static function optional(callable $callback): OptionalProp
	{
		return new OptionalProp($callback(...));
	}

	/**
	 * Announced on a full visit, then loaded by the client in a follow-up request.
	 * @param string $group the props of a group are loaded together, the groups in parallel
	 * @param bool $rescue report a failure and omit the prop instead of failing the response
	 */
	public static function defer(callable $callback, string $group = 'default', bool $rescue = false): DeferProp
	{
		return new DeferProp($callback(...), $group, $rescue);
	}

	/** Appended by the client to the value it holds, on partial reloads. */
	public static function merge(mixed $value): MergeProp
	{
		return new MergeProp($value);
	}

	/** Deep merged by the client with the value it holds, on partial reloads. */
	public static function deepMerge(mixed $value): MergeProp
	{
		return (new MergeProp($value))->deepMerge();
	}

	/** Resolved once, then remembered by the client across the pages including it. */
	public static function once(callable $callback): OnceProp
	{
		return new OnceProp($callback(...));
	}

	/**
	 * Share a once prop with every page of the request.
	 */
	public static function shareOnce(string $key, callable $callback): OnceProp
	{
		$prop = new OnceProp($callback(...));
		self::share($key, $prop);
		return $prop;
	}

	/**
	 * Paginated data of an infinite scroll.
	 * @param mixed $value the paginated data, or a Closure producing it
	 * @param ScrollMetadata|Closure(mixed):ScrollMetadata $metadata the pagination cursor, or a Closure deriving it from the data
	 * @param string $wrapper key of the items inside the paginated data
	 */
	public static function scroll(mixed $value, ScrollMetadata|Closure $metadata, string $wrapper = 'data'): ScrollProp
	{
		return new ScrollProp($value, $metadata, $wrapper);
	}

	// Internals

	private static function config(): Config
	{
		return self::$config ?? throw new LogicException('Inertia::configure() must be called before rendering a page');
	}

	private static function state(): RequestState
	{
		return self::$state ??= new RequestState();
	}

	/** @return array<array-key,mixed> */
	private static function sharedProps(): array
	{
		$state = self::state();
		$share = self::config()->share;
		$state->configShared ??= $share === null ? [] : $share();

		if (!$state->shared) return $state->configShared;
		return $state->configShared ? array_merge($state->configShared, $state->shared) : $state->shared;
	}

	/**
	 * The errors of the page: the default bag, or the named bags without default bag. With an error bag
	 * header, the default bag is sent under that name.
	 * @param array<string,mixed> $stored
	 * @param array<string,array<string,mixed>> $pending
	 * @return array<string,mixed>|stdClass
	 */
	private static function errors(array $stored, array $pending): array|stdClass
	{
		$bags = \is_array($stored['errors'] ?? null) ? $stored['errors'] : [];
		foreach ($pending as $bag => $errors) {
			$bags[$bag] = \is_array($bags[$bag] ?? null) ? array_replace($bags[$bag], $errors) : $errors;
		}

		if (!$bags) return new stdClass();
		if (!isset($bags['default'])) return $bags;

		$bag = HttpUtils::getHeader(Header::ERROR_BAG);
		return $bag === '' ? $bags['default'] : [$bag => $bags['default']];
	}

	/** Keep the pending data in the session until the next page. */
	private static function persist(RequestState $state): void
	{
		$session = self::$config?->session;
		if ($session === null || !$state->hasPending()) return;

		// Data kept by an earlier redirect that no page consumed yet is merged, not lost.
		$stored = $session->pull(self::SESSION_KEY);
		if (!\is_array($stored)) $stored = [];

		if ($state->flash) {
			$stored['flash'] = \is_array($stored['flash'] ?? null) ? array_replace($stored['flash'], $state->flash) : $state->flash;
		}
		if ($state->errors && !\is_array($stored['errors'] ?? null)) $stored['errors'] = [];
		foreach ($state->errors as $bag => $errors) {
			$stored['errors'][$bag] = \is_array($stored['errors'][$bag] ?? null) ? array_replace($stored['errors'][$bag], $errors) : $errors;
		}
		if ($state->clearHistory) $stored['clearHistory'] = true;
		if ($state->preserveFragment) $stored['preserveFragment'] = true;

		$session->put(self::SESSION_KEY, $stored);
		$state->clearPending();
	}

	/** Redirect of an Inertia visit produced without the redirect helpers. */
	private static function fixRedirect(int $status): void
	{
		$location = null;
		foreach (headers_list() as $header) {
			if (strncasecmp($header, 'Location:', 9) === 0) $location = ltrim(substr($header, 9));
		}
		if ($location === null) return;

		if (str_contains($location, '#') && !self::isPrefetch()) {
			header_remove('Location');
			header(Header::REDIRECT . ": {$location}", true, 409);
		} elseif ($status === 302 && self::redirectStatus() === 303) {
			http_response_code(303);
		}
	}

	private static function redirectStatus(): int
	{
		return match (HttpUtils::getMethod()) {
			'PUT', 'PATCH', 'DELETE' => 303,
			default => 302,
		};
	}

	/** The URL of the page: path and query string of the request. */
	private static function requestUrl(): string
	{
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		if (!\is_string($uri) || $uri === '') return '/';
		// A leading "//" would make the URL protocol-relative on the client.
		if ($uri[0] === '/') return ($uri[1] ?? '') === '/' ? '/' . ltrim($uri, '/') : $uri;

		$parts = parse_url($uri);
		if (!\is_array($parts)) return '/';
		$url = '/' . ltrim($parts['path'] ?? '', '/');
		return isset($parts['query']) ? "{$url}?{$parts['query']}" : $url;
	}

	private static function renderTemplate(string $file, RootView $inertia): string
	{
		$level = ob_get_level();
		ob_start();
		try {
			(static function (string $__file, RootView $inertia, array $__data): void {
				$page = $inertia->page;
				extract($__data, EXTR_SKIP);
				unset($__data);
				require $__file;
			})($file, $inertia, $inertia->data);
			return (string) ob_get_clean();
		} catch (Throwable $e) {
			while (ob_get_level() > $level) ob_end_clean();
			throw $e;
		}
	}
}
