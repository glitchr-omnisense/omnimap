# Installation, in plain PHP

```
composer require omniscient/omnimap omnimap/openstreetmap symfony/http-client
```

GD (`ext-gd`) cuts and makes the pictures. Everything below runs without a network: the tiles
come from `Omnimap\Testing\TestTiles`, a provider for tests whose tiles a recorded HTTP client
draws. Put `omnimap/openstreetmap`'s provider in its place, with your application's User-Agent,
and the same code fetches real tiles.

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
