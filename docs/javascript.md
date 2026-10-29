---
title: The browser
order: 36
---

# The browser

Three ES modules, no library, no build step needed (a bundler takes them as they are):
`assets/omnimap.js` draws a live map with a provider's driver, `assets/snapshot.js` lays a kept
picture's tiles out, `assets/capture.js` sends a live map's picture to the cache. Each provider
package ships its driver: `omnimap/google`'s `assets/google.js`, `omnimap/openstreetmap`'s
`assets/leaflet.js`, `omnimap/maplibre`'s `assets/maplibre.js`. Installed with Composer, they sit
under `vendor/omnisense/omnimap/assets/` and `vendor/omnimap/<provider>/assets/`: copy them to
the public folder, map them in an import map, or let the application's bundler take them.

```js
import { register, mountAll } from '/assets/omnimap/omnimap.js';
import google from '/assets/omnimap/google.js';
import leaflet from '/assets/omnimap/leaflet.js';
import maplibre from '/assets/omnimap/maplibre.js';
import { autoInit } from '/assets/omnimap/snapshot.js';
import { bindCapture } from '/assets/omnimap/capture.js';

register('google', google);
register('openstreetmap', leaflet);
register('maplibre', maplibre);
mountAll(document, { consent: (feature, load) => window.Consent ? Consent.use(feature, {}, load) : load() });
autoInit();
bindCapture(document, { html2canvas: window.html2canvas });
```

(the paths are where the host serves the files)

## omnimap.js - a live map

```js
import { register, mountAll } from '.../omnimap/assets/omnimap.js';
import google from '.../omnimap/google/assets/google.js';
import leaflet from '.../omnimap/openstreetmap/assets/leaflet.js';

register('google', google);
register('openstreetmap', leaflet);
mountAll(document, { consent: (feature, load) => window.Consent ? Consent.use(feature, {}, load) : load() });
```

`mountAll()` draws every `[data-omnimap="map"]` element (`MapHtml::live()`) from its
`data-omnimap-description`. A driver is `{ draw(element, description, options) }` returning the
map; it gets `loadScript()` and `loadStyle()` in its options. The third party (Google's API,
Leaflet and its tile server) is loaded through `consent(feature, load)`, the feature being the
description's `consent` (`MAP`): with omnibase/consent, `Consent.use('MAP', ...)`.

