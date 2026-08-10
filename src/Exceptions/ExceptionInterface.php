<?php

namespace Yarunoka\Laravel\Exceptions;

use Throwable;

/**
 * Every exception the bridge itself raises — a failure of the wiring
 * between Laravel and Yarunoka, not of the engine. Deliberately
 * independent of the core's ExceptionInterface: the bridge holds no
 * core functionality, so "the integration failed" (this interface) and
 * "the DSL or the engine failed" (the core's) stay distinguishable to a
 * caller, and a union catch covers both.
 */
interface ExceptionInterface extends Throwable {}
