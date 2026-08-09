---
title: Installation
description: Installing the package with Composer, publishing the config, and adding the optional holiday provider.
sidebar:
  order: 2
---

## Composer

```console
composer require yarunoka/laravel
```

Requires PHP 8.4 or newer and Laravel 13. The package brings
`yarunoka/core` with it, and the service provider is registered by
package auto-discovery — there is no bootstrapping step.

:::caution
The 0.x releases exist to exercise the release pipeline. They are **not
intended for use**. This notice will be removed at 1.0.0.
:::

## Publishing the config

```console
php artisan vendor:publish --tag=yarunoka-config
```

This copies `config/yarunoka.php` into your application. Publishing is
optional: without it, the defaults apply — the timezone falls back to
`app.timezone`, and the calendar is empty. What the keys mean is the
subject of [Configuration](configuration).

## Resolving public holidays with yasumi

Wherever the DSL accepts a resolver name, a name of the form
`yasumi-{Provider}` is resolved by
[yasumi](https://github.com/azuyalabs/yasumi) when that library is
installed — no resolver of your own to write:

```console
composer require azuyalabs/yasumi
```

`{Provider}` is a yasumi provider name (`Japan`, `USA`, …). Without the
library installed, such a name is simply an unregistered resolver, and
using it fails validation — nothing resolves silently to an empty list.

## Verifying the installation

The bridge binds the engine's entry points into the container, so
resolving one and parsing a document proves the wiring:

```php
use Yarunoka\YrnkParser;

$document = app(YrnkParser::class)->parse('{
    "version": "1.0",
    "timezone": "Asia/Tokyo",
    "schedules": [{"days": [25], "times": ["10:00"]}]
}');

$document->timezone->getName();   // "Asia/Tokyo"
count($document->schedules);      // 1
```
