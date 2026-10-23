# The map cache

A map drawn live costs the visitor a third party's script and tiles - their address sent to
Google or to a tile server, a consent to ask, a quota spent. The cache shows a **picture** of the
map instead: made once, kept in the host's storage, served by the host. It is the cache
glitchr/ux-google kept for lesringostars, made independent of the provider: the same for Google
and OpenStreetMap, only the source of the picture differs.

## A name per view

`Snapshots::signature($view)` = `Signer::sign($renderer->fingerprint($view))`: HMAC-SHA1 of what
the picture depends on, with the host's secret (URL-safe base64, as Google gives its URL signing
secret), in URL-safe base64 without padding - 27 characters. Change the centre, the zoom, the
markers (OpenStreetMap) or the provider, and the name changes: a new picture is needed, the old
one is not served for the new view.

omnimap/google's `legacy_signature` option fingerprints a view as ux-google did (the Map's class
and its `zoom`, `center`, `html2canvas`, `disableDefaultUI` options; markers and styles not
included), so the pictures it captured keep their names.

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

The storage is an interface of four methods (`Snapshot\Storage`: read, write, exists,
deleteDirectory): `DirectoryStorage` (a directory), `FlysystemStorage` (any Flysystem operator:
local, S3...), or the host's own storage contract behind a small adapter.

## Where pictures come from

- **The browser**: whoever may change the map sees the live map and an export button
  (`MapHtml::button('export', ...)`, `assets/capture.js`): html2canvas draws the element, the
  picture is posted as a `data:` URL (`image`, `tilesize`, `_token`) to the host's export route,
  which checks the right and the token and calls `Snapshots::save($signature, $image)`. That is
  how Google's map is cached: Google's Static Maps are limited to 640 pixels a side, and its
  terms limit what may be kept of its content - whoever caches it answers for that.
- **The server**: given a `StaticMapInterface`, `Snapshots::make($view)` (or `findOrMake()`)
  asks the provider for an image of `size` x `scale` and cuts it. omnimap/openstreetmap makes it
  from its tile server's tiles (`Static\TileStitcher`): a 1512 x 938 picture at scale 1 is 35
  tiles, fetched once with the application's User-Agent and kept as long as the server's
  Cache-Control allows.

## Serving

The page prints `MapHtml::snapshot($id, $signature, $snapshot, $attribution)`: a frame with the
picture's size, its grid and the URL template of a tile (`/omnimap/{signature}/{id}.webp` by
default; a host keeps its own, lesringostars keeps `/google/{signature}/{id}.webp`).
`assets/snapshot.js` lays the tiles out to cover the frame and loads each when it is on screen.
The host's route reads `SnapshotStore::tile($signature, $id)` and answers the image (the
bridges convert it to WebP and may write it under the public folder, so that the web server
serves it without PHP the next time). No picture yet: the frame has no tile, nothing is fetched.

## Expiry

None by time: a picture is kept until it is deleted (`Snapshots::delete()`, the suppress button,
or a new capture under the same name, which replaces it). A view that changes gets a new name;
the old picture stays in the storage until deleted. The tiles a server-made picture was made of
are kept in the PSR-16 cache for their own lifetime; the picture itself does not refresh: delete
it to make it again from fresh tiles.
