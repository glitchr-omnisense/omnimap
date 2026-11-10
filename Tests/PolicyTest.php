<?php

namespace Omnimap\Tests;

use Omnimap\Capabilities;
use Omnimap\Config;
use Omnimap\Exception\InvalidConfigException;
use Omnimap\Exception\NotSupportedException;
use Omnimap\Exception\OmnimapException;
use Omnimap\Exception\UsageLimitReachedException;
use Omnimap\GeocoderInterface;
use Omnimap\MapRendererInterface;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Place;
use Omnimap\Policy\GuardedGeocoder;
use Omnimap\Policy\GuardedRenderer;
use Omnimap\ProviderFactory;
use Omnimap\ProviderInterface;
use Omnimap\Registry;
use Omnimap\StaticMapInterface;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;
use Omnimap\Testing\TestTiles;
use Omnimap\TileSourceInterface;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\UsageNotPermitted;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/**
 * Usage policies through omnimap's registry: a provider's declared policy,
 * a site's tightening, the guarded contracts (Registry::guarded()) refusing
 * a call over a limit before it is made.
 */
final class PolicyTest extends TestCase
{
    public function testASiteLimitIsCountedAndTheCallOverItNeverMade(): void
    {
        $geocoder = self::geocoder(UsagePolicy::fromArray(['provider' => 'places', 'rateLimits' => []]));
        $registry = new Registry([self::factory($geocoder)], ['places' => [
            'factory' => 'places',
            'policy' => ['rateLimits' => [['max' => 2, 'window' => 'day', 'operations' => ['geocode', 'reverse']]]],
        ]]);
        $guarded = $registry->guarded('places', GeocoderInterface::class);

        self::assertInstanceOf(GuardedGeocoder::class, $guarded);
        self::assertSame($geocoder, $guarded->inner());
        self::assertCount(1, $guarded->geocode('Kyoto'));
        self::assertCount(1, $guarded->reverse(new LatLng(35.0, 135.7)));
        try {
            $guarded->geocode('Osaka');
            self::fail('A third call in a day of two.');
        } catch (UsageLimitReachedException $e) {
            self::assertInstanceOf(OmnimapException::class, $e);
            self::assertGreaterThan(0, $e->retryAfter);
        }
        self::assertSame(2, $geocoder->calls, 'the call over the limit never reached the provider');
        self::assertSame($guarded, $registry->guarded('places', GeocoderInterface::class), 'built once');
        self::assertSame($geocoder, $registry->geocoder('places'), 'geocoder() gives the provider itself, unguarded');
        self::assertSame(0, $registry->guard('places')->remaining('geocode'));
    }

    public function testADeclaredLimitHoldsEveryProcessSharingTheLimiter(): void
    {
        $policy = UsagePolicy::fromArray(['provider' => 'places', 'rateLimits' => [['max' => 1, 'window' => 'second', 'operations' => ['geocode', 'reverse']]]]);
        $geocoder = self::geocoder($policy);
        $registry = new Registry([self::factory($geocoder)], ['places' => ['factory' => 'places']]);

        $registry->guarded('places', GeocoderInterface::class)->geocode('Kyoto');
        $this->expectException(UsageLimitReachedException::class);
        $registry->guarded('places', GeocoderInterface::class)->reverse(new LatLng(35.0, 135.7));
    }

    public function testTheRendererCountsTheMapsDrawnNotItsAttribution(): void
    {
        $registry = new Registry([self::tilesFactory()], ['maps' => [
            'factory' => 'test',
            'policy' => ['rateLimits' => [['max' => 1, 'window' => 'day', 'operations' => ['describe']]]],
        ]]);
        $renderer = $registry->guarded('maps', MapRendererInterface::class);
        $view = new MapView(new LatLng(35.0, 135.7), 12);

        self::assertInstanceOf(GuardedRenderer::class, $renderer);
        self::assertSame('test', $renderer->describe($view)['provider']);
        self::assertSame($renderer->fingerprint($view), $registry->renderer('maps')->fingerprint($view));
        self::assertNotSame('', $renderer->attribution());
        $registry->guarded('maps', TileSourceInterface::class)->url(1, 0, 0);
        $registry->guarded('maps', StaticMapInterface::class);
        $this->expectException(UsageLimitReachedException::class);
        $renderer->describe($view);
    }

