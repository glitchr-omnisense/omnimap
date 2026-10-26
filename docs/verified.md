---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-25 in the [harness](harness.md), the core and its three providers from their
checkouts, PHP 8.4.26 with GD, PHPUnit 11.5.57: 92 tests, 568 assertions, green (the `live` group
excluded). The core's own tests:

- the models and the projection: a latitude past a pole refused, a longitude wrapped, a box
  across the antimeridian, the slippy-map tile of Kyoto at zoom 12, the world's corners, a point
  there and back, the box of a view (`ModelTest`, `WebMercatorTest`);
- the registry and the configuration: providers by name built once, a required option, values
  as an environment gives them (`RegistryTest`);
- the signer against `hash_hmac()` and against geocoder-php's own implementation of Google's URL
  signing, an empty secret (`SignerTest`);
- the cache: a capture glitchr/ux-google wrote read as it is (its `metadata.txt` byte for byte,
  written back identical); cutting, padding with transparency, deleting, in a directory, in
  Flysystem's memory adapter and on an omnidrive `Drive`; a whole picture; only a signature
  reaching a path; not an image refused (`SnapshotStoreTest`); the cache the same whichever makes
  the picture (`SnapshotsTest`);
- the page: the picture, the live map or an empty frame by `MapPrinter`'s rule, a picture the
  server could not make (`MapPrinterTest`); the markup with a host's own names, no picture yet,
  the live map and the buttons (`MapHtmlTest`);
- images made of tiles: each tile once with the source's headers then from the cache, kept as
  long as max-age plus stale-while-revalidate, twice the density from the next zoom, the world
  repeating across the antimeridian, the markers drawn, an error status (`TileStitcherTest`);
- the geocoder adapter: a place found then kept, the pace between two calls (`GeocoderPhpTest`);
- every installed renderer's description against `schema/map-description.schema.json`
  (opis/json-schema), and what is not a description refused (`MapDescriptionSchemaTest`);
- in plain PHP, a view described, made, cached, printed and geocoded loading nothing but omnimap
  and what it requires, and every class loading with the required packages alone (`BareTest`);
- [Installation](installation.md)'s script, run as it is written (`DocsTest`);
- the conformance suite on `TestTiles` (`TestTilesContractTest`), on omnimap/google,
  omnimap/openstreetmap and omnimap/maplibre ([Testing](testing.md#conformance-suite-each-test)).

The capability table of the [index](index.md#providers) was probed with `capabilities()` on
2026-10-25. Every example of this manual marked "run" was run in the harness on 2026-10-25 and
its output pasted as it came.

## Verified live

- `docker compose run --rm omnimap live`, 2026-10-23: an image of Kyoto made of
  tile.openstreetmap.org's tiles and one Nominatim query, with omnimap/openstreetmap's
  User-Agent.
- omnimap/maplibre's live group, 2026-10-25 (its README): OpenFreeMap's style, its TileJSON, a
  vector tile, a glyph range and the sprite on the declared host; unpkg's MapLibre GL JS 5.24.0
  against its SHA-384 hashes; `assets/maplibre.js` drawn through `omnimap.js` in headless Chrome.
- On lesringostars, 2026-10-23 (the providers' READMEs): the names of the pictures ux-google
  captured kept, their tiles served at the same addresses; with `MAP_PROVIDER=openstreetmap`, the
  page's map made by the server and kept, no request to Google nor to the tile server from the
  visitor's browser (headless Chrome).

## What is not verified

- The core's JavaScript (`omnimap.js`, `snapshot.js`, `capture.js`) and the Google and Leaflet
  drivers have no test of their own; only `maplibre.js` was drawn in a browser.
- Google's live answers (Static Maps, Geocoding, the Maps JavaScript API): no key in the harness.
- Tile servers other than the OpenStreetMap Foundation's, and 512-pixel tile sources (see the
  known defect in [Tiles](usage-tiles.md)).
- The Symfony and Laravel pages of this manual were written from omnibridge's code and its
  `MapTest`s, not run in an application for this manual.
- The scripts that reach a real service (the second script of [Installation](installation.md),
  the Photon example of [Geocoding](usage-geocoding.md)) were not run.

The known defects found while writing this manual are flagged where the behaviour is described:
`staticMap` declared without a key or a User-Agent ([index](index.md#providers)), a longitude
outside -180..180 in a description ([Concepts](concepts.md#latlng)), 512-pixel tiles
([Images](usage-static.md#tiles-put-together-tilestitcher)), `data-missing` read by nothing
([The browser](javascript.md#snapshotjs---a-kept-picture)), omnimap/maplibre not wired by the
bridges ([Symfony](symfony.md#services), [Laravel](laravel.md#bindings)), two exceptions outside
the family ([Errors](errors.md#outside-the-family)).

## Responsibility

omnisense/omnimap and its omnimap/* providers are unofficial clients, affiliated with none of
the services they reach. The MIT licence covers the code only and grants no right to any service
or its data; whoever runs them complies with each service's terms (named in each provider's
README and in `Capabilities::$terms`) and obtains what they require - including whether a
picture of a provider's map may be kept in the map cache.
