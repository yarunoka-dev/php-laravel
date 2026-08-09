<?php

namespace Yarunoka\Laravel\Exceptions;

use RuntimeException;
use Yarunoka\Exceptions\ExceptionInterface;

/**
 * The data a column held cannot be read as Yrnk. The cast's get runs in
 * the middle of model retrieval or serialization — a place where the
 * cause is hard to see — so the message names the model and the column.
 */
class InvalidYrnkColumnException extends RuntimeException implements ExceptionInterface {}
