<?php

namespace Yarunoka\Laravel\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Exceptions\UnregisteredResolverException;
use Yarunoka\Laravel\Tests\Support\HolidaySource;
use Yarunoka\Laravel\Tests\Support\InjectedHolidaysResolver;
use Yarunoka\Laravel\YarunokaServiceProvider;
use Yarunoka\Resolvers\YrnkHolidaysResolverInterface;
use Yarunoka\Schedule\YrnkScheduleParser;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkParser;
use Yarunoka\YrnkSchedule;

class YarunokaServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        InjectedHolidaysResolver::reset();
    }

    // ---- environment definitions (picked per test with #[DefineEnvironment]) ----

    protected function withHolidaysList(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.calendar.holidays', ['2026-01-01']);
    }

    protected function withEmptyHolidays(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.calendar.holidays', []);
    }

    protected function withResolverNameReference(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.calendar.holidays', 'jp-holidays');
        $this->setConfig($app, 'yarunoka.resolvers', ['jp-holidays' => InjectedHolidaysResolver::class]);
        $app->instance(HolidaySource::class, new HolidaySource(['2026-01-01']));
    }

    protected function withUnregisteredResolverName(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.calendar.holidays', 'nowhere');
    }

    protected function withYasumi(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.calendar.holidays', 'yasumi-Japan');
    }

    protected function withTokyoTimezone(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.timezone', 'Asia/Tokyo');
    }

    protected function withTokyoAppTimezoneOnly(Application $app): void
    {
        $this->setConfig($app, 'app.timezone', 'Asia/Tokyo');
    }

    protected function withNewYorkTimezone(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.timezone', 'America/New_York');
        $this->setConfig($app, 'yarunoka.calendar.holidays', ['2026-01-01']);
    }

    protected function withUnknownTimezone(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.timezone', 'Neko/Nowhere');
    }

    protected function withWorkweekAndDateSets(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.calendar', [
            'workweek' => ['mon', 'tue', 'wed'],
            'date_sets' => ['founding-day' => ['2026-10-01']],
        ]);
    }

    // ---- helpers ----

    private function schedule(array $raw): YrnkSchedule
    {
        return new YrnkScheduleParser()->parse($raw, new DateTimeZone('UTC'));
    }

    private function holidaySchedule(): YrnkSchedule
    {
        return $this->schedule(['days' => ['holiday'], 'allday' => true]);
    }

    private function at(string $dateTime, string $timezone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone($timezone));
    }

    // ---- merging and publishing the config ----

    #[Test]
    public function merges_the_config_and_answers_the_defaults(): void
    {
        $this->assertNull(config('yarunoka.timezone'));
        $this->assertSame([], config('yarunoka.calendar'));
        $this->assertSame([], config('yarunoka.resolvers'));
    }

    #[Test]
    public function publishes_the_config_under_the_yarunoka_config_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(YarunokaServiceProvider::class, 'yarunoka-config');

        $this->assertCount(1, $paths);
        $this->assertStringEndsWith('config/yarunoka.php', (string) array_key_first($paths));
    }

    // ---- building YrnkEvaluator (config → environment) ----

    #[Test]
    #[DefineEnvironment('withHolidaysList')]
    public function a_date_list_in_the_config_becomes_the_holidays_layer(): void
    {
        $evaluator = app()->make(YrnkEvaluator::class);

        $this->assertTrue($evaluator->matches($this->holidaySchedule(), $this->at('2026-01-01 12:00')));
        $this->assertFalse($evaluator->matches($this->holidaySchedule(), $this->at('2026-01-02 12:00')));
    }

    #[Test]
    #[DefineEnvironment('withEmptyHolidays')]
    public function an_empty_list_in_the_config_is_defined_as_the_statement_of_no_such_days(): void
    {
        $evaluator = app()->make(YrnkEvaluator::class);

        $this->assertFalse($evaluator->matches($this->holidaySchedule(), $this->at('2026-01-01 12:00')));
    }

    #[Test]
    public function using_layer_vocabulary_with_no_layer_in_config_or_bindings_is_an_error(): void
    {
        $evaluator = app()->make(YrnkEvaluator::class);

        $this->expectException(MissingCalendarDataException::class);

        $evaluator->matches($this->holidaySchedule(), $this->at('2026-01-01 12:00'));
    }

    #[Test]
    #[DefineEnvironment('withResolverNameReference')]
    public function a_resolver_name_in_the_config_delegates_to_the_resolvers_class_with_constructor_injection(): void
    {
        $evaluator = app()->make(YrnkEvaluator::class);

        $this->assertTrue($evaluator->matches($this->holidaySchedule(), $this->at('2026-01-01 12:00')));
    }

    #[Test]
    #[DefineEnvironment('withUnregisteredResolverName')]
    public function an_unregistered_resolver_name_fails_loudly_when_a_question_is_asked(): void
    {
        $evaluator = app()->make(YrnkEvaluator::class);

        $this->expectException(UnregisteredResolverException::class);

        $evaluator->matches($this->holidaySchedule(), $this->at('2026-01-01 12:00'));
    }

    #[Test]
    #[DefineEnvironment('withYasumi')]
    public function a_yasumi_name_in_the_config_answers_japanese_public_holidays(): void
    {
        $evaluator = app()->make(YrnkEvaluator::class);

        $this->assertTrue($evaluator->matches($this->holidaySchedule(), $this->at('2026-01-01 12:00'))); // New Year's Day
        $this->assertFalse($evaluator->matches($this->holidaySchedule(), $this->at('2026-06-10 12:00')));
    }

    #[Test]
    #[DefineEnvironment('withWorkweekAndDateSets')]
    public function workweek_and_date_sets_are_read_from_the_config_with_the_validation_of_the_dsl(): void
    {
        $evaluator = app()->make(YrnkEvaluator::class);
        $schedule = $this->schedule(['days' => ['founding-day'], 'allday' => true]);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-10-01 12:00')));
    }

    // ---- binding the layer interfaces (the app wins) ----

    #[Test]
    public function binding_a_layer_interface_defines_that_layer(): void
    {
        app()->instance(HolidaySource::class, new HolidaySource(['2026-03-03']));
        app()->bind(YrnkHolidaysResolverInterface::class, InjectedHolidaysResolver::class);

        $evaluator = app()->make(YrnkEvaluator::class);

        $this->assertTrue($evaluator->matches($this->holidaySchedule(), $this->at('2026-03-03 12:00')));
    }

    #[Test]
    #[DefineEnvironment('withHolidaysList')]
    public function the_apps_binding_wins_over_the_layer_in_the_config(): void
    {
        app()->instance(HolidaySource::class, new HolidaySource(['2026-03-03']));
        app()->bind(YrnkHolidaysResolverInterface::class, InjectedHolidaysResolver::class);

        $evaluator = app()->make(YrnkEvaluator::class);

        $this->assertTrue($evaluator->matches($this->holidaySchedule(), $this->at('2026-03-03 12:00')));
        $this->assertFalse($evaluator->matches($this->holidaySchedule(), $this->at('2026-01-01 12:00')));
    }

    #[Test]
    public function a_resolver_is_instantiated_lazily_and_only_once(): void
    {
        app()->instance(HolidaySource::class, new HolidaySource(['2026-03-03']));
        app()->bind(YrnkHolidaysResolverInterface::class, InjectedHolidaysResolver::class);

        $evaluator = app()->make(YrnkEvaluator::class);

        $this->assertSame(0, InjectedHolidaysResolver::$instantiations);

        $evaluator->matches($this->holidaySchedule(), $this->at('2026-03-03 12:00'));
        $evaluator->matches($this->holidaySchedule(), $this->at('2026-03-04 12:00'));

        // One instance answers both questions; the engine resolves per
        // question by design, so the resolutions follow the questions
        $this->assertSame(1, InjectedHolidaysResolver::$instantiations);
        $this->assertSame(2, InjectedHolidaysResolver::$resolutions);
    }

    // ---- timezone ----

    #[Test]
    #[DefineEnvironment('withTokyoTimezone')]
    public function the_configured_timezone_is_yarunoka_timezone(): void
    {
        config()->set('yarunoka.calendar.holidays', []);
        $schedule = $this->schedule(['days' => [1], 'times' => ['10:00']]);

        $evaluator = app()->make(YrnkEvaluator::class);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-01 10:00', 'Asia/Tokyo')));
        $this->assertFalse($evaluator->matches($schedule, $this->at('2026-07-01 10:00', 'UTC')));
    }

    #[Test]
    #[DefineEnvironment('withTokyoAppTimezoneOnly')]
    public function a_null_yarunoka_timezone_falls_back_to_app_timezone(): void
    {
        $schedule = $this->schedule(['days' => [1], 'times' => ['10:00']]);

        $evaluator = app()->make(YrnkEvaluator::class);

        $this->assertTrue($evaluator->matches($schedule, $this->at('2026-07-01 10:00', 'Asia/Tokyo')));
    }

    #[Test]
    #[DefineEnvironment('withNewYorkTimezone')]
    public function a_timezone_with_daylight_saving_can_be_configured(): void
    {
        $evaluator = app()->make(YrnkEvaluator::class);

        $this->assertTrue($evaluator->matches($this->holidaySchedule(), $this->at('2026-01-01 12:00', 'America/New_York')));
    }

    #[Test]
    #[DefineEnvironment('withUnknownTimezone')]
    public function an_unknown_timezone_is_an_exception_on_the_first_resolution(): void
    {
        $this->expectException(\DateInvalidTimeZoneException::class);

        app()->make(YrnkEvaluator::class);
    }

    // ---- the service bindings (scoped + If) ----

    #[Test]
    public function the_bridge_yields_when_the_app_rebinds_yrnk_evaluator(): void
    {
        $custom = new YrnkEvaluator(new YrnkCalendar(), new DateTimeZone('UTC'));
        app()->scoped(YrnkEvaluator::class, fn () => $custom);

        $this->assertSame($custom, app()->make(YrnkEvaluator::class));
    }

    #[Test]
    public function yrnk_evaluator_is_the_same_instance_within_a_scope(): void
    {
        $this->assertSame(
            app()->make(YrnkEvaluator::class),
            app()->make(YrnkEvaluator::class),
        );
    }

    #[Test]
    #[DefineEnvironment('withResolverNameReference')]
    public function yrnk_parser_is_bound_knowing_the_configs_resolver_names(): void
    {
        $parser = app()->make(YrnkParser::class);

        $yrnk = $parser->parse([
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'resolvers' => ['jp-holidays'],
            'calendar' => ['holidays' => 'jp-holidays'],
            'schedules' => [['days' => ['holiday'], 'allday' => true]],
        ]);

        $this->assertSame('jp-holidays', $yrnk->calendar->holidays);
    }
}
