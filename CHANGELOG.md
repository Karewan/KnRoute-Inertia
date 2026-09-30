v1.0.0 (2026-09-30)
----------------------------
First release: Inertia.js v3 server-side adapter for KnRoute 4.1+.

### Added
* **Pages:** `Inertia::render()` answers an Inertia visit with the page object as JSON and a full page load with the root template, a PHP template receiving a `RootView` or a Closure. `RootView::head()` and `RootView::app()` print the SSR markup or the page object script, escaped so no prop value can close the script element. Components may be enum cases. `Inertia::page()` builds the page object without sending it.
* **Props:** Closures are resolved only when sent. `Inertia::always()`, `optional()`, `defer()` (groups, `rescue`), `merge()` and `deepMerge()` (append and prepend at the root or at nested paths, item matching), `once()` (custom keys, expiration, forced refresh) and `scroll()` (infinite scroll cursor, scroll direction) follow the v3 protocol, with their page object metadata. Prop types are resolved at any depth, inside arrays and inside Closure results, with dot-notation paths; top-level dot keys are nested. `ProvidesInertiaProperty` and `ProvidesInertiaProperties` let objects provide props.
* **Partial reloads:** `only` and `except` lists with dot notation, always props, reset props and once props already held by the client.
* **Shared data:** `share` Closure of the configuration, called only for pages, `Inertia::share()`, `shareOnce()` and `getShared()`, listed in `sharedProps` for the instant visits.
* **Redirects:** `Inertia::redirect()`, `back()` (Referer of the current host only) and `location()`, with `303` after `PUT`, `PATCH` and `DELETE`, `409` with `X-Inertia-Redirect` for a URL with a fragment and `409` with `X-Inertia-Location` for full page visits. `InertiaMiddleware` applies the same rules to the redirects written with `HttpUtils`.
* **Flash data and validation errors:** `Inertia::flash()`, `withErrors()` with error bags, `validate()`, `ValidationException` and `input()`, kept in the session across redirects until a page sends them, through `NativeSessionStore` or any `SessionStore`.
* **Precognition:** validation-only requests answered by the action with `204` or `422`, without running it.
* **Errors:** `ErrorHandler` renders the KnRoute HTTP errors as an Inertia page for visits and page loads, a JSON error for the other clients, and sends the validation errors back to the form.
* **Asset versioning:** outdated Inertia visits get a `409` reload before the routing, so their action never runs.
* **History encryption:** `Inertia::encryptHistory()`, `clearHistory()`, configuration default and `#[EncryptHistory]` route middleware.
* **Server-side rendering:** `HttpSsrGateway` for the Inertia SSR server and the endpoint of the `@inertiajs/vite` plugin, with a timeout, a health check and classified `SsrException` failures reported before falling back to client rendering. `Inertia::disableSsr()` and `#[WithoutSsr]` render a request on the client only.
* **Long-running workers:** the request state is forgotten at the end of every request.
* **Tests, benchmarks and sample:** a test suite without PHPUnit, `composer bench`, and a Vue 3 sample application.
