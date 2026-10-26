---
title: Installation
order: 10
---

# Installation

```sh
composer require omnisense/omnimap omnimap/openstreetmap symfony/http-client
```

## Requirements

- PHP 8.2 or later.
- `omnisense/omnimap` requires `psr/simple-cache` (^2.0 or ^3.0), `symfony/http-client-contracts`
  (^3.0, interfaces only) and `willdurand/geocoder` (^5.0, geocoder-php's interfaces and models),
  and nothing else: no framework, no HTTP client.
- `ext-gd` for the map cache and for images made of tiles: `SnapshotStore::save()` cuts a picture
  with GD, `Static\TileStitcher` puts tiles together with it (PNG, JPEG and WebP support for the
  formats you keep). Without GD, live maps, descriptions, geocoding and tile URLs still work.
- A provider that calls a service needs an HTTP client: `symfony/http-client` (a library that
  stands alone) for tiles and Static Maps; for geocoding, a PSR-18 client - Symfony's
  `Psr18Client` over the same client works, with a PSR-17 factory such as `nyholm/psr7` installed.
- Suggested by the core, each for one storage of the map cache: `league/flysystem`
  (`Snapshot\FlysystemStorage`) and `omnisense/omnidrive` (`Snapshot\DriveStorage`).

The providers require the core, `symfony/http-client-contracts` and, when they geocode,
`psr/http-client`, `psr/simple-cache` and their geocoder-php provider
(`geocoder-php/google-maps-provider` ^4.7, `geocoder-php/nominatim-provider` ^5.7).
`omnimap/maplibre` requires the core and `symfony/http-client-contracts` only.

## Plain PHP

GD (`ext-gd`) cuts and makes the pictures. Everything below runs without a network: the tiles
come from `Omnimap\Testing\TestTiles`, a provider for tests whose tiles a recorded HTTP client
draws. Put `omnimap/openstreetmap`'s provider in its place, with your application's User-Agent,
and the same code fetches real tiles. Saved as `map.php`:

```php
<?php

use Omnimap\Geocoding\GeocoderPhp;
use Omnimap\MapHtml;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\Signer;
use Omnimap\Snapshot\DirectoryStorage;
use Omnimap\Snapshot\Snapshots;
use Omnimap\Snapshot\SnapshotStore;
use Omnimap\Testing\ArrayCache;
use Omnimap\Testing\FixedGeocoder;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;
use Omnimap\Testing\TestTiles;

require 'vendor/autoload.php';

$provider = new TestTiles(new RecordedHttpClient(PlainTiles::answer()), new ArrayCache());
$view = new MapView(new LatLng(35.0116, 135.7681), 12, [new Marker(new LatLng(35.0036, 135.7780), 'Gion')], controls: false, id: 'map');

// The map cache: pictures named by a signature, kept in a directory, made by the server from tiles.
$dir = sys_get_temp_dir().'/omnimap-docs-'.getmypid();
$snapshots = new Snapshots($provider, new Signer('c2VjcmV0'), new SnapshotStore(new DirectoryStorage($dir), 'maps'), $provider);
$snapshot = $snapshots->findOrMake($view);
echo "{$snapshot->width}x{$snapshot->height} in {$snapshot->xtiles}x{$snapshot->ytiles} tiles of {$snapshot->tilesize}\n";
// 1512x938 in 3x2 tiles of 512

// The page's element: the picture's frame, which assets/snapshot.js fills with the tiles.
$html = (new MapHtml(['src' => '/maps/{signature}/{id}.webp']))->snapshot('map', $snapshot->signature, $snapshot, $provider->attribution());
echo substr_count($html, 'data-omnimap="snapshot"'), " frame\n";
// 1 frame

// Geocoding: a geocoder-php provider behind omnimap's contract, its answers kept.
$geocoder = new GeocoderPhp(new FixedGeocoder(['Kyoto' => ['lat' => 35.0116, 'lng' => 135.7681, 'locality' => 'Kyoto', 'country' => 'Japan']]), new ArrayCache());
echo $geocoder->geocode('Kyoto')[0]->label, "\n";
// Kyoto, Japan

$snapshots->delete($snapshot->signature);
@rmdir("$dir/maps");
@rmdir($dir);
```

```
$ php map.php
1512x938 in 3x2 tiles of 512
1 frame
Kyoto, Japan
```

(run on 2026-10-25 in the [harness](harness.md), PHP 8.4 with GD; `Tests/DocsTest.php` runs the
block above as it is written and checks these three lines, on every run of the suite)

No key, no network. The pieces:

- a **provider**: here `TestTiles`, which describes a view, gives tile URLs and makes images of
  tiles; in an application, a factory's provider (`OpenStreetMapFactory`, `GoogleFactory`,
  `MapLibreFactory`), usually through a `Registry`;
