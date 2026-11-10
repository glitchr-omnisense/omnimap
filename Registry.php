<?php

namespace Omnimap;

use Omnimap\Exception\InvalidConfigException;
use Omnimap\Exception\NotSupportedException;
use Omnimap\Policy\GuardedGeocoder;
use Omnimap\Policy\GuardedRenderer;
use Omnimap\Policy\GuardedStaticMap;
use Omnimap\Policy\GuardedTiles;
use Omnisense\Contracts\Policy\RateLimiterInterface;
use Omnisense\Contracts\Provider\ProviderFactoryInterface as BaseFactoryInterface;
use Omnisense\Contracts\Provider\ProviderInterface as BaseProvider;
use Omnisense\Contracts\Provider\Registry as BaseRegistry;

/**
 * The application's map providers by name, each built once from its factory
 * and options, each with its usage policy (omnisense/contracts' Registry):
 *
 *   $registry = new Registry([new GoogleFactory($http), new OpenStreetMapFactory($http, $psr18, $cache)], [
 *       'maps' => ['factory' => 'openstreetmap', 'options' => ['user_agent' => 'MySite/1.0 (+https://example.org)']],
 *       'places' => [
 *           'factory' => 'google',
 *           'options' => ['api_key' => '...'],
 *           'policy' => ['rateLimits' => [['max' => 1000, 'window' => 'day', 'operations' => ['geocode', 'reverse']]]],
 *       ],
 *   ], $limiter);
 *
 *   $registry->renderer('maps')->describe($view);
 *   $registry->geocoder('places')->geocode('Kyoto');
 *   $registry->guarded('places', GeocoderInterface::class)->geocode('Kyoto');   // counted, refused over a limit
 *
 * renderer(), staticMap(), geocoder() and tiles() give the provider itself,
 * as before; guarded() gives it behind its guard (Policy\Guarded*): each
 * call that reaches a server - describe() (a map the browser draws),
 * image(), geocode(), reverse(), url() (a tile about to be fetched) - is
 * an operation named after its method, checked against the policy's rate
 * limits, counted, and refused with Exception\UsageLimitReachedException
 * once a limit is reached. "policy" tightens the provider's policy, never
 * loosens it; "usage" says what the site is: a use the terms forbid fails
 * when the policy is first read (policy(), guard(), guarded()).
 */
final class Registry extends BaseRegistry
{
    /** @var string */
    public const PREFIX = Config::PREFIX;

    /** What each contract lets a provider do, for NotSupportedException's message. */
    private const CAPABILITY = [
        MapRendererInterface::class => 'draw a map in the browser',
        StaticMapInterface::class => 'make a map image',
        GeocoderInterface::class => 'geocode',
        TileSourceInterface::class => 'give tiles',
    ];

    /** @var array<string, ProviderInterface> the guarded contracts, by "contract name" */
    private array $guarded = [];

    /**
     * @param iterable<ProviderFactoryInterface>                                                                                         $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>, policy?: array<string, mixed>, usage?: array<string, mixed>}> $config
     * @param ?RateLimiterInterface                                                                                                      $limiter   where every provider's calls are counted: an in-memory one by default, a shared one for an application served by many processes
     */
    public function __construct(iterable $factories, array $config, ?RateLimiterInterface $limiter = null)
    {
        parent::__construct($factories, $config, $limiter);
    }

    public function get(string $name): ProviderInterface
    {
        $provider = parent::get($name);

        return $provider instanceof ProviderInterface ? $provider : throw new InvalidConfigException(\sprintf('The "%s" provider (%s) is no omnimap provider.', $name, $provider::class));
    }

    /** The provider, as a map drawn in the browser. */
    public function renderer(string $name): MapRendererInterface
    {
        return $this->contract($name, MapRendererInterface::class, self::CAPABILITY[MapRendererInterface::class]);
    }

    /** The provider, as a map image made on the server. */
    public function staticMap(string $name): StaticMapInterface
    {
        return $this->contract($name, StaticMapInterface::class, self::CAPABILITY[StaticMapInterface::class]);
    }

    /** The provider, as a geocoder. */
    public function geocoder(string $name): GeocoderInterface
    {
        return $this->contract($name, GeocoderInterface::class, self::CAPABILITY[GeocoderInterface::class]);
    }

    /** The provider, as tiles. */
    public function tiles(string $name): TileSourceInterface
    {
        return $this->contract($name, TileSourceInterface::class, self::CAPABILITY[TileSourceInterface::class]);
    }

    /**
     * The provider as one of omnimap's contracts, behind its guard: every
     * call that reaches a server counted against its usage policy.
     *
     * @template T of ProviderInterface
     *
     * @param class-string<T> $contract MapRendererInterface, StaticMapInterface, GeocoderInterface or TileSourceInterface
     *
     * @return T
     */
    public function guarded(string $name, string $contract): ProviderInterface
    {
        $guarded = $this->guarded[$contract.' '.$name] ??= match ($contract) {
            MapRendererInterface::class => new GuardedRenderer($this->renderer($name), $this->guard($name)),
            StaticMapInterface::class => new GuardedStaticMap($this->staticMap($name), $this->guard($name)),
            GeocoderInterface::class => new GuardedGeocoder($this->geocoder($name), $this->guard($name)),
            TileSourceInterface::class => new GuardedTiles($this->tiles($name), $this->guard($name)),
            default => throw new InvalidConfigException(\sprintf('No guarded "%s": omnimap guards %s.', $contract, implode(', ', array_keys(self::CAPABILITY)))),
        };

        return $guarded instanceof $contract ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /**
     * The configured providers' names, in the configuration's order; those
     * keeping a contract ("renderer", "staticMap", "geocoder", "tiles") when
     * one is named.
     *
     * @return list<string>
     */
    public function names(?string $contract = null): array
    {
        return parent::names($contract);
    }

    protected function factory(string $name): BaseFactoryInterface
    {
        $provider = $this->config[$name] ?? throw $this->invalid(\sprintf('No "%s" map provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$provider['factory']] ?? throw $this->invalid(\sprintf('No "%s" factory for the "%s" map provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }

    protected function notSupported(BaseProvider $provider, string $name, string $capability): \Throwable
    {
        return NotSupportedException::capability($provider, $name, $capability);
    }
}
