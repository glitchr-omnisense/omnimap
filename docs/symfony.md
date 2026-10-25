---
title: Symfony
order: 15
---

## Symfony

The core holds nothing of a framework. In a Symfony application, omnibridge/symfony wires it:
`Omnibridge\Symfony\Map\DependencyInjection\OmnimapExtension`, registered by
`Omnibridge\Symfony\OmnibridgeBundle` when `omniscient/omnimap` is installed, builds the providers,
the registry, the map cache and the printer from `config/packages/omnimap.yaml`; omnibridge/twig's
`Omnibridge\Twig\MapExtension` gives Twig `omnimap()`; a route loader and a controller serve the
cache. Without an `omnimap.yaml`, the extension loads nothing.

```sh
composer require omnibridge/symfony omniscient/omnimap omnimap/openstreetmap
```

with `Omnibridge\Symfony\OmnibridgeBundle` in `config/bundles.php`. The Twig functions need
`omnibridge/twig` and TwigBundle; `cache.drive` needs `omniscient/omnidrive` and the bridge's
`Omnidrive\Drives` service; geocoding needs a PSR-17 factory (`nyholm/psr7` or
`php-http/discovery`) for the PSR-18 client.

### omnimap.yaml

```yaml
# config/packages/omnimap.yaml
omnimap:
    provider: openstreetmap            # the page's map: a key of providers
    secret: '%env(MAP_SECRET)%'        # signs the pictures' names
    providers:
        openstreetmap: { user_agent: 'MySite/1.0 (+https://example.org)' }
        google: { api_key: '%env(GOOGLE_MAPS_CLIENT_KEY)%', secret: '%env(GOOGLE_MAPS_SECRET)%' }
    cache:
        drive: local.uploads           # an omnidrive drive (Omnidrive\Drives); or storage: an Omnimap\Snapshot\Storage service
        public_directory: '%kernel.project_dir%/public'   # tiles served are written there too
    routes:
        tile: { path: '/omnimap/{signature}/{id}.webp', name: omnimap_tile }
```

```yaml
# config/routes/omnimap.yaml
omnimap: { resource: ., type: omnimap }
```

The whole tree, from `Omnibridge\Symfony\Map\DependencyInjection\Configuration`:

| Key | Type | Default | |
|---|---|---|---|
| `provider` | string | required, not empty | the provider of the page's map: a key of `providers` |
| `geocoder` | string or null | `null` | the provider `Omnimap\GeocoderInterface` is (a key of `providers`); the page's provider when `null` |
| `secret` | string | `''` | signs the pictures' names (URL-safe base64; Google's URL signing secret fits) |
| `providers` | map of name => options | `{}` | each provider's options, as its factory reads them ([Configuration](configuration.md)); a `factory` key names the factory when the name is not one (`places: { factory: google, ... }`) |
| `cache_only` | bool | `true` | a visitor never gets the live map: the picture kept, or an empty frame |
| `control` | string | `ROLE_ADMIN` | the attribute (`Access::isGranted()`) of whoever may capture and delete pictures, and sees the live map while there is none |
| `cache.drive` | string or null | `null` | the omnidrive drive the cache is kept on (`Omnidrive\Drives`: a storage's name, `local.uploads`) |
| `cache.storage` | string or null | `null` | a service implementing `Omnimap\Snapshot\Storage`, when the cache is on no omnidrive drive; `drive` or `storage` is required |
| `cache.directory` | string | `omnimap` | the folder of the pictures in the storage |
| `cache.pattern` | string | `{signature}/image-{id}` | a tile's path in it |
| `cache.format` | `png`, `jpeg`, `jpg`, `webp` | `png` | the tiles' format as kept |
| `cache.tilesize` | int | `512` | the tiles' size; 0 or less keeps pictures whole |
| `cache.make` | `auto`, `true`, `false` | `auto` | whether the server makes a missing picture itself: when the page's provider makes images (`auto`), always (`true`: a provider that does not implement `StaticMapInterface` is a `NotSupportedException` when the cache is built), never (`false`: a host that reads another host's cache) |
| `cache.size` | string | `1512x938` | the size of a picture the server makes (`WIDTHxHEIGHT`) |
| `cache.scale` | int, 1 or 2 | `1` | its density |
| `cache.pool` | string | `cache.app` | the PSR-6 pool, seen as PSR-16 (`omnimap.cache`), that keeps fetched tiles and geocoders' answers |
| `cache.max_age` | int | `86400` | the HTTP lifetime of a tile served by the route |
| `cache.public_directory` | string or null | `null` | where a tile served is also written, at the route's own path (the web server then serves it without PHP): the public folder |
| `cache.public_size` | int or null | `null` | the largest side of a tile served as WebP, in pixels (none: as kept) |
| `html.class` | string | `omnimap-snapshot` | the frame's class ([The markup](cache.md#the-markup-maphtml)) |
| `html.suffix` | string | `_snapshot` | the frame's id suffix |
| `html.missing` | string | `''` | the frame's `data-missing` (read by nothing today) |
| `routes.tile` | `{path, name}` | `/omnimap/{signature}/{id}.webp`, `omnimap_tile` | a tile (also the `src` the frame carries) |
| `routes.metadata` | `{path, name}` | `/omnimap/{signature}`, `omnimap_metadata` | a picture's metadata |
| `routes.export` | `{path, name}` | `/omnimap/{signature}/export`, `omnimap_export` | the capture's post |
| `routes.suppress` | `{path, name}` | `/omnimap/{signature}/suppress`, `omnimap_suppress` | the deletion's post |