- the **map cache**: `Snapshots` names a view by a signature (`Signer`, with the host's secret),
  finds its picture in a `SnapshotStore` or makes it with the provider's `StaticMapInterface`,
  and the store cuts it into 512-pixel tiles in a `Storage` (`DirectoryStorage` here);
- **`MapHtml`**: the page's markup, a picture's frame (`snapshot()`) or a live map (`live()`),
  which the browser's modules fill ([The browser](javascript.md));
- **`GeocoderPhp`**: a geocoder-php provider (`FixedGeocoder` here, a test double) behind
  omnimap's models, its answers kept in a PSR-16 cache (`ArrayCache` here).

No class of a framework, of an HTTP client or of Flysystem is loaded on the way:
`Tests/BareTest.php` checks it in a process of its own, with `docker/harness/bin/bare`.

## With a real provider

The same view drawn live by Leaflet over OpenStreetMap's tiles, and its picture made by the server
from those tiles. Fetching tiles needs a `user_agent` naming the application and a contact (the
OpenStreetMap Foundation's tile policy blocks a library's default one); `HttpClient::create()` is
any `Symfony\Contracts\HttpClient\HttpClientInterface`.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnimap\MapHtml;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\Registry;
use Omnimap\Signer;
use Omnimap\Snapshot\DirectoryStorage;
use Omnimap\Snapshot\Snapshots;
use Omnimap\Snapshot\SnapshotStore;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

$http = HttpClient::create();
$registry = new Registry([new OpenStreetMapFactory($http, new Psr18Client($http))], [
    'maps' => ['factory' => 'openstreetmap', 'options' => ['user_agent' => 'MySite/1.0 (+https://example.org; maps@example.org)']],
]);
$view = new MapView(new LatLng(35.0116, 135.7681), 12, [new Marker(new LatLng(35.0036, 135.7780), 'Gion')], id: 'map');

// Live: the description omnimap.js and leaflet.js draw, behind the page's consent gate.
echo (new MapHtml())->live('map', $registry->renderer('maps')->describe($view));

// Cached: the picture made once by the server (35 tiles for 1512 x 938), then read from /var/maps.
$snapshots = new Snapshots(
    $registry->renderer('maps'),
    new Signer(getenv('MAP_SECRET') ?: ''),
    new SnapshotStore(new DirectoryStorage('/var/maps')),
    $registry->staticMap('maps'),
);
echo (new MapHtml())->snapshot('map', $snapshots->signature($view), $snapshots->findOrMake($view), $registry->renderer('maps')->attribution());

// Geocoding: Nominatim, one call a second at most in this process.
echo $registry->geocoder('maps')->geocode('Kyoto, Japan', 'en', 1)[0]->label;
```

This script reaches tile.openstreetmap.org and nominatim.openstreetmap.org: it was not run for
this page. The harness's `live` command runs the same calls (an image of Kyoto from 35 tiles and
one Nominatim query), last on 2026-10-23 ([Verified, and not](verified.md)). In production, the
Foundation's tile server is for modest use only: its policy asks heavy users for a tile provider
of their own or their own server (the `tiles` option, see omnimap/openstreetmap's manual). The
page then needs `assets/omnimap.js` and `assets/leaflet.js` ([The browser](javascript.md)) and,
for the cached picture, `assets/snapshot.js` and a route that serves the tiles ([The map
cache](cache.md#serving)).

## In a framework

- **Symfony**: omnibridge/symfony's `OmnimapExtension` builds the registry, the cache and the
  printer from `config/packages/omnimap.yaml`, routes the cache and gives Twig `omnimap()`:
  [Symfony](symfony.md).
- **Laravel**: omnibridge/laravel's `Map\MapServiceProvider` reads `config/omnimap.php`, the same
  keys: [Laravel](laravel.md).
- **Any other**: build the `Registry` (and `Snapshots`, `MapPrinter`) once, where the framework
  builds its services, as the scripts above do.
