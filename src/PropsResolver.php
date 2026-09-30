<?php

declare(strict_types=1);

namespace Karewan\KnRouteInertia;

use Closure;
use Karewan\KnRoute\HttpUtils;
use Karewan\KnRouteInertia\Props\AlwaysProp;
use Karewan\KnRouteInertia\Props\Deferrable;
use Karewan\KnRouteInertia\Props\IgnoreFirstLoad;
use Karewan\KnRouteInertia\Props\Mergeable;
use Karewan\KnRouteInertia\Props\Onceable;
use Karewan\KnRouteInertia\Props\Prop;
use Karewan\KnRouteInertia\Props\Rescuable;
use Karewan\KnRouteInertia\Props\ScrollProp;
use stdClass;
use Throwable;

/**
 * Resolves the props of one page and collects the metadata of the page object.
 *
 * Plain values are the hot path: they are traversed once, arrays are only copied when one of their
 * values changes and paths are only built where a prop type or a Closure needs one.
 * @internal
 */
final class PropsResolver
{
	/** Marks a prop left out of the response: excluded from a full visit or rescued. */
	private static ?stdClass $omitted = null;

	private readonly bool $isPartial;

	/** Partial reload filtering by the only and except lists. */
	private readonly bool $filtering;

	/** @var list<string>|null */
	private readonly ?array $only;

	/** @var list<string>|null */
	private readonly ?array $except;

	/** @var array<string,true> */
	private readonly array $reset;

	/** @var array<string,true> */
	private readonly array $exceptOnce;

	private readonly bool $prependScroll;

	/** @var array<string,true> */
	private array $sharedKeys = [];

	/** @var array<string,list<string>> */
	private array $deferredProps = [];

	/** @var list<string> */
	private array $rescuedProps = [];

	/** @var list<string> */
	private array $mergeProps = [];

	/** @var list<string> */
	private array $prependProps = [];

	/** @var list<string> */
	private array $deepMergeProps = [];

	/** @var list<string> */
	private array $matchPropsOn = [];

	/** @var array<string,array<string,mixed>> */
	private array $scrollProps = [];

	/** @var array<string,array{prop:string,expiresAt:?int}> */
	private array $onceProps = [];

	/**
	 * @param Closure(Throwable):void $reporter reports the failures of rescued props
	 */
	public function __construct(
		private readonly string $component,
		private readonly bool $isInertia,
		private readonly Closure $reporter,
	) {
		self::$omitted ??= new stdClass();

		$this->isPartial = HttpUtils::getHeader(Header::PARTIAL_COMPONENT) === $component;
		if ($this->isPartial) {
			$this->only = self::parseList(HttpUtils::getHeader(Header::PARTIAL_ONLY));
			$this->except = self::parseList(HttpUtils::getHeader(Header::PARTIAL_EXCEPT));
		} else {
			$this->only = $this->except = null;
		}
		$this->filtering = $this->only !== null || $this->except !== null;
		$this->reset = array_fill_keys(self::parseList(HttpUtils::getHeader(Header::RESET)) ?? [], true);
		$this->exceptOnce = $isInertia
			? array_fill_keys(self::parseList(HttpUtils::getHeader(Header::EXCEPT_ONCE_PROPS)) ?? [], true)
			: [];
		$this->prependScroll = HttpUtils::getHeader(Header::INFINITE_SCROLL_MERGE_INTENT) === 'prepend';
	}

	/**
	 * Resolve the shared props and the page props, the latter taking precedence.
	 * @param array<array-key,mixed> $shared
	 * @param array<array-key,mixed> $props
	 * @return array<array-key,mixed>
	 */
	public function resolve(array $shared, array $props): array
	{
		if ($shared) {
			$shared = $this->expandProviders($shared);
			foreach ($shared as $key => $_) {
				$key = (string) $key;
				$dot = strpos($key, '.');
				$this->sharedKeys[$dot === false ? $key : substr($key, 0, $dot)] = true;
			}
			$props = $props ? array_merge($shared, $props) : $shared;
		}

		$props = self::unpackDotKeys($this->expandProviders($props));

		return $this->filtering
			? $this->resolveTree($props, '')
			: $this->resolveData($props, '', null) ?? $props;
	}

