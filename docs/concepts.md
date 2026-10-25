---
title: Concepts
order: 20
---

# Concepts

## Providers and capabilities

`Omnimap\ProviderInterface` is one map provider, configured. Every provider has:

| Method | Returns |
|---|---|
| `getName(): string` | the factory's name (`google`, `openstreetmap`, `maplibre`): also the `provider` of its descriptions and the driver the browser picks |
| `getTitle(): string` | its display name (`Google Maps`, `OpenStreetMap`, `MapLibre`), used in error messages |
| `capabilities(): Capabilities` | what it can be asked, and what it costs the visitor's privacy |

What a provider can be asked is a set of four contracts, each an interface extending
`ProviderInterface`. A provider implements those it can answer:

| Contract | Capability | Methods |
|---|---|---|
| `MapRendererInterface` | `renderer`: draws a map in the browser | `describe(MapView): array`, `fingerprint(MapView): string`, `attribution(): string` |
| `StaticMapInterface` | `staticMap`: makes an image of a map on the server | `image(MapView, Size, int $scale = 1): Image` |
| `GeocoderInterface` | `geocoder`: places from an address, addresses from a point | `geocode(string, ?string $locale = null, int $limit = 5): list<Place>`, `reverse(LatLng, ?string $locale = null, int $limit = 5): list<Place>` |
| `TileSourceInterface` | `tiles`: square Web Mercator tiles, one URL each | `template()`, `url(int, int, int)`, `tileSize()`, `maxZoom()`, `attribution()`, `headers()` |

Implementing an interface is not enough to be asked: a provider built without the client a
capability needs (no HTTP client, no PSR-18 client, no key) still implements the interface but
declares the capability off, and its method throws `NotSupportedException` before any request.
The `Registry`'s typed getters check the interface only; `capabilities()` is what to read
beforehand.

### Capabilities

`Omnimap\Capabilities` is a `final readonly` value, built with named arguments:

| Property | Type | Default | |
|---|---|---|---|
| `renderer` | `bool` | `false` | it draws a map in the browser |
| `staticMap` | `bool` | `false` | it makes an image of a map on the server |
| `geocoder` | `bool` | `false` | it geocodes |
| `tiles` | `bool` | `false` | it gives tile URLs |
| `browserHosts` | `list<string>` | `[]` | the host names (no scheme, no path) a visitor's browser reaches when the map is drawn live: what a consent banner asks about and what a Content-Security-Policy must allow |
| `cookies` | `bool` | `false` | whether its browser script sets or reads cookies |
| `terms` | `list<string>` | `[]` | the `https://` addresses of the terms whoever runs it must follow |

`contracts(): list<string>` lists the capabilities that are on, by name (`renderer`,
`staticMap`, `geocoder`, `tiles`); `toArray()` gives every property and `contracts`, ready for
JSON.

## Factories and Config

`Omnimap\ProviderFactoryInterface` builds a provider from options:

| Method | |
|---|---|
| `getName(): string` | the name providers are configured with (`factory:` in a configuration) |
| `create(array $options = []): ProviderInterface` | a provider, its options checked |

Every provider's factory extends `Omnimap\ProviderFactory`, which implements both methods around
two of its own:

1. `createConfig($options)` makes a `Config` (an `ArrayObject`) holding the options given, then
   calls the factory's `populate(Config $c)`, which sets its name, its title, its required options
   and the defaults of the others with `$c->defaults([...])` - only the keys the options did not
   set;
2. `create()` checks the keys listed in `omnimap.required_options`: one missing, `''` or `[]` is
   an `InvalidConfigException` naming every missing key (`The "acme" provider needs: api_key.`);
3. `build(Config $c)` returns the provider.

`getName()` is `omnimap.factory_name` of an empty configuration (the class name when there is
none). Building calls nothing: HTTP clients, PSR-18 clients and caches come through the factory's
own constructor, and a factory given none builds a provider with the capabilities that need them
off.

The reserved keys:

| Key | |
|---|---|
| `omnimap.factory_name` | the provider's name, snake_case or kebab-case (`^[a-z0-9][a-z0-9_-]*$`) |
| `omnimap.factory_title` | its display name |
| `omnimap.required_options` | `list<string>`: options that must be filled |