lesringostars keeps glitchr/ux-google's names: `cache.directory: google`, `html: { class:
google-tilemap, suffix: _html2canvas }`, `routes.tile: { path: '/google/{signature}/{id}.webp' }`.

### Services

| Service id | Class | |
|---|---|---|
| `Omnimap\Google\GoogleFactory`, `Omnimap\OpenStreetMap\OpenStreetMapFactory` | the factories | registered when their package is installed, over `http_client`, `omnimap.psr18` and `omnimap.cache`, tagged `omnimap.provider_factory` |
| `Omnimap\Registry` (public) | `Registry` | every service tagged `omnimap.provider_factory`, and `providers` |
| `Omnimap\MapRendererInterface` | the page's provider | `Registry::renderer(provider)` |
| `Omnimap\GeocoderInterface` | the geocoder | `Registry::geocoder(geocoder ?? provider)` |
| `Omnimap\Snapshot\SnapshotStore` | the store | on `cache.storage`, or a `DriveStorage` of `Drives::get(cache.drive)` |
| `Omnimap\Snapshot\Snapshots` (public) | the cache | the page's renderer, `Signer(secret)`, the store, the static map by `cache.make`, `cache.size`, `cache.scale`, `cache.tilesize` |
| `Omnimap\MapHtml` | the markup | `html` and the tile route's path as `src` |
| `Omnimap\MapPrinter` (public) | the page's rule | `cache_only`, errors logged as warnings on `logger` (`omnimap: the map's picture could not be made: ...`) |
| `Omnibridge\Twig\MapExtension` | Twig | when omnibridge/twig and TwigBundle are there, tagged `twig.extension` |
| `Omnibridge\Symfony\Map\SnapshotController` (public) | the routes' controller | |
| `Omnibridge\Symfony\Map\MapRouteLoader` | route loader of type `omnimap` | |
| `omnimap.cache` | `Psr16Cache` over `cache.pool` | |
| `omnimap.psr18` | `Psr18Client` over `http_client` | when a PSR-17 factory is installed; otherwise no provider geocodes |

All are autowirable by their class: `public function __construct(private readonly Registry $maps, private readonly GeocoderInterface $geocoder) {}`.