	/**
	 * Top-level keys of the shared props, for the instant visits.
	 * @return list<string>
	 */
	public function sharedKeys(): array
	{
		return array_keys($this->sharedKeys);
	}

	/**
	 * Metadata of the page object, only the non-empty members.
	 * @return array<string,mixed>
	 */
	public function metadata(): array
	{
		$metadata = [];
		if ($this->mergeProps) $metadata['mergeProps'] = $this->mergeProps;
		if ($this->prependProps) $metadata['prependProps'] = $this->prependProps;
		if ($this->deepMergeProps) $metadata['deepMergeProps'] = $this->deepMergeProps;
		if ($this->matchPropsOn) $metadata['matchPropsOn'] = $this->matchPropsOn;
		if ($this->deferredProps) $metadata['deferredProps'] = $this->deferredProps;
		if ($this->rescuedProps) $metadata['rescuedProps'] = $this->rescuedProps;
		if ($this->scrollProps) $metadata['scrollProps'] = $this->scrollProps;
		if ($this->onceProps) $metadata['onceProps'] = $this->onceProps;
		return $metadata;
	}

	/**
	 * Resolve a level of a partial reload filtered by the only and except lists. Always props and the
	 * content of a resolved value are never filtered.
	 * @param array<array-key,mixed> $props
	 * @return array<array-key,mixed>
	 */
	private function resolveTree(array $props, string $prefix): array
	{
		$result = [];

		foreach ($props as $key => $value) {
			$path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
			if (!$value instanceof AlwaysProp && !$this->selects($path)) continue;

			if (is_array($value)) {
				if ($value === []) {
					$result[$key] = $value;
				} elseif ($this->filtersBelow($path)) {
					$result[$key] = $this->resolveTree($this->expandProviders($value), $path);
				} else {
					$result[$key] = $this->resolveData($value, $prefix, $key) ?? $value;
				}
				continue;
			}

			if ($value instanceof Closure || $value instanceof Prop || $value instanceof ProvidesInertiaProperty) {
				$value = $this->resolveObject($value, $path, $props);
				if ($value === self::$omitted) continue;
				if (is_array($value) && $value !== []) $value = $this->resolveData($value, $prefix, $key) ?? $value;
			}

			$result[$key] = $value;
		}

		return $result;
	}

	/**
	 * Resolve the Closures and prop types found at any depth of an unfiltered array.
	 * The path of the array is built from its parent path and its key only when it is needed.
	 * @param array<array-key,mixed> $data
	 * @return array<array-key,mixed>|null null when nothing changed, so the array is never copied
	 */
	private function resolveData(array $data, string $parentPath, int|string|null $key): ?array
	{
		$path = null;
		$changed = false;

		foreach ($data as $k => $v) {
			if (\is_array($v)) {
				// Arrays of plain values, the bulk of the data, are scanned here without a call.
				foreach ($v as $item) {
					if (!\is_array($item) && !\is_object($item)) continue;

					$path ??= $key === null ? $parentPath : ($parentPath === '' ? (string) $key : "{$parentPath}.{$key}");
					$resolved = $this->resolveData($v, $path, $k);
					if ($resolved !== null) {
						$data[$k] = $resolved;
						$changed = true;
					}
					break;
				}
				continue;
			}

			if (!\is_object($v)) continue;

			if ($v instanceof ProvidesInertiaProperties) {
				if (!\is_int($k)) continue;
				$expanded = $this->expandProviders($data);
				return $this->resolveData($expanded, $parentPath, $key) ?? $expanded;
			}

			if (!$v instanceof Closure && !$v instanceof Prop && !$v instanceof ProvidesInertiaProperty) continue;

			$path ??= $key === null ? $parentPath : ($parentPath === '' ? (string) $key : "{$parentPath}.{$key}");
			$resolved = $this->resolveObject($v, $path === '' ? (string) $k : "{$path}.{$k}", $data);
			$changed = true;

			if ($resolved === self::$omitted) {
				unset($data[$k]);
				continue;
			}

			if (\is_array($resolved) && $resolved !== []) $resolved = $this->resolveData($resolved, $path, $k) ?? $resolved;
			$data[$k] = $resolved;
		}

		return $changed ? $data : null;
	}

