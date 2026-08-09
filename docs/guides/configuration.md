---
title: Configuration
description: The timezone schedules are judged in, the calendar the application defines, and the resolvers it binds.
sidebar:
  order: 3
---

`config/yarunoka.php` describes the **evaluation environment**: the
timezone schedules are judged in, the calendar definitions, and the
resolvers that answer names at runtime. The bridge builds
`YrnkEvaluator` and `YrnkParser` from it and binds them into the
container, scoped per request.

```php
return [
    'timezone' => null,
    'calendar' => [],
    'resolvers' => [],
];
```

## timezone

The timezone schedules are judged in. `null` falls back to
`config('app.timezone')`.

An application that runs `app.timezone` at UTC — a common choice for
storage — should name its wall-clock timezone here, because a schedule
like "the 25th at 10:00" means a wall clock somewhere:

```php
'timezone' => 'Asia/Tokyo',
```

## calendar

The calendar part of a Yrnk document, spelled as a PHP array — the same
shape the DSL accepts under `calendar`, so what the
[specification](https://github.com/yarunoka-dev/spec/tree/1.0) says about
the calendar applies here verbatim.

```php
'calendar' => [
    'holidays' => 'yasumi-Japan',
    'business_holidays' => ['2026-08-14'],
    'business_days' => [],
    'workweek' => ['mon', 'tue', 'wed', 'thu', 'fri'],
    'date_sets' => ['founding-day' => ['2026-10-01']],
],
```

A date-list position (`holidays` / `business_holidays` / `business_days`)
takes either the date list itself or the **name** of what resolves it — a
name from `resolvers` below, or a `yasumi-{Provider}` name when
[yasumi is installed](installation#resolving-public-holidays-with-yasumi).

Omitting a key leaves that definition **undefined**, which is not the
same as empty: schedule vocabulary that requires the definition (a
schedule on `holiday`, a `business_day` shift) is then a validation
error, while an empty list is the statement that no such days exist.

## resolvers

Resolver name → class-string of a `YrnkResolverInterface`
implementation:

```php
'resolvers' => [
    'company-holidays' => App\Yarunoka\CompanyHolidays::class,
],
```

Instances are made by the Laravel container **on first use**, so
constructor injection works — a resolver can take a repository, a cache,
or an HTTP client, and nothing is instantiated until a question first
reaches the name. The names are usable wherever the DSL accepts a name:
the calendar positions above, and documents stored by your application.

Writing a resolver is the engine's contract, documented by
[yarunoka/core](https://github.com/yarunoka-dev/php-core).

## Binding a layer interface

The three date-set layers have a second supply besides the config: bind
the layer's interface in the container, and the binding **wins over the
config key**.

```php
use Yarunoka\Resolvers\YrnkHolidaysResolverInterface;

$this->app->bind(YrnkHolidaysResolverInterface::class, CompanyHolidays::class);
```

`YrnkHolidaysResolverInterface`, `YrnkBusinessHolidaysResolverInterface`,
and `YrnkBusinessDaysResolverInterface` each define their layer when
bound. This is the natural form when the supply is already a service of
your application and the config would only repeat its name.

## How the bindings behave

- **Scoped per request.** The evaluator, the parser, and the environment
  they share are resolved once per container scope, so every consumer in
  a request sees one consistent environment.
- **The config is read lazily**, when the environment is first derived
  within a scope — not at registration. Config set by a later provider,
  or by a test, is honoured.
- **Your bindings win.** The bridge registers `YrnkEvaluator` and
  `YrnkParser` with `scopedIf`, so an application that binds either one
  itself replaces the bridge's construction entirely.
