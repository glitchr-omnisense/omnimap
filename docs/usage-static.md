---
title: Images made on the server
order: 32
---

## Images made on the server

A static map (`StaticMapInterface`) makes an image of a view on the server: for an e-mail, a PDF,
a page that must reach no third party, or the [map cache](cache.md), which keeps such an image as
the view's picture. Two ways exist today: a provider's static map service (Google's Static Maps)
and tiles put together by `Omnimap\Static\TileStitcher` (OpenStreetMap, any tile source).

### An image

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnimap\Google\GoogleFactory;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Model\Marker;
use Omnimap\Model\Size;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\Testing\ArrayCache;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;

// Tiles answered here (PlainTiles); HttpClient::create() in production.
$http = new RecordedHttpClient(PlainTiles::answer());
$cache = new ArrayCache();
$osm = (new OpenStreetMapFactory($http, null, $cache))->create(['user_agent' => 'MySite/1.0 (+https://example.org; maps@example.org)']);

$view = new MapView(new LatLng(35.0116, 135.7681), 12, [new Marker(new LatLng(35.0036, 135.7780), 'Gion')]);
$image = $osm->image($view, new Size(640, 400), 2);
file_put_contents(sys_get_temp_dir().'/kyoto.png', $image->bytes);

echo $image->mimeType, ' ', $image->size, ', ', count($http->requests), " tiles fetched\n";
echo $http->requests[0]['url'], ' ', $http->requests[0]['options']['headers']['User-Agent'], "\n";
echo 'kept ', $cache->ttls[array_key_first($cache->ttls)], " s\n";
$osm->image($view, new Size(640, 400), 2);
echo count($http->requests), " tiles fetched after a second image\n";

$google = (new GoogleFactory())->create(['api_key' => 'browser-key', 'server_key' => 'server-key', 'secret' => 'vNIXE0xscrmjlyV-12Nj_BvUPaw=']);
echo $google->staticUrl($view, new Size(1512, 938), 2), "\n";
```

```
image/png 1280x800, 30 tiles fetched
https://tile.openstreetmap.org/13/7182/3242.png MySite/1.0 (+https://example.org; maps@example.org)
kept 695880 s
30 tiles fetched after a second image
https://maps.googleapis.com/maps/api/staticmap?center=35.0116,135.7681&zoom=12&size=640x397&scale=2&markers=35.0036%2C135.778&key=server-key&signature=rUXZxBfvqM0gblKazD4BVDZ7KaQ=
```

(run on 2026-10-25 in the [harness](harness.md); the tiles came from `PlainTiles` with
OpenStreetMap's own `Cache-Control` of 2026-10-23, `max-age=91080, stale-while-revalidate=604800`)

At scale 2 the stitcher took the tiles of zoom 13 for a view at zoom 12, and kept each tile for
max-age plus stale-while-revalidate (91080 + 604800 = 695880 seconds); the second image fetched
nothing. Google's URL was built without any request: `staticUrl()` is public.

### Tiles put together: TileStitcher

`new TileStitcher(TileSourceInterface $tiles, HttpClientInterface $http, ?CacheInterface $cache = null, string $background = '#aad3df', string $markerColor = '#d33b3b')`,
then `image(MapView $view, Size $size, int $scale = 1): Image`:

- the scale is kept between 1 and 2; the zoom is the view's plus `scale - 1`, at most the source's
  `maxZoom()`; the image is `$size` x scale pixels, centred on the view's centre;
- every tile the image covers is listed once; above and below the world (`y` outside the grid) the
  `background` colour shows; east and west the world repeats (`x` taken modulo the grid);
- tiles found in the PSR-16 cache (key `omnimap.tile.` + SHA-1 of the URL) are not fetched; the
  others are requested all at once (the HTTP client runs them side by side) with the source's
  `headers()`; each answer is kept for max-age plus stale-while-revalidate of its `Cache-Control`,
  or 604800 seconds (7 days) when it gives neither (`TileStitcher::lifetime()`, public);
- a tile answered with a status other than 200 is a `ProviderException`, a transport failure an
  `UnreachableException`; the tiles still on their way are cancelled. A body GD cannot read as an
  image is a `ProviderException` too;
- each marker is drawn as a dot of radius 6 x scale in its `color` (else `markerColor`), ringed in
  white; the marker's `title` is not drawn;
- the source's attribution, its tags stripped and its entities decoded, is written in the
  bottom-right corner on a translucent white band with GD's built-in font (`©` written `(c)`,
  other non-ASCII characters dropped);
- the result is a PNG.

> [!WARNING]
> Known defect: with a 512-pixel tile source (`tile_size: 512`), the stitcher requests the tiles
> of the view's zoom laid on a grid half as wide (a view at zoom 1 asks for `1/0/0` only), where
> a tile server whose 512-pixel tiles follow the usual numbering serves that area at the zoom
> below (`0/0/0`): the image shows the wrong part of the world. Leaflet's driver is configured the
> same way (no `zoomOffset`). 256-pixel sources are not affected.

### Google Static Maps

`GoogleProvider::image()` fetches `staticUrl($view, $size, $scale)`:

- `size` clamped to 640 pixels on the longer side, the ratio kept (1512 x 938 becomes 640 x 397);
  `scale` kept between 1 and 2;
- one `markers=` per marker, `color:0x...|` before its position when it has a colour;
- one `style=` per rule of the view's default Google style ([A live map](usage-rendering.md#a-views-options-of-one-provider));
- `language` and `region` when set, then `key` (the `server_key`, else the `api_key`; neither is
  an `InvalidConfigException`), and with a `secret` the `signature=` of the path and query,
  HMAC-SHA1 padded (Google's URL signing).

The answer must be 200 and its bytes an image (their media type is read from the bytes, not from
the `Content-Type`): anything else is a `ProviderException`, a transport failure an
`UnreachableException`. `Image::$size` is the clamped size times the scale.

### Without an HTTP client

A provider built without its HTTP client declares `staticMap` off and its `image()` throws
`NotSupportedException` before any request. `omnimap/maplibre` makes no image at all: vector
tiles are drawn by the browser's WebGL. Its picture reaches the map cache only through the
browser's capture ([The map cache](cache.md#where-pictures-come-from)).
