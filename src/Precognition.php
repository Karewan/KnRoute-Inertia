<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

use Karewan\KnRoute\HttpUtils;

/**
 * Precognition: live validation of a form (useForm().withPrecognition(), <Form> validation) by the action
 * that handles it, without running the action. Inertia::validate() uses it.
 */
final class Precognition
{
	/** Whether the request only validates the data. */
	public static function isRequest(): bool
	{
		return HttpUtils::getHeader(Header::PRECOGNITION) === 'true';
	}

	/**
	 * The fields to validate, null for every field.
	 * @return list<string>|null
	 */
	public static function validateOnly(): ?array
	{
		$header = HttpUtils::getHeader(Header::PRECOGNITION_VALIDATE_ONLY);
		if ($header === '') return null;

		$fields = [];
		foreach (explode(',', $header) as $field) {
			$field = trim($field);
			if ($field !== '') $fields[] = $field;
		}
		return $fields ?: null;
	}

	/**
	 * Keep the errors of the fields the request validates.
	 * @param array<string,string|list<string>> $errors
	 * @return array<string,string|list<string>>
	 */
	public static function filter(array $errors): array
	{
		$fields = self::validateOnly();
		return $fields === null ? $errors : array_intersect_key($errors, array_flip($fields));
	}

	/** Answer that the data is valid. */
	public static function pass(): void
	{
		header(Header::PRECOGNITION . ': true', true, 204);
		header(Header::PRECOGNITION_SUCCESS . ': true');
		header('Vary: ' . Header::PRECOGNITION, false);
	}

	/**
	 * Answer with the validation errors.
	 * @param array<string,string|list<string>> $errors messages by field
	 */
	public static function fail(array $errors, string $message = 'The given data was invalid.'): void
	{
		$json = json_encode(['message' => $message, 'errors' => $errors ?: new \stdClass()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		header('Content-Type: application/json', true, 422);
		header(Header::PRECOGNITION . ': true');
		header('Vary: ' . Header::PRECOGNITION, false);
		echo $json;
	}
}
