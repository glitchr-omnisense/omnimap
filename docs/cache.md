---
title: The map cache
order: 35
---

# The map cache

A map drawn live costs the visitor a third party's script and tiles - their address sent to
Google or to a tile server, a consent to ask, a quota spent. The cache shows a **picture** of the
map instead: made once, kept in the host's storage, served by the host. It is the cache
glitchr/ux-google kept for lesringostars, made independent of the provider: the same for Google,
OpenStreetMap and MapLibre, only the source of the picture differs.

## A whole example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnimap\MapHtml;
use Omnimap\MapPrinter;
use Omnimap\Model\LatLng;
use Omnimap\Model\MapView;
use Omnimap\Signer;
use Omnimap\Snapshot\DirectoryStorage;
use Omnimap\Snapshot\Snapshots;
use Omnimap\Snapshot\SnapshotStore;
use Omnimap\Testing\PlainTiles;
use Omnimap\Testing\RecordedHttpClient;
use Omnimap\Testing\TestTiles;

$renderer = new TestTiles(new RecordedHttpClient(PlainTiles::answer()));   // a renderer that makes no image here
$dir = sys_get_temp_dir().'/maps';
$store = new SnapshotStore(new DirectoryStorage($dir), 'omnimap', '{signature}/image-{id}', 'png');
$snapshots = new Snapshots($renderer, new Signer('c2VjcmV0'), $store);    // no StaticMapInterface: pictures come from the browser
$printer = new MapPrinter($snapshots, new MapHtml(), cacheOnly: true);

$view = new MapView(new LatLng(35.0116, 135.7681), 12, id: 'map');
$signature = $snapshots->signature($view);

echo $printer->map($view), "\n";                     // a visitor, nothing kept yet: an empty frame
echo substr($printer->map($view, mayChange: true), 0, 40), "...\n";      // whoever may change it: the live map
echo $printer->exportButton($view, true, "/omnimap/$signature/export", 'token', 'Save'), "\n";

// What the export route receives from capture.js: a data: URL.
$capture = 'data:image/png;base64,'.base64_encode(PlainTiles::png(12, 0, 0, 1000));
$snapshot = $snapshots->save($signature, $capture, 512);
echo json_encode($snapshot), "\n";
echo implode(' ', array_map('basename', glob("$dir/omnimap/$signature/*"))), "\n";
echo $printer->map($view), "\n";                     // now the picture, for everyone
echo $printer->suppressButton($view, true, "/omnimap/$signature/suppress", 'token', 'Delete'), "\n";

$snapshots->delete($signature);
var_dump($snapshots->find($view));
```

```
<div id="map">
<div class="omnimap-snapshot" id="map_snapshot" data-omnimap="snapshot" data-src="/omnimap/%7Bsignature%7D/%7Bid%7D.webp" data-signature="0xkZGhEUccy1p6qE0cE-IRmuZgg" data-tilesize="" data-xtiles="0" data-ytiles="0" data-missing="" width="0" height="0"></div>
<div class="omnimap-attribution" style="position:absolute;right:0;bottom:0;z-index:1;padding:0 5px;font:11px/1.5 sans-serif;color:#333;background:rgba(255,255,255,.75)">&copy; Test tiles</div>
</div>

<div id="map" data-omnimap="map" data-om...
<button type="button" data-omnimap-export="/omnimap/0xkZGhEUccy1p6qE0cE-IRmuZgg/export" data-omnimap-target="map" data-omnimap-token="token">Save</button>
{"signature":"0xkZGhEUccy1p6qE0cE-IRmuZgg","width":1000,"height":1000,"tilesize":512,"xtiles":2,"ytiles":2}
image-0.png image-1.png image-2.png image-3.png metadata.txt
<div id="map">
<div class="omnimap-snapshot" id="map_snapshot" data-omnimap="snapshot" data-src="/omnimap/%7Bsignature%7D/%7Bid%7D.webp" data-signature="0xkZGhEUccy1p6qE0cE-IRmuZgg" data-tilesize="512" data-xtiles="2" data-ytiles="2" data-missing="" width="1000" height="1000"></div>
<div class="omnimap-attribution" style="position:absolute;right:0;bottom:0;z-index:1;padding:0 5px;font:11px/1.5 sans-serif;color:#333;background:rgba(255,255,255,.75)">&copy; Test tiles</div>
</div>

