# The browser

Three ES modules, no library, no build step needed (a bundler takes them as they are).

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

## snapshot.js - a kept picture

`autoInit()` lays out every `[data-omnimap="snapshot"]` frame once it is near the screen and
shown, again on resize, and after transparent.js swaps a page in. `initSnapshots(root)`,
`relayout(root)` and `layout(frame)` for a host that drives it itself (React draws its own
canvas from the same data instead).

## capture.js - sending a picture to the cache

`bindCapture(document, { html2canvas, format: 'png', quality: 1, tilesize: 512 })` binds the
buttons `MapHtml::button()` prints. omnimap ships no html2canvas: the host gives its own (1.x
returns a promise; 0.4's `onrendered` is accepted too).
