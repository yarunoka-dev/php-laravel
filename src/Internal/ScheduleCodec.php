<?php

namespace Yarunoka\Laravel\Internal;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Schedule\YrnkScheduleBuilder;
use Yarunoka\Schedule\YrnkScheduleParser;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkSchedule;

/**
 * Opens and closes schedule columns with validation against the config
 * environment: one schedule (a column of RawSchedule) and a schedules
 * part (a column of list<RawSchedule>). Shared by the casts (storing and
 * reading) and the rules (rejecting a request with a 422).
 *
 * Structure and values are the schedule parser's calls; that every name
 * resolves in the config environment is asked eagerly (ensureResolvable),
 * so a wiring mistake surfaces on the storing or validation path instead
 * of at evaluation time. The check consults no resolver, so nothing is
 * fetched for it.
 *
 * @internal
 */
final readonly class ScheduleCodec
{
    public function __construct(
        private ConfigEnvironment $environment,
        private YrnkScheduleParser $parser = new YrnkScheduleParser(),
        private YrnkScheduleBuilder $builder = new YrnkScheduleBuilder(),
    ) {}

    /**
     * JSON string | array → one validated schedule.
     *
     * @param  string|array<mixed>  $raw
     */
    public function decodeSchedule(string|array $raw): YrnkSchedule
    {
        $raw = $this->arrayFrom($raw, 'The schedule must be a JSON object');

        if (array_is_list($raw)) {
            throw new InvalidYrnkException('The schedule must be a single object (a list belongs in a schedules column)');
        }

        $schedule = $this->parser->parse($raw, $this->environment->timezone());

        $this->ensureResolvable([$schedule]);

        return $schedule;
    }

    /**
     * One schedule (a YrnkSchedule | an array | a JSON string) → the JSON
     * to store. An invalid schedule stops on an exception and never
     * reaches the database.
     *
     * @param  YrnkSchedule|array<mixed>|string  $value
     */
    public function encodeSchedule(YrnkSchedule|array|string $value): string
    {
        $schedule = $value instanceof YrnkSchedule ? $value : $this->decodeSchedule($value);

        $this->ensureResolvable([$schedule]);

        return json_encode($this->builder->build($schedule), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * JSON string | array → the validated schedules.
     *
     * @param  string|array<mixed>  $raw
     * @return non-empty-list<YrnkSchedule>
     */
    public function decodeSchedules(string|array $raw): array
    {
        $raw = $this->arrayFrom($raw, 'The schedules part must be a JSON list');

        if (! array_is_list($raw)) {
            throw new InvalidYrnkException('The schedules part must be a list (wrap even a single schedule in a list)');
        }

        if ($raw === []) {
            throw new InvalidYrnkException('The schedules part cannot be empty');
        }

        $schedules = array_map(
            function (mixed $schedule): YrnkSchedule {
                if (! is_array($schedule)) {
                    throw new InvalidYrnkException('Elements of the schedules part must be objects');
                }

                return $this->parser->parse($schedule, $this->environment->timezone());
            },
            $raw,
        );

        $this->ensureResolvable($schedules);

        return $schedules;
    }

    /**
     * Schedules (a list of YrnkSchedule | an array | a JSON string) →
     * the JSON to store. An invalid schedules part stops on an exception
     * and never reaches the database.
     *
     * @param  list<YrnkSchedule>|array<mixed>|string  $value
     */
    public function encodeSchedules(array|string $value): string
    {
        $schedules = $this->isScheduleList($value) ? $value : $this->decodeSchedules($value);

        $this->ensureResolvable($schedules);

        $raw = array_map($this->builder->build(...), $schedules);

        return json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  string|array<mixed>  $raw
     * @return array<mixed>
     */
    private function arrayFrom(string|array $raw, string $message): array
    {
        if (! is_string($raw)) {
            return $raw;
        }

        $decoded = json_decode($raw, associative: true);

        if (! is_array($decoded)) {
            throw new InvalidYrnkException($message);
        }

        return $decoded;
    }

    /**
     * @param  list<YrnkSchedule>  $schedules
     */
    private function ensureResolvable(array $schedules): void
    {
        new YrnkEvaluator($this->environment->calendar(), $this->environment->timezone())
            ->ensureResolvable($schedules);
    }

    /**
     * @param  list<YrnkSchedule>|array<mixed>|string  $value
     *
     * @phpstan-assert-if-true list<YrnkSchedule> $value
     */
    private function isScheduleList(array|string $value): bool
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            return false;
        }

        foreach ($value as $schedule) {
            if (! $schedule instanceof YrnkSchedule) {
                return false;
            }
        }

        return true;
    }
}
