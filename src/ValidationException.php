<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

use Karewan\KnRoute\Exceptions\HttpException;

/**
 * Validation failure: a 422 error carrying the errors by field in its "errors" extension.
 *
 * ErrorHandler sends them back to the form (redirect to the previous page with the errors), and answers
 * the JSON clients such as useHttp() with the 422 error and its "errors" member.
 */
final class ValidationException extends HttpException
{
	/**
	 * @param array<string,string|list<string>> $errors messages by field, one message or a list
	 * @param string $bag named bag, for a page with several forms
	 */
	public function __construct(
		private readonly array $errors,
		private readonly string $bag = 'default',
		string $message = 'The given data was invalid.',
	) {
		parent::__construct(422, $message, extensions: ['errors' => $errors, 'errorBag' => $bag]);
	}

	/** @return array<string,string|list<string>> */
	public function getErrors(): array
	{
		return $this->errors;
	}

	public function getBag(): string
	{
		return $this->bag;
	}
}
