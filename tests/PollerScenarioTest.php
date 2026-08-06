<?php

namespace Yarunoka\Laravel\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Yarunoka\Laravel\Rules\ValidYrnkSchedules;
use Yarunoka\Laravel\Schedule;
use Yarunoka\Laravel\Tests\Support\RoutineRecord;

/**
 * A rehearsal of the consuming application's shape — "receive over RPC,
 * validate, store, read back, fire with isDue from a poller" — end to
 * end through the bridge.
 */
class PollerScenarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('routine_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('schedules')->nullable();
            $table->timestamp('last_run_at')->nullable();
        });
    }

    protected function withCompanyCalendar(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.timezone', 'Asia/Tokyo');
        $this->setConfig($app, 'yarunoka.calendar', [
            // The real 2026-07 calendar: the 20th (Mon) is Marine Day in
            // Japan
            'holidays' => ['2026-07-20'],
            'business_holidays' => ['2026-07-27'],
            'business_days' => [],
        ]);
    }

    private function at(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('Asia/Tokyo'));
    }

    #[Test]
    #[DefineEnvironment('withCompanyCalendar')]
    public function validated_storing_through_poller_firing_passes_end_to_end(): void
    {
        // 1. Validate input shaped like an RPC request with the rule
        $input = ['schedules' => [
            // Payday: the 25th at 10:00, moved earlier on a closed day
            // (2026-07-25 is a Saturday, so it lands on Friday the 24th)
            ['days' => [25], 'shift' => ['prev', 'or_same', 'business_day'], 'times' => ['10:00']],
        ]];

        $this->assertTrue(
            Validator::make($input, ['schedules' => ['required', new ValidYrnkSchedules()]])->passes(),
        );

        // 2. Store through the cast
        $routine = RoutineRecord::query()->create([
            'name' => 'payday-notice',
            'schedules' => $input['schedules'],
        ]);

        // 3. The poller's shape: read back, ask isDue, advance
        // last_run_at when it fires
        $lastRunAt = $this->at('2026-07-24 00:00');
        $fired = [];

        foreach ([
            '2026-07-24 09:59',
            '2026-07-24 10:01',
            '2026-07-24 10:02',
            '2026-07-25 10:01',
        ] as $tick) {
            $now = $this->at($tick);
            $schedules = RoutineRecord::query()->findOrFail($routine->id)->schedules;

            if ($schedules instanceof Schedule && $schedules->isDue($now, since: $lastRunAt)) {
                $fired[] = $tick;
                $lastRunAt = $now;
            }
        }

        // The Friday the 24th 10:00 point is picked up once, by the
        // 10:01 poll. Saturday the 25th is not the landing day, so no
        // firing there
        $this->assertSame(['2026-07-24 10:01'], $fired);
    }

    #[Test]
    #[DefineEnvironment('withCompanyCalendar')]
    public function a_missed_point_is_caught_up_and_never_fires_twice(): void
    {
        $routine = RoutineRecord::query()->create([
            'name' => 'holiday-morning',
            'schedules' => [['days' => ['holiday'], 'times' => ['08:00']]],
        ]);

        // The first poll comes long past the 2026-07-20 (Marine Day)
        // 08:00 point
        $lastRunAt = $this->at('2026-07-19 00:00');
        $now = $this->at('2026-07-20 23:00');

        $schedules = RoutineRecord::query()->findOrFail($routine->id)->schedules;
        $this->assertInstanceOf(Schedule::class, $schedules);

        // Late, but it still fires once (catch-up)
        $this->assertTrue($schedules->isDue($now, since: $lastRunAt));

        // Advancing last_run_at after the firing, the same point never
        // fires again
        $this->assertFalse($schedules->isDue($this->at('2026-07-21 08:30'), since: $now));
    }
}
