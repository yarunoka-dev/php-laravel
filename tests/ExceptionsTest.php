<?php

namespace Yarunoka\Laravel\Tests;

use PHPUnit\Framework\Attributes\Test;
use Yarunoka\Laravel\Exceptions\ExceptionInterface;
use Yarunoka\Laravel\Exceptions\InvalidYrnkColumnException;
use Yarunoka\Laravel\Exceptions\InvalidYrnkResolverException;

class ExceptionsTest extends TestCase
{
    private const array BRIDGE_EXCEPTIONS = [
        InvalidYrnkColumnException::class,
        InvalidYrnkResolverException::class,
    ];

    #[Test]
    public function bridge_exceptions_implement_the_bridge_interface(): void
    {
        foreach (self::BRIDGE_EXCEPTIONS as $exception) {
            $this->assertContains(ExceptionInterface::class, class_implements($exception));
        }
    }

    #[Test]
    public function the_bridge_interface_is_independent_of_the_cores(): void
    {
        $this->assertFalse(is_subclass_of(ExceptionInterface::class, \Yarunoka\Exceptions\ExceptionInterface::class));
    }

    #[Test]
    public function bridge_exceptions_are_not_part_of_the_cores_family(): void
    {
        foreach (self::BRIDGE_EXCEPTIONS as $exception) {
            $this->assertFalse(is_a($exception, \Yarunoka\Exceptions\ExceptionInterface::class, allow_string: true));
        }
    }
}
