<?php

namespace Yarunoka\Laravel\Internal;

use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Schedule\YrnkScheduleBuilder;
use Yarunoka\Yrnk;
use Yarunoka\YrnkParser;
use Yarunoka\YrnkSchedule;

/**
 * Opens and closes a schedules part (a column of list<RawSchedule>) with
 * validation against the config environment. Shared by the cast (storing
 * and reading) and the rule (rejecting a request with a 422).
 *
 * The column is validated as the schedules part of a document whose
 * environment is the config: a synthetic document is composed around the
 * input and handed to YrnkParser, so everything the language rejects in a
 * document — structure, values, duplicate schedules, references nothing
 * defines — is rejected here, by the same code and with the same
 * messages. The names the input uses are declared on the document when a
 * binding answers for them; a name nothing answers for is left
 * undeclared, so the parser reports it as undefined.
 *
 * @internal
 */
final readonly class ScheduleCodec
{
    public function __construct(
        private ConfigEnvironment $environment,
    ) {}

    /**
     * JSON string | array → the validated schedules.
     *
     * @param  string|array<mixed>  $raw
     * @return non-empty-list<YrnkSchedule>
     */
    public function decode(string|array $raw): array
    {
        return $this->parseAsDocument($this->listFrom($raw))->schedules;
    }

    /**
     * Schedules (a list of YrnkSchedule | an array | a JSON string) →
     * the JSON to store. An invalid schedules part stops on an exception
     * and never reaches the database.
     *
     * @param  list<YrnkSchedule>|array<mixed>|string  $value
     */
    public function encode(array|string $value): string
    {
        $builder = new YrnkScheduleBuilder();

        $raw = $this->isScheduleList($value)
            ? array_map($builder->build(...), $value)
            : $this->listFrom($value);

        $this->parseAsDocument($raw);

        return json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  string|array<mixed>  $raw
     * @return array<mixed>
     */
    private function listFrom(string|array $raw): array
    {
        if (! is_string($raw)) {
            return $raw;
        }

        $decoded = json_decode($raw, associative: true);

        if (! is_array($decoded)) {
            throw new InvalidYrnkException('The schedules part must be a JSON list');
        }

        return $decoded;
    }

    /**
     * The validation: a document made of the config environment and the
     * given schedules part, parsed whole. List shape, emptiness, and
     * every document rule are the parser's calls, not re-implemented
     * here.
     *
     * @param  array<mixed>  $raw
     */
    private function parseAsDocument(array $raw): Yrnk
    {
        $document = [
            'version' => Yrnk::SUPPORTED_VERSION,
            'timezone' => $this->environment->timezone()->getName(),
            'calendar' => $this->environment->rawCalendar(),
            'schedules' => $raw,
        ];

        $declared = $this->declaredNames($raw);

        if ($declared !== []) {
            $document['resolvers'] = $declared;
        }

        return new YrnkParser($this->environment->resolverContainer())->parse($document);
    }

    /**
     * The names to declare on the synthetic document: every string the
     * input or the calendar config spells that a binding answers for. A
     * declared name a schedule never uses is legal, so collecting
     * loosely (any string, wherever it appears) over-declares at worst;
     * date_sets entries are excluded because a name is either defined or
     * declared, never both.
     *
     * @param  array<mixed>  $rawSchedules
     * @return list<string>
     */
    private function declaredNames(array $rawSchedules): array
    {
        $rawCalendar = $this->environment->rawCalendar();
        $dateSets = $rawCalendar['date_sets'] ?? [];
        $resolvers = $this->environment->resolverContainer();

        $found = [];

        array_walk_recursive($rawSchedules, static function (mixed $value) use (&$found): void {
            if (is_string($value)) {
                $found[$value] = true;
            }
        });

        foreach (['holidays', 'business_holidays', 'business_days'] as $key) {
            if (is_string($rawCalendar[$key] ?? null)) {
                $found[$rawCalendar[$key]] = true;
            }
        }

        $names = array_map(strval(...), array_keys($found));

        return array_values(array_filter(
            $names,
            static fn (string $name): bool => ! (is_array($dateSets) && array_key_exists($name, $dateSets))
                && $resolvers->has($name),
        ));
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
