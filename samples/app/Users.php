<?php

declare(strict_types=1);

namespace Samples;

/** Users of the sample, kept in the session. */
final class Users
{
	/** @return list<array{id:int,name:string,email:string,role:string}> */
	public static function all(string $search = ''): array
	{
		$_SESSION['users'] ??= [
			['id' => 1, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'role' => 'admin'],
			['id' => 2, 'name' => 'Grace Hopper', 'email' => 'grace@example.com', 'role' => 'member'],
			['id' => 3, 'name' => 'Alan Turing', 'email' => 'alan@example.com', 'role' => 'member'],
		];

		if ($search === '') return $_SESSION['users'];
		return array_values(array_filter($_SESSION['users'], static fn(array $user): bool => stripos($user['name'], $search) !== false));
	}

	/**
	 * Validation errors of a new user, by field.
	 * @param array<array-key,mixed> $input
	 * @return array<string,string>
	 */
	public static function validate(array $input): array
	{
		$errors = [];
		$name = trim((string) ($input['name'] ?? ''));
		$email = trim((string) ($input['email'] ?? ''));

		if ($name === '') $errors['name'] = 'The name is required.';
		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			$errors['email'] = 'The email address is invalid.';
		} elseif (in_array(strtolower($email), array_map(static fn(array $user): string => strtolower($user['email']), self::all()), true)) {
			$errors['email'] = 'This email address is already taken.';
		}

		return $errors;
	}

	public static function add(string $name, string $email): int
	{
		$users = self::all();
		$id = max(array_column($users, 'id') ?: [0]) + 1;
		$_SESSION['users'][] = ['id' => $id, 'name' => $name, 'email' => $email, 'role' => 'member'];
		return $id;
	}

	public static function delete(int $id): bool
	{
		$count = count(self::all());
		$_SESSION['users'] = array_values(array_filter($_SESSION['users'], static fn(array $user): bool => $user['id'] !== $id));
		return count($_SESSION['users']) !== $count;
	}
}
