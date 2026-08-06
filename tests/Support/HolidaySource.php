<?php

namespace Yarunoka\Laravel\Tests\Support;

/**
 * A service injected into a resolver, for verifying constructor
 * injection.
 */
final class HolidaySource
{
    /**
     * @param  list<string>  $dates
     */
    public function __construct(public array $dates = []) {}
}
