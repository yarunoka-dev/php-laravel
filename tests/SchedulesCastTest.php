<?php

namespace Yarunoka\Laravel\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Laravel\Exceptions\InvalidYrnkColumnException;
use Yarunoka\Laravel\Schedule;
use Yarunoka\Laravel\Schedules;
use Yarunoka\Laravel\Tests\Support\RoutineRecord;
use Yarunoka\Schedule\YrnkScheduleParser;

class SchedulesCastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('routine_records', function (Blueprint $table): void {
            $table->id();
            $table->json('schedules')->nullable();
        });
    }

    protected function withTokyoAndHolidays(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.timezone', 'Asia/Tokyo');
        $this->setConfig($app, 'yarunoka.calendar.holidays', ['2026-01-01']);
    }

    private function at(string $dateTime, string $timezone = 'Asia/Tokyo'): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone($timezone));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function parsed(array $raw): Schedule
    {
        return new Schedule(
            new YrnkScheduleParser()->parse($raw, new DateTimeZone('UTC')),
        );
    }

    /**
     * Reads the model back from the database and takes the schedules out
     * as the wrapper.
     */
    private function reloadedSchedules(RoutineRecord $routine): Schedules
    {
        $schedules = RoutineRecord::query()->findOrFail($routine->id)->schedules;

        if (! $schedules instanceof Schedules) {
            $this->fail('The schedules column did not come back as the wrapper');
        }

        return $schedules;
    }

    // ---- get ----

    #[Test]
    public function get_answers_a_schedules_part_column_as_the_wrapper(): void
    {
        DB::table('routine_records')->insert(['schedules' => '[{"days": [25], "times": ["10:00"]}]']);

        $schedules = RoutineRecord::query()->firstOrFail()->schedules;

        if (! $schedules instanceof Schedules) {
            $this->fail('The wrapper did not come back');
        }

        $this->assertCount(1, $schedules->schedules);
        $this->assertInstanceOf(Schedule::class, $schedules->schedules[0]);
    }

    #[Test]
    public function get_answers_a_null_column_as_null(): void
    {
        DB::table('routine_records')->insert(['schedules' => null]);

        $this->assertNull(RoutineRecord::query()->firstOrFail()->schedules);
    }

    #[Test]
    public function get_reports_a_broken_column_with_the_model_and_column_names(): void
    {
        DB::table('routine_records')->insert(['schedules' => '{broken']);

        $this->expectException(InvalidYrnkColumnException::class);
        $this->expectExceptionMessageMatches('/schedules.+RoutineRecord/');

        RoutineRecord::query()->firstOrFail()->getAttribute('schedules');
    }

    // ---- set ----

    #[Test]
    public function set_stores_an_array_with_validation(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedules' => [['days' => [25], 'times' => ['10:00']]],
        ]);

        $stored = DB::table('routine_records')->where('id', $routine->id)->value('schedules');

        $this->assertIsString($stored);
        $this->assertSame([['days' => [25], 'times' => ['10:00']]], json_decode($stored, associative: true));
    }

    #[Test]
    public function set_accepts_a_json_string_too(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedules' => '[{"days": [25], "times": ["10:00"]}]',
        ]);

        $this->assertCount(1, $this->reloadedSchedules($routine)->schedules);
    }

    #[Test]
    public function set_accepts_a_list_of_yrnk_schedules_too(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedules' => [
                new YrnkScheduleParser()->parse(['days' => [25], 'times' => ['10:00']], new DateTimeZone('UTC')),
            ],
        ]);

        $this->assertCount(1, $this->reloadedSchedules($routine)->schedules);
    }

    #[Test]
    public function set_accepts_a_list_of_schedule_wrappers_too(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedules' => [$this->parsed(['days' => [25], 'times' => ['10:00']])],
        ]);

        $this->assertCount(1, $this->reloadedSchedules($routine)->schedules);
    }

    #[Test]
    public function set_accepts_the_wrapper_instance_too(): void
    {
        $wrapper = new Schedules([$this->parsed(['days' => [25], 'times' => ['10:00']])]);

        $routine = RoutineRecord::query()->create(['schedules' => $wrapper]);

        $this->assertCount(1, $this->reloadedSchedules($routine)->schedules);
    }

    #[Test]
    public function set_stores_null_as_null(): void
    {
        $routine = RoutineRecord::query()->create(['schedules' => null]);

        $this->assertNull(RoutineRecord::query()->findOrFail($routine->id)->schedules);
    }

    #[Test]
    public function set_stops_an_invalid_schedule_on_an_exception_before_the_database(): void
    {
        try {
            RoutineRecord::query()->create(['schedules' => [['days' => [32], 'times' => ['10:00']]]]);
            $this->fail('No exception was thrown');
        } catch (InvalidYrnkException) {
            $this->assertSame(0, DB::table('routine_records')->count());
        }
    }

    // ---- the wrapper's invariants ----

    #[Test]
    public function the_wrapper_refuses_elements_other_than_schedule(): void
    {
        $this->expectException(InvalidValueException::class);

        // @phpstan-ignore argument.type (an intentional type violation to verify the guard)
        new Schedules(['not-a-schedule']);
    }

    #[Test]
    public function the_wrapper_refuses_an_empty_list(): void
    {
        $this->expectException(InvalidValueException::class);

        new Schedules([]);
    }

    // ---- isDue (the firing decision) ----

    #[Test]
    #[DefineEnvironment('withTokyoAndHolidays')]
    public function is_due_is_true_when_any_schedule_has_a_point_in_the_half_open_interval(): void
    {
        $routine = RoutineRecord::query()->create(['schedules' => [
            ['days' => [25], 'times' => ['10:00']],
            ['days' => ['holiday'], 'allday' => true],
        ]]);

        $schedules = $this->reloadedSchedules($routine);

        // The second schedule (holiday, all-day) has its day = 2026-01-01
        // overlapping the interval — and a day is due for as long as it
        // lasts, so an interval starting mid-day still counts it
        $this->assertTrue($schedules->isDue($this->at('2026-01-01 00:30'), since: $this->at('2025-12-31 23:00')));
        $this->assertTrue($schedules->isDue($this->at('2026-01-01 23:00'), since: $this->at('2026-01-01 12:00')));
        // An interval touching no day and no point of either schedule
        $this->assertFalse($schedules->isDue($this->at('2026-01-03 09:00'), since: $this->at('2026-01-02 09:00')));
    }

    #[Test]
    #[DefineEnvironment('withTokyoAndHolidays')]
    public function identical_schedules_are_legal_in_a_column_and_fire_as_one_or(): void
    {
        // A column is not a document: the document-level uniqueItems rule
        // does not apply here, so identical schedules stay legal (the OR
        // makes them harmless)
        $routine = RoutineRecord::query()->create(['schedules' => [
            ['days' => [25], 'times' => ['10:00']],
            ['days' => [25], 'times' => ['10:00']],
        ]]);

        $schedules = $this->reloadedSchedules($routine);

        $this->assertTrue($schedules->isDue(
            $this->at('2026-07-25 10:00'),
            since: $this->at('2026-07-25 09:00'),
        ));
    }

    // ---- dirtiness and serialization ----

    #[Test]
    public function the_same_content_differing_only_in_key_order_or_whitespace_is_not_dirty(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedules' => '[{"days":[25],"times":["10:00"]}]',
        ]);

        $routine = RoutineRecord::query()->findOrFail($routine->id);
        $routine->setAttribute('schedules', '[{"times": ["10:00"], "days": [25]}]');

        $this->assertFalse($routine->isDirty('schedules'));
    }

    #[Test]
    public function changed_content_is_dirty(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedules' => '[{"days":[25],"times":["10:00"]}]',
        ]);

        $routine = RoutineRecord::query()->findOrFail($routine->id);
        $routine->setAttribute('schedules', '[{"days": [26], "times": ["10:00"]}]');

        $this->assertTrue($routine->isDirty('schedules'));
    }

    #[Test]
    public function the_models_to_array_shows_the_raw_form_of_the_dsl(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedules' => [['days' => [25], 'times' => ['10:00']]],
        ]);

        $array = RoutineRecord::query()->findOrFail($routine->id)->toArray();

        $this->assertSame([['days' => [25], 'times' => ['10:00']]], $array['schedules']);
    }
}
