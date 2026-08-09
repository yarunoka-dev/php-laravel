<?php

namespace Yarunoka\Laravel;

use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Laravel\Casts\AsYrnkSchedules;

/**
 * The wrapper the cast answers a schedules-part column with — a list of
 * Schedule whose firing decision composes with any. The elements are the
 * bridge's Schedule, not bare YrnkSchedule, so each of them carries the
 * same vocabulary alone as it does in the list.
 *
 * readonly, so a change is a reassignment (`$routine->schedules = $new`).
 *
 * @implements Arrayable<int, array<string, mixed>>
 */
final readonly class Schedules implements Arrayable, Castable
{
    /** @var non-empty-list<Schedule> */
    public array $schedules;

    /**
     * @param  list<Schedule>  $schedules  Unvalidated input. An empty list violates the invariants
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
            if (! $schedule instanceof Schedule) {
                throw new InvalidValueException(
                    'Elements of the schedules part must be Schedule: ' . get_debug_type($schedule),
                );
            }
        }

        $this->schedules = $schedules;
    }

    /**
     * Is there a scheduled point of any schedule in the half-open
     * interval (since, at] — the firing decision, composed with any.
     */
    public function isDue(DateTimeInterface $at, DateTimeInterface $since): bool
    {
        foreach ($this->schedules as $schedule) {
            if ($schedule->isDue($at, since: $since)) {
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
        return array_map(static fn(Schedule $schedule): array => $schedule->toArray(), $this->schedules);
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
