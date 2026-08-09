---
title: Usage
description: Storing schedules in Eloquent columns, validating requests with the engine's messages, and deciding when to fire.
sidebar:
  order: 4
---

The bridge adds three surfaces on top of the engine: **container
services** built from the config, **Eloquent casts** for storing
schedules in columns, and **validation rules** for rejecting a bad
request. Everything the engine itself does — parsing, building, and the
three questions — is documented by
[yarunoka/core](https://github.com/yarunoka-dev/php-core).

## The container services

`YrnkEvaluator` and `YrnkParser` come out of the container carrying the
[configured environment](configuration):

```php
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkParser;

app(YrnkEvaluator::class)->matches($schedule, now());
app(YrnkParser::class)->parse($json);   // knows the config's resolver names
```

## Storing a schedule column

A column holding a **schedules part** — a JSON list of schedules that
share the application's calendar — is cast by naming the wrapper in
`casts()`:

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

Writing accepts the spellings a schedules part arrives in — a PHP array,
a JSON string, a list of `YrnkSchedule`, or the wrapper itself — and
**validates on both paths**: structure, values, and that every name
resolves in the configured environment. An invalid schedules part stops
on an exception and never reaches the database; a broken column value
surfaces on read as `InvalidYrnkColumnException`, naming the model and
the column.

```php
$routine = Routine::create([
    'schedule' => [
        ['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']],
    ],
]);
```

Reading gives the wrapper back. It is readonly — a change is a
reassignment of the whole attribute — and the model's `toArray()` /
`toJson()` show the stored spelling of the DSL as it is.

The column is a list of OR branches, like a document's `schedules`. Two
things differ from a document: the calendar and timezone come from the
config rather than travelling with the data, and the document-level
"no duplicate schedules" rule does not apply — identical branches are
legal in a column and fire as one.

## Deciding when to fire

The wrapper carries the firing decision, composed across its branches:

```php
if ($routine->schedule->isDue(now(), since: $routine->last_run_at)) {
    run();
    $routine->last_run_at = now();
}
```

`isDue` asks whether any branch has a scheduled point **after `since`,
through `at`** — the same half-open question as the engine's
`hasMatchIn`, so each question's "now" becomes the next one's start and
every point is seen exactly once across a series of polls. Catch-up,
grace, and throttling are the caller's decisions, made by how the period
is cut; the engine documentation covers the patterns.

## Storing a whole document

A column may instead hold a **whole Yrnk document** — its own timezone
and calendar per row, for rows whose rules do not share the
application's environment:

```php
use Yarunoka\Laravel\Casts\AsYrnk;

protected function casts(): array
{
    return ['document' => AsYrnk::class];
}
```

Reading gives a bare `Yrnk`. A whole-document column stores *such a
document* and grows no judgment conveniences; whoever wants to judge
builds an evaluator from the document itself:

```php
use Yarunoka\YrnkEvaluator;

$evaluator = YrnkEvaluator::fromYrnk($routine->document);
```

Writing accepts a `Yrnk`, an array, or a JSON string, and always passes
construction validation before the database.

## Validating a request

The rules try the construction and put the engine's message on the
validation error as it is:

```php
use Yarunoka\Laravel\Rules\ValidYrnk;
use Yarunoka\Laravel\Rules\ValidYrnkSchedules;

$validated = $request->validate([
    'schedule' => ['required', new ValidYrnkSchedules()],
    'document' => ['sometimes', new ValidYrnk()],
]);
```

`ValidYrnkSchedules` checks a schedules part — structure, values, and
references against the configured environment — and `ValidYrnk` a whole
document. What passed validation is exactly what the cast will accept,
so validate-then-store never fails in the second step.

## Dirtiness and serialization

JSON varies in key order and whitespace as a string, so the casts
compare decoded values: reassigning a column with the same content in a
different spelling is **not dirty**, and no pointless `UPDATE` runs.
Changed content is dirty as usual.

## Handling failures

Everything the bridge and the engine throw implements
`Yarunoka\Exceptions\ExceptionInterface`. The bridge adds one exception
of its own: `InvalidYrnkColumnException`, raised when a stored column
cannot be read back — it names the model and the column, because a
cast's failure surfaces in the middle of model retrieval where the cause
is otherwise hard to see. Validation failures never reach an exception:
the rules turn them into validation errors instead.
