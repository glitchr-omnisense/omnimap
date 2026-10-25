# omniscient/omnimap

Maps under any PHP host, whoever draws them. One description of a view - its centre, its zoom,
its markers - that a provider draws in the browser, that the server can turn into an image, and
whose picture is kept in a cache of the host's storage; places found from an address and
addresses from a point through [geocoder-php](https://geocoder-php.org). Plain PHP: no framework
in `require`.

```php
use Omnimap\MapHtml;
use Omnimap\Model\{LatLng, MapView, Marker};
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\Registry;
use Omnimap\Signer;
use Omnimap\Snapshot\{DirectoryStorage, Snapshots, SnapshotStore};
use Symfony\Component\HttpClient\HttpClient;

$registry = new Registry([new OpenStreetMapFactory(HttpClient::create())], [
    'maps' => ['factory' => 'openstreetmap', 'options' => ['user_agent' => 'MySite/1.0 (+https://example.org)']],
]);
$view = new MapView(new LatLng(35.0116, 135.7681), 12, [new Marker(new LatLng(35.0036, 135.7780), 'Gion')], id: 'map');

// In the browser: a description assets/omnimap.js draws (Leaflet here, Google's API with omnimap/google).
echo (new MapHtml())->live('map', $registry->renderer('maps')->describe($view));

// Or its picture, made once on the server and served from the cache: no third party reached by the visitor.
$snapshots = new Snapshots($registry->renderer('maps'), new Signer($secret), new SnapshotStore(new DirectoryStorage('/var/maps')), $registry->staticMap('maps'));
echo (new MapHtml())->snapshot('map', $snapshots->signature($view), $snapshots->findOrMake($view), $registry->renderer('maps')->attribution());
```

## The shape

- **Models** (`Omnimap\Model`): `LatLng`, `Bounds`, `Size`, `Point`, `Place` and its `Address`,
  `Marker`, `MapView` (centre, zoom, markers, controls, options of one provider under its name),
  `Image`. `Projection\WebMercator`: the projection of every web map's tiles.
- **Contracts by capability**, each provider saying which it has (`Capabilities`):
  `MapRendererInterface` (a view described for the browser, its fingerprint, its attribution),
  `StaticMapInterface` (a view as an image made on the server), `GeocoderInterface`,
  `TileSourceInterface`. `Registry`, `Config`, `ProviderFactory`: providers by name, as in the
  other omni families.
- **Geocoding is geocoder-php's** (`Geocoding\GeocoderPhp`): a geocoder-php provider asked, its
  answers turned into `Place`s, kept in a PSR-16 cache, paced.
- **The map cache** (`Snapshot\*`): the picture of a view, named by the signature of what it
  depends on (`Signer`: HMAC-SHA1, Google's rule), cut in square tiles, kept in a `Storage`
  (a directory, Flysystem, an omnidrive Drive, or the host's own in a few lines), the same for every provider - only
  the source of the picture differs: the browser's capture of a live map (`Snapshots::save()`,
  `assets/capture.js`), or an image the server makes (`Snapshots::make()`: Static Maps,
  `Static\TileStitcher` over a tile server). See [docs/cache.md](docs/cache.md).
- **The browser** (`assets/`, ES modules, no library): `omnimap.js` draws a description with the
  provider's driver (shipped by the provider package) behind a consent gate; `snapshot.js` lays
  a kept picture's tiles out; `capture.js` sends a live map's picture to the cache.
- **Providers** are packages: [omnimap/google](https://github.com/glitchr-studio/omnimap-google),
  [omnimap/openstreetmap](https://github.com/glitchr-studio/omnimap-openstreetmap).
- **Hosts**: omnibridge/symfony (`Omnibridge\Symfony\Map`) and omnibridge/laravel
  (`Omnibridge\Laravel\Map`) wire it - configuration, services, the cache's routes, Twig
  functions. Nothing of a framework here.

## Documentation

- [Installation, in plain PHP](docs/installation.md) (run as written by the tests)
- [The map cache](docs/cache.md): names, storage layout, serving, expiry, capture
- [The browser](docs/javascript.md): drivers, consent, snapshots, capture
- [Writing a provider](docs/providers.md)
- [The harness](docs/harness.md)

## Verified, and not

- Verified (`docker compose run --rm omnimap test` in `docker/`, PHP 8.4 with GD): the models and
  the projection (the slippy-map tile of Kyoto at zoom 12); the signer against
  `hash_hmac()` and against geocoder-php's own implementation of Google's URL signing; the cache
  reading a capture glitchr/ux-google wrote (its `metadata.txt` byte for byte, written back
  identical), cutting, padding with transparency, deleting, in a directory, in Flysystem's
  memory adapter and on an omnidrive Drive; images made of tiles (each tile once, the source's headers sent, cached as
  long as max-age plus stale-while-revalidate, the world repeating across the antimeridian, the
  markers drawn); the geocoder adapter (answers kept, the pace kept); in plain PHP, a view
  described, made, cached, printed and geocoded loading nothing but omnimap and what it requires
  (`BareTest`); the conformance suite (`Testing\ProviderContractTests`, see
  [docs/providers.md](docs/providers.md#the-conformance-suite)) on the core's test provider, on
  omnimap/openstreetmap and on omnimap/google, offline.
- Verified live (`docker compose run --rm omnimap live`, 2026-10-23): an image of Kyoto made of
  tile.openstreetmap.org's tiles and one Nominatim query, with this package's User-Agent.
- Not verified here: the JavaScript in a browser;
  Google's live answers (no key in this harness: see omnimap/google).

## Responsibility

omniscient/omnimap and its omnimap/* providers are unofficial clients, affiliated with none of
the services they reach. The MIT licence covers the code only and grants no right to any service
or its data; whoever runs them complies with each service's terms (named in each provider's
README and in `Capabilities::$terms`) and obtains what they require.

## Licence

MIT.
