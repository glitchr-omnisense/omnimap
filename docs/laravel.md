---
title: Laravel
order: 16
---

# Laravel

omnibridge/laravel wires omnimap into a Laravel application: `Omnibridge\Laravel\Map\MapServiceProvider`,
registered by `Omnibridge\Laravel\OmnibridgeServiceProvider` when `omnisense/omnimap` is installed,
reads `config/omnimap.php` - the keys of omnibridge/symfony's `omnimap.yaml`, fewer of them - and
binds the registry, the map cache, the printer and omnibridge/twig's `MapExtension`, and routes
the tiles. It routes nothing and gives Twig nothing unless `config/omnimap.php` names a
`provider`.

```sh
composer require omnibridge/laravel omnisense/omnimap omnimap/openstreetmap
```

## config/omnimap.php

```php
// config/omnimap.php
return [
    'provider' => 'openstreetmap',
    'secret' => env('MAP_SECRET'),
    'providers' => [
        'openstreetmap' => ['user_agent' => 'MySite/1.0 (+https://example.org; maps@example.org)'],
        'places' => ['factory' => 'google', 'server_key' => env('GOOGLE_MAPS_SERVER_KEY')],
    ],
    'cache' => ['storage' => storage_path('app/omnimap'), 'directory' => 'omnimap', 'make' => 'auto'],
    'routes' => ['tile' => ['path' => '/omnimap/{signature}/{id}.webp', 'name' => 'omnimap_tile', 'register' => true]],
];
```

Every key, as `MapServiceProvider::config()` and the bindings read them:

| Key | Default | |
|---|---|---|
| `provider` | `''` | the page's provider, a key of `providers`; empty: no route, no Twig function |
| `secret` | `''` | signs the pictures' names |
| `providers` | `[]` | name => the factory's options; a `factory` key names the factory when the name is not one |
| `geocoder` | the page's provider | the provider bound as `Omnimap\GeocoderInterface` |
| `cache.drive` | - | an omnidrive drive of the bound `Omnidrive\Drives` (a storage's name: `local.uploads`) |
| `cache.storage` | `storage_path('app/omnimap')` | when there is no `drive`: the container's name of an `Omnimap\Snapshot\Storage`, else a directory (`DirectoryStorage`) |
| `cache.directory` | `omnimap` | |
| `cache.pattern` | `{signature}/image-{id}` | |
| `cache.format` | `png` | `png`, `jpeg`, `jpg`, `webp` |
| `cache.tilesize` | `512` | 0 or less keeps pictures whole |
| `cache.make` | `auto` | `auto`: the server makes a missing picture when the page's provider declares `staticMap`; `true` or `'true'`: whenever it implements `StaticMapInterface`; `false` or `'false'`: never |
| `cache.size` | `1512x938` | |
| `cache.scale` | `1` | |
| `cache.pool` | - | a store of Laravel's cache (`config/cache.php`): the providers' PSR-16 cache (tiles, geocoders' answers); without it a Symfony `FilesystemAdapter` (namespace `omnimap`) in `storage/framework/cache/omnimap` |
| `cache.max_age` | `86400` | a tile's `max-age` |
| `cache.public_directory` | - | where each tile served is written at the route's path, for the web server to serve next |
| `cache.public_size` | - | a tile served as WebP is fitted within it |
| `cache_only` | `true` | anything but `false` keeps visitors off the live map |
| `control` | `ROLE_ADMIN` | the ability (`Gate`) of whoever may change the map |
| `html.class`, `html.suffix`, `html.missing` | `omnimap-snapshot`, `_snapshot`, `''` | the frame's markup ([The markup](cache.md#the-markup-maphtml)); `src` is the tile route's path |
| `routes.tile` | `/omnimap/{signature}/{id}.webp`, `omnimap_tile`, `register: true` | the tile route; `register: false` for a host whose web server serves the tiles, or that reads another host's cache |
| `routes.metadata`, `routes.export`, `routes.suppress` | `/omnimap/{signature}`, `.../export`, `.../suppress`; `omnimap_metadata`, `omnimap_export`, `omnimap_suppress` | names only: these routes are not registered (below) |

These are omnibridge/laravel 1.x's since 77de4a4; before it `geocoder` and the four last `cache`
keys were read by nothing.

## Bindings

| Binding | |
|---|---|
| `Omnimap\Registry` (singleton) | `GoogleFactory`, `OpenStreetMapFactory` and `MapLibreFactory` when installed (`MapServiceProvider::FACTORIES`), and every binding tagged `omnimap.provider_factory` (`MapServiceProvider::FACTORY_TAG`), each over the HTTP client bound as `omnimap.http_client` (else `HttpClient::create()`), a `Psr18Client` over it when `nyholm/psr7` or `php-http/discovery` is installed (else none: no provider geocodes), and the cache above |
| `Omnimap\Snapshot\Snapshots` (singleton) | the page's renderer, `Signer(secret)`, a `SnapshotStore` on the storage above, the static map by `cache.make` |
| `Omnimap\MapRendererInterface` | the page's provider (`Snapshots::renderer()`) |
| `Omnimap\MapPrinter` (singleton) | `cache_only`; a picture the server could not make logged as a warning when a `LoggerInterface` is bound |
| `Omnibridge\Twig\MapExtension` (singleton) | the printer, the host's `Urls`, `Access` and `Tokens` (omnibridge/laravel's: the router, the `Gate`, the session), `control` and the export and suppress route names |

`Omnimap\GeocoderInterface` is bound to the registry's geocoder named by `geocoder` (the page's
provider by default); another one is the registry's, `app(Registry::class)->geocoder('places')`.

