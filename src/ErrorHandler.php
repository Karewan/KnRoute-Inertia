<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

use Closure;
use Karewan\KnRoute\HttpError;
use Karewan\KnRoute\HttpUtils;

/**
 * Error handler of the router for an Inertia application:
 * $router->setDefaultErrorHandler(new ErrorHandler('Error'));
 *
 * - A ValidationException sends its errors back to the form: redirect to the previous page with the errors,
 *   or 422 answer of a Precognition request.
 * - An Inertia visit or a full page load gets the error page component, with the error status.
 * - Any other request (useHttp(), API client...) gets the fallback, a JSON error by default.
 */
final class ErrorHandler
{
	private readonly Closure $fallback;

	/**
	 * @param string|null $component page component of the errors, null to never render an error page
	 * @param list<int>|null $pageStatuses statuses rendered with the component, null for every status
	 * @param Closure(HttpError):array<string,mixed>|null $props props of the error page, status, title and
	 * detail by default
	 * @param Closure(HttpError):void|null $fallback renders the other errors, HttpUtils::outputError() by default
	 */
	public function __construct(
		private readonly ?string $component = 'Error',
		private readonly ?array $pageStatuses = null,
		private readonly ?Closure $props = null,
		?Closure $fallback = null,
	) {
		$this->fallback = $fallback ?? static function (HttpError $error): void {
			HttpUtils::outputError($error->code, $error->title, $error->detail, $error->extensions);
		};
	}

	public function __invoke(HttpError $error): void
	{
		$this->render($error);
	}

	public function render(HttpError $error): void
	{
		if (self::handleValidation($error)) return;

		if (
			$this->component !== null
			&& ($this->pageStatuses === null || \in_array($error->code, $this->pageStatuses, true))
			&& self::wantsPage()
		) {
			$props = $this->props !== null
				? ($this->props)($error)
				: ['status' => $error->code, 'title' => $error->title, 'detail' => $error->detail];
			Inertia::render($this->component, $props, status: $error->code);
			return;
		}

		($this->fallback)($error);
	}

	/**
	 * Send the errors of a ValidationException back to the form, for an error handler of the application.
	 * @return bool whether the error was a validation error handled here
	 */
	public static function handleValidation(HttpError $error): bool
	{
		$errors = $error->extensions['errors'] ?? null;
		if ($error->code !== 422 || !\is_array($errors) || !$errors) return false;

		if (Precognition::isRequest()) {
			$errors = Precognition::filter($errors);
			$errors ? Precognition::fail($errors, $error->detail) : Precognition::pass();
			return true;
		}

		// A JSON client reads the errors of the 422 answer.
		if (!Inertia::isInertiaRequest() && HttpUtils::isXmlHttpRequest()) return false;

		$bag = $error->extensions['errorBag'] ?? null;
		Inertia::withErrors($errors, \is_string($bag) ? $bag : 'default');
		Inertia::back();
		return true;
	}

	/** Whether the request expects a page: an Inertia visit, or a full page load of a browser. */
	public static function wantsPage(): bool
	{
		if (Inertia::isInertiaRequest()) return true;
		if (HttpUtils::isXmlHttpRequest()) return false;
		return str_contains(HttpUtils::getHeader('Accept'), 'text/html');
	}
}
