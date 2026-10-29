# Changelog

## [Unreleased]

### Fixed

- `Static\TileStitcher` reads a 512-pixel tile source (`tileSize()` 512) as such servers number
  their tiles, 2^z a side at zoom `z` (Leaflet's `zoomOffset: -1`): a view at zoom `z` takes the
  tiles of `z - 1`, and at zoom 0 the one tile is drawn at half its size. It asked the tiles of the
  view's zoom on a grid half as wide: the image showed the wrong part of the world.
- `MapView::toArray()`, hence every description, carries the centre's and the markers' longitudes
  wrapped between -180 and 180, as `schema/map-description.schema.json` asks: `new LatLng(10, 190)`
  gave a description the schema refused.
- `assets/snapshot.js` reads `data-missing` (`MapHtml`'s `missing` option, `html.missing` in the
  bridges): the picture at that address is shown over a frame with no picture kept and in place of
  a tile that fails to load. It was printed and read by nothing.
- `Snapshots::make()` without a static map throws `NotSupportedException` (still a
  `\LogicException`), `DirectoryStorage` a path with `..` `Omnimap\Exception\InvalidArgumentException`
  (still an `\InvalidArgumentException`): both were plain SPL exceptions, missed by a
  `catch (OmnimapException)`.
- `MapPrinter::exportButton()` gives no button when a picture is kept, as its docblock says ("when
  the live map is what they see"): it gave one whenever `$mayChange`.

### Added

- `Testing\TestTiles` takes a tile size (`tileSize: 512`), `Testing\PlainTiles::answer()` a size
  for its tiles.
- The harness's `js` service: the browser scripts' tests (`Tests/js` of the core and of each
  provider) on node's test runner.
