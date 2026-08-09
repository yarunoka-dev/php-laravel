<?php

namespace Yarunoka\Laravel\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Yarunoka\Laravel\Casts\AsYrnk;
use Yarunoka\Laravel\Schedule;
use Yarunoka\Laravel\Schedules;
use Yarunoka\Yrnk;

/**
 * A model for the cast tests. The schedule column holds one schedule,
 * the schedules column a schedules part, the document column a whole
 * document (the wrappers are Castable, so casts() names them directly).
 *
 * @property int $id
 * @property Schedule|null $schedule
 * @property Schedules|null $schedules
 * @property Yrnk|null $document
 */
class RoutineRecord extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schedule' => Schedule::class,
            'schedules' => Schedules::class,
            'document' => AsYrnk::class,
        ];
    }
}
