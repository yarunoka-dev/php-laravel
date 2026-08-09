<?php

namespace Yarunoka\Laravel;

use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Yarunoka\Laravel\Casts\AsYrnkSchedule;
use Yarunoka\Schedule\YrnkScheduleBuilder;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkSchedule;

/**
 * The wrapper the cast answers a schedule column with — one schedule of
 * the DSL. The core's YrnkSchedule stays exposed as a property, so
 * everything php-core can do with a schedule stays reachable. The firing
 * vocabulary the core deliberately lacks (isDue) lives here —
 * convenience is the bridge's business.
 *
 * readonly, so a change is a reassignment (`$routine->schedule = $new`).
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Schedule implements Arrayable, Castable
{
    public function __construct(public YrnkSchedule $yrnkSchedule) {}

    /**
     * Is there a scheduled point in the half-open interval (since, at] —
     * the firing decision, the engine's hasMatchIn as it is.
     *
     * YrnkEvaluator is pulled from the container per call rather than
     * held on a property: a cast is constructed with a bare `new` where
     * DI cannot reach, and a model living across requests would
     * otherwise hold an instance of a stale scope.
     */
    public function isDue(DateTimeInterface $at, DateTimeInterface $since): bool
    {
        return Container::getInstance()->make(YrnkEvaluator::class)
            ->hasMatchIn($this->yrnkSchedule, $since, $at);
    }

    /**
     * The raw form of the DSL (RawSchedule) — the model's toArray /
     * toJson show the stored spelling as it is.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return new YrnkScheduleBuilder()->build($this->yrnkSchedule);
    }

    /**
     * The Castable form: casts() takes this wrapper's class name alone.
     *
     * @param  array<mixed>  $arguments
     * @return class-string<AsYrnkSchedule>
     */
    public static function castUsing(array $arguments): string
    {
        return AsYrnkSchedule::class;
    }
}
