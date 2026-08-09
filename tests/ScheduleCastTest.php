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
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Laravel\Exceptions\InvalidYrnkColumnException;
use Yarunoka\Laravel\Schedule;
use Yarunoka\Laravel\Tests\Support\RoutineRecord;
use Yarunoka\Schedule\YrnkScheduleParser;
use Yarunoka\YrnkSchedule;

class ScheduleCastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('routine_records', function (Blueprint $table): void {
            $table->id();
            $table->json('schedule')->nullable();
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
     * Reads the model back from the database and takes the schedule out
     * as the wrapper.
     */
    private function reloadedSchedule(RoutineRecord $routine): Schedule
    {
        $schedule = RoutineRecord::query()->findOrFail($routine->id)->schedule;

        if (! $schedule instanceof Schedule) {
            $this->fail('The schedule column did not come back as the wrapper');
        }

        return $schedule;
    }

    // ---- get ----

    #[Test]
    public function get_answers_a_schedule_column_as_the_wrapper(): void
    {
        DB::table('routine_records')->insert(['schedule' => '{"days": [25], "times": ["10:00"]}']);

        $schedule = RoutineRecord::query()->firstOrFail()->schedule;

        if (! $schedule instanceof Schedule) {
            $this->fail('The wrapper did not come back');
        }

        $this->assertInstanceOf(YrnkSchedule::class, $schedule->yrnkSchedule);
    }

    #[Test]
    public function get_answers_a_null_column_as_null(): void
    {
        DB::table('routine_records')->insert(['schedule' => null]);

        $this->assertNull(RoutineRecord::query()->firstOrFail()->schedule);
    }

    #[Test]
    public function get_reports_a_broken_column_with_the_model_and_column_names(): void
    {
        DB::table('routine_records')->insert(['schedule' => '{broken']);

        $this->expectException(InvalidYrnkColumnException::class);
        $this->expectExceptionMessageMatches('/schedule.+RoutineRecord/');

        RoutineRecord::query()->firstOrFail()->getAttribute('schedule');
    }

    // ---- set ----

    #[Test]
    public function set_stores_an_array_with_validation(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedule' => ['days' => [25], 'times' => ['10:00']],
        ]);

        $stored = DB::table('routine_records')->where('id', $routine->id)->value('schedule');

        $this->assertIsString($stored);
        $this->assertSame(['days' => [25], 'times' => ['10:00']], json_decode($stored, associative: true));
    }

    #[Test]
    public function set_accepts_a_json_string_too(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedule' => '{"days": [25], "times": ["10:00"]}',
        ]);

        $this->assertInstanceOf(YrnkSchedule::class, $this->reloadedSchedule($routine)->yrnkSchedule);
    }

    #[Test]
    public function set_accepts_a_yrnk_schedule_instance_too(): void
    {
        $yrnkSchedule = new YrnkScheduleParser()->parse(['days' => [25], 'times' => ['10:00']], new DateTimeZone('UTC'));

        $routine = RoutineRecord::query()->create(['schedule' => $yrnkSchedule]);

        $this->assertSame(['days' => [25], 'times' => ['10:00']], $this->reloadedSchedule($routine)->toArray());
    }

    #[Test]
    public function set_accepts_a_wrapper_instance_too(): void
    {
        $wrapper = new Schedule(
            new YrnkScheduleParser()->parse(['days' => [25], 'times' => ['10:00']], new DateTimeZone('UTC')),
        );

        $routine = RoutineRecord::query()->create(['schedule' => $wrapper]);

        $this->assertSame(['days' => [25], 'times' => ['10:00']], $this->reloadedSchedule($routine)->toArray());
    }

    #[Test]
    public function set_stores_null_as_null(): void
    {
        $routine = RoutineRecord::query()->create(['schedule' => null]);

        $this->assertNull(RoutineRecord::query()->findOrFail($routine->id)->schedule);
    }

    #[Test]
    public function set_rejects_a_list_pointing_to_a_schedules_column(): void
    {
        $this->expectException(InvalidYrnkException::class);
        $this->expectExceptionMessageMatches('/schedules column/');

        RoutineRecord::query()->create(['schedule' => [['days' => [25], 'times' => ['10:00']]]]);
    }

    #[Test]
    public function set_stops_an_invalid_schedule_on_an_exception_before_the_database(): void
    {
        try {
            RoutineRecord::query()->create(['schedule' => ['days' => [32], 'times' => ['10:00']]]);
            $this->fail('No exception was thrown');
        } catch (InvalidYrnkException) {
            $this->assertSame(0, DB::table('routine_records')->count());
        }
    }

    // ---- isDue (the firing decision) ----

    #[Test]
    #[DefineEnvironment('withTokyoAndHolidays')]
    public function is_due_cuts_the_interval_half_open_the_same_as_has_match_in(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedule' => ['days' => [25], 'times' => ['10:00']],
        ]);

        $schedule = $this->reloadedSchedule($routine);
        $point = $this->at('2026-07-25 10:00');

        // A point exactly at $at counts (the through side is closed)
        $this->assertTrue($schedule->isDue($point, since: $this->at('2026-07-25 09:00')));
        // A point exactly at $since does not (the since side is open)
        $this->assertFalse($schedule->isDue($this->at('2026-07-25 11:00'), since: $point));
    }

    #[Test]
    #[DefineEnvironment('withTokyoAndHolidays')]
    public function is_due_counts_a_day_for_as_long_as_it_lasts(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedule' => ['days' => ['holiday'], 'allday' => true],
        ]);

        $schedule = $this->reloadedSchedule($routine);

        // The holiday all-day schedule has its day = 2026-01-01
        // overlapping the interval — an interval starting mid-day still
        // counts it
        $this->assertTrue($schedule->isDue($this->at('2026-01-01 23:00'), since: $this->at('2026-01-01 12:00')));
        // An interval touching no day of the schedule
        $this->assertFalse($schedule->isDue($this->at('2026-01-03 09:00'), since: $this->at('2026-01-02 09:00')));
    }

    // ---- dirtiness and serialization ----

    #[Test]
    public function the_same_content_differing_only_in_key_order_or_whitespace_is_not_dirty(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedule' => '{"days":[25],"times":["10:00"]}',
        ]);

        $routine = RoutineRecord::query()->findOrFail($routine->id);
        $routine->setAttribute('schedule', '{"times": ["10:00"], "days": [25]}');

        $this->assertFalse($routine->isDirty('schedule'));
    }

    #[Test]
    public function changed_content_is_dirty(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedule' => '{"days":[25],"times":["10:00"]}',
        ]);

        $routine = RoutineRecord::query()->findOrFail($routine->id);
        $routine->setAttribute('schedule', '{"days": [26], "times": ["10:00"]}');

        $this->assertTrue($routine->isDirty('schedule'));
    }

    #[Test]
    public function the_models_to_array_shows_the_raw_form_of_the_dsl(): void
    {
        $routine = RoutineRecord::query()->create([
            'schedule' => ['days' => [25], 'times' => ['10:00']],
        ]);

        $array = RoutineRecord::query()->findOrFail($routine->id)->toArray();

        $this->assertSame(['days' => [25], 'times' => ['10:00']], $array['schedule']);
    }
}
