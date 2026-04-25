# Changelog

All notable changes to `laravel-data-proxy` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Stability**: this package is pre-1.0 and **not recommended for production
> use** outside the maintainer's own projects. The public API may still change
> between minor releases (per semver, `0.x` minor bumps may include breaking
> changes). A 1.0 release will be cut once the API is considered stable.

## [Unreleased]

## [0.4.0] - 2026-04-25

Breaking-change release. See the **Upgrade guide** at the bottom of this
entry for migration steps.

### Added
- Laravel 13 support.
- Constraint-signature aggregate batching: when multiple aggregates of the
  same model share the same `where` constraints they collapse into one SQL
  `SELECT` even when other aggregates of that model use *different*
  constraints. Previously batching was all-or-nothing and a single divergent
  filter forced every aggregate of that model into its own query.
- Pre-resolution of callable constraint values when computing batching
  signatures, so closures that resolve to equal values share a query.
- `query.merge_shared_eager_loads` config flag (default `false`). When true,
  batched entities that request the same relation with different shapes have
  their constraints, fields, limits and nested relations **unioned** instead
  of the second silently overwriting the first.
- `DataSet` now memoizes its materialized array, so repeated `count()` /
  `all()` / iteration on the same instance is O(1). Generator-backed clones
  produced by `take()` / `skip()` / `pluck()` opt out so they remain
  streaming.
- `PaginatedResult::items()` returns the same `DataSet` instance on repeated
  calls instead of allocating a new one each time.
- Eight new performance-invariant tests guarding the above behaviors against
  future regressions (query-count assertions via `DB::getQueryLog()`,
  single-invocation counters, materialization checks).

### Changed
- **BREAKING**: dropped Laravel 10 support. Minimum required versions are now
  Laravel 11, 12 or 13 with PHP 8.2+ (PHP 8.3+ for Laravel 13).
- **BREAKING**: `Resolver::resolveValue()` no longer treats plain strings or
  arrays as callables. Only `Closure` instances and invokable objects are
  invoked against the resolution state. Previously a constraint like
  `where('title', 'old')` would accidentally call PHP's `old()` helper
  because PHP function names are case-insensitive and `is_callable('Old')`
  returns true. Existing closure-based deferred values continue to work
  unchanged.
- **BREAKING (latent fix)**: `LaravelCacheAdapter::tags()` on a non-default
  store now correctly targets that store. Previously the tag operation went
  to the default cache store regardless of the configured `$store`.
- Callable values passed as entity IDs (`->one('x', Model::class, fn() => …)`,
  `->many(...)`) are now invoked **exactly once** per resolve. They were
  previously invoked twice — once during ID collection and once during
  result distribution. If a closure relied on observable double-execution
  for side effects this is a regression; convert it to a stable value.

### Performance
- Removed redundant recursive walks in entity batching. `extractRelations`
  was called solely to feed an `if (!empty(...))` guard and has been
  replaced with a cheap `!empty($shape->getRelations())` check; the helper
  itself is gone.
- Replaced `array_merge` calls inside hot loops with append-then-dedupe,
  eliminating O(n²) behavior when batching many entities.
- Memoized model-class validation and primary-key lookup. `validateModelClass`
  no longer re-runs `class_exists()` per batch; the primary key is read once
  per model class instead of via `(new $modelClass())->getKeyName()` on
  every batched query.
- `LaravelCacheAdapter` memoizes the resolved cache `Repository` per
  instance, so repeat `get` / `set` / `has` / `forget` calls don't re-walk
  the facade and store manager.
- `LaravelModelPresenterAdapter` now caches **negative** auto-discovery
  results, so models without a presenter no longer re-run `class_exists()`
  on every call.
- `Config::get()` memoizes resolved dot-notation keys; the cache is
  invalidated on `set()` and `merge()`.
- `DataSet` no longer carries a duplicate private iterator method.

### Fixed
- `LaravelCacheAdapter::tags()` ignored the configured non-default store
  (see Changed → BREAKING above).
- Callable IDs in `one()` / `many()` were invoked twice per resolve.
- Plain string constraint values that coincide with a registered helper
  function name (such as Laravel's `old`, `auth`, `request`) were
  inadvertently invoked instead of being treated as literal values.

### Upgrade guide (0.3.x → 0.4.0)

1. **Laravel 10**: bump your app to Laravel 11, 12 or 13.
2. **Deferred values**: if you passed a non-Closure callable (a string
   function name, a `[Class, 'method']` pair) as a constraint value,
   replace it with a Closure: `where('age', '>', fn() => $minAge())`.
3. **Side-effecting ID closures**: closures supplied to `->one(...)` /
   `->many(...)` are now called once instead of twice per resolve. Verify
   any closures with side effects.
4. **Cache tagging on non-default stores**: tagged cache operations now go
   to the configured store. If you depended on the previous (incorrect)
   behavior of always hitting the default store, update your store
   configuration explicitly.
5. **Eager-load merge**: behavior is unchanged by default. Opt in by
   setting `'merge_shared_eager_loads' => true` in the `query` config
   block (or via `DATAPROXY_MERGE_SHARED_EAGER_LOADS=true`) when you want
   batched aliases that request the same relation to receive a unioned
   result instead of last-write-wins.

## [0.3.0] - 2026-03-22

### Added
- Multiple-scopes support on `Shape`. `scope()` now accumulates and
  multiple registered scopes are applied in declaration order. Added
  `getScopes()` and `clearScopes()` helpers.

### Fixed
- `array_unique` over collected IDs uses `SORT_REGULAR` so mixed-type IDs
  (int and string keys referring to the same value) deduplicate correctly.

## [0.2.0] - 2026-03-03

### Added
- `Shape::hydrate()` callback for batch-loading additional data after a
  paginated query executes.

## [0.1.0] - 2026-02-27

Initial release.

### Added
- Declarative `Requirements` builder: `one`, `many`, `query`, `paginate`,
  `count`, `sum`, `avg`, `min`, `max`, `raw`, `compute`, `cache`.
- `Shape` query specification with field selection, nested relations,
  `where*` constraints, ordering, limit/offset, scopes, `present()` and
  `asArray()`.
- `Resolver` with automatic batching of entity lookups by model and
  aggregate batching when constraints match.
- `Result` DTO with magic accessors, ArrayAccess, transform, metrics.
- `DataSet` memory-efficient lazy collection with chunking.
- `PaginatedResult` wrapper around `LengthAwarePaginator`.
- Cache and presenter adapter contracts plus Laravel implementations:
  `LaravelCacheAdapter`, `LaravelModelPresenterAdapter`,
  `ClosurePresenterAdapter`.
- Laravel service provider, facade, and publishable config.
- Built-in metrics (queries, cache hits, batch savings, time, memory).
- Configuration presets: `forApi()`, `forExport()`, `forPerformance()`.
- Integration with `adrosoftware/laravel-model-presenter`.
- PHPStan level 9 compliance.

[Unreleased]: https://github.com/adrorocker/laravel-data-proxy/compare/0.4.0...HEAD
[0.4.0]: https://github.com/adrorocker/laravel-data-proxy/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/adrorocker/laravel-data-proxy/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/adrorocker/laravel-data-proxy/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/adrorocker/laravel-data-proxy/releases/tag/0.1.0
