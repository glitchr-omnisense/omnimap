<?php

namespace Omnimap\Tests;

use Omnimap\Config;
use Omnimap\ProviderFactory;
use Omnimap\ProviderFactoryInterface;
use Omnimap\ProviderInterface;
use Omnimap\Testing\ArrayCache;
use Omnimap\Testing\ProviderContractTests;
use Omnimap\Testing\TestTiles;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The conformance suite on the core's own test provider: the suite checked before any provider runs it. */
final class TestTilesContractTest extends TestCase
{
    use ProviderContractTests;

    protected function contractFactory(?HttpClientInterface $http): ProviderFactoryInterface
    {
        return new class($http) extends ProviderFactory {
            public function __construct(private readonly ?HttpClientInterface $http)
            {
            }

            protected function populate(Config $c): void
            {
                $c->defaults(['omnimap.factory_name' => 'test', 'omnimap.factory_title' => 'Test tiles', 'omnimap.required_options' => []]);
            }

            protected function build(Config $c): ProviderInterface
            {
                return new TestTiles($this->http ?? new MockHttpClient(), new ArrayCache());
            }
        };
    }
}
