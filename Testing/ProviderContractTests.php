<?php

namespace Omnimap\Testing;

use Omnimap\Exception\InvalidConfigException;
use Omnimap\Exception\NotSupportedException;
use Omnimap\Exception\OmnimapException;
use Omnimap\Exception\ProviderException;
use Omnimap\Exception\UnreachableException;
use Omnimap\GeocoderInterface;
use Omnimap\MapRendererInterface;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\Model\Place;
use Omnimap\Model\Size;
use Omnimap\ProviderFactory;
use Omnimap\ProviderFactoryInterface;
use Omnimap\ProviderInterface;
use Omnimap\Registry;
use Omnimap\StaticMapInterface;
use Omnimap\TileSourceInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What every omnimap provider does, as PHPUnit tests: a provider's test case
 * uses this trait and gives its factory, built over the HTTP client the suite
 * hands it (the provider wraps it in a PSR-18 client for geocoder-php when it
 * geocodes). No test reaches the network: every answer is recorded.
 *
 *   final class OpenStreetMapContractTest extends TestCase
 *   {
 *       use ProviderContractTests;
 *
 *       protected function contractFactory(?HttpClientInterface $http): ProviderFactoryInterface
 *       {
 *           return new OpenStreetMapFactory($http, $http ? new Psr18Client($http) : null, new ArrayCache());
 *       }
 *
 *       protected function contractOptions(): array { return ['user_agent' => 'tests/1.0', 'nominatim_interval' => 0]; }
 *   }
 *
 * What it checks:
 * - the factory's name is the provider's, the Registry builds it from {factory, options} once;
 *   a missing required option is refused with InvalidConfigException naming it;
 * - capabilities() says exactly what the provider answers: a declared contract is implemented,
 *   an implemented one declared off (no client given) throws NotSupportedException, never a
 *   TypeError; browser hosts are host names, terms are https addresses;
 * - a renderer's description is plain data (JSON and back) naming the provider, the view and
 *   the consent; every address in it is on a declared browser host; its fingerprint is stable
 *   and follows the centre, the zoom and the markers;
 * - a static map is an image: bytes that are the media type it says, no larger than asked;
 * - a tile source's addresses are complete https URLs, its sizes and zooms sane;
 * - a geocoder's places are places of this provider, within the limit, on the globe;
 * - errors are the family's: an answer other than 200 or a body that is not what was asked
 *   is a ProviderException, a transport failure an UnreachableException.
 *
 * Requires phpunit/phpunit and symfony/http-client (the provider's require-dev).
 */
trait ProviderContractTests
{
    /** The factory, built over the given client (null: a provider without one - its network capabilities off). */
    abstract protected function contractFactory(?HttpClientInterface $http): ProviderFactoryInterface;

    /**
     * Options that switch on every capability the provider has (fake keys, a User-Agent...).
     *
     * @return array<string, mixed>
     */
    protected function contractOptions(): array
    {
        return [];
    }

    /**
     * The recorded answer to a request that succeeds: [status, body, headers]. The default
     * answers every request with a PNG tile; a geocoder gives its service's recorded answer
     * for its geocoding URLs.
     *
     * @param array<string, mixed> $options
     *
     * @return array{0: int, 1: string, 2: array<string, string|list<string>>}
     */
    protected function contractAnswer(string $method, string $url, array $options): array
    {
        return [200, PlainTiles::png(1, 0, 0), ['content-type' => 'image/png', 'cache-control' => 'max-age=3600']];
    }

    /** A free-text question the recorded geocoding answer answers. */
    protected function contractGeocodeQuery(): string
    {
        return 'Osaka, Japan';
    }

    /** @var list<string> */
    private array $contractUrls = [];

    private function contractProvider(?\Closure $answer = null): ProviderInterface
    {
        $this->contractUrls = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($answer): MockResponse {
            $this->contractUrls[] = $url;
            if (null !== $answer) {
                $response = $answer($method, $url, $options);
                \assert($response instanceof MockResponse);

                return $response;
            }
            /** @var array<string, mixed> $options */
            [$status, $body, $headers] = $this->contractAnswer($method, $url, $options);

            return new MockResponse($body, ['http_code' => $status, 'response_headers' => $headers]);
        });

        return $this->contractFactory($http)->create($this->contractOptions());
    }

    private function contractView(): MapView
    {
        return new MapView(new LatLng(34.6937, 135.5023), 11, [new Marker(new LatLng(34.7025, 135.4959), 'Umeda')], id: 'contract');
    }

    public function testTheFactoryNamesWhatItBuildsAndTheRegistryBuildsItOnce(): void
    {
        $factory = $this->contractFactory(null);
        $provider = $factory->create($this->contractOptions());

        self::assertSame($factory->getName(), $provider->getName());
        self::assertMatchesRegularExpression('/^[a-z0-9][a-z0-9_-]*$/', $provider->getName(), 'a short name, as configured');
        self::assertNotSame('', trim($provider->getTitle()));

        $registry = new Registry([$factory], ['maps' => ['factory' => $factory->getName(), 'options' => $this->contractOptions()]]);
        self::assertSame($provider->getName(), $registry->get('maps')->getName());
        self::assertSame($registry->get('maps'), $registry->get('maps'), 'built once');
    }

    public function testAMissingRequiredOptionIsRefusedByName(): void
    {
        $factory = $this->contractFactory(null);
        $required = $factory instanceof ProviderFactory ? $factory->createConfig($this->contractOptions())->get('omnimap.required_options', []) : [];
        $required = \is_array($required) ? array_values(array_filter($required, 'is_string')) : [];
        if ([] === $required) {
            self::assertInstanceOf(ProviderInterface::class, $factory->create([]), 'no required option: built with none');

            return;
        }
        foreach ($required as $option) {
            $options = $this->contractOptions();
            unset($options[$option]);
            try {
                $factory->create($options);
                self::fail(\sprintf('Built without its required "%s".', $option));
            } catch (InvalidConfigException $e) {
                self::assertStringContainsString($option, $e->getMessage());
            }
        }
    }

    public function testCapabilitiesAreWhatTheProviderAnswers(): void
    {
        foreach ([$this->contractProvider(), $this->contractFactory(null)->create($this->contractOptions())] as $provider) {
            $capabilities = $provider->capabilities();
            self::assertNotSame([], $capabilities->contracts(), 'a provider answers at least one contract');
            foreach (['renderer' => MapRendererInterface::class, 'staticMap' => StaticMapInterface::class, 'geocoder' => GeocoderInterface::class, 'tiles' => TileSourceInterface::class] as $capability => $interface) {
                if ($capabilities->{$capability}) {
                    self::assertInstanceOf($interface, $provider, \sprintf('"%s" declared', $capability));
                }
            }
            foreach ($capabilities->browserHosts as $host) {
                self::assertMatchesRegularExpression('/^[a-z0-9.-]+(:\d+)?$/i', $host, 'a host name, no scheme, no path');
            }
            foreach ($capabilities->terms as $terms) {
                self::assertStringStartsWith('https://', $terms);
            }
            self::assertSame($capabilities->toArray(), json_decode((string) json_encode($capabilities->toArray()), true));
        }

        // Built without a client: what it implements but declares off says so, before any request.
        $bare = $this->contractFactory(null)->create($this->contractOptions());
        if ($bare instanceof StaticMapInterface && !$bare->capabilities()->staticMap) {
            $this->assertNotSupported(fn () => $bare->image($this->contractView(), new Size(100, 100)));
        }
        if ($bare instanceof GeocoderInterface && !$bare->capabilities()->geocoder) {
            $this->assertNotSupported(fn () => $bare->geocode($this->contractGeocodeQuery()));
            $this->assertNotSupported(fn () => $bare->reverse(new LatLng(34.6937, 135.5023)));
        }
    }

    public function testARendererDescribesTheViewAsPlainData(): void
    {
        $provider = $this->contractProvider();
        if (!$provider instanceof MapRendererInterface || !$provider->capabilities()->renderer) {
            self::assertFalse($provider->capabilities()->renderer);

            return;
        }
        $view = $this->contractView();
        $description = $provider->describe($view);

        self::assertSame($provider->getName(), $description['provider'] ?? null, 'the driver omnimap.js picks');
        self::assertIsString($description['consent'] ?? null, 'the consent feature the map waits for');
        self::assertSame($view->toArray(), $description['view'] ?? null);
        self::assertIsString($description['attribution'] ?? null);
        self::assertSame($description['attribution'], $provider->attribution());
        $json = json_encode($description, \JSON_THROW_ON_ERROR);
        self::assertSame($json, json_encode(json_decode($json), \JSON_THROW_ON_ERROR), 'plain data: JSON and back');

        $hosts = $provider->capabilities()->browserHosts;
        foreach ($this->contractAddresses($description) as $address) {
            $host = (string) parse_url(str_replace('{s}', 'a', $address), \PHP_URL_HOST);
            self::assertTrue(\in_array($host, $hosts, true) || \in_array(preg_replace('/^a\./', '{s}.', $host), $hosts, true), \sprintf('%s: a host the browser reaches is declared in capabilities()->browserHosts (%s)', $address, implode(', ', $hosts)));
        }
        self::assertSame([], $this->contractUrls, 'describing reaches nothing');

        $fingerprint = $provider->fingerprint($view);
        self::assertSame($fingerprint, $provider->fingerprint($this->contractView()), 'stable');
        self::assertNotSame($fingerprint, $provider->fingerprint(new MapView(new LatLng(35.0116, 135.7681), 11, $view->markers, id: 'contract')), 'follows the centre');
        self::assertNotSame($fingerprint, $provider->fingerprint(new MapView($view->center, 12, $view->markers, id: 'contract')), 'follows the zoom');
        self::assertNotSame($fingerprint, $provider->fingerprint($view->withMarkers([])), 'follows the markers');
    }

    public function testAStaticMapIsAnImageOfTheSizeAsked(): void
    {
        $provider = $this->contractProvider();
        if (!$provider instanceof StaticMapInterface || !$provider->capabilities()->staticMap) {
            self::assertFalse($provider->capabilities()->staticMap);

            return;
        }
        $image = $provider->image($this->contractView(), new Size(300, 200));

        self::assertNotSame('', $image->bytes);
        self::assertStringStartsWith('image/', $image->mimeType);
        $info = getimagesizefromstring($image->bytes);
        self::assertIsArray($info, 'the bytes are an image');
        self::assertSame($image->mimeType, $info['mime']);
        self::assertLessThanOrEqual(300, $image->size->width);
        self::assertLessThanOrEqual(200, $image->size->height);
        self::assertNotSame([], $this->contractUrls);
        foreach ($this->contractUrls as $url) {
            self::assertStringStartsWith('https://', $url);
        }
    }

    public function testTileAddressesAreComplete(): void
    {
        $provider = $this->contractProvider();
        if (!$provider instanceof TileSourceInterface || !$provider->capabilities()->tiles) {
            self::assertFalse($provider->capabilities()->tiles);

            return;
        }
        foreach (['{z}', '{x}', '{y}'] as $placeholder) {
            self::assertStringContainsString($placeholder, $provider->template());
        }
        $url = $provider->url(11, 1798, 812);
        self::assertStringStartsWith('https://', $url);
        self::assertStringNotContainsString('{', $url);
        self::assertStringContainsString('1798', $url);
        self::assertContains($provider->tileSize(), [256, 512]);
        self::assertGreaterThanOrEqual(1, $provider->maxZoom());
        self::assertLessThanOrEqual(24, $provider->maxZoom());
        $provider->headers(); // with the contract's options, no configuration missing
    }

    public function testAGeocoderAnswersPlacesOfItsOwn(): void
    {
        $provider = $this->contractProvider();
        if (!$provider instanceof GeocoderInterface || !$provider->capabilities()->geocoder) {
            self::assertFalse($provider->capabilities()->geocoder);

            return;
        }
        $places = $provider->geocode($this->contractGeocodeQuery(), 'en', 1);
        self::assertNotSame([], $places, 'the recorded answer has a place');
        self::assertLessThanOrEqual(1, \count($places), 'the limit kept');
        foreach ($places as $place) {
            self::assertInstanceOf(Place::class, $place);
            self::assertNotSame('', $place->providedBy);
            self::assertGreaterThanOrEqual(-90, $place->position->lat);
            self::assertLessThanOrEqual(90, $place->position->lat);
            self::assertGreaterThanOrEqual(-180, $place->position->lng);
            self::assertLessThanOrEqual(180, $place->position->lng);
        }
        self::assertLessThanOrEqual(1, \count($provider->reverse($places[0]->position, 'en', 1)), 'reverse: the limit kept');
    }

    public function testErrorsAreTheFamilysExceptions(): void
    {
        $probe = $this->contractProvider();
        $calls = [];
        if ($probe instanceof StaticMapInterface && $probe->capabilities()->staticMap) {
            $calls['image'] = fn (ProviderInterface $p) => $p instanceof StaticMapInterface ? $p->image($this->contractView(), new Size(300, 200)) : null;
        }
        if ($probe instanceof GeocoderInterface && $probe->capabilities()->geocoder) {
            $calls['geocode'] = fn (ProviderInterface $p) => $p instanceof GeocoderInterface ? $p->geocode($this->contractGeocodeQuery().' '.bin2hex(random_bytes(3))) : null;
        }
        if ([] === $calls) {
            self::assertSame([], $this->contractUrls, 'a provider that fetches nothing on the server has no network error');

            return;
        }
        $answers = [
            '401' => static fn () => new MockResponse('{"error":"unauthorized"}', ['http_code' => 401, 'response_headers' => ['content-type' => 'application/json']]),
            '403' => static fn () => new MockResponse('Forbidden', ['http_code' => 403]),
            '404' => static fn () => new MockResponse('Not found', ['http_code' => 404]),
            '429' => static fn () => new MockResponse('Too many requests', ['http_code' => 429, 'response_headers' => ['retry-after' => '30']]),
            '500' => static fn () => new MockResponse('Internal error', ['http_code' => 500]),
            '503' => static fn () => new MockResponse('Unavailable', ['http_code' => 503]),
            'html' => static fn () => new MockResponse('<!doctype html><title>Proxy</title>', ['http_code' => 200, 'response_headers' => ['content-type' => 'text/html']]),
            'unreachable' => static fn () => new MockResponse('', ['error' => 'Could not resolve host']),
        ];
        foreach ($calls as $operation => $call) {
            foreach ($answers as $case => $answer) {
                $provider = $this->contractProvider($answer);
                try {
                    $result = $call($provider);
                    self::fail(\sprintf('%s() answered %s on "%s" instead of throwing.', $operation, get_debug_type($result), $case));
                } catch (OmnimapException $e) {
                    $expected = 'unreachable' === $case ? UnreachableException::class : ProviderException::class;
                    self::assertInstanceOf($expected, $e, \sprintf('%s() on "%s": %s', $operation, $case, $e->getMessage()));
                }
            }
        }
    }

    private function assertNotSupported(\Closure $call): void
    {
        try {
            $call();
            self::fail('A capability declared off answered.');
        } catch (NotSupportedException) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * Every http(s) address a description holds, but in its attribution (links, not fetched).
     *
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private function contractAddresses(array $data): array
    {
        $addresses = [];
        foreach ($data as $key => $value) {
            if ('attribution' === $key) {
                continue;
            }
            if (\is_array($value)) {
                array_push($addresses, ...$this->contractAddresses($value));
            } elseif (\is_string($value) && preg_match('#^(https?:)?//#', $value)) {
                $addresses[] = str_starts_with($value, '//') ? 'https:'.$value : $value;
            }
        }

        return $addresses;
    }
}
