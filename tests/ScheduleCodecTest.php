<?php

namespace Yarunoka\Laravel\Tests;

use DateTimeZone;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Laravel\Internal\ScheduleCodec;
use Yarunoka\Schedule\FixedTimes;
use Yarunoka\Schedule\YrnkScheduleParser;
use Yarunoka\YrnkSchedule;

class ScheduleCodecTest extends TestCase
{
    protected function withCompanyCalendar(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.calendar', [
            'holidays' => ['2026-01-01'],
            'date_sets' => ['founding-day' => ['2026-10-01']],
        ]);
    }

    private function codec(): ScheduleCodec
    {
        return app()->make(ScheduleCodec::class);
    }

    // ---- decodeSchedule (one schedule) ----

    #[Test]
    public function decode_schedule_turns_a_json_object_into_one_yrnk_schedule(): void
    {
        $schedule = $this->codec()->decodeSchedule('{"days": [25], "times": ["10:00"]}');

        $this->assertInstanceOf(YrnkSchedule::class, $schedule);
        $this->assertInstanceOf(FixedTimes::class, $schedule->times);
    }

    #[Test]
    public function decode_schedule_accepts_an_array_too(): void
    {
        $schedule = $this->codec()->decodeSchedule(['days' => [25], 'times' => ['10:00']]);

        $this->assertInstanceOf(YrnkSchedule::class, $schedule);
    }

    #[Test]
    public function decode_schedule_rejects_a_list_pointing_to_a_schedules_column(): void
    {
        $this->expectException(InvalidYrnkException::class);
        $this->expectExceptionMessageMatches('/schedules column/');

        $this->codec()->decodeSchedule([['days' => [25], 'times' => ['10:00']]]);
    }

    #[Test]
    public function decode_schedule_rejects_broken_json(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->codec()->decodeSchedule('{broken');
    }

    #[Test]
    public function decode_schedule_rejects_a_structure_violation(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->codec()->decodeSchedule(['days' => [25]]); // neither times nor allday
    }

    #[Test]
    #[DefineEnvironment('withCompanyCalendar')]
    public function decode_schedule_validates_references_against_the_config_environment(): void
    {
        $schedule = $this->codec()->decodeSchedule(['days' => ['founding-day'], 'allday' => true]);

        $this->assertInstanceOf(YrnkSchedule::class, $schedule);
    }

    #[Test]
    #[DefineEnvironment('withCompanyCalendar')]
    public function decode_schedule_rejects_a_reference_to_an_undefined_name(): void
    {
        $this->expectException(UndefinedNameException::class);

        $this->codec()->decodeSchedule(['days' => ['nowhere-day'], 'allday' => true]);
    }

    // ---- encodeSchedule (one schedule) ----

    #[Test]
    public function encode_schedule_validates_an_array_into_a_json_string(): void
    {
        $raw = ['days' => [25], 'times' => ['10:00']];

        $this->assertSame($raw, json_decode($this->codec()->encodeSchedule($raw), associative: true));
    }

    #[Test]
    public function encode_schedule_accepts_a_json_string_with_validation(): void
    {
        $json = '{"days":[25],"times":["10:00"]}';

        $this->assertSame(
            ['days' => [25], 'times' => ['10:00']],
            json_decode($this->codec()->encodeSchedule($json), associative: true),
        );
    }

    #[Test]
    public function encode_schedule_accepts_a_yrnk_schedule_instance_too(): void
    {
        $schedule = new YrnkScheduleParser()->parse(['days' => [25], 'times' => ['10:00']], new DateTimeZone('UTC'));

        $this->assertSame(
            ['days' => [25], 'times' => ['10:00']],
            json_decode($this->codec()->encodeSchedule($schedule), associative: true),
        );
    }

    #[Test]
    public function encode_schedule_stops_an_invalid_schedule_on_an_exception_before_the_database(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->codec()->encodeSchedule(['days' => [32], 'times' => ['10:00']]);
    }

    // ---- decodeSchedules (a schedules part) ----

    #[Test]
    public function decode_schedules_turns_a_json_string_into_a_list_of_yrnk_schedules(): void
    {
        $schedules = $this->codec()->decodeSchedules('[{"days": [25], "times": ["10:00"]}]');

        $this->assertCount(1, $schedules);
        $this->assertInstanceOf(YrnkSchedule::class, $schedules[0]);
        $this->assertInstanceOf(FixedTimes::class, $schedules[0]->times);
    }

    #[Test]
    public function decode_schedules_accepts_an_array_too(): void
    {
        $schedules = $this->codec()->decodeSchedules([['days' => [25], 'times' => ['10:00']]]);

        $this->assertCount(1, $schedules);
    }

    #[Test]
    public function decode_schedules_rejects_a_bare_object_saying_wrap_it_in_a_list(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->codec()->decodeSchedules(['days' => [25], 'times' => ['10:00']]);
    }

    #[Test]
    public function decode_schedules_rejects_an_empty_list(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->codec()->decodeSchedules([]);
    }

    #[Test]
    public function decode_schedules_rejects_broken_json(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->codec()->decodeSchedules('{broken');
    }

    #[Test]
    public function decode_schedules_rejects_a_structure_violation(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->codec()->decodeSchedules([['days' => [25]]]); // neither times nor allday
    }

    #[Test]
    #[DefineEnvironment('withCompanyCalendar')]
    public function decode_schedules_validates_references_against_the_config_environment(): void
    {
        $schedules = $this->codec()->decodeSchedules([
            ['days' => ['holiday'], 'allday' => true],
            ['days' => ['founding-day'], 'allday' => true],
        ]);

        $this->assertCount(2, $schedules);
    }

    #[Test]
    #[DefineEnvironment('withCompanyCalendar')]
    public function decode_schedules_rejects_a_reference_to_an_undefined_name(): void
    {
        $this->expectException(UndefinedNameException::class);

        $this->codec()->decodeSchedules([['days' => ['nowhere-day'], 'allday' => true]]);
    }

    #[Test]
    public function decode_schedules_rejects_layer_vocabulary_with_the_layer_undefined(): void
    {
        $this->expectException(MissingCalendarDataException::class);

        $this->codec()->decodeSchedules([['days' => ['holiday'], 'allday' => true]]);
    }

    // ---- encodeSchedules (a schedules part) ----

    #[Test]
    public function encode_schedules_validates_an_array_into_a_json_string(): void
    {
        $raw = [['days' => [25], 'times' => ['10:00']]];

        $this->assertSame($raw, json_decode($this->codec()->encodeSchedules($raw), associative: true));
    }

    #[Test]
    public function encode_schedules_accepts_a_json_string_with_validation(): void
    {
        $json = '[{"days":[25],"times":["10:00"]}]';

        $this->assertSame(
            [['days' => [25], 'times' => ['10:00']]],
            json_decode($this->codec()->encodeSchedules($json), associative: true),
        );
    }

    #[Test]
    public function encode_schedules_accepts_a_list_of_yrnk_schedule_instances_too(): void
    {
        $schedules = [new YrnkScheduleParser()->parse(['days' => [25], 'times' => ['10:00']], new DateTimeZone('UTC'))];

        $this->assertSame(
            [['days' => [25], 'times' => ['10:00']]],
            json_decode($this->codec()->encodeSchedules($schedules), associative: true),
        );
    }

    #[Test]
    public function encode_schedules_stops_an_invalid_schedule_on_an_exception_before_the_database(): void
    {
        $this->expectException(InvalidYrnkException::class);

        $this->codec()->encodeSchedules([['days' => [32], 'times' => ['10:00']]]);
    }

    #[Test]
    #[DefineEnvironment('withCompanyCalendar')]
    public function encode_schedules_validates_references_too(): void
    {
        $this->expectException(UndefinedNameException::class);

        $this->codec()->encodeSchedules([['days' => ['nowhere-day'], 'allday' => true]]);
    }

    #[Test]
    public function encoding_and_decoding_round_trip_to_the_same_value(): void
    {
        $raw = [
            ['days' => [25, 'mon'], 'shift' => ['prev', 'or_same', 'weekday'], 'times' => ['10:00', '15:30']],
            ['months' => [7], 'days' => ['last_day_of_month'], 'allday' => true],
        ];

        $decoded = $this->codec()->decodeSchedules($this->codec()->encodeSchedules($raw));

        $this->assertSame($raw, json_decode($this->codec()->encodeSchedules($decoded), associative: true));
    }
}
