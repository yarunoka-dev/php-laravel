<?php

namespace Yarunoka\Laravel\Internal;

use Illuminate\Contracts\Container\Container;
use Yarunoka\Laravel\Exceptions\InvalidYrnkResolverException;
use Yarunoka\Resolvers\YrnkResolverInterface;
use Yarunoka\YrnkDate;

/**
 * A resolver whose implementation the Laravel container makes on first
 * use. Lets a name be bound into a YrnkResolverContainer ahead of time (a
 * class name from the resolvers config, or a layer interface the app
 * bound) without instantiating anything until a question first reaches
 * the name. The Laravel container does not enforce binding types, so the
 * instance is guarded at that moment.
 *
 * @internal
 */
final class ContainerResolver implements YrnkResolverInterface
{
    private ?YrnkResolverInterface $resolver = null;

    public function __construct(
        private readonly Container $container,
        private readonly string $abstract,
    ) {}

    public function resolve(YrnkDate $from, YrnkDate $through): array
    {
        return ($this->resolver ??= $this->make())->resolve($from, $through);
    }

    private function make(): YrnkResolverInterface
    {
        $resolver = $this->container->make($this->abstract);

        if (! $resolver instanceof YrnkResolverInterface) {
            throw new InvalidYrnkResolverException(
                "{$this->abstract} must resolve to a YrnkResolverInterface implementation",
            );
        }

        return $resolver;
    }
}
