# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable version. It requires `omnisense/contracts` `^1.0` with no stability flag, so `composer require omnisense/omnimap` installs it in a project with Composer's default minimum-stability; the 1.x series follows semantic versioning. A tag `vX.Y.Z` is released by `.github/workflows/release.yml` once the package's CI passes on it.

- The README opens on the install line and a first call that needs no key and makes no request (a view
  described for the browser, OpenStreetMap), and says which provider needs a key (Google).

### Changed

- omnisense/omnimap on omnisense/contracts, the kernel of the omni families, with usage policies;
  the public API unchanged. `Config`, `ProviderInterface`, `ProviderFactoryInterface`,
  `ProviderFactory` (`populate()` and `build()` as before), `Registry` (its messages as before),
  `Http\Redaction`, `Testing\RecordedHttpClient` and `RecordedResponse` keep their names and
  their API, each a thin subclass of the kernel's (prefix `omnimap`, omnimap's exceptions);
  `RecordedHttpClient::stream()` now streams. `OmnimapException` extends the kernel's
  `ExceptionInterface`.

### Added

- `Capabilities::policy()`: the provider's `UsagePolicy`, given (`policy:`) or made of `$terms`,
  which stays, deprecated, read from the policy when it is given. `toArray()` unchanged.
- `Registry::guarded($name, $contract)`: the provider behind its guard (`Policy\GuardedRenderer`,
  `GuardedStaticMap`, `GuardedGeocoder`, `GuardedTiles`), each call that reaches a server counted
  against the policy and refused over a limit (`Exception\UsageLimitReachedException`), the call
  never made. `renderer()`, `staticMap()`, `geocoder()`, `tiles()` and `get()` give the provider
  itself as before. `Registry::policy()`, `policies()`, `guard()`, `names($contract)`; `policy`
  and `usage` beside a provider's options; a shared `RateLimiterInterface` as the registry's third
  argument. [docs/policies.md](docs/policies.md).
- The harness installs omnisense/contracts from its checkout (`OMNISENSE_CONTRACTS`) or GitHub,
  runs its tests, and names its entrypoint (the image may be another family's harness tagged).

### Fixed

- `LatLng::wrapped()` keeps a longitude already between -180 and 180 as it is: it shifted every
  one by 180 and back, and `MapView::toArray()` (so each description) gave 136.06687999999997 for
  136.06688 since the longitudes are wrapped there.
- No key in what omnimap throws. `Static\TileStitcher` put the tile's URL and the HTTP client's
  message (the URL again) in its `UnreachableException` and `ProviderException`, a key in their
  query included; `Geocoding\GeocoderPhp` put the PSR-18 client's and geocoder-php's messages,
  which quote the URL called with its key; each wrapped the client's own exception as its previous,
  and every trace kept the arguments of the calls under it (the URL, the headers). The addresses and
  messages now have the key's parameters, the URL's user part and the request's secrets masked as
  `***` (`Http\Redaction`), the previous is a copy of the client's exception (the same class and
  code), and no trace of the chain keeps its arguments.
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
- `MapPrinter::exportButton()`'s docblock says what it does: the button for whoever may change the
  map, a picture kept or not (the next capture replaces it), where it said "when the live map is
  what they see".

### Added

- `Testing\TestTiles` takes a tile size (`tileSize: 512`), `Testing\PlainTiles::answer()` a size
  for its tiles.
- The harness's `js` service: the browser scripts' tests (`Tests/js` of the core and of each
  provider) on node's test runner.