	/**
	 * Resolve a Closure, a prop type or a property provider, and collect its metadata.
	 * @param array<array-key,mixed> $siblings
	 */
	private function resolveObject(object $prop, string $path, array $siblings): mixed
	{
		if ($prop instanceof ScrollProp) $prop->configureMergeIntent($this->prependScroll);
		if (!$this->isPartial && $this->excludedFromFullVisit($prop, $path)) return self::$omitted;

		$value = $this->evaluate($prop, $path, $siblings);

		// A Closure may return a prop type, which then takes part in the filtering and the metadata.
		if ($value instanceof Prop) {
			$prop = $value;
			if ($prop instanceof ScrollProp) $prop->configureMergeIntent($this->prependScroll);
			if (!$this->isPartial && $this->excludedFromFullVisit($prop, $path)) return self::$omitted;
			$value = $this->evaluate($prop, $path, $siblings);
		}

		if ($value === self::$omitted) return $value;

		if ($prop instanceof Prop) $this->collectMetadata($prop, $path);
		return $value;
	}

	/** @param array<array-key,mixed> $siblings */
	private function evaluate(object $prop, string $path, array $siblings): mixed
	{
		try {
			$value = match (true) {
				$prop instanceof Closure => $prop(),
				$prop instanceof Prop => $prop->resolve(),
				default => $prop,
			};

			if ($value instanceof ProvidesInertiaProperty) {
				$value = $value->toInertiaProperty(new PropertyContext($path, $siblings, $this->component));
			}

			return $value;
		} catch (Throwable $e) {
			if (!$prop instanceof Rescuable || !$prop->shouldRescue()) throw $e;

			($this->reporter)($e);
			$this->rescuedProps[] = $path;
			return self::$omitted;
		}
	}

	/**
	 * On a full visit, leave out the optional and deferred props and the once props the client already
	 * holds, while announcing their metadata.
	 */
	private function excludedFromFullVisit(object $prop, string $path): bool
	{
		if ($prop instanceof IgnoreFirstLoad) {
			if ($prop instanceof Deferrable && $prop->shouldDefer() && !$this->isLoadedByClient($prop, $path)) {
				$this->deferredProps[$prop->group()][] = $path;
			}
			if ($prop instanceof Mergeable && $prop->shouldMerge()) $this->collectMerge($prop, $path);
			if ($prop instanceof Onceable && $prop->shouldResolveOnce()) $this->collectOnce($prop, $path);
			return true;
		}

		if ($prop instanceof Deferrable && $prop->shouldDefer()) {
			$this->deferredProps[$prop->group()][] = $path;
			if ($prop instanceof Mergeable && $prop->shouldMerge()) $this->collectMerge($prop, $path);
			return true;
		}

		if ($prop instanceof Onceable && $this->isLoadedByClient($prop, $path)) {
			$this->collectOnce($prop, $path);
			return true;
		}

		return false;
	}

	private function isLoadedByClient(object $prop, string $path): bool
	{
		return $this->exceptOnce
			&& $prop instanceof Onceable
			&& $prop->shouldResolveOnce()
			&& !$prop->shouldBeRefreshed()
			&& isset($this->exceptOnce[$prop->getKey() ?? $path]);
	}

	private function collectMetadata(Prop $prop, string $path): void
	{
		if ($prop instanceof Mergeable && $prop->shouldMerge()) $this->collectMerge($prop, $path);

		if ($prop instanceof ScrollProp) {
			$this->scrollProps[$path] = $prop->metadata() + ['reset' => isset($this->reset[$path])];
		}

		if ($prop instanceof Onceable && $prop->shouldResolveOnce()) $this->collectOnce($prop, $path);
	}

	private function collectMerge(Mergeable $prop, string $path): void
	{
		// A reset prop is replaced by the client, so it carries no merge label.
		if (isset($this->reset[$path])) return;
		if ($this->isPartial && !$this->inPartialMetadata($path)) return;

		if ($prop->shouldDeepMerge()) {
			$this->deepMergeProps[] = $path;
		} elseif ($prop->appendsAtRoot()) {
			$this->mergeProps[] = $path;
		} elseif ($prop->prependsAtRoot()) {
			$this->prependProps[] = $path;
		} else {
			foreach ($prop->appendsAtPaths() as $appendPath) $this->mergeProps[] = "{$path}.{$appendPath}";
			foreach ($prop->prependsAtPaths() as $prependPath) $this->prependProps[] = "{$path}.{$prependPath}";
		}

		foreach ($prop->matchesOn() as $field) $this->matchPropsOn[] = "{$path}.{$field}";
	}