`Config` reads options as an environment variable may give them:

| Method | |
|---|---|
| `defaults(array $defaults): self` | sets each key not set yet |
| `validateNotEmpty(list<string> $keys): void` | step 2 above |
| `get(string $key, mixed $default = null): mixed` | the value, or `$default` when the key is not set |
| `string(string $key): ?string` | a scalar as a string; `null` when it is not set, not scalar, or `''` |
| `int(string $key, int $default = 0): int` | a numeric value as an integer, else `$default` |
| `float(string $key, float $default = 0.0): float` | a numeric value as a float, else `$default` |
| `bool(string $key): bool` | `true` for `true` and for the strings `1`, `true`, `yes`, `on` (any case, trimmed); `false` otherwise |

## Registry

`Omnimap\Registry` holds the application's providers by name, each built once:

```php
$registry = new Registry(
    [new GoogleFactory($http, $psr18, $cache), new OpenStreetMapFactory($http, $psr18, $cache)],   // iterable, keyed by getName()
    [
        'maps'   => ['factory' => 'openstreetmap', 'options' => ['user_agent' => 'MySite/1.0 (+https://example.org)']],
        'places' => ['factory' => 'google', 'options' => ['server_key' => '...']],   // options default to []
    ],
);
```

| Method | |
|---|---|
| `get(string $name): ProviderInterface` | the provider, built on the first call and kept |
| `has(string $name): bool` | whether a provider of that name is configured |
| `renderer(string $name): MapRendererInterface` | the provider, as a renderer |
| `staticMap(string $name): StaticMapInterface` | the provider, as a static map |
| `geocoder(string $name): GeocoderInterface` | the provider, as a geocoder |
| `tiles(string $name): TileSourceInterface` | the provider, as a tile source |
| `options(string $name): array` | the options it is configured with (`[]` for an unknown name) |
| `names(): list<string>` | the configured names, in the configuration's order |
| `factories(): list<string>` | the factories installed: what `factory` may name |

An unknown name (`No "x" map provider; configured: maps, places.`) and a factory that is not
installed (`No "google" factory for the "places" map provider; installed: openstreetmap.`) are
`InvalidConfigException`s. A typed getter on a provider that does not implement the interface is a
`NotSupportedException` (`The "vector" provider (Omnimap\MapLibre\MapLibreProvider) does not
make a map image.`); it does not look at `capabilities()`.

## The contracts

### MapRendererInterface

The map in the browser. `describe(MapView $view): array` returns plain, JSON-ready data that the
page's script draws with the provider's driver: no code, the same for a Twig page, a Laravel view
or a React component. Every description holds the envelope `schema/map-description.schema.json`
defines - `provider`, `consent`, `view`, `attribution`, and optionally `library` and `options` -
with the provider's own keys beside it ([A live map](usage-rendering.md#the-description)).

