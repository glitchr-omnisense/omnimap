<?php

namespace Omnimap;

use Omnimap\Exception\InvalidConfigException;
use Omnimap\Exception\UsageLimitReachedException;
use Omnisense\Contracts\Provider\Config as BaseConfig;
use Omnisense\Contracts\Provider\ProviderFactory as BaseFactory;

/**
 * A provider's factory fills a Config - its name ("omnimap.factory_name"),
 * its title ("omnimap.factory_title"), the options it needs
 * ("omnimap.required_options") and the defaults of the others - then builds
 * the provider from it. omnisense/contracts' factory underneath gives the
 * Config the provider's Backoff (Config::backoff()) and, once built, its
 * PolicyGuard (guard()).
 *
 * Nothing here calls anything: a factory whose provider is reached over
 * HTTP takes its client in its own constructor - the application's, a
 * MockHttpClient in a test.
 */
abstract class ProviderFactory extends BaseFactory implements ProviderFactoryInterface
{
    public function create(array $options = []): ProviderInterface
    {
        $provider = parent::create($options);

        return $provider instanceof ProviderInterface ? $provider : throw new InvalidConfigException(\sprintf('The "%s" factory built no omnimap provider.', $this->getName()));
    }

    /** @param array<string, mixed> $options */
    public function createConfig(array $options = []): Config
    {
        return self::own(parent::createConfig($options));
    }

    /** The factory's name and title, its required options, the defaults of the others. */
    abstract protected function populate(Config $c): void;

    /** The provider, from a Config that holds everything it needs. */
    abstract protected function build(Config $c): ProviderInterface;

    protected function newConfig(array $options): Config
    {
        return new Config($options);
    }

    protected function limitException(): string
    {
        return UsageLimitReachedException::class;
    }

    final protected function configure(BaseConfig $c): void
    {
        $this->populate(self::own($c));
    }

    final protected function make(BaseConfig $c): ProviderInterface
    {
        return $this->build(self::own($c));
    }

    private static function own(BaseConfig $c): Config
    {
        return $c instanceof Config ? $c : throw new InvalidConfigException('An omnimap factory builds from omnimap\'s Config.');
    }
}
