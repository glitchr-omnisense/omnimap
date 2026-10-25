# omniscient/omnimap

Omnimap is one PHP contract for web maps, whoever draws them. A page describes a view once - its
centre, its zoom, its markers - and the provider configured for it turns that view into what is
needed: a description the browser draws with the provider's own library (Google's Maps JavaScript
API, Leaflet, MapLibre GL JS), an image of the view made on the server, the tiles of a slippy map,
places found from an address and addresses found from a point. Each provider is a package of its
own (`omnimap/google`, `omnimap/openstreetmap`, `omnimap/maplibre`) that answers the contracts it
can and says which in its `Capabilities`.

```php
$registry->renderer('maps')->describe($view);            // array: what the browser's driver draws
$registry->staticMap('maps')->image($view, new Size(640, 400));   // Image: PNG bytes made on the server
$registry->geocoder('maps')->geocode('Kyoto');           // Place[], the best first
$registry->tiles('maps')->url(12, 3592, 1622);           // one tile's URL
$registry->get('maps')->capabilities();                  // what it does, which hosts the browser reaches
```

Around the contracts sits the **map cache**: the picture of a view, named by a signature of what
it depends on, cut in square tiles and kept in the host's storage (a directory, a Flysystem
storage, an omnidrive drive). A page then shows the picture instead of the live map, and a visitor's
browser reaches no third party at all. The picture comes from the browser (an administrator's
capture of the live map) or from the server (a provider's static image, or tiles put together):
the cache is the same for every provider. This is the cache `glitchr/ux-google` kept for
lesringostars, made independent of Google; its file layout and its names are kept, so pictures
captured before omnimap are still served.

The core needs no framework: it requires `psr/simple-cache`, `symfony/http-client-contracts`
(interfaces only) and `willdurand/geocoder` (geocoder-php's interfaces and models). Geocoding is
geocoder-php's: omnimap does not parse a geocoding API itself, it puts a geocoder-php provider
behind its own contract with a cache and a pace. The Symfony and Laravel wiring lives in
omnibridge ([Symfony](symfony.md), [Laravel](laravel.md)). The browser side is three ES modules
without a library ([The browser](javascript.md)).

## When to use it

- A site that shows maps and wants to be able to change provider (Google to OpenStreetMap, raster
  to vector tiles) without touching its pages: the view, the markup and the cache stay the same.
- A site that must not send its visitors to a third party without their consent: every live map
  goes through a consent gate, and a cached picture reaches no one.
- An application that needs geocoding with the caching and the pace the services' terms ask for
  (Nominatim: one request a second at most, results cached).
- A server that makes map images (an e-mail, a PDF, an Open Graph image) from a tile server.

It is not a map library: drawing, panning and zooming are the provider's library's job in the
browser. It does not store anything but the pictures of the map cache, and it does not decide
whether a provider's terms allow a picture of its map to be kept: whoever runs it answers for
that (see [Responsibility](verified.md#responsibility)).

## Providers

Three packages. The capabilities are what each provider's `capabilities()->contracts()` answers,
probed in the [harness](harness.md) on 2026-10-25 with the options and clients named; "with X"
means the capability is off until X is given. The browser hosts are the defaults.

| Package | Factory name | Renderer (driver) | Static map | Geocoder | Tiles | Browser hosts |
|---|---|---|---|---|---|---|
| [`omnimap/google`](https://github.com/glitchr-studio/omnimap-google) | `google` | yes (`assets/google.js`, Maps JavaScript API) | with an HTTP client (and a key, see below) | with a PSR-18 client and a key (geocoder-php's `GoogleMaps`) | - | `maps.googleapis.com`, `maps.gstatic.com`, `fonts.googleapis.com` |
| [`omnimap/openstreetmap`](https://github.com/glitchr-studio/omnimap-openstreetmap) | `openstreetmap` | yes (`assets/leaflet.js`, Leaflet 1.9.4) | with an HTTP client (and a `user_agent`, see below), tiles put together | with a PSR-18 client, a `user_agent` and a `nominatim` URL (geocoder-php's `Nominatim`) | yes | `tile.openstreetmap.org`, `unpkg.com` |
| [`omnimap/maplibre`](https://github.com/glitchr-studio/omnimap-maplibre) | `maplibre` | yes (`assets/maplibre.js`, MapLibre GL JS 5.24.0) | - | - | - | `tiles.openfreemap.org`, `unpkg.com` |

The core ships a fourth provider for tests, `Omnimap\Testing\TestTiles` (name `test`: renderer,
static map, tiles, on `https://tiles.example/`), with no factory ([Testing](testing.md)).

> [!WARNING]
> Known defect: `omnimap/google` declares `staticMap` as soon as it has an HTTP client, and
> `omnimap/openstreetmap` as soon as it has one too, where the capability should also depend on a
> key (Google: `server_key` or `api_key`) and on a `user_agent` (OpenStreetMap). Without them,
> `image()` throws an `InvalidConfigException` although `capabilities()->staticMap` is `true`.

Each provider's own manual lists its options, its description's keys and its behaviour. A new
provider starts from [glitchr-studio/omni-provider-template](https://github.com/glitchr-studio/omni-provider-template)
([Writing a provider](providers.md)).

## How this manual is organised

- [Installation](installation.md): requirements, a first script in plain PHP, a real provider.
- [Symfony](symfony.md) and [Laravel](laravel.md): the omnibridge wiring, its configuration, its
  routes, its Twig functions.
- [Concepts](concepts.md): providers, factories, the registry, the four contracts, every model,
  the projection, the signer.
- Usage: [a live map in the browser](usage-rendering.md), [images made on the
  server](usage-static.md), [geocoding](usage-geocoding.md), [tiles](usage-tiles.md),
  [the map cache](cache.md), [the browser's modules](javascript.md).
- [Configuration reference](configuration.md): every option of every factory and every
  constructor argument of the core's classes.
- [Errors](errors.md): the exceptions and when each is thrown.
- [Testing](testing.md): the fakes, recorded answers, the conformance suite test by test.
- [The Docker harness](harness.md): the core and every provider in one installation.
- [Writing a provider](providers.md).
- [Verified, and not](verified.md).
