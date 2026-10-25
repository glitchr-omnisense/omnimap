---
title: Testing
order: 60
---

# Testing

Testing an application that shows maps with omnimap, and the conformance suite every provider
runs. Everything in `Omnimap\Testing` ships with the core (it is autoloaded with it), so a test
needs no network and no other package; `ProviderContractTests` needs `phpunit/phpunit` and
`symfony/http-client`, the image helpers GD.

## The fakes

| Class | |
|---|---|
| `TestTiles(HttpClientInterface $http, ?CacheInterface $cache = null)` | a provider (name `test`, title `Test tiles`): a renderer, a static map (through `TileStitcher`) and a tile source on `https://tiles.example/{z}/{x}/{y}.png`, browser host `tiles.example`, attribution `&copy; Test tiles`, User-Agent `omnimap-tests/1.0 (+https://github.com/glitchr-studio/omnimap)`; no factory |
| `PlainTiles::png(int $zoom, int $x, int $y, int $size = 256): string` | a PNG tile of one colour per zoom, x and y, its coordinates written on it |
| `PlainTiles::answer(): \Closure` | the answer `RecordedHttpClient` gives for any URL ending in `/{z}/{x}/{y}.png`: that tile, with `Cache-Control: max-age=91080, stale-while-revalidate=604800, stale-if-error=604800`; a 404 otherwise |
| `RecordedHttpClient(\Closure $answer)` | a Symfony HTTP client answering from a closure of the method, the URL and the options, returning `[status, body, headers]`; every request kept in `$requests` (`method`, `url`, `options`); `stream()` is not supported |
| `RecordedResponse` | its responses: `getStatusCode()`, `getHeaders()` (names lowercased), `getContent()`, `toArray()` (`[]` when the body is not a JSON object), never throwing |
| `ArrayCache` | a PSR-16 cache in memory; `$ttls` keeps the lifetime each key was set with (a `\DateInterval` read as seconds) |
| `FixedGeocoder(array $places)` | a geocoder-php provider (name `fixed`) that knows places by their exact text: `['Kyoto' => ['lat' => ..., 'lng' => ..., 'locality' => ..., 'country' => ..., 'countryCode' => ...]]`; reverse finds a place within 0.01 degree; `$calls` counts the questions |

Symfony's `MockHttpClient` and `MockResponse` work as well wherever a provider takes an HTTP
client (status codes, transport errors: `new MockResponse('', ['error' => '...'])`).

## An application's test

```php
<?php

use Omnimap\Geocoding\GeocoderPhp;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\OpenStreetMap\OpenStreetMapProvider;
use Omnimap\Signer;
use Omnimap\Snapshot\DirectoryStorage;
use Omnimap\Snapshot\Snapshots;
use Omnimap\Snapshot\SnapshotStore;
use Omnimap\Testing\ArrayCache;
use Omnimap\Testing\FixedGeocoder;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;
use Omnimap\Testing\TestTiles;
use PHPUnit\Framework\TestCase;

final class StoreMapTest extends TestCase
{
    public function testTheStoresMapIsMadeOnceAndKept(): void
    {
        $http = new RecordedHttpClient(PlainTiles::answer());          // every tile drawn here, no network
        $tiles = new TestTiles($http, new ArrayCache());
        $snapshots = new Snapshots($tiles, new Signer('c2VjcmV0'), new SnapshotStore(new DirectoryStorage(sys_get_temp_dir().'/maps-test')), $tiles);
        $view = new MapView(new LatLng(48.5818, 7.7509), 14, id: 'store');

        $snapshot = $snapshots->findOrMake($view);
        self::assertSame([1512, 938, 3, 2], [$snapshot->width, $snapshot->height, $snapshot->xtiles, $snapshot->ytiles]);
        self::assertCount(35, $http->requests);

        self::assertEquals($snapshot, $snapshots->findOrMake($view));    // read back, not made again
        self::assertCount(35, $http->requests);
        $snapshots->delete($snapshot->signature);
    }

    public function testTheStoresAddressIsGeocoded(): void
    {
        $places = new FixedGeocoder(['1 place de la Cathédrale, Strasbourg' => ['lat' => 48.5818, 'lng' => 7.7509, 'locality' => 'Strasbourg', 'country' => 'France', 'countryCode' => 'FR']]);
        $geocoder = new OpenStreetMapProvider(geocoder: new GeocoderPhp($places, new ArrayCache()));

        $found = $geocoder->geocode('1 place de la Cathédrale, Strasbourg');
        self::assertSame('Strasbourg, France', $found[0]->label);
        self::assertSame('fixed', $found[0]->providedBy);
        self::assertSame([], $geocoder->geocode('nowhere'));
        $geocoder->geocode('1 place de la Cathédrale, Strasbourg');
        self::assertSame(2, $places->calls);                             // the repeated question answered by the cache
    }
}
```

```
$ vendor/bin/phpunit --testdox tests/
Store Map
 ✔ The stores map is made once and kept
 ✔ The stores address is geocoded

Tile Server Down
 ✔ A tile server down is a provider exception

OK (3 tests, 9 assertions)
```

(run on 2026-10-25 in the [harness](harness.md), PHPUnit 11.5, with `TileServerDownTest` below)

### A geocoder that knows its answers

