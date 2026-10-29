---
title: Configuration reference
order: 40
---

# Configuration reference

Every option below is read from the code at this commit: the factories' `populate()` defaults and
what their `build()` reads, and the constructors of the core's classes. Options reach a factory
through `create($options)`, a `Registry`'s `options`, or the bridges' `providers` key
([Symfony](symfony.md#omnimapyaml), [Laravel](laravel.md#configomnimapphp)). A value may be given
as an environment variable gives it: `Config::bool()` reads `1`, `true`, `yes`, `on` as true,
`Config::int()` and `float()` read numeric strings, and an empty string is "not set" for a string
option.

## The registry

```php
new Registry(iterable $factories, array $config);
```

| Argument | |
|---|---|
| `$factories` | `ProviderFactoryInterface`s, keyed by their `getName()` (a later one of the same name replaces an earlier one) |
| `$config` | `array<string, array{factory: string, options?: array}>`: each provider's name, its factory's name, its options |

## omnimap/google

`new GoogleFactory(?HttpClientInterface $http = null, ?Psr\Http\Client\ClientInterface $psr18 = null, ?Psr\SimpleCache\CacheInterface $cache = null)`;
no required option.

| Option | Type | Default | |
|---|---|---|---|
| `api_key` | string | `''` | the browser key (Maps JavaScript API), written in the page: restrict it to your sites in Google's console |
| `server_key` | string | `''` | the key of the server's calls (Static Maps, Geocoding); the `api_key` when empty |
| `secret` | string | `''` | the URL signing secret: Static Maps' `signature` (none when empty) |
| `version` | string | `weekly` | the Maps JavaScript API's channel or version: `weekly`, `quarterly`, `beta`, a number |
| `language` | string | `''` | the map's language and the geocoder's default locale |
| `region` | string | `''` | the map's region, and geocoder-php's region bias |
| `legacy_signature` | bool | `false` | `true`: a view's fingerprint is glitchr/ux-google's, so the pictures it captured keep their names |
| `geocoder_ttl` | int | `2592000` | seconds a geocoding answer is kept (30 days) |

Static Maps needs the HTTP client; geocoding needs the PSR-18 client and a key (`server_key` or
`api_key`); both answers are kept in `$cache` when given (geocoding only: Static Maps images are
not cached by the provider; the map cache keeps the picture).

## omnimap/openstreetmap

`new OpenStreetMapFactory(?HttpClientInterface $http = null, ?Psr\Http\Client\ClientInterface $psr18 = null, ?Psr\SimpleCache\CacheInterface $cache = null)`;
no required option.

| Option | Type | Default | |
|---|---|---|---|
| `tiles` | string | `https://tile.openstreetmap.org/{z}/{x}/{y}.png` | the tile URL, `{z}`, `{x}`, `{y}` in it, `{s}` for a server's subdomains (`a`, `b`, `c`); the default is the Foundation's server, for modest use |
| `attribution` | string (HTML) | `&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors` | shown on the live map, beside a kept picture and drawn on an image |
| `max_zoom` | int | `19` | |
| `tile_size` | int | `256` | the tiles' size in pixels; 512 for a server numbering 512-pixel tiles 2^z a side at `z` ([Tiles](usage-tiles.md)) |
| `user_agent` | string | `''` | the application's name and a contact, sent with every request the server makes (tiles, Nominatim); required to fetch a tile and to geocode |
| `referer` | string | `''` | sent with them too, when set |
| `nominatim` | string | `https://nominatim.openstreetmap.org` | the geocoder's URL; empty: no geocoding |
| `nominatim_interval` | float | `1.0` | seconds between two geocoder calls of a process |
| `geocoder_ttl` | int | `2592000` | seconds a geocoding answer is kept |
| `leaflet_script` | string | `https://unpkg.com/leaflet@1.9.4/dist/leaflet.js` | where the browser gets Leaflet |
| `leaflet_script_integrity` | string | `sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=` | its Subresource Integrity hash; empty: none |
| `leaflet_style` | string | `https://unpkg.com/leaflet@1.9.4/dist/leaflet.css` | |
| `leaflet_style_integrity` | string | `sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=` | |
| `background` | string | `#aad3df` | the colour above and below the world on an image made of tiles |

The tile host and the Leaflet script's host are the provider's `browserHosts`.

## omnimap/maplibre

`new MapLibreFactory(?HttpClientInterface $http = null)`; no required option. The client is only
for `MapLibreProvider::inspect()` (reading a style's hosts on the server).

| Option | Type | Default | |
|---|---|---|---|
| `style` | string | `https://tiles.openfreemap.org/styles/liberty` | the MapLibre style's URL |
| `attribution` | string (HTML) | OpenFreeMap, OpenMapTiles, OpenStreetMap (`MapLibreFactory::OPENFREEMAP_ATTRIBUTION`) | shown beside a kept picture; the live map shows its sources' own |
| `hosts` | list or comma-separated string | `[]` | the hosts the browser reaches for the style, its tiles, glyphs and sprite; the style's host when empty |
| `library_script` | string | `https://unpkg.com/maplibre-gl@5.24.0/dist/maplibre-gl.js` | |
| `library_script_integrity` | string | `MapLibreFactory::LIBRARY_SCRIPT_INTEGRITY` (SHA-384) | |
| `library_style` | string | `https://unpkg.com/maplibre-gl@5.24.0/dist/maplibre-gl.css` | |
| `library_style_integrity` | string | `MapLibreFactory::LIBRARY_STYLE_INTEGRITY` (SHA-384) | |
| `library_module` | string | `''` | an ES module instead of the script (MapLibre GL JS 6); set, the script and its integrity are left out |
| `preserve_drawing_buffer` | bool | `true` | keeps the canvas readable for `capture.js` |

## Map cache classes

| Class | Constructor | |
|---|---|---|
| `Signer` | `(?string $secret)` | URL-safe base64; `null` or `''` signs with an empty key |
| `Snapshot\SnapshotStore` | `(Storage $storage, string $directory = 'omnimap', string $pattern = '{signature}/image-{id}', string $format = 'png')` | [The layout](cache.md#the-layout-in-the-storage) |
| `Snapshot\Snapshots` | `(MapRendererInterface $renderer, Signer $signer, SnapshotStore $store, ?StaticMapInterface $staticMap = null, Size $size = new Size(1512, 938), int $scale = 1, ?int $tilesize = 512)` | `size`, `scale`: a picture the server makes; `tilesize`: how pictures are cut (`null`: whole) |
| `Snapshot\DirectoryStorage` | `(string $root)` | |
| `Snapshot\FlysystemStorage` | `(League\Flysystem\FilesystemOperator $filesystem)` | |
| `Snapshot\DriveStorage` | `(Omnidrive\Drive $drive)` | |
| `MapHtml` | `(array $options = [])` | `class` (`omnimap-snapshot`), `suffix` (`_snapshot`), `src` (`/omnimap/{signature}/{id}.webp`), `missing` (`''`: the address of the picture `snapshot.js` shows for a missing picture or tile) |
| `MapPrinter` | `(Snapshots $snapshots, MapHtml $html = new MapHtml(), bool $cacheOnly = true, ?\Closure $onError = null)` | [The page](cache.md#the-page-mapprinter) |

## Images and geocoding

| Class | Constructor | |
|---|---|---|
| `Static\TileStitcher` | `(TileSourceInterface $tiles, HttpClientInterface $http, ?CacheInterface $cache = null, string $background = '#aad3df', string $markerColor = '#d33b3b')` | colours as CSS hex (`#rgb` or `#rrggbb`) |
| `Geocoding\GeocoderPhp` | `(Geocoder\Provider\Provider $provider, ?CacheInterface $cache = null, int $ttl = 2592000, float $interval = 0.0)` | `ttl` in seconds, `interval` the pace in seconds |

## Browser options

The options of `mountAll()`/`draw()` (`consent`), `bindCapture()` (`html2canvas`, `format`,
`quality`, `tilesize`, `html2canvasOptions`) and the description's keys are in
[The browser](javascript.md).
