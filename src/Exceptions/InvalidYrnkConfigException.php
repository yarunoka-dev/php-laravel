<?php

namespace Yarunoka\Laravel\Exceptions;

use RuntimeException;

/**
 * A yarunoka.* config value has the wrong type. Only the shape the
 * bridge reads is its business — whether the content means something
 * valid is the engine's question, answered with the core's exceptions.
 */
class InvalidYrnkConfigException extends RuntimeException implements ExceptionInterface {}
