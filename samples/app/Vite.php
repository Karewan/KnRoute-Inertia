<?php

declare(strict_types=1);

namespace Samples;

/** Script and style tags of the Vite entry: the development server, or the built files of the manifest. */
final class Vite
{
	/** @var array<string,array<string,mixed>>|null */
	private static ?array $manifest = null;

	private static string $buildDir = '';

	private static ?string $devServer = null;

	public static function configure(string $buildDir, ?string $devServer): void
	{
		self::$buildDir = rtrim($buildDir, '/');
		self::$devServer = $devServer === null ? null : rtrim($devServer, '/');
	}

	/** Asset version of Inertia: changes with every build. */
	public static function version(): string
	{
		return self::$devServer !== null ? 'dev' : (string) hash_file('xxh128', self::manifestFile());
	}

	public static function tags(string $entry): string
	{
		if (self::$devServer !== null) {
			return '<script type="module" src="' . self::$devServer . '/@vite/client"></script>'
				. '<script type="module" src="' . self::$devServer . "/{$entry}\"></script>";
		}

		$manifest = self::manifest();
		$chunk = $manifest[$entry] ?? throw new \RuntimeException("Unknown Vite entry {$entry}");
		$tags = '';
		foreach ($chunk['css'] ?? [] as $css) $tags .= "<link rel=\"stylesheet\" href=\"/build/{$css}\">";
		foreach ($chunk['imports'] ?? [] as $import) $tags .= "<link rel=\"modulepreload\" href=\"/build/{$manifest[$import]['file']}\">";
		return $tags . "<script type=\"module\" src=\"/build/{$chunk['file']}\"></script>";
	}

	/** @return array<string,array<string,mixed>> */
	private static function manifest(): array
	{
		return self::$manifest ??= json_decode((string) file_get_contents(self::manifestFile()), true, 512, JSON_THROW_ON_ERROR);
	}

	private static function manifestFile(): string
	{
		return self::$buildDir . '/.vite/manifest.json';
	}
}
