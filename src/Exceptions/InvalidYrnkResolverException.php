<?php

namespace Yarunoka\Laravel\Exceptions;

use RuntimeException;

/**
 * A bound resolver name resolved to something that is not a
 * YrnkResolverInterface implementation. The Laravel container does not
 * enforce binding types, so the mistake surfaces here — at the first
 * question that reaches the name — rather than where the binding was
 * written.
 */
class InvalidYrnkResolverException extends RuntimeException implements ExceptionInterface {}
