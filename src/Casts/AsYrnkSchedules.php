<?php

namespace Yarunoka\Laravel\Casts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Yarunoka\Exceptions\ExceptionInterface;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Laravel\Exceptions\InvalidYrnkColumnException;
use Yarunoka\Laravel\Internal\ScheduleCodec;
use Yarunoka\Laravel\Schedule;

/**
 * The Eloquent cast of a schedules-part column (the JSON of a
 * list<RawSchedule>). The consumer writes Schedule::class (Castable) in
 * casts() and this class is an implementation detail. ScheduleCodec is
 * pulled from the container per call because a cast is constructed with
 * a bare `new` where DI cannot reach (this also keeps the scope fresh).
 *
 * @implements CastsAttributes<Schedule|null, Schedule|array<mixed>|string|null>
 */
final class AsYrnkSchedules implements CastsAttributes, ComparesCastableAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Schedule
    {
        if ($value === null) {
            return null;
        }

        try {
            if (! is_string($value)) {
                throw new InvalidYrnkException('The column value is not a JSON string: ' . get_debug_type($value));
            }

            return new Schedule(Container::getInstance()->make(ScheduleCodec::class)->decodeSchedules($value));
        } catch (ExceptionInterface $e) {
            throw new InvalidYrnkColumnException(
                sprintf('The %s column of %s does not hold a readable schedules part: %s', $key, $model::class, $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Schedule) {
            $value = $value->schedules;
        }

        // TSet's phpdoc is the consumer-facing type; Eloquent hands over
        // mixed at runtime
        // @phpstan-ignore booleanAnd.alwaysFalse, function.alreadyNarrowedType
        if (! is_array($value) && ! is_string($value)) {
            throw new InvalidYrnkException(
                'A schedules-part column takes the wrapper, a list of YrnkSchedule, an array, or a JSON string: ' . get_debug_type($value),
            );
        }

        return Container::getInstance()->make(ScheduleCodec::class)->encodeSchedules($value);
    }

    /**
     * JSON varies in key order and whitespace as a string, so the decoded
     * arrays are compared to avoid a false dirty (a pointless UPDATE).
     */
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return $this->normalize($firstValue) == $this->normalize($secondValue);
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return json_decode($value, associative: true) ?? $value;
    }
}
