<?php

namespace Yarunoka\Laravel\Tests\Support;

use Yarunoka\Resolvers\YrnkHolidaysResolverInterface;
use Yarunoka\YrnkDate;

/**
 * A test resolver that answers with the dates of a HolidaySource
 * injected by the container. Counts constructions and resolutions
 * statically (for verifying laziness; reset in the test's setUp).
 */
final class InjectedHolidaysResolver implements YrnkHolidaysResolverInterface
{
    public static int $instantiations = 0;

    public static int $resolutions = 0;

    public function __construct(private readonly HolidaySource $source)
    {
        self::$instantiations++;
    }

    public function resolve(YrnkDate $from, YrnkDate $through): array
    {
        self::$resolutions++;

        // Dates outside the asked range are ignored by the engine, so
        // answering everything is legal
        return $this->source->dates;
    }

    public static function reset(): void
    {
        self::$instantiations = 0;
        self::$resolutions = 0;
    }
}
