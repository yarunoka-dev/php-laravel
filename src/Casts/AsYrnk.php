<?php

namespace Yarunoka\Laravel\Casts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Yarunoka\Exceptions\ExceptionInterface;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Laravel\Exceptions\InvalidYrnkColumnException;
use Yarunoka\Yrnk;
use Yarunoka\YrnkBuilder;
use Yarunoka\YrnkParser;

/**
 * The Eloquent cast of a whole-document column (the JSON of a Yrnk
 * document), for storing a timezone / calendar per row. get answers a
 * bare Yrnk — a whole-document column stores "such a document" and grows
 * no judgment conveniences. Whoever wants to judge builds a
 * YrnkEvaluator from the document's calendar / timezone.
 *
 * @implements CastsAttributes<Yrnk|null, Yrnk|array<mixed>|string|null>
 */
final class AsYrnk implements CastsAttributes, ComparesCastableAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Yrnk
    {
        if ($value === null) {
            return null;
        }

        try {
            if (! is_string($value)) {
                throw new InvalidYrnkException('The column value is not a JSON string: ' . get_debug_type($value));
            }

            return Container::getInstance()->make(YrnkParser::class)->parse($value);
        } catch (ExceptionInterface $e) {
            throw new InvalidYrnkColumnException(
                sprintf('The %s column of %s does not hold a readable Yrnk document: %s', $key, $model::class, $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Yrnk) {
            // TSet's phpdoc is the consumer-facing type; Eloquent hands
            // over mixed at runtime
            // @phpstan-ignore booleanAnd.alwaysFalse, function.alreadyNarrowedType
            if (! is_array($value) && ! is_string($value)) {
                throw new InvalidYrnkException(
                    'A whole-document column takes a Yrnk, an array, or a JSON string: ' . get_debug_type($value),
                );
            }

            // The storing path always passes construction validation (an
            // invalid document never reaches the database)
            $value = Container::getInstance()->make(YrnkParser::class)->parse($value);
        }

        return new YrnkBuilder()->toJson($value);
    }

    /**
     * JSON varies in key order and whitespace as a string, so the decoded
     * arrays are compared to avoid a false dirty (a pointless UPDATE).
     */
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return $this->normalize($firstValue) == $this->normalize($secondValue);
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return json_decode($value, associative: true) ?? $value;
    }
}