    public function testAContractTheProviderDoesNotKeepOrOmnimapDoesNotGuard(): void
    {
        $registry = new Registry([self::tilesFactory()], ['maps' => ['factory' => 'test']]);

        try {
            $registry->guarded('maps', GeocoderInterface::class);
            self::fail('Not a geocoder.');
        } catch (NotSupportedException $e) {
            self::assertStringContainsString('does not geocode', $e->getMessage());
        }
        $this->expectException(InvalidConfigException::class);
        $registry->guarded('maps', ProviderInterface::class);
    }

    public function testATighteningIsAcceptedALooserLimitAndACommercialSiteRefused(): void
    {
        $policy = UsagePolicy::fromArray(['provider' => 'places', 'rateLimits' => [['max' => 10, 'window' => 'minute']], 'rights' => ['commercial' => 'forbidden']]);
        $tight = new Registry([self::factory(self::geocoder($policy))], ['places' => ['factory' => 'places', 'options' => ['policy' => ['rateLimits' => [['max' => 5, 'window' => 'minute']]]]]]);

        self::assertCount(2, $tight->policy('places')->rateLimits ?? []);
        self::assertSame(['places'], array_keys($tight->policies()));

        try {
            (new Registry([self::factory(self::geocoder($policy))], ['places' => ['factory' => 'places', 'policy' => ['rateLimits' => [['max' => 50, 'window' => 'minute']]]]]))->policy('places');
            self::fail('A looser limit.');
        } catch (InvalidPolicyException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(UsageNotPermitted::class);
        (new Registry([self::factory(self::geocoder($policy))], ['places' => ['factory' => 'places', 'usage' => ['commercial' => true]]]))->guarded('places', GeocoderInterface::class);
    }

    public function testTheOlderTermsMakeAPolicyAndArePolicyRead(): void
    {
        $described = new Capabilities(renderer: true, terms: ['https://example.org/terms']);
        self::assertNull($described->policy()->rateLimits, 'limits unknown');
        self::assertSame('https://example.org/terms', $described->policy()->provenance->terms);

        $declared = new Capabilities(renderer: true, policy: UsagePolicy::fromArray(['provider' => 'x', 'provenance' => ['terms' => 'https://example.org/terms', 'policy' => 'https://example.org/policy']]));
        self::assertSame(['https://example.org/terms', 'https://example.org/policy'], $declared->terms);
        self::assertSame(['renderer'], $declared->contracts());
    }

    public function testTheConfigKnowsTheFamilysKeys(): void
    {
        $config = (new Config(['policy' => ['rateLimits' => []]]));

        self::assertSame('omnimap.factory_name', $config->key('factory_name'));
        self::assertSame(['rateLimits' => []], $config->map('policy'));
        self::assertNull($config->backoff(), 'outside a factory\'s create()');
        $this->expectException(InvalidConfigException::class);
        (new Config(['usage' => 'commercial']))->map('usage');
    }

    private static function geocoder(UsagePolicy $policy): CountingGeocoder
    {
        return new CountingGeocoder($policy);
    }

    private static function factory(ProviderInterface $provider): ProviderFactory
    {
        return new class($provider) extends ProviderFactory {
            public function __construct(private readonly ProviderInterface $provider)
            {
            }

            protected function populate(Config $c): void
            {
                $c->defaults(['omnimap.factory_name' => 'places', 'omnimap.factory_title' => 'Places']);
            }

            protected function build(Config $c): ProviderInterface
            {
                return $this->provider;
            }
        };
    }

    private static function tilesFactory(): ProviderFactory
    {
        return new class extends ProviderFactory {
            protected function populate(Config $c): void
            {
                $c->defaults(['omnimap.factory_name' => 'test', 'omnimap.factory_title' => 'Test']);
            }

            protected function build(Config $c): ProviderInterface
            {
                return new TestTiles(new RecordedHttpClient(PlainTiles::answer()));
            }
        };
    }
}

/** A geocoder that answers one place and counts the calls that reached it. */
final class CountingGeocoder implements GeocoderInterface
{
    public int $calls = 0;

    public function __construct(private readonly UsagePolicy $policy)
    {
    }

    public function getName(): string
    {
        return 'places';
    }

    public function getTitle(): string
    {
        return 'Places';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(geocoder: true, policy: $this->policy);
    }

    public function geocode(string $address, ?string $locale = null, int $limit = 5): array
    {
        ++$this->calls;

        return [new Place(new LatLng(35.0116, 135.7681), $address, 'places')];
    }

    public function reverse(LatLng $position, ?string $locale = null, int $limit = 5): array
    {
        ++$this->calls;

        return [new Place($position, 'Here', 'places')];
    }
}
