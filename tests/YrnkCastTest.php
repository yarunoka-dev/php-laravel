<?php

namespace Yarunoka\Laravel\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yarunoka\Calendar\YrnkHolidaysDateSet;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Laravel\Exceptions\InvalidYrnkColumnException;
use Yarunoka\Laravel\Tests\Support\RoutineRecord;
use Yarunoka\Yrnk;
use Yarunoka\YrnkParser;

class YrnkCastTest extends TestCase
{
    private const string DOCUMENT_JSON = '{"version": "1.0", "timezone": "Asia/Tokyo", "calendar": {"holidays": ["2026-01-01"]}, "schedules": [{"days": ["holiday"], "allday": true}]}';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('routine_records', function (Blueprint $table): void {
            $table->id();
            $table->json('schedule')->nullable();
            $table->json('document')->nullable();
        });
    }

    /**
     * Reads the model back from the database and takes document out as a
     * Yrnk.
     */
    private function reloadedDocument(RoutineRecord $routine): Yrnk
    {
        $document = RoutineRecord::query()->findOrFail($routine->id)->document;

        if (! $document instanceof Yrnk) {
            $this->fail('The document column did not come back as a Yrnk');
        }

        return $document;
    }

    #[Test]
    public function get_answers_a_whole_document_column_as_a_bare_yrnk(): void
    {
        DB::table('routine_records')->insert(['document' => self::DOCUMENT_JSON]);

        $document = RoutineRecord::query()->firstOrFail()->document;

        if (! $document instanceof Yrnk) {
            $this->fail('A Yrnk did not come back');
        }

        $this->assertSame('Asia/Tokyo', $document->timezone->getName());
        $this->assertInstanceOf(YrnkHolidaysDateSet::class, $document->calendar->holidays);
    }

    #[Test]
    public function get_answers_a_null_column_as_null(): void
    {
        DB::table('routine_records')->insert(['document' => null]);

        $this->assertNull(RoutineRecord::query()->firstOrFail()->document);
    }

    #[Test]
    public function get_reports_a_broken_column_with_the_model_and_column_names(): void
    {
        DB::table('routine_records')->insert(['document' => '{"version": "1.0"}']);

        $this->expectException(InvalidYrnkColumnException::class);
        $this->expectExceptionMessageMatches('/document.+RoutineRecord/');

        RoutineRecord::query()->firstOrFail()->getAttribute('document');
    }

    #[Test]
    public function set_stores_an_array_and_a_json_string_with_validation(): void
    {
        $routine = RoutineRecord::query()->create(['document' => self::DOCUMENT_JSON]);

        $reloaded = $this->reloadedDocument($routine);

        $this->assertCount(1, $reloaded->schedules);

        $arrayRoutine = RoutineRecord::query()->create([
            'document' => json_decode(self::DOCUMENT_JSON, associative: true),
        ]);

        $this->assertCount(1, $this->reloadedDocument($arrayRoutine)->schedules);
    }

    #[Test]
    public function set_accepts_a_yrnk_instance_too(): void
    {
        $document = new YrnkParser()->parse(self::DOCUMENT_JSON);

        $routine = RoutineRecord::query()->create(['document' => $document]);

        $this->assertSame('Asia/Tokyo', $this->reloadedDocument($routine)->timezone->getName());
    }

    #[Test]
    public function set_stops_an_invalid_document_on_an_exception_before_the_database(): void
    {
        try {
            RoutineRecord::query()->create(['document' => ['version' => '1.0']]);
            $this->fail('No exception was thrown');
        } catch (InvalidYrnkException) {
            $this->assertSame(0, DB::table('routine_records')->count());
        }
    }

    #[Test]
    public function documents_of_different_environments_can_be_stored_per_row(): void
    {
        RoutineRecord::query()->create(['document' => self::DOCUMENT_JSON]);
        RoutineRecord::query()->create(['document' => '{"version": "1.0", "timezone": "America/New_York", "schedules": [{"days": [1], "times": ["09:00"]}]}']);

        $timezones = RoutineRecord::query()->get()->map(
            fn(RoutineRecord $routine): ?string => $routine->document?->timezone->getName(),
        );

        $this->assertSame(['Asia/Tokyo', 'America/New_York'], $timezones->all());
    }

    #[Test]
    public function the_same_content_differing_only_in_key_order_is_not_dirty(): void
    {
        $routine = RoutineRecord::query()->create(['document' => self::DOCUMENT_JSON]);

        $routine = RoutineRecord::query()->findOrFail($routine->id);
        $routine->setAttribute('document', '{"timezone": "Asia/Tokyo", "version": "1.0", "calendar": {"holidays": ["2026-01-01"]}, "schedules": [{"days": ["holiday"], "allday": true}]}');

        $this->assertFalse($routine->isDirty('document'));
    }
}
