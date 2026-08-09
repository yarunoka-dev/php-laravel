<?php

namespace Yarunoka\Laravel\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Yarunoka\Laravel\Casts\AsYrnk;
use Yarunoka\Laravel\Schedule;
use Yarunoka\Yrnk;

/**
 * A model for the cast tests. The schedules column is a schedules part
 * (named as the Castable wrapper), the document column a whole document.
 *
 * @property int $id
 * @property Schedule|null $schedules
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
            'schedules' => Schedule::class,
            'document' => AsYrnk::class,
        ];
    }
}
