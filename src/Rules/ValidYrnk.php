<?php

namespace Yarunoka\Laravel\Rules;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Yarunoka\Exceptions\ExceptionInterface;
use Yarunoka\YrnkParser;

/**
 * The validation rule of a whole document (a Yrnk document). Tries the
 * construction with YrnkParser (carrying the config's resolvers) and
 * puts the engine's message on the validation error as it is.
 */
class ValidYrnk implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) && ! is_string($value)) {
            $fail('A Yrnk document must be an object or a JSON string');

            return;
        }

        try {
            Container::getInstance()->make(YrnkParser::class)->parse($value);
        } catch (ExceptionInterface $e) {
            $fail($e->getMessage());
        }
    }
}
