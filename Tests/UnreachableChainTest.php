<?php

namespace Omnimap\Tests;

use Geocoder\Collection;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Nyholm\Psr7\Request;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Omnimap\Capabilities;
use Omnimap\Exception\OmnimapException;
use Omnimap\Exception\UnreachableException;
use Omnimap\Geocoding\GeocoderPhp;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;
use Omnimap\Static\TileStitcher;
use Omnimap\TileSourceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Nothing of a key anywhere in what a log of a failed call writes: the messages (a tile's URL, a
 * geocoder's request), the previous exceptions (copies of the clients', their class kept, the
 * key's parameter masked), and the traces' arguments, which PHP keeps unless
 * zend.exception_ignore_args is on.
 */
final class UnreachableChainTest extends TestCase
{
    private string|false $ignoreArgs = false;

    protected function setUp(): void
    {
        $this->ignoreArgs = ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', false !== $this->ignoreArgs ? $this->ignoreArgs : '1');
    }

    public function testAnUnreachableTileServerHoldsNoKey(): void
    {
        $http = new MockHttpClient(static fn (string $method, string $url) => new MockResponse('', ['error' => \sprintf('Could not resolve host: tiles.example for "%s".', $url)]));
        $tiles = new class implements TileSourceInterface {
            public function getName(): string
            {
                return 'keyed';
            }

            public function getTitle(): string
            {
                return 'Keyed tiles';
            }

            public function capabilities(): Capabilities
            {
                return new Capabilities(tiles: true, browserHosts: ['tiles.example']);
            }

            public function template(): string
            {
                return 'https://tiles.example/{z}/{x}/{y}.png?key=tile-key-123';
            }

            public function url(int $zoom, int $x, int $y): string
            {
                return "https://tiles.example/$zoom/$x/$y.png?key=tile-key-123";
            }

            public function tileSize(): int
            {
                return 256;
            }

            public function maxZoom(): int
            {
                return 19;
            }

            public function attribution(): string
            {
                return '&copy; Keyed tiles';
            }

            public function headers(): array
            {
                return ['Authorization' => 'Bearer tile-token-456'];
            }
        };

        try {
            (new TileStitcher($tiles, $http))->image(new MapView(new LatLng(35.0116, 135.7681), 12), new Size(300, 200));
            self::fail('no exception');
        } catch (UnreachableException $e) {
            self::assertInstanceOf(TransportException::class, $e->getPrevious());
            self::assertStringContainsString('key=***', $e->getMessage());
            self::assertClean($e, ['tile-key-123', 'tile-token-456']);
        }
    }

    public function testAnUnreachableGeocoderHoldsNoKey(): void
    {
        $provider = new class implements Provider {
            public function geocodeQuery(GeocodeQuery $query): Collection
            {
                return $this->send(new Request('GET', 'https://maps.example/geocode/json?address=Kyoto&key=geocoder-key-123'));
            }

            public function reverseQuery(ReverseQuery $query): Collection
            {
                return $this->send(new Request('GET', 'https://maps.example/geocode/json?latlng=35,135&key=geocoder-key-123'));
            }

            public function getName(): string
            {
                return 'keyed';
            }

            private function send(Request $request): never
            {
                throw new NetworkFailure('Could not resolve host: maps.example for "'.$request->getUri().'".', $request);
            }
        };

        try {
            (new GeocoderPhp($provider))->geocode('Kyoto');
            self::fail('no exception');
        } catch (UnreachableException $e) {
            self::assertInstanceOf(NetworkFailure::class, $e->getPrevious());
            self::assertClean($e, ['geocoder-key-123']);
        }
    }

    /** @param list<string> $secrets */
    private static function assertClean(OmnimapException&\Throwable $e, array $secrets): void
    {
        self::assertNotEmpty($e->getTrace());
        for ($one = $e; null !== $one; $one = $one->getPrevious()) {
            foreach ($secrets as $secret) {
                self::assertStringNotContainsString($secret, $one->getMessage());
                self::assertStringNotContainsString($secret, print_r($one->getTrace(), true));
                self::assertStringNotContainsString($secret, $one->getTraceAsString());
            }
        }
    }
}

/** A PSR-18 client's network failure, its request (the key in its query) kept, as Symfony's Psr18NetworkException is. */
final class NetworkFailure extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(string $message, private readonly RequestInterface $request)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
