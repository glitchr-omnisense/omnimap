<?php

namespace Omnimap;

use Omnimap\Exception\InvalidConfigException;
use Omnimap\Exception\NotSupportedException;

/**
 * The application's map providers by name, each built once from its factory
 * and options:
 *
 *   $registry = new Registry([new GoogleFactory($http), new OpenStreetMapFactory($http)], [
 *       'maps' => ['factory' => 'openstreetmap', 'options' => ['user_agent' => 'MySite/1.0 (+https://example.org)']],
 *       'places' => ['factory' => 'google', 'options' => ['api_key' => '...']],
 *   ]);
 *
 *   $registry->renderer('maps')->describe($view);
 *   $registry->geocoder('places')->geocode('Kyoto');
 */
final class Registry
{
    /** @var array<string, ProviderFactoryInterface> */
    private array $factories = [];

    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<ProviderFactoryInterface>                                     $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
    }

    public function get(string $name): ProviderInterface
    {
        return $this->providers[$name] ??= $this->factory($name)->create($this->config[$name]['options'] ?? []);
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /** The provider, as a map drawn in the browser. */
    public function renderer(string $name): MapRendererInterface
    {
        $provider = $this->get($name);

        return $provider instanceof MapRendererInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'draw a map in the browser');
    }

    /** The provider, as a map image made on the server. */
    public function staticMap(string $name): StaticMapInterface
    {
        $provider = $this->get($name);

        return $provider instanceof StaticMapInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'make a map image');
    }

    /** The provider, as a geocoder. */
    public function geocoder(string $name): GeocoderInterface
    {
        $provider = $this->get($name);

        return $provider instanceof GeocoderInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'geocode');
    }

    /** The provider, as tiles. */
    public function tiles(string $name): TileSourceInterface
    {
        $provider = $this->get($name);

        return $provider instanceof TileSourceInterface ? $provider : throw NotSupportedException::capability($provider, $name, 'give tiles');
    }

    /** @return array<string, mixed> the options a provider is configured with */
    public function options(string $name): array
    {
        return $this->config[$name]['options'] ?? [];
    }

    /** @return list<string> the configured providers' names */
    public function names(): array
    {
        return array_keys($this->config);
    }

    /** @return list<string> the factories installed: what `factory:` may name */
    public function factories(): array
    {
        return array_keys($this->factories);
    }

    private function factory(string $name): ProviderFactoryInterface
    {
        $provider = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" map provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$provider['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" map provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }
}
