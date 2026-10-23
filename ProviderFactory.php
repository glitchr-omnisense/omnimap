<?php

namespace Omnimap;

/**
 * A provider's factory fills a Config - its name ("omnimap.factory_name"),
 * its title ("omnimap.factory_title"), the options it needs
 * ("omnimap.required_options") and the defaults of the others - then builds
 * the provider from it.
 *
 * Nothing here calls anything: a factory whose provider is reached over
 * HTTP takes its client in its own constructor - the application's, a
 * MockHttpClient in a test.
 */
abstract class ProviderFactory implements ProviderFactoryInterface
{
    public function getName(): string
    {
        return $this->createConfig()->string('omnimap.factory_name') ?? static::class;
    }

    public function create(array $options = []): ProviderInterface
    {
        $config = $this->createConfig($options);
        $required = $config->get('omnimap.required_options', []);
        $config->validateNotEmpty(\is_array($required) ? array_values(array_filter($required, 'is_string')) : []);

        return $this->build($config);
    }

    /** @param array<string, mixed> $options */
    public function createConfig(array $options = []): Config
    {
        $config = new Config($options);
        $this->populate($config);

        return $config;
    }

    /** The factory's name and title, its required options, the defaults of the others. */
    abstract protected function populate(Config $c): void;

    /** The provider, from a Config that holds everything it needs. */
    abstract protected function build(Config $c): ProviderInterface;
}