`GeocoderPhp` is not itself a `GeocoderInterface`; to hand application code a geocoder that
answers from a list, build `OpenStreetMapProvider` directly with a `GeocoderPhp` over
`FixedGeocoder`, as the second test does: `capabilities()->geocoder` is `true`, and nothing else
of the provider is used.

### A provider on recorded answers

To test the application's handling of a real provider's answers and failures, give its factory a
`MockHttpClient` (and a `Psr18Client` over it for geocoding), as the third test of the run above:

```php
<?php

use Omnimap\Exception\ProviderException;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Size;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TileServerDownTest extends TestCase
{
    public function testATileServerDownIsAProviderException(): void
    {
        $http = new MockHttpClient(static fn () => new MockResponse('Unavailable', ['http_code' => 503]));
        $osm = (new OpenStreetMapFactory($http))->create(['user_agent' => 'tests/1.0']);

        $this->expectException(ProviderException::class);
        $osm->image(new MapView(new LatLng(35.0116, 135.7681), 12), new Size(256, 256));
    }
}
```

A closure makes one response per request: an image needs several tiles. Given a single
`MockResponse` object instead, the same call ends in Symfony's own `ServerException` (seen in the
harness), not in the family's `ProviderException`.

### In a Symfony or Laravel test

Replace the HTTP client the bridge gives the factories: `http_client` in Symfony's test
environment, or bind `omnimap.http_client` in Laravel, with a `MockHttpClient` answering
`PlainTiles::png()`; point the cache's storage at a temporary directory. omnibridge/symfony's and
omnibridge/laravel's `Tests/MapTest.php` do exactly that ([Symfony](symfony.md),
[Laravel](laravel.md)).

## Conformance suite: each test

`Omnimap\Testing\ProviderContractTests` is a PHPUnit trait: a provider's test case uses it and
gives its factory, built over the HTTP client the suite hands it. No test reaches the network:
every answer is a `MockResponse`. The core runs it on `TestTiles`
(`Tests/TestTilesContractTest.php`); each provider in `Tests/<Name>ContractTest.php`. Writing one:
[Writing a provider](providers.md#the-conformance-suite).

The hooks:

| Hook | Default | |
|---|---|---|
| `contractFactory(?HttpClientInterface $http): ProviderFactoryInterface` | abstract | the factory over `$http`; `null` builds a provider without a client, its network capabilities off. A geocoding provider wraps `$http` in a `Psr18Client` |
| `contractOptions(): array` | `[]` | options that switch every capability on (fake keys, a User-Agent, `nominatim_interval: 0`) |
| `contractAnswer(string $method, string $url, array $options): array` | `[200, PlainTiles::png(1, 0, 0), content-type image/png, max-age 3600]` for any request | `[status, body, headers]` of a request that succeeds: a geocoder answers its service's recorded answer for its geocoding URLs |
| `contractGeocodeQuery(): string` | `Osaka, Japan` | a free-text question the recorded geocoding answer answers |

The view every test uses: Osaka (34.6937, 135.5023) at zoom 11, one marker at Umeda, id
`contract`.

| Test | What it checks |
|---|---|
| `testTheFactoryNamesWhatItBuildsAndTheRegistryBuildsItOnce` | the factory's name is the provider's, matches `^[a-z0-9][a-z0-9_-]*$`; a title that is not blank; a `Registry` builds it from `{factory, options}`, the same object twice |
| `testAMissingRequiredOptionIsRefusedByName` | each option in `omnimap.required_options`, removed, is an `InvalidConfigException` whose message names it; with none, `create([])` builds a provider |
| `testCapabilitiesAreWhatTheProviderAnswers` | with and without a client: at least one contract; each declared contract is implemented; browser hosts are host names (no scheme, no path); terms start with `https://`; `toArray()` survives JSON. Without a client: a static map or geocoder implemented but declared off throws `NotSupportedException` (never a `TypeError`) |
| `testARendererDescribesTheViewAsPlainData` | `provider` is the provider's name, `consent` a string, `view` is `MapView::toArray()`, `attribution` a string equal to `attribution()`; JSON and back gives the same; every `http(s)` address in it (but the attribution's links) is on a declared browser host (`{s}` read as `a`); describing sends no request; the fingerprint is stable and changes with the centre, the zoom and the markers |
| `testAStaticMapIsAnImageOfTheSizeAsked` | a 300 x 200 image: bytes that are an image of the media type it says, no larger than asked, fetched over `https://` only |
| `testTileAddressesAreComplete` | the template holds `{z}`, `{x}`, `{y}`; `url(11, 1798, 812)` is `https://`, has no `{`, holds `1798`; tile size 256 or 512; max zoom 1 to 24; `headers()` answers with the contract's options |
| `testAGeocoderAnswersPlacesOfItsOwn` | `geocode(contractGeocodeQuery(), 'en', 1)`: at least one place, at most one, `providedBy` not empty, coordinates on the globe; `reverse()` of it keeps the limit |
| `testErrorsAreTheFamilysExceptions` | for `image()` and `geocode()` when declared: answers 401, 403, 404, 429 (with `Retry-After`), 500, 503, an HTML page in a 200 are each a `ProviderException`, a transport failure (`Could not resolve host`) an `UnreachableException`; never an answer, never another exception. A provider that fetches nothing on the server has nothing to check |

A check that does not apply (a renderer-only provider's image test) passes by asserting the
capability is off: such a test is not skipped, and the provider is counted as running the suite.
