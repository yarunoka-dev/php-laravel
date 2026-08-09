<?php

namespace Yarunoka\Laravel;

use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Laravel\Casts\AsYrnkSchedules;
use Yarunoka\Schedule\YrnkScheduleBuilder;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkSchedule;

/**
 * The wrapper the cast answers a schedules-part column with. The core's
 * YrnkSchedule is one OR branch and the judgment API is per branch, so
 * the any-composition a column (a whole schedules part — a list of
 * branches) needs is closed inside this class. The firing vocabulary the
 * core deliberately lacks (isDue) lives here — convenience is the
 * bridge's business.
 *
 * readonly, so a change is a reassignment (`$routine->schedule = $new`).
 *
 * @implements Arrayable<int, array<string, mixed>>
 */
final readonly class Schedule implements Arrayable, Castable
{
    /** @var non-empty-list<YrnkSchedule> */
    public array $schedules;

    /**
     * @param  list<YrnkSchedule>  $schedules  Unvalidated input. An empty list violates the invariants
     */
    public function __construct(array $schedules)
    {
        if ($schedules === []) {
            throw new InvalidValueException('The schedules part cannot be empty');
        }

        foreach ($schedules as $schedule) {
            // The phpdoc type is for the consumer; unvalidated input
            // arrives at runtime
            // @phpstan-ignore instanceof.alwaysTrue
            if (! $schedule instanceof YrnkSchedule) {
                throw new InvalidValueException(
                    'Elements of the schedules part must be YrnkSchedule: ' . get_debug_type($schedule),
                );
            }
        }

        $this->schedules = $schedules;
    }

    /**
     * Is there a scheduled point of any branch in the half-open interval
     * (since, at] — the firing decision. Asks YrnkEvaluator's hasMatchIn
     * per branch and composes with any.
     *
     * YrnkEvaluator is pulled from the container per call rather than
     * held on a property: a cast is constructed with a bare `new` where
     * DI cannot reach, and a model living across requests would
     * otherwise hold an instance of a stale scope.
     */
    public function isDue(DateTimeInterface $at, DateTimeInterface $since): bool
    {
        $evaluator = Container::getInstance()->make(YrnkEvaluator::class);

        foreach ($this->schedules as $schedule) {
            if ($evaluator->hasMatchIn($schedule, $since, $at)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The raw form of the DSL (list<RawSchedule>) — the model's toArray /
     * toJson show the stored spelling as it is.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        $builder = new YrnkScheduleBuilder();

        return array_map($builder->build(...), $this->schedules);
    }

    /**
     * The Castable form: casts() takes this wrapper's class name alone.
     *
     * @param  array<mixed>  $arguments
     * @return class-string<AsYrnkSchedules>
     */
    public static function castUsing(array $arguments): string
    {
        return AsYrnkSchedules::class;
    }
}
