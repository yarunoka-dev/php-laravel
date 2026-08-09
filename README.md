# yarunoka/laravel

[![CI](https://github.com/yarunoka-dev/php-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/yarunoka-dev/php-laravel/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/yarunoka/laravel)](https://packagist.org/packages/yarunoka/laravel)
[![PHP Version](https://img.shields.io/packagist/php-v/yarunoka/laravel)](https://packagist.org/packages/yarunoka/laravel)
[![License](https://img.shields.io/packagist/l/yarunoka/laravel)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/yarunoka/laravel)](https://packagist.org/packages/yarunoka/laravel)

Laravel integration for the Yarunoka schedule definition DSL.

## What is this?

Yarunoka is a small JSON DSL — **Yrnk** — that states calendar rules like
"payday is the 25th, moved up to the previous business day" as data, plus
a pure engine that answers questions about them. The DSL and the engine
live in [yarunoka/core](https://github.com/yarunoka-dev/php-core); the
language-independent specification lives in the
[spec repository](https://github.com/yarunoka-dev/spec/tree/1.0).

This package binds that engine into Laravel:

- **A service provider** builds the evaluation environment (timezone,
  calendar, resolvers) from `config/yarunoka.php` and binds
  `YrnkEvaluator` / `YrnkParser` into the container — scoped per request,
  and yielding to any binding the application makes itself.
- **Eloquent casts** store schedules in JSON columns with validation on
  both paths: a schedules-part column comes back as a wrapper with the
  firing decision (`isDue`), a whole-document column as a bare `Yrnk`.
- **Validation rules** (`ValidYrnk`, `ValidYrnkSchedules`) reject a bad
  request with the engine's own message on the validation error.
- **Container-made resolvers**: a name in the config maps to a class the
  Laravel container instantiates on first use, so constructor injection
  works; binding one of the core's layer interfaces wins over the config.

> [!WARNING]
> The 0.x releases exist to exercise the release pipeline. They are
> **not intended for use**. This notice will be removed at 1.0.0.

## Installation

```console
composer require yarunoka/laravel
```

Requires PHP 8.4 or newer and Laravel 13. The service provider is
registered by package auto-discovery. To publish the config:

```console
php artisan vendor:publish --tag=yarunoka-config
```

## Quick example

Name the wall-clock timezone and the calendar once, in the config:

```php
// config/yarunoka.php
return [
    'timezone' => 'Asia/Tokyo',
    'calendar' => [
        'holidays' => 'yasumi-Japan', // resolved automatically when azuyalabs/yasumi is installed
        'business_holidays' => ['2026-08-14'],
        'business_days' => [],
    ],
    'resolvers' => [],
];
```

Cast a JSON column to a schedules part by naming the wrapper in `casts()`:

```php
use Yarunoka\Laravel\Schedule;

class Routine extends Model
{
    protected function casts(): array
    {
        return ['schedule' => Schedule::class];
    }
}
```

Validate a request and store the schedule as it was spelled — an
invalid schedules part never reaches the database:

```php
use Yarunoka\Laravel\Rules\ValidYrnkSchedules;

$validated = $request->validate([
    'schedule' => ['required', new ValidYrnkSchedules()],
]);

$routine = Routine::create(['schedule' => $validated['schedule']]);
```

Ask the firing question from a poller — was there a scheduled point since
the last run?

```php
if ($routine->schedule->isDue(now(), since: $routine->last_run_at)) {
    // fire, then advance last_run_at
}
```

## Documentation

- [yarunoka/core](https://github.com/yarunoka-dev/php-core) — the DSL and
  the engine this package wraps, with guides on reading, writing, and
  evaluating documents
- [The spec repository](https://github.com/yarunoka-dev/spec/tree/1.0) —
  the DSL specification

## License

[MIT](LICENSE)