| Export | |
|---|---|
| `register(provider, driver)` | the driver of a provider's descriptions, by the provider's name (the description's `provider`); a second call replaces the first |
| `draw(element, description, options = {})` | draws one description in an element: a `Promise` of the driver's map |
| `mountAll(root = document, options = {})` | draws every `[data-omnimap="map"]` under `root`, once each: a `Promise` of all |
| `loadScript(src, attributes = {})` | adds `<script async src>` to the head once per `src`; a `Promise` resolved when it has run |
| `loadStyle(href, attributes = {})` | adds `<link rel="stylesheet">` once per `href` |

`draw()`:

- no driver registered for `description.provider`: the promise is rejected
  (`omnimap: no driver registered for "x".`);
- the gate is `options.consent`, called as `consent(description.consent || 'MAP', load)`; without
  one, `load` runs at once. Until the gate calls `load`, nothing is loaded and the promise stays
  pending;
- `load` calls `driver.draw(element, description, {...options, loadScript, loadStyle})`; when the
  map is there, the element dispatches `omnimap:ready` (a `CustomEvent` that bubbles,
  `detail: { map, description }`) and the promise resolves with the map.

`mountAll()` marks each element `data-omnimap-mounted="1"` (a second call skips it), logs an
unreadable `data-omnimap-description` to the console and skips the element, and on a failed draw
logs the error and hides the element (`display: none`).

`loadScript()` sets each attribute whose value is truthy (an `integrity`, a `crossorigin`), gives
the script the nonce of the page's first `script[nonce]` (a Content-Security-Policy with nonces),
and rejects with `omnimap: <src> could not load.` on an error. `loadStyle()` does nothing when a
stylesheet of that `href` is already in the document.

```js
document.addEventListener('omnimap:ready', (event) => {
  const { map, description } = event.detail;   // a google.maps.Map, a Leaflet map, a maplibregl.Map
  console.log(description.provider, 'drawn in', event.target.id);
});
```

## The drivers

A driver is a module whose default export is `{ draw }`; `draw(element, description, options)`
returns the map (or a promise of it). The three shipped:

| | `google.js` | `leaflet.js` | `maplibre.js` |
|---|---|---|---|
| library loaded | Google's dynamic library loader (`importLibrary('maps')`, `importLibrary('marker')`) from `https://maps.googleapis.com/maps/api/js`, with `description.library`'s keys as parameters (camelCase written snake_case) and the page's nonce; reused when `google.maps.importLibrary` exists | `library.style` (with its integrity, `crossorigin="anonymous"` when it has one) and `library.script`, unless `window.L` exists; no `script`: `omnimap: no Leaflet to load.` | `library.style`, then `library.module` (a dynamic `import()`) or `library.script`, unless `window.maplibregl` exists; neither: `omnimap: no MapLibre GL JS to load.` |
| map | `new Map(element, {center, zoom, disableDefaultUI: !controls, ...options.map})` | `L.map(element, {zoomControl: controls, attributionControl: true, ...options.map})`, `setView()`; one `L.tileLayer(tiles.template, {maxZoom, tileSize, attribution})` | `new maplibregl.Map({container, style, center, zoom, attributionControl: {compact: false}, preserveDrawingBuffer, interactive: controls, ...options.map})`; a `NavigationControl` without compass when `controls` |
| markers | `google.maps.Marker` with the position and title (the colour is not used) | `L.circleMarker`, radius 6, white ring of 2, filled with the marker's colour (`#d33b3b` by default), its title as a tooltip | `maplibregl.Marker` in the marker's colour (`#d33b3b` by default), its title as `title` and `aria-label` |
| view options | `styles` (each a `StyledMapType`), `zoomStyles` (the map type by zoom, applied on `idle` and `zoom_changed` when `below` has stops), `map` | `map` | `map` |
| window events | `tilesloaded`, `idle`, `drag`, each time the map fires them | `idle`, once the map is ready | `idle`, the first time the map is idle |

The window events are glitchr/ux-google's: code that waited for Google's `idle` keeps working with
the other drivers.

A driver of one's own:

```js
export async function draw(element, description, { loadScript, loadStyle }) {
  loadStyle(description.library.style);
  await loadScript(description.library.script);
  const map = window.AcmeMaps.create(element, { center: description.view.center, zoom: description.view.zoom });
  description.view.markers.forEach((marker) => map.addPin(marker.position, marker.title));

  return map;
}

export default { draw };
```

(a sketch: `AcmeMaps` stands for the provider's own library)

## snapshot.js - a kept picture

`autoInit()` lays out every `[data-omnimap="snapshot"]` frame once it is near the screen and
shown, again on resize, and after transparent.js swaps a page in. `initSnapshots(root)`,
`relayout(root)` and `layout(frame)` for a host that drives it itself (React draws its own
canvas from the same data instead).

| Export | |
|---|---|
| `autoInit()` | `initSnapshots()` on `load` (at once when the document is complete), `relayout()` on `resize`, `initSnapshots()` again on `transparent:ready` and `transparent:postactive` |
| `initSnapshots(root = document)` | observes each frame not yet laid out; lays it out when it comes within 200 pixels of the screen and is shown (not `display: none`, not `visibility: hidden`, with an offset parent unless fixed); without `IntersectionObserver`, lays every frame out at once |
| `relayout(root = document)` | lays the frames already laid out and shown out again, then `initSnapshots(root)` |
| `layout(frame)` | lays one frame's tiles out, creating them the first time; a frame that is not a `div` throws |
| `COPYRIGHT_MARGIN` | `0.04`: the share of the grid pushed below the frame's bottom edge, as ux-google's `tilemap.js` did |

`layout()` reads the frame's attributes ([The markup](cache.md#the-markup-maphtml)): `data-src`
(URI-decoded), `data-signature`, `data-xtiles`, `data-ytiles`, `width`, `height` and
`data-tilesize` (the larger of width and height when empty: a whole picture). No grid: nothing.
It sets the frame centred and full-size (`object-fit: cover`), fits the picture's real size to
cover the frame (the padding of the last row and column outside), widens the grid by
`COPYRIGHT_MARGIN` and lowers its bottom by as much, then places one absolutely positioned
`<span id="<frame id>_<n>">` per tile with the tile's URL (`{signature}` and `{id}` replaced) in
`data-background-image`. Each tile is loaded only once it is on screen (an
`IntersectionObserver`; all at once without one), then shown as the span's background with a
0.25 s fade (none when the image was already in the browser's cache). The frame is marked
`data-omnimap-initialized="1"`.

`data-missing` (the `missing` option, `html.missing` in the bridges), when not empty, is the
address of a picture shown for what is not there: over a frame with no picture kept (its
background, `cover`, centred) and in place of a tile that fails to load. Empty, a frame with no
picture stays empty and a failed tile transparent.

## capture.js - sending a picture to the cache

`bindCapture(document, { html2canvas, format: 'png', quality: 1, tilesize: 512 })` binds the
buttons `MapHtml::button()` prints. omnimap ships no html2canvas: the host gives its own (1.x
returns a promise; 0.4's `onrendered` is accepted too).

| Option | Default | |
|---|---|---|
| `html2canvas` | `window.html2canvas` | the function that draws an element on a canvas |
| `format` | `png` | the picture's media type, `image/<format>` |
| `quality` | `1` | `canvas.toDataURL()`'s quality (JPEG, WebP) |
| `tilesize` | `512` | sent with the picture: the tile size the server cuts it into |
| `html2canvasOptions` | `{}` | merged over `{ useCORS: true }` |

- `[data-omnimap-export]`: on click, draws the element named by `data-omnimap-target` and posts
  `image` (a `data:` URL), `tilesize` and `_token` (`data-omnimap-token`) to the URL in
  `data-omnimap-export`;
- `[data-omnimap-suppress]`: on click, posts `_token` to the URL in `data-omnimap-suppress`.

Posts are `fetch()` `POST`, `credentials: 'same-origin'`, `application/x-www-form-urlencoded`,
`Accept: application/json`. The button's text turns `#9ce62a99` (green) when the answer is JSON
with `status: "OK"`, grey otherwise (an HTTP error, no html2canvas, no target element). Each button
is bound once (`data-omnimap-bound="1"`). The routes that receive the posts are the host's
([Symfony](symfony.md#routes-and-controller)); the server answers a capture with the picture's
metadata, whose `status` is `OK`.

## Data attributes and events

| Attribute | On | Set by | Read by |
|---|---|---|---|
| `data-omnimap="map"` | live map element | `MapHtml::live()` | `mountAll()` |
| `data-omnimap-description` | live map element | `MapHtml::live()` | `mountAll()` |
| `data-omnimap-mounted` | live map element | `mountAll()` | `mountAll()` |
| `data-omnimap="snapshot"` | picture frame | `MapHtml::snapshot()` | `initSnapshots()`, `relayout()` |
| `data-src`, `data-signature`, `data-tilesize`, `data-xtiles`, `data-ytiles`, `width`, `height` | picture frame | `MapHtml::snapshot()` | `layout()` |
| `data-missing` | picture frame | `MapHtml::snapshot()` | `snapshot.js`: the picture shown for a missing picture or tile |
| `data-omnimap-initialized` | picture frame | `snapshot.js` | `snapshot.js` |
| `data-background-image` | a tile's `span` | `layout()` | removed when the tile loads |
| `data-omnimap-export`, `data-omnimap-suppress`, `data-omnimap-target`, `data-omnimap-token` | button | `MapHtml::button()` | `bindCapture()` |
| `data-omnimap-bound` | button | `bindCapture()` | `bindCapture()` |

| Event | Target | When |
|---|---|---|
| `omnimap:ready` | the map's element (bubbles), `detail: {map, description}` | a driver drew the map |
| `idle` | `window` | Google's `idle` (each time), Leaflet's map ready, MapLibre's first idle |
| `tilesloaded`, `drag` | `window` | Google only, each time the map fires them |
| `load`, `resize`, `transparent:ready`, `transparent:postactive` | `window` | listened to by `autoInit()` |
