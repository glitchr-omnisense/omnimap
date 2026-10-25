---
title: Tiles
order: 34
---

## Tiles

A tile source (`TileSourceInterface`) gives the square tiles of the Web Mercator grid, one URL
each: what a browser library draws (Leaflet fills `template()` itself) and what
`Static\TileStitcher` fetches on the server. Of the providers, `omnimap/openstreetmap` is one, over
any raster tile server; the core's `Testing\TestTiles` is another.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnimap\Model\LatLng;
use Omnimap\OpenStreetMap\OpenStreetMapFactory;
use Omnimap\Projection\WebMercator;
use Omnimap\Registry;

$registry = new Registry([new OpenStreetMapFactory()], [
    'osm' => ['factory' => 'openstreetmap', 'options' => ['user_agent' => 'MySite/1.0 (+https://example.org)']],
]);
$tiles = $registry->tiles('osm');

$point = WebMercator::point(new LatLng(35.0116, 135.7681), 12);
[$x, $y] = [intdiv((int) $point->x, $tiles->tileSize()), intdiv((int) $point->y, $tiles->tileSize())];

echo $tiles->template(), "\n";
echo $tiles->url(12, $x, $y), "\n";
echo $tiles->tileSize(), ' ', $tiles->maxZoom(), ' ', json_encode($tiles->headers()), "\n";
```

```
https://tile.openstreetmap.org/{z}/{x}/{y}.png
https://tile.openstreetmap.org/12/3592/1622.png
256 19 {"User-Agent":"MySite\/1.0 (+https:\/\/example.org)"}
```

(run on 2026-10-25 in the [harness](harness.md); nothing was fetched)

### What a tile source answers

- `template()`: the URL with `{z}`, `{x}`, `{y}` (and `{s}`, a server's subdomain, when the
  server has several). Leaflet fills it in the browser; the description carries it.
- `url($zoom, $x, $y)`: one tile's complete URL. `omnimap/openstreetmap` replaces `{s}` with `a`,
  `b` or `c` by `(x + y) mod 3`, so a tile always has the same address (and the same cache entry).
- `tileSize()`: 256 for nearly every server.
- `maxZoom()`: the deepest zoom served; `TileStitcher` never asks beyond it: a deeper view is made
  at that zoom, its tiles not enlarged, so the image shows a wider area than the view.
- `attribution()`: HTML, the attribution the tiles' licence asks for.
- `headers()`: what every server-side request for a tile carries. `omnimap/openstreetmap` gives
  its `user_agent` and, when set, its `referer`; without a `user_agent` it throws
  `InvalidConfigException`, so that no tile is fetched with a library's default User-Agent (the
  OpenStreetMap Foundation's tile policy blocks those).

### Fetching a tile oneself

The URLs are plain: any HTTP client fetches them with `headers()`. `TileStitcher` already fetches,
caches and puts tiles together; fetch one directly only to proxy tiles, and then keep the server's
caching headers. The OpenStreetMap Foundation's policy forbids bulk downloads and prefetching, and
asks heavy users for another provider or their own server
(https://operations.osmfoundation.org/policies/tiles/).

> [!WARNING]
> Known defect: a source with `tileSize()` 512 is read by `TileStitcher` and by `leaflet.js` as
> if its zoom `z` held 2^(z-1) tiles a side, where 512-pixel servers usually serve 2^z at `z` (Leaflet's
> own answer is `zoomOffset: -1`): use 256-pixel tiles ([Images](usage-static.md#tiles-put-together-tilestitcher)).