	private function collectOnce(Onceable $prop, string $path): void
	{
		if ($this->isPartial && !$this->inPartialMetadata($path)) return;

		$this->onceProps[$prop->getKey() ?? $path] = ['prop' => $path, 'expiresAt' => $prop->expiresAt()];
	}

	/** Whether the partial reload keeps the path: selected, or leading to a selected path, and not excluded. */
	private function selects(string $path): bool
	{
		if ($this->only !== null && !$this->matchesOnly($path) && !self::hasBelow($this->only, $path)) return false;
		return $this->except === null || !self::matches($this->except, $path);
	}

	/** Whether the children of a kept path still have to be filtered one by one. */
	private function filtersBelow(string $path): bool
	{
		return ($this->only !== null && !$this->matchesOnly($path))
			|| ($this->except !== null && self::hasBelow($this->except, $path));
	}

	/** Whether a prop resolved by a partial reload carries its merge and once metadata. */
	private function inPartialMetadata(string $path): bool
	{
		return ($this->only === null || $this->matchesOnly($path))
			&& ($this->except === null || !self::matches($this->except, $path));
	}

	private function matchesOnly(string $path): bool
	{
		return self::matches($this->only ?? [], $path);
	}

	/**
	 * Whether the path is one of the listed paths or nested under one of them.
	 * @param list<string> $paths
	 */
	private static function matches(array $paths, string $path): bool
	{
		foreach ($paths as $listed) {
			if ($path === $listed || str_starts_with($path, "{$listed}.")) return true;
		}
		return false;
	}

	/**
	 * Whether one of the listed paths is nested under the path.
	 * @param list<string> $paths
	 */
	private static function hasBelow(array $paths, string $path): bool
	{
		$prefix = "{$path}.";
		foreach ($paths as $listed) {
			if (str_starts_with($listed, $prefix)) return true;
		}
		return false;
	}

	/**
	 * Replace the property providers listed without a key by the props they provide.
	 * @param array<array-key,mixed> $props
	 * @return array<array-key,mixed>
	 */
	private function expandProviders(array $props): array
	{
		foreach ($props as $key => $value) {
			if (!\is_int($key) || !$value instanceof ProvidesInertiaProperties) continue;

			$context = new RenderContext($this->component);
			$expanded = [];
			foreach ($props as $k => $v) {
				if (\is_int($k) && $v instanceof ProvidesInertiaProperties) {
					foreach ($v->toInertiaProperties($context) as $providedKey => $provided) $expanded[$providedKey] = $provided;
				} else {
					$expanded[$k] = $v;
				}
			}
			return $expanded;
		}

		return $props;
	}

	/**
	 * Nest the top-level keys written in dot notation ("auth.user") into their parent array.
	 * @param array<array-key,mixed> $props
	 * @return array<array-key,mixed>
	 */
	private static function unpackDotKeys(array $props): array
	{
		foreach ($props as $key => $value) {
			if (!\is_string($key) || !str_contains($key, '.')) continue;

			unset($props[$key]);
			$segments = explode('.', $key);
			$last = array_pop($segments);
			$target = &$props;
			foreach ($segments as $segment) {
				$parent = $target[$segment] ?? null;
				// A lazy parent has to be resolved to receive the nested prop.
				if ($parent instanceof Closure) $parent = $parent();
				$target[$segment] = \is_array($parent) ? $parent : [];
				$target = &$target[$segment];
			}
			$target[$last] = $value;
			unset($target);
		}

		return $props;
	}

	/** @return list<string>|null */
	private static function parseList(string $header): ?array
	{
		if ($header === '') return null;

		$list = [];
		foreach (explode(',', $header) as $item) {
			$item = trim($item);
			if ($item !== '') $list[] = $item;
		}
		return $list ?: null;
	}
}
