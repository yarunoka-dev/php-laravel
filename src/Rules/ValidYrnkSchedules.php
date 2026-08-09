<?php

namespace Yarunoka\Laravel\Rules;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Yarunoka\Exceptions\ExceptionInterface;
use Yarunoka\Laravel\Internal\ScheduleCodec;

/**
 * The validation rule of a schedules part. Tries the construction with
 * ScheduleCodec (structure + references against the config environment)
 * and puts the engine's message on the validation error as it is. Not a
 * second system beside the JSON Schema — the implementation is the
 * authority.
 */
class ValidYrnkSchedules implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) && ! is_string($value)) {
            $fail('A schedules part must be a list or a JSON string');

            return;
        }

        try {
            Container::getInstance()->make(ScheduleCodec::class)->decodeSchedules($value);
        } catch (ExceptionInterface $e) {
            $fail($e->getMessage());
        }
    }
}
