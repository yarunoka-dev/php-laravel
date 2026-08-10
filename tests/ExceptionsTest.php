<?php

namespace Yarunoka\Laravel\Tests;

use PHPUnit\Framework\Attributes\Test;
use Yarunoka\Laravel\Exceptions\ExceptionInterface;
use Yarunoka\Laravel\Exceptions\InvalidYrnkColumnException;

class ExceptionsTest extends TestCase
{
    #[Test]
    public function bridge_exceptions_implement_the_bridge_interface(): void
    {
        $this->assertContains(ExceptionInterface::class, class_implements(InvalidYrnkColumnException::class));
    }

    #[Test]
    public function the_bridge_interface_is_independent_of_the_cores(): void
    {
        $this->assertFalse(is_subclass_of(ExceptionInterface::class, \Yarunoka\Exceptions\ExceptionInterface::class));
    }

    #[Test]
    public function bridge_exceptions_are_not_part_of_the_cores_family(): void
    {
        $this->assertFalse(is_a(InvalidYrnkColumnException::class, \Yarunoka\Exceptions\ExceptionInterface::class, allow_string: true));
    }
}