## Routes

Only the tile route is registered: `GET routes.tile.path`, the signature matching
`[A-Za-z0-9_-]{1,128}` and the id digits, served by `Omnibridge\Laravel\Map\TileController` as omnibridge/symfony's controller serves it:
at a path ending in `.webp` the tile as WebP (fitted within `cache.public_size`; GD with WebP, else
Imagick, else as kept, `Content-Type: image/<format>`, `jpg` as `image/jpeg`), written in
`cache.public_directory` when set, public, `max-age` `cache.max_age`, `must-revalidate`, an ETag
(MD5 of the bytes), 304 when it matches, 404 when there is none.

The metadata, the capture and the deletion of a picture are routed by omnibridge/symfony only. A
Laravel host that shows the buttons routes them itself, under the names in `routes.export.name`
and `routes.suppress.name` (the buttons' URLs are built from those names, and building them fails
when no route has the name). Written by hand, with the cache's own methods:

```php
// routes/web.php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Omnibridge\Contract\Tokens;
use Omnibridge\Twig\MapExtension;
use Omnimap\Snapshot\Snapshots;

Route::post('/omnimap/{signature}/export', function (Request $request, Snapshots $snapshots, Tokens $tokens, string $signature) {
    abort_unless(Gate::allows('ROLE_ADMIN') && $tokens->isValid(MapExtension::EXPORT, $request->input('_token')), 403);

    return $snapshots->save($signature, (string) $request->input('image'), (int) $request->input('tilesize') ?: null)->metadata();
})->where('signature', '[A-Za-z0-9_-]{1,128}')->name('omnimap_export');

Route::post('/omnimap/{signature}/suppress', function (Request $request, Snapshots $snapshots, Tokens $tokens, string $signature) {
    abort_unless(Gate::allows('ROLE_ADMIN') && $tokens->isValid(MapExtension::SUPPRESS, $request->input('_token')), 403);
    $snapshots->delete($signature);

    return ['status' => 'OK'];
})->where('signature', '[A-Za-z0-9_-]{1,128}')->name('omnimap_suppress');
```

(not run; a picture that is not an image throws `Omnimap\Exception\InvalidArgumentException`,
which such a route should turn into a 400). The buttons' token is omnibridge/laravel's
`SessionTokens::csrf()`: Laravel's session token, the one its CSRF middleware checks on the `web`
group, the same for every intent - the posts of `capture.js` pass that middleware as they are.

## In a page

With omnibridge/laravel's Twig views, `omnimap()`, `omnimap_export()`, `omnimap_suppress()` and
`omnimap_signature()` are there as under Symfony ([Twig functions](symfony.md#twig-functions)). In
a Blade view, print the printer's markup:

```blade
<div style="position: relative; height: 400px">
    {!! app(\Omnimap\MapPrinter::class)->map($map, Gate::allows('ROLE_ADMIN')) !!}
</div>
```

The browser side is the same as everywhere ([The browser](javascript.md)).

## Tested

omnibridge/laravel's `Tests/MapTest.php` (Orchestra Testbench): a page's Twig has `omnimap()`; the
picture is made by the server from OpenStreetMap's tiles (answered by a `MockHttpClient` bound as
`omnimap.http_client`) and kept on the omnidrive drive `local.uploads` (a local disk); its tiles
are served by the route (a 404 past the grid); a user allowed `ROLE_ADMIN` by the `Gate` gets the
export button. This page was written from that code and that test, not run in an application.