<button type="button" data-omnimap-suppress="/omnimap/0xkZGhEUccy1p6qE0cE-IRmuZgg/suppress" data-omnimap-target="map" data-omnimap-token="token">Delete</button>
NULL
```

(run on 2026-10-25 in the [harness](harness.md))

## A name per view

`Snapshots::signature($view)` = `Signer::sign($renderer->fingerprint($view))`: HMAC-SHA1 of what
the picture depends on, with the host's secret (URL-safe base64, as Google gives its URL signing
secret), in URL-safe base64 without padding - 27 characters. Change the centre, the zoom, the
markers (all providers, but Google with `legacy_signature`) or the provider, and the name
changes: a new picture is needed, the old one is not served for the new view. What each provider
puts in its fingerprint is listed in [A live map](usage-rendering.md#the-fingerprint).

omnimap/google's `legacy_signature` option fingerprints a view as ux-google did (the Map's class
and its `zoom`, `center`, `html2canvas`, `disableDefaultUI` options; markers and styles not
included), so the pictures it captured keep their names.

A signature is checked before it reaches a storage path: only `[A-Za-z0-9_-]{1,128}` is accepted
(`InvalidArgumentException` otherwise), so no `/` or `..` gets in.

## The layout in the storage

`SnapshotStore($storage, $directory, $pattern, $format)`:

    <directory>/<signature>/image-<n>.<format>     the tiles, n = y * xtiles + x
    <directory>/<signature>/metadata.txt           serialize(['status' => 'OK', 'image_width' => int,
                                                   'image_height' => int, 'image_tilesize' => '512',
                                                   'image_xtiles' => float, 'image_ytiles' => float])

`metadata.txt` keeps ux-google's keys and types (the tile size a string, the counts floats), so
a capture it wrote is read as it is and one omnimap writes is read by ux-google. Tiles are squares
of `tilesize` (512), the last row and column padded with transparency; with a tile size of `null`
the picture is kept whole as tile 0.

| Argument | Default | |
|---|---|---|
| `$storage` | - | a `Snapshot\Storage` |
| `$directory` | `omnimap` | the folder under the storage's root; `''` for the root itself (lesringostars keeps `google`) |
| `$pattern` | `{signature}/image-{id}` | a tile's path under `$directory`, without its extension |
| `$format` | `png` | `png`, `jpeg`, `jpg` or `webp` (else `InvalidArgumentException`): the tiles' encoding (JPEG at quality 92, WebP at 90) and their extension |

| Method | |
|---|---|
| `find(string $signature): ?Snapshot` | the picture kept: its `metadata.txt` read and unserialized (no classes allowed); `null` when it is missing, unreadable or its `status` is not `OK` |
| `tile(string $signature, int $id): ?string` | a tile's bytes in the store's format; `null` when there is none (or `$id` < 0) |
| `save(string $signature, string $image, ?int $tilesize = 512): Snapshot` | cuts an image GD can read into tiles (or keeps it whole with `null` or a size below 1), replacing what was kept under that name; not an image: `InvalidArgumentException` |
| `delete(string $signature): void` | the picture's folder, tiles and metadata |
| `format()`, `tilePath($signature, $id)`, `metadataPath($signature)` | the format, and where a tile and the metadata are |

What `find()` answered is remembered for the store's life (one storage round trip per question:
on S3 each is an HTTP request); `save()` and `delete()` update it.

`Snapshot` is the kept picture: `signature`, `width`, `height` (pixels), `tilesize` (`?int`,
`null` for a whole picture), `xtiles`, `ytiles`; `tiles()` is their product, `metadata()` the
array written to `metadata.txt`, `Snapshot::fromMetadata($signature, $array)` reads one back
(missing counts read as 1, a missing size as 0).

The storage is an interface of four methods (`Snapshot\Storage`: read, write, exists,
deleteDirectory); paths are relative and `/`-separated:

| Storage | Constructor | |
|---|---|---|
| `DirectoryStorage` | `(string $root)` | a directory of the server's disk; directories created `0775`; a path holding `..` is an `\InvalidArgumentException` |
| `FlysystemStorage` | `(League\Flysystem\FilesystemOperator $filesystem)` | any Flysystem 3 storage (local, S3, SFTP...): the operator a host already has; needs `league/flysystem` |
| `DriveStorage` | `(Omnidrive\Drive $drive)` | an omnisense/omnidrive `Drive`: the storage an application names, wherever it is; needs `omnisense/omnidrive` |
| the host's own | implements `Storage` | `read()` returns `null` for a missing file, `deleteDirectory()` does nothing for a missing folder |

## Where pictures come from

- **The browser**: whoever may change the map sees the live map and an export button
  (`MapHtml::button('export', ...)`, `assets/capture.js`): html2canvas draws the element, the
  picture is posted as a `data:` URL (`image`, `tilesize`, `_token`) to the host's export route,
  which checks the right and the token and calls `Snapshots::save($signature, $image)`. That is
  how Google's map is cached: Google's Static Maps are limited to 640 pixels a side, and its
  terms limit what may be kept of its content - whoever caches it answers for that. MapLibre's
  map keeps its drawing buffer so that it can be captured the same way.
- **The server**: given a `StaticMapInterface`, `Snapshots::make($view)` (or `findOrMake()`)
  asks the provider for an image of `size` x `scale` and cuts it. omnimap/openstreetmap makes it
  from its tile server's tiles (`Static\TileStitcher`): a 1512 x 938 picture at scale 1 is 35
  tiles, fetched once with the application's User-Agent and kept as long as the server's
  Cache-Control allows.

`new Snapshots(MapRendererInterface $renderer, Signer $signer, SnapshotStore $store, ?StaticMapInterface $staticMap = null, Size $size = new Size(1512, 938), int $scale = 1, ?int $tilesize = 512)`:

| Method | |
|---|---|
| `signature(MapView $view): string` | the view's name |
| `find(MapView $view): ?Snapshot` | the picture kept, or `null` |
| `canMake(): bool` | whether a static map was given |
| `make(MapView $view): Snapshot` | the picture made now by the server and kept, replacing any; without a static map a `NotSupportedException` (a `\LogicException`) |
| `findOrMake(MapView $view): ?Snapshot` | the picture kept; made now when there is none and the server can make one; else `null` |
| `save(string $signature, string $image, ?int $tilesize = null): Snapshot` | a picture the browser captured - an image's bytes, or a `data:` URL (its base64 after the comma, decoded strictly) - kept under the signature, cut in `$tilesize` (the constructor's when `null`) |
| `delete(string $signature): void` | the picture forgotten |
| `store(): SnapshotStore`, `renderer(): MapRendererInterface` | |

`save()` takes the signature the page posts, not a view: the host's route must check who posts
(the bridges allow `control`, `ROLE_ADMIN`, with a CSRF token), since anyone who can post may
replace the picture of any name.

## The page: MapPrinter

`MapPrinter` decides what a page shows of a view, whatever the host - glitchr/ux-google's rule
(`cache_only`, `cache_control`), the provider aside:

- the picture kept, when there is one - made now by the server when it can (`findOrMake()`);
- else, when `$cacheOnly` (the default) and the viewer may not change the map: the empty frame (no
  picture, no tile fetched, no third party reached) until a picture is captured;
- else the live map.

`new MapPrinter(Snapshots $snapshots, MapHtml $html = new MapHtml(), bool $cacheOnly = true, ?\Closure $onError = null)`:

| Method | |
|---|---|
| `map(MapView $view, bool $mayChange = false): string` | the element, by the rule above |
| `exportButton(MapView $view, bool $mayChange, string $url, string $token, string $contents, array $attributes = []): string` | the export button when `$mayChange` and no picture is kept (the live map is what they see), else `''` |
| `suppressButton(...)` (same arguments) | the delete button when `$mayChange` and a picture is kept, else `''` |
| `signature(MapView $view): string`, `snapshots(): Snapshots` | |

A picture the server could not make (a tile server down, a missing `user_agent`) is handed to
`$onError` (the bridges log it as a warning) and the page shows no picture: `map()` never throws
for it. Whether the viewer may change the map is the host's to say (omnibridge's Twig extension
asks its `Access` for `control`).

## The markup: MapHtml

`new MapHtml(array $options = [])`, options `class` (`omnimap-snapshot`), `suffix` (`_snapshot`),
`src` (`/omnimap/{signature}/{id}.webp`) and `missing` (`''`) - the host's own names where it has
older ones to keep (lesringostars: class `google-tilemap`, suffix `_html2canvas`, src
`/google/{signature}/{id}.webp`).

`snapshot(string $id, string $signature, ?Snapshot $snapshot, string $attribution = '')` prints the
element `$id` holding the frame `$id.$suffix` and, when not empty, the attribution in a small
absolutely positioned band (bottom right). The frame's attributes:

| Attribute | Value |
|---|---|
| `class` | the `class` option |
| `data-omnimap` | `snapshot` |
| `data-src` | the `src` option, `{` and `}` written `%7B` and `%7D` (`snapshot.js` decodes them) |
| `data-signature` | the view's signature |
| `data-tilesize` | the picture's tile size; `''` for a whole picture or none |
| `data-xtiles`, `data-ytiles` | the grid; `0` when there is no picture (nothing is laid out, nothing fetched) |
| `data-missing` | the `missing` option |
| `width`, `height` | the picture's size; `0` when there is none |

`live(string $id, array $description)` prints the live map's element
([A live map](usage-rendering.md#printing-it)). `button(string $action, string $id, string $url,
string $token, string $contents, array $attributes = [])` prints a `type="button"` with
`data-omnimap-export` (or `data-omnimap-suppress` when `$action` is `suppress`) set to `$url`,
`data-omnimap-target` set to the map's element and `data-omnimap-token`, the extra attributes
first. `$contents` is printed as it is: escape it.

The element is laid out by `assets/snapshot.js` and its tiles bound by `assets/capture.js`
([The browser](javascript.md)); give the outer element a size and `position: relative` in CSS so
that the frame and the attribution band sit in it.

## Serving

The page prints `MapHtml::snapshot($id, $signature, $snapshot, $attribution)`: a frame with the
picture's size, its grid and the URL template of a tile (`/omnimap/{signature}/{id}.webp` by
default; a host keeps its own, lesringostars keeps `/google/{signature}/{id}.webp`).
`assets/snapshot.js` lays the tiles out to cover the frame and loads each when it is on screen.
The host's route reads `SnapshotStore::tile($signature, $id)` and answers the image:
omnibridge/symfony converts it to WebP when the route's path ends in `.webp` and may write it
under the public folder, so that the web server serves it without PHP the next time;
omnibridge/laravel serves it as kept, with its own media type ([Symfony](symfony.md),
[Laravel](laravel.md)). No picture yet: the frame has no tile, nothing is fetched.

A route of one's own, in plain PHP:

```php
// GET /omnimap/{signature}/{id}.webp
$bytes = $snapshots->store()->tile($signature, (int) $id);    // InvalidArgumentException for a bad signature
if (null === $bytes) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/'.('jpg' === $snapshots->store()->format() ? 'jpeg' : $snapshots->store()->format()));
header('Cache-Control: public, max-age=86400');
echo $bytes;
```

## Expiry

None by time: a picture is kept until it is deleted (`Snapshots::delete()`, the suppress button,
or a new capture under the same name, which replaces it). A view that changes gets a new name;
the old picture stays in the storage until deleted. The tiles a server-made picture was made of
are kept in the PSR-16 cache for their own lifetime; the picture itself does not refresh: delete
it to make it again from fresh tiles.