> [!WARNING]
> Known defect: the extension registers `GoogleFactory` and `OpenStreetMapFactory` only, where
> `omnimap/maplibre` is expected among the providers it wires: with only omnimap/maplibre
> installed, `provider: maplibre` is `No "maplibre" factory ... installed: ...`. Register its
> factory yourself, with the tag the registry collects:
>
> ```yaml
> # config/services.yaml
> services:
>     Omnimap\MapLibre\MapLibreFactory:
>         arguments: ['@http_client']
>         tags: [omnimap.provider_factory]
> ```

### Twig functions

| Function | |
|---|---|
| `omnimap(view)` | `MapPrinter::map(view, mayChange)`: the picture kept (made now when the server can), else the empty frame for a visitor (`cache_only`), else the live map; `''` for a `null` view |
| `omnimap_export(view, {text: 'O', class: ...})` | for whoever has `control`: the button that sends the live map's picture to `routes.export`, with a token of intent `omnimap-export`; `''` for anyone else |
| `omnimap_suppress(view, {text: 'X', ...})` | for whoever has `control`, when a picture is kept: the button that deletes it (`routes.suppress`, intent `omnimap-suppress`) |
| `omnimap_signature(view)` | the view's signature |

The buttons' `text` (default `O` and `X`) is printed as it is; every other key is an attribute of
the button. The `view` is a `MapView` the controller passes to the template:

```php
return $this->render('store/show.html.twig', [
    'map' => new MapView(new LatLng($store->lat, $store->lng), 15, [new Marker(new LatLng($store->lat, $store->lng), $store->name)], id: 'store-map'),
]);
```

```twig
<div style="position: relative; height: 400px">{{ omnimap(map) }}</div>
{{ omnimap_export(map, {text: 'Keep this picture', class: 'btn'}) }}
{{ omnimap_suppress(map, {text: 'Delete the picture', class: 'btn'}) }}
```

### Routes and controller

`MapRouteLoader` (type `omnimap`) gives four routes, the signature matching `[A-Za-z0-9_-]{1,128}`,
the tile first (its path may lie under the metadata's):

| Route | Method | Controller | |
|---|---|---|---|
| `routes.tile` | GET, HEAD | `SnapshotController::tile` | `id` digits, default 0 |
| `routes.export` | POST | `SnapshotController::export` | |
| `routes.suppress` | POST | `SnapshotController::suppress` | |
| `routes.metadata` | GET, HEAD | `SnapshotController::metadata` | |

- **tile**: the tile kept, 404 when there is none (or the signature is not one). When the route's
  path ends in `.webp` and the store's format is not WebP, the tile is converted: with GD built
  with WebP (fitted within `public_size`, quality 75), else Imagick with WebP, else served as kept.
  With a `public_directory`, it is also written there at the route's own path. Public,
  `max-age=max_age`, `must-revalidate`, an ETag (MD5 of the bytes), 304 when it matches.
- **metadata**: the picture's `metadata.txt` as JSON, `{"status": "BAD"}` when there is none.
- **export**: for whoever `Access::isGranted(control)`, with a valid token of intent
  `omnimap-export` (when a `Tokens` service exists): `Snapshots::save(signature, image, tilesize)`
  from the posted `image` (a `data:` URL) and `tilesize` (else `cache.tilesize`), answered with the
  picture's metadata; then the tiles written in the public directory for that signature are
  deleted. No `image`: 400; not an image: 400; no right or a wrong token: 403.
- **suppress**: the same right, intent `omnimap-suppress`: the picture deleted, its public tiles
  too, `{"status": "OK"}`.

The `Access`, `Tokens` and `Urls` services are omnibridge's host contracts
(`Omnibridge\Contract\*`), which omnibridge/symfony implements over the security component, the
CSRF token manager and the router. Without an `Access` service, nobody may change the map.

### Testing a Symfony application

The `MapTest` of omnibridge/symfony is a model: replace `http_client` in the test environment with
a `MockHttpClient` answering the tiles (`Omnimap\Testing\PlainTiles::png()`), point `cache.drive`
or `cache.storage` at a temporary directory, and request the page, the tiles and the routes
([Testing](testing.md)).
