<?php

namespace Yarunoka\Laravel\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Validator;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Yarunoka\Laravel\Rules\ValidYrnk;
use Yarunoka\Laravel\Rules\ValidYrnkSchedules;

class RulesTest extends TestCase
{
    protected function withCompanyCalendar(Application $app): void
    {
        $this->setConfig($app, 'yarunoka.calendar', [
            'date_sets' => ['founding-day' => ['2026-10-01']],
        ]);
    }

    // ---- ValidYrnkSchedules ----

    #[Test]
    public function a_legal_schedules_part_array_passes(): void
    {
        $validator = Validator::make(
            ['schedules' => [['days' => [25], 'times' => ['10:00']]]],
            ['schedules' => ['required', new ValidYrnkSchedules()]],
        );

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function a_legal_schedules_part_json_string_passes(): void
    {
        $validator = Validator::make(
            ['schedules' => '[{"days": [25], "times": ["10:00"]}]'],
            ['schedules' => ['required', new ValidYrnkSchedules()]],
        );

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function a_structure_violation_carries_the_engines_message_onto_the_validation_error(): void
    {
        $validator = Validator::make(
            ['schedules' => [['days' => [25]]]], // neither times nor allday
            ['schedules' => [new ValidYrnkSchedules()]],
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString(
            'Exactly one of times, allday, or every is required',
            $validator->errors()->first('schedules'),
        );
    }

    #[Test]
    public function a_value_violation_carries_the_engines_message_onto_the_validation_error(): void
    {
        $validator = Validator::make(
            ['schedules' => [['days' => [32], 'times' => ['10:00']]]],
            ['schedules' => [new ValidYrnkSchedules()]],
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('32', $validator->errors()->first('schedules'));
    }

    #[Test]
    #[DefineEnvironment('withCompanyCalendar')]
    public function references_are_validated_against_the_config_environment_and_an_undefined_name_is_rejected(): void
    {
        $valid = Validator::make(
            ['schedules' => [['days' => ['founding-day'], 'allday' => true]]],
            ['schedules' => [new ValidYrnkSchedules()]],
        );
        $invalid = Validator::make(
            ['schedules' => [['days' => ['nowhere-day'], 'allday' => true]]],
            ['schedules' => [new ValidYrnkSchedules()]],
        );

        $this->assertTrue($valid->passes());
        $this->assertFalse($invalid->passes());
        $this->assertStringContainsString('nowhere-day', $invalid->errors()->first('schedules'));
    }

    #[Test]
    public function a_value_that_is_neither_an_array_nor_a_string_is_rejected(): void
    {
        $validator = Validator::make(
            ['schedules' => 12345],
            ['schedules' => [new ValidYrnkSchedules()]],
        );

        $this->assertFalse($validator->passes());
    }

    // ---- ValidYrnk ----

    #[Test]
    public function a_legal_whole_document_passes(): void
    {
        $validator = Validator::make(
            ['document' => '{"version": "1.0", "timezone": "Asia/Tokyo", "schedules": [{"days": [1], "times": ["09:00"]}]}'],
            ['document' => ['required', new ValidYrnk()]],
        );

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function a_missing_document_key_carries_the_engines_message_onto_the_validation_error(): void
    {
        $validator = Validator::make(
            ['document' => ['version' => '1.0']],
            ['document' => [new ValidYrnk()]],
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('timezone is required', $validator->errors()->first('document'));
    }
}
