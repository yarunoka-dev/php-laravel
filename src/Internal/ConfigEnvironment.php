<?php

namespace Yarunoka\Laravel\Internal;

use DateTimeZone;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Yarunoka\Calendar\YrnkCalendar;
use Yarunoka\Calendar\YrnkCalendarParser;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Resolvers\YrnkBusinessDaysResolverInterface;
use Yarunoka\Resolvers\YrnkBusinessHolidaysResolverInterface;
use Yarunoka\Resolvers\YrnkHolidaysResolverInterface;
use Yarunoka\Resolvers\YrnkResolverContainer;

/**
 * The evaluation environment the config describes — the timezone, the
 * calendar, and the resolver bindings. Read lazily and memoized, so a DI
 * scope sees one consistent environment however many services derive
 * from it, and config set after registration (a later provider, a test)
 * is still honoured.
 *
 * The three date-set layers have a second supply besides the config: an
 * app binding of the layer interface. A binding wins over the config key
 * — it is expressed by pointing the layer at a bridge-reserved resolver
 * name bound to whatever the app registered.
 *
 * @internal
 */
final class ConfigEnvironment
{
    /**
     * The layer interfaces an app may bind, with the bridge-reserved
     * resolver name each binding is carried under.
     */
    private const array LAYER_BINDINGS = [
        'holidays' => [YrnkHolidaysResolverInterface::class, 'laravel-holidays'],
        'business_holidays' => [YrnkBusinessHolidaysResolverInterface::class, 'laravel-business-holidays'],
        'business_days' => [YrnkBusinessDaysResolverInterface::class, 'laravel-business-days'],
    ];

    private ?DateTimeZone $timezone = null;

    private ?YrnkResolverContainer $resolvers = null;

    /** @var array<string, mixed>|null */
    private ?array $rawCalendar = null;

    private ?YrnkCalendar $calendar = null;

    public function __construct(
        private readonly Container $container,
        private readonly Repository $config,
    ) {}

    public function timezone(): DateTimeZone
    {
        if ($this->timezone !== null) {
            return $this->timezone;
        }

        $name = $this->config->get('yarunoka.timezone') ?? $this->config->get('app.timezone', 'UTC');

        if (! is_string($name)) {
            throw new InvalidYrnkException('config yarunoka.timezone must be a timezone name string');
        }

        return $this->timezone = new DateTimeZone($name);
    }

    /**
     * The bindings: yasumi-{Provider} names seeded by the core, the
     * resolvers config (name => class, instantiated by the Laravel
     * container on first use), and the layer interfaces the app bound.
     */
    public function resolverContainer(): YrnkResolverContainer
    {
        if ($this->resolvers !== null) {
            return $this->resolvers;
        }

        $resolvers = new YrnkResolverContainer();
        $raw = $this->config->get('yarunoka.resolvers', []);

        if (! is_array($raw)) {
            throw new InvalidYrnkException('config yarunoka.resolvers must be an array of resolver name => class name');
        }

        foreach ($raw as $name => $class) {
            if (! is_string($class)) {
                throw new InvalidYrnkException("config yarunoka.resolvers.{$name} must be a class name string");
            }

            $resolvers->add((string) $name, new ContainerResolver($this->container, $class));
        }

        foreach (self::LAYER_BINDINGS as [$interface, $name]) {
            if ($this->container->bound($interface)) {
                $resolvers->add($name, new ContainerResolver($this->container, $interface));
            }
        }

        return $this->resolvers = $resolvers;
    }

    /**
     * The calendar config with the app's layer bindings folded in. This
     * raw form is what a document sharing the config environment embeds
     * (ScheduleCodec); the parsed form below is for answering questions.
     *
     * @return array<string, mixed>
     */
    public function rawCalendar(): array
    {
        if ($this->rawCalendar !== null) {
            return $this->rawCalendar;
        }

        $raw = $this->config->get('yarunoka.calendar', []);

        if (! is_array($raw)) {
            throw new InvalidYrnkException('config yarunoka.calendar must be an array shaped like the calendar part of a document');
        }

        foreach (self::LAYER_BINDINGS as $key => [$interface, $name]) {
            if ($this->container->bound($interface)) {
                $raw[$key] = $name;
            }
        }

        return $this->rawCalendar = $raw;
    }

    public function calendar(): YrnkCalendar
    {
        return $this->calendar ??= new YrnkCalendarParser()->parse(
            $this->rawCalendar(),
            $this->timezone(),
            $this->resolverContainer(),
        );
    }
}