`fingerprint(MapView $view): string` is the text everything a picture of the view depends on is
written in: two views with the same fingerprint must look the same. The map cache names a
picture by the fingerprint, signed ([The map cache](cache.md#a-name-per-view)). Each provider
puts in it its name, the view's centre, zoom and markers, and what else changes its drawing
(the controls, the tile URL, the style, the view's options of its own).

`attribution(): string` is the attribution the map must show, as HTML: printed beside a kept
picture. It may be empty when the live map shows its own (Google).

### StaticMapInterface

`image(MapView $view, Size $size, int $scale = 1): Image` - a map image made on the server: a
provider's static map service (Google's Static Maps) or its tiles put together
(`Static\TileStitcher`). A `$scale` of 2 gives an image of twice the density, `$size` x 2 pixels.
The image is no larger than asked: a provider with a limit (Google: 640 pixels a side at scale 1)
answers smaller, `Image::$size` says how large.

### GeocoderInterface

`geocode(string $address, ?string $locale = null, int $limit = 5): list<Place>` - places for an
address, the best first, at most `$limit`; nothing found is an empty list, not an exception.
`reverse(LatLng $position, ?string $locale = null, int $limit = 5): list<Place>` - addresses at a
point. `$locale` is the language of the answer (`en`, `ja`), the provider's default when `null`.
Both go through geocoder-php ([Geocoding](usage-geocoding.md)).

### TileSourceInterface

Square tiles of the Web Mercator grid, one URL each: what Leaflet draws and what `TileStitcher`
puts together.

| Method | |
|---|---|
| `template(): string` | the URL template, `{z}`, `{x}` and `{y}` in it (and `{s}` for a server's subdomains): the browser's library fills it |
| `url(int $zoom, int $x, int $y): string` | one tile's complete URL |
| `tileSize(): int` | in pixels: 256 nearly always (the conformance suite accepts 256 and 512) |
| `maxZoom(): int` | the deepest zoom served, 1 to 24 |
| `attribution(): string` | as HTML |
| `headers(): array<string, string>` | the headers a request for a tile must carry (a User-Agent naming the application); may throw when they cannot be given |

## Models

All models are `final readonly` classes in `Omnimap\Model`. Coordinates are degrees (WGS 84),
sizes and positions pixels, zooms the Web Mercator zoom every provider shares (0 is the whole
world in one 256-pixel tile). Every model but `Point` and `Image` implements `JsonSerializable`
with the same array as its `toArray()`.

### LatLng

A point on Earth.

| Property | Type | |
|---|---|---|
| `lat` | `float` | -90 to 90: anything else, or a value that is not finite, throws `InvalidArgumentException` |
| `lng` | `float` | any finite value: 180.5 and -179.5 are the same meridian |

| Method | |
|---|---|
| `LatLng::fromArray(['lat' => ..., 'lng' => ...])` | from an array; numeric strings accepted |
| `wrapped(): LatLng` | the same point, its longitude between -180 (included) and 180 (excluded) |
| `equals(LatLng $other, float $tolerance = 1e-9): bool` | both coordinates within the tolerance |
| `toArray(): array{lat, lng}` | |
| `__toString()` | `"lat,lng"` with at most 7 decimals and no trailing zeros (`35.0116,135.7681`), as URLs and geocoders write a point |

> [!WARNING]
> Known defect: a `LatLng` keeps a longitude outside -180 to 180 as it is, and `MapView::toArray()`
> (hence every description) carries it unwrapped, where `schema/map-description.schema.json`
> requires a longitude between -180 and 180: a view centred at `new LatLng(10, 190)` gives a
> description the schema refuses. Call `wrapped()` on a longitude that may have gone round the
> world.

### Bounds

A box on the map, from its south-west to its north-east corner. A box across the antimeridian has
a western longitude greater than its eastern one.

| Property / method | |
|---|---|
| `southWest`, `northEast` | `LatLng` |
| `contains(LatLng $point): bool` | latitude between the corners; longitude compared wrapped, across the antimeridian too |
| `toArray()` | `['south' => float, 'west' => float, 'north' => float, 'east' => float]` |

### Size and Point

`Size(int $width, int $height)`: pixels, at least 1 each way (`InvalidArgumentException`
otherwise). `Size::fromString('640x480')` reads `WIDTHxHEIGHT`, `scaled(int $scale)` multiplies
both sides, `(string)` writes `640x480`, `jsonSerialize()` gives `['width' => ..., 'height' =>
...]`.

`Point(float $x, float $y)`: a position in pixels, on the whole world at a zoom
(`Projection\WebMercator`) or on an image.

### Marker

A pin on the map.

| Property | Type | Default | |
|---|---|---|---|
| `position` | `LatLng` | - | |
| `title` | `?string` | `null` | shown on hover by the drivers that can (Leaflet's tooltip, Google's title, MapLibre's label) |
| `color` | `?string` | `null` | a CSS hex colour (`#d33b3b`): the dot's colour on an image made of tiles, Leaflet's and MapLibre's marker colour, Google's Static Maps marker colour; the Google live driver does not use it |

`toArray()` gives `['position' => [...], 'title' => ..., 'color' => ...]`.

### MapView

What a map shows, whoever draws it.

| Property | Type | Default | |
|---|---|---|---|
| `center` | `LatLng` | - | |
| `zoom` | `int` | - | 0 to 24 (`InvalidArgumentException` otherwise) |
| `markers` | `list<Marker>` | `[]` | |
| `controls` | `bool` | `true` | whether the provider's own controls are shown (Google's default UI, Leaflet's zoom control, MapLibre's navigation control and its interactivity) |
| `options` | `array<string, array>` | `[]` | the options only one provider understands, under its name: `['google' => ['styles' => [...]]]`; another provider ignores them |
| `id` | `string` | `'map'` | the page element the map is drawn into |

| Method | |
|---|---|
| `withMarker(Marker $marker): MapView` | a copy with one more marker |
| `withMarkers(list<Marker> $markers): MapView` | a copy with these markers instead |
| `withOptions(string $provider, array $options): MapView` | a copy with `$options` merged over that provider's options already set (`array_replace`) |
| `options(string $provider): array` | one provider's options (`[]` when none) |
| `toArray(): array` | `['id', 'center', 'zoom', 'markers', 'controls']`: the `view` of every description; `options` are not in it, each provider puts what it reads of them in its description's `options` |

### Place and Address

A place a geocoder found.

| Property | Type | |
|---|---|---|
| `position` | `LatLng` | |
| `label` | `string` | the provider's one-line name of the place (Google's formatted address, Nominatim's display name), else the address's parts joined: `street number + street, postcode + locality, region, country` |
| `providedBy` | `string` | geocoder-php's provider name (`google_maps`, `nominatim`): a service's terms often ask that its answers be shown with its name, and some forbid showing them on another provider's map |
| `address` | `?Address` | |
| `bounds` | `?Bounds` | the box the place covers, when the provider gives one |
| `id` | `?string` | the provider's id of the place: Google's `place_id`; OpenStreetMap's object as `type/id` (`relation/357794`) |

`Address` holds what a geocoder says of the address, each part `?string`, `null` when unknown:
`streetNumber`, `streetName`, `postalCode`, `locality`, `subLocality`, `region` (the first
administrative level), `country` (its name), `countryCode`.

### Image

`Image(string $bytes, string $mimeType, Size $size)`: an image a provider made, its media type as
the bytes are (`image/png`) and its size in pixels.

## The projection

`Omnimap\Projection\WebMercator` is EPSG:3857, the projection of Google's, OpenStreetMap's and
nearly every web map's tiles: at zoom z the world is a square of 256 x 2^z pixels, x growing
eastward from the antimeridian, y southward from latitude 85.0511 (the square's edge).

| Member | |
|---|---|
| `TILE` | 256 |
| `MAX_LATITUDE` | 85.0511287798066: latitudes beyond are drawn on the edge |
| `worldSize(float $zoom): float` | the world's width at a zoom, in pixels |
| `point(LatLng $latLng, float $zoom): Point` | where a point lies on the world, in pixels |
| `latLng(Point $point, float $zoom): LatLng` | the point at a position (its longitude not wrapped) |
| `bounds(LatLng $center, int $zoom, Size $size): Bounds` | the box an image of that size shows around that centre |

The tile holding a point at zoom z is `floor(point->x / 256)`, `floor(point->y / 256)` ([Tiles](usage-tiles.md)).

## The signer

`Omnimap\Signer(?string $secret)` signs with HMAC-SHA1, the secret given in URL-safe base64, the
signature in URL-safe base64: Google's rule for signing a Maps URL, and the rule
`glitchr/ux-google` named its captured maps by. An empty or `null` secret signs with an empty key,
as ux-google did.

| Method | |
|---|---|
| `sign(string $payload): string` | the signature without base64's `=` padding: the map cache's names (27 characters) |
| `signPadded(string $payload): string` | with its padding: what Google expects in a URL's `signature=` |
| `verify(string $payload, string $signature): bool` | constant-time comparison, padding ignored |
| `Signer::encode(string $bytes)`, `Signer::decode(string $text)` | URL-safe base64 (`-` and `_` for `+` and `/`) |

## The map cache, in short

`Snapshot\Snapshots` names a view (`signature()`), finds its picture, makes it with a
`StaticMapInterface` or keeps one the browser captured; `Snapshot\SnapshotStore` cuts and keeps
pictures in a `Snapshot\Storage`; `Snapshot\Snapshot` describes one kept picture;
`MapHtml` and `MapPrinter` print the page's element. All of it is in [The map cache](cache.md).
